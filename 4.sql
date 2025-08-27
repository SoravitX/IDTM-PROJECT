-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               10.4.32-MariaDB - mariadb.org binary distribution
-- Server OS:                    Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table psu_blue_cafe.attendance
CREATE TABLE IF NOT EXISTS `attendance` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `date_in` date NOT NULL,
  `time_in` time NOT NULL,
  `date_out` date NOT NULL,
  `time_out` time NOT NULL,
  PRIMARY KEY (`attendance_id`),
  KEY `fk_attendance_user` (`user_id`),
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table psu_blue_cafe.attendance: ~0 rows (approximately)

-- Dumping structure for table psu_blue_cafe.categories
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table psu_blue_cafe.categories: ~5 rows (approximately)
INSERT IGNORE INTO `categories` (`category_id`, `category_name`) VALUES
	(1, 'MATCHA'),
	(2, 'TEA & COFFEE'),
	(3, 'ITALIAN SODA'),
	(4, 'NON COFFEE'),
	(5, 'Other');

-- Dumping structure for table psu_blue_cafe.menu
CREATE TABLE IF NOT EXISTS `menu` (
  `menu_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`menu_id`),
  KEY `fk_menu_category` (`category_id`),
  CONSTRAINT `fk_menu_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table psu_blue_cafe.menu: ~2 rows (approximately)
INSERT IGNORE INTO `menu` (`menu_id`, `category_id`, `name`, `price`, `description`, `image`) VALUES
	(7, 2, 'ชาไทย', 40.00, NULL, '1755537032_3d7ed429ef.jpg'),
	(8, 3, 'โซดาพาณิชย์', 40.00, NULL, '1755537089_9564a8f184.png');

-- Dumping structure for table psu_blue_cafe.orders
CREATE TABLE IF NOT EXISTS `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `order_time` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','ready','canceled') NOT NULL DEFAULT 'pending',
  `total_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`order_id`),
  KEY `fk_orders_user` (`user_id`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table psu_blue_cafe.orders: ~3 rows (approximately)
INSERT IGNORE INTO `orders` (`order_id`, `user_id`, `order_time`, `status`, `total_price`) VALUES
	(1, 4, '2025-08-19 00:45:00', 'pending', 80.00),
	(2, 4, '2025-08-19 00:48:53', 'pending', 40.00),
	(3, 4, '2025-08-19 01:27:52', 'pending', 240.00);

-- Dumping structure for table psu_blue_cafe.order_details
CREATE TABLE IF NOT EXISTS `order_details` (
  `order_detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `promo_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `total_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`order_detail_id`),
  KEY `fk_details_order` (`order_id`),
  KEY `fk_details_menu` (`menu_id`),
  KEY `fk_details_promo` (`promo_id`),
  CONSTRAINT `fk_details_menu` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`menu_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_details_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_details_promo` FOREIGN KEY (`promo_id`) REFERENCES `promotions` (`promo_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table psu_blue_cafe.order_details: ~1 rows (approximately)
INSERT IGNORE INTO `order_details` (`order_detail_id`, `order_id`, `menu_id`, `promo_id`, `quantity`, `note`, `total_price`) VALUES
	(3, 3, 8, NULL, 6, NULL, 240.00);

-- Dumping structure for table psu_blue_cafe.promotions
CREATE TABLE IF NOT EXISTS `promotions` (
  `promo_id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_id` int(11) NOT NULL,
  `promo_name` varchar(100) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `discount` int(11) NOT NULL,
  PRIMARY KEY (`promo_id`),
  KEY `fk_promotions_menu` (`menu_id`),
  CONSTRAINT `fk_promotions_menu` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`menu_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table psu_blue_cafe.promotions: ~0 rows (approximately)

-- Dumping structure for table psu_blue_cafe.users
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `student_ID` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table psu_blue_cafe.users: ~3 rows (approximately)
INSERT IGNORE INTO `users` (`user_id`, `username`, `password`, `student_ID`, `name`) VALUES
	(1, 'user1', 'e10adc3949ba59abbe56e057f20f883e', 65010001, 'สมชาย ใจดี'),
	(2, 'a', '1234', 0, ''),
	(4, 'b', 'c4ca4238a0b923820dcc509a6f75849b', 65010002, 'ทดสอบ ระบบ');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
