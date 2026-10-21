<?php
// ==============================================================================
// MODUL PAYROLL LANJUTAN - HRIS CORE API
// Kalender kerja & shift, lembur PP 35/2021, saldo cuti, prorata, THR, komponen
// gaji tambahan, alur persetujuan payroll, audit trail, transfer bank, email slip,
// dan bukti potong 1721-A1. Di-include oleh absensi_api.php.
// ==============================================================================

/**
 * Skema tabel & kolom modul lanjutan (idempotent, dipanggil saat koneksi dibuat)
 */
function ensurePayrollExtSchema(PDO $c) {
    $c->exec("CREATE TABLE IF NOT EXISTS shift (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL,
        jam_masuk TIME NOT NULL,
        jam_pulang TIME NOT NULL,
        hari_kerja VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5',
        istirahat_menit INT NOT NULL DEFAULT 60,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $c->exec("CREATE TABLE IF NOT EXISTS hari_libur (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL UNIQUE,
        nama VARCHAR(150) NOT NULL,
        jenis ENUM('nasional', 'cuti_bersama', 'perusahaan') NOT NULL DEFAULT 'nasional',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $c->exec("CREATE TABLE IF NOT EXISTS lembur (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nik VARCHAR(50) NOT NULL,
        nama VARCHAR(150) NOT NULL,
        tanggal DATE NOT NULL,
        jam DECIMAL(5,2) NOT NULL,
        jenis_hari ENUM('kerja', 'libur') NOT NULL DEFAULT 'kerja',
        sumber ENUM('absensi', 'manual') NOT NULL DEFAULT 'absensi',
        status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
        keterangan VARCHAR(255) NULL,
        diproses_oleh VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_lembur (nik, tanggal)
    )");
    $c->exec("CREATE TABLE IF NOT EXISTS payroll_periode (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bulan VARCHAR(20) NOT NULL,
        tahun INT NOT NULL,
        status ENUM('draft', 'diajukan', 'disetujui', 'dibayar') NOT NULL DEFAULT 'draft',
        thr_config TEXT NULL,
        catatan VARCHAR(255) NULL,
        dibuat_oleh VARCHAR(100) NULL,
        diajukan_oleh VARCHAR(100) NULL,
        disetujui_oleh VARCHAR(100) NULL,
        dibayar_oleh VARCHAR(100) NULL,
        diajukan_at DATETIME NULL,
        disetujui_at DATETIME NULL,
        dibayar_at DATETIME NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_periode (bulan, tahun)
    )");
    $c->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        pengguna VARCHAR(100) NULL,
        peran VARCHAR(20) NULL,
        aksi VARCHAR(60) NOT NULL,
        entitas VARCHAR(60) NULL,
        entitas_id VARCHAR(100) NULL,
        data_lama MEDIUMTEXT NULL,
        data_baru MEDIUMTEXT NULL,
        ip VARCHAR(45) NULL,
        INDEX idx_audit_waktu (waktu)
    )");
    $c->exec("CREATE TABLE IF NOT EXISTS komponen_gaji (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL,
        jenis ENUM('pendapatan', 'potongan') NOT NULL,
        kena_pajak TINYINT NOT NULL DEFAULT 1,
        aktif TINYINT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $c->exec("CREATE TABLE IF NOT EXISTS komponen_karyawan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nik VARCHAR(50) NOT NULL,
        komponen_id INT NOT NULL,
        jumlah DECIMAL(14,2) NOT NULL,
        berulang TINYINT NOT NULL DEFAULT 0,
        bulan VARCHAR(20) NULL,
        tahun INT NULL,
        keterangan VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    try {
        $c->query("SELECT shift_id FROM karyawan LIMIT 1");
    } catch (Exception $e) {
        foreach (["shift_id INT NULL", "tanggal_keluar DATE NULL", "bank_nama VARCHAR(50) NULL",
                  "bank_rekening VARCHAR(40) NULL", "bank_atas_nama VARCHAR(150) NULL"] as $col) {
            try { $c->exec("ALTER TABLE karyawan ADD COLUMN $col"); } catch (Exception $ex) {}
        }
    }
    try {
        $c->query("SELECT thr FROM penggajian LIMIT 1");
    } catch (Exception $e) {
        foreach (["thr DECIMAL(14,2) NOT NULL DEFAULT 0.00", "komponen_tambahan TEXT NULL",
                  "tambahan_pendapatan DECIMAL(14,2) NOT NULL DEFAULT 0.00", "tambahan_potongan DECIMAL(14,2) NOT NULL DEFAULT 0.00",
                  "prorata_faktor DECIMAL(6,4) NOT NULL DEFAULT 1.0000", "hari_kerja_aktif INT NOT NULL DEFAULT 0",
                  "hari_kerja_bulan INT NOT NULL DEFAULT 0", "rincian_lembur TEXT NULL", "email_terkirim_at DATETIME NULL"] as $col) {
            try { $c->exec("ALTER TABLE penggajian ADD COLUMN $col"); } catch (Exception $ex) {}
        }
    }
    try {
        $c->query("SELECT jumlah_hari FROM cuti LIMIT 1");
    } catch (Exception $e) {
        try { $c->exec("ALTER TABLE cuti ADD COLUMN jumlah_hari INT NULL"); } catch (Exception $ex) {}
    }

    // Periode lama (sebelum ada alur persetujuan) didaftarkan sebagai draft
    $c->exec("INSERT IGNORE INTO payroll_periode (bulan, tahun, status, dibuat_oleh)
              SELECT DISTINCT bulan, tahun, 'draft', 'migrasi' FROM penggajian");
}

// ------------------------------------------------------------------------------
// PENGGUNA & AUDIT TRAIL
// ------------------------------------------------------------------------------
function hrisActor() {
    $user = trim(rawurldecode($_SERVER['HTTP_X_HRIS_USER'] ?? ''));
    $role = trim($_SERVER['HTTP_X_HRIS_ROLE'] ?? '');
    return [
        'user' => $user !== '' ? mb_substr($user, 0, 100) : 'sistem',
        'role' => in_array($role, ['admin', 'direktur', 'karyawan'], true) ? $role : null
    ];
}

function auditLog($db, $aksi, $entitas = null, $entitasId = null, $lama = null, $baru = null) {
    try {
        $actor = hrisActor();
        $enc = function ($v) {
            if ($v === null) return null;
            $json = is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return mb_substr($json, 0, 60000);
        };
        $db->prepare("INSERT INTO audit_log (pengguna, peran, aksi, entitas, entitas_id, data_lama, data_baru, ip)
                      VALUES (:u, :r, :a, :e, :id, :lama, :baru, :ip)")
           ->execute([
               ':u' => $actor['user'], ':r' => $actor['role'], ':a' => $aksi, ':e' => $entitas,
               ':id' => $entitasId !== null ? (string)$entitasId : null,
               ':lama' => $enc($lama), ':baru' => $enc($baru), ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
           ]);
    } catch (Exception $e) {
        // Audit tidak boleh menggagalkan transaksi utama
    }
}

/**
 * Daftar aksi yang dicatat otomatis oleh router (payload request disimpan tanpa data sensitif)
 */
function auditedActions() {
    return [
        'register_karyawan' => 'karyawan', 'delete_karyawan' => 'karyawan', 'update_karyawan_detail' => 'karyawan',
        'add_user' => 'akun', 'delete_user' => 'akun', 'add_leave' => 'cuti', 'save_settings' => 'pengaturan',
        'apply_payroll_defaults' => 'karyawan', 'add_kasbon' => 'kasbon', 'delete_kasbon' => 'kasbon',
        'add_reimbursement' => 'reimbursement', 'update_reimbursement_status' => 'reimbursement',
        'delete_reimbursement' => 'reimbursement', 'add_riwayat_jabatan' => 'jabatan', 'delete_riwayat_jabatan' => 'jabatan',
        'sync_lembur' => 'lembur', 'save_lembur' => 'lembur', 'update_lembur_status' => 'lembur', 'delete_lembur' => 'lembur',
        'save_shift' => 'shift', 'delete_shift' => 'shift', 'save_hari_libur' => 'hari_libur', 'delete_hari_libur' => 'hari_libur',
        'seed_hari_libur' => 'hari_libur', 'save_komponen' => 'komponen_gaji', 'delete_komponen' => 'komponen_gaji',
        'save_komponen_karyawan' => 'komponen_karyawan', 'delete_komponen_karyawan' => 'komponen_karyawan',
        'send_payslip_email' => 'email', 'export_bank_transfer' => 'penggajian', 'review_absensi_foto' => 'absensi'
    ];
}

function sanitizeAuditPayload($raw) {
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        // multipart/form-data (update_karyawan_detail)
        $data = $_POST ?: null;
    }
    if (!is_array($data)) return null;
    foreach (['image_base64', 'password', 'slip_logo', 'smtp_pass'] as $secret) {
        if (isset($data[$secret])) $data[$secret] = '[disembunyikan]';
    }
    return $data;
}

function getAuditLog($db) {
    try {
        $where = [];
        $params = [];
        foreach (['pengguna' => 'pengguna', 'entitas' => 'entitas', 'aksi' => 'aksi'] as $param => $col) {
            if (!empty($_GET[$param])) {
                $where[] = "$col = :$param";
                $params[":$param"] = $_GET[$param];
            }
        }
        if (!empty($_GET['dari'])) { $where[] = "waktu >= :dari"; $params[':dari'] = $_GET['dari'] . ' 00:00:00'; }
        if (!empty($_GET['sampai'])) { $where[] = "waktu <= :sampai"; $params[':sampai'] = $_GET['sampai'] . ' 23:59:59'; }
        if (!empty($_GET['q'])) {
            $where[] = "(entitas_id LIKE :q OR data_baru LIKE :q OR data_lama LIKE :q)";
            $params[':q'] = '%' . $_GET['q'] . '%';
        }
        $limit = max(1, min(500, intval($_GET['limit'] ?? 200)));
        $sql = "SELECT * FROM audit_log" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY id DESC LIMIT $limit";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $entitas = $db->query("SELECT DISTINCT entitas FROM audit_log WHERE entitas IS NOT NULL ORDER BY entitas")->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC), "entitas" => $entitas]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

function requireRole($roles) {
    $role = hrisActor()['role'];
    if (!in_array($role, (array)$roles, true)) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Akses ditolak untuk peran ini."]);
        return false;
    }
    return true;
}

function jsonInput() {
    return json_decode(file_get_contents("php://input"), true) ?: [];
}

// ------------------------------------------------------------------------------
// KALENDER KERJA: SHIFT & HARI LIBUR
// ------------------------------------------------------------------------------
function defaultShift($db) {
    $set = $db->query("SELECT jam_masuk, jam_pulang FROM pengaturan ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'id' => null, 'nama' => 'Jam Kantor (Default)',
        'jam_masuk' => $set['jam_masuk'] ?? '08:30:00', 'jam_pulang' => $set['jam_pulang'] ?? '17:00:00',
        'hari_kerja' => '1,2,3,4,5', 'istirahat_menit' => 60
    ];
}

function shiftMap($db) {
    $map = [];
    foreach ($db->query("SELECT * FROM shift")->fetchAll(PDO::FETCH_ASSOC) as $row) $map[$row['id']] = $row;
    return $map;
}

function shiftForEmployee($shiftId, $map, $default) {
    return ($shiftId && isset($map[$shiftId])) ? $map[$shiftId] : $default;
}

function shiftDays($shift) {
    return array_values(array_filter(array_map('intval', explode(',', $shift['hari_kerja'] ?? '1,2,3,4,5')), fn($d) => $d >= 1 && $d <= 7));
}

function holidayMap($db, $start, $end) {
    $stmt = $db->prepare("SELECT tanggal, nama, jenis FROM hari_libur WHERE tanggal BETWEEN :s AND :e");
    $stmt->execute([':s' => $start, ':e' => $end]);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $h) $map[$h['tanggal']] = $h;
    return $map;
}

function isWorkday($date, $days, $holidays) {
    return in_array(intval(date('N', strtotime($date))), $days, true) && !isset($holidays[$date]);
}

function countWorkdays($start, $end, $days, $holidays) {
    $count = 0;
    for ($t = strtotime($start); $t <= strtotime($end); $t = strtotime('+1 day', $t)) {
        if (isWorkday(date('Y-m-d', $t), $days, $holidays)) $count++;
    }
    return $count;
}

function getShifts($db) {
    try {
        $rows = $db->query("SELECT s.*, (SELECT COUNT(*) FROM karyawan k WHERE k.shift_id = s.id) AS jumlah_karyawan FROM shift s ORDER BY s.nama")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["success" => true, "data" => $rows, "default" => defaultShift($db)]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

function saveShift($db) {
    $d = jsonInput();
    $days = array_values(array_unique(array_filter(array_map('intval', (array)($d['hari_kerja'] ?? [])), fn($x) => $x >= 1 && $x <= 7)));
    sort($days);
    if (empty($d['nama']) || empty($d['jam_masuk']) || empty($d['jam_pulang']) || empty($days)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Nama, jam masuk, jam pulang, dan hari kerja wajib diisi."]);
        return;
    }
    try {
        $params = [
            ':nama' => mb_substr(trim($d['nama']), 0, 100), ':masuk' => $d['jam_masuk'], ':pulang' => $d['jam_pulang'],
            ':hari' => implode(',', $days), ':ist' => max(0, intval($d['istirahat_menit'] ?? 60))
        ];
        if (!empty($d['id'])) {
            $params[':id'] = intval($d['id']);
            $db->prepare("UPDATE shift SET nama = :nama, jam_masuk = :masuk, jam_pulang = :pulang, hari_kerja = :hari, istirahat_menit = :ist WHERE id = :id")->execute($params);
        } else {
            $db->prepare("INSERT INTO shift (nama, jam_masuk, jam_pulang, hari_kerja, istirahat_menit) VALUES (:nama, :masuk, :pulang, :hari, :ist)")->execute($params);
        }
        echo json_encode(["success" => true, "message" => "Shift berhasil disimpan."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal menyimpan shift: " . $e->getMessage()]);
    }
}

function deleteShift($db) {
    $d = jsonInput();
    try {
        $db->prepare("UPDATE karyawan SET shift_id = NULL WHERE shift_id = :id")->execute([':id' => intval($d['id'] ?? 0)]);
        $db->prepare("DELETE FROM shift WHERE id = :id")->execute([':id' => intval($d['id'] ?? 0)]);
        echo json_encode(["success" => true, "message" => "Shift dihapus. Karyawan terkait kembali ke jam kantor default."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

function getHariLibur($db) {
    try {
        $tahun = intval($_GET['tahun'] ?? date('Y'));
        $stmt = $db->prepare("SELECT * FROM hari_libur WHERE YEAR(tanggal) = :t ORDER BY tanggal");
        $stmt->execute([':t' => $tahun]);
        echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

function saveHariLibur($db) {
    $d = jsonInput();
    $jenis = in_array($d['jenis'] ?? '', ['nasional', 'cuti_bersama', 'perusahaan'], true) ? $d['jenis'] : 'nasional';
    if (empty($d['tanggal']) || empty($d['nama']) || !strtotime($d['tanggal'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Tanggal dan nama hari libur wajib diisi."]);
        return;
    }
    try {
        $db->prepare("INSERT INTO hari_libur (tanggal, nama, jenis) VALUES (:t, :n, :j) ON DUPLICATE KEY UPDATE nama = VALUES(nama), jenis = VALUES(jenis)")
           ->execute([':t' => date('Y-m-d', strtotime($d['tanggal'])), ':n' => mb_substr(trim($d['nama']), 0, 150), ':j' => $jenis]);
        echo json_encode(["success" => true, "message" => "Hari libur disimpan."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

function deleteHariLibur($db) {
    $d = jsonInput();
    try {
        $db->prepare("DELETE FROM hari_libur WHERE id = :id")->execute([':id' => intval($d['id'] ?? 0)]);
        echo json_encode(["success" => true, "message" => "Hari libur dihapus."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

/**
 * Isi libur nasional bertanggal tetap. Libur berdasarkan kalender Hijriah/Imlek/Saka
 * dan cuti bersama berubah setiap tahun sehingga perlu diisi manual dari SKB 3 Menteri.
 */
function seedHariLibur($db) {
    $d = jsonInput();
    $tahun = intval($d['tahun'] ?? date('Y'));
    $fixed = [
        '01-01' => 'Tahun Baru Masehi', '05-01' => 'Hari Buruh Internasional', '06-01' => 'Hari Lahir Pancasila',
        '08-17' => 'Hari Kemerdekaan Republik Indonesia', '12-25' => 'Hari Raya Natal'
    ];
    try {
        $stmt = $db->prepare("INSERT IGNORE INTO hari_libur (tanggal, nama, jenis) VALUES (:t, :n, 'nasional')");
        $added = 0;
        foreach ($fixed as $md => $nama) {
            $stmt->execute([':t' => "$tahun-$md", ':n' => $nama]);
            $added += $stmt->rowCount();
        }
        echo json_encode(["success" => true, "message" => "$added libur nasional bertanggal tetap ditambahkan untuk $tahun. Tambahkan libur keagamaan & cuti bersama sesuai SKB 3 Menteri."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

// ------------------------------------------------------------------------------
// LEMBUR (PP 35/2021)
// ------------------------------------------------------------------------------
/**
 * Jam lembur ekuivalen (pengali upah sejam) sesuai PP 35/2021 Pasal 31
 * Hari kerja  : jam ke-1 ×1,5; jam berikutnya ×2
 * Hari libur  : 5 hari kerja/minggu → jam 1–8 ×2, jam 9 ×3, jam 10–11 ×4
 *               6 hari kerja/minggu → jam 1–7 ×2, jam 8 ×3, jam 9–10 ×4
 */
function lemburEkuivalen($jam, $jenis, $hariPerMinggu) {
    $jam = max(0, (double)$jam);
    if ($jam <= 0) return 0.0;
    if ($jenis !== 'libur') return min($jam, 1) * 1.5 + max(0, $jam - 1) * 2;
    $batas = $hariPerMinggu >= 6 ? 7 : 8;
    $ekv = min($jam, $batas) * 2;
    $sisa = $jam - $batas;
    if ($sisa > 0) { $ekv += min($sisa, 1) * 3; $sisa -= 1; }
    if ($sisa > 0) { $ekv += $sisa * 4; }
    return $ekv;
}

/**
 * Tarik jam lembur dari log absensi "Pulang" ke tabel lembur (tidak menimpa data yang sudah ada/diedit)
 */
function syncLemburFromAbsensi($db, $startDate, $endDate, $autoApprove) {
    $holidays = holidayMap($db, $startDate, $endDate);
    $shifts = shiftMap($db);
    $default = defaultShift($db);
    $emps = [];
    foreach ($db->query("SELECT nik, shift_id FROM karyawan")->fetchAll(PDO::FETCH_ASSOC) as $e) $emps[$e['nik']] = $e['shift_id'];

    $stmt = $db->prepare("SELECT nik, nama, DATE(scan_time) AS tanggal, MAX(overtime_hours) AS jam
                          FROM log_absensi WHERE status = 'Pulang' AND overtime_hours > 0 AND scan_time BETWEEN :s AND :e
                          GROUP BY nik, nama, DATE(scan_time)");
    $stmt->execute([':s' => "$startDate 00:00:00", ':e' => "$endDate 23:59:59"]);
    $ins = $db->prepare("INSERT IGNORE INTO lembur (nik, nama, tanggal, jam, jenis_hari, sumber, status, diproses_oleh)
                         VALUES (:nik, :nama, :tgl, :jam, :jenis, 'absensi', :status, :oleh)");
    $added = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $shift = shiftForEmployee($emps[$r['nik']] ?? null, $shifts, $default);
        $jenis = isWorkday($r['tanggal'], shiftDays($shift), $holidays) ? 'kerja' : 'libur';
        $ins->execute([
            ':nik' => $r['nik'], ':nama' => $r['nama'], ':tgl' => $r['tanggal'], ':jam' => $r['jam'], ':jenis' => $jenis,
            ':status' => $autoApprove ? 'approved' : 'pending', ':oleh' => $autoApprove ? 'otomatis' : null
        ]);
        $added += $ins->rowCount();
    }
    return $added;
}

function periodRange($bulan, $tahun) {
    $map = bulanMap();
    if (!isset($map[$bulan]) || intval($tahun) <= 0) return null;
    $start = intval($tahun) . '-' . $map[$bulan] . '-01';
    return [$start, date('Y-m-t', strtotime($start)), $map[$bulan]];
}

function getLembur($db) {
    $range = periodRange($_GET['bulan'] ?? '', $_GET['tahun'] ?? 0);
    if (!$range) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Periode tidak valid."]);
        return;
    }
    try {
        $sql = "SELECT l.*, k.gaji_pokok, k.tunjangan, k.status_kerja, k.shift_id FROM lembur l LEFT JOIN karyawan k ON k.nik = l.nik COLLATE utf8mb4_unicode_ci
                WHERE l.tanggal BETWEEN :s AND :e";
        $params = [':s' => $range[0], ':e' => $range[1]];
        if (!empty($_GET['nik']) && $_GET['nik'] !== 'null') {
            $sql .= " AND l.nik = :nik";
            $params[':nik'] = $_GET['nik'];
        }
        $stmt = $db->prepare($sql . " ORDER BY l.tanggal DESC, l.nama");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Lengkapi estimasi upah lembur sesuai metode aktif
        $cfg = payrollConfig($db);
        $shifts = shiftMap($db);
        $default = defaultShift($db);
        foreach ($rows as &$r) {
            $shift = shiftForEmployee($r['shift_id'], $shifts, $default);
            $ekv = lemburEkuivalen($r['jam'], $r['jenis_hari'], count(shiftDays($shift)));
            $r['ekuivalen'] = round($ekv, 2);
            $upah = (double)$r['gaji_pokok'] + (($r['status_kerja'] === 'PKWTT' || !$cfg['tunjangan_hanya_pkwtt']) ? (double)$r['tunjangan'] : 0);
            $r['estimasi_upah'] = $cfg['lembur_metode'] === 'pp35' ? round($ekv * $upah / $cfg['lembur_pembagi']) : null;
            $r['melebihi_batas'] = (double)$r['jam'] > $cfg['lembur_maks_harian'];
            unset($r['gaji_pokok'], $r['tunjangan']);
        }
        echo json_encode(["success" => true, "data" => $rows, "metode" => $cfg['lembur_metode'], "perlu_persetujuan" => $cfg['lembur_perlu_persetujuan']]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

function syncLembur($db) {
    $d = jsonInput();
    $range = periodRange($d['bulan'] ?? '', $d['tahun'] ?? 0);
    if (!$range) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Periode tidak valid."]);
        return;
    }
    try {
        $cfg = payrollConfig($db);
        $n = syncLemburFromAbsensi($db, $range[0], $range[1], !$cfg['lembur_perlu_persetujuan']);
        echo json_encode(["success" => true, "message" => "$n data lembur baru ditarik dari absensi."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

function saveLembur($db) {
    $d = jsonInput();
    $jam = (double)($d['jam'] ?? 0);
    if ($jam <= 0 || $jam > 24) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Jam lembur harus antara 0 dan 24."]);
        return;
    }
    $jenis = ($d['jenis_hari'] ?? '') === 'libur' ? 'libur' : 'kerja';
    try {
        if (!empty($d['id'])) {
            $db->prepare("UPDATE lembur SET jam = :jam, jenis_hari = :jenis, keterangan = :ket WHERE id = :id")
               ->execute([':jam' => $jam, ':jenis' => $jenis, ':ket' => $d['keterangan'] ?? null, ':id' => intval($d['id'])]);
        } else {
            $emp = $db->prepare("SELECT nama FROM karyawan WHERE nik = :nik");
            $emp->execute([':nik' => $d['nik'] ?? '']);
            $nama = $emp->fetchColumn();
            if (!$nama || empty($d['tanggal'])) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Karyawan dan tanggal wajib diisi."]);
                return;
            }
            $db->prepare("INSERT INTO lembur (nik, nama, tanggal, jam, jenis_hari, sumber, status, keterangan)
                          VALUES (:nik, :nama, :tgl, :jam, :jenis, 'manual', 'pending', :ket)
                          ON DUPLICATE KEY UPDATE jam = VALUES(jam), jenis_hari = VALUES(jenis_hari), keterangan = VALUES(keterangan), status = 'pending'")
               ->execute([':nik' => $d['nik'], ':nama' => $nama, ':tgl' => $d['tanggal'], ':jam' => $jam, ':jenis' => $jenis, ':ket' => $d['keterangan'] ?? null]);
        }
        echo json_encode(["success" => true, "message" => "Data lembur disimpan."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

function updateLemburStatus($db) {
    if (!requireRole(['admin', 'direktur'])) return;
    $d = jsonInput();
    $ids = array_map('intval', (array)($d['ids'] ?? [$d['id'] ?? 0]));
    $status = in_array($d['status'] ?? '', ['approved', 'rejected', 'pending'], true) ? $d['status'] : null;
    if (!$status || empty($ids)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Data persetujuan lembur tidak valid."]);
        return;
    }
    try {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("UPDATE lembur SET status = ?, diproses_oleh = ? WHERE id IN ($in)");
        $stmt->execute(array_merge([$status, hrisActor()['user']], $ids));
        echo json_encode(["success" => true, "message" => $stmt->rowCount() . " data lembur diperbarui."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

function deleteLembur($db) {
    $d = jsonInput();
    try {
        $db->prepare("DELETE FROM lembur WHERE id = :id")->execute([':id' => intval($d['id'] ?? 0)]);
        echo json_encode(["success" => true, "message" => "Data lembur dihapus."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

// ------------------------------------------------------------------------------
// CUTI: HARI KERJA & SALDO CUTI TAHUNAN
// ------------------------------------------------------------------------------
function leaveWorkdays($db, $nik, $start, $end) {
    $emp = $db->prepare("SELECT shift_id FROM karyawan WHERE nik = :nik");
    $emp->execute([':nik' => $nik]);
    $shift = shiftForEmployee($emp->fetchColumn(), shiftMap($db), defaultShift($db));
    return countWorkdays($start, $end, shiftDays($shift), holidayMap($db, $start, $end));
}

function saldoCuti($db, $nik, $tahun) {
    $cfg = payrollConfig($db);
    $emp = $db->prepare("SELECT tanggal_mulai_kerja FROM karyawan WHERE nik = :nik");
    $emp->execute([':nik' => $nik]);
    $mulai = $emp->fetchColumn();

    $tanggalBerhak = $mulai ? date('Y-m-d', strtotime("+{$cfg['cuti_syarat_bulan']} months", strtotime($mulai))) : null;
    $eligible = !$tanggalBerhak || $tanggalBerhak <= "$tahun-12-31";
    $jatah = $eligible ? $cfg['cuti_jatah'] : 0;

    $stmt = $db->prepare("SELECT id, tanggal_mulai, tanggal_selesai, jumlah_hari, status FROM cuti
                          WHERE nik = :nik AND jenis_cuti = 'Cuti Tahunan' AND status IN ('approved', 'pending') AND YEAR(tanggal_mulai) = :t");
    $stmt->execute([':nik' => $nik, ':t' => $tahun]);
    $terpakai = $pending = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $hari = $c['jumlah_hari'] !== null ? intval($c['jumlah_hari']) : leaveWorkdays($db, $nik, $c['tanggal_mulai'], $c['tanggal_selesai']);
        if ($c['status'] === 'approved') $terpakai += $hari; else $pending += $hari;
    }

    $cutiBersama = 0;
    if ($eligible && $cfg['cuti_bersama_potong']) {
        $cb = $db->prepare("SELECT COUNT(*) FROM hari_libur WHERE jenis = 'cuti_bersama' AND YEAR(tanggal) = :t");
        $cb->execute([':t' => $tahun]);
        $cutiBersama = intval($cb->fetchColumn());
    }
    return [
        'tahun' => intval($tahun), 'eligible' => $eligible, 'tanggal_berhak' => $tanggalBerhak,
        'jatah' => $jatah, 'terpakai' => $terpakai, 'cuti_bersama' => $cutiBersama, 'pending' => $pending,
        'sisa' => $jatah - $terpakai - $cutiBersama
    ];
}

function getSaldoCuti($db) {
    try {
        $tahun = intval($_GET['tahun'] ?? date('Y'));
        if (!empty($_GET['nik']) && $_GET['nik'] !== 'null') {
            echo json_encode(["success" => true, "data" => saldoCuti($db, $_GET['nik'], $tahun)]);
            return;
        }
        $rows = [];
        foreach ($db->query("SELECT nik, nama, tanggal_mulai_kerja FROM karyawan WHERE tanggal_keluar IS NULL OR tanggal_keluar >= CURDATE() ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC) as $e) {
            $rows[] = array_merge(['nik' => $e['nik'], 'nama' => $e['nama']], saldoCuti($db, $e['nik'], $tahun));
        }
        echo json_encode(["success" => true, "data" => $rows]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

function addLeave($db) {
    $data = json_decode(file_get_contents("php://input"));
    if (empty($data->nik) || empty($data->nama) || empty($data->jenis_cuti) || empty($data->tanggal_mulai) || empty($data->tanggal_selesai) || empty($data->alasan)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Data pengajuan cuti tidak lengkap."]);
        return;
    }
    if ($data->tanggal_mulai > $data->tanggal_selesai) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Tanggal mulai tidak boleh melebihi tanggal selesai."]);
        return;
    }
    try {
        $hari = leaveWorkdays($db, $data->nik, $data->tanggal_mulai, $data->tanggal_selesai);
        if ($hari <= 0) {
            echo json_encode(["success" => false, "message" => "Rentang tanggal tidak mengandung hari kerja (akhir pekan/hari libur)."]);
            return;
        }
        if ($data->jenis_cuti === 'Cuti Tahunan') {
            $saldo = saldoCuti($db, $data->nik, intval(substr($data->tanggal_mulai, 0, 4)));
            if (!$saldo['eligible'] || ($saldo['tanggal_berhak'] && $data->tanggal_mulai < $saldo['tanggal_berhak'])) {
                echo json_encode(["success" => false, "message" => "Belum berhak cuti tahunan. Hak cuti mulai " . $saldo['tanggal_berhak'] . "."]);
                return;
            }
            if ($hari > $saldo['sisa'] - $saldo['pending']) {
                echo json_encode(["success" => false, "message" => "Saldo cuti tidak cukup. Sisa {$saldo['sisa']} hari (menunggu persetujuan {$saldo['pending']} hari), diajukan $hari hari."]);
                return;
            }
        }
        $stmt = $db->prepare("INSERT INTO cuti (nik, nama, jenis_cuti, tanggal_mulai, tanggal_selesai, alasan, jumlah_hari)
                              VALUES (:nik, :nama, :jenis, :mulai, :selesai, :alasan, :hari)");
        $stmt->execute([
            ':nik' => $data->nik, ':nama' => $data->nama, ':jenis' => $data->jenis_cuti, ':mulai' => $data->tanggal_mulai,
            ':selesai' => $data->tanggal_selesai, ':alasan' => $data->alasan, ':hari' => $hari
        ]);
        echo json_encode(["success" => true, "message" => "Pengajuan cuti $hari hari kerja berhasil dikirim."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal mengirim pengajuan cuti: " . $e->getMessage()]);
    }
}

function updateLeaveStatus($db) {
    $data = json_decode(file_get_contents("php://input"));
    if (empty($data->id) || !in_array($data->status ?? '', ['approved', 'rejected', 'pending'], true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID dan status persetujuan harus diisi."]);
        return;
    }
    try {
        $old = $db->prepare("SELECT * FROM cuti WHERE id = :id");
        $old->execute([':id' => $data->id]);
        $cuti = $old->fetch(PDO::FETCH_ASSOC);
        if (!$cuti) {
            echo json_encode(["success" => false, "message" => "Pengajuan cuti tidak ditemukan."]);
            return;
        }
        if ($data->status === 'approved' && $cuti['jenis_cuti'] === 'Cuti Tahunan' && $cuti['status'] !== 'approved') {
            $saldo = saldoCuti($db, $cuti['nik'], intval(substr($cuti['tanggal_mulai'], 0, 4)));
            $hari = $cuti['jumlah_hari'] !== null ? intval($cuti['jumlah_hari']) : leaveWorkdays($db, $cuti['nik'], $cuti['tanggal_mulai'], $cuti['tanggal_selesai']);
            if ($hari > $saldo['sisa']) {
                echo json_encode(["success" => false, "message" => "Saldo cuti karyawan tidak cukup (sisa {$saldo['sisa']} hari, diajukan $hari hari)."]);
                return;
            }
        }
        $db->prepare("UPDATE cuti SET status = :status WHERE id = :id")->execute([':status' => $data->status, ':id' => $data->id]);
        auditLog($db, 'update_leave_status', 'cuti', $data->id, ['status' => $cuti['status']], ['status' => $data->status, 'nik' => $cuti['nik']]);
        echo json_encode(["success" => true, "message" => "Status pengajuan cuti diperbarui."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal memperbarui status cuti."]);
    }
}

// ------------------------------------------------------------------------------
// KOMPONEN GAJI TAMBAHAN (bonus, insentif, potongan lain, dll.)
// ------------------------------------------------------------------------------
function getKomponen($db) {
    try {
        $master = $db->query("SELECT * FROM komponen_gaji ORDER BY jenis, nama")->fetchAll(PDO::FETCH_ASSOC);
        $sql = "SELECT kk.*, kg.nama AS komponen, kg.jenis, kg.kena_pajak, k.nama AS nama_karyawan
                FROM komponen_karyawan kk JOIN komponen_gaji kg ON kg.id = kk.komponen_id LEFT JOIN karyawan k ON k.nik = kk.nik COLLATE utf8mb4_unicode_ci";
        $params = [];
        if (!empty($_GET['bulan']) && !empty($_GET['tahun'])) {
            $sql .= " WHERE kk.berulang = 1 OR (kk.bulan = :b AND kk.tahun = :t)";
            $params = [':b' => $_GET['bulan'], ':t' => intval($_GET['tahun'])];
        }
        $stmt = $db->prepare($sql . " ORDER BY k.nama, kg.nama");
        $stmt->execute($params);
        echo json_encode(["success" => true, "master" => $master, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

function saveKomponen($db) {
    $d = jsonInput();
    if (empty($d['nama']) || !in_array($d['jenis'] ?? '', ['pendapatan', 'potongan'], true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Nama dan jenis komponen wajib diisi."]);
        return;
    }
    try {
        $p = [':nama' => mb_substr(trim($d['nama']), 0, 100), ':jenis' => $d['jenis'],
              ':pajak' => !empty($d['kena_pajak']) ? 1 : 0, ':aktif' => isset($d['aktif']) ? (int)!empty($d['aktif']) : 1];
        if (!empty($d['id'])) {
            $p[':id'] = intval($d['id']);
            $db->prepare("UPDATE komponen_gaji SET nama = :nama, jenis = :jenis, kena_pajak = :pajak, aktif = :aktif WHERE id = :id")->execute($p);
        } else {
            $db->prepare("INSERT INTO komponen_gaji (nama, jenis, kena_pajak, aktif) VALUES (:nama, :jenis, :pajak, :aktif)")->execute($p);
        }
        echo json_encode(["success" => true, "message" => "Komponen gaji disimpan."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

function deleteKomponen($db) {
    $d = jsonInput();
    try {
        $db->prepare("DELETE FROM komponen_karyawan WHERE komponen_id = :id")->execute([':id' => intval($d['id'] ?? 0)]);
        $db->prepare("DELETE FROM komponen_gaji WHERE id = :id")->execute([':id' => intval($d['id'] ?? 0)]);
        echo json_encode(["success" => true, "message" => "Komponen gaji dihapus."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

function saveKomponenKaryawan($db) {
    $d = jsonInput();
    $berulang = !empty($d['berulang']) ? 1 : 0;
    if (empty($d['nik']) || empty($d['komponen_id']) || !isset($d['jumlah']) || (double)$d['jumlah'] <= 0
        || (!$berulang && (empty($d['bulan']) || empty($d['tahun'])))) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Karyawan, komponen, jumlah, dan periode (jika tidak berulang) wajib diisi."]);
        return;
    }
    try {
        $db->prepare("INSERT INTO komponen_karyawan (nik, komponen_id, jumlah, berulang, bulan, tahun, keterangan)
                      VALUES (:nik, :kid, :jml, :ber, :b, :t, :ket)")
           ->execute([':nik' => $d['nik'], ':kid' => intval($d['komponen_id']), ':jml' => (double)$d['jumlah'], ':ber' => $berulang,
                      ':b' => $berulang ? null : $d['bulan'], ':t' => $berulang ? null : intval($d['tahun']), ':ket' => $d['keterangan'] ?? null]);
        echo json_encode(["success" => true, "message" => "Komponen ditetapkan ke karyawan."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

function deleteKomponenKaryawan($db) {
    $d = jsonInput();
    try {
        $db->prepare("DELETE FROM komponen_karyawan WHERE id = :id")->execute([':id' => intval($d['id'] ?? 0)]);
        echo json_encode(["success" => true, "message" => "Penetapan komponen dihapus."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

// ------------------------------------------------------------------------------
// ALUR PERSETUJUAN PAYROLL (Draft → Diajukan → Disetujui → Dibayar/Terkunci)
// ------------------------------------------------------------------------------
function payrollPeriode($db, $bulan, $tahun) {
    $stmt = $db->prepare("SELECT * FROM payroll_periode WHERE bulan = :b AND tahun = :t");
    $stmt->execute([':b' => $bulan, ':t' => intval($tahun)]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getPayrollPeriode($db) {
    try {
        $rows = $db->query("SELECT p.*, (SELECT COUNT(*) FROM penggajian g WHERE g.bulan = p.bulan AND g.tahun = p.tahun) AS jumlah_slip,
                                   (SELECT COALESCE(SUM(total_gaji), 0) FROM penggajian g WHERE g.bulan = p.bulan AND g.tahun = p.tahun) AS total_gaji
                            FROM payroll_periode p
                            ORDER BY p.tahun DESC, FIELD(p.bulan, 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember') DESC")
                   ->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["success" => true, "data" => $rows]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

function updatePayrollStatus($db) {
    $d = jsonInput();
    $actor = hrisActor();
    $periode = payrollPeriode($db, $d['bulan'] ?? '', $d['tahun'] ?? 0);
    if (!$periode) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Periode payroll belum di-generate."]);
        return;
    }
    $from = $periode['status'];
    $to = $d['status'] ?? '';
    // Transisi yang diizinkan: [dari][ke] => peran yang boleh
    $rules = [
        'draft'     => ['diajukan' => ['admin']],
        'diajukan'  => ['disetujui' => ['direktur'], 'draft' => ['admin', 'direktur']],
        'disetujui' => ['dibayar' => ['admin'], 'draft' => ['direktur']]
    ];
    $allowed = $rules[$from][$to] ?? null;
    if (!$allowed) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Perubahan status dari '$from' ke '$to' tidak diizinkan."]);
        return;
    }
    if (!in_array($actor['role'], $allowed, true)) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Peran Anda tidak berwenang mengubah status ke '$to'."]);
        return;
    }
    try {
        $sets = ["status = :to", "catatan = :cat"];
        $params = [':to' => $to, ':cat' => isset($d['catatan']) ? mb_substr($d['catatan'], 0, 255) : $periode['catatan'], ':id' => $periode['id']];
        $stamp = ['diajukan' => 'diajukan', 'disetujui' => 'disetujui', 'dibayar' => 'dibayar'][$to] ?? null;
        if ($stamp) {
            $sets[] = "{$stamp}_oleh = :oleh";
            $sets[] = "{$stamp}_at = NOW()";
            $params[':oleh'] = $actor['user'];
        }
        $db->prepare("UPDATE payroll_periode SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
        auditLog($db, 'update_payroll_status', 'payroll_periode', $d['bulan'] . ' ' . $d['tahun'], ['status' => $from], ['status' => $to, 'catatan' => $d['catatan'] ?? null]);
        $label = ['draft' => 'dikembalikan ke Draft', 'diajukan' => 'diajukan untuk persetujuan', 'disetujui' => 'disetujui', 'dibayar' => 'ditandai dibayar & dikunci'][$to];
        echo json_encode(["success" => true, "message" => "Payroll {$d['bulan']} {$d['tahun']} $label."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

// ------------------------------------------------------------------------------
// AMBIL & GENERATE PAYROLL
// ------------------------------------------------------------------------------
function getPayroll($db) {
    try {
        $nik = $_GET['nik'] ?? '';
        $id = intval($_GET['id'] ?? 0);
        $where = [];
        $params = [];
        if (!empty($nik) && $nik !== 'null') {
            $where[] = "g.nik = :nik";
            $params[':nik'] = $nik;
            // Karyawan hanya melihat slip dari periode yang sudah disetujui
            $where[] = "p.status IN ('disetujui', 'dibayar')";
        }
        if ($id > 0) { $where[] = "g.id = :id"; $params[':id'] = $id; }
        if (!empty($_GET['bulan']) && !empty($_GET['tahun'])) {
            $where[] = "g.bulan = :b AND g.tahun = :t";
            $params[':b'] = $_GET['bulan'];
            $params[':t'] = intval($_GET['tahun']);
        }
        $sql = "SELECT g.*, COALESCE(p.status, 'draft') AS status_periode FROM penggajian g
                LEFT JOIN payroll_periode p ON p.bulan = g.bulan AND p.tahun = g.tahun"
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . " ORDER BY g.tahun DESC, FIELD(g.bulan, 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember') DESC, g.nama ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

/**
 * Generate Payroll Bulanan
 *
 * Pendapatan : Gaji Pokok & Tunjangan Tetap (prorata), Uang Makan & Transport (per hari hadir), Tunjangan Lain,
 *              Lembur (PP 35/2021 atau tarif flat, hanya yang disetujui), THR, komponen tambahan, Reimbursement.
 * Potongan   : BPJS TK & Kesehatan, PPh 21 (TER / Pasal 17 masa terakhir), Kasbon, keterlambatan,
 *              cuti tanpa upah (hari kerja), komponen potongan tambahan.
 */
function generatePayroll($db) {
    $data = jsonInput();
    $range = periodRange($data['bulan'] ?? '', $data['tahun'] ?? 0);
    if (!$range) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Bulan dan tahun penggajian tidak valid."]);
        return;
    }
    [$startDate, $endDate, $monthNum] = $range;
    $bulan = $data['bulan'];
    $tahun = intval($data['tahun']);

    $periode = payrollPeriode($db, $bulan, $tahun);
    if ($periode && $periode['status'] !== 'draft') {
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "Payroll $bulan $tahun berstatus '{$periode['status']}' dan terkunci. Kembalikan ke Draft untuk generate ulang."]);
        return;
    }

    // Konfigurasi THR opsional untuk periode ini
    $thrCfg = null;
    if (!empty($data['thr']['aktif']) && !empty($data['thr']['tanggal_hari_raya']) && strtotime($data['thr']['tanggal_hari_raya'])) {
        $thrCfg = ['tanggal_hari_raya' => date('Y-m-d', strtotime($data['thr']['tanggal_hari_raya'])),
                   'nama' => mb_substr($data['thr']['nama'] ?? 'Hari Raya', 0, 60)];
    }

    try {
        $cfg = payrollConfig($db);
        $employees = $db->query("SELECT * FROM karyawan")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($employees)) {
            echo json_encode(["success" => false, "message" => "Belum ada data karyawan terdaftar."]);
            return;
        }

        $startTime = "$startDate 00:00:00";
        $endTime = "$endDate 23:59:59";
        $holidays = holidayMap($db, $startDate, $endDate);
        $shifts = shiftMap($db);
        $defaultShift = defaultShift($db);

        $db->beginTransaction();

        // Tarik lembur dari absensi sebelum menghitung (yang sudah ada/diedit tidak ditimpa)
        syncLemburFromAbsensi($db, $startDate, $endDate, !$cfg['lembur_perlu_persetujuan']);

        $p = [':bulan' => $bulan, ':tahun' => $tahun];
        $db->prepare("DELETE FROM penggajian WHERE bulan = :bulan AND tahun = :tahun")->execute($p);
        $db->prepare("DELETE FROM kasbon_potongan WHERE bulan = :bulan AND tahun = :tahun")->execute($p);
        $db->prepare("UPDATE reimbursement SET dibayar_bulan = NULL, dibayar_tahun = NULL WHERE dibayar_bulan = :bulan AND dibayar_tahun = :tahun")->execute($p);

        $lateStmt = $db->prepare("SELECT COUNT(id) FROM log_absensi WHERE nik = :nik AND status = 'Terlambat' AND scan_time BETWEEN :s AND :e");
        $presentStmt = $db->prepare("SELECT COUNT(DISTINCT DATE(scan_time)) FROM log_absensi
                                     WHERE nik = :nik AND status IN ('Hadir Tepat Waktu', 'Terlambat') AND scan_time BETWEEN :s AND :e");
        $lemburStmt = $db->prepare("SELECT tanggal, jam, jenis_hari FROM lembur WHERE nik = :nik AND status = 'approved' AND tanggal BETWEEN :s AND :e ORDER BY tanggal");
        $leaveStmt = $db->prepare("SELECT jenis_cuti, tanggal_mulai, tanggal_selesai FROM cuti
                                   WHERE nik = :nik AND status = 'approved' AND NOT (tanggal_selesai < :s OR tanggal_mulai > :e)");
        $reimbSumStmt = $db->prepare("SELECT COALESCE(SUM(jumlah), 0) FROM reimbursement
                                      WHERE nik = :nik AND status = 'approved' AND dibayar_bulan IS NULL AND tanggal <= :e");
        $reimbPayStmt = $db->prepare("UPDATE reimbursement SET dibayar_bulan = :bulan, dibayar_tahun = :tahun
                                      WHERE nik = :nik AND status = 'approved' AND dibayar_bulan IS NULL AND tanggal <= :e");
        $kasbonStmt = $db->prepare("SELECT k.id, k.jumlah, k.cicilan_per_bulan, COALESCE(SUM(p.jumlah), 0) AS terbayar
                                    FROM kasbon k LEFT JOIN kasbon_potongan p ON p.kasbon_id = k.id
                                    WHERE k.nik = :nik AND k.tanggal <= :e
                                    GROUP BY k.id, k.jumlah, k.cicilan_per_bulan ORDER BY k.tanggal ASC");
        $kasbonPotStmt = $db->prepare("INSERT INTO kasbon_potongan (kasbon_id, bulan, tahun, jumlah) VALUES (:kid, :bulan, :tahun, :jumlah)");
        $komponenStmt = $db->prepare("SELECT kg.nama, kg.jenis, kg.kena_pajak, kk.jumlah, kk.keterangan
                                      FROM komponen_karyawan kk JOIN komponen_gaji kg ON kg.id = kk.komponen_id
                                      WHERE kk.nik = :nik AND kg.aktif = 1 AND (kk.berulang = 1 OR (kk.bulan = :bulan AND kk.tahun = :tahun))");
        $pphYtdStmt = $db->prepare("SELECT COUNT(*) AS masa, COALESCE(SUM(bruto_pph21), 0) AS bruto,
                                           COALESCE(SUM(pph21 + pph21_ditanggung), 0) AS pph,
                                           COALESCE(SUM(bpjs_jht_karyawan + bpjs_jp_karyawan), 0) AS iuran_pensiun
                                    FROM penggajian WHERE nik = :nik AND tahun = :tahun AND bulan <> :bulan");

        $columns = ['nomor_slip', 'nik', 'nama', 'divisi', 'jabatan', 'status_kerja', 'bulan', 'tahun',
            'hari_hadir', 'jumlah_terlambat', 'hari_cuti_unpaid', 'gaji_pokok', 'tunjangan', 'uang_makan', 'uang_transport',
            'tunjangan_lain', 'lembur_pay', 'total_jam_lembur', 'reimbursement', 'potongan', 'potongan_cuti',
            'bpjs_tk_program', 'bpjs_jht_karyawan', 'bpjs_jp_karyawan', 'bpjs_kes_kelas', 'bpjs_kes_karyawan',
            'jkp_iuran', 'potongan_kasbon', 'bpjs_perusahaan', 'rincian_perusahaan', 'bpjs_kes_keluarga', 'no_kpj', 'no_jkn',
            'status_ptkp', 'npwp', 'bruto_pph21', 'pph21', 'pph21_ditanggung', 'rincian_pph21',
            'thr', 'komponen_tambahan', 'tambahan_pendapatan', 'tambahan_potongan', 'prorata_faktor',
            'hari_kerja_aktif', 'hari_kerja_bulan', 'rincian_lembur', 'total_pendapatan', 'total_potongan', 'total_gaji'];
        $insertStmt = $db->prepare("INSERT INTO penggajian (" . implode(', ', $columns) . ") VALUES (:" . implode(', :', $columns) . ")");

        $generatedCount = 0;
        $skipped = 0;
        foreach ($employees as $emp) {
            $nik = $emp['nik'];
            $range = [':nik' => $nik, ':s' => $startTime, ':e' => $endTime];
            $dateRange = [':nik' => $nik, ':s' => $startDate, ':e' => $endDate];

            // 0. Masa aktif di periode ini (karyawan baru masuk / resign)
            $mulai = $emp['tanggal_mulai_kerja'] ?: null;
            $keluar = $emp['tanggal_keluar'] ?: null;
            if (($mulai && $mulai > $endDate) || ($keluar && $keluar < $startDate)) { $skipped++; continue; }
            $aktifMulai = ($mulai && $mulai > $startDate) ? $mulai : $startDate;
            $aktifSelesai = ($keluar && $keluar < $endDate) ? $keluar : $endDate;
            $resignBulanIni = $keluar && $keluar >= $startDate && $keluar <= $endDate;

            $shift = shiftForEmployee($emp['shift_id'] ?? null, $shifts, $defaultShift);
            $days = shiftDays($shift);
            if ($cfg['prorata_metode'] === 'kalender') {
                $hariBulan = intval(date('t', strtotime($startDate)));
                $hariAktif = intval((strtotime($aktifSelesai) - strtotime($aktifMulai)) / 86400) + 1;
            } else {
                $hariBulan = countWorkdays($startDate, $endDate, $days, $holidays);
                $hariAktif = countWorkdays($aktifMulai, $aktifSelesai, $days, $holidays);
            }
            $faktor = ($cfg['prorata_aktif'] && $hariBulan > 0) ? min(1, $hariAktif / $hariBulan) : 1.0;

            $statusKerja = $emp['status_kerja'] ?: 'PKWT';
            $isKaryawanTetap = ($statusKerja === 'PKWTT');

            // 1. Komponen tetap (prorata)
            $gaji_pokok_penuh = (double)$emp['gaji_pokok'];
            $tunjangan_penuh = ($isKaryawanTetap || !$cfg['tunjangan_hanya_pkwtt']) ? (double)$emp['tunjangan'] : 0.0;
            $gaji_pokok = round($gaji_pokok_penuh * $faktor);
            $tunjangan = round($tunjangan_penuh * $faktor);
            $tunjangan_lain = round((double)$emp['tunjangan_lain'] * $faktor);
            $upah_bulanan = $gaji_pokok_penuh + $tunjangan_penuh;

            // 2. Kehadiran, keterlambatan, uang makan & transport
            $presentStmt->execute($range);
            $hari_hadir = intval($presentStmt->fetchColumn());
            $lateStmt->execute($range);
            $jumlah_terlambat = intval($lateStmt->fetchColumn());
            $potongan_terlambat = $jumlah_terlambat * (double)$emp['potongan_terlambat'];
            $uang_makan = $hari_hadir * (double)$emp['uang_makan_harian'];
            $uang_transport = $hari_hadir * (double)$emp['uang_transport_harian'];

            // 3. Cuti tanpa upah (Izin Khusus) dihitung per hari kerja
            $leaveStmt->execute($dateRange);
            $unpaid_leave_days = 0;
            foreach ($leaveStmt->fetchAll(PDO::FETCH_ASSOC) as $leave) {
                if ($leave['jenis_cuti'] !== 'Izin Khusus') continue;
                $s = max($aktifMulai, $leave['tanggal_mulai']);
                $e = min($aktifSelesai, $leave['tanggal_selesai']);
                if ($s <= $e) $unpaid_leave_days += countWorkdays($s, $e, $days, $holidays);
            }
            $potongan_cuti = round($unpaid_leave_days * ($gaji_pokok_penuh / $cfg['hari_kerja_pembagi']));

            // 4. Lembur yang disetujui
            $lemburStmt->execute($dateRange);
            $total_jam_lembur = 0.0;
            $total_ekuivalen = 0.0;
            $lemburHari = [];
            foreach ($lemburStmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
                $ekv = lemburEkuivalen($l['jam'], $l['jenis_hari'], count($days));
                $total_jam_lembur += (double)$l['jam'];
                $total_ekuivalen += $ekv;
                $lemburHari[] = ['tanggal' => $l['tanggal'], 'jam' => (double)$l['jam'], 'jenis' => $l['jenis_hari'], 'ekuivalen' => round($ekv, 2)];
            }
            if ($cfg['lembur_metode'] === 'pp35') {
                $upah_sejam = $upah_bulanan / $cfg['lembur_pembagi'];
                $lembur_pay = round($total_ekuivalen * $upah_sejam);
            } else {
                $upah_sejam = (double)$emp['upah_lembur_jam'];
                $lembur_pay = round($total_jam_lembur * $upah_sejam);
            }
            $rincian_lembur = $lemburHari ? json_encode([
                'metode' => $cfg['lembur_metode'], 'upah_sejam' => round($upah_sejam), 'total_jam' => $total_jam_lembur,
                'total_ekuivalen' => round($total_ekuivalen, 2), 'hari_per_minggu' => count($days), 'detail' => $lemburHari
            ]) : null;

            // 5. THR (Permenaker 6/2016): ≥12 bulan = 1× upah, ≥1 bulan = prorata masa kerja/12
            $thr = 0.0;
            if ($thrCfg) {
                $refMulai = $mulai ?: '1900-01-01';
                $hariRaya = $thrCfg['tanggal_hari_raya'];
                if ($refMulai <= $hariRaya && (!$keluar || $keluar >= $startDate)) {
                    $diff = (new DateTime($refMulai))->diff(new DateTime($hariRaya));
                    $bulanKerja = $diff->y * 12 + $diff->m;
                    if ($bulanKerja >= 12) $thr = $upah_bulanan;
                    elseif ($bulanKerja >= 1) $thr = round($bulanKerja / 12 * $upah_bulanan);
                }
            }

            // 6. Komponen tambahan
            $komponenStmt->execute([':nik' => $nik, ':bulan' => $bulan, ':tahun' => $tahun]);
            $komponen = [];
            $tambahan_pendapatan = $tambahan_potongan = $tambahan_kena_pajak = 0.0;
            foreach ($komponenStmt->fetchAll(PDO::FETCH_ASSOC) as $k) {
                $jml = (double)$k['jumlah'];
                $komponen[] = ['nama' => $k['nama'], 'jenis' => $k['jenis'], 'kena_pajak' => (int)$k['kena_pajak'], 'jumlah' => $jml, 'keterangan' => $k['keterangan']];
                if ($k['jenis'] === 'pendapatan') {
                    $tambahan_pendapatan += $jml;
                    if ($k['kena_pajak']) $tambahan_kena_pajak += $jml;
                } else {
                    $tambahan_potongan += $jml;
                }
            }

            // 7. Reimbursement disetujui yang belum dibayarkan
            $reimbSumStmt->execute([':nik' => $nik, ':e' => $endDate]);
            $reimbursement = (double)$reimbSumStmt->fetchColumn();
            if ($reimbursement > 0) $reimbPayStmt->execute([':bulan' => $bulan, ':tahun' => $tahun, ':nik' => $nik, ':e' => $endDate]);

            // 8. BPJS Ketenagakerjaan (dasar upah penuh, minimal UMK)
            $bpjs_tk_program = intval($emp['bpjs_tk_program']);
            $bpjs_kes_kelas = intval($emp['bpjs_kes_kelas']);
            $umk_diterapkan = ($cfg['upah_minimum'] > 0 && $upah_bulanan < $cfg['upah_minimum']);
            $upah_bpjs = $umk_diterapkan ? $cfg['upah_minimum'] : $upah_bulanan;
            $usia = !empty($emp['tanggal_lahir']) ? (new DateTime($emp['tanggal_lahir']))->diff(new DateTime($endDate))->y : null;
            $jp_berhenti_usia = ($usia !== null && $cfg['usia_pensiun'] > 0 && $usia >= $cfg['usia_pensiun']);

            $jkk = $jkm = $jht_perusahaan = $jht_karyawan = $jp_perusahaan = $jp_karyawan = $jkp = 0.0;
            if ($bpjs_tk_program >= 2) {
                $jkk = $upah_bpjs * $cfg['jkk_perusahaan'];
                $jkm = $upah_bpjs * $cfg['jkm_perusahaan'];
            }
            if ($bpjs_tk_program >= 3) {
                $jht_perusahaan = $upah_bpjs * $cfg['jht_perusahaan'];
                $jht_karyawan = $upah_bpjs * $cfg['jht_karyawan'];
                $jkp = min($upah_bpjs, $cfg['jkp_batas_upah']) * $cfg['jkp_persen'];
            }
            if ($bpjs_tk_program >= 4 && !$jp_berhenti_usia) {
                $upah_jp = min($upah_bpjs, $cfg['jp_batas_upah']);
                $jp_perusahaan = $upah_jp * $cfg['jp_perusahaan'];
                $jp_karyawan = $upah_jp * $cfg['jp_karyawan'];
            }

            // 9. BPJS Kesehatan
            $keluarga_tambahan = max(0, intval($emp['bpjs_kes_keluarga_tambahan'] ?? 0));
            $bpjs_kes = $bpjs_kes_perusahaan = $bpjs_kes_keluarga = $upah_kes = 0.0;
            if ($bpjs_kes_kelas >= 1 && $bpjs_kes_kelas <= 3) {
                if ($cfg['bpjs_kes_mode'] === 'ppu') {
                    $upah_kes = min($upah_bpjs, $cfg['bpjs_kes_batas_upah']);
                    $bpjs_kes = $upah_kes * $cfg['bpjs_kes_karyawan'];
                    $bpjs_kes_perusahaan = $upah_kes * $cfg['bpjs_kes_perusahaan'];
                    $bpjs_kes_keluarga = $keluarga_tambahan * $upah_kes * $cfg['bpjs_kes_keluarga'];
                } else {
                    $tarif = $cfg['bpjs_kes_tarif'][$bpjs_kes_kelas] ?? 0;
                    $bpjs_kes = $tarif;
                    $bpjs_kes_keluarga = $keluarga_tambahan * $tarif;
                }
            } else {
                $bpjs_kes_kelas = 0;
                $keluarga_tambahan = 0;
            }

            // 10. Kasbon
            $potongan_kasbon = 0.0;
            $kasbonStmt->execute([':nik' => $nik, ':e' => $endDate]);
            foreach ($kasbonStmt->fetchAll(PDO::FETCH_ASSOC) as $kb) {
                $sisa = (double)$kb['jumlah'] - (double)$kb['terbayar'];
                if ($sisa <= 0) continue;
                $cicilan = min((double)$kb['cicilan_per_bulan'], $sisa);
                $kasbonPotStmt->execute([':kid' => $kb['id'], ':bulan' => $bulan, ':tahun' => $tahun, ':jumlah' => $cicilan]);
                $potongan_kasbon += $cicilan;
            }

            // 11. PPh 21 — TER bulanan; Pasal 17 setahun di masa Desember atau masa terakhir karyawan resign
            $status_ptkp = in_array($emp['status_ptkp'] ?? '', pph21Statuses(), true) ? $emp['status_ptkp'] : 'TK/0';
            $punya_npwp = trim($emp['npwp'] ?? '') !== '';
            $bruto_pph21 = max(0, $gaji_pokok + $tunjangan + $uang_makan + $uang_transport + $tunjangan_lain + $lembur_pay
                                  + $thr + $tambahan_kena_pajak - $potongan_terlambat - $potongan_cuti)
                           + $jkk + $jkm + $bpjs_kes_perusahaan;
            $pph21_hitung = 0.0;
            $rincian_pph21 = null;
            if ($cfg['pph21_aktif']) {
                $masaTerakhir = ($monthNum === '12' || $resignBulanIni);
                if (!$masaTerakhir) {
                    $kategori = pph21TerKategori($status_ptkp);
                    $tarif = pph21TerRate($kategori, $bruto_pph21);
                    $pph21_hitung = $bruto_pph21 * $tarif / 100;
                    $rincian_pph21 = ['metode' => 'TER', 'kategori' => $kategori, 'tarif' => $tarif];
                } else {
                    $pphYtdStmt->execute([':nik' => $nik, ':tahun' => $tahun, ':bulan' => $bulan]);
                    $ytd = $pphYtdStmt->fetch(PDO::FETCH_ASSOC);
                    $masa = intval($ytd['masa']) + 1;
                    $bruto_setahun = (double)$ytd['bruto'] + $bruto_pph21;
                    $biaya_jabatan = min($bruto_setahun * $cfg['biaya_jabatan'], $cfg['biaya_jabatan_maks_bulan'] * $masa);
                    $iuran_pensiun = (double)$ytd['iuran_pensiun'] + $jht_karyawan + $jp_karyawan;
                    $neto = $bruto_setahun - $biaya_jabatan - $iuran_pensiun;
                    $ptkp = pph21Ptkp($status_ptkp, $cfg);
                    $pkp = max(0, floor(($neto - $ptkp) / 1000) * 1000);
                    $pph_setahun = pph21Pasal17($pkp);
                    $pph21_hitung = $pph_setahun - (double)$ytd['pph'] / ($punya_npwp ? 1 : $cfg['pph21_tanpa_npwp']);
                    $rincian_pph21 = [
                        'metode' => 'PASAL17', 'alasan' => $resignBulanIni && $monthNum !== '12' ? 'resign' : 'desember',
                        'masa' => $masa, 'bruto_setahun' => round($bruto_setahun), 'biaya_jabatan' => round($biaya_jabatan),
                        'iuran_pensiun' => round($iuran_pensiun), 'neto' => round($neto), 'ptkp' => $ptkp, 'pkp' => $pkp,
                        'pph_setahun' => round($pph_setahun), 'pph_dipotong' => round((double)$ytd['pph'])
                    ];
                }
                if (!$punya_npwp) $pph21_hitung *= $cfg['pph21_tanpa_npwp'];
                $rincian_pph21['npwp'] = $punya_npwp;
                $rincian_pph21['status_ptkp'] = $status_ptkp;
                $rincian_pph21['ditanggung'] = $cfg['pph21_metode'] === 'ditanggung';
            }
            $pph21_hitung = round($pph21_hitung);
            $pph21 = $cfg['pph21_metode'] === 'ditanggung' ? 0 : $pph21_hitung;
            $pph21_ditanggung = $cfg['pph21_metode'] === 'ditanggung' ? $pph21_hitung : 0;

            // 12. Kalkulasi akhir
            $total_pendapatan = $gaji_pokok + $tunjangan + $uang_makan + $uang_transport + $tunjangan_lain + $lembur_pay
                              + $thr + $tambahan_pendapatan + $reimbursement;
            $total_potongan = $potongan_terlambat + $potongan_cuti + $jht_karyawan + $jp_karyawan + $bpjs_kes + $bpjs_kes_keluarga
                            + $potongan_kasbon + $pph21 + $tambahan_potongan;
            $total_gaji = max(0, $total_pendapatan - $total_potongan);

            $insertStmt->execute([
                ':nomor_slip' => $cfg['slip_prefix'] . '/' . $tahun . $monthNum . '/' . $nik,
                ':nik' => $nik, ':nama' => $emp['nama'], ':divisi' => $emp['divisi'], ':jabatan' => $emp['jabatan'],
                ':status_kerja' => $statusKerja, ':bulan' => $bulan, ':tahun' => $tahun,
                ':hari_hadir' => $hari_hadir, ':jumlah_terlambat' => $jumlah_terlambat, ':hari_cuti_unpaid' => $unpaid_leave_days,
                ':gaji_pokok' => $gaji_pokok, ':tunjangan' => $tunjangan, ':uang_makan' => $uang_makan, ':uang_transport' => $uang_transport,
                ':tunjangan_lain' => $tunjangan_lain, ':lembur_pay' => $lembur_pay, ':total_jam_lembur' => $total_jam_lembur,
                ':reimbursement' => $reimbursement, ':potongan' => $potongan_terlambat, ':potongan_cuti' => $potongan_cuti,
                ':bpjs_tk_program' => $bpjs_tk_program, ':bpjs_jht_karyawan' => round($jht_karyawan), ':bpjs_jp_karyawan' => round($jp_karyawan),
                ':bpjs_kes_kelas' => $bpjs_kes_kelas, ':bpjs_kes_karyawan' => round($bpjs_kes), ':jkp_iuran' => round($jkp),
                ':potongan_kasbon' => $potongan_kasbon,
                ':bpjs_perusahaan' => round($jkk + $jkm + $jht_perusahaan + $jp_perusahaan + $bpjs_kes_perusahaan),
                ':rincian_perusahaan' => json_encode([
                    'jkk' => round($jkk), 'jkm' => round($jkm), 'jht' => round($jht_perusahaan), 'jp' => round($jp_perusahaan),
                    'jkp' => round($jkp), 'upah_bpjs' => $upah_bpjs, 'kes' => round($bpjs_kes_perusahaan), 'kes_mode' => $cfg['bpjs_kes_mode'],
                    'upah_kes' => $upah_kes, 'keluarga' => $keluarga_tambahan, 'umk' => $umk_diterapkan, 'upah_aktual' => $upah_bulanan,
                    'jp_berhenti_usia' => $jp_berhenti_usia, 'usia' => $usia
                ]),
                ':bpjs_kes_keluarga' => round($bpjs_kes_keluarga), ':no_kpj' => $emp['no_kpj'] ?: null, ':no_jkn' => $emp['no_jkn'] ?: null,
                ':status_ptkp' => $status_ptkp, ':npwp' => $punya_npwp ? trim($emp['npwp']) : null,
                ':bruto_pph21' => round($bruto_pph21), ':pph21' => $pph21, ':pph21_ditanggung' => $pph21_ditanggung,
                ':rincian_pph21' => $rincian_pph21 ? json_encode($rincian_pph21) : null,
                ':thr' => $thr,
                ':komponen_tambahan' => $komponen ? json_encode($komponen, JSON_UNESCAPED_UNICODE) : null,
                ':tambahan_pendapatan' => $tambahan_pendapatan, ':tambahan_potongan' => $tambahan_potongan,
                ':prorata_faktor' => round($faktor, 4), ':hari_kerja_aktif' => $hariAktif, ':hari_kerja_bulan' => $hariBulan,
                ':rincian_lembur' => $rincian_lembur,
                ':total_pendapatan' => round($total_pendapatan), ':total_potongan' => round($total_potongan), ':total_gaji' => round($total_gaji)
            ]);
            $generatedCount++;
        }

        // Daftarkan / perbarui periode sebagai draft
        $db->prepare("INSERT INTO payroll_periode (bulan, tahun, status, thr_config, dibuat_oleh) VALUES (:b, :t, 'draft', :thr, :oleh)
                      ON DUPLICATE KEY UPDATE thr_config = VALUES(thr_config), dibuat_oleh = VALUES(dibuat_oleh)")
           ->execute([':b' => $bulan, ':t' => $tahun, ':thr' => $thrCfg ? json_encode($thrCfg) : null, ':oleh' => hrisActor()['user']]);

        $db->commit();
        auditLog($db, 'generate_payroll', 'penggajian', "$bulan $tahun", null, ['karyawan' => $generatedCount, 'dilewati' => $skipped, 'thr' => $thrCfg]);
        $msg = "Berhasil menggenerasi payroll (Draft) untuk $generatedCount karyawan.";
        if ($skipped) $msg .= " $skipped karyawan dilewati (belum masuk / sudah keluar).";
        echo json_encode(["success" => true, "message" => $msg]);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal menggenerasi payroll: " . $e->getMessage()]);
    }
}

// ------------------------------------------------------------------------------
// TRANSFER BANK
// ------------------------------------------------------------------------------
function getBankTransfer($db) {
    $periode = payrollPeriode($db, $_GET['bulan'] ?? '', $_GET['tahun'] ?? 0);
    if (!$periode || !in_array($periode['status'], ['disetujui', 'dibayar'], true)) {
        echo json_encode(["success" => false, "message" => "File transfer hanya tersedia untuk payroll yang sudah disetujui."]);
        return;
    }
    try {
        $stmt = $db->prepare("SELECT g.nik, g.nama, g.total_gaji, g.nomor_slip, k.bank_nama, k.bank_rekening, k.bank_atas_nama
                              FROM penggajian g LEFT JOIN karyawan k ON k.nik = g.nik COLLATE utf8mb4_unicode_ci
                              WHERE g.bulan = :b AND g.tahun = :t AND g.total_gaji > 0 ORDER BY k.bank_nama, g.nama");
        $stmt->execute([':b' => $periode['bulan'], ':t' => $periode['tahun']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        auditLog($db, 'export_bank_transfer', 'penggajian', "{$periode['bulan']} {$periode['tahun']}", null, ['jumlah_baris' => count($rows)]);
        echo json_encode(["success" => true, "data" => $rows, "status" => $periode['status']]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

// ------------------------------------------------------------------------------
// EMAIL SLIP GAJI (SMTP)
// ------------------------------------------------------------------------------
/**
 * Klien SMTP minimal (AUTH LOGIN, SSL port 465 atau STARTTLS port 587)
 */
function smtpSend($cfg, $to, $toName, $subject, $html) {
    $host = $cfg['smtp_host'];
    $port = intval($cfg['smtp_port']);
    $secure = $cfg['smtp_secure'];
    if (!$host || !$port) throw new Exception("SMTP belum dikonfigurasi di Pengaturan Payroll.");

    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new Exception("Tidak dapat terhubung ke SMTP $host:$port ($errstr)");
    stream_set_timeout($fp, 20);

    $read = function () use ($fp) {
        $resp = '';
        while (($line = fgets($fp, 515)) !== false) {
            $resp .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $resp;
    };
    $cmd = function ($command, $expect) use ($fp, $read) {
        if ($command !== null) fwrite($fp, $command . "\r\n");
        $resp = $read();
        if (!in_array(intval(substr($resp, 0, 3)), (array)$expect, true)) {
            throw new Exception("SMTP error: " . trim($resp));
        }
        return $resp;
    };

    $cmd(null, 220);
    $ehloHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $cmd("EHLO $ehloHost", 250);
    if ($secure === 'tls') {
        $cmd("STARTTLS", 220);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new Exception("Gagal memulai TLS.");
        $cmd("EHLO $ehloHost", 250);
    }
    if ($cfg['smtp_user'] !== '') {
        $cmd("AUTH LOGIN", 334);
        $cmd(base64_encode($cfg['smtp_user']), 334);
        $cmd(base64_encode($cfg['smtp_pass']), 235);
    }
    $from = $cfg['smtp_from_email'] ?: $cfg['smtp_user'];
    $cmd("MAIL FROM:<$from>", 250);
    $cmd("RCPT TO:<$to>", [250, 251]);
    $cmd("DATA", 354);

    $encName = fn($n) => '=?UTF-8?B?' . base64_encode($n) . '?=';
    $headers = [
        "Date: " . date('r'),
        "From: " . $encName($cfg['smtp_from_name'] ?: 'HRIS') . " <$from>",
        "To: " . $encName($toName) . " <$to>",
        "Subject: " . $encName($subject),
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "Content-Transfer-Encoding: base64"
    ];
    $body = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($html));
    fwrite($fp, $body . "\r\n.\r\n");
    $cmd(null, 250);
    fwrite($fp, "QUIT\r\n");
    fclose($fp);
}

function payslipEmailHtml($pay, $company) {
    $rp = fn($v) => 'Rp ' . number_format((double)$v, 0, ',', '.');
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $rows = function ($items, $color) use ($rp, $e) {
        $html = '';
        foreach ($items as [$label, $val]) {
            if ((double)$val == 0) continue;
            $html .= "<tr><td style='padding:6px 0;color:#475569;border-bottom:1px solid #f1f5f9'>{$e($label)}</td>"
                   . "<td style='padding:6px 0;text-align:right;font-family:monospace;color:$color;border-bottom:1px solid #f1f5f9'>{$rp($val)}</td></tr>";
        }
        return $html ?: "<tr><td style='color:#94a3b8;padding:6px 0'>-</td></tr>";
    };
    $komponen = json_decode($pay['komponen_tambahan'] ?? '[]', true) ?: [];
    $pendapatan = [['Gaji Pokok', $pay['gaji_pokok']], ['Tunjangan Tetap', $pay['tunjangan']], ['Uang Makan', $pay['uang_makan']],
                   ['Uang Transport', $pay['uang_transport']], ['Tunjangan Lain-lain', $pay['tunjangan_lain']],
                   ['Upah Lembur', $pay['lembur_pay']], ['THR', $pay['thr']], ['Reimbursement', $pay['reimbursement']]];
    $potongan = [['BPJS TK – JHT', $pay['bpjs_jht_karyawan']], ['BPJS TK – JP', $pay['bpjs_jp_karyawan']],
                 ['BPJS Kesehatan', $pay['bpjs_kes_karyawan']], ['BPJS Kes – Keluarga', $pay['bpjs_kes_keluarga']],
                 ['PPh 21', $pay['pph21']], ['Kasbon', $pay['potongan_kasbon']], ['Keterlambatan', $pay['potongan']],
                 ['Cuti Tanpa Upah', $pay['potongan_cuti']]];
    foreach ($komponen as $k) {
        if ($k['jenis'] === 'pendapatan') $pendapatan[] = [$k['nama'], $k['jumlah']]; else $potongan[] = [$k['nama'], $k['jumlah']];
    }
    return "<div style='font-family:Arial,sans-serif;max-width:640px;margin:0 auto;color:#0f172a'>
        <div style='border-bottom:3px solid #0f172a;padding-bottom:12px;margin-bottom:16px'>
            <div style='font-size:20px;font-weight:bold'>{$e($company['nama_perusahaan'] ?? 'Perusahaan')}</div>
            <div style='font-size:12px;color:#64748b'>{$e($company['alamat_perusahaan'] ?? '')}</div>
            <div style='margin-top:8px;font-size:12px;color:#059669;font-weight:bold;letter-spacing:2px'>SLIP GAJI • {$e($pay['bulan'])} {$e($pay['tahun'])}</div>
            <div style='font-size:12px;font-family:monospace'>{$e($pay['nomor_slip'])}</div>
        </div>
        <p style='font-size:14px'>Yth. <b>{$e($pay['nama'])}</b> ({$e($pay['nik'])}),<br>berikut rincian gaji Anda untuk periode {$e($pay['bulan'])} {$e($pay['tahun'])}.</p>
        <table width='100%' style='font-size:13px;border-collapse:collapse'>
            <tr><td colspan='2' style='font-weight:bold;color:#047857;border-bottom:2px solid #10b981;padding:8px 0'>PENDAPATAN</td></tr>
            {$rows($pendapatan, '#0f172a')}
            <tr><td style='font-weight:bold;padding:8px 0'>Total Pendapatan</td><td style='text-align:right;font-weight:bold;font-family:monospace'>{$rp($pay['total_pendapatan'])}</td></tr>
            <tr><td colspan='2' style='font-weight:bold;color:#dc2626;border-bottom:2px solid #ef4444;padding:16px 0 8px'>POTONGAN</td></tr>
            {$rows($potongan, '#dc2626')}
            <tr><td style='font-weight:bold;padding:8px 0'>Total Potongan</td><td style='text-align:right;font-weight:bold;font-family:monospace'>{$rp($pay['total_potongan'])}</td></tr>
        </table>
        <div style='background:#0f172a;color:#fff;border-radius:12px;padding:16px;margin-top:16px'>
            <div style='font-size:11px;letter-spacing:2px;color:#34d399;font-weight:bold'>TAKE HOME PAY</div>
            <div style='font-size:26px;font-weight:bold;color:#34d399;font-family:monospace'>{$rp($pay['total_gaji'])}</div>
        </div>
        <p style='font-size:11px;color:#94a3b8;margin-top:16px'>Email ini dikirim otomatis oleh sistem HRIS dan bersifat rahasia. Detail lengkap dapat dilihat dan diunduh (PDF) di aplikasi HRIS.</p>
    </div>";
}

function sendPayslipEmail($db) {
    $d = jsonInput();
    try {
        $cfg = loadPayrollSettings($db);
        $company = $db->query("SELECT * FROM pengaturan ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];

        if (!empty($d['test_to'])) {
            smtpSend($cfg, $d['test_to'], 'Tes', 'Tes Email HRIS', "<p>Konfigurasi SMTP HRIS berhasil. Email slip gaji siap dikirim.</p>");
            echo json_encode(["success" => true, "message" => "Email tes terkirim ke {$d['test_to']}."]);
            return;
        }

        $where = !empty($d['id']) ? "g.id = :id" : "g.bulan = :b AND g.tahun = :t";
        $params = !empty($d['id']) ? [':id' => intval($d['id'])] : [':b' => $d['bulan'] ?? '', ':t' => intval($d['tahun'] ?? 0)];
        $stmt = $db->prepare("SELECT g.*, k.email, p.status AS status_periode FROM penggajian g
                              LEFT JOIN karyawan k ON k.nik = g.nik COLLATE utf8mb4_unicode_ci
                              LEFT JOIN payroll_periode p ON p.bulan = g.bulan AND p.tahun = g.tahun WHERE $where");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            echo json_encode(["success" => false, "message" => "Data slip tidak ditemukan."]);
            return;
        }
        if (!in_array($rows[0]['status_periode'], ['disetujui', 'dibayar'], true)) {
            echo json_encode(["success" => false, "message" => "Slip hanya dapat dikirim setelah payroll disetujui."]);
            return;
        }
        $sent = 0;
        $failed = [];
        $mark = $db->prepare("UPDATE penggajian SET email_terkirim_at = NOW() WHERE id = :id");
        foreach ($rows as $pay) {
            if (!filter_var($pay['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                $failed[] = "{$pay['nama']} (email kosong/tidak valid)";
                continue;
            }
            try {
                smtpSend($cfg, $pay['email'], $pay['nama'], "Slip Gaji {$pay['bulan']} {$pay['tahun']} - {$pay['nama']}", payslipEmailHtml($pay, $company));
                $mark->execute([':id' => $pay['id']]);
                $sent++;
            } catch (Exception $ex) {
                $failed[] = "{$pay['nama']} ({$ex->getMessage()})";
            }
        }
        $msg = "$sent slip terkirim.";
        if ($failed) $msg .= " Gagal: " . implode('; ', array_slice($failed, 0, 5)) . (count($failed) > 5 ? ' …' : '');
        echo json_encode(["success" => $sent > 0, "message" => $msg, "terkirim" => $sent, "gagal" => count($failed)]);
    } catch (Exception $e) {
        // 400 = SMTP belum dikonfigurasi, 502 = server SMTP tidak dapat dihubungi / menolak
        http_response_code(strpos($e->getMessage(), 'belum dikonfigurasi') !== false ? 400 : 502);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

// ------------------------------------------------------------------------------
// BUKTI POTONG 1721-A1
// ------------------------------------------------------------------------------
function buildA1($db, $nik, $tahun) {
    $cfg = payrollConfig($db);
    $stmt = $db->prepare("SELECT * FROM penggajian WHERE nik = :nik AND tahun = :t
                          ORDER BY FIELD(bulan, 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember')");
    $stmt->execute([':nik' => $nik, ':t' => $tahun]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return null;

    $emp = $db->prepare("SELECT * FROM karyawan WHERE nik = :nik");
    $emp->execute([':nik' => $nik]);
    $k = $emp->fetch(PDO::FETCH_ASSOC) ?: [];

    $sum = fn($f) => array_sum(array_map($f, $rows));
    $premi = $sum(function ($r) {
        $c = json_decode($r['rincian_perusahaan'] ?? '{}', true) ?: [];
        return (double)($c['jkk'] ?? 0) + (double)($c['jkm'] ?? 0) + (double)($c['kes'] ?? 0);
    });
    $tambahanKenaPajak = $sum(function ($r) {
        $t = 0;
        foreach (json_decode($r['komponen_tambahan'] ?? '[]', true) ?: [] as $c) if ($c['jenis'] === 'pendapatan' && $c['kena_pajak']) $t += $c['jumlah'];
        return $t;
    });
    $l1 = $sum(fn($r) => (double)$r['gaji_pokok'] - (double)$r['potongan'] - (double)$r['potongan_cuti']);
    $l3 = $sum(fn($r) => (double)$r['tunjangan'] + (double)$r['uang_makan'] + (double)$r['uang_transport'] + (double)$r['tunjangan_lain'] + (double)$r['lembur_pay']);
    $l7 = $sum(fn($r) => (double)$r['thr']) + $tambahanKenaPajak;
    $l8 = $l1 + $l3 + $premi + $l7; // baris 1 s.d. 7 (baris 2, 4, 6 = 0)
    $masa = count($rows);
    $l9 = min($l8 * $cfg['biaya_jabatan'], $cfg['biaya_jabatan_maks_bulan'] * $masa);
    $l10 = $sum(fn($r) => (double)$r['bpjs_jht_karyawan'] + (double)$r['bpjs_jp_karyawan']);
    $l11 = $l9 + $l10;
    $l12 = $l8 - $l11;
    $status = $rows[count($rows) - 1]['status_ptkp'] ?: ($k['status_ptkp'] ?? 'TK/0');
    $npwp = $rows[count($rows) - 1]['npwp'] ?: ($k['npwp'] ?? null);
    $l15 = pph21Ptkp($status, $cfg);
    $l16 = max(0, floor(($l12 - $l15) / 1000) * 1000);
    $l17 = pph21Pasal17($l16);
    $l19 = $npwp ? $l17 : $l17 * $cfg['pph21_tanpa_npwp'];
    $l20 = $sum(fn($r) => (double)$r['pph21'] + (double)$r['pph21_ditanggung']);

    return [
        'tahun' => intval($tahun), 'nik' => $nik, 'nama' => $k['nama'] ?? $rows[0]['nama'], 'npwp' => $npwp,
        'alamat' => $k['alamat'] ?? null, 'jenis_kelamin' => $k['jenis_kelamin'] ?? null, 'jabatan' => $k['jabatan'] ?? $rows[0]['jabatan'],
        'status_ptkp' => $status, 'masa_awal' => bulanMap()[$rows[0]['bulan']], 'masa_akhir' => bulanMap()[$rows[count($rows) - 1]['bulan']],
        'jumlah_masa' => $masa,
        'baris' => [
            1 => round($l1), 2 => 0, 3 => round($l3), 4 => 0, 5 => round($premi), 6 => 0, 7 => round($l7), 8 => round($l8),
            9 => round($l9), 10 => round($l10), 11 => round($l11), 12 => round($l12), 13 => 0, 14 => round($l12),
            15 => $l15, 16 => $l16, 17 => round($l17), 18 => 0, 19 => round($l19), 20 => round($l20)
        ],
        'selisih' => round($l20 - $l19)
    ];
}

function getA1($db) {
    $tahun = intval($_GET['tahun'] ?? 0);
    try {
        if (!empty($_GET['nik'])) {
            $a1 = buildA1($db, $_GET['nik'], $tahun);
            echo json_encode($a1 ? ["success" => true, "data" => $a1] : ["success" => false, "message" => "Tidak ada data payroll karyawan ini di tahun $tahun."]);
            return;
        }
        $stmt = $db->prepare("SELECT DISTINCT nik FROM penggajian WHERE tahun = :t ORDER BY nama");
        $stmt->execute([':t' => $tahun]);
        $all = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $nik) {
            $a1 = buildA1($db, $nik, $tahun);
            if ($a1) $all[] = $a1;
        }
        echo json_encode(["success" => true, "data" => $all]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}
