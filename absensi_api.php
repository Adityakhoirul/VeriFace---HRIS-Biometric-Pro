<?php
// ==============================================================================
// PHP NATIVE BACKEND - HRIS CORE API
// Menyimpan Log Absensi, Mengontrol Database, & Integrasi ke Python AI Server
// ==============================================================================

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-HRIS-User, X-HRIS-Role");

require_once __DIR__ . '/payroll_ext.php';

// Penanganan Preflight Request oleh Browser
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ------------------------------------------------------------------------------
// KONFIGURASI DATABASE MYSQL
// ------------------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hris_biometric');

// Versi skema database: naikkan setiap kali menambah tabel/kolom agar migrasi dijalankan ulang
define('SCHEMA_VERSION', '2026.10.19-1');

// Zona waktu aplikasi: PHP & sesi MySQL diseragamkan agar jam absensi, keterlambatan,
// lembur, dan rentang "hari ini" konsisten (XAMPP default PHP = Europe/Berlin)
define('APP_TIMEZONE', 'Asia/Jakarta');
date_default_timezone_set(APP_TIMEZONE);

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->exec("SET time_zone = '" . date('P') . "'");
            // Set error mode ke Exception demi kemudahan debugging saat pengembangan
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Migrasi skema hanya dijalankan bila versi skema berubah (tidak di setiap request)
            if (!$this->schemaUpToDate()) {
                $this->migrateSchema();
            }
        } catch(PDOException $exception) {
            echo json_encode([
                "success" => false, 
                "message" => "Koneksi database gagal: " . $exception->getMessage()
            ]);
            exit();
        }
        return $this->conn;
    }

    /**
     * Cek penanda versi skema agar DDL (CREATE/ALTER) tidak dijalankan di setiap request
     */
    private function schemaUpToDate() {
        try {
            $v = $this->conn->query("SELECT nilai FROM pengaturan_payroll WHERE kunci = '_schema_version'")->fetchColumn();
            return $v === SCHEMA_VERSION;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Buat / migrasi seluruh tabel & kolom, lalu simpan penanda versi skema
     */
    private function migrateSchema() {
        
        // Auto-create tabel users untuk Manajemen Akun & Hak Akses
        $this->conn->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'direktur', 'karyawan') NOT NULL,
            nik VARCHAR(50) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Auto-create tabel karyawan dengan detail gaji terpisah & centralized database fields
        $this->conn->exec("CREATE TABLE IF NOT EXISTS karyawan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nik VARCHAR(50) NOT NULL UNIQUE,
            nama VARCHAR(150) NOT NULL,
            divisi VARCHAR(100) NOT NULL,
            jabatan VARCHAR(100) NOT NULL DEFAULT 'Staff',
            email VARCHAR(100) NULL,
            telepon VARCHAR(20) NULL,
            jenis_kelamin ENUM('Laki-laki', 'Perempuan') NULL,
            tempat_lahir VARCHAR(100) NULL,
            tanggal_lahir DATE NULL,
            alamat TEXT NULL,
            manager_nik VARCHAR(50) NULL,
            status_kerja ENUM('PKWT', 'PKWTT') NOT NULL DEFAULT 'PKWT',
            tanggal_mulai_kerja DATE NULL,
            tanggal_selesai_kontrak DATE NULL,
            tanggal_selesai_probation DATE NULL,
            kontak_darurat_nama VARCHAR(150) NULL,
            kontak_darurat_hubungan VARCHAR(100) NULL,
            kontak_darurat_telepon VARCHAR(20) NULL,
            file_ktp VARCHAR(255) NULL,
            file_npwp VARCHAR(255) NULL,
            file_kontrak VARCHAR(255) NULL,
            gaji_pokok DECIMAL(12,2) NOT NULL DEFAULT 5000000.00,
            tunjangan DECIMAL(12,2) NOT NULL DEFAULT 1000000.00,
            potongan_terlambat DECIMAL(12,2) NOT NULL DEFAULT 50000.00,
            upah_lembur_jam DECIMAL(12,2) NOT NULL DEFAULT 25000.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Cek & Migrasi kolom baru jika tabel karyawan sudah ada di DB lama
        try {
            $this->conn->query("SELECT upah_lembur_jam FROM karyawan LIMIT 1");
        } catch (Exception $migrationEx) {
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN gaji_pokok DECIMAL(12,2) NOT NULL DEFAULT 5000000.00"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN tunjangan DECIMAL(12,2) NOT NULL DEFAULT 1000000.00"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN potongan_terlambat DECIMAL(12,2) NOT NULL DEFAULT 50000.00"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN upah_lembur_jam DECIMAL(12,2) NOT NULL DEFAULT 25000.00"); } catch(Exception $e){}
        }

        // Cek & Migrasi kolom centralized database jika tabel karyawan sudah ada di DB lama
        try {
            $this->conn->query("SELECT status_kerja FROM karyawan LIMIT 1");
        } catch (Exception $migrationEx2) {
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN jabatan VARCHAR(100) NOT NULL DEFAULT 'Staff'"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN email VARCHAR(100) NULL"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN telepon VARCHAR(20) NULL"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN jenis_kelamin ENUM('Laki-laki', 'Perempuan') NULL"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN tempat_lahir VARCHAR(100) NULL"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN tanggal_lahir DATE NULL"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN alamat TEXT NULL"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN manager_nik VARCHAR(50) NULL"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN status_kerja ENUM('PKWT', 'PKWTT') NOT NULL DEFAULT 'PKWT'"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN kontak_darurat_nama VARCHAR(150) NULL"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN kontak_darurat_hubungan VARCHAR(100) NULL"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN kontak_darurat_telepon VARCHAR(20) NULL"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN file_ktp VARCHAR(255) NULL"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN file_npwp VARCHAR(255) NULL"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN file_kontrak VARCHAR(255) NULL"); } catch(Exception $e){}
        }

        // Cek & Migrasi manager_nik secara terpisah
        try {
            $this->conn->query("SELECT manager_nik FROM karyawan LIMIT 1");
        } catch (Exception $e) {
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN manager_nik VARCHAR(50) NULL"); } catch(Exception $ex){}
        }

        // Cek & Migrasi kolom tanggal kontrak / probation secara terpisah
        try {
            $this->conn->query("SELECT tanggal_mulai_kerja FROM karyawan LIMIT 1");
        } catch (Exception $e) {
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN tanggal_mulai_kerja DATE NULL"); } catch(Exception $ex){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN tanggal_selesai_kontrak DATE NULL"); } catch(Exception $ex){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN tanggal_selesai_probation DATE NULL"); } catch(Exception $ex){}
        }

        // Auto-create tabel riwayat_jabatan
        $this->conn->exec("CREATE TABLE IF NOT EXISTS riwayat_jabatan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nik VARCHAR(50) NOT NULL,
            jabatan VARCHAR(100) NOT NULL,
            tanggal_mulai DATE NOT NULL,
            tanggal_selesai DATE NULL,
            keterangan TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Auto-create tabel cuti
        $this->conn->exec("CREATE TABLE IF NOT EXISTS cuti (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nik VARCHAR(50) NOT NULL,
            nama VARCHAR(150) NOT NULL,
            jenis_cuti VARCHAR(50) NOT NULL,
            tanggal_mulai DATE NOT NULL,
            tanggal_selesai DATE NOT NULL,
            alasan TEXT NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Auto-create tabel pengumuman
        $this->conn->exec("CREATE TABLE IF NOT EXISTS pengumuman (
            id INT AUTO_INCREMENT PRIMARY KEY,
            judul VARCHAR(255) NOT NULL,
            konten TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Auto-create tabel penggajian
        $this->conn->exec("CREATE TABLE IF NOT EXISTS penggajian (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nik VARCHAR(50) NOT NULL,
            nama VARCHAR(150) NOT NULL,
            bulan VARCHAR(20) NOT NULL,
            tahun INT NOT NULL,
            gaji_pokok DECIMAL(12,2) NOT NULL,
            tunjangan DECIMAL(12,2) NOT NULL,
            potongan DECIMAL(12,2) NOT NULL,
            potongan_cuti DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            lembur_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            total_jam_lembur DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            total_gaji DECIMAL(12,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Cek & Migrasi kolom baru jika penggajian sudah ada di DB lama
        try {
            $this->conn->query("SELECT potongan_cuti FROM penggajian LIMIT 1");
        } catch (Exception $migrationEx) {
            try { $this->conn->exec("ALTER TABLE penggajian ADD COLUMN potongan_cuti DECIMAL(12,2) NOT NULL DEFAULT 0.00"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE penggajian ADD COLUMN lembur_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE penggajian ADD COLUMN total_jam_lembur DECIMAL(6,2) NOT NULL DEFAULT 0.00"); } catch(Exception $e){}
        }

        // Migrasi komponen payroll lengkap (Uang Makan, Transport, BPJS, Kasbon, Reimbursement)
        try {
            $this->conn->query("SELECT uang_makan_harian FROM karyawan LIMIT 1");
        } catch (Exception $migrationEx) {
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN uang_makan_harian DECIMAL(12,2) NOT NULL DEFAULT 25000.00"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN uang_transport_harian DECIMAL(12,2) NOT NULL DEFAULT 20000.00"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN tunjangan_lain DECIMAL(12,2) NOT NULL DEFAULT 0.00"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN bpjs_tk_program TINYINT NOT NULL DEFAULT 3"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN bpjs_kes_kelas TINYINT NOT NULL DEFAULT 2"); } catch(Exception $e){}
        }

        try {
            $this->conn->query("SELECT nomor_slip FROM penggajian LIMIT 1");
        } catch (Exception $migrationEx) {
            $newCols = [
                "nomor_slip VARCHAR(40) NULL",
                "divisi VARCHAR(100) NULL",
                "jabatan VARCHAR(100) NULL",
                "status_kerja VARCHAR(10) NULL",
                "hari_hadir INT NOT NULL DEFAULT 0",
                "jumlah_terlambat INT NOT NULL DEFAULT 0",
                "hari_cuti_unpaid INT NOT NULL DEFAULT 0",
                "uang_makan DECIMAL(12,2) NOT NULL DEFAULT 0.00",
                "uang_transport DECIMAL(12,2) NOT NULL DEFAULT 0.00",
                "tunjangan_lain DECIMAL(12,2) NOT NULL DEFAULT 0.00",
                "reimbursement DECIMAL(12,2) NOT NULL DEFAULT 0.00",
                "bpjs_tk_program TINYINT NOT NULL DEFAULT 0",
                "bpjs_jht_karyawan DECIMAL(12,2) NOT NULL DEFAULT 0.00",
                "bpjs_jp_karyawan DECIMAL(12,2) NOT NULL DEFAULT 0.00",
                "bpjs_kes_kelas TINYINT NOT NULL DEFAULT 0",
                "bpjs_kes_karyawan DECIMAL(12,2) NOT NULL DEFAULT 0.00",
                "jkp_iuran DECIMAL(12,2) NOT NULL DEFAULT 0.00",
                "potongan_kasbon DECIMAL(12,2) NOT NULL DEFAULT 0.00",
                "bpjs_perusahaan DECIMAL(12,2) NOT NULL DEFAULT 0.00",
                "rincian_perusahaan TEXT NULL",
                "total_pendapatan DECIMAL(12,2) NOT NULL DEFAULT 0.00",
                "total_potongan DECIMAL(12,2) NOT NULL DEFAULT 0.00"
            ];
            foreach ($newCols as $col) {
                try { $this->conn->exec("ALTER TABLE penggajian ADD COLUMN " . $col); } catch(Exception $e){}
            }
        }

        // Migrasi data kepesertaan BPJS per karyawan & rincian BPJS di slip
        try {
            $this->conn->query("SELECT no_kpj FROM karyawan LIMIT 1");
        } catch (Exception $migrationEx) {
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN no_kpj VARCHAR(30) NULL"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN no_jkn VARCHAR(30) NULL"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN bpjs_kes_keluarga_tambahan TINYINT NOT NULL DEFAULT 0"); } catch(Exception $e){}
        }
        try {
            $this->conn->query("SELECT bpjs_kes_keluarga FROM penggajian LIMIT 1");
        } catch (Exception $migrationEx) {
            try { $this->conn->exec("ALTER TABLE penggajian ADD COLUMN bpjs_kes_keluarga DECIMAL(12,2) NOT NULL DEFAULT 0.00"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE penggajian ADD COLUMN no_kpj VARCHAR(30) NULL"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE penggajian ADD COLUMN no_jkn VARCHAR(30) NULL"); } catch(Exception $e){}
        }

        // Migrasi data perpajakan karyawan (PPh 21) & rincian pajak di slip
        try {
            $this->conn->query("SELECT status_ptkp FROM karyawan LIMIT 1");
        } catch (Exception $migrationEx) {
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN status_ptkp VARCHAR(5) NOT NULL DEFAULT 'TK/0'"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE karyawan ADD COLUMN npwp VARCHAR(30) NULL"); } catch(Exception $e){}
        }
        try {
            $this->conn->query("SELECT pph21 FROM penggajian LIMIT 1");
        } catch (Exception $migrationEx) {
            try { $this->conn->exec("ALTER TABLE penggajian ADD COLUMN status_ptkp VARCHAR(5) NULL"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE penggajian ADD COLUMN npwp VARCHAR(30) NULL"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE penggajian ADD COLUMN bruto_pph21 DECIMAL(14,2) NOT NULL DEFAULT 0.00"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE penggajian ADD COLUMN pph21 DECIMAL(14,2) NOT NULL DEFAULT 0.00"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE penggajian ADD COLUMN pph21_ditanggung DECIMAL(14,2) NOT NULL DEFAULT 0.00"); } catch(Exception $e){}
            try { $this->conn->exec("ALTER TABLE penggajian ADD COLUMN rincian_pph21 TEXT NULL"); } catch(Exception $e){}
        }

        // Auto-create tabel pengaturan payroll (key-value, dikelola dari menu Pengaturan Payroll)
        $this->conn->exec("CREATE TABLE IF NOT EXISTS pengaturan_payroll (
            kunci VARCHAR(60) NOT NULL PRIMARY KEY,
            nilai MEDIUMTEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Auto-create tabel kasbon (pinjaman karyawan, dipotong cicilan per bulan)
        $this->conn->exec("CREATE TABLE IF NOT EXISTS kasbon (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nik VARCHAR(50) NOT NULL,
            nama VARCHAR(150) NOT NULL,
            tanggal DATE NOT NULL,
            jumlah DECIMAL(12,2) NOT NULL,
            cicilan_per_bulan DECIMAL(12,2) NOT NULL,
            keterangan TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Riwayat potongan kasbon per periode payroll (agar re-generate tidak memotong dua kali)
        $this->conn->exec("CREATE TABLE IF NOT EXISTS kasbon_potongan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kasbon_id INT NOT NULL,
            bulan VARCHAR(20) NOT NULL,
            tahun INT NOT NULL,
            jumlah DECIMAL(12,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Auto-create tabel reimbursement (dibayarkan bersama gaji periode berjalan)
        $this->conn->exec("CREATE TABLE IF NOT EXISTS reimbursement (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nik VARCHAR(50) NOT NULL,
            nama VARCHAR(150) NOT NULL,
            tanggal DATE NOT NULL,
            kategori VARCHAR(50) NOT NULL,
            jumlah DECIMAL(12,2) NOT NULL,
            keterangan TEXT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            dibayar_bulan VARCHAR(20) NULL,
            dibayar_tahun INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Auto-create tabel log_absensi (riwayat scan wajah datang/pulang)
        $this->conn->exec("CREATE TABLE IF NOT EXISTS log_absensi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nik VARCHAR(50) NOT NULL,
            nama VARCHAR(150) NOT NULL,
            divisi VARCHAR(100) NOT NULL,
            scan_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(50) NOT NULL,
            match_rate VARCHAR(10) NOT NULL,
            overtime_hours DECIMAL(4,2) NOT NULL DEFAULT 0.00,
            KEY idx_scan_time (scan_time),
            KEY idx_nik_log (nik),
            CONSTRAINT fk_log_karyawan FOREIGN KEY (nik) REFERENCES karyawan (nik) ON DELETE CASCADE ON UPDATE CASCADE
        )");

        // Cek & Migrasi kolom baru jika log_absensi sudah ada
        try {
            $this->conn->query("SELECT overtime_hours FROM log_absensi LIMIT 1");
        } catch (Exception $migrationEx) {
            try { $this->conn->exec("ALTER TABLE log_absensi ADD COLUMN overtime_hours DECIMAL(4,2) NOT NULL DEFAULT 0.00"); } catch(Exception $e){}
        }

        // Kolom absen alternatif via foto (metode, foto bukti, status verifikasi admin)
        foreach ([
            "metode VARCHAR(20) NOT NULL DEFAULT 'Wajah'",
            "foto_bukti VARCHAR(255) NULL",
            "verifikasi VARCHAR(20) NOT NULL DEFAULT 'Terverifikasi'"
        ] as $colDef) {
            $colName = strtok($colDef, ' ');
            try { $this->conn->query("SELECT $colName FROM log_absensi LIMIT 1"); }
            catch (Exception $e) { try { $this->conn->exec("ALTER TABLE log_absensi ADD COLUMN $colDef"); } catch (Exception $e2) {} }
        }

        // Auto-create tabel pengaturan
        $this->conn->exec("CREATE TABLE IF NOT EXISTS pengaturan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama_perusahaan VARCHAR(255) NOT NULL,
            alamat_perusahaan TEXT NOT NULL,
            jam_masuk TIME NOT NULL DEFAULT '08:30:00',
            jam_pulang TIME NOT NULL DEFAULT '17:00:00',
            latitude_kantor DOUBLE NOT NULL DEFAULT -6.200000,
            longitude_kantor DOUBLE NOT NULL DEFAULT 106.816666,
            radius_meter DOUBLE NOT NULL DEFAULT 100.0
        )");

        // Inisialisasi data default pengaturan jika tabel masih kosong
        $stmt = $this->conn->query("SELECT COUNT(*) FROM pengaturan");
        if ($stmt->fetchColumn() == 0) {
            $this->conn->exec("INSERT INTO pengaturan (nama_perusahaan, alamat_perusahaan, jam_masuk, jam_pulang, latitude_kantor, longitude_kantor, radius_meter) 
                VALUES ('VeriFace Corp', 'Jl. Jenderal Sudirman Kav. 21, Jakarta Selatan', '08:30:00', '17:00:00', -6.200000, 106.816666, 100.0)");
        }

        // Skema modul payroll lanjutan (shift, libur, lembur, periode, audit, komponen)
        ensurePayrollExtSchema($this->conn);

        $this->conn->prepare("INSERT INTO pengaturan_payroll (kunci, nilai) VALUES ('_schema_version', :v) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)")
                   ->execute([':v' => SCHEMA_VERSION]);
    }
}

// ------------------------------------------------------------------------------
// ROUTER SEDERHANA API NATIVE
// ------------------------------------------------------------------------------
$database = new Database();
$db = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Audit trail otomatis: simpan payload aksi yang berhasil mengubah data
$auditMap = auditedActions();
$auditPayload = isset($auditMap[$action]) ? sanitizeAuditPayload(file_get_contents("php://input")) : null;
if (isset($auditMap[$action])) ob_start();

switch($action) {
    case 'register_karyawan':
        registerKaryawan($db);
        break;
        
    case 'absensi_scan':
        processFaceAttendance($db);
        break;
        
    case 'get_karyawan':
        getKaryawanList($db);
        break;
        
    case 'get_karyawan_detail':
        getKaryawanDetail($db);
        break;
        
    case 'update_karyawan_detail':
        updateKaryawanDetail($db);
        break;
        
    case 'get_contract_alerts':
        getContractAlerts($db);
        break;
        
    case 'get_riwayat_jabatan':
        getRiwayatJabatan($db);
        break;
        
    case 'add_riwayat_jabatan':
        addRiwayatJabatan($db);
        break;
        
    case 'delete_riwayat_jabatan':
        deleteRiwayatJabatan($db);
        break;
        
    case 'absensi_foto':
        processPhotoAttendance($db);
        break;

    case 'review_absensi_foto':
        if (requireRole(['admin', 'direktur'])) reviewPhotoAttendance($db);
        break;

    case 'get_logs':
        getAttendanceLogs($db);
        break;
        
    case 'delete_karyawan':
        deleteKaryawanData($db);
        break;

    case 'login':
        processLogin($db);
        break;
        
    case 'get_users':
        getUsersList($db);
        break;
        
    case 'add_user':
        addUser($db);
        break;
        
    case 'delete_user':
        deleteUser($db);
        break;

    case 'get_announcements':
        getAnnouncements($db);
        break;
        
    case 'add_announcement':
        addAnnouncement($db);
        break;
        
    case 'delete_announcement':
        deleteAnnouncement($db);
        break;
        
    case 'get_leaves':
        getLeaves($db);
        break;
        
    case 'add_leave':
        addLeave($db);
        break;
        
    case 'update_leave_status':
        updateLeaveStatus($db);
        break;
        
    case 'get_payroll':
        getPayroll($db);
        break;
        
    case 'generate_payroll':
        if (requireRole('admin')) generatePayroll($db);
        break;

    case 'get_settings':
        getSettings($db);
        break;
        
    case 'save_settings':
        saveSettings($db);
        break;

    case 'update_karyawan_gaji':
        updateKaryawanGaji($db);
        break;

    case 'get_payroll_settings':
        getPayrollSettings($db);
        break;

    case 'save_payroll_settings':
        savePayrollSettings($db);
        break;

    case 'apply_payroll_defaults':
        applyPayrollDefaults($db);
        break;

    case 'get_pph21_ter':
        $status = in_array($_GET['status'] ?? '', pph21Statuses(), true) ? $_GET['status'] : 'TK/0';
        $kategori = pph21TerKategori($status);
        echo json_encode(["success" => true, "kategori" => $kategori, "tarif" => pph21TerRate($kategori, (double)($_GET['bruto'] ?? 0))]);
        break;

    case 'get_rekap_payroll':
        getRekapPayroll($db);
        break;

    case 'get_kasbon':
        getKasbon($db);
        break;

    case 'add_kasbon':
        addKasbon($db);
        break;

    case 'delete_kasbon':
        deleteKasbon($db);
        break;

    case 'get_reimbursement':
        getReimbursement($db);
        break;

    case 'add_reimbursement':
        addReimbursement($db);
        break;

    case 'update_reimbursement_status':
        updateReimbursementStatus($db);
        break;

    case 'delete_reimbursement':
        deleteReimbursement($db);
        break;

    // --- Modul payroll lanjutan ---
    case 'get_shift':                getShifts($db); break;
    case 'save_shift':               if (requireRole('admin')) saveShift($db); break;
    case 'delete_shift':             if (requireRole('admin')) deleteShift($db); break;
    case 'get_hari_libur':           getHariLibur($db); break;
    case 'save_hari_libur':          if (requireRole('admin')) saveHariLibur($db); break;
    case 'delete_hari_libur':        if (requireRole('admin')) deleteHariLibur($db); break;
    case 'seed_hari_libur':          if (requireRole('admin')) seedHariLibur($db); break;
    case 'get_lembur':               getLembur($db); break;
    case 'sync_lembur':              if (requireRole(['admin', 'direktur'])) syncLembur($db); break;
    case 'save_lembur':              if (requireRole(['admin', 'direktur'])) saveLembur($db); break;
    case 'update_lembur_status':     updateLemburStatus($db); break;
    case 'delete_lembur':            if (requireRole('admin')) deleteLembur($db); break;
    case 'get_saldo_cuti':           getSaldoCuti($db); break;
    case 'get_komponen':             getKomponen($db); break;
    case 'save_komponen':            if (requireRole('admin')) saveKomponen($db); break;
    case 'delete_komponen':          if (requireRole('admin')) deleteKomponen($db); break;
    case 'save_komponen_karyawan':   if (requireRole('admin')) saveKomponenKaryawan($db); break;
    case 'delete_komponen_karyawan': if (requireRole('admin')) deleteKomponenKaryawan($db); break;
    case 'get_payroll_periode':      getPayrollPeriode($db); break;
    case 'update_payroll_status':    updatePayrollStatus($db); break;
    case 'get_bank_transfer':        if (requireRole(['admin', 'direktur'])) getBankTransfer($db); break;
    case 'send_payslip_email':       if (requireRole('admin')) sendPayslipEmail($db); break;
    case 'get_a1':                   getA1($db); break;
    case 'get_audit_log':            if (requireRole(['admin', 'direktur'])) getAuditLog($db); break;

    default:
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Endpoint API tidak ditemukan."]);
        break;
}

if (isset($auditMap[$action])) {
    $auditOutput = ob_get_contents();
    ob_end_flush();
    $auditResult = json_decode($auditOutput, true);
    if (!empty($auditResult['success'])) {
        auditLog($db, $action, $auditMap[$action], $auditPayload['nik'] ?? ($auditPayload['id'] ?? null), null, $auditPayload);
    }
}

// ------------------------------------------------------------------------------
// LOGIKA BISNIS FUNGSI-FUNGSI UTAMA
// ------------------------------------------------------------------------------

/**
 * 1. Pendaftaran Karyawan Baru
 */
function registerKaryawan($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->nik) || empty($data->nama) || empty($data->divisi) || empty($data->image_base64)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Data pendaftaran tidak lengkap."]);
        return;
    }

    try {
        // Cek duplikasi NIK terlebih dahulu
        $checkQuery = "SELECT id FROM karyawan WHERE nik = :nik LIMIT 1";
        $stmtCheck = $db->prepare($checkQuery);
        $stmtCheck->bindParam(':nik', $data->nik);
        $stmtCheck->execute();
        
        if ($stmtCheck->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "NIK sudah terdaftar sebelumnya."]);
            return;
        }

        // --- KIRIM GAMBAR KE PYTHON SERVICE GUNA EKSTRAKSI BIOMETRIK ---
        $python_url = 'http://127.0.0.1:5050/api/register';
        $payload = json_encode([
            'nik' => $data->nik,
            'image' => $data->image_base64
        ]);

        $ch = curl_init($python_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Tambahkan baris ini (60 detik)
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res_data = json_decode($response);

        if ($http_code !== 200 || !$res_data || !isset($res_data->success) || !$res_data->success) {
            http_response_code($http_code ?: 500);
            echo json_encode([
                "success" => false, 
                "message" => "Gagal mengekstrak wajah pada Engine Python: " . ($res_data->message ?? 'Server offline.')
            ]);
            return;
        }

        // --- SIMPAN BIODATA KE DATABASE MYSQL ---
        $jabatan = $data->jabatan ?? 'Staff';
        $status_kerja = $data->status_kerja ?? 'PKWT';
        
        $query = "INSERT INTO karyawan (nik, nama, divisi, jabatan, status_kerja, created_at) VALUES (:nik, :nama, :divisi, :jabatan, :status_kerja, NOW())";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nik', $data->nik);
        $stmt->bindParam(':nama', $data->nama);
        $stmt->bindParam(':divisi', $data->divisi);
        $stmt->bindParam(':jabatan', $jabatan);
        $stmt->bindParam(':status_kerja', $status_kerja);
        
        if ($stmt->execute()) {
            // Terapkan nilai default komponen gaji dari Pengaturan Payroll
            try {
                applyPayrollDefaultsToEmployees($db, ['gaji_pokok', 'tunjangan', 'uang_makan', 'uang_transport', 'tunjangan_lain', 'upah_lembur', 'potongan_terlambat', 'bpjs_tk_program', 'bpjs_kes_kelas', 'status_ptkp'], 'all', $data->nik);
            } catch (Exception $defEx) {
                // Gunakan default kolom database jika gagal
            }

            // Auto-insert riwayat jabatan awal
            try {
                $initialJobQuery = "INSERT INTO riwayat_jabatan (nik, jabatan, tanggal_mulai, keterangan) VALUES (:nik, :jabatan, CURRENT_DATE(), 'Jabatan Awal saat Pendaftaran')";
                $stmtJob = $db->prepare($initialJobQuery);
                $stmtJob->bindParam(':nik', $data->nik);
                $stmtJob->bindParam(':jabatan', $jabatan);
                $stmtJob->execute();
            } catch (Exception $jobEx) {
                // Jangan menggagalkan pendaftaran jika riwayat jabatan awal gagal
            }

            http_response_code(201);
            echo json_encode([
                "success" => true, 
                "message" => "Pendaftaran berhasil. Data tersimpan di DB & representasi wajah berhasil disinkronisasi."
            ]);
        } else {
            throw new Exception("Gagal menyimpan data karyawan di database lokal.");
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan sistem: " . $e->getMessage()]);
    }
}

/**
 * 2. Logika Verifikasi Absensi Wajah
 */
function processFaceAttendance($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->image_base64)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Sampel gambar tidak boleh kosong."]);
        return;
    }

    $latitude = isset($data->latitude) ? (double)$data->latitude : null;
    $longitude = isset($data->longitude) ? (double)$data->longitude : null;

    try {
        if (!enforceGeofence($db, $latitude, $longitude)) return;

        // --- PROSES VERIFIKASI BIOMETRIK PADA ENGINE PYTHON ---
        $python_url = 'http://127.0.0.1:5050/api/verify';
        $payload = json_encode(['image' => $data->image_base64]);

        $ch = curl_init($python_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res_data = json_decode($response);

        if ($http_code === 200 && $res_data && isset($res_data->success) && $res_data->success) {
            $matched_nik = $res_data->nik;
            $confidence = $res_data->confidence;

            recordAttendance($db, $matched_nik, $confidence);
        } else {
            http_response_code($http_code ?: 401);
            echo json_encode([
                "success" => false, 
                "message" => $res_data->message ?? "Gagal verifikasi wajah. Koneksi terputus."
            ]);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Kesalahan Internal: " . $e->getMessage()]);
    }
}

/**
 * Catat absensi datang/pulang untuk NIK tertentu (dipakai verifikasi wajah & absen foto).
 * Mengembalikan true bila tercatat; bila false, respons error sudah dikirim.
 */
function recordAttendance($db, $matched_nik, $confidence, $metode = 'Wajah', $fotoBukti = null, $verifikasi = 'Terverifikasi') {
    // Tarik detail profil karyawan dari MySQL
    $query = "SELECT nama, divisi, shift_id FROM karyawan WHERE nik = :nik LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':nik', $matched_nik);
    $stmt->execute();
    $karyawan = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($karyawan) {
        // Cek status Datang / Pulang berdasarkan riwayat absensi hari ini
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $checkQuery = "SELECT id, status, scan_time FROM log_absensi WHERE nik = :nik AND scan_time BETWEEN :start AND :end ORDER BY id DESC LIMIT 1";
        $stmtCheck = $db->prepare($checkQuery);
        $stmtCheck->bindParam(':nik', $matched_nik);
        $stmtCheck->bindParam(':start', $todayStart);
        $stmtCheck->bindParam(':end', $todayEnd);
        $stmtCheck->execute();
        $lastLog = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $status = 'Hadir Tepat Waktu';
        // Jadwal kerja sesuai shift karyawan; hari libur/akhir pekan tidak dihitung terlambat
        $shift = shiftForEmployee($karyawan['shift_id'] ?? null, shiftMap($db), defaultShift($db));
        $today = date('Y-m-d');
        $isRestDay = !isWorkday($today, shiftDays($shift), holidayMap($db, $today, $today));
        $jamMasukLimit = substr($shift['jam_masuk'], 0, 5);
        $currentTime = date('H:i');

        if ($lastLog) {
            if ($lastLog['status'] === 'Pulang') {
                // Karyawan sudah melakukan absen masuk dan pulang hari ini
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Batas Absensi Terpenuhi! Anda sudah melakukan absensi datang dan pulang hari ini."
                ]);
                return false;
            } else {
                // Cek selisih waktu antara absen masuk dengan saat ini
                $scanTimestamp = strtotime($lastLog['scan_time']);
                $currentTimestamp = time();
                $diffSeconds = $currentTimestamp - $scanTimestamp;

                // Batasan minimal 5 menit (300 detik) untuk menghindari ketidaksengajaan langsung absen pulang setelah datang
                if ($diffSeconds < 300) {
                    http_response_code(400);
                    echo json_encode([
                        "success" => false,
                        "message" => "Absen pulang ditolak! Harap tunggu setidaknya 5 menit setelah melakukan absen datang."
                    ]);
                    return false;
                }

                // Absen masuk sudah tercatat sebelumnya, rekam absen pulang
                $status = 'Pulang';
            }
        } else {
            // Absen masuk baru, tentukan ketepatan waktu
            $status = (!$isRestDay && $currentTime > $jamMasukLimit) ? 'Terlambat' : 'Hadir Tepat Waktu';
        }

        // Hitung Jam Lembur (Overtime) jika status adalah Pulang
        $overtime_hours = 0.00;
        if ($status === 'Pulang' && $isRestDay) {
            // Masuk di hari libur: seluruh jam kerja dihitung lembur (dikurangi istirahat bila > 4 jam)
            $workedHours = (time() - strtotime($lastLog['scan_time'])) / 3600.0;
            if ($workedHours > 4) $workedHours -= intval($shift['istirahat_menit']) / 60.0;
            $overtime_hours = round(max(0, min(24, $workedHours)), 2);
        } elseif ($status === 'Pulang') {
            $jamPulangLimit = $shift['jam_pulang'];
            $now_time = date('H:i:s');
            $checkout_sec = strtotime($now_time);
            $pulang_sec = strtotime($jamPulangLimit);
            
            if ($checkout_sec > $pulang_sec) {
                $overtime_sec = $checkout_sec - $pulang_sec;
                $overtime_hours = round($overtime_sec / 3600.0, 2);
            }
        }

        $logQuery = "INSERT INTO log_absensi (nik, nama, divisi, scan_time, status, match_rate, overtime_hours, metode, foto_bukti, verifikasi) 
                    VALUES (:nik, :nama, :divisi, NOW(), :status, :match_rate, :overtime_hours, :metode, :foto_bukti, :verifikasi)";
        $stmtLog = $db->prepare($logQuery);
        $stmtLog->bindParam(':nik', $matched_nik);
        $stmtLog->bindParam(':nama', $karyawan['nama']);
        $stmtLog->bindParam(':divisi', $karyawan['divisi']);
        $stmtLog->bindParam(':status', $status);
        $stmtLog->bindParam(':match_rate', $confidence);
        $stmtLog->bindParam(':overtime_hours', $overtime_hours);
        
        $stmtLog->bindValue(':metode', $metode);
        $stmtLog->bindValue(':foto_bukti', $fotoBukti);
        $stmtLog->bindValue(':verifikasi', $verifikasi);
        $stmtLog->execute();

        echo json_encode([
            "success" => true,
            "message" => ($status === 'Pulang' ? "Absen pulang sukses dicatat." : "Absen datang sukses dicatat.") . ($verifikasi === 'Menunggu' ? " Menunggu verifikasi admin." : ""),
            "data" => [
                "nik" => $matched_nik,
                "nama" => $karyawan['nama'],
                "divisi" => $karyawan['divisi'],
                "status" => $status,
                "time" => date('H:i:s'),
                "confidence" => $confidence,
                "metode" => $metode,
                "verifikasi" => $verifikasi
            ]
        ]);
        return true;
    } else {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Wajah teridentifikasi tapi database NIK tidak ditemukan."]);
        return false;
    }
}

/**
 * Validasi radius kantor (geofencing). Mengembalikan false bila ditolak (respons error sudah dikirim).
 */
function enforceGeofence($db, $latitude, $longitude) {
// --- AMBIL PENGATURAN KANTOR (GEOFENCING & WAKTU SHIFT) ---
    $setQuery = "SELECT * FROM pengaturan ORDER BY id DESC LIMIT 1";
    $stmtSet = $db->query($setQuery);
    $settings = $stmtSet->fetch(PDO::FETCH_ASSOC);

    if ($settings) {
        $officeLat = (double)$settings['latitude_kantor'];
        $officeLng = (double)$settings['longitude_kantor'];
        $maxRadius = (double)$settings['radius_meter'];

        if ($latitude !== null && $longitude !== null) {
            // Formula Haversine untuk menghitung jarak koordinat bumi (dalam meter)
            $earthRadius = 6371000;
            $latDelta = deg2rad($officeLat - $latitude);
            $lonDelta = deg2rad($officeLng - $longitude);
            
            $a = sin($latDelta / 2) * sin($latDelta / 2) +
                 cos(deg2rad($latitude)) * cos(deg2rad($officeLat)) *
                 sin($lonDelta / 2) * sin($lonDelta / 2);
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            $distance = $earthRadius * $c;

            if ($distance > $maxRadius) {
                http_response_code(403);
                echo json_encode([
                    "success" => false,
                    "message" => "Akses ditolak! Anda berada di luar radius kantor (" . round($distance, 1) . "m dari kantor). Jarak maksimal yang diperbolehkan adalah " . $maxRadius . "m."
                ]);
                return false;
            }
        } else {
            http_response_code(403);
            echo json_encode([
                "success" => false, 
                "message" => "Akses ditolak! Perangkat Anda harus memberikan izin akses lokasi (Geolocation) untuk melakukan absensi."
            ]);
            return false;
        }
    }
    return true;
}

/**
 * 2b. Absen alternatif via unggah foto (cadangan bila verifikasi wajah gagal).
 * Tanpa geofencing; tercatat berstatus "Menunggu" hingga divalidasi admin.
 */
function processPhotoAttendance($db) {
    $data = json_decode(file_get_contents("php://input"));
    $nik = isset($data->nik) ? trim((string)$data->nik) : '';

    if ($nik === '' || empty($data->image_base64)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "NIK dan foto bukti wajib diisi."]);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT nik FROM karyawan WHERE nik = :nik LIMIT 1");
        $stmt->execute([':nik' => $nik]);
        if (!$stmt->fetchColumn()) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "NIK tidak ditemukan."]);
            return;
        }

        // Absen foto tidak dicek radius kantor; keabsahannya divalidasi admin

        // Validasi & simpan foto (hanya JPEG/PNG, maks 5 MB)
        $raw = preg_replace('#^data:image/\w+;base64,#i', '', (string)$data->image_base64);
        $bin = base64_decode($raw, true);
        if ($bin === false || strlen($bin) > 5 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Foto tidak valid atau lebih dari 5 MB."]);
            return;
        }
        $info = @getimagesizefromstring($bin);
        $ext = $info ? ([IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'][$info[2]] ?? null) : null;
        if (!$ext) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Format foto harus JPEG atau PNG."]);
            return;
        }

        $dir = __DIR__ . '/bukti_absen';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $safeNik = preg_replace('/[^A-Za-z0-9_-]/', '', $nik);
        $fileName = $safeNik . '_' . date('Ymd_His') . '.' . $ext;
        if (file_put_contents($dir . '/' . $fileName, $bin) === false) {
            throw new Exception("Gagal menyimpan foto bukti.");
        }

        if (!recordAttendance($db, $nik, '0', 'Foto', 'bukti_absen/' . $fileName, 'Menunggu')) {
            @unlink($dir . '/' . $fileName);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Kesalahan Internal: " . $e->getMessage()]);
    }
}

/**
 * 2c. Admin meninjau absen foto: setujui (Terverifikasi) atau tolak (log dihapus).
 */
function reviewPhotoAttendance($db) {
    $data = json_decode(file_get_contents("php://input"));
    $id = isset($data->id) ? (int)$data->id : 0;
    $keputusan = isset($data->keputusan) ? $data->keputusan : '';

    if ($id <= 0 || !in_array($keputusan, ['setuju', 'tolak'], true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Data peninjauan tidak lengkap."]);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT id FROM log_absensi WHERE id = :id AND metode = 'Foto' AND verifikasi = 'Menunggu'");
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetchColumn()) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Absen foto tidak ditemukan atau sudah ditinjau."]);
            return;
        }
        if ($keputusan === 'setuju') {
            $db->prepare("UPDATE log_absensi SET verifikasi = 'Terverifikasi' WHERE id = :id")->execute([':id' => $id]);
        } else {
            $db->prepare("DELETE FROM log_absensi WHERE id = :id")->execute([':id' => $id]);
        }
        echo json_encode(["success" => true, "message" => $keputusan === 'setuju' ? "Absen foto disetujui." : "Absen foto ditolak dan dihapus."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

/**
 * 3. Ambil List Seluruh Karyawan
 */
function getKaryawanList($db) {
    try {
        $query = "SELECT nik, nama, divisi, jabatan, status_kerja, manager_nik, tanggal_mulai_kerja, tanggal_selesai_kontrak, tanggal_selesai_probation, gaji_pokok, tunjangan, potongan_terlambat, upah_lembur_jam, uang_makan_harian, uang_transport_harian, tunjangan_lain, bpjs_tk_program, bpjs_kes_kelas, no_kpj, no_jkn, bpjs_kes_keluarga_tambahan, tanggal_lahir, status_ptkp, npwp, shift_id, tanggal_keluar, bank_nama, bank_rekening, bank_atas_nama, email, created_at FROM karyawan ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(["success" => true, "data" => $results]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

/**
 * 4. Ambil Log Audit Absensi Hari Ini
 */
function getAttendanceLogs($db) {
    try {
        $nik = isset($_GET['nik']) ? $_GET['nik'] : '';
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        
        $conditions = [];
        $params = [];
        
        if (!empty($nik) && $nik !== 'null') {
            $conditions[] = "nik = :nik";
            $params[':nik'] = $nik;
        }
        
        if (!empty($startDate)) {
            $conditions[] = "scan_time >= :start_date";
            $params[':start_date'] = $startDate . " 00:00:00";
        }
        
        if (!empty($endDate)) {
            $conditions[] = "scan_time <= :end_date";
            $params[':end_date'] = $endDate . " 23:59:59";
        }
        
        if (!empty($status)) {
            $conditions[] = "status = :status";
            $params[':status'] = $status;
        }
        
        $whereClause = "";
        if (!empty($conditions)) {
            $whereClause = "WHERE " . implode(" AND ", $conditions);
        }
        
        $query = "SELECT id, nik, nama, divisi, scan_time, status, match_rate, metode, foto_bukti, verifikasi FROM log_absensi $whereClause ORDER BY scan_time DESC";
        $stmt = $db->prepare($query);
        
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(["success" => true, "data" => $results]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

/**
 * 5. Hapus Data Karyawan dan Sampel Wajah
 */
function deleteKaryawanData($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->nik)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "NIK karyawan tidak disertakan."]);
        return;
    }

    try {
        $nik = $data->nik;

        // Fetch document paths before deleting
        $selectQuery = "SELECT file_ktp, file_npwp, file_kontrak FROM karyawan WHERE nik = :nik LIMIT 1";
        $stmtSelect = $db->prepare($selectQuery);
        $stmtSelect->bindParam(':nik', $nik);
        $stmtSelect->execute();
        $empDoc = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        // --- HAPUS DARI DATABASE MYSQL ---
        $query = "DELETE FROM karyawan WHERE nik = :nik";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nik', $nik);
        
        if ($stmt->execute()) {
            // Delete associated job history
            try {
                $deleteJobQuery = "DELETE FROM riwayat_jabatan WHERE nik = :nik";
                $stmtDelJob = $db->prepare($deleteJobQuery);
                $stmtDelJob->bindParam(':nik', $nik);
                $stmtDelJob->execute();
            } catch (Exception $eJob) {}

            // --- HAPUS FOTO FISIK DARI FOLDER face_db ---
            $file_path = __DIR__ . "/face_db/" . $nik . ".jpg";
            if (file_exists($file_path)) {
                unlink($file_path);
            }

            // --- HAPUS PHYSICAL DOCUMENTS FROM uploads ---
            if ($empDoc) {
                $docFields = ['file_ktp', 'file_npwp', 'file_kontrak'];
                foreach ($docFields as $field) {
                    if (!empty($empDoc[$field]) && file_exists($empDoc[$field])) {
                        unlink($empDoc[$field]);
                    }
                }
            }

            echo json_encode(["success" => true, "message" => "Karyawan berhasil dihapus beserta sampel wajah dan dokumennya."]);
        } else {
            throw new Exception("Gagal menghapus data dari database.");
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Kesalahan Internal: " . $e->getMessage()]);
    }
}

/**
 * 6. Manajemen Akun - Login Otentikasi
 */
function processLogin($db) {
    $data = json_decode(file_get_contents("php://input"));
    $user = $data->username ?? '';
    $pass = $data->password ?? '';

    // Default fallback superadmin
    if ($user === 'admin' && $pass === 'admin123') {
        echo json_encode(["success" => true, "data" => ["username" => "Super Admin", "role" => "admin", "nik" => null]]);
        return;
    }

    // Default fallback direktur
    if ($user === 'direktur' && $pass === 'direktur123') {
        echo json_encode(["success" => true, "data" => ["username" => "Direktur Utama", "role" => "direktur", "nik" => null]]);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT username, password, role, nik FROM users WHERE username = :user LIMIT 1");
        $stmt->bindParam(':user', $user);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && password_verify($pass, $row['password'])) {
            echo json_encode([
                "success" => true, 
                "data" => ["username" => $row['username'], "role" => $row['role'], "nik" => $row['nik']]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Kredensial salah atau tidak ditemukan."]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan: " . $e->getMessage()]);
    }
}

/**
 * 7. Manajemen Akun - Ambil Daftar User
 */
function getUsersList($db) {
    try {
        $stmt = $db->prepare("SELECT id, username, role, nik, created_at FROM users ORDER BY created_at DESC");
        $stmt->execute();
        echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

/**
 * 8. Manajemen Akun - Tambah User
 */
function addUser($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->username) || empty($data->password) || empty($data->role)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Data akun tidak lengkap."]);
        return;
    }

    try {
        $hash = password_hash($data->password, PASSWORD_BCRYPT);
        $nik = ($data->role === 'karyawan') ? $data->nik : null;

        $query = "INSERT INTO users (username, password, role, nik) VALUES (:user, :pass, :role, :nik)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user', $data->username);
        $stmt->bindParam(':pass', $hash);
        $stmt->bindParam(':role', $data->role);
        $stmt->bindParam(':nik', $nik);
        
        $stmt->execute();
        echo json_encode(["success" => true, "message" => "Akun berhasil ditambahkan."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal menambahkan akun (mungkin username duplikat)."]);
    }
}

/**
 * 9. Manajemen Akun - Hapus User
 */
function deleteUser($db) {
    $data = json_decode(file_get_contents("php://input"));
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->bindParam(':id', $data->id);
        $stmt->execute();
        echo json_encode(["success" => true, "message" => "Akun berhasil dihapus."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal menghapus akun."]);
    }
}

/**
 * 10. Ambil Pengumuman
 */
function getAnnouncements($db) {
    try {
        $stmt = $db->prepare("SELECT id, judul, konten, created_at FROM pengumuman ORDER BY created_at DESC");
        $stmt->execute();
        echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

/**
 * 11. Tambah Pengumuman
 */
function addAnnouncement($db) {
    $data = json_decode(file_get_contents("php://input"));
    if (empty($data->judul) || empty($data->konten)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Judul dan konten pengumuman tidak boleh kosong."]);
        return;
    }
    try {
        $query = "INSERT INTO pengumuman (judul, konten) VALUES (:judul, :konten)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':judul', $data->judul);
        $stmt->bindParam(':konten', $data->konten);
        $stmt->execute();
        echo json_encode(["success" => true, "message" => "Pengumuman berhasil dipublikasikan."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal memublikasikan pengumuman."]);
    }
}

/**
 * 12. Hapus Pengumuman
 */
function deleteAnnouncement($db) {
    $data = json_decode(file_get_contents("php://input"));
    try {
        $stmt = $db->prepare("DELETE FROM pengumuman WHERE id = :id");
        $stmt->bindParam(':id', $data->id);
        $stmt->execute();
        echo json_encode(["success" => true, "message" => "Pengumuman berhasil dihapus."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal menghapus pengumuman."]);
    }
}

/**
 * 13. Ambil Pengajuan Cuti
 */
function getLeaves($db) {
    try {
        $nik = isset($_GET['nik']) ? $_GET['nik'] : '';
        if (!empty($nik) && $nik !== 'null') {
            $query = "SELECT id, nik, nama, jenis_cuti, tanggal_mulai, tanggal_selesai, alasan, status, jumlah_hari, created_at FROM cuti WHERE nik = :nik ORDER BY created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nik', $nik);
        } else {
            $query = "SELECT id, nik, nama, jenis_cuti, tanggal_mulai, tanggal_selesai, alasan, status, jumlah_hari, created_at FROM cuti ORDER BY created_at DESC";
            $stmt = $db->prepare($query);
        }
        $stmt->execute();
        echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

/**
 * Nilai bawaan Pengaturan Payroll (dapat diubah admin lewat menu Pengaturan Payroll).
 * Persentase disimpan dalam bentuk persen (0.24 = 0,24%).
 */
function payrollSettingDefaults() {
    return [
        // BPJS Ketenagakerjaan (persen dari Upah BPJS = Gaji Pokok + Tunjangan Tetap)
        'jkk_persen'            => 0.24,
        'jkm_persen'            => 0.30,
        'jht_perusahaan_persen' => 3.7,
        'jht_karyawan_persen'   => 2,
        'jp_perusahaan_persen'  => 2,
        'jp_karyawan_persen'    => 1,
        'jp_batas_upah'         => 10547400,
        // Jaminan Kehilangan Pekerjaan (ditanggung pemerintah, tidak memotong gaji)
        'jkp_persen'            => 0.36,
        'jkp_batas_upah'        => 5000000,
        // BPJS Kesehatan tarif tetap per kelas (dipotong dari gaji karyawan)
        'bpjs_kes_kelas1'       => 150000,
        'bpjs_kes_kelas2'       => 100000,
        'bpjs_kes_kelas3'       => 42000,
        // Mode BPJS Kesehatan: 'ppu' = persentase upah (pekerja penerima upah), 'tarif' = tarif tetap per kelas (mandiri)
        'bpjs_kes_mode'              => 'tarif',
        'bpjs_kes_perusahaan_persen' => 4,
        'bpjs_kes_karyawan_persen'   => 1,
        'bpjs_kes_batas_upah'        => 12000000,
        'bpjs_kes_keluarga_persen'   => 1,
        // Upah minimum (UMK) sebagai batas bawah dasar iuran BPJS, 0 = nonaktif
        'upah_minimum'               => 0,
        // Usia pensiun: iuran Jaminan Pensiun berhenti setelah karyawan mencapai usia ini
        'usia_pensiun'               => 59,
        // PPh 21 (TER PMK 168/2023)
        'pph21_aktif'                => 1,
        'pph21_metode'               => 'potong',   // 'potong' = dipotong dari gaji, 'ditanggung' = ditanggung perusahaan
        'ptkp_dasar'                 => 54000000,
        'ptkp_kawin'                 => 4500000,
        'ptkp_tanggungan'            => 4500000,
        'biaya_jabatan_persen'       => 5,
        'biaya_jabatan_maks_bulan'   => 500000,
        'pph21_tanpa_npwp_persen'    => 120,
        'default_status_ptkp'        => 'TK/0',
        // Lembur (PP 35/2021)
        'lembur_metode'              => 'pp35',     // 'pp35' = upah sejam 1/173 × pengali; 'flat' = tarif per jam karyawan
        'lembur_pembagi'             => 173,
        'lembur_perlu_persetujuan'   => 1,
        'lembur_maks_harian'         => 4,
        // Prorata gaji karyawan masuk/keluar di tengah bulan
        'prorata_aktif'              => 1,
        'prorata_metode'             => 'hari_kerja', // 'hari_kerja' atau 'kalender'
        // Cuti tahunan
        'cuti_jatah'                 => 12,
        'cuti_syarat_bulan'          => 12,
        'cuti_bersama_potong'        => 1,
        // Email slip gaji (SMTP)
        'smtp_host'                  => '',
        'smtp_port'                  => 587,
        'smtp_secure'                => 'tls',
        'smtp_user'                  => '',
        'smtp_pass'                  => '',
        'smtp_from_email'            => '',
        'smtp_from_name'             => 'HRIS Payroll',
        // Aturan perhitungan
        'hari_kerja_pembagi'    => 25,
        'tunjangan_hanya_pkwtt' => 1,
        // Nilai default komponen gaji karyawan
        'default_gaji_pokok'         => 5000000,
        'default_tunjangan'          => 1000000,
        'default_uang_makan'         => 25000,
        'default_uang_transport'     => 20000,
        'default_tunjangan_lain'     => 0,
        'default_upah_lembur'        => 25000,
        'default_potongan_terlambat' => 50000,
        'default_bpjs_tk_program'    => 3,
        'default_bpjs_kes_kelas'     => 2,
        // Tampilan slip gaji
        'slip_judul'                 => 'Slip Gaji Karyawan',
        'slip_prefix'                => 'SLIP',
        'slip_logo'                  => '',
        'slip_penandatangan_nama'    => '',
        'slip_penandatangan_jabatan' => 'HRD / Finance',
        'slip_catatan_kaki'          => 'Dokumen ini diterbitkan otomatis oleh sistem VeriFace HRIS dan sah tanpa tanda tangan basah. Bersifat rahasia.',
        'slip_sembunyikan_nol'       => 0,
        'slip_tampilkan_kehadiran'   => 1,
        'slip_tampilkan_kontribusi'  => 1,
        'slip_tampilkan_terbilang'   => 1,
        'slip_tampilkan_ttd'         => 1
    ];
}

/**
 * Ambil Pengaturan Payroll tersimpan, digabung dengan nilai bawaan
 */
function loadPayrollSettings($db) {
    $settings = payrollSettingDefaults();
    try {
        $rows = $db->query("SELECT kunci, nilai FROM pengaturan_payroll")->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($rows as $key => $value) {
            if (!array_key_exists($key, $settings)) continue;
            $settings[$key] = is_string($settings[$key]) ? (string)$value : (double)$value;
        }
    } catch (Exception $e) {
        // Tabel belum tersedia: gunakan nilai bawaan
    }
    return $settings;
}

/**
 * Konfigurasi tarif komponen payroll siap pakai untuk perhitungan (persen → pecahan desimal).
 */
function payrollConfig($db) {
    $s = loadPayrollSettings($db);
    return [
        'jkk_perusahaan'        => $s['jkk_persen'] / 100,
        'jkm_perusahaan'        => $s['jkm_persen'] / 100,
        'jht_perusahaan'        => $s['jht_perusahaan_persen'] / 100,
        'jht_karyawan'          => $s['jht_karyawan_persen'] / 100,
        'jp_perusahaan'         => $s['jp_perusahaan_persen'] / 100,
        'jp_karyawan'           => $s['jp_karyawan_persen'] / 100,
        'jp_batas_upah'         => $s['jp_batas_upah'],
        'jkp_persen'            => $s['jkp_persen'] / 100,
        'jkp_batas_upah'        => $s['jkp_batas_upah'],
        'bpjs_kes_tarif'        => [1 => $s['bpjs_kes_kelas1'], 2 => $s['bpjs_kes_kelas2'], 3 => $s['bpjs_kes_kelas3']],
        'hari_kerja_pembagi'    => max(1, $s['hari_kerja_pembagi']),
        'bpjs_kes_mode'         => $s['bpjs_kes_mode'] === 'ppu' ? 'ppu' : 'tarif',
        'pph21_aktif'           => (bool)$s['pph21_aktif'],
        'pph21_metode'          => $s['pph21_metode'] === 'ditanggung' ? 'ditanggung' : 'potong',
        'ptkp_dasar'            => $s['ptkp_dasar'],
        'ptkp_kawin'            => $s['ptkp_kawin'],
        'ptkp_tanggungan'       => $s['ptkp_tanggungan'],
        'biaya_jabatan'         => $s['biaya_jabatan_persen'] / 100,
        'biaya_jabatan_maks_bulan' => $s['biaya_jabatan_maks_bulan'],
        'pph21_tanpa_npwp'      => max(100, $s['pph21_tanpa_npwp_persen']) / 100,
        'lembur_metode'         => $s['lembur_metode'] === 'flat' ? 'flat' : 'pp35',
        'lembur_pembagi'        => max(1, $s['lembur_pembagi']),
        'lembur_perlu_persetujuan' => (bool)$s['lembur_perlu_persetujuan'],
        'lembur_maks_harian'    => $s['lembur_maks_harian'],
        'prorata_aktif'         => (bool)$s['prorata_aktif'],
        'prorata_metode'        => $s['prorata_metode'] === 'kalender' ? 'kalender' : 'hari_kerja',
        'cuti_jatah'            => intval($s['cuti_jatah']),
        'cuti_syarat_bulan'     => intval($s['cuti_syarat_bulan']),
        'cuti_bersama_potong'   => (bool)$s['cuti_bersama_potong'],
        'bpjs_kes_perusahaan'   => $s['bpjs_kes_perusahaan_persen'] / 100,
        'bpjs_kes_karyawan'     => $s['bpjs_kes_karyawan_persen'] / 100,
        'bpjs_kes_batas_upah'   => $s['bpjs_kes_batas_upah'],
        'bpjs_kes_keluarga'     => $s['bpjs_kes_keluarga_persen'] / 100,
        'upah_minimum'          => $s['upah_minimum'],
        'usia_pensiun'          => $s['usia_pensiun'],
        'tunjangan_hanya_pkwtt' => (bool)$s['tunjangan_hanya_pkwtt'],
        'slip_prefix'           => $s['slip_prefix'] !== '' ? $s['slip_prefix'] : 'SLIP'
    ];
}

/**
 * Pengaturan Payroll: ambil
 */
function getPayrollSettings($db) {
    $data = loadPayrollSettings($db);
    $data['smtp_pass_set'] = $data['smtp_pass'] !== '';
    $data['smtp_pass'] = '';
    echo json_encode(["success" => true, "data" => $data, "defaults" => payrollSettingDefaults()]);
}

/**
 * Pengaturan Payroll: simpan (hanya kunci yang dikenal yang diterima)
 */
function savePayrollSettings($db) {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Format data pengaturan tidak valid."]);
        return;
    }
    $defaults = payrollSettingDefaults();
    $before = loadPayrollSettings($db);
    // Kata sandi SMTP kosong = tidak diubah
    if (isset($data['smtp_pass']) && $data['smtp_pass'] === '') unset($data['smtp_pass']);
    try {
        $stmt = $db->prepare("INSERT INTO pengaturan_payroll (kunci, nilai) VALUES (:k, :v) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
        $db->beginTransaction();
        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $defaults)) continue;
            if (is_string($defaults[$key])) {
                $value = (string)$value;
                if ($key === 'slip_logo' && $value !== '' && (strpos($value, 'data:image/') !== 0 || strlen($value) > 400000)) {
                    throw new Exception("Logo harus berupa gambar dan maksimal ±300KB.");
                }
                if ($key !== 'slip_logo') $value = mb_substr(trim($value), 0, 500);
            } else {
                if (!is_numeric($value) || $value < 0) throw new Exception("Nilai '$key' harus berupa angka positif.");
                $value = (string)(double)$value;
            }
            $stmt->execute([':k' => $key, ':v' => $value]);
        }
        $db->commit();
        $after = loadPayrollSettings($db);
        $diffOld = $diffNew = [];
        foreach ($after as $k => $v) {
            if ($k === 'slip_logo' || $k === 'smtp_pass') continue;
            if ((string)$before[$k] !== (string)$v) { $diffOld[$k] = $before[$k]; $diffNew[$k] = $v; }
        }
        if ($diffNew) auditLog($db, 'save_payroll_settings', 'pengaturan_payroll', null, $diffOld, $diffNew);
        echo json_encode(["success" => true, "message" => "Pengaturan payroll berhasil disimpan."]);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Gagal menyimpan pengaturan payroll: " . $e->getMessage()]);
    }
}

/**
 * Terapkan nilai default komponen gaji ke karyawan (massal)
 * Body: { fields: ['uang_makan', ...], scope: 'all' | 'PKWT' | 'PKWTT', nik?: '...' }
 */
function applyPayrollDefaultsToEmployees($db, $fields, $scope = 'all', $nik = null) {
    $s = loadPayrollSettings($db);
    $map = [
        'gaji_pokok'         => ['gaji_pokok', 'default_gaji_pokok'],
        'tunjangan'          => ['tunjangan', 'default_tunjangan'],
        'uang_makan'         => ['uang_makan_harian', 'default_uang_makan'],
        'uang_transport'     => ['uang_transport_harian', 'default_uang_transport'],
        'tunjangan_lain'     => ['tunjangan_lain', 'default_tunjangan_lain'],
        'upah_lembur'        => ['upah_lembur_jam', 'default_upah_lembur'],
        'potongan_terlambat' => ['potongan_terlambat', 'default_potongan_terlambat'],
        'bpjs_tk_program'    => ['bpjs_tk_program', 'default_bpjs_tk_program'],
        'bpjs_kes_kelas'     => ['bpjs_kes_kelas', 'default_bpjs_kes_kelas'],
        'status_ptkp'        => ['status_ptkp', 'default_status_ptkp']
    ];
    $sets = [];
    $params = [];
    foreach ($fields as $f) {
        if (!isset($map[$f])) continue;
        [$column, $settingKey] = $map[$f];
        $sets[] = "$column = :$f";
        $params[":$f"] = $s[$settingKey];
    }
    if (empty($sets)) return 0;

    $where = [];
    if ($nik !== null) {
        $where[] = "nik = :nik";
        $params[':nik'] = $nik;
    } elseif ($scope === 'PKWT' || $scope === 'PKWTT') {
        $where[] = "status_kerja = :scope";
        $params[':scope'] = $scope;
    }
    $whereClause = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);
    $db->prepare("UPDATE karyawan SET " . implode(', ', $sets) . $whereClause)->execute($params);

    // rowCount() MySQL hanya menghitung baris yang berubah, jadi hitung karyawan yang tercakup
    $countParams = array_intersect_key($params, [':nik' => 1, ':scope' => 1]);
    $countStmt = $db->prepare("SELECT COUNT(*) FROM karyawan" . $whereClause);
    $countStmt->execute($countParams);
    return intval($countStmt->fetchColumn());
}

function applyPayrollDefaults($db) {
    $data = json_decode(file_get_contents("php://input"), true);
    $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];
    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Pilih minimal satu komponen yang akan diterapkan."]);
        return;
    }
    try {
        $count = applyPayrollDefaultsToEmployees($db, $fields, $data['scope'] ?? 'all');
        echo json_encode(["success" => true, "message" => "Nilai default diterapkan ke $count karyawan."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal menerapkan default: " . $e->getMessage()]);
    }
}

/**
 * ==============================================================================
 * PPh 21 — Tarif Efektif Rata-rata (TER) PP 58/2023 & PMK 168/2023
 * Masa Januari–November memakai TER bulanan, masa Desember dihitung ulang setahun
 * dengan tarif Pasal 17 UU HPP lalu dikurangi PPh 21 yang telah dipotong.
 * ==============================================================================
 */
function pph21TerTable($kategori) {
    // [batas atas penghasilan bruto bulanan, tarif %]; baris terakhir = di atas batas sebelumnya
    $tables = [
        'A' => [[5400000,0],[5650000,0.25],[5950000,0.5],[6300000,0.75],[6750000,1],[7500000,1.25],[8550000,1.5],[9650000,1.75],
                [10050000,2],[10350000,2.25],[10700000,2.5],[11050000,3],[11600000,3.5],[12500000,4],[13750000,5],[15100000,6],
                [16950000,7],[19750000,8],[24150000,9],[26450000,10],[28000000,11],[30050000,12],[32400000,13],[35400000,14],
                [39100000,15],[43850000,16],[47800000,17],[51400000,18],[56300000,19],[62200000,20],[68600000,21],[77500000,22],
                [89000000,23],[103000000,24],[125000000,25],[157000000,26],[206000000,27],[337000000,28],[454000000,29],
                [550000000,30],[695000000,31],[910000000,32],[1400000000,33],[PHP_INT_MAX,34]],
        'B' => [[6200000,0],[6500000,0.25],[6850000,0.5],[7300000,0.75],[9200000,1],[10750000,1.5],[11250000,2],[11600000,2.5],
                [12600000,3],[13600000,4],[14950000,5],[16400000,6],[18450000,7],[21850000,8],[26000000,9],[27700000,10],
                [29350000,11],[31450000,12],[33950000,13],[37100000,14],[41100000,15],[45800000,16],[49500000,17],[53800000,18],
                [58500000,19],[64000000,20],[71000000,21],[80000000,22],[93000000,23],[109000000,24],[129000000,25],[163000000,26],
                [211000000,27],[374000000,28],[459000000,29],[555000000,30],[704000000,31],[957000000,32],[1405000000,33],[PHP_INT_MAX,34]],
        'C' => [[6600000,0],[6950000,0.25],[7350000,0.5],[7800000,0.75],[8850000,1],[9800000,1.25],[10950000,1.5],[11200000,1.75],
                [12050000,2],[12950000,3],[14150000,4],[15550000,5],[17050000,6],[19500000,7],[22700000,8],[26600000,9],
                [28100000,10],[30100000,11],[32600000,12],[35400000,13],[38900000,14],[43000000,15],[47400000,16],[51200000,17],
                [55800000,18],[60400000,19],[66700000,20],[74500000,21],[83200000,22],[95600000,23],[110000000,24],[134000000,25],
                [169000000,26],[221000000,27],[390000000,28],[463000000,29],[561000000,30],[709000000,31],[965000000,32],
                [1419000000,33],[PHP_INT_MAX,34]]
    ];
    return $tables[$kategori] ?? $tables['A'];
}

function pph21Statuses() {
    return ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'];
}

function pph21TerKategori($status) {
    if (in_array($status, ['TK/2', 'TK/3', 'K/1', 'K/2'], true)) return 'B';
    if ($status === 'K/3') return 'C';
    return 'A'; // TK/0, TK/1, K/0
}

function pph21TerRate($kategori, $bruto) {
    foreach (pph21TerTable($kategori) as [$batas, $tarif]) {
        if ($bruto <= $batas) return $tarif;
    }
    return 34;
}

function pph21Ptkp($status, $cfg) {
    [$kawin, $tanggungan] = array_pad(explode('/', $status), 2, '0');
    $ptkp = $cfg['ptkp_dasar'];
    if ($kawin === 'K') $ptkp += $cfg['ptkp_kawin'];
    $ptkp += min(3, intval($tanggungan)) * $cfg['ptkp_tanggungan'];
    return $ptkp;
}

// Tarif progresif Pasal 17 ayat (1) huruf a UU HPP
function pph21Pasal17($pkp) {
    $lapisan = [[60000000, 0.05], [250000000, 0.15], [500000000, 0.25], [5000000000, 0.30], [PHP_INT_MAX, 0.35]];
    $pajak = 0.0;
    $bawah = 0.0;
    foreach ($lapisan as [$atas, $tarif]) {
        if ($pkp <= $bawah) break;
        $pajak += (min($pkp, $atas) - $bawah) * $tarif;
        $bawah = $atas;
    }
    return $pajak;
}

/**
 * Peta nama bulan Indonesia ke angka bulan
 */
function bulanMap() {
    return [
        'Januari'=>'01', 'Februari'=>'02', 'Maret'=>'03', 'April'=>'04',
        'Mei'=>'05', 'Juni'=>'06', 'Juli'=>'07', 'Agustus'=>'08',
        'September'=>'09', 'Oktober'=>'10', 'November'=>'11', 'Desember'=>'12'
    ];
}

/**
 * 14. Ambil Pengaturan Kantor
 */
function getSettings($db) {
    try {
        $query = "SELECT * FROM pengaturan ORDER BY id DESC LIMIT 1";
        $stmt = $db->query($query);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings) {
            echo json_encode(["success" => true, "data" => $settings]);
        } else {
            echo json_encode(["success" => false, "message" => "Pengaturan tidak ditemukan."]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal mengambil pengaturan: " . $e->getMessage()]);
    }
}

/**
 * 15. Simpan Pengaturan Kantor
 */
function saveSettings($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->nama_perusahaan) || empty($data->alamat_perusahaan) || empty($data->jam_masuk) || empty($data->jam_pulang) || !isset($data->latitude_kantor) || !isset($data->longitude_kantor) || !isset($data->radius_meter)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Data pengaturan tidak lengkap."]);
        return;
    }
    
    try {
        // Ambil ID pengaturan yang sedang aktif
        $check = $db->query("SELECT id FROM pengaturan ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        
        if ($check) {
            $query = "UPDATE pengaturan SET 
                        nama_perusahaan = :nama_perusahaan, 
                        alamat_perusahaan = :alamat_perusahaan, 
                        jam_masuk = :jam_masuk, 
                        jam_pulang = :jam_pulang, 
                        latitude_kantor = :latitude_kantor, 
                        longitude_kantor = :longitude_kantor, 
                        radius_meter = :radius_meter 
                      WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $check['id']);
        } else {
            $query = "INSERT INTO pengaturan (nama_perusahaan, alamat_perusahaan, jam_masuk, jam_pulang, latitude_kantor, longitude_kantor, radius_meter) 
                      VALUES (:nama_perusahaan, :alamat_perusahaan, :jam_masuk, :jam_pulang, :latitude_kantor, :longitude_kantor, :radius_meter)";
            $stmt = $db->prepare($query);
        }
        
        $stmt->bindParam(':nama_perusahaan', $data->nama_perusahaan);
        $stmt->bindParam(':alamat_perusahaan', $data->alamat_perusahaan);
        $stmt->bindParam(':jam_masuk', $data->jam_masuk);
        $stmt->bindParam(':jam_pulang', $data->jam_pulang);
        $stmt->bindParam(':latitude_kantor', $data->latitude_kantor);
        $stmt->bindParam(':longitude_kantor', $data->longitude_kantor);
        $stmt->bindParam(':radius_meter', $data->radius_meter);
        
        if ($stmt->execute()) {
            // Dapatkan ID yang baru saja diperbarui atau dibuat
            $activeId = $check ? (int)$check['id'] : (int)$db->lastInsertId();
            // Bersihkan baris lama/duplikat agar tabel pengaturan selalu konsisten memiliki 1 baris
            $db->exec("DELETE FROM pengaturan WHERE id != {$activeId}");
            
            echo json_encode(["success" => true, "message" => "Pengaturan kantor berhasil disimpan."]);
        } else {
            throw new Exception("Gagal menyimpan ke database.");
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal menyimpan pengaturan: " . $e->getMessage()]);
    }
}

/**
 * 16. Perbarui Detail Gaji Karyawan Individu
 */
function updateKaryawanGaji($db) {
    $data = json_decode(file_get_contents("php://input"));

    if (empty($data->nik) || !isset($data->gaji_pokok) || !isset($data->tunjangan) || !isset($data->potongan_terlambat)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Data tidak lengkap."]);
        return;
    }

    $bpjsTk = intval($data->bpjs_tk_program ?? 3);
    $bpjsKes = intval($data->bpjs_kes_kelas ?? 2);
    if (!in_array($bpjsTk, [0, 2, 3, 4], true)) $bpjsTk = 3;
    if (!in_array($bpjsKes, [0, 1, 2, 3], true)) $bpjsKes = 2;

    try {
        $query = "UPDATE karyawan SET
                    gaji_pokok = :gaji_pokok,
                    tunjangan = :tunjangan,
                    potongan_terlambat = :potongan_terlambat,
                    upah_lembur_jam = :upah_lembur_jam,
                    uang_makan_harian = :uang_makan_harian,
                    uang_transport_harian = :uang_transport_harian,
                    tunjangan_lain = :tunjangan_lain,
                    bpjs_tk_program = :bpjs_tk_program,
                    bpjs_kes_kelas = :bpjs_kes_kelas,
                    no_kpj = :no_kpj,
                    no_jkn = :no_jkn,
                    bpjs_kes_keluarga_tambahan = :keluarga,
                    status_ptkp = :status_ptkp,
                    npwp = :npwp,
                    shift_id = :shift_id,
                    tanggal_keluar = :tanggal_keluar,
                    bank_nama = :bank_nama,
                    bank_rekening = :bank_rekening,
                    bank_atas_nama = :bank_atas_nama
                  WHERE nik = :nik";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':gaji_pokok', (double)$data->gaji_pokok);
        $stmt->bindValue(':tunjangan', (double)$data->tunjangan);
        $stmt->bindValue(':potongan_terlambat', (double)$data->potongan_terlambat);
        $stmt->bindValue(':upah_lembur_jam', (double)($data->upah_lembur_jam ?? 25000));
        $stmt->bindValue(':uang_makan_harian', (double)($data->uang_makan_harian ?? 0));
        $stmt->bindValue(':uang_transport_harian', (double)($data->uang_transport_harian ?? 0));
        $stmt->bindValue(':tunjangan_lain', (double)($data->tunjangan_lain ?? 0));
        $stmt->bindValue(':bpjs_tk_program', $bpjsTk, PDO::PARAM_INT);
        $stmt->bindValue(':bpjs_kes_kelas', $bpjsKes, PDO::PARAM_INT);
        $stmt->bindValue(':no_kpj', trim($data->no_kpj ?? '') !== '' ? mb_substr(trim($data->no_kpj), 0, 30) : null);
        $stmt->bindValue(':no_jkn', trim($data->no_jkn ?? '') !== '' ? mb_substr(trim($data->no_jkn), 0, 30) : null);
        $stmt->bindValue(':keluarga', max(0, min(20, intval($data->bpjs_kes_keluarga_tambahan ?? 0))), PDO::PARAM_INT);
        $stmt->bindValue(':status_ptkp', in_array($data->status_ptkp ?? '', pph21Statuses(), true) ? $data->status_ptkp : 'TK/0');
        $stmt->bindValue(':npwp', trim($data->npwp ?? '') !== '' ? mb_substr(trim($data->npwp), 0, 30) : null);
        $stmt->bindValue(':shift_id', !empty($data->shift_id) ? intval($data->shift_id) : null, !empty($data->shift_id) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':tanggal_keluar', !empty($data->tanggal_keluar) && strtotime($data->tanggal_keluar) ? date('Y-m-d', strtotime($data->tanggal_keluar)) : null);
        $stmt->bindValue(':bank_nama', trim($data->bank_nama ?? '') !== '' ? mb_substr(trim($data->bank_nama), 0, 50) : null);
        $stmt->bindValue(':bank_rekening', trim($data->bank_rekening ?? '') !== '' ? preg_replace('/[^0-9]/', '', $data->bank_rekening) : null);
        $stmt->bindValue(':bank_atas_nama', trim($data->bank_atas_nama ?? '') !== '' ? mb_substr(trim($data->bank_atas_nama), 0, 150) : null);

        $oldStmt = $db->prepare("SELECT gaji_pokok, tunjangan, potongan_terlambat, upah_lembur_jam, uang_makan_harian, uang_transport_harian,
                                        tunjangan_lain, bpjs_tk_program, bpjs_kes_kelas, no_kpj, no_jkn, bpjs_kes_keluarga_tambahan, status_ptkp,
                                        npwp, shift_id, tanggal_keluar, bank_nama, bank_rekening, bank_atas_nama FROM karyawan WHERE nik = :nik");
        $oldStmt->execute([':nik' => $data->nik]);
        $oldRow = $oldStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $stmt->bindValue(':nik', $data->nik);

        if ($stmt->execute()) {
            $newStmt = $db->prepare("SELECT " . implode(', ', array_keys($oldRow ?: ['gaji_pokok' => 1])) . " FROM karyawan WHERE nik = :nik");
            $newStmt->execute([':nik' => $data->nik]);
            $newRow = $newStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $diffOld = $diffNew = [];
            foreach ($newRow as $k => $v) {
                if ((string)($oldRow[$k] ?? '') !== (string)$v) { $diffOld[$k] = $oldRow[$k] ?? null; $diffNew[$k] = $v; }
            }
            if ($diffNew) auditLog($db, 'update_karyawan_gaji', 'karyawan', $data->nik, $diffOld, $diffNew);
            echo json_encode(["success" => true, "message" => "Pengaturan gaji karyawan berhasil diperbarui."]);
        } else {
            throw new Exception("Gagal memperbarui database.");
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal memperbarui pengaturan gaji: " . $e->getMessage()]);
    }
}

/**
 * 16c. Rekap Iuran BPJS & PPh 21 per periode (untuk pembayaran BPJS & SPT Masa PPh 21)
 */
function getRekapPayroll($db) {
    $bulan = $_GET['bulan'] ?? '';
    $tahun = intval($_GET['tahun'] ?? 0);
    if (!isset(bulanMap()[$bulan]) || $tahun <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Periode tidak valid."]);
        return;
    }
    try {
        $stmt = $db->prepare("SELECT nik, nama, divisi, status_kerja, no_kpj, no_jkn, npwp, status_ptkp, bpjs_tk_program, bpjs_kes_kelas,
                                     bpjs_jht_karyawan, bpjs_jp_karyawan, bpjs_kes_karyawan, bpjs_kes_keluarga, jkp_iuran,
                                     rincian_perusahaan, bruto_pph21, pph21, pph21_ditanggung, rincian_pph21, total_pendapatan, total_gaji
                              FROM penggajian WHERE bulan = :bulan AND tahun = :tahun ORDER BY nama ASC");
        $stmt->execute([':bulan' => $bulan, ':tahun' => $tahun]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $c = json_decode($r['rincian_perusahaan'] ?? '{}', true) ?: [];
            $pph = json_decode($r['rincian_pph21'] ?? '{}', true) ?: [];
            $rows[] = [
                'nik' => $r['nik'], 'nama' => $r['nama'], 'divisi' => $r['divisi'], 'status_kerja' => $r['status_kerja'],
                'no_kpj' => $r['no_kpj'], 'no_jkn' => $r['no_jkn'], 'npwp' => $r['npwp'], 'status_ptkp' => $r['status_ptkp'],
                'program' => intval($r['bpjs_tk_program']), 'kelas' => intval($r['bpjs_kes_kelas']),
                'upah_bpjs' => (double)($c['upah_bpjs'] ?? 0),
                'jkk' => (double)($c['jkk'] ?? 0), 'jkm' => (double)($c['jkm'] ?? 0),
                'jht_perusahaan' => (double)($c['jht'] ?? 0), 'jht_karyawan' => (double)$r['bpjs_jht_karyawan'],
                'jp_perusahaan' => (double)($c['jp'] ?? 0), 'jp_karyawan' => (double)$r['bpjs_jp_karyawan'],
                'jkp' => (double)$r['jkp_iuran'],
                'kes_perusahaan' => (double)($c['kes'] ?? 0), 'kes_karyawan' => (double)$r['bpjs_kes_karyawan'],
                'kes_keluarga' => (double)$r['bpjs_kes_keluarga'],
                'bruto_pph21' => (double)$r['bruto_pph21'], 'pph21' => (double)$r['pph21'] + (double)$r['pph21_ditanggung'],
                'pph21_ditanggung' => (double)$r['pph21_ditanggung'] != 0,
                'pph21_keterangan' => ($pph['metode'] ?? '') === 'PASAL17' ? 'Pasal 17 (Desember)'
                                      : (isset($pph['kategori']) ? 'TER ' . $pph['kategori'] . ' ' . $pph['tarif'] . '%' : '-'),
                'total_gaji' => (double)$r['total_gaji']
            ];
        }
        echo json_encode(["success" => true, "data" => $rows]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

/**
 * 16a. Kasbon (Pinjaman Karyawan) - daftar beserta sisa pinjaman
 */
function getKasbon($db) {
    try {
        $nik = $_GET['nik'] ?? '';
        $where = (!empty($nik) && $nik !== 'null') ? "WHERE k.nik = :nik" : "";
        $stmt = $db->prepare("SELECT k.*, COALESCE(SUM(p.jumlah), 0) AS terbayar, k.jumlah - COALESCE(SUM(p.jumlah), 0) AS sisa
                              FROM kasbon k LEFT JOIN kasbon_potongan p ON p.kasbon_id = k.id
                              $where GROUP BY k.id ORDER BY k.tanggal DESC, k.id DESC");
        if ($where) $stmt->bindValue(':nik', $nik);
        $stmt->execute();
        echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

function addKasbon($db) {
    $data = json_decode(file_get_contents("php://input"));
    if (empty($data->nik) || empty($data->tanggal) || empty($data->jumlah) || (double)$data->jumlah <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "NIK, tanggal, dan jumlah kasbon wajib diisi."]);
        return;
    }
    try {
        $emp = $db->prepare("SELECT nama FROM karyawan WHERE nik = :nik");
        $emp->execute([':nik' => $data->nik]);
        $nama = $emp->fetchColumn();
        if (!$nama) {
            echo json_encode(["success" => false, "message" => "Karyawan tidak ditemukan."]);
            return;
        }
        $jumlah = (double)$data->jumlah;
        $cicilan = (double)($data->cicilan_per_bulan ?? 0);
        if ($cicilan <= 0 || $cicilan > $jumlah) $cicilan = $jumlah; // default: lunas sekali potong

        $stmt = $db->prepare("INSERT INTO kasbon (nik, nama, tanggal, jumlah, cicilan_per_bulan, keterangan) VALUES (:nik, :nama, :tanggal, :jumlah, :cicilan, :ket)");
        $stmt->execute([
            ':nik' => $data->nik, ':nama' => $nama, ':tanggal' => $data->tanggal,
            ':jumlah' => $jumlah, ':cicilan' => $cicilan, ':ket' => $data->keterangan ?? null
        ]);
        echo json_encode(["success" => true, "message" => "Kasbon berhasil dicatat."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal menyimpan kasbon: " . $e->getMessage()]);
    }
}

function deleteKasbon($db) {
    $data = json_decode(file_get_contents("php://input"));
    if (empty($data->id)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID kasbon tidak valid."]);
        return;
    }
    try {
        $db->beginTransaction();
        $db->prepare("DELETE FROM kasbon_potongan WHERE kasbon_id = :id")->execute([':id' => $data->id]);
        $db->prepare("DELETE FROM kasbon WHERE id = :id")->execute([':id' => $data->id]);
        $db->commit();
        echo json_encode(["success" => true, "message" => "Kasbon berhasil dihapus."]);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal menghapus kasbon: " . $e->getMessage()]);
    }
}

/**
 * 16b. Reimbursement - klaim biaya karyawan yang dibayarkan bersama gaji
 */
function getReimbursement($db) {
    try {
        $nik = $_GET['nik'] ?? '';
        $where = (!empty($nik) && $nik !== 'null') ? "WHERE nik = :nik" : "";
        $stmt = $db->prepare("SELECT * FROM reimbursement $where ORDER BY tanggal DESC, id DESC");
        if ($where) $stmt->bindValue(':nik', $nik);
        $stmt->execute();
        echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

function addReimbursement($db) {
    $data = json_decode(file_get_contents("php://input"));
    if (empty($data->nik) || empty($data->tanggal) || empty($data->kategori) || empty($data->jumlah) || (double)$data->jumlah <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "NIK, tanggal, kategori, dan jumlah wajib diisi."]);
        return;
    }
    try {
        $emp = $db->prepare("SELECT nama FROM karyawan WHERE nik = :nik");
        $emp->execute([':nik' => $data->nik]);
        $nama = $emp->fetchColumn();
        if (!$nama) {
            echo json_encode(["success" => false, "message" => "Karyawan tidak ditemukan."]);
            return;
        }
        // Input oleh admin langsung disetujui, pengajuan karyawan menunggu persetujuan
        $status = (isset($data->status) && $data->status === 'approved') ? 'approved' : 'pending';

        $stmt = $db->prepare("INSERT INTO reimbursement (nik, nama, tanggal, kategori, jumlah, keterangan, status) VALUES (:nik, :nama, :tanggal, :kategori, :jumlah, :ket, :status)");
        $stmt->execute([
            ':nik' => $data->nik, ':nama' => $nama, ':tanggal' => $data->tanggal, ':kategori' => $data->kategori,
            ':jumlah' => (double)$data->jumlah, ':ket' => $data->keterangan ?? null, ':status' => $status
        ]);
        echo json_encode(["success" => true, "message" => $status === 'approved' ? "Reimbursement dicatat & disetujui." : "Pengajuan reimbursement terkirim."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal menyimpan reimbursement: " . $e->getMessage()]);
    }
}

function updateReimbursementStatus($db) {
    $data = json_decode(file_get_contents("php://input"));
    if (empty($data->id) || !in_array($data->status ?? '', ['approved', 'rejected'], true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Data persetujuan tidak valid."]);
        return;
    }
    try {
        $stmt = $db->prepare("UPDATE reimbursement SET status = :status WHERE id = :id AND dibayar_bulan IS NULL");
        $stmt->execute([':status' => $data->status, ':id' => $data->id]);
        echo json_encode(["success" => true, "message" => "Status reimbursement diperbarui."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal memperbarui reimbursement: " . $e->getMessage()]);
    }
}

function deleteReimbursement($db) {
    $data = json_decode(file_get_contents("php://input"));
    if (empty($data->id)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID reimbursement tidak valid."]);
        return;
    }
    try {
        $stmt = $db->prepare("DELETE FROM reimbursement WHERE id = :id AND dibayar_bulan IS NULL");
        $stmt->execute([':id' => $data->id]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(["success" => false, "message" => "Reimbursement yang sudah dibayarkan di slip gaji tidak dapat dihapus."]);
            return;
        }
        echo json_encode(["success" => true, "message" => "Reimbursement berhasil dihapus."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal menghapus reimbursement: " . $e->getMessage()]);
    }
}

/**
 * 17. Ambil Detail Karyawan (Centralized Profile)
 */
function getKaryawanDetail($db) {
    $nik = $_GET['nik'] ?? '';
    if (empty($nik)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "NIK tidak boleh kosong."]);
        return;
    }

    try {
        $query = "SELECT * FROM karyawan WHERE nik = :nik LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nik', $nik);
        $stmt->execute();
        $karyawan = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($karyawan) {
            echo json_encode(["success" => true, "data" => $karyawan]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Data karyawan tidak ditemukan."]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Kesalahan Internal: " . $e->getMessage()]);
    }
}

/**
 * 18. Helper untuk Mencegah Circular Reporting Line
 */
function isCircularManager($db, $nik, $manager_nik) {
    $current_manager = $manager_nik;
    for ($i = 0; $i < 50; $i++) { 
        if (empty($current_manager)) return false;
        if ($current_manager === $nik) return true;
        
        $stmt = $db->prepare("SELECT manager_nik FROM karyawan WHERE nik = :nik LIMIT 1");
        $stmt->bindParam(':nik', $current_manager);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        $current_manager = $row['manager_nik'];
    }
    return true; 
}

/**
 * 19. Perbarui Detail Profil & Dokumen Karyawan
 */
function updateKaryawanDetail($db) {
    // Because we are using multipart/form-data, we get values in $_POST
    $nik = $_POST['nik'] ?? '';
    if (empty($nik)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "NIK tidak boleh kosong."]);
        return;
    }

    try {
        $nama = $_POST['nama'] ?? '';
        $divisi = $_POST['divisi'] ?? '';
        $jabatan = $_POST['jabatan'] ?? '';
        $email = $_POST['email'] ?? null;
        $telepon = $_POST['telepon'] ?? null;
        $jenis_kelamin = $_POST['jenis_kelamin'] ?? null;
        $tempat_lahir = $_POST['tempat_lahir'] ?? null;
        $tanggal_lahir = (!empty($_POST['tanggal_lahir'])) ? $_POST['tanggal_lahir'] : null;
        $alamat = $_POST['alamat'] ?? null;
        $status_kerja = $_POST['status_kerja'] ?? 'PKWT';
        $manager_nik = !empty($_POST['manager_nik']) ? $_POST['manager_nik'] : null;
        
        $tanggal_mulai_kerja = !empty($_POST['tanggal_mulai_kerja']) ? $_POST['tanggal_mulai_kerja'] : null;
        $tanggal_selesai_kontrak = !empty($_POST['tanggal_selesai_kontrak']) ? $_POST['tanggal_selesai_kontrak'] : null;
        $tanggal_selesai_probation = !empty($_POST['tanggal_selesai_probation']) ? $_POST['tanggal_selesai_probation'] : null;
        
        $kontak_darurat_nama = $_POST['kontak_darurat_nama'] ?? null;
        $kontak_darurat_hubungan = $_POST['kontak_darurat_hubungan'] ?? null;
        $kontak_darurat_telepon = $_POST['kontak_darurat_telepon'] ?? null;

        // Validasi atasan langsung
        if ($manager_nik === $nik) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Karyawan tidak boleh menjadi atasan bagi dirinya sendiri."]);
            return;
        }

        if ($manager_nik && isCircularManager($db, $nik, $manager_nik)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Gagal menetapkan atasan: Terdeteksi alur pelaporan melingkar (circular reference)."]);
            return;
        }

        // Fetch existing document paths from DB first in case no new files are uploaded
        $querySelect = "SELECT file_ktp, file_npwp, file_kontrak FROM karyawan WHERE nik = :nik LIMIT 1";
        $stmtSelect = $db->prepare($querySelect);
        $stmtSelect->bindParam(':nik', $nik);
        $stmtSelect->execute();
        $currentKaryawan = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        $file_ktp = $currentKaryawan['file_ktp'] ?? null;
        $file_npwp = $currentKaryawan['file_npwp'] ?? null;
        $file_kontrak = $currentKaryawan['file_kontrak'] ?? null;

        // Process File Uploads
        $uploadDir = 'uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $documentTypes = ['file_ktp', 'file_npwp', 'file_kontrak'];
        foreach ($documentTypes as $docType) {
            if (isset($_FILES[$docType]) && $_FILES[$docType]['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES[$docType]['tmp_name'];
                $fileName = $_FILES[$docType]['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                // Sanitize filename
                $newFileName = $nik . "_" . str_replace('file_', '', $docType) . "_" . time() . "." . $fileExtension;
                $destPath = $uploadDir . $newFileName;
                
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    // Remove old physical file if exists
                    $oldFile = $$docType;
                    if ($oldFile && file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                    $$docType = $destPath;
                }
            }
        }

        // Update karyawan data in database
        $queryUpdate = "UPDATE karyawan SET 
            nama = :nama,
            divisi = :divisi,
            jabatan = :jabatan,
            email = :email,
            telepon = :telepon,
            jenis_kelamin = :jenis_kelamin,
            tempat_lahir = :tempat_lahir,
            tanggal_lahir = :tanggal_lahir,
            alamat = :alamat,
            manager_nik = :manager_nik,
            status_kerja = :status_kerja,
            tanggal_mulai_kerja = :tanggal_mulai_kerja,
            tanggal_selesai_kontrak = :tanggal_selesai_kontrak,
            tanggal_selesai_probation = :tanggal_selesai_probation,
            kontak_darurat_nama = :kontak_darurat_nama,
            kontak_darurat_hubungan = :kontak_darurat_hubungan,
            kontak_darurat_telepon = :kontak_darurat_telepon,
            file_ktp = :file_ktp,
            file_npwp = :file_npwp,
            file_kontrak = :file_kontrak
            WHERE nik = :nik";

        $stmtUpdate = $db->prepare($queryUpdate);
        $stmtUpdate->bindParam(':nama', $nama);
        $stmtUpdate->bindParam(':divisi', $divisi);
        $stmtUpdate->bindParam(':jabatan', $jabatan);
        $stmtUpdate->bindParam(':email', $email);
        $stmtUpdate->bindParam(':telepon', $telepon);
        $stmtUpdate->bindParam(':jenis_kelamin', $jenis_kelamin);
        $stmtUpdate->bindParam(':tempat_lahir', $tempat_lahir);
        $stmtUpdate->bindParam(':tanggal_lahir', $tanggal_lahir);
        $stmtUpdate->bindParam(':alamat', $alamat);
        $stmtUpdate->bindParam(':manager_nik', $manager_nik);
        $stmtUpdate->bindParam(':status_kerja', $status_kerja);
        $stmtUpdate->bindParam(':tanggal_mulai_kerja', $tanggal_mulai_kerja);
        $stmtUpdate->bindParam(':tanggal_selesai_kontrak', $tanggal_selesai_kontrak);
        $stmtUpdate->bindParam(':tanggal_selesai_probation', $tanggal_selesai_probation);
        $stmtUpdate->bindParam(':kontak_darurat_nama', $kontak_darurat_nama);
        $stmtUpdate->bindParam(':kontak_darurat_hubungan', $kontak_darurat_hubungan);
        $stmtUpdate->bindParam(':kontak_darurat_telepon', $kontak_darurat_telepon);
        $stmtUpdate->bindParam(':file_ktp', $file_ktp);
        $stmtUpdate->bindParam(':file_npwp', $file_npwp);
        $stmtUpdate->bindParam(':file_kontrak', $file_kontrak);
        $stmtUpdate->bindParam(':nik', $nik);

        if ($stmtUpdate->execute()) {
            echo json_encode(["success" => true, "message" => "Data detail karyawan berhasil diperbarui."]);
        } else {
            throw new Exception("Gagal mengupdate data karyawan ke database.");
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Kesalahan Internal: " . $e->getMessage()]);
    }
}

/**
 * 19. Ambil Riwayat Jabatan
 */
function getRiwayatJabatan($db) {
    $nik = $_GET['nik'] ?? '';
    if (empty($nik)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "NIK tidak boleh kosong."]);
        return;
    }
    try {
        $query = "SELECT * FROM riwayat_jabatan WHERE nik = :nik ORDER BY tanggal_mulai DESC, id DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nik', $nik);
        $stmt->execute();
        echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Kesalahan: " . $e->getMessage()]);
    }
}

/**
 * 20. Tambah Riwayat Jabatan
 */
function addRiwayatJabatan($db) {
    $data = json_decode(file_get_contents("php://input"));
    if (empty($data->nik) || empty($data->jabatan) || empty($data->tanggal_mulai)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Data riwayat jabatan tidak lengkap."]);
        return;
    }
    try {
        $tanggal_selesai = (!empty($data->tanggal_selesai)) ? $data->tanggal_selesai : null;
        $keterangan = $data->keterangan ?? null;

        $db->beginTransaction();

        $query = "INSERT INTO riwayat_jabatan (nik, jabatan, tanggal_mulai, tanggal_selesai, keterangan) 
                  VALUES (:nik, :jabatan, :tanggal_mulai, :tanggal_selesai, :keterangan)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nik', $data->nik);
        $stmt->bindParam(':jabatan', $data->jabatan);
        $stmt->bindParam(':tanggal_mulai', $data->tanggal_mulai);
        $stmt->bindParam(':tanggal_selesai', $tanggal_selesai);
        $stmt->bindParam(':keterangan', $keterangan);
        $stmt->execute();

        // Sync current position in karyawan table to match this new entry if it's the latest
        $syncQuery = "UPDATE karyawan SET jabatan = :jabatan WHERE nik = :nik";
        $stmtSync = $db->prepare($syncQuery);
        $stmtSync->bindParam(':jabatan', $data->jabatan);
        $stmtSync->bindParam(':nik', $data->nik);
        $stmtSync->execute();

        $db->commit();

        echo json_encode(["success" => true, "message" => "Riwayat jabatan berhasil ditambahkan dan disinkronkan."]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal menambahkan riwayat jabatan: " . $e->getMessage()]);
    }
}

/**
 * 21. Hapus Riwayat Jabatan
 */
function deleteRiwayatJabatan($db) {
    $data = json_decode(file_get_contents("php://input"));
    if (empty($data->id) || empty($data->nik)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID dan NIK tidak boleh kosong."]);
        return;
    }
    try {
        $db->beginTransaction();

        $query = "DELETE FROM riwayat_jabatan WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $data->id);
        $stmt->execute();

        // Restore current jabatan in karyawan table to the next latest in riwayat_jabatan
        $latestQuery = "SELECT jabatan FROM riwayat_jabatan WHERE nik = :nik ORDER BY tanggal_mulai DESC, id DESC LIMIT 1";
        $stmtLatest = $db->prepare($latestQuery);
        $stmtLatest->bindParam(':nik', $data->nik);
        $stmtLatest->execute();
        $latest = $stmtLatest->fetch(PDO::FETCH_ASSOC);

        $newJabatan = $latest ? $latest['jabatan'] : 'Staff';
        $syncQuery = "UPDATE karyawan SET jabatan = :jabatan WHERE nik = :nik";
        $stmtSync = $db->prepare($syncQuery);
        $stmtSync->bindParam(':jabatan', $newJabatan);
        $stmtSync->bindParam(':nik', $data->nik);
        $stmtSync->execute();

        $db->commit();

        echo json_encode(["success" => true, "message" => "Riwayat jabatan berhasil dihapus."]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal menghapus riwayat jabatan: " . $e->getMessage()]);
    }
}

/**
 * 22. Ambil Notifikasi/Peringatan Kontrak & Masa Percobaan (Probation)
 */
function getContractAlerts($db) {
    try {
        // Use a 60-day lookahead window so HR has ample time to act
        $query = "SELECT 
                    nik, nama, divisi, jabatan, status_kerja,
                    tanggal_mulai_kerja, tanggal_selesai_kontrak, tanggal_selesai_probation,
                    DATEDIFF(tanggal_selesai_kontrak, CURDATE())    AS sisa_kontrak,
                    DATEDIFF(tanggal_selesai_probation, CURDATE())  AS sisa_probation
                  FROM karyawan 
                  WHERE 
                    (status_kerja = 'PKWT'  AND tanggal_selesai_kontrak   IS NOT NULL 
                        AND tanggal_selesai_kontrak   <= DATE_ADD(CURDATE(), INTERVAL 60 DAY))
                    OR 
                    (tanggal_selesai_probation IS NOT NULL 
                        AND tanggal_selesai_probation <= DATE_ADD(CURDATE(), INTERVAL 60 DAY))
                  ORDER BY 
                    LEAST(
                        IFNULL(tanggal_selesai_kontrak,   '9999-12-31'),
                        IFNULL(tanggal_selesai_probation, '9999-12-31')
                    ) ASC";

        $stmt = $db->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enrich each row with urgency metadata
        $alerts = [];
        foreach ($rows as $row) {
            // Determine the relevant days_remaining and type for this alert
            $items = [];

            // Contract expiry alert (PKWT only)
            if ($row['status_kerja'] === 'PKWT' && !is_null($row['sisa_kontrak'])) {
                $days = (int)$row['sisa_kontrak'];
                if ($days <= 60) {
                    $urgency = $days <= 0  ? 'expired'
                             : ($days <= 7  ? 'critical'
                             : ($days <= 30 ? 'warning'
                             : 'notice'));
                    $items[] = [
                        'type'          => 'kontrak',
                        'label'         => 'Kontrak PKWT',
                        'tanggal_akhir' => $row['tanggal_selesai_kontrak'],
                        'days'          => $days,
                        'urgency'       => $urgency,
                    ];
                }
            }

            // Probation expiry alert (any status_kerja)
            if (!is_null($row['sisa_probation'])) {
                $days = (int)$row['sisa_probation'];
                if ($days <= 60) {
                    $urgency = $days <= 0  ? 'expired'
                             : ($days <= 7  ? 'critical'
                             : ($days <= 30 ? 'warning'
                             : 'notice'));
                    $items[] = [
                        'type'          => 'probation',
                        'label'         => 'Masa Probation',
                        'tanggal_akhir' => $row['tanggal_selesai_probation'],
                        'days'          => $days,
                        'urgency'       => $urgency,
                    ];
                }
            }

            foreach ($items as $item) {
                $alerts[] = array_merge([
                    'nik'         => $row['nik'],
                    'nama'        => $row['nama'],
                    'divisi'      => $row['divisi'],
                    'jabatan'     => $row['jabatan'],
                    'status_kerja'=> $row['status_kerja'],
                ], $item);
            }
        }

        echo json_encode(["success" => true, "data" => $alerts, "total" => count($alerts)]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal memuat notifikasi kontrak: " . $e->getMessage()]);
    }
}
?>