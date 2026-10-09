-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: Database_Gabungan_Sarpras
-- ------------------------------------------------------
-- Server version	8.0.30

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

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `finance_locations`
--

DROP TABLE IF EXISTS `finance_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_lokasi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `finance_locations`
--

LOCK TABLES `finance_locations` WRITE;
/*!40000 ALTER TABLE `finance_locations` DISABLE KEYS */;
INSERT INTO `finance_locations` VALUES (1,'Umum','Lokasi default untuk data sebelum pembaruan.','aktif','2026-10-09 07:57:51','2026-10-09 08:33:33');
/*!40000 ALTER TABLE `finance_locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `finance_reports`
--

DROP TABLE IF EXISTS `finance_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `location_id` bigint unsigned DEFAULT NULL,
  `tanggal` date NOT NULL,
  `kondisi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `foto_disk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `finance_reports_user_id_foreign` (`user_id`),
  KEY `finance_reports_location_id_foreign` (`location_id`),
  CONSTRAINT `finance_reports_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `finance_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `finance_reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `finance_reports`
--

LOCK TABLES `finance_reports` WRITE;
/*!40000 ALTER TABLE `finance_reports` DISABLE KEYS */;
INSERT INTO `finance_reports` VALUES (1,1,1,'2026-10-09','normal','Test finance report','test.jpg','public','2026-10-09 08:24:37','2026-10-09 08:24:37');
/*!40000 ALTER TABLE `finance_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `items`
--

DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `min_stock` int NOT NULL DEFAULT '0',
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `items_item_code_unique` (`item_code`),
  KEY `idx_items_name` (`name`),
  KEY `idx_items_search` (`item_code`,`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `items`
--

LOCK TABLES `items` WRITE;
/*!40000 ALTER TABLE `items` DISABLE KEYS */;
INSERT INTO `items` VALUES (1,'TEST001','Test Item Low Stock',NULL,1000.00,6,10,'pcs',NULL,'2026-10-09 08:06:23','2026-10-09 08:25:07'),(2,'TEST002','Test Item Safe Stock',NULL,2000.00,20,5,'pcs',NULL,'2026-10-09 08:06:23','2026-10-09 08:06:23');
/*!40000 ALTER TABLE `items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2014_10_12_000000_create_users_table',1),(2,'2014_10_12_100000_create_password_reset_tokens_table',1),(3,'2019_08_19_000000_create_failed_jobs_table',1),(4,'2019_12_14_000001_create_personal_access_tokens_table',1),(5,'2026_09_11_033225_create_monitoring_locations_table',1),(6,'2026_09_11_033225_create_water_monitorings_table',1),(7,'2026_09_16_140752_drop_jenis_from_monitoring_locations_table',1),(8,'2026_09_18_000000_create_finance_reports_table',1),(9,'2026_09_18_000100_create_finance_locations_table',1),(10,'2026_09_18_000200_add_location_id_to_finance_reports_table',1),(11,'2026_09_22_000000_change_location_fk_on_water_monitorings_to_restrict',1),(12,'2026_09_28_150000_add_foto_disk_to_photo_tables',1),(13,'2026_09_29_032820_create_rusun_table',1),(14,'2026_09_29_032826_create_items_table',1),(15,'2026_09_29_032827_create_withdrawals_table',1),(16,'2026_09_29_041723_add_min_stock_to_items_table',1),(17,'2026_10_05_100000_create_withdrawal_items_table',1),(18,'2026_10_05_100100_migrate_withdrawals_to_items_structure',1),(19,'2026_10_05_100200_restrict_withdrawals_rusun_foreign_key',1),(20,'2026_10_05_100300_remove_legacy_columns_from_withdrawals_table',1),(21,'2026_10_06_100804_create_stock_adjustments_table',1),(22,'2026_10_07_073600_add_performance_indexes',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `monitoring_locations`
--

DROP TABLE IF EXISTS `monitoring_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `monitoring_locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_lokasi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `monitoring_locations`
--

LOCK TABLES `monitoring_locations` WRITE;
/*!40000 ALTER TABLE `monitoring_locations` DISABLE KEYS */;
INSERT INTO `monitoring_locations` VALUES (1,'GWT','Ground Water Tank utama','aktif','2026-10-09 07:57:51','2026-10-09 07:57:51'),(2,'Kolam Air Bersih','Kolam air bersih utama','aktif','2026-10-09 07:57:51','2026-10-09 07:57:51');
/*!40000 ALTER TABLE `monitoring_locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rusun`
--

DROP TABLE IF EXISTS `rusun`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rusun` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rusun_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rusun`
--

LOCK TABLES `rusun` WRITE;
/*!40000 ALTER TABLE `rusun` DISABLE KEYS */;
INSERT INTO `rusun` VALUES (1,'RABEK','Rawa Bebek','2026-10-09 07:57:51','2026-10-09 07:57:51'),(2,'UMEN','Umen','2026-10-09 07:57:51','2026-10-09 07:57:51'),(3,'TIPAR','Tipar','2026-10-09 07:57:51','2026-10-09 07:57:51'),(4,'ALBO','Albo','2026-10-09 07:57:51','2026-10-09 07:57:51'),(5,'CBT','CBT','2026-10-09 07:57:51','2026-10-09 07:57:51'),(6,'KM2','KM2','2026-10-09 07:57:51','2026-10-09 07:57:51');
/*!40000 ALTER TABLE `rusun` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_adjustments`
--

DROP TABLE IF EXISTS `stock_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_adjustments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `type` enum('add','subtract') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int unsigned NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_adjustments_user_id_foreign` (`user_id`),
  KEY `stock_adjustments_item_id_created_at_index` (`item_id`,`created_at`),
  CONSTRAINT `stock_adjustments_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_adjustments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_adjustments`
--

LOCK TABLES `stock_adjustments` WRITE;
/*!40000 ALTER TABLE `stock_adjustments` DISABLE KEYS */;
INSERT INTO `stock_adjustments` VALUES (1,1,1,'add',10,'Test adjustment','2026-10-09 08:25:05','2026-10-09 08:25:05');
/*!40000 ALTER TABLE `stock_adjustments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'petugas',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin Sarpras','admin@example.com',NULL,'$2y$10$d4iovRDv0CqzDm9z0cDygumo0KrBycGwr9F3WrPubO79OpKTCSYI2','admin',NULL,'2026-10-09 07:57:51','2026-10-09 07:57:51'),(2,'Petugas Sarpras','petugas@example.com',NULL,'$2y$10$0AaX4uBwoETSUeG382lf/e3kBhjOecRa0ewwbDsVzYTc9cj0ojWiu','petugas',NULL,'2026-10-09 07:57:51','2026-10-09 07:57:51'),(3,'Admin','admin@sarpras.com',NULL,'$2y$10$AQebqhx2Vj4w3kV7HJ9YyudaO1sComZGylueUE2FxRf2ADX30BZ3a','admin',NULL,'2026-10-09 07:59:05','2026-10-09 07:59:05'),(4,'Petugas','petugas@sarpras.com',NULL,'$2y$10$4CWU5HuXlPcDy6J1q6eFruGJINAbsTRvI1PMPBO.kn0W7.ZhH7Vuq','petugas',NULL,'2026-10-09 07:59:05','2026-10-09 07:59:05'),(5,'Invalid Role User','invalid@test.com',NULL,'$2y$10$xhwZIwCxJLal6P.TQYLhf.Ba/4Pb1qlB8xjun9GwlN.ddRXKP4lji','guest',NULL,'2026-10-09 07:59:05','2026-10-09 07:59:05');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `water_monitorings`
--

DROP TABLE IF EXISTS `water_monitorings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `water_monitorings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `location_id` bigint unsigned NOT NULL,
  `tanggal` date NOT NULL,
  `sesi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `waktu` time NOT NULL,
  `kondisi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `foto_disk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `water_monitorings_location_id_tanggal_sesi_unique` (`location_id`,`tanggal`,`sesi`),
  KEY `water_monitorings_user_id_foreign` (`user_id`),
  CONSTRAINT `water_monitorings_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `monitoring_locations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `water_monitorings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `water_monitorings`
--

LOCK TABLES `water_monitorings` WRITE;
/*!40000 ALTER TABLE `water_monitorings` DISABLE KEYS */;
INSERT INTO `water_monitorings` VALUES (1,2,1,'2026-10-09','pagi','08:00:00','normal','Test monitoring untuk sesi pagi','monitoring/test_pagi_1791532683.jpg','evidence','2026-10-09 07:58:03','2026-10-09 07:58:03'),(2,2,1,'2026-10-09','siang','12:00:00','debit_turun','EDITED: Test keterangan updated','monitoring/test_updated_1791532683.jpg','evidence','2026-10-09 07:58:03','2026-10-09 07:58:03'),(5,2,2,'2026-10-09','pagi','08:00:00','debit_turun','Test kondisi Debit Turun','monitoring/test_kondisi_debit_turun_1791532683.jpg','evidence','2026-10-09 07:58:03','2026-10-09 07:58:03'),(6,2,2,'2026-10-09','siang','12:00:00','tekanan_air_kecil','Test kondisi Tekanan Air Kecil','monitoring/test_kondisi_tekanan_air_kecil_1791532683.jpg','evidence','2026-10-09 07:58:03','2026-10-09 07:58:03'),(7,1,2,'2026-10-09','sore','16:00:00','normal','Admin entry for access control test','test.jpg','evidence','2026-10-09 07:58:11','2026-10-09 07:58:11');
/*!40000 ALTER TABLE `water_monitorings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `withdrawal_items`
--

DROP TABLE IF EXISTS `withdrawal_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `withdrawal_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `withdrawal_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `withdrawal_items_withdrawal_item_unique` (`withdrawal_id`,`item_id`),
  KEY `withdrawal_items_item_id_foreign` (`item_id`),
  CONSTRAINT `withdrawal_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `withdrawal_items_withdrawal_id_foreign` FOREIGN KEY (`withdrawal_id`) REFERENCES `withdrawals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `withdrawal_items`
--

LOCK TABLES `withdrawal_items` WRITE;
/*!40000 ALTER TABLE `withdrawal_items` DISABLE KEYS */;
INSERT INTO `withdrawal_items` VALUES (1,1,1,5,1000.00,5000.00,'2026-10-09 08:23:35','2026-10-09 08:23:35');
/*!40000 ALTER TABLE `withdrawal_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `withdrawals`
--

DROP TABLE IF EXISTS `withdrawals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `withdrawals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `taken_by` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rusun_id` bigint unsigned NOT NULL,
  `taken_at` datetime NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `total_quantity` int NOT NULL DEFAULT '0',
  `total_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `withdrawals_rusun_id_foreign` (`rusun_id`),
  KEY `idx_withdrawals_taken_at` (`taken_at`),
  KEY `idx_withdrawals_taken_by` (`taken_by`),
  CONSTRAINT `withdrawals_rusun_id_foreign` FOREIGN KEY (`rusun_id`) REFERENCES `rusun` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `withdrawals`
--

LOCK TABLES `withdrawals` WRITE;
/*!40000 ALTER TABLE `withdrawals` DISABLE KEYS */;
INSERT INTO `withdrawals` VALUES (1,'Test User',1,'2026-10-09 15:05:50','Test withdrawal','2026-10-09 08:05:50','2026-10-09 08:05:50',10,50000.00);
/*!40000 ALTER TABLE `withdrawals` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-09 15:43:08
