-- Skema DW FORMA (umkm_dw): 6 dimensi + 3 fakta + etl_validasi. Dihasilkan dari migrasi database/migrations/dw.
CREATE DATABASE IF NOT EXISTS umkm_dw CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE umkm_dw;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dim_waktu` (
  `waktu_key` int unsigned NOT NULL,
  `tanggal` date NOT NULL,
  `hari_dalam_minggu` tinyint unsigned NOT NULL,
  `nama_hari` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis_hari` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bulan` tinyint unsigned NOT NULL,
  `nama_bulan` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kuartal` tinyint unsigned NOT NULL,
  `tahun` smallint unsigned NOT NULL,
  PRIMARY KEY (`waktu_key`),
  UNIQUE KEY `dim_waktu_tanggal_unique` (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dim_produk` (
  `produk_key` int unsigned NOT NULL AUTO_INCREMENT,
  `id_varian` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ukuran` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warna` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_produk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_produk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_kategori` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`produk_key`),
  UNIQUE KEY `dim_produk_id_varian_unique` (`id_varian`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dim_pelanggan` (
  `pelanggan_key` int unsigned NOT NULL AUTO_INCREMENT,
  `id_akun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipe_pelanggan` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_daftar` date DEFAULT NULL,
  PRIMARY KEY (`pelanggan_key`),
  UNIQUE KEY `dim_pelanggan_id_akun_unique` (`id_akun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dim_promo` (
  `promo_key` int unsigned NOT NULL AUTO_INCREMENT,
  `id_promo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_promo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `persen_diskon` decimal(5,2) NOT NULL,
  `kelompok_diskon` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_mulai` date DEFAULT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `durasi_hari` smallint unsigned NOT NULL,
  PRIMARY KEY (`promo_key`),
  UNIQUE KEY `dim_promo_id_promo_unique` (`id_promo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dim_transaksi` (
  `transaksi_key` int unsigned NOT NULL AUTO_INCREMENT,
  `kanal_penjualan` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metode_pembayaran` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`transaksi_key`),
  UNIQUE KEY `dim_transaksi_kanal_penjualan_metode_pembayaran_unique` (`kanal_penjualan`,`metode_pembayaran`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dim_supplier` (
  `supplier_key` int unsigned NOT NULL AUTO_INCREMENT,
  `id_supplier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_supplier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kota` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`supplier_key`),
  UNIQUE KEY `dim_supplier_id_supplier_unique` (`id_supplier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fact_penjualan` (
  `penjualan_fact_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `waktu_key` int unsigned NOT NULL,
  `produk_key` int unsigned NOT NULL,
  `pelanggan_key` int unsigned NOT NULL,
  `promo_key` int unsigned NOT NULL,
  `transaksi_key` int unsigned NOT NULL,
  `id_penjualan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_detail_penjualan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah` int NOT NULL,
  `nilai_kotor` decimal(14,2) NOT NULL,
  `diskon_promo` decimal(14,2) NOT NULL,
  `diskon_poin` decimal(14,2) NOT NULL,
  `pendapatan_bersih` decimal(14,2) NOT NULL,
  `hpp` decimal(14,2) NOT NULL,
  `laba_kotor` decimal(14,2) NOT NULL,
  `poin_didapat` decimal(12,2) NOT NULL,
  `poin_ditukar` decimal(12,2) NOT NULL,
  PRIMARY KEY (`penjualan_fact_id`),
  UNIQUE KEY `fact_penjualan_id_detail_penjualan_unique` (`id_detail_penjualan`),
  KEY `fact_penjualan_waktu_key_foreign` (`waktu_key`),
  KEY `fact_penjualan_produk_key_foreign` (`produk_key`),
  KEY `fact_penjualan_pelanggan_key_foreign` (`pelanggan_key`),
  KEY `fact_penjualan_promo_key_foreign` (`promo_key`),
  KEY `fact_penjualan_transaksi_key_foreign` (`transaksi_key`),
  KEY `fact_penjualan_id_penjualan_index` (`id_penjualan`),
  CONSTRAINT `fact_penjualan_pelanggan_key_foreign` FOREIGN KEY (`pelanggan_key`) REFERENCES `dim_pelanggan` (`pelanggan_key`),
  CONSTRAINT `fact_penjualan_produk_key_foreign` FOREIGN KEY (`produk_key`) REFERENCES `dim_produk` (`produk_key`),
  CONSTRAINT `fact_penjualan_promo_key_foreign` FOREIGN KEY (`promo_key`) REFERENCES `dim_promo` (`promo_key`),
  CONSTRAINT `fact_penjualan_transaksi_key_foreign` FOREIGN KEY (`transaksi_key`) REFERENCES `dim_transaksi` (`transaksi_key`),
  CONSTRAINT `fact_penjualan_waktu_key_foreign` FOREIGN KEY (`waktu_key`) REFERENCES `dim_waktu` (`waktu_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fact_pembelian` (
  `pembelian_fact_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `waktu_key` int unsigned NOT NULL,
  `produk_key` int unsigned NOT NULL,
  `supplier_key` int unsigned NOT NULL,
  `id_pembelian` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_detail_pembelian` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah` int NOT NULL,
  `total_biaya` decimal(14,2) NOT NULL,
  PRIMARY KEY (`pembelian_fact_id`),
  UNIQUE KEY `fact_pembelian_id_detail_pembelian_unique` (`id_detail_pembelian`),
  KEY `fact_pembelian_waktu_key_foreign` (`waktu_key`),
  KEY `fact_pembelian_produk_key_foreign` (`produk_key`),
  KEY `fact_pembelian_supplier_key_foreign` (`supplier_key`),
  KEY `fact_pembelian_id_pembelian_index` (`id_pembelian`),
  CONSTRAINT `fact_pembelian_produk_key_foreign` FOREIGN KEY (`produk_key`) REFERENCES `dim_produk` (`produk_key`),
  CONSTRAINT `fact_pembelian_supplier_key_foreign` FOREIGN KEY (`supplier_key`) REFERENCES `dim_supplier` (`supplier_key`),
  CONSTRAINT `fact_pembelian_waktu_key_foreign` FOREIGN KEY (`waktu_key`) REFERENCES `dim_waktu` (`waktu_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fact_pengeluaran` (
  `pengeluaran_fact_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `waktu_key` int unsigned NOT NULL,
  `id_pengeluaran` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis_pengeluaran` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nominal` decimal(14,2) NOT NULL,
  PRIMARY KEY (`pengeluaran_fact_id`),
  UNIQUE KEY `fact_pengeluaran_id_pengeluaran_unique` (`id_pengeluaran`),
  KEY `fact_pengeluaran_waktu_key_foreign` (`waktu_key`),
  CONSTRAINT `fact_pengeluaran_waktu_key_foreign` FOREIGN KEY (`waktu_key`) REFERENCES `dim_waktu` (`waktu_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `etl_validasi` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `dijalankan_pada` datetime NOT NULL,
  `kode` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pemeriksaan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nilai_oltp` decimal(18,2) NOT NULL,
  `nilai_dw` decimal(18,2) NOT NULL,
  `status` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

