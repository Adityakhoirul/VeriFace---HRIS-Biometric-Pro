# ==============================================================================
# MICROSERVICE PYTHON - FACE RECOGNITION API SERVER (DEEPFACE)
# Arsitektur Kelas Atas: Bebas dlib/C++ compiler, Sangat Stabil di Windows
#
# Mesin pencocokan cepat:
#  - Embedding wajah karyawan disimpan di memori (tidak dihitung ulang tiap scan).
#  - Satu kali deteksi + satu kali embedding per scan (bukan pencarian ulang folder).
#  - Detektor cepat (YuNet) dulu; detektor kuat (retinaface/mtcnn) hanya jika gagal.
#  - Normalisasi cahaya (CLAHE) + embedding cermin untuk toleransi pencahayaan/pose.
#  - Model dipanaskan (warm-up) saat server start sehingga scan pertama langsung cepat.
# ==============================================================================

import os

# Matikan peringatan TensorFlow/oneDNN agar log terminal lebih bersih
os.environ['TF_ENABLE_ONEDNN_OPTS'] = '0'
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '2'
import cv2
import numpy as np
import base64
import time
import uuid
import threading
import importlib.util
from datetime import datetime
from flask import Flask, request, jsonify
from flask_cors import CORS
from deepface import DeepFace

app = Flask(__name__)
CORS(app)

MODEL_NAME = "VGG-Face"
# Ambang jarak kosinus. Bawaan DeepFace untuk VGG-Face = 0.68 (longgar); 0.40 terlalu ketat
# sehingga sering menolak wajah asli. 0.50 = keseimbangan akurasi tinggi & aman.
MATCH_THRESHOLD = float(os.environ.get('FACE_THRESHOLD', '0.50'))
# Jika dua karyawan berbeda sama-sama dekat, tolak sebagai ambigu (hindari salah orang)
AMBIGUITY_MARGIN = float(os.environ.get('FACE_AMBIGUITY_MARGIN', '0.04'))
# Sisi terpanjang maksimum gambar scan (px) agar deteksi lebih cepat
MAX_SCAN_SIDE = 800
# Urutan detektor: cepat dulu, kuat sebagai cadangan
DETECTORS = ['yunet', 'retinaface', 'mtcnn']
YUNET_MODEL = os.path.join(os.path.dirname(__file__), 'models', 'face_detection_yunet_2023mar.onnx')

# Anti-spoofing (liveness) butuh PyTorch; bila tidak terpasang, jangan buang waktu mencobanya
SPOOF_AVAILABLE = importlib.util.find_spec('torch') is not None

# Direktori penyimpanan gambar wajah karyawan terdaftar
DB_DIR = os.path.join(os.path.dirname(__file__), 'face_db')
if not os.path.exists(DB_DIR):
    os.makedirs(DB_DIR)

# Direktori barang bukti audit HRD (Spoofing)
SPOOF_DIR = os.path.join(os.path.dirname(__file__), 'spoof_db')
if not os.path.exists(SPOOF_DIR):
    os.makedirs(SPOOF_DIR)

# Direktori log wajah tidak dikenal (Gagal Dikenali / Unknown)
UNKNOWN_DIR = os.path.join(os.path.dirname(__file__), 'unknown_db')
if not os.path.exists(UNKNOWN_DIR):
    os.makedirs(UNKNOWN_DIR)

IMG_EXT = ('.jpg', '.jpeg', '.png')

# Keras tidak aman multi-thread: serialkan seluruh inferensi
_model_lock = threading.Lock()
_gallery_lock = threading.Lock()
_gallery = {}        # nik -> {'mtime': float, 'embs': np.ndarray (n, d), normalized}
_last_unknown_save = 0.0


_yunet = None
_yunet_lock = threading.Lock()


def _get_yunet():
    """YuNet (OpenCV DNN): detektor wajah ringan ~10-30 ms di CPU. Haar Cascade tidak ada di OpenCV 5."""
    global _yunet
    if _yunet is None and hasattr(cv2, 'FaceDetectorYN') and os.path.isfile(YUNET_MODEL):
        _yunet = cv2.FaceDetectorYN.create(YUNET_MODEL, '', (320, 320), 0.6, 0.3, 5000)
    return _yunet


