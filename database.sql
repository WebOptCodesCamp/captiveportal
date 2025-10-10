-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               10.4.28-MariaDB - mariadb.org binary distribution
-- Server OS:                    Win64
-- HeidiSQL Version:             12.5.0.6677
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for captive_portal
CREATE DATABASE IF NOT EXISTS `captive_portal`;
USE `captive_portal`;

-- Dumping structure for table captive_portal.admins
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table captive_portal.admins: ~1 rows (approximately)
INSERT INTO `admins` (`id`, `username`, `password`) VALUES
	(1, 'webopt', 'hacker_webopt'),
	(1, 'admin@ebazzuwifi', 'ebazzuwifi@admin2026');

-- Dumping structure for table captive_portal.bundles
CREATE TABLE IF NOT EXISTS `bundles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `data_limit_mb` int(11) NOT NULL,
  `price_kes` decimal(10,2) NOT NULL,
  `duration_minutes` int(11) NOT NULL,
  `is_unlimited` tinyint(1) NOT NULL DEFAULT 0,
  `download_limit_kbps` int(11) NOT NULL DEFAULT 2048,
  `upload_limit_kbps` int(11) NOT NULL DEFAULT 1024,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table captive_portal.bundles: ~3 rows (approximately)
INSERT INTO `bundles` (`id`, `name`, `data_limit_mb`, `price_kes`, `duration_minutes`, `is_unlimited`, `download_limit_kbps`, `upload_limit_kbps`) VALUES
	(1, 'Daily 200MB', 200, 3.00, 1440, 0, 2048, 1024),
	(2, 'Daily 1GB', 1024, 10.00, 1440, 0, 10240, 5120),
	(3, 'Weekly 5GB', 5120, 20.00, 10080, 0, 5120, 2048);

-- Dumping structure for table captive_portal.devices
CREATE TABLE IF NOT EXISTS `devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mac_address` varchar(17) NOT NULL,
  `bundle_id` int(11) DEFAULT NULL,
  `data_used_mb` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bundle_start_time` timestamp NULL DEFAULT NULL,
  `bundle_expiry_time` timestamp NULL DEFAULT NULL,
  `last_seen` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `mac_address` (`mac_address`),
  KEY `bundle_id` (`bundle_id`),
  CONSTRAINT `devices_ibfk_2` FOREIGN KEY (`bundle_id`) REFERENCES `bundles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping structure for table captive_portal.transactions
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bundle_id` int(11) NOT NULL,
  `mpesa_receipt_number` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bundle_id` (`bundle_id`),
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`bundle_id`) REFERENCES `bundles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;