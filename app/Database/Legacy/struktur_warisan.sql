
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `biaya`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `biaya` (
  `id_biaya` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int unsigned NOT NULL,
  `biaya_id` enum('1','2') NOT NULL,
  `nominal` int NOT NULL,
  `label` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id_biaya`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dibeli`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dibeli` (
  `id_beli` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int unsigned NOT NULL,
  `stok_id` int unsigned DEFAULT NULL,
  `kode` varchar(20) NOT NULL,
  `ukuran` varchar(6) DEFAULT NULL,
  `qty` int unsigned NOT NULL,
  `harga` int unsigned NOT NULL,
  PRIMARY KEY (`id_beli`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dj_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dj_sessions` (
  `id` varchar(128) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `timestamp` int unsigned NOT NULL DEFAULT '0',
  `data` blob NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ci_sessions_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice` (
  `id_invoice` int unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_pesan` date NOT NULL,
  `seri` varchar(14) NOT NULL,
  `juragan_id` int unsigned NOT NULL,
  `pemesan_id` int unsigned NOT NULL,
  `kirimKepada_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `status_pesanan` enum('1','2','3') NOT NULL DEFAULT '1',
  `status_pembayaran` enum('1','2','3','4','5','6') NOT NULL DEFAULT '1',
  `status_pengiriman` enum('1','2','3') NOT NULL DEFAULT '1',
  `keterangan` text,
  `created_at` int unsigned NOT NULL,
  `update_at` int unsigned NOT NULL,
  `deleted_at` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id_invoice`)
) ENGINE=InnoDB AUTO_INCREMENT=8569 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_status` (
  `id_status` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int unsigned NOT NULL,
  `status` enum('1','2','3','4','5','6','7') DEFAULT NULL,
  `tanggal_masuk` int unsigned DEFAULT NULL,
  `tanggal_selesai` int unsigned DEFAULT NULL,
  `keterangan_masuk` varchar(100) DEFAULT NULL,
  `keterangan_selesai` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `label_invoice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `label_invoice` (
  `id_label` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int unsigned NOT NULL,
  `source_id` int unsigned NOT NULL,
  `label` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_label`)
) ENGINE=InnoDB AUTO_INCREMENT=10908 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifikasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifikasi` (
  `id_notifikasi` int unsigned NOT NULL AUTO_INCREMENT,
  `tanggal` varchar(11) NOT NULL,
  `dari` int unsigned NOT NULL,
  `kepada` int unsigned NOT NULL,
  `type` int unsigned NOT NULL DEFAULT '1',
  `juragan` int unsigned DEFAULT NULL,
  `url` varchar(20) NOT NULL,
  `dibaca` enum('0','1') NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_notifikasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pelanggan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pelanggan` (
  `id_pelanggan` int unsigned NOT NULL AUTO_INCREMENT,
  `nama_pelanggan` varchar(60) NOT NULL,
  `hp` varchar(50) DEFAULT NULL,
  `cod` enum('0','1') NOT NULL DEFAULT '0',
  `kecamatan` int unsigned DEFAULT NULL,
  `kabupaten` int unsigned DEFAULT NULL,
  `provinsi` int unsigned DEFAULT NULL,
  `alamat` text,
  `kodepos` int unsigned DEFAULT NULL,
  `created_at` int unsigned NOT NULL,
  `updated_at` int unsigned NOT NULL,
  `deleted_at` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id_pelanggan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `relasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `relasi` (
  `id_relasi` int unsigned NOT NULL AUTO_INCREMENT,
  `table` int unsigned NOT NULL,
  `juragan_id` int unsigned NOT NULL,
  `val_id` int unsigned NOT NULL,
  PRIMARY KEY (`id_relasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(60) NOT NULL,
  `name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `level` enum('superadmin','admin','cs','viewer','reseller') NOT NULL DEFAULT 'viewer',
  `status` enum('pending','inactive','active','blocked') NOT NULL DEFAULT 'pending',
  `login_terakhir` int unsigned DEFAULT NULL,
  `created_at` int unsigned NOT NULL,
  `updated_at` int unsigned NOT NULL,
  `deleted_at` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `versions` (
  `version` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank` (
  `id_bank` int unsigned NOT NULL AUTO_INCREMENT,
  `nama_bank` enum('bca','bri','bni','mandiri','edc','cod','lainnya') NOT NULL,
  `tipe_bank` enum('1','2','3') DEFAULT NULL COMMENT 'null:cod, 1:bank, 2:edc, 3:lainnya',
  `rekening` varchar(50) NOT NULL,
  `atas_nama` varchar(50) NOT NULL,
  `created_at` int unsigned NOT NULL,
  `updated_at` int unsigned NOT NULL,
  `deleted_at` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id_bank`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `biaya_unik`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `biaya_unik` (
  `id_unik` int unsigned NOT NULL AUTO_INCREMENT,
  `faktur_id` int NOT NULL,
  `nominal` int NOT NULL,
  PRIMARY KEY (`id_unik`)
) ENGINE=InnoDB AUTO_INCREMENT=835 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `juragan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `juragan` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `membership` enum('ya','tidak') NOT NULL DEFAULT 'tidak',
  `short` varchar(2) NOT NULL,
  `slug` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=MyISAM AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `membership`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `membership` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `user_card` varchar(100) NOT NULL,
  `nama_member` varchar(100) NOT NULL,
  `tanggal_daftar` datetime NOT NULL,
  `hp` varchar(15) NOT NULL,
  `alamat` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_card` (`user_card`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `group` varchar(255) NOT NULL,
  `namespace` varchar(255) NOT NULL,
  `time` int NOT NULL,
  `batch` int unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pengaturan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengaturan` (
  `kunci` varchar(100) NOT NULL,
  `gembok` text NOT NULL,
  UNIQUE KEY `kunci` (`kunci`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pengguna`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengguna` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `sandi` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `level` enum('superadmin','admin','cs','reseller','viewer') NOT NULL DEFAULT 'cs',
  `aktif` enum('ya','tidak') NOT NULL DEFAULT 'tidak',
  `blokir` enum('ya','tidak') NOT NULL DEFAULT 'tidak',
  `valid` enum('ya','tidak') NOT NULL DEFAULT 'tidak',
  `login_terakhir` varchar(10) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pengguna_relation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengguna_relation` (
  `id_relation` int unsigned NOT NULL AUTO_INCREMENT,
  `pengguna_id` int unsigned NOT NULL,
  `juragan_id` int unsigned NOT NULL,
  PRIMARY KEY (`id_relation`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