def yunet_crop(img):
    """Deteksi wajah terbesar dengan YuNet lalu luruskan (eye-aligned) & potong. None bila tak ada wajah."""
    det = _get_yunet()
    if det is None:
        return None
    h, w = img.shape[:2]
    with _yunet_lock:
        det.setInputSize((w, h))
        _, faces = det.detect(img)
    if faces is None or len(faces) == 0:
        return None
    f = max(faces, key=lambda r: r[2] * r[3])
    x, y, fw, fh = f[0:4]
    re_x, re_y, le_x, le_y = f[4], f[5], f[6], f[7]  # mata kanan & kiri (sisi gambar)
    angle = np.degrees(np.arctan2(le_y - re_y, le_x - re_x))
    cx, cy = x + fw / 2.0, y + fh / 2.0
    rot = cv2.getRotationMatrix2D((float(cx), float(cy)), float(angle), 1.0)
    aligned = cv2.warpAffine(img, rot, (w, h), flags=cv2.INTER_LINEAR, borderMode=cv2.BORDER_REPLICATE)
    side = max(fw, fh) * 1.25
    x0, y0 = int(max(0, cx - side / 2)), int(max(0, cy - side / 2))
    x1, y1 = int(min(w, cx + side / 2)), int(min(h, cy + side / 2))
    crop = aligned[y0:y1, x0:x1]
    if crop.size == 0 or min(crop.shape[:2]) < 40:
        return None
    return crop


class SpoofDetected(Exception):
    pass


def decode_base64_image(base64_str):
    """
    Mengonversi string base64 kembali menjadi array gambar OpenCV.
    """
    try:
        if ',' in base64_str:
            base64_str = base64_str.split(',')[1]

        img_data = base64.b64decode(base64_str)
        nparr = np.frombuffer(img_data, np.uint8)
        img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
        return img
    except Exception as e:
        print(f"Gagal decode gambar: {str(e)}")
        return None


def normalize_lighting(img):
    """CLAHE pada kanal luminans: menstabilkan wajah gelap / backlight / terlalu terang."""
    lab = cv2.cvtColor(img, cv2.COLOR_BGR2LAB)
    l, a, b = cv2.split(lab)
    l = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8)).apply(l)
    return cv2.cvtColor(cv2.merge((l, a, b)), cv2.COLOR_LAB2BGR)


def shrink(img, max_side=MAX_SCAN_SIDE):
    h, w = img.shape[:2]
    scale = max_side / float(max(h, w))
    if scale >= 1.0:
        return img
    return cv2.resize(img, (int(w * scale), int(h * scale)), interpolation=cv2.INTER_AREA)


def _unit(v):
    v = np.asarray(v, dtype=np.float32)
    return v / (np.linalg.norm(v) + 1e-10)


def embed_face(img, check_spoof=False):
    """
    Deteksi + embedding SATU wajah (yang terbesar). Mencoba detektor cepat dulu.
    Mengembalikan (embedding_ter-normalisasi, nama_detektor) atau (None, None) bila tak ada wajah.
    Melempar SpoofDetected jika liveness gagal.
    """
    for backend in DETECTORS:
        if backend == 'yunet':
            crop = yunet_crop(img)
            if crop is None:
                continue
            try:
                with _model_lock:
                    results = DeepFace.represent(
                        img_path=crop, model_name=MODEL_NAME, detector_backend='skip',
                        enforce_detection=False, align=False
                    )
                if results:
                    return _unit(results[0]['embedding']), backend
            except Exception as err:
                print(f"Detektor yunet error: {err}")
            continue
        try:
            with _model_lock:
                results = DeepFace.represent(
                    img_path=img, model_name=MODEL_NAME, detector_backend=backend,
                    enforce_detection=True, align=True,
                    anti_spoofing=bool(check_spoof and SPOOF_AVAILABLE)
                )
        except ValueError as err:
            msg = str(err).lower()
            if 'spoof' in msg or 'fake' in msg:
                raise SpoofDetected()
            continue  # wajah tidak terdeteksi oleh detektor ini -> coba berikutnya
        except Exception as err:
            print(f"Detektor {backend} error: {err}")
            continue
        if not results:
            continue
        best = max(results, key=lambda r: r.get('facial_area', {}).get('w', 0) * r.get('facial_area', {}).get('h', 0))
        return _unit(best['embedding']), backend
    return None, None


