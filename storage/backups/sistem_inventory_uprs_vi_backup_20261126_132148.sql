-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: sistem_inventory_uprs_vi
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
  KEY `idx_items_search` (`item_code`,`name`),
  KEY `idx_items_stock_alert` (`stock`,`min_stock`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `items`
--

LOCK TABLES `items` WRITE;
/*!40000 ALTER TABLE `items` DISABLE KEYS */;
INSERT INTO `items` VALUES (64,'BRG-001','Sapu Lidi','default.jpg',15000.00,24,10,'pcs','Sapu lidi untuk kebersihan area rusun','2026-10-08 01:11:32','2026-10-08 01:22:10'),(65,'BRG-002','Pel Lantai','default.jpg',35000.00,40,7,'pcs','Pel lantai dengan gagang stainless','2026-10-08 01:11:32','2026-10-08 03:08:05'),(66,'BRG-003','Kain Lap','default.jpg',5000.00,50,20,'pcs','Kain lap microfiber ukuran 30x30 cm','2026-10-08 01:11:32','2026-10-08 01:11:32'),(67,'BRG-004','Sabun Cuci Piring','default.jpg',12000.00,30,25,'botol','Sabun cuci piring cair 800ml','2026-10-08 01:11:32','2026-10-08 01:11:32'),(68,'BRG-005','Ember Plastik','default.jpg',25000.00,5,8,'pcs','Ember plastik kapasitas 10 liter','2026-10-08 01:11:32','2026-10-08 01:11:32'),(69,'BRG-006','Pengki Sampah','default.jpg',18000.00,12,10,'pcs','Pengki sampah plastik dengan gagang','2026-10-08 01:11:32','2026-10-08 01:11:32'),(70,'BRG-007','Sikat WC','default.jpg',22000.00,20,10,'pcs','Sikat WC dengan tempat holder','2026-10-08 01:11:32','2026-10-08 01:55:11'),(71,'BRG-008','Pembersih Lantai','default.jpg',20000.00,13,10,'botol','Cairan pembersih lantai 1 liter','2026-10-08 01:11:32','2026-10-08 01:55:38'),(72,'BRG-009','Laptop kantor','8fcf6d5f-367f-43a3-bc40-e40c44c77b86.png',9500000.00,20,5,'unit','Laptop i5 gen 12\r\nRam 8Gb\r\nStorage 256Gb','2026-10-08 02:07:35','2026-10-09 03:59:23'),(73,'BRG-010','Kursi kantor','679cf521-4e7e-487a-8b56-3ce3d8ae2e2b.png',1250000.00,19,5,'pcs',NULL,'2026-10-08 02:11:56','2026-10-08 02:15:14'),(74,'BRG-011','Meja kantor','9ef7677c-4598-41a7-8bea-fac03d319dca.png',1500000.00,19,5,'pcs',NULL,'2026-10-08 02:12:36','2026-10-08 02:15:14'),(75,'BRG-012','Monitor pc 24\"','3a660a20-7212-4040-a4c9-12ec42301823.png',1350000.00,17,5,'unit',NULL,'2026-10-08 02:14:00','2026-10-08 02:32:46');
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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2014_10_12_000000_create_users_table',1),(2,'2014_10_12_100000_create_password_reset_tokens_table',1),(3,'2019_08_19_000000_create_failed_jobs_table',1),(4,'2019_12_14_000001_create_personal_access_tokens_table',1),(5,'2026_09_29_032820_create_rusun_table',1),(6,'2026_09_29_032826_create_items_table',1),(7,'2026_09_29_032827_create_withdrawals_table',1),(8,'2026_09_29_041715_create_incoming_stocks_table',1),(9,'2026_09_29_041723_add_min_stock_to_items_table',1),(10,'2026_10_05_100000_create_withdrawal_items_table',1),(11,'2026_10_05_100100_migrate_withdrawals_to_items_structure',1),(12,'2026_10_05_100200_restrict_withdrawals_rusun_foreign_key',1),(13,'2026_10_05_100300_remove_legacy_columns_from_withdrawals_table',1),(14,'2026_10_06_083740_drop_incoming_stocks_table_feature_removed',1),(15,'2026_10_06_100804_create_stock_adjustments_table',1),(16,'2026_10_07_073600_add_performance_indexes',1),(17,'2026_10_15_105832_add_performance_indexes_to_tables',2);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rusun`
--

LOCK TABLES `rusun` WRITE;
/*!40000 ALTER TABLE `rusun` DISABLE KEYS */;
INSERT INTO `rusun` VALUES (27,'RABEK','Rawa Bebek','2026-10-08 01:11:32','2026-10-08 01:11:32'),(28,'UMEN','Umen','2026-10-08 01:11:32','2026-10-08 01:11:32'),(29,'TIPAR','Tipar','2026-10-08 01:11:32','2026-10-08 01:11:32'),(30,'ALBO','Albo','2026-10-08 01:11:32','2026-10-08 01:11:32'),(31,'CBT','CBT','2026-10-08 01:11:32','2026-10-08 01:11:32'),(32,'KM2','KM2','2026-10-08 01:11:32','2026-10-08 01:11:32');
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_adjustments`
--

LOCK TABLES `stock_adjustments` WRITE;
/*!40000 ALTER TABLE `stock_adjustments` DISABLE KEYS */;
INSERT INTO `stock_adjustments` VALUES (1,70,50,'add',17,'Penambahan stok melalui form edit barang','2026-10-08 01:55:11','2026-10-08 01:55:11'),(2,72,50,'add',5,'Penambahan stok melalui form edit barang','2026-10-08 02:08:11','2026-10-08 02:08:11'),(3,65,50,'add',33,'Penambahan stok melalui form edit barang','2026-10-08 03:08:05','2026-10-08 03:08:05');
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
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (50,'Petugas Sarpras (DEV)','petugas@sarpras.com',NULL,'$2y$10$x8J0blqBiZU.MEytbvZ3QOQnr1iycrYBrckX6mlO.lLivm.OnOriq','jluCt3S9zuNI7pSOfi0SxLqsCOecuTpVF5v4M9Nsj8FLuwjYstiPuv9W6AIk','2026-10-08 01:11:32','2026-10-08 02:54:00');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
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
  KEY `idx_withdrawal_items_wid` (`withdrawal_id`),
  CONSTRAINT `withdrawal_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `withdrawal_items_withdrawal_id_foreign` FOREIGN KEY (`withdrawal_id`) REFERENCES `withdrawals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `withdrawal_items`
--

LOCK TABLES `withdrawal_items` WRITE;
/*!40000 ALTER TABLE `withdrawal_items` DISABLE KEYS */;
INSERT INTO `withdrawal_items` VALUES (38,15,64,1,15000.00,15000.00,'2026-10-08 01:22:10','2026-10-08 01:22:10'),(39,15,65,1,35000.00,35000.00,'2026-10-08 01:22:10','2026-10-08 01:22:10'),(40,16,71,2,20000.00,40000.00,'2026-10-08 01:55:38','2026-10-08 01:55:38'),(41,17,72,2,9500000.00,19000000.00,'2026-10-08 02:08:54','2026-10-08 02:08:54'),(42,18,73,1,1250000.00,1250000.00,'2026-10-08 02:15:14','2026-10-08 02:15:14'),(43,18,74,1,1500000.00,1500000.00,'2026-10-08 02:15:14','2026-10-08 02:15:14'),(44,18,75,1,1350000.00,1350000.00,'2026-10-08 02:15:14','2026-10-08 02:15:14'),(45,18,72,1,9500000.00,9500000.00,'2026-10-08 02:15:14','2026-10-08 02:15:14'),(46,19,75,2,1350000.00,2700000.00,'2026-10-08 02:32:46','2026-10-08 02:32:46'),(47,19,72,1,9500000.00,9500000.00,'2026-10-08 02:32:46','2026-10-08 02:32:46'),(48,20,72,1,9500000.00,9500000.00,'2026-10-09 03:59:23','2026-10-09 03:59:23');
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
  KEY `idx_withdrawals_date_rusun` (`taken_at`,`rusun_id`),
  CONSTRAINT `withdrawals_rusun_id_foreign` FOREIGN KEY (`rusun_id`) REFERENCES `rusun` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `withdrawals`
--

LOCK TABLES `withdrawals` WRITE;
/*!40000 ALTER TABLE `withdrawals` DISABLE KEYS */;
INSERT INTO `withdrawals` VALUES (15,'Arya putrana kohan',27,'2026-10-08 08:21:00',NULL,'2026-10-08 01:22:10','2026-10-08 01:22:10',2,50000.00),(16,'putrana',28,'2026-10-08 08:55:00',NULL,'2026-10-08 01:55:38','2026-10-08 01:55:38',2,40000.00),(17,'aldo',29,'2026-10-08 09:08:00',NULL,'2026-10-08 02:08:54','2026-10-08 02:08:54',2,19000000.00),(18,'Arya putrana kohan',31,'2026-10-08 09:14:00','Satu paket untuk 1 karyawan kantor','2026-10-08 02:15:14','2026-10-08 02:15:14',4,13600000.00),(19,'Arya putrana kohan',32,'2026-10-08 09:32:00',NULL,'2026-10-08 02:32:46','2026-10-08 02:32:46',3,12200000.00),(20,'satria',27,'2026-10-09 10:59:00',NULL,'2026-10-09 03:59:23','2026-10-09 03:59:23',1,9500000.00);
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

-- Dump completed on 2026-11-26 13:21:48
