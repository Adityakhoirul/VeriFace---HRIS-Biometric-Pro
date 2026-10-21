import os
import sys
import requests

def download_file_with_resume(url, dest_path):
    # Membuat direktori jika belum ada
    os.makedirs(os.path.dirname(dest_path), exist_ok=True)
    
    # Perkiraan ukuran file VGG-Face asli (~580 MB)
    expected_size = 580045448 
    
    headers = {}
    write_mode = 'wb'
    existing_size = 0
    
    if os.path.exists(dest_path):
        existing_size = os.path.getsize(dest_path)
        if existing_size >= expected_size:
            print(f"🎉 File weights sudah terunduh sepenuhnya di: {dest_path}")
            return True
        elif existing_size > 0:
            print(f"🔄 Menemukan file unduhan parsial ({existing_size / (1024*1024):.2f} MB).")
            print("🚀 Melanjutkan unduhan dari posisi terakhir (Auto-Resume)...")
            headers['Range'] = f"bytes={existing_size}-"
            write_mode = 'ab'

    try:
        response = requests.get(url, headers=headers, stream=True, timeout=60)
        
        # HTTP 206 berarti server mendukung resume partial download
        if response.status_code not in [200, 206]:
            print(f"❌ Gagal mengunduh. Server merespon dengan status HTTP: {response.status_code}")
            return False

        total_size = int(response.headers.get('content-length', 0)) + existing_size
        
        with open(dest_path, write_mode) as f:
            downloaded = existing_size
            for chunk in response.iter_content(chunk_size=1024*1024): # Unduh per 1 MB
                if chunk:
                    f.write(chunk)
                    downloaded += len(chunk)
                    percent = (downloaded / total_size) * 100
                    sys.stdout.write(f"\rProgress: {downloaded / (1024*1024):.2f} MB / {total_size / (1024*1024):.2f} MB ({percent:.2f}%)")
                    sys.stdout.flush()
        print("\n✅ Unduhan model AI sukses 100%!")
        return True
    except Exception as e:
        print(f"\n⚠️ Koneksi terputus: {str(e)}")
        print("💡 Jangan khawatir! Cukup jalankan kembali script ini untuk melanjutkan.")
        return False

if __name__ == '__main__':
    url = "https://github.com/serengil/deepface_models/releases/download/v1.0/vgg_face_weights.h5"
    dest_dir = os.path.expanduser("~/.deepface/weights")
    dest_path = os.path.join(dest_dir, "vgg_face_weights.h5")
    
    print("=== DeepFace Weights Smart Downloader ===")
    download_file_with_resume(url, dest_path)