def gallery_embeddings(img):
    """Beberapa variasi embedding untuk satu foto terdaftar (cahaya dinormalisasi, cermin, mentah)."""
    variants = [normalize_lighting(img), cv2.flip(normalize_lighting(img), 1), img]
    embs = []
    for v in variants:
        e, _ = embed_face(v)
        if e is not None:
            embs.append(e)
    return np.array(embs, dtype=np.float32) if embs else None


def sync_gallery():
    """Sinkronkan embedding di memori dengan isi folder face_db (tambah/ubah/hapus otomatis)."""
    try:
        files = {f: os.path.getmtime(os.path.join(DB_DIR, f))
                 for f in os.listdir(DB_DIR) if f.lower().endswith(IMG_EXT)}
    except OSError:
        return
    with _gallery_lock:
        known = {nik: entry['mtime'] for nik, entry in _gallery.items()}
        current = {os.path.splitext(f)[0]: (f, m) for f, m in files.items()}

        for nik in list(_gallery.keys()):
            if nik not in current:
                del _gallery[nik]
                print(f"[gallery] {nik} dihapus dari memori")

        for nik, (fname, mtime) in current.items():
            if known.get(nik) == mtime:
                continue
            img = cv2.imread(os.path.join(DB_DIR, fname))
            if img is None:
                continue
            embs = gallery_embeddings(img)
            if embs is None:
                print(f"[gallery] wajah tidak terdeteksi pada {fname}, dilewati")
                continue
            _gallery[nik] = {'mtime': mtime, 'embs': embs}
            print(f"[gallery] {nik} dimuat ({len(embs)} embedding)")


def best_matches(query_emb):
    """Jarak kosinus terkecil per NIK, diurutkan menaik: [(nik, jarak), ...]."""
    with _gallery_lock:
        items = list(_gallery.items())
    scored = []
    for nik, entry in items:
        d = 1.0 - entry['embs'] @ query_emb
        scored.append((nik, float(np.min(d))))
    scored.sort(key=lambda x: x[1])
    return scored


def save_evidence(folder, prefix, img):
    ts = datetime.now().strftime("%Y%m%d_%H%M%S")
    path = os.path.join(folder, f"{prefix}_{ts}_{uuid.uuid4().hex[:6]}.jpg")
    cv2.imwrite(path, img)


@app.route('/api/register', methods=['POST'])
def register_face():
    """
    Endpoint pendaftaran wajah karyawan baru.
    Menyimpan foto fisik wajah ke dalam folder face_db dengan nama NIK.jpg
    """
    try:
        data = request.json
        image_base64 = data.get('image')
        nik = data.get('nik')

        if not image_base64 or not nik:
            return jsonify({'success': False, 'message': 'Parameter tidak lengkap.'}), 400

        img = decode_base64_image(image_base64)
        if img is None:
            return jsonify({'success': False, 'message': 'Format gambar tidak valid.'}), 400

        # Validasi bahwa gambar mengandung wajah (dicoba dengan normalisasi cahaya bila perlu)
        try:
            emb, _ = embed_face(img, check_spoof=True)
            if emb is None:
                emb, _ = embed_face(normalize_lighting(img), check_spoof=True)
        except SpoofDetected:
            save_evidence(SPOOF_DIR, 'spoof_reg', img)
            return jsonify({'success': False, 'message': 'Pendaftaran ditolak! Terdeteksi penggunaan foto/layar HP (Spoofing).'}), 403

        if emb is None:
            return jsonify({
                'success': False,
                'message': 'Sistem gagal mendeteksi wajah pada sampel foto. Pastikan pencahayaan cukup (wajah terang), wajah menghadap lurus ke kamera, dan hindari sorotan cahaya kuat dari belakang kepala (backlight).'
            }), 422

        # Simpan file gambar dengan nama NIK untuk database pencocokan wajah
        file_path = os.path.join(DB_DIR, f"{nik}.jpg")
        cv2.imwrite(file_path, img)
        sync_gallery()

        return jsonify({
            'success': True,
            'message': 'Foto sampel biometrik wajah berhasil didaftarkan.',
            'nik': nik
        }), 200

    except Exception as e:
        return jsonify({'success': False, 'message': f"Internal Server Error: {str(e)}"}), 500


