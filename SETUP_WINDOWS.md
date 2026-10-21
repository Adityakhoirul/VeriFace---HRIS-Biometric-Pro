# Setup Guide: VeriFace - HRIS Biometric Pro di Windows

## Daftar Isi
1. [Prasyarat Sistem](#prasyarat-sistem)
2. [Instalasi Step-by-Step](#instalasi-step-by-step)
3. [Konfigurasi Sistem](#konfigurasi-sistem)
4. [Menjalankan Aplikasi](#menjalankan-aplikasi)
5. [Troubleshooting](#troubleshooting)

---

## Prasyarat Sistem

### Spesifikasi Hardware Minimum
- **Processor:** Intel Core i5 / AMD Ryzen 5 (atau lebih baik)
- **RAM:** 8 GB minimum (16 GB disarankan untuk performance optimal)
- **Disk Space:** 10 GB untuk system + model weights
- **Webcam:** USB Webcam atau integrated webcam untuk face capture

### Kebutuhan Software
- Windows 10/11 64-bit
- XAMPP 7.4+ (Apache + PHP + MySQL)
- Python 3.8 - 3.11 (3.10 recommended)
- Git (optional, untuk cloning)

---

## Instalasi Step-by-Step

### 1. Instalasi XAMPP

1. **Download XAMPP**
   - Kunjungi https://www.apachefriends.org/
   - Download versi terbaru (PHP 8.0+ recommended)

2. **Install XAMPP**
   - Jalankan installer
   - Pilih komponen: Apache, MySQL, PHP
   - Install ke lokasi default: `C:\xampp`

3. **Verifikasi Instalasi**
   - Buka Command Prompt
   - Jalankan: `xampp-control.exe` dari folder `C:\xampp`
   - Atau cari di Start Menu

### 2. Instalasi Python

1. **Download Python**
   - Kunjungi https://www.python.org/downloads/
   - Download Python 3.10.x (recommended)

2. **Install Python**
   - Jalankan installer
   - **PENTING:** Centang ✓ "Add Python 3.10 to PATH"
   - Pilih "Install Now"

3. **Verifikasi Instalasi**
   ```bash
   python --version
   pip --version
   ```
   Kedua command harus menampilkan versi tanpa error.

### 3. Setup Project Files

1. **Lokasi Project**
   ```
   C:\xampp\htdocs\VeriFace - HRIS Biometric Pro\
   ```
   File-file sudah berada di lokasi ini.

2. **Verifikasi File Utama**
   ```
   ✓ app.py              (Flask API Server)
   ✓ index.html          (Frontend Aplikasi)
   ✓ presensi.html       (Halaman Presensi)
   ✓ absensi_api.php     (API PHP untuk Absensi)
   ✓ payroll_ext.php     (Extension Payroll)
   ✓ face_db/            (Database Wajah)
   ✓ spoof_db/           (Log Spoofing Attempts)
   ✓ unknown_db/         (Log Wajah Tidak Dikenal)
   ```

### 4. Setup Python Virtual Environment

1. **Buka Command Prompt / PowerShell**
   ```bash
   cd "C:\xampp\htdocs\VeriFace - HRIS Biometric Pro"
   ```

2. **Buat Virtual Environment**
   ```bash
   python -m venv .venv
   ```

3. **Aktivasi Virtual Environment**
   
   **Untuk Command Prompt:**
   ```bash
   .venv\Scripts\activate.bat
   ```
   
   **Untuk PowerShell:**
   ```bash
   .venv\Scripts\Activate.ps1
   ```
   
   > Jika mendapat error di PowerShell, jalankan:
   > ```powershell
   > Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
   > ```

4. **Verifikasi Aktivasi**
   - Prompt harus berubah menjadi: `(.venv) C:\xampp\htdocs\...`

### 5. Install Python Dependencies

1. **Upgrade pip, setuptools, wheel**
   ```bash
   python -m pip install --upgrade pip setuptools wheel
   ```

2. **Install Dependencies Utama**
   ```bash
   pip install flask==2.3.3
   pip install flask-cors==4.0.0
   pip install opencv-python==4.8.0.74
   pip install deepface==0.0.83
   pip install tensorflow==2.13.0
   pip install numpy==1.24.3
   ```

   > **Catatan:** Instalasi TensorFlow + DeepFace membutuhkan waktu 10-20 menit tergantung internet.

3. **Verifikasi Instalasi**
   ```bash
   python -c "import deepface; print('DeepFace OK')"
   python -c "import cv2; print('OpenCV OK')"
   python -c "import flask; print('Flask OK')"
   ```

### 6. Download Face Recognition Weights

1. **Jalankan Script Download**
   ```bash
   python download_weights.py
   ```
   
   > Script akan download pre-trained models (~500MB)
   > - VGGFace2 atau FaceNet models
   > - Liveness detection models
   > - Disimpan di cache: `C:\Users\[username]\.deepface\weights\`

2. **Tunggu hingga selesai** (5-15 menit tergantung koneksi)

### 7. Konfigurasi Database MySQL

1. **Buka XAMPP Control Panel**
   - Start Apache
   - Start MySQL

2. **Akses phpMyAdmin**
   ```
   http://localhost/phpmyadmin
   ```

3. **Buat Database**
   - Klik "New"
   - Nama: `hris_biometric`
   - Collation: `utf8mb4_unicode_ci`
   - Klik "Create"

4. **Import Database Schema (jika ada)**
   - Jika ada file `.sql`, import via phpMyAdmin
   - Database siap digunakan

---

## Konfigurasi Sistem

### 1. Konfigurasi Flask API (`app.py`)

Edit bagian konfigurasi di `app.py`:

```python
# Port untuk Flask Server (default: 5000)
FLASK_PORT = 5000

# Mode Debug (False untuk production)
DEBUG_MODE = False

# CORS Settings
CORS_ORIGINS = ['http://localhost:8080', 'http://127.0.0.1']
```

### 2. Konfigurasi Database PHP

Edit `absensi_api.php` dan `payroll_ext.php`:

```php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Kosong untuk XAMPP default
define('DB_NAME', 'hris_biometric');
```

### 3. Konfigurasi Camera/Webcam

Di `index.html`, pastikan permissions:

```html
<!-- Perijinan akses camera -->
<video id="video" autoplay playsinline></video>
```

Browsers modern memerlukan HTTPS atau localhost untuk akses camera.

### 4. Konfigurasi Map Geofencing (Optional)

Di `index.html`, setup lokasi kantor:

```javascript
// Set lokasi kantor (default: Jakarta)
const OFFICE_LOCATION = {
    latitude: -6.2088,
    longitude: 106.8456,
    radius: 100 // meter
};
```

---

## Menjalankan Aplikasi

### Start Lengkap (Recommended)

1. **Buka Command Prompt / PowerShell**

2. **Navigate ke project**
   ```bash
   cd "C:\xampp\htdocs\VeriFace - HRIS Biometric Pro"
   ```

3. **Aktifkan Virtual Environment**
   ```bash
   .venv\Scripts\activate
   ```

4. **Start XAMPP (jika belum)**
   - Buka XAMPP Control Panel
   - Start Apache
   - Start MySQL

5. **Start Flask Server**
   ```bash
   python app.py
   ```
   
   Output yang diharapkan:
   ```
   * Running on http://127.0.0.1:5000
   * WARNING in app.run(), this is a development server. Do not use it in production.
   ```

6. **Buka Aplikasi di Browser**
   ```
   http://localhost/VeriFace%20-%20HRIS%20Biometric%20Pro/
   ```
   atau
   ```
   http://127.0.0.1/VeriFace%20-%20HRIS%20Biometric%20Pro/
   ```

### Akses Web Interface

| Halaman | URL |
|---------|-----|
| Dashboard Utama | `http://localhost/VeriFace - HRIS Biometric Pro/index.html` |
| Presensi/Absensi | `http://localhost/VeriFace - HRIS Biometric Pro/presensi.html` |
| phpMyAdmin | `http://localhost/phpmyadmin` |

---

## Troubleshooting

### Error: "ModuleNotFoundError: No module named 'flask'"

**Solusi:**
1. Verifikasi virtual environment aktif (prompt menunjukkan `(.venv)`)
2. Reinstall dependencies:
   ```bash
   pip install -r requirements.txt
   ```

### Error: "Cannot connect to camera"

**Solusi:**
1. Periksa permissions sistem (klik Allow saat browser minta akses camera)
2. Coba browser lain (Chrome/Edge recommended)
3. Verifikasi webcam di Device Manager:
   - Win + X → Device Manager
   - Cari "Imaging devices"
   - Webcam harus terdaftar tanpa tanda silang merah

### Error: "TensorFlow tidak bisa load"

**Solusi:**
1. Update TensorFlow:
   ```bash
   pip install --upgrade tensorflow
   ```
2. Install VC++ Redistributable:
   - Download dari: https://support.microsoft.com/en-us/help/2977003
   - Install untuk Windows 10/11

### Error: "Port 5000 sudah digunakan"

**Solusi 1 - Ubah Port:**
Edit `app.py`:
```python
if __name__ == '__main__':
    app.run(debug=True, port=8000)  # Ganti ke port lain
```

**Solusi 2 - Tutup Program yang Menggunakan Port 5000:**
```bash
netstat -ano | findstr :5000
taskkill /PID [PID_NUMBER] /F
```

### Error: "MySQL Connection Refused"

**Solusi:**
1. Verifikasi MySQL aktif di XAMPP Control Panel
2. Restart MySQL:
   - Stop MySQL
   - Tunggu 3 detik
   - Start MySQL
3. Verifikasi credentials di `absensi_api.php`

### Webcam/Kamera Tidak Terdeteksi

**Solusi:**
1. Test webcam di aplikasi default Windows (Camera app)
2. Reinizialize webcam driver:
   - Device Manager → Imaging Devices
   - Klik kanan webcam → Update driver
3. Coba port USB berbeda

### Face Recognition Accuracy Rendah

**Solusi:**
1. Pastikan pencahayaan cukup (minimum 300 lux)
2. Posisikan wajah langsung menghadap kamera
3. Update face database dengan foto berkualitas tinggi
4. Turunkan confidence threshold di `app.py`:
   ```python
   CONFIDENCE_THRESHOLD = 0.5  # Default 0.6
   ```

---

## Operasi Harian

### Backup Data

```bash
# Backup face database
robocopy "C:\xampp\htdocs\VeriFace - HRIS Biometric Pro\face_db" "D:\Backup\face_db" /E

# Backup database MySQL
mysqldump -u root hris_biometric > backup_hris.sql
```

### Monitor System

1. **Check Flask Server Status**
   - Jika crash, restart:
     ```bash
     python app.py
     ```

2. **Check Apache Status**
   - XAMPP Control Panel → Apache Start/Stop

3. **Clear Cache/Logs**
   - Python cache: `del /s __pycache__`
   - Log files: `spoof_db/` dan `unknown_db/`

### Update Models (Monthly)

Untuk mendapatkan akurasi terbaik:

```bash
# Delete old weights
rmdir /s %USERPROFILE%\.deepface\weights

# Download weights terbaru
python download_weights.py
```

---

## Performance Optimization

### Untuk Windows Bespoken

1. **Matikan Windows Update sementara** (production server)
2. **Disable antivirus real-time scanning** untuk folder project
3. **Prioritaskan Python process:**
   - Task Manager → Details
   - Klik kanan `python.exe` → Set Priority → High

### Untuk Database

1. **Optimize MySQL di `my.ini`:**
   ```ini
   [mysqld]
   max_connections = 1000
   max_allowed_packet = 256M
   ```

2. **Tambah index database:**
   ```sql
   CREATE INDEX idx_employee_id ON absensi(employee_id);
   CREATE INDEX idx_datetime ON absensi(datetime);
   ```

### Untuk Flask

1. **Gunakan production server** (bukan dev server):
   ```bash
   pip install gunicorn
   gunicorn --workers 4 --bind 0.0.0.0:5000 app:app
   ```

---

## Kontak & Support

- **Project Folder:** `C:\xampp\htdocs\VeriFace - HRIS Biometric Pro\`
- **Database:** `hris_biometric` (MySQL)
- **Main API:** Flask on http://localhost:5000
- **Frontend:** Apache on http://localhost

---

## Changelog Setup

| Tanggal | Versi | Perubahan |
|---------|-------|----------|
| 2026-10-16 | 1.0 | Initial setup guide untuk Windows |

---

**Last Updated:** 2026-10-16
