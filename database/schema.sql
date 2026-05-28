-- KasirRapi database schema
-- Struktur tabel saja. Tidak berisi data user, transaksi, barang, atau kredensial.
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table structure for `absensi`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `absensi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `jadwal_shift_id` int(11) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `jam_masuk` datetime DEFAULT NULL,
  `jam_pulang` datetime DEFAULT NULL,
  `metode_masuk` enum('Wajah','Manual','Koreksi','Sistem') DEFAULT NULL,
  `metode_pulang` enum('Wajah','Manual','Koreksi','Sistem') DEFAULT NULL,
  `foto_masuk` varchar(255) DEFAULT NULL,
  `foto_pulang` varchar(255) DEFAULT NULL,
  `confidence_masuk` decimal(8,6) DEFAULT NULL,
  `confidence_pulang` decimal(8,6) DEFAULT NULL,
  `status_kehadiran` enum('Hadir','Terlambat','Pulang Cepat','Tidak Hadir','Izin','Cuti','Sakit','Libur','Belum Pulang') NOT NULL DEFAULT 'Belum Pulang',
  `menit_terlambat` int(11) NOT NULL DEFAULT 0,
  `menit_pulang_cepat` int(11) NOT NULL DEFAULT 0,
  `durasi_kerja_menit` int(11) NOT NULL DEFAULT 0,
  `catatan` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `latitude_absen` decimal(10,8) DEFAULT NULL,
  `longitude_absen` decimal(11,8) DEFAULT NULL,
  `jarak_meter` int(11) DEFAULT NULL,
  `lokasi_valid` tinyint(1) NOT NULL DEFAULT 0,
  `akurasi_meter` int(11) DEFAULT NULL,
  `face_distance` decimal(8,6) DEFAULT NULL,
  `threshold` decimal(8,6) DEFAULT NULL,
  `descriptor` longtext DEFAULT NULL,
  `metode` varchar(50) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_absensi_user_tanggal` (`tenant_id`,`user_id`,`tanggal`),
  KEY `idx_absensi_tenant_tanggal` (`tenant_id`,`tanggal`),
  KEY `idx_absensi_user` (`user_id`),
  KEY `idx_absensi_jadwal` (`jadwal_shift_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `barang`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `barang` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `kode_barang` varchar(50) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `barcode_eceran` varchar(100) DEFAULT NULL,
  `nama_barang` varchar(150) NOT NULL,
  `kategori` varchar(50) DEFAULT NULL,
  `harga_beli` decimal(10,2) NOT NULL DEFAULT 0.00,
  `harga_jual` decimal(10,2) NOT NULL DEFAULT 0.00,
  `harga_ecer` decimal(15,2) DEFAULT NULL,
  `stok_gudang` int(11) NOT NULL DEFAULT 0,
  `satuan` varchar(20) DEFAULT NULL,
  `isi_per_kemasan` int(11) DEFAULT 1,
  `satuan_ecer` varchar(50) DEFAULT NULL,
  `foto_barang` varchar(255) DEFAULT NULL,
  `is_aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_barang_supplier` (`supplier_id`),
  KEY `idx_barang_barcode_eceran` (`tenant_id`,`barcode_eceran`),
  CONSTRAINT `barang_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_barang_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------
-- Table structure for `barang_harga_riwayat`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `barang_harga_riwayat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `jenis_harga` enum('harga_beli','harga_jual','harga_ecer') NOT NULL,
  `harga_lama` decimal(15,2) NOT NULL DEFAULT 0.00,
  `harga_baru` decimal(15,2) NOT NULL DEFAULT 0.00,
  `mulai_berlaku` datetime NOT NULL DEFAULT current_timestamp(),
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_barang` (`tenant_id`,`barang_id`),
  KEY `idx_barang_jenis` (`barang_id`,`jenis_harga`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------
-- Table structure for `diskon_rules`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `diskon_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `nama_diskon` varchar(120) NOT NULL,
  `kondisi` enum('minimal_belanja','produk_tertentu','qty_produk','metode_bayar') NOT NULL DEFAULT 'minimal_belanja',
  `barang_id` int(11) DEFAULT NULL,
  `metode_bayar` enum('Tunai','QRIS','Transfer','Hutang') DEFAULT NULL,
  `min_subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `min_qty` int(11) NOT NULL DEFAULT 0,
  `tipe_diskon` enum('persen','nominal') NOT NULL DEFAULT 'persen',
  `nilai_diskon` decimal(15,2) NOT NULL DEFAULT 0.00,
  `max_diskon` decimal(15,2) NOT NULL DEFAULT 0.00,
  `mulai` date DEFAULT NULL,
  `selesai` date DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `prioritas` int(11) NOT NULL DEFAULT 100,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_diskon_tenant_aktif` (`tenant_id`,`aktif`),
  KEY `idx_diskon_barang` (`barang_id`),
  KEY `idx_diskon_periode` (`mulai`,`selesai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `face_absen_log`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `face_absen_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id_terdeteksi` int(11) DEFAULT NULL,
  `user_id_diklaim` int(11) DEFAULT NULL,
  `absensi_id` int(11) DEFAULT NULL,
  `jenis_absen` enum('Masuk','Pulang','Enroll','Gagal') NOT NULL DEFAULT 'Gagal',
  `foto_capture` varchar(255) DEFAULT NULL,
  `similarity_score` decimal(8,6) DEFAULT NULL,
  `threshold_similarity` decimal(8,6) DEFAULT NULL,
  `liveness_passed` tinyint(1) NOT NULL DEFAULT 0,
  `liveness_score` decimal(8,6) DEFAULT NULL,
  `quality_score` decimal(8,6) DEFAULT NULL,
  `hasil` enum('Berhasil','Gagal','Review') NOT NULL DEFAULT 'Review',
  `alasan_gagal` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_face_log_tenant_time` (`tenant_id`,`created_at`),
  KEY `idx_face_log_user` (`user_id_diklaim`),
  KEY `idx_face_log_absensi` (`absensi_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `jadwal_shift`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `jadwal_shift` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `status_jadwal` enum('Dijadwalkan','Libur','Izin','Cuti','Sakit','Tukar Shift','Dibatalkan') NOT NULL DEFAULT 'Dijadwalkan',
  `catatan` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_jadwal_user_tanggal` (`tenant_id`,`user_id`,`tanggal`),
  KEY `idx_jadwal_tenant_tanggal` (`tenant_id`,`tanggal`),
  KEY `idx_jadwal_user` (`user_id`),
  KEY `idx_jadwal_shift` (`shift_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `karyawan_gaji`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `karyawan_gaji` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `gaji_pokok` decimal(15,2) NOT NULL DEFAULT 0.00,
  `uang_makan_per_hari` decimal(15,2) NOT NULL DEFAULT 0.00,
  `uang_transport_per_hari` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tarif_lembur_per_jam` decimal(15,2) NOT NULL DEFAULT 0.00,
  `potongan_terlambat_per_menit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `potongan_pulang_cepat_per_menit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `potongan_alpha_per_hari` decimal(15,2) NOT NULL DEFAULT 0.00,
  `jam_kerja_normal_menit` int(11) NOT NULL DEFAULT 480,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_gaji_user` (`tenant_id`,`user_id`),
  KEY `idx_gaji_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `karyawan_wajah`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `karyawan_wajah` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  `jumlah_embedding` int(11) NOT NULL DEFAULT 0,
  `threshold_similarity` decimal(8,6) NOT NULL DEFAULT 0.520000,
  `model_name` varchar(100) NOT NULL DEFAULT 'face-api.js browser',
  `model_version` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_wajah_user` (`tenant_id`,`user_id`),
  KEY `idx_wajah_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `karyawan_wajah_embedding`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `karyawan_wajah_embedding` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `karyawan_wajah_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `foto_referensi` varchar(255) DEFAULT NULL,
  `embedding_json` mediumtext NOT NULL,
  `pose_label` varchar(50) DEFAULT NULL,
  `quality_score` decimal(8,6) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_embedding_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_embedding_profile` (`karyawan_wajah_id`),
  KEY `idx_embedding_aktif` (`aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `notifikasi`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifikasi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `pengirim_id` int(11) DEFAULT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `target_role` enum('Owner','Admin','Gudang','Kasir','HRD','Semua') DEFAULT 'Semua',
  `tipe` enum('Info','Pengumuman','Approval','Absensi','Gaji','Piutang','Stok','Sistem') NOT NULL DEFAULT 'Info',
  `judul` varchar(150) NOT NULL,
  `pesan` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `prioritas` enum('Normal','Penting','Darurat') NOT NULL DEFAULT 'Normal',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifikasi_tenant_created` (`tenant_id`,`created_at`),
  KEY `idx_notifikasi_target_user` (`tenant_id`,`target_user_id`),
  KEY `idx_notifikasi_target_role` (`tenant_id`,`target_role`),
  KEY `idx_notifikasi_tipe` (`tenant_id`,`tipe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `notifikasi_read`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifikasi_read` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `notifikasi_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_read_user_notif` (`notifikasi_id`,`user_id`),
  KEY `idx_read_tenant_user` (`tenant_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `payroll_detail`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payroll_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `payroll_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `hari_jadwal` int(11) NOT NULL DEFAULT 0,
  `hari_hadir` int(11) NOT NULL DEFAULT 0,
  `hari_izin` int(11) NOT NULL DEFAULT 0,
  `hari_cuti` int(11) NOT NULL DEFAULT 0,
  `hari_sakit` int(11) NOT NULL DEFAULT 0,
  `hari_alpha` int(11) NOT NULL DEFAULT 0,
  `total_menit_terlambat` int(11) NOT NULL DEFAULT 0,
  `total_menit_pulang_cepat` int(11) NOT NULL DEFAULT 0,
  `total_menit_kerja` int(11) NOT NULL DEFAULT 0,
  `total_jam_lembur` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gaji_pokok` decimal(15,2) NOT NULL DEFAULT 0.00,
  `uang_makan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `uang_transport` decimal(15,2) NOT NULL DEFAULT 0.00,
  `uang_lembur` decimal(15,2) NOT NULL DEFAULT 0.00,
  `bonus` decimal(15,2) NOT NULL DEFAULT 0.00,
  `potongan_terlambat` decimal(15,2) NOT NULL DEFAULT 0.00,
  `potongan_pulang_cepat` decimal(15,2) NOT NULL DEFAULT 0.00,
  `potongan_alpha` decimal(15,2) NOT NULL DEFAULT 0.00,
  `potongan_lain` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_gaji` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status_bayar` enum('Belum Dibayar','Sudah Dibayar') NOT NULL DEFAULT 'Belum Dibayar',
  `metode_bayar` enum('Tunai','Transfer','QRIS') DEFAULT NULL,
  `tanggal_bayar` datetime DEFAULT NULL,
  `dibayar_by` int(11) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_payroll_user` (`payroll_id`,`user_id`),
  KEY `idx_detail_tenant` (`tenant_id`),
  KEY `idx_detail_user` (`tenant_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `payroll_pembayaran`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payroll_pembayaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `payroll_id` int(11) NOT NULL,
  `payroll_detail_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nominal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `metode_bayar` enum('Tunai','Transfer','QRIS') NOT NULL DEFAULT 'Tunai',
  `tanggal_bayar` datetime NOT NULL,
  `catatan` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bayar_tenant` (`tenant_id`),
  KEY `idx_bayar_payroll` (`payroll_id`),
  KEY `idx_bayar_user` (`tenant_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `payroll_periode`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payroll_periode` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `nama_periode` varchar(100) NOT NULL,
  `periode_mulai` date NOT NULL,
  `periode_selesai` date NOT NULL,
  `status` enum('Draft','Dikunci','Dibayar') NOT NULL DEFAULT 'Draft',
  `total_gaji` decimal(15,2) NOT NULL DEFAULT 0.00,
  `dibuat_by` int(11) DEFAULT NULL,
  `dibayar_by` int(11) DEFAULT NULL,
  `dibayar_at` datetime DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payroll_tenant_periode` (`tenant_id`,`periode_mulai`,`periode_selesai`),
  KEY `idx_payroll_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `pelanggan`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pelanggan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `nama_pelanggan` varchar(150) NOT NULL,
  `no_wa` varchar(30) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `limit_hutang` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pelanggan_tenant` (`tenant_id`),
  KEY `idx_pelanggan_nama` (`nama_pelanggan`),
  CONSTRAINT `fk_pelanggan_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `pembelian`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pembelian` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `nomor_nota` varchar(50) DEFAULT NULL,
  `foto_nota` varchar(255) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `total_pembelian` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `supplier_id` (`supplier_id`),
  CONSTRAINT `pembelian_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pembelian_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------
-- Table structure for `pembelian_detail`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pembelian_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pembelian_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `harga` decimal(15,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `pembelian_id` (`pembelian_id`),
  KEY `barang_id` (`barang_id`),
  CONSTRAINT `pembelian_detail_ibfk_1` FOREIGN KEY (`pembelian_id`) REFERENCES `pembelian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pembelian_detail_ibfk_2` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `pengajuan_absensi`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pengajuan_absensi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `jenis` enum('Izin','Cuti','Sakit','Koreksi Masuk','Koreksi Pulang','Lembur') NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `alasan` text DEFAULT NULL,
  `lampiran` varchar(255) DEFAULT NULL,
  `status` enum('Menunggu','Disetujui','Ditolak','Dibatalkan') NOT NULL DEFAULT 'Menunggu',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `catatan_approval` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pengajuan_tenant_status` (`tenant_id`,`status`),
  KEY `idx_pengajuan_user` (`user_id`),
  KEY `idx_pengajuan_tanggal` (`tanggal_mulai`,`tanggal_selesai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `piutang`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `piutang` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `pelanggan_id` int(11) NOT NULL,
  `transaksi_id` int(11) NOT NULL,
  `nomor_invoice` varchar(50) NOT NULL,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `jumlah_piutang` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_dibayar` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sisa_piutang` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('Belum Lunas','Lunas','Batal') NOT NULL DEFAULT 'Belum Lunas',
  `jatuh_tempo` date DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_piutang_transaksi` (`transaksi_id`),
  KEY `idx_piutang_tenant_status` (`tenant_id`,`status`),
  KEY `idx_piutang_pelanggan` (`pelanggan_id`),
  CONSTRAINT `fk_piutang_pelanggan` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_piutang_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_piutang_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `piutang_pembayaran`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `piutang_pembayaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `piutang_id` int(11) NOT NULL,
  `kasir_id` int(11) NOT NULL,
  `tanggal_bayar` datetime NOT NULL DEFAULT current_timestamp(),
  `metode_bayar` enum('Tunai','QRIS','Transfer') NOT NULL DEFAULT 'Tunai',
  `nominal_bayar` decimal(15,2) NOT NULL DEFAULT 0.00,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bayar_tenant` (`tenant_id`),
  KEY `idx_bayar_piutang` (`piutang_id`),
  KEY `idx_bayar_kasir` (`kasir_id`),
  CONSTRAINT `fk_bayar_kasir` FOREIGN KEY (`kasir_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bayar_piutang` FOREIGN KEY (`piutang_id`) REFERENCES `piutang` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bayar_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `shift_tukar`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shift_tukar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jadwal_asal_id` int(11) DEFAULT NULL,
  `user_asal_id` int(11) NOT NULL,
  `user_pengganti_id` int(11) NOT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `alasan` text DEFAULT NULL,
  `status` enum('Menunggu','Disetujui','Ditolak','Dibatalkan') NOT NULL DEFAULT 'Menunggu',
  `requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tukar_tenant_status` (`tenant_id`,`status`),
  KEY `idx_tukar_tanggal` (`tanggal`),
  KEY `idx_tukar_user_asal` (`user_asal_id`),
  KEY `idx_tukar_user_pengganti` (`user_pengganti_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `shifts`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `nama_shift` varchar(100) NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `toleransi_terlambat_menit` int(11) NOT NULL DEFAULT 10,
  `toleransi_pulang_cepat_menit` int(11) NOT NULL DEFAULT 10,
  `lintas_hari` tinyint(1) NOT NULL DEFAULT 0,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_shifts_tenant` (`tenant_id`),
  KEY `idx_shifts_aktif` (`aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `suppliers`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `nama_supplier` varchar(100) NOT NULL,
  `no_wa` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------
-- Table structure for `registrasi_toko`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `registrasi_toko` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_ref` varchar(50) NOT NULL,
  `tripay_reference` varchar(100) DEFAULT NULL,
  `checkout_url` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_name` varchar(100) DEFAULT NULL,
  `pay_code` varchar(100) DEFAULT NULL,
  `qr_url` text DEFAULT NULL,
  `nama_toko` varchar(100) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `nama_pemilik` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `no_wa` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `paket_langganan` enum('Gratis','Basic','Plus','Pro') NOT NULL DEFAULT 'Gratis',
  `amount` int(11) NOT NULL DEFAULT 0,
  `billing_cycle` enum('monthly','yearly','trial') NOT NULL DEFAULT 'monthly',
  `addon_hrd_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `max_users` int(11) NOT NULL DEFAULT 1,
  `max_products` int(11) NOT NULL DEFAULT 100,
  `max_transactions_per_month` int(11) NOT NULL DEFAULT 100,
  `max_employees` int(11) NOT NULL DEFAULT 0,
  `max_outlets` int(11) NOT NULL DEFAULT 1,
  `status` enum('PENDING','UNPAID','PAID','EXPIRED','FAILED','REFUND','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `tenant_id` int(11) DEFAULT NULL,
  `raw_response` longtext DEFAULT NULL,
  `callback_payload` longtext DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `trial_ends_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `merchant_ref` (`merchant_ref`),
  KEY `idx_registrasi_email` (`email`),
  KEY `idx_registrasi_status` (`status`),
  KEY `idx_registrasi_tripay_reference` (`tripay_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `tenants`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tenants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_toko` varchar(100) NOT NULL,
  `slug` varchar(50) DEFAULT NULL,
  `nama_pemilik` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `no_wa` varchar(20) DEFAULT NULL,
  `paket_langganan` enum('Gratis','Basic','Plus','Pro') DEFAULT 'Gratis',
  `plan` varchar(20) NOT NULL DEFAULT 'Gratis',
  `plan_expired_at` datetime DEFAULT NULL,
  `max_users` int(11) NOT NULL DEFAULT 1,
  `max_products` int(11) NOT NULL DEFAULT 100,
  `max_transactions_per_month` int(11) NOT NULL DEFAULT 100,
  `addon_hrd_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `max_employees` int(11) NOT NULL DEFAULT 0,
  `max_outlets` int(11) NOT NULL DEFAULT 1,
  `trial_ends_at` datetime DEFAULT NULL,
  `status` enum('Aktif','Suspend') DEFAULT 'Aktif',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `alamat_toko` text DEFAULT NULL,
  `logo_struk` varchar(255) DEFAULT NULL,
  `catatan_struk` varchar(180) DEFAULT NULL,
  `maps_url` text DEFAULT NULL,
  `latitude_toko` decimal(10,8) DEFAULT NULL,
  `longitude_toko` decimal(11,8) DEFAULT NULL,
  `radius_absensi_meter` int(11) NOT NULL DEFAULT 100,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------
-- Table structure for `transaksi`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transaksi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `kasir_id` int(11) NOT NULL,
  `pelanggan_id` int(11) DEFAULT NULL,
  `nomor_invoice` varchar(50) NOT NULL,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `subtotal` decimal(15,2) NOT NULL,
  `diskon` decimal(15,2) DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL,
  `metode_bayar` enum('Tunai','QRIS','Transfer','Hutang') DEFAULT 'Tunai',
  `nominal_bayar` decimal(15,2) NOT NULL,
  `kembalian` decimal(15,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `kasir_id` (`kasir_id`),
  KEY `idx_transaksi_pelanggan` (`pelanggan_id`),
  CONSTRAINT `fk_transaksi_pelanggan` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `transaksi_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transaksi_ibfk_2` FOREIGN KEY (`kasir_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------
-- Table structure for `transaksi_detail`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transaksi_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaksi_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `harga_satuan` decimal(10,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `tipe_satuan` enum('kemasan','eceran') NOT NULL DEFAULT 'eceran',
  `satuan_jual` varchar(50) DEFAULT NULL,
  `isi_per_kemasan` int(11) NOT NULL DEFAULT 1,
  `qty_konversi_stok` int(11) NOT NULL DEFAULT 0,
  `harga_modal_satuan` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_transaksi_detail_barang_id` (`barang_id`),
  KEY `idx_transaksi_detail_transaksi_id` (`transaksi_id`),
  CONSTRAINT `transaksi_detail_ibfk_1` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transaksi_detail_ibfk_2` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------
-- Table structure for `support_tickets`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_code` varchar(40) NOT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `nama_toko` varchar(100) DEFAULT NULL,
  `nama_user` varchar(100) DEFAULT NULL,
  `subject` varchar(180) NOT NULL,
  `message` text NOT NULL,
  `chat_history` longtext DEFAULT NULL,
  `cs_name` varchar(30) DEFAULT NULL,
  `source` varchar(50) NOT NULL DEFAULT 'live_chat',
  `status` enum('Baru','Diproses','Selesai') NOT NULL DEFAULT 'Baru',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_code` (`ticket_code`),
  KEY `idx_support_status` (`status`),
  KEY `idx_support_email` (`email`),
  KEY `idx_support_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `users`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expired` datetime DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Owner','Admin','Gudang','Kasir','HRD') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

SET FOREIGN_KEY_CHECKS = 1;