@app.route('/api/verify', methods=['POST'])
def verify_face():
    """
    Endpoint verifikasi presensi wajah secara real-time.
    Membandingkan embedding wajah input dengan galeri embedding di memori.
    """
    global _last_unknown_save
    try:
        t0 = time.time()
        data = request.json
        image_base64 = data.get('image')

        if not image_base64:
            return jsonify({'success': False, 'message': 'Sampel wajah absen tidak ditemukan.'}), 400

        img = decode_base64_image(image_base64)
        if img is None:
            return jsonify({'success': False, 'message': 'Gagal memproses gambar absensi.'}), 400

        sync_gallery()
        if not _gallery:
            registered = [f for f in os.listdir(DB_DIR) if f.lower().endswith(IMG_EXT)]
            if not registered:
                return jsonify({'success': False, 'message': 'Belum ada data karyawan terdaftar di sistem.'}), 404
            return jsonify({'success': False, 'message': 'Data wajah terdaftar tidak dapat diproses.'}), 500

        small = shrink(img)
        try:
            # Percobaan 1: gambar apa adanya (cepat, termasuk liveness bila tersedia)
            emb, backend = embed_face(normalize_lighting(small), check_spoof=True)
            # Percobaan 2: tanpa normalisasi (kasus langka CLAHE merusak deteksi)
            if emb is None:
                emb, backend = embed_face(small, check_spoof=True)
        except SpoofDetected:
            save_evidence(SPOOF_DIR, 'spoof_scan', img)
            return jsonify({'success': False, 'message': 'Akses Ditolak! Terdeteksi percobaan Spoofing (foto dari layar/kertas).'}), 403

        if emb is None:
            # Belum ada wajah di frame: bukan kegagalan verifikasi, kiosk cukup mencoba lagi
            return jsonify({'success': False, 'message': 'Wajah belum terdeteksi. Hadapkan wajah ke kamera.'}), 422

        ranked = best_matches(emb)
        best_nik, best_dist = ranked[0]
        second_dist = next((d for n, d in ranked[1:]), None)
        elapsed = (time.time() - t0) * 1000
        print(f"[verify] {best_nik} jarak={best_dist:.3f} ({backend}, {elapsed:.0f} ms)")

        ambiguous = second_dist is not None and (second_dist - best_dist) < AMBIGUITY_MARGIN and best_dist > 0.30
        if best_dist <= MATCH_THRESHOLD and not ambiguous:
            confidence = max(0.0, min(100.0, (1 - best_dist) * 100))
            return jsonify({
                'success': True,
                'message': 'Verifikasi wajah sukses.',
                'nik': best_nik,
                'confidence': f"{confidence:.2f}%"
            }), 200

        # Wajah terdeteksi namun tidak cocok; simpan bukti maksimal sekali per 10 detik
        if time.time() - _last_unknown_save > 10:
            _last_unknown_save = time.time()
            save_evidence(UNKNOWN_DIR, 'unknown_scan', img)

        return jsonify({'success': False, 'message': 'Verifikasi gagal. Wajah tidak cocok dengan karyawan manapun.'}), 401

    except Exception as e:
        return jsonify({'success': False, 'message': f"Internal Server Error: {str(e)}"}), 500


def warm_up():
    """Muat model & embedding karyawan saat start agar scan pertama langsung cepat."""
    t0 = time.time()
    try:
        dummy = np.full((160, 160, 3), 127, dtype=np.uint8)
        with _model_lock:
            try:
                DeepFace.represent(img_path=dummy, model_name=MODEL_NAME, detector_backend='skip', enforce_detection=False)
            except Exception:
                pass
        sync_gallery()
        print(f"[warm-up] selesai dalam {time.time() - t0:.1f} detik, {len(_gallery)} karyawan dimuat. "
              f"Liveness: {'aktif' if SPOOF_AVAILABLE else 'nonaktif (PyTorch belum terpasang)'}")
    except Exception as e:
        print(f"[warm-up] gagal: {e}")


if __name__ == '__main__':
    warm_up()
    # Aktif di port mikroservis AI (default 5050)
    port = int(os.environ.get('FACE_PORT', '5050'))
    app.run(host='0.0.0.0', port=port, debug=True, use_reloader=False, threaded=True)
