-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 21, 2026 at 09:13 AM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hris_biometric`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` bigint(20) NOT NULL,
  `waktu` timestamp NOT NULL DEFAULT current_timestamp(),
  `pengguna` varchar(100) DEFAULT NULL,
  `peran` varchar(20) DEFAULT NULL,
  `aksi` varchar(60) NOT NULL,
  `entitas` varchar(60) DEFAULT NULL,
  `entitas_id` varchar(100) DEFAULT NULL,
  `data_lama` mediumtext DEFAULT NULL,
  `data_baru` mediumtext DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `waktu`, `pengguna`, `peran`, `aksi`, `entitas`, `entitas_id`, `data_lama`, `data_baru`, `ip`) VALUES
(16, '2026-10-16 07:47:40', 'Super Admin', 'admin', 'generate_payroll', 'penggajian', 'Oktober 2026', NULL, '{\"karyawan\":1,\"dilewati\":0,\"thr\":{\"tanggal_hari_raya\":\"2026-10-16\",\"nama\":\"Idul Fitri\"}}', '::1'),
(17, '2026-10-19 05:29:30', 'Super Admin', 'admin', 'delete_user', 'akun', '4', NULL, '{\"id\":4}', '::1'),
(18, '2026-10-19 05:29:49', 'Super Admin', 'admin', 'add_user', 'akun', 'NIK-2026-001', NULL, '{\"username\":\"rama\",\"password\":\"[disembunyikan]\",\"role\":\"karyawan\",\"nik\":\"NIK-2026-001\"}', '::1'),
(19, '2026-10-19 05:30:38', 'Super Admin', 'admin', 'delete_karyawan', 'karyawan', 'NIK-2026-001', NULL, '{\"nik\":\"NIK-2026-001\"}', '::1'),
(20, '2026-10-19 05:37:05', 'Super Admin', 'admin', 'register_karyawan', 'karyawan', 'NIK-2026-001', NULL, '{\"nik\":\"NIK-2026-001\",\"nama\":\"Rama\",\"divisi\":\"IT & Engineering\",\"jabatan\":\"Staff\",\"status_kerja\":\"PKWTT\",\"image_base64\":\"[disembunyikan]\"}', '::1'),
(21, '2026-10-19 05:39:05', 'Super Admin', 'admin', 'add_user', 'akun', 'NIK-2026-002', NULL, '{\"username\":\"adit\",\"password\":\"[disembunyikan]\",\"role\":\"karyawan\",\"nik\":\"NIK-2026-002\"}', '::1'),
(22, '2026-10-19 05:39:33', 'Super Admin', 'admin', 'register_karyawan', 'karyawan', 'NIK-2026-002', NULL, '{\"nik\":\"NIK-2026-002\",\"nama\":\"Aditya\",\"divisi\":\"Finance & Accounting\",\"jabatan\":\"Staff\",\"status_kerja\":\"PKWTT\",\"image_base64\":\"[disembunyikan]\"}', '::1'),
(23, '2026-10-19 06:03:09', 'Super Admin', 'admin', 'review_absensi_foto', 'absensi', '19', NULL, '{\"id\":19,\"keputusan\":\"setuju\"}', '::1'),
(24, '2026-10-19 06:16:14', 'Super Admin', 'admin', 'add_user', 'akun', 'NIK-2026-003', NULL, '{\"username\":\"tadi\",\"password\":\"[disembunyikan]\",\"role\":\"karyawan\",\"nik\":\"NIK-2026-003\"}', '::1'),
(25, '2026-10-19 06:16:40', 'Super Admin', 'admin', 'register_karyawan', 'karyawan', 'NIK-2026-003', NULL, '{\"nik\":\"NIK-2026-003\",\"nama\":\"Tadi\",\"divisi\":\"Marketing & Sales\",\"jabatan\":\"Staff\",\"status_kerja\":\"PKWT\",\"image_base64\":\"[disembunyikan]\"}', '::1'),
(26, '2026-10-19 06:19:18', 'Super Admin', 'admin', 'delete_user', 'akun', '5', NULL, '{\"id\":5}', '::1'),
(27, '2026-10-19 06:19:26', 'Super Admin', 'admin', 'delete_karyawan', 'karyawan', 'NIK-2026-001', NULL, '{\"nik\":\"NIK-2026-001\"}', '::1'),
(28, '2026-10-19 06:21:39', 'Super Admin', 'admin', 'delete_karyawan', 'karyawan', 'NIK-2026-002', NULL, '{\"nik\":\"NIK-2026-002\"}', '::1'),
(29, '2026-10-19 06:21:48', 'Super Admin', 'admin', 'delete_user', 'akun', '6', NULL, '{\"id\":6}', '::1'),
(30, '2026-10-19 06:22:06', 'Super Admin', 'admin', 'add_user', 'akun', 'NIK-2026-004', NULL, '{\"username\":\"aditya\",\"password\":\"[disembunyikan]\",\"role\":\"karyawan\",\"nik\":\"NIK-2026-004\"}', '::1'),
(31, '2026-10-19 06:22:33', 'Super Admin', 'admin', 'register_karyawan', 'karyawan', 'NIK-2026-004', NULL, '{\"nik\":\"NIK-2026-004\",\"nama\":\"Adit\",\"divisi\":\"IT & Engineering\",\"jabatan\":\"Staff\",\"status_kerja\":\"PKWT\",\"image_base64\":\"[disembunyikan]\"}', '::1'),
(32, '2026-10-19 06:33:38', 'Super Admin', 'admin', 'review_absensi_foto', 'absensi', '22', NULL, '{\"id\":22,\"keputusan\":\"setuju\"}', '::1'),
(33, '2026-10-19 08:44:21', 'Super Admin', 'admin', 'delete_karyawan', 'karyawan', 'NIK-2026-004', NULL, '{\"nik\":\"NIK-2026-004\"}', '::1'),
(34, '2026-10-19 08:44:24', 'Super Admin', 'admin', 'delete_karyawan', 'karyawan', 'NIK-2026-003', NULL, '{\"nik\":\"NIK-2026-003\"}', '::1'),
(35, '2026-10-19 08:45:03', 'Super Admin', 'admin', 'register_karyawan', 'karyawan', 'NIK-2026-004', NULL, '{\"nik\":\"NIK-2026-004\",\"nama\":\"aditya\",\"divisi\":\"IT & Engineering\",\"jabatan\":\"Staff\",\"status_kerja\":\"PKWT\",\"image_base64\":\"[disembunyikan]\"}', '::1'),
(36, '2026-10-21 06:00:56', 'Super Admin', 'admin', 'generate_payroll', 'penggajian', 'November 2026', NULL, '{\"karyawan\":1,\"dilewati\":0,\"thr\":null}', '::1'),
(37, '2026-10-21 06:01:08', 'Super Admin', 'admin', 'generate_payroll', 'penggajian', 'Desember 2026', NULL, '{\"karyawan\":1,\"dilewati\":0,\"thr\":null}', '::1');

-- --------------------------------------------------------

--
-- Table structure for table `cuti`
--

CREATE TABLE `cuti` (
  `id` int(11) NOT NULL,
  `nik` varchar(50) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `jenis_cuti` varchar(50) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `alasan` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `jumlah_hari` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cuti`
--

INSERT INTO `cuti` (`id`, `nik`, `nama`, `jenis_cuti`, `tanggal_mulai`, `tanggal_selesai`, `alasan`, `status`, `created_at`, `jumlah_hari`) VALUES
(1, 'NIK-2026-001', 'gilang01', 'Cuti Sakit', '2026-07-28', '2026-07-29', 'sakit1', 'approved', '2026-07-27 04:28:14', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `hari_libur`
--

CREATE TABLE `hari_libur` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `nama` varchar(150) NOT NULL,
  `jenis` enum('nasional','cuti_bersama','perusahaan') NOT NULL DEFAULT 'nasional',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `karyawan`
--

CREATE TABLE `karyawan` (
  `id` int(11) NOT NULL,
  `nik` varchar(50) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `divisi` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `gaji_pokok` decimal(12,2) NOT NULL DEFAULT 5000000.00,
  `tunjangan` decimal(12,2) NOT NULL DEFAULT 1000000.00,
  `potongan_terlambat` decimal(12,2) NOT NULL DEFAULT 50000.00,
  `upah_lembur_jam` decimal(12,2) NOT NULL DEFAULT 25000.00,
  `jabatan` varchar(100) NOT NULL DEFAULT 'Staff',
  `email` varchar(100) DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `jenis_kelamin` enum('Laki-laki','Perempuan') DEFAULT NULL,
  `tempat_lahir` varchar(100) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `status_kerja` enum('PKWT','PKWTT') NOT NULL DEFAULT 'PKWT',
  `kontak_darurat_nama` varchar(150) DEFAULT NULL,
  `kontak_darurat_hubungan` varchar(100) DEFAULT NULL,
  `kontak_darurat_telepon` varchar(20) DEFAULT NULL,
  `file_ktp` varchar(255) DEFAULT NULL,
  `file_npwp` varchar(255) DEFAULT NULL,
  `file_kontrak` varchar(255) DEFAULT NULL,
  `manager_nik` varchar(50) DEFAULT NULL,
  `tanggal_mulai_kerja` date DEFAULT NULL,
  `tanggal_selesai_kontrak` date DEFAULT NULL,
  `tanggal_selesai_probation` date DEFAULT NULL,
  `uang_makan_harian` decimal(12,2) NOT NULL DEFAULT 25000.00,
  `uang_transport_harian` decimal(12,2) NOT NULL DEFAULT 20000.00,
  `tunjangan_lain` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bpjs_tk_program` tinyint(4) NOT NULL DEFAULT 3,
  `bpjs_kes_kelas` tinyint(4) NOT NULL DEFAULT 2,
  `no_kpj` varchar(30) DEFAULT NULL,
  `no_jkn` varchar(30) DEFAULT NULL,
  `bpjs_kes_keluarga_tambahan` tinyint(4) NOT NULL DEFAULT 0,
  `status_ptkp` varchar(5) NOT NULL DEFAULT 'TK/0',
  `npwp` varchar(30) DEFAULT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `tanggal_keluar` date DEFAULT NULL,
  `bank_nama` varchar(50) DEFAULT NULL,
  `bank_rekening` varchar(40) DEFAULT NULL,
  `bank_atas_nama` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `karyawan`
--

INSERT INTO `karyawan` (`id`, `nik`, `nama`, `divisi`, `created_at`, `gaji_pokok`, `tunjangan`, `potongan_terlambat`, `upah_lembur_jam`, `jabatan`, `email`, `telepon`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `alamat`, `status_kerja`, `kontak_darurat_nama`, `kontak_darurat_hubungan`, `kontak_darurat_telepon`, `file_ktp`, `file_npwp`, `file_kontrak`, `manager_nik`, `tanggal_mulai_kerja`, `tanggal_selesai_kontrak`, `tanggal_selesai_probation`, `uang_makan_harian`, `uang_transport_harian`, `tunjangan_lain`, `bpjs_tk_program`, `bpjs_kes_kelas`, `no_kpj`, `no_jkn`, `bpjs_kes_keluarga_tambahan`, `status_ptkp`, `npwp`, `shift_id`, `tanggal_keluar`, `bank_nama`, `bank_rekening`, `bank_atas_nama`) VALUES
(9, 'NIK-2026-004', 'aditya', 'IT & Engineering', '2026-10-19 15:45:03', '5000000.00', '1000000.00', '50000.00', '25000.00', 'Staff', NULL, NULL, NULL, NULL, NULL, NULL, 'PKWT', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '25000.00', '20000.00', '0.00', 3, 2, NULL, NULL, 0, 'TK/0', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `kasbon`
--

CREATE TABLE `kasbon` (
  `id` int(11) NOT NULL,
  `nik` varchar(50) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `tanggal` date NOT NULL,
  `jumlah` decimal(12,2) NOT NULL,
  `cicilan_per_bulan` decimal(12,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kasbon_potongan`
--

CREATE TABLE `kasbon_potongan` (
  `id` int(11) NOT NULL,
  `kasbon_id` int(11) NOT NULL,
  `bulan` varchar(20) NOT NULL,
  `tahun` int(11) NOT NULL,
  `jumlah` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `komponen_gaji`
--

CREATE TABLE `komponen_gaji` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `jenis` enum('pendapatan','potongan') NOT NULL,
  `kena_pajak` tinyint(4) NOT NULL DEFAULT 1,
  `aktif` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `komponen_karyawan`
--

CREATE TABLE `komponen_karyawan` (
  `id` int(11) NOT NULL,
  `nik` varchar(50) NOT NULL,
  `komponen_id` int(11) NOT NULL,
  `jumlah` decimal(14,2) NOT NULL,
  `berulang` tinyint(4) NOT NULL DEFAULT 0,
  `bulan` varchar(20) DEFAULT NULL,
  `tahun` int(11) DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lembur`
--

CREATE TABLE `lembur` (
  `id` int(11) NOT NULL,
  `nik` varchar(50) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `tanggal` date NOT NULL,
  `jam` decimal(5,2) NOT NULL,
  `jenis_hari` enum('kerja','libur') NOT NULL DEFAULT 'kerja',
  `sumber` enum('absensi','manual') NOT NULL DEFAULT 'absensi',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `keterangan` varchar(255) DEFAULT NULL,
  `diproses_oleh` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `log_absensi`
--

CREATE TABLE `log_absensi` (
  `id` int(11) NOT NULL,
  `nik` varchar(50) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `divisi` varchar(100) NOT NULL,
  `scan_time` datetime DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL,
  `match_rate` varchar(10) NOT NULL,
  `overtime_hours` decimal(4,2) NOT NULL DEFAULT 0.00,
  `metode` varchar(20) NOT NULL DEFAULT 'Wajah',
  `foto_bukti` varchar(255) DEFAULT NULL,
  `verifikasi` varchar(20) NOT NULL DEFAULT 'Terverifikasi'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `log_absensi`
--

INSERT INTO `log_absensi` (`id`, `nik`, `nama`, `divisi`, `scan_time`, `status`, `match_rate`, `overtime_hours`, `metode`, `foto_bukti`, `verifikasi`) VALUES
(24, 'NIK-2026-004', 'aditya', 'IT & Engineering', '2026-10-19 15:45:11', 'Terlambat', '82.85%', '0.00', 'Wajah', NULL, 'Terverifikasi'),
(25, 'NIK-2026-004', 'aditya', 'IT & Engineering', '2026-10-19 15:50:12', 'Pulang', '69.38%', '0.00', 'Wajah', NULL, 'Terverifikasi');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_periode`
--

CREATE TABLE `payroll_periode` (
  `id` int(11) NOT NULL,
  `bulan` varchar(20) NOT NULL,
  `tahun` int(11) NOT NULL,
  `status` enum('draft','diajukan','disetujui','dibayar') NOT NULL DEFAULT 'draft',
  `thr_config` text DEFAULT NULL,
  `catatan` varchar(255) DEFAULT NULL,
  `dibuat_oleh` varchar(100) DEFAULT NULL,
  `diajukan_oleh` varchar(100) DEFAULT NULL,
  `disetujui_oleh` varchar(100) DEFAULT NULL,
  `dibayar_oleh` varchar(100) DEFAULT NULL,
  `diajukan_at` datetime DEFAULT NULL,
  `disetujui_at` datetime DEFAULT NULL,
  `dibayar_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_periode`
--

INSERT INTO `payroll_periode` (`id`, `bulan`, `tahun`, `status`, `thr_config`, `catatan`, `dibuat_oleh`, `diajukan_oleh`, `disetujui_oleh`, `dibayar_oleh`, `diajukan_at`, `disetujui_at`, `dibayar_at`, `updated_at`) VALUES
(1, 'Januari', 2026, 'draft', NULL, NULL, 'migrasi', NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-16 04:24:50'),
(400, 'Oktober', 2026, 'draft', '{\"tanggal_hari_raya\":\"2026-10-16\",\"nama\":\"Idul Fitri\"}', NULL, 'Super Admin', NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-16 07:47:40'),
(402, 'November', 2026, 'draft', NULL, NULL, 'Super Admin', NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-21 06:00:56'),
(403, 'Desember', 2026, 'draft', NULL, NULL, 'Super Admin', NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-21 06:01:08');

-- --------------------------------------------------------

--
-- Table structure for table `pengaturan`
--

CREATE TABLE `pengaturan` (
  `id` int(11) NOT NULL,
  `nama_perusahaan` varchar(255) NOT NULL,
  `alamat_perusahaan` text NOT NULL,
  `jam_masuk` time NOT NULL DEFAULT '08:30:00',
  `jam_pulang` time NOT NULL DEFAULT '17:00:00',
  `latitude_kantor` double NOT NULL DEFAULT -6.2,
  `longitude_kantor` double NOT NULL DEFAULT 106.816666,
  `radius_meter` double NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengaturan`
--

INSERT INTO `pengaturan` (`id`, `nama_perusahaan`, `alamat_perusahaan`, `jam_masuk`, `jam_pulang`, `latitude_kantor`, `longitude_kantor`, `radius_meter`) VALUES
(1, 'VeriFace Corp', 'JL.H.Rean RT.001/001 nO.84', '08:30:00', '17:00:00', -6.339099, 106.722287, 100);

-- --------------------------------------------------------

--
-- Table structure for table `pengaturan_payroll`
--

CREATE TABLE `pengaturan_payroll` (
  `kunci` varchar(60) NOT NULL,
  `nilai` mediumtext DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengaturan_payroll`
--

INSERT INTO `pengaturan_payroll` (`kunci`, `nilai`, `updated_at`) VALUES
('jkk_persen', '0.24', '2026-10-16 03:58:24'),
('slip_prefix', 'SLIP', '2026-10-16 03:58:24'),
('_schema_version', '2026.10.19-1', '2026-10-19 05:46:04');

-- --------------------------------------------------------

--
-- Table structure for table `penggajian`
--

CREATE TABLE `penggajian` (
  `id` int(11) NOT NULL,
  `nik` varchar(50) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `bulan` varchar(20) NOT NULL,
  `tahun` int(11) NOT NULL,
  `gaji_pokok` decimal(12,2) NOT NULL,
  `tunjangan` decimal(12,2) NOT NULL,
  `potongan` decimal(12,2) NOT NULL,
  `total_gaji` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `potongan_cuti` decimal(12,2) NOT NULL DEFAULT 0.00,
  `lembur_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_jam_lembur` decimal(6,2) NOT NULL DEFAULT 0.00,
  `nomor_slip` varchar(40) DEFAULT NULL,
  `divisi` varchar(100) DEFAULT NULL,
  `jabatan` varchar(100) DEFAULT NULL,
  `status_kerja` varchar(10) DEFAULT NULL,
  `hari_hadir` int(11) NOT NULL DEFAULT 0,
  `jumlah_terlambat` int(11) NOT NULL DEFAULT 0,
  `hari_cuti_unpaid` int(11) NOT NULL DEFAULT 0,
  `uang_makan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `uang_transport` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tunjangan_lain` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reimbursement` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bpjs_tk_program` tinyint(4) NOT NULL DEFAULT 0,
  `bpjs_jht_karyawan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bpjs_jp_karyawan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bpjs_kes_kelas` tinyint(4) NOT NULL DEFAULT 0,
  `bpjs_kes_karyawan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `jkp_iuran` decimal(12,2) NOT NULL DEFAULT 0.00,
  `potongan_kasbon` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bpjs_perusahaan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rincian_perusahaan` text DEFAULT NULL,
  `total_pendapatan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_potongan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bpjs_kes_keluarga` decimal(12,2) NOT NULL DEFAULT 0.00,
  `no_kpj` varchar(30) DEFAULT NULL,
  `no_jkn` varchar(30) DEFAULT NULL,
  `status_ptkp` varchar(5) DEFAULT NULL,
  `npwp` varchar(30) DEFAULT NULL,
  `bruto_pph21` decimal(14,2) NOT NULL DEFAULT 0.00,
  `pph21` decimal(14,2) NOT NULL DEFAULT 0.00,
  `pph21_ditanggung` decimal(14,2) NOT NULL DEFAULT 0.00,
  `rincian_pph21` text DEFAULT NULL,
  `thr` decimal(14,2) NOT NULL DEFAULT 0.00,
  `komponen_tambahan` text DEFAULT NULL,
  `tambahan_pendapatan` decimal(14,2) NOT NULL DEFAULT 0.00,
  `tambahan_potongan` decimal(14,2) NOT NULL DEFAULT 0.00,
  `prorata_faktor` decimal(6,4) NOT NULL DEFAULT 1.0000,
  `hari_kerja_aktif` int(11) NOT NULL DEFAULT 0,
  `hari_kerja_bulan` int(11) NOT NULL DEFAULT 0,
  `rincian_lembur` text DEFAULT NULL,
  `email_terkirim_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `penggajian`
--

INSERT INTO `penggajian` (`id`, `nik`, `nama`, `bulan`, `tahun`, `gaji_pokok`, `tunjangan`, `potongan`, `total_gaji`, `created_at`, `potongan_cuti`, `lembur_pay`, `total_jam_lembur`, `nomor_slip`, `divisi`, `jabatan`, `status_kerja`, `hari_hadir`, `jumlah_terlambat`, `hari_cuti_unpaid`, `uang_makan`, `uang_transport`, `tunjangan_lain`, `reimbursement`, `bpjs_tk_program`, `bpjs_jht_karyawan`, `bpjs_jp_karyawan`, `bpjs_kes_kelas`, `bpjs_kes_karyawan`, `jkp_iuran`, `potongan_kasbon`, `bpjs_perusahaan`, `rincian_perusahaan`, `total_pendapatan`, `total_potongan`, `bpjs_kes_keluarga`, `no_kpj`, `no_jkn`, `status_ptkp`, `npwp`, `bruto_pph21`, `pph21`, `pph21_ditanggung`, `rincian_pph21`, `thr`, `komponen_tambahan`, `tambahan_pendapatan`, `tambahan_potongan`, `prorata_faktor`, `hari_kerja_aktif`, `hari_kerja_bulan`, `rincian_lembur`, `email_terkirim_at`) VALUES
(1, 'NIK-2026-001', 'Gilang', 'Januari', 2026, '5000000.00', '1000000.00', '0.00', '6000000.00', '2026-07-27 04:29:16', '0.00', '0.00', '0.00', NULL, NULL, NULL, NULL, 0, 0, 0, '0.00', '0.00', '0.00', '0.00', 0, '0.00', '0.00', 0, '0.00', '0.00', '0.00', '0.00', NULL, '0.00', '0.00', '0.00', NULL, NULL, NULL, NULL, '0.00', '0.00', '0.00', NULL, '0.00', NULL, '0.00', '0.00', '1.0000', 0, 0, NULL, NULL),
(20, 'NIK-2026-001', 'Gilang', 'Oktober', 2026, '5000000.00', '0.00', '0.00', '9559352.00', '2026-10-16 07:47:40', '0.00', '0.00', '0.00', 'SLIP/202610/NIK-2026-001', 'Human Resources', 'Staff', 'PKWT', 0, 0, 0, '0.00', '0.00', '0.00', '0.00', 3, '100000.00', '0.00', 2, '100000.00', '18000.00', '0.00', '212000.00', '{\"jkk\":12000,\"jkm\":15000,\"jht\":185000,\"jp\":0,\"jkp\":18000,\"upah_bpjs\":5000000,\"kes\":0,\"kes_mode\":\"tarif\",\"upah_kes\":0,\"keluarga\":0,\"umk\":false,\"upah_aktual\":5000000,\"jp_berhenti_usia\":false,\"usia\":null}', '10000000.00', '440648.00', '0.00', NULL, NULL, 'TK/0', NULL, '10027000.00', '240648.00', '0.00', '{\"metode\":\"TER\",\"kategori\":\"A\",\"tarif\":2,\"npwp\":false,\"status_ptkp\":\"TK\\/0\",\"ditanggung\":false}', '5000000.00', NULL, '0.00', '0.00', '1.0000', 22, 22, NULL, NULL),
(21, 'NIK-2026-004', 'aditya', 'November', 2026, '5000000.00', '0.00', '0.00', '4800000.00', '2026-10-21 06:00:56', '0.00', '0.00', '0.00', 'SLIP/202611/NIK-2026-004', 'IT & Engineering', 'Staff', 'PKWT', 0, 0, 0, '0.00', '0.00', '0.00', '0.00', 3, '100000.00', '0.00', 2, '100000.00', '18000.00', '0.00', '212000.00', '{\"jkk\":12000,\"jkm\":15000,\"jht\":185000,\"jp\":0,\"jkp\":18000,\"upah_bpjs\":5000000,\"kes\":0,\"kes_mode\":\"tarif\",\"upah_kes\":0,\"keluarga\":0,\"umk\":false,\"upah_aktual\":5000000,\"jp_berhenti_usia\":false,\"usia\":null}', '5000000.00', '200000.00', '0.00', NULL, NULL, 'TK/0', NULL, '5027000.00', '0.00', '0.00', '{\"metode\":\"TER\",\"kategori\":\"A\",\"tarif\":0,\"npwp\":false,\"status_ptkp\":\"TK\\/0\",\"ditanggung\":false}', '0.00', NULL, '0.00', '0.00', '1.0000', 21, 21, NULL, NULL),
(22, 'NIK-2026-004', 'aditya', 'Desember', 2026, '5000000.00', '0.00', '0.00', '4800000.00', '2026-10-21 06:01:08', '0.00', '0.00', '0.00', 'SLIP/202612/NIK-2026-004', 'IT & Engineering', 'Staff', 'PKWT', 0, 0, 0, '0.00', '0.00', '0.00', '0.00', 3, '100000.00', '0.00', 2, '100000.00', '18000.00', '0.00', '212000.00', '{\"jkk\":12000,\"jkm\":15000,\"jht\":185000,\"jp\":0,\"jkp\":18000,\"upah_bpjs\":5000000,\"kes\":0,\"kes_mode\":\"tarif\",\"upah_kes\":0,\"keluarga\":0,\"umk\":false,\"upah_aktual\":5000000,\"jp_berhenti_usia\":false,\"usia\":null}', '5000000.00', '200000.00', '0.00', NULL, NULL, 'TK/0', NULL, '5027000.00', '0.00', '0.00', '{\"metode\":\"PASAL17\",\"alasan\":\"desember\",\"masa\":2,\"bruto_setahun\":10054000,\"biaya_jabatan\":502700,\"iuran_pensiun\":200000,\"neto\":9351300,\"ptkp\":54000000,\"pkp\":0,\"pph_setahun\":0,\"pph_dipotong\":0,\"npwp\":false,\"status_ptkp\":\"TK\\/0\",\"ditanggung\":false}', '0.00', NULL, '0.00', '0.00', '1.0000', 23, 23, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `pengumuman`
--

CREATE TABLE `pengumuman` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `konten` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengumuman`
--

INSERT INTO `pengumuman` (`id`, `judul`, `konten`, `created_at`) VALUES
(1, 'tes1', '1', '2026-07-27 04:27:29');

-- --------------------------------------------------------

--
-- Table structure for table `reimbursement`
--

CREATE TABLE `reimbursement` (
  `id` int(11) NOT NULL,
  `nik` varchar(50) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `tanggal` date NOT NULL,
  `kategori` varchar(50) NOT NULL,
  `jumlah` decimal(12,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `dibayar_bulan` varchar(20) DEFAULT NULL,
  `dibayar_tahun` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `riwayat_jabatan`
--

CREATE TABLE `riwayat_jabatan` (
  `id` int(11) NOT NULL,
  `nik` varchar(50) NOT NULL,
  `jabatan` varchar(100) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `riwayat_jabatan`
--

INSERT INTO `riwayat_jabatan` (`id`, `nik`, `jabatan`, `tanggal_mulai`, `tanggal_selesai`, `keterangan`, `created_at`) VALUES
(5, 'NIK-2026-004', 'Staff', '2026-10-19', NULL, 'Jabatan Awal saat Pendaftaran', '2026-10-19 08:45:03');

-- --------------------------------------------------------

--
-- Table structure for table `shift`
--

CREATE TABLE `shift` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `jam_masuk` time NOT NULL,
  `jam_pulang` time NOT NULL,
  `hari_kerja` varchar(20) NOT NULL DEFAULT '1,2,3,4,5',
  `istirahat_menit` int(11) NOT NULL DEFAULT 60,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','direktur','karyawan') NOT NULL,
  `nik` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `nik`, `created_at`) VALUES
(7, 'tadi', '$2y$10$7aRKWPvIMSOcS9pj5nE0DOyt6gSYyMnVoMFJXlhoY.D.HhjvgCerW', 'karyawan', 'NIK-2026-003', '2026-10-19 06:16:14'),
(8, 'aditya', '$2y$10$FsnfBik3sN2HX10CdJ0Tvu0bzctGhZ4XqLuhvkV7XvBrvuGCEmDbS', 'karyawan', 'NIK-2026-004', '2026-10-19 06:22:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_waktu` (`waktu`);

--
-- Indexes for table `cuti`
--
ALTER TABLE `cuti`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hari_libur`
--
ALTER TABLE `hari_libur`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tanggal` (`tanggal`);

--
-- Indexes for table `karyawan`
--
ALTER TABLE `karyawan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nik` (`nik`),
  ADD KEY `idx_nik` (`nik`);

--
-- Indexes for table `kasbon`
--
ALTER TABLE `kasbon`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kasbon_potongan`
--
ALTER TABLE `kasbon_potongan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `komponen_gaji`
--
ALTER TABLE `komponen_gaji`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `komponen_karyawan`
--
ALTER TABLE `komponen_karyawan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lembur`
--
ALTER TABLE `lembur`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_lembur` (`nik`,`tanggal`);

--
-- Indexes for table `log_absensi`
--
ALTER TABLE `log_absensi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scan_time` (`scan_time`),
  ADD KEY `idx_nik_log` (`nik`);

--
-- Indexes for table `payroll_periode`
--
ALTER TABLE `payroll_periode`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_periode` (`bulan`,`tahun`);

--
-- Indexes for table `pengaturan`
--
ALTER TABLE `pengaturan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pengaturan_payroll`
--
ALTER TABLE `pengaturan_payroll`
  ADD PRIMARY KEY (`kunci`);

--
-- Indexes for table `penggajian`
--
ALTER TABLE `penggajian`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pengumuman`
--
ALTER TABLE `pengumuman`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reimbursement`
--
ALTER TABLE `reimbursement`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `riwayat_jabatan`
--
ALTER TABLE `riwayat_jabatan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shift`
--
ALTER TABLE `shift`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `cuti`
--
ALTER TABLE `cuti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `hari_libur`
--
ALTER TABLE `hari_libur`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `karyawan`
--
ALTER TABLE `karyawan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `kasbon`
--
ALTER TABLE `kasbon`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `kasbon_potongan`
--
ALTER TABLE `kasbon_potongan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `komponen_gaji`
--
ALTER TABLE `komponen_gaji`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `komponen_karyawan`
--
ALTER TABLE `komponen_karyawan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `lembur`
--
ALTER TABLE `lembur`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `log_absensi`
--
ALTER TABLE `log_absensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `payroll_periode`
--
ALTER TABLE `payroll_periode`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=404;

--
-- AUTO_INCREMENT for table `pengaturan`
--
ALTER TABLE `pengaturan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `penggajian`
--
ALTER TABLE `penggajian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `pengumuman`
--
ALTER TABLE `pengumuman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reimbursement`
--
ALTER TABLE `reimbursement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `riwayat_jabatan`
--
ALTER TABLE `riwayat_jabatan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `shift`
--
ALTER TABLE `shift`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `log_absensi`
--
ALTER TABLE `log_absensi`
  ADD CONSTRAINT `fk_log_karyawan` FOREIGN KEY (`nik`) REFERENCES `karyawan` (`nik`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
