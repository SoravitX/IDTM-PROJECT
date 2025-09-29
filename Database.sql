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


-- Dumping database structure for psu_blue_cafe
CREATE DATABASE IF NOT EXISTS `psu_blue_cafe` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `psu_blue_cafe`;

-- Dumping structure for table psu_blue_cafe.attendance
CREATE TABLE IF NOT EXISTS `attendance` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `date_in` date NOT NULL,
  `time_in` time NOT NULL,
  `date_out` date NOT NULL,
  `time_out` time NOT NULL,
  `hour_type` enum('normal','fund') NOT NULL DEFAULT 'normal',
  PRIMARY KEY (`attendance_id`),
  KEY `fk_attendance_user` (`user_id`),
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table psu_blue_cafe.attendance: ~14 rows (approximately)
INSERT INTO `attendance` (`attendance_id`, `user_id`, `date_in`, `time_in`, `date_out`, `time_out`, `hour_type`) VALUES
	(1, 3, '2025-08-27', '21:23:37', '2025-08-27', '21:23:48', 'normal'),
	(2, 3, '2025-08-27', '21:24:42', '2025-08-27', '21:24:45', 'normal'),
	(3, 3, '2025-08-27', '23:11:49', '2025-08-28', '12:10:26', 'normal'),
	(4, 3, '2025-09-01', '16:55:44', '2025-09-01', '16:55:45', 'normal'),
	(10, 3, '2025-09-09', '13:37:59', '2025-09-09', '13:38:02', 'normal'),
	(11, 3, '2025-09-09', '13:38:04', '2025-09-09', '13:47:35', 'normal'),
	(12, 3, '2025-09-09', '13:47:37', '2025-09-09', '13:47:38', 'fund'),
	(13, 1, '2025-09-09', '13:49:04', '2025-09-09', '13:49:05', 'normal'),
	(14, 1, '2025-09-09', '13:49:06', '2025-09-09', '13:49:07', 'normal'),
	(15, 1, '2025-09-09', '13:58:33', '2025-09-09', '14:18:04', 'normal'),
	(16, 1, '2025-09-09', '14:18:07', '2025-09-09', '23:59:00', 'normal'),
	(17, 3, '2025-09-13', '13:11:27', '2025-09-13', '17:00:00', 'normal'),
	(18, 3, '2025-09-17', '13:25:51', '2025-09-17', '13:25:53', 'fund'),
	(19, 3, '2025-09-17', '13:25:54', '2025-09-17', '13:25:55', 'fund');

-- Dumping structure for table psu_blue_cafe.categories
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table psu_blue_cafe.categories: ~6 rows (approximately)
INSERT INTO `categories` (`category_id`, `category_name`, `position`, `is_active`) VALUES
	(1, 'MATCHA', 4, 1),
	(2, 'TEA & COFFEE', 2, 1),
	(3, 'ITALIAN SODA', 6, 1),
	(4, 'NON COFFEE', 1, 1),
	(5, 'Other', 5, 1),
	(6, 'Beverage', 3, 1),
	(8, '111', 0, 1);

-- Dumping structure for table psu_blue_cafe.menu
CREATE TABLE IF NOT EXISTS `menu` (
  `menu_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`menu_id`),
  KEY `fk_menu_category` (`category_id`),
  CONSTRAINT `fk_menu_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table psu_blue_cafe.menu: ~19 rows (approximately)
INSERT INTO `menu` (`menu_id`, `category_id`, `name`, `price`, `description`, `image`, `is_active`) VALUES
	(7, 1, 'ชาไทยๆ', 50.00, NULL, '1755537032_3d7ed429ef.jpg', 0),
	(8, 3, 'โซดาพาณิชย์', 40.00, NULL, '1759062053_3a547bed3b.png', 1),
	(9, 1, 'มัทฉะลาเต้1', 40.00, NULL, '1756375083_7f688ce59c.jpg', 1),
	(10, 1, 'มัทฉะมะพร้าว', 40.00, NULL, '1756375212_b66ec3274f.webp', 0),
	(11, 2, 'ชาเขียวนม', 40.00, NULL, '1756375242_55470ce83d.webp', 1),
	(12, 2, 'ชาดำ', 40.00, NULL, '1756375310_d7f1afc2dd.jpg', 1),
	(13, 2, 'ชามะนาว', 40.00, NULL, '1756375400_68ae94caf9.jpg', 1),
	(14, 2, 'Blue Americano', 40.00, NULL, '1756375424_cea13ebdd0.jpg', 1),
	(15, 3, 'โซดาถาปัตย์', 40.00, NULL, '1756375482_bc30f29139.png', 1),
	(16, 3, 'บ๊วยโซดา', 40.00, NULL, '1756375550_2e6dff320e.jpeg', 1),
	(17, 4, 'โอวัลตินเย็น', 40.00, NULL, '1756375572_0be3f31ec9.jpg', 1),
	(18, 4, 'โกโก้เย็น', 40.00, NULL, '1756375625_841d0a2bb6.jpg', 1),
	(19, 4, 'นมชมพู', 40.00, NULL, '1756375656_0e4115762a.webp', 1),
	(20, 4, 'มะพร้าวโยเกิร์ต', 40.00, NULL, '1756375737_60eba06c92.jpg', 1),
	(21, 4, 'นมสตรอเบอร์รี่', 40.00, NULL, '1756375829_f25d6dd63e.png', 1),
	(22, 5, 'เสาวรสโซดา', 40.00, NULL, '1756375860_d9521e3685.webp', 1),
	(23, 5, 'ชาเขียวลิ้นจี่', 40.00, NULL, '1756375918_63045893fa.png', 1),
	(24, 6, 'Matcha Coconut (มัทฉะมะพร้าว) (16 oz. Glass)', 40.00, NULL, NULL, 0),
	(25, 3, 'CM Soda', 25.00, NULL, NULL, 0),
	(26, 1, 'PUBG', 40.00, NULL, '1759154240_f0ad90657b.png', 1);

-- Dumping structure for table psu_blue_cafe.menu_toppings
CREATE TABLE IF NOT EXISTS `menu_toppings` (
  `menu_id` int(11) NOT NULL,
  `topping_id` int(11) NOT NULL,
  `extra_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`menu_id`,`topping_id`),
  KEY `topping_id` (`topping_id`),
  CONSTRAINT `menu_toppings_ibfk_1` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`menu_id`),
  CONSTRAINT `menu_toppings_ibfk_2` FOREIGN KEY (`topping_id`) REFERENCES `toppings` (`topping_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table psu_blue_cafe.menu_toppings: ~0 rows (approximately)

-- Dumping structure for table psu_blue_cafe.orders
CREATE TABLE IF NOT EXISTS `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `order_time` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('awaiting_payment','pending','ready','canceled') NOT NULL DEFAULT 'awaiting_payment',
  `payment_method` enum('transfer','cash') NOT NULL DEFAULT 'transfer',
  `total_price` decimal(10,2) NOT NULL,
  `updated_at` datetime(6) NOT NULL,
  `promo_id` int(11) DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `final_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`order_id`),
  KEY `fk_orders_user` (`user_id`),
  KEY `idx_orders_updated_at` (`updated_at`),
  KEY `idx_orders_user_updated` (`user_id`,`updated_at`,`status`),
  KEY `idx_orders_feed` (`user_id`,`updated_at`,`order_id`),
  KEY `fk_orders_promo` (`promo_id`),
  KEY `idx_orders_status_updated` (`status`,`updated_at`),
  KEY `idx_orders_user` (`user_id`),
  KEY `idx_orders_order` (`order_id`),
  KEY `idx_orders_updated` (`updated_at`),
  CONSTRAINT `fk_orders_promo` FOREIGN KEY (`promo_id`) REFERENCES `promotions` (`promo_id`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table psu_blue_cafe.orders: ~77 rows (approximately)
INSERT INTO `orders` (`order_id`, `user_id`, `order_time`, `status`, `payment_method`, `total_price`, `updated_at`, `promo_id`, `discount_amount`, `final_total`) VALUES
	(1, 3, '2025-09-27 01:24:08', 'ready', 'transfer', 40.00, '2025-09-27 01:24:24.000000', NULL, 0.00, 0.00),
	(2, 3, '2025-09-27 01:24:16', 'ready', 'transfer', 40.00, '2025-09-27 01:24:21.000000', NULL, 0.00, 0.00),
	(3, 3, '2025-09-27 01:24:27', 'ready', 'transfer', 40.00, '2025-09-27 01:24:49.117712', NULL, 0.00, 0.00),
	(4, 3, '2025-09-27 01:25:13', 'ready', 'transfer', 40.00, '2025-09-27 01:26:09.000000', NULL, 0.00, 0.00),
	(5, 3, '2025-09-27 01:25:37', 'ready', 'transfer', 40.00, '2025-09-27 11:55:25.000000', NULL, 0.00, 0.00),
	(6, 3, '2025-09-27 01:25:58', 'ready', 'transfer', 40.00, '2025-09-27 01:26:18.000000', NULL, 0.00, 0.00),
	(7, 3, '2025-09-27 11:54:49', 'ready', 'transfer', 40.00, '2025-09-27 11:55:24.000000', NULL, 0.00, 0.00),
	(8, 3, '2025-09-27 11:55:28', 'ready', 'transfer', 40.00, '2025-09-27 11:55:49.000000', NULL, 0.00, 0.00),
	(9, 3, '2025-09-27 11:56:52', 'ready', 'transfer', 40.00, '2025-09-27 11:57:08.000000', NULL, 0.00, 0.00),
	(10, 3, '2025-09-27 12:04:37', 'ready', 'transfer', 40.00, '2025-09-27 12:05:26.000000', NULL, 0.00, 0.00),
	(11, 3, '2025-09-27 12:05:29', 'ready', 'transfer', 80.00, '2025-09-27 12:14:03.000000', NULL, 0.00, 0.00),
	(12, 3, '2025-09-27 12:05:53', 'ready', 'transfer', 80.00, '2025-09-27 12:14:02.000000', NULL, 0.00, 0.00),
	(13, 3, '2025-09-27 12:14:07', 'ready', 'transfer', 40.00, '2025-09-27 12:14:37.000000', NULL, 0.00, 0.00),
	(14, 3, '2025-09-27 13:24:19', 'ready', 'transfer', 40.00, '2025-09-27 13:24:32.000000', NULL, 0.00, 0.00),
	(15, 1, '2025-09-27 13:40:47', 'ready', 'transfer', 40.00, '2025-09-27 13:41:49.000000', NULL, 0.00, 0.00),
	(16, 3, '2025-09-27 13:41:54', 'ready', 'transfer', 40.00, '2025-09-27 13:42:25.000000', NULL, 0.00, 0.00),
	(17, 3, '2025-09-27 13:44:00', 'ready', 'transfer', 40.00, '2025-09-27 13:46:56.000000', NULL, 0.00, 0.00),
	(18, 3, '2025-09-27 13:44:32', 'ready', 'transfer', 38.00, '2025-09-27 16:49:35.000000', NULL, 0.00, 0.00),
	(19, 3, '2025-09-27 13:56:03', 'ready', 'transfer', 741.00, '2025-09-27 14:00:09.000000', NULL, 0.00, 0.00),
	(20, 1, '2025-09-27 14:07:21', 'ready', 'transfer', 40.00, '2025-09-27 16:49:34.000000', NULL, 0.00, 0.00),
	(21, 3, '2025-09-27 16:48:51', 'ready', 'transfer', 40.00, '2025-09-27 16:49:34.000000', NULL, 0.00, 0.00),
	(22, 3, '2025-09-27 17:25:05', 'ready', 'transfer', 40.00, '2025-09-27 20:20:07.000000', NULL, 0.00, 0.00),
	(23, 3, '2025-09-27 17:25:17', 'ready', 'transfer', 40.00, '2025-09-27 20:20:07.000000', NULL, 0.00, 0.00),
	(24, 3, '2025-09-27 17:30:55', 'ready', 'transfer', 40.00, '2025-09-27 20:20:06.000000', NULL, 0.00, 0.00),
	(25, 3, '2025-09-27 17:30:56', 'ready', 'transfer', 40.00, '2025-09-27 20:20:06.000000', NULL, 0.00, 0.00),
	(26, 3, '2025-09-27 17:34:16', 'ready', 'transfer', 40.00, '2025-09-27 20:20:06.000000', NULL, 0.00, 0.00),
	(27, 3, '2025-09-27 17:34:17', 'ready', 'transfer', 40.00, '2025-09-27 20:20:05.000000', NULL, 0.00, 0.00),
	(28, 3, '2025-09-27 17:35:11', 'ready', 'transfer', 40.00, '2025-09-27 20:20:05.000000', NULL, 0.00, 0.00),
	(29, 3, '2025-09-27 17:35:12', 'ready', 'transfer', 40.00, '2025-09-27 20:13:21.000000', NULL, 0.00, 0.00),
	(30, 3, '2025-09-27 17:36:11', 'ready', 'transfer', 40.00, '2025-09-27 20:13:20.000000', NULL, 0.00, 0.00),
	(31, 3, '2025-09-27 17:36:12', 'ready', 'transfer', 40.00, '2025-09-27 20:13:20.000000', NULL, 0.00, 0.00),
	(32, 3, '2025-09-27 17:41:09', 'ready', 'transfer', 40.00, '2025-09-27 20:13:20.000000', NULL, 0.00, 0.00),
	(33, 3, '2025-09-27 17:41:10', 'ready', 'transfer', 40.00, '2025-09-27 20:13:19.000000', NULL, 0.00, 0.00),
	(34, 3, '2025-09-27 17:41:18', 'ready', 'transfer', 40.00, '2025-09-27 20:13:19.000000', NULL, 0.00, 0.00),
	(35, 3, '2025-09-27 17:45:06', 'ready', 'transfer', 40.00, '2025-09-27 20:13:19.000000', NULL, 0.00, 0.00),
	(36, 3, '2025-09-27 17:45:07', 'ready', 'transfer', 40.00, '2025-09-27 19:35:24.000000', NULL, 0.00, 0.00),
	(37, 1, '2025-09-27 20:20:12', 'ready', 'transfer', 40.00, '2025-09-27 20:23:37.000000', NULL, 0.00, 0.00),
	(38, 1, '2025-09-27 20:24:38', 'ready', 'transfer', 40.00, '2025-09-27 20:25:30.000000', NULL, 0.00, 0.00),
	(39, 3, '2025-09-27 22:03:12', 'ready', 'transfer', 40.00, '2025-09-27 22:04:21.000000', NULL, 0.00, 0.00),
	(40, 3, '2025-09-27 22:04:27', 'ready', 'transfer', 40.00, '2025-09-27 22:04:47.000000', NULL, 0.00, 0.00),
	(41, 3, '2025-09-27 22:08:41', 'ready', 'transfer', 40.00, '2025-09-27 22:09:22.000000', NULL, 0.00, 0.00),
	(42, 3, '2025-09-27 22:08:50', 'ready', 'transfer', 40.00, '2025-09-27 22:09:20.000000', NULL, 0.00, 0.00),
	(43, 3, '2025-09-27 22:30:15', 'canceled', 'transfer', 80.00, '2025-09-27 22:31:11.000000', NULL, 0.00, 0.00),
	(44, 3, '2025-09-27 22:31:18', 'ready', 'transfer', 40.00, '2025-09-27 22:32:06.000000', NULL, 0.00, 0.00),
	(45, 3, '2025-09-28 15:22:31', 'ready', 'transfer', 40.00, '2025-09-28 16:17:59.000000', NULL, 0.00, 0.00),
	(46, 3, '2025-09-28 15:27:43', 'ready', 'transfer', 40.00, '2025-09-28 16:17:59.000000', NULL, 0.00, 0.00),
	(47, 3, '2025-09-28 15:28:27', 'ready', 'transfer', 40.00, '2025-09-28 16:17:58.000000', NULL, 0.00, 0.00),
	(48, 3, '2025-09-28 15:30:44', 'ready', 'transfer', 40.00, '2025-09-28 16:17:58.000000', NULL, 0.00, 0.00),
	(49, 3, '2025-09-28 15:30:46', 'ready', 'transfer', 40.00, '2025-09-28 16:17:58.000000', NULL, 0.00, 0.00),
	(50, 3, '2025-09-28 15:30:58', 'ready', 'transfer', 40.00, '2025-09-28 16:17:56.000000', NULL, 0.00, 0.00),
	(51, 3, '2025-09-28 15:31:07', 'ready', 'transfer', 40.00, '2025-09-28 16:17:56.000000', NULL, 0.00, 0.00),
	(52, 3, '2025-09-28 15:31:31', 'ready', 'transfer', 40.00, '2025-09-28 16:17:56.000000', NULL, 0.00, 0.00),
	(53, 3, '2025-09-28 15:31:40', 'ready', 'transfer', 40.00, '2025-09-28 16:17:55.000000', NULL, 0.00, 0.00),
	(54, 3, '2025-09-28 15:31:48', 'ready', 'transfer', 40.00, '2025-09-28 16:17:55.000000', NULL, 0.00, 0.00),
	(55, 3, '2025-09-28 15:32:08', 'ready', 'transfer', 40.00, '2025-09-28 16:17:55.000000', NULL, 0.00, 0.00),
	(56, 3, '2025-09-28 15:32:24', 'ready', 'transfer', 40.00, '2025-09-28 16:17:54.000000', NULL, 0.00, 0.00),
	(57, 3, '2025-09-28 15:32:55', 'ready', 'transfer', 40.00, '2025-09-28 16:17:54.000000', NULL, 0.00, 0.00),
	(58, 3, '2025-09-28 15:33:57', 'ready', 'transfer', 40.00, '2025-09-28 16:17:54.000000', NULL, 0.00, 0.00),
	(59, 3, '2025-09-28 15:49:15', 'ready', 'transfer', 40.00, '2025-09-28 16:17:53.000000', NULL, 0.00, 0.00),
	(60, 3, '2025-09-28 15:55:32', 'ready', 'transfer', 40.00, '2025-09-28 16:17:53.000000', NULL, 0.00, 0.00),
	(61, 3, '2025-09-28 15:55:34', 'ready', 'transfer', 40.00, '2025-09-28 16:17:53.000000', NULL, 0.00, 0.00),
	(62, 3, '2025-09-28 15:56:29', 'ready', 'transfer', 40.00, '2025-09-28 16:17:52.000000', NULL, 0.00, 0.00),
	(63, 3, '2025-09-28 15:58:50', 'ready', 'transfer', 40.00, '2025-09-28 16:17:52.000000', NULL, 0.00, 0.00),
	(64, 3, '2025-09-28 15:58:52', 'ready', 'transfer', 40.00, '2025-09-28 16:17:51.000000', NULL, 0.00, 0.00),
	(65, 3, '2025-09-28 16:13:09', 'ready', 'transfer', 40.00, '2025-09-28 16:17:51.000000', NULL, 0.00, 0.00),
	(66, 3, '2025-09-28 16:31:05', 'canceled', 'transfer', 40.00, '2025-09-28 16:46:08.000000', NULL, 0.00, 0.00),
	(67, 3, '2025-09-28 16:31:42', 'ready', 'transfer', 40.00, '2025-09-28 21:34:05.000000', NULL, 0.00, 0.00),
	(68, 3, '2025-09-28 16:32:30', 'canceled', 'transfer', 40.00, '2025-09-28 16:34:17.000000', NULL, 0.00, 0.00),
	(69, 3, '2025-09-28 16:33:39', 'ready', 'transfer', 40.00, '2025-09-28 21:34:04.000000', NULL, 0.00, 0.00),
	(70, 3, '2025-09-28 21:33:41', 'ready', 'transfer', 40.00, '2025-09-28 21:34:04.000000', NULL, 0.00, 0.00),
	(71, 3, '2025-09-28 22:09:06', 'canceled', 'transfer', 40.00, '2025-09-28 22:13:56.000000', NULL, 0.00, 0.00),
	(72, 3, '2025-09-28 22:09:12', 'canceled', 'transfer', 40.00, '2025-09-28 22:13:56.000000', NULL, 0.00, 0.00),
	(73, 3, '2025-09-28 22:10:43', 'canceled', 'transfer', 40.00, '2025-09-28 22:13:56.000000', NULL, 0.00, 0.00),
	(74, 3, '2025-09-28 22:13:45', 'canceled', 'transfer', 40.00, '2025-09-28 22:13:55.000000', NULL, 0.00, 0.00),
	(75, 3, '2025-09-28 22:13:58', 'canceled', 'transfer', 40.00, '2025-09-28 22:14:49.000000', NULL, 0.00, 0.00),
	(76, 1, '2025-09-28 23:20:17', 'ready', 'transfer', 55.00, '2025-09-28 23:20:22.000000', NULL, 0.00, 0.00),
	(77, 3, '2025-09-29 14:42:48', 'canceled', 'transfer', 36.00, '2025-09-29 20:49:44.000000', NULL, 0.00, 0.00),
	(78, 3, '2025-09-29 20:49:56', 'ready', 'transfer', 46.00, '2025-09-29 20:50:26.000000', NULL, 0.00, 0.00);

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
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table psu_blue_cafe.order_details: ~98 rows (approximately)
INSERT INTO `order_details` (`order_detail_id`, `order_id`, `menu_id`, `promo_id`, `quantity`, `note`, `total_price`) VALUES
	(1, 1, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(2, 2, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(3, 3, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(4, 4, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(5, 5, 10, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(6, 6, 10, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(7, 7, 10, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(8, 8, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(9, 9, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(10, 10, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(11, 11, 10, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(12, 11, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(13, 12, 10, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(14, 12, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(15, 13, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(16, 14, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(17, 15, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(18, 16, 10, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(19, 17, 10, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(20, 18, 9, 7, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 38.00),
	(21, 19, 7, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(22, 19, 8, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(23, 19, 9, 7, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 38.00),
	(24, 19, 10, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(25, 19, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(26, 19, 12, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(27, 19, 13, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(28, 19, 14, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(29, 19, 15, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(30, 19, 16, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(31, 19, 17, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(32, 19, 18, 7, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 38.00),
	(33, 19, 19, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(34, 19, 20, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(35, 19, 21, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(36, 19, 22, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(37, 19, 23, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(38, 19, 24, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(39, 19, 25, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 25.00),
	(40, 20, 8, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(41, 21, 10, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(42, 22, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(43, 23, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(44, 24, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(45, 25, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(46, 26, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(47, 27, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(48, 28, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(49, 29, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(50, 30, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(51, 31, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(52, 32, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(53, 33, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(54, 34, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(55, 35, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(56, 36, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(57, 37, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(58, 38, 8, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(59, 39, 12, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(60, 40, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(61, 41, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(62, 42, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(63, 43, 12, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(64, 43, 17, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(65, 44, 12, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(66, 45, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(67, 46, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(68, 47, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(69, 48, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(70, 49, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(71, 50, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(72, 51, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(73, 52, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(74, 53, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(75, 54, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(76, 55, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(77, 56, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(78, 57, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(79, 58, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(80, 59, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(81, 60, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(82, 61, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(83, 62, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(84, 63, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(85, 64, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(86, 65, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(87, 66, 9, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(88, 67, 12, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(89, 68, 12, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(90, 69, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(91, 70, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(92, 71, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(93, 72, 12, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(94, 73, 12, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(95, 74, 12, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(96, 75, 12, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 40.00),
	(97, 76, 11, NULL, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ | ท็อปปิง: วิปครีม, เจลลี่', 55.00),
	(98, 77, 9, 8, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ', 36.00),
	(99, 78, 9, 8, 1, 'ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ | ท็อปปิง: วิปครีม', 46.00);

-- Dumping structure for table psu_blue_cafe.payment_slips
CREATE TABLE IF NOT EXISTS `payment_slips` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime` varchar(64) NOT NULL,
  `size_bytes` int(11) NOT NULL,
  `uploaded_at` datetime(6) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `idx_slips_order_uploaded` (`order_id`,`uploaded_at`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table psu_blue_cafe.payment_slips: ~69 rows (approximately)
INSERT INTO `payment_slips` (`id`, `order_id`, `user_id`, `file_path`, `mime`, `size_bytes`, `uploaded_at`, `note`) VALUES
	(1, 326, 3, 'uploads/slips/slip_326_20250906_110414_a70b62.jpg', 'image/jpeg', 26584, '2025-09-06 16:04:14.000000', ''),
	(2, 328, 3, 'uploads/slips/slip_328_20250906_115041_2950ec.jpg', 'image/jpeg', 26584, '2025-09-06 16:50:41.000000', ''),
	(3, 333, 3, 'uploads/slips/slip_333_20250906_134020_26e84e.jpg', 'image/jpeg', 26584, '2025-09-06 18:40:20.000000', ''),
	(4, 337, 3, 'uploads/slips/slip_337_20250906_134100_5adac2.jpg', 'image/jpeg', 26584, '2025-09-06 18:41:00.000000', ''),
	(5, 342, 3, 'uploads/slips/slip_342_20250906_135617_d8ea50.jpg', 'image/jpeg', 26584, '2025-09-06 18:56:17.000000', ''),
	(6, 343, 3, 'uploads/slips/slip_343_20250906_135825_40aaae.jpg', 'image/jpeg', 26584, '2025-09-06 18:58:25.000000', ''),
	(7, 344, 3, 'uploads/slips/slip_344_20250906_141548_e7de82.jpg', 'image/jpeg', 26584, '2025-09-06 19:15:48.000000', ''),
	(8, 345, 3, 'uploads/slips/slip_345_20250906_141614_49b25a.jpg', 'image/jpeg', 26584, '2025-09-06 19:16:14.000000', ''),
	(9, 346, 3, 'uploads/slips/slip_346_20250906_142859_bb26d4.jpg', 'image/jpeg', 26584, '2025-09-06 19:28:59.000000', ''),
	(10, 346, 3, 'uploads/slips/slip_346_20250906_142901_24374e.jpg', 'image/jpeg', 26584, '2025-09-06 19:29:01.000000', ''),
	(11, 346, 3, 'uploads/slips/slip_346_20250906_142901_23eb93.jpg', 'image/jpeg', 26584, '2025-09-06 19:29:01.000000', ''),
	(12, 346, 3, 'uploads/slips/slip_346_20250906_142910_c182ae.jpg', 'image/jpeg', 26584, '2025-09-06 19:29:10.000000', ''),
	(13, 346, 3, 'uploads/slips/slip_346_20250906_142917_509460.jpg', 'image/jpeg', 26584, '2025-09-06 19:29:17.000000', ''),
	(14, 347, 3, 'uploads/slips/slip_347_20250906_145611_cf5e09.jpg', 'image/jpeg', 26584, '2025-09-06 19:56:11.000000', ''),
	(15, 349, 3, 'uploads/slips/slip_349_20250906_145833_ffb391.jpg', 'image/jpeg', 26584, '2025-09-06 19:58:33.000000', ''),
	(16, 349, 3, 'uploads/slips/slip_349_20250906_145834_dd7aa8.jpg', 'image/jpeg', 26584, '2025-09-06 19:58:34.000000', ''),
	(17, 349, 3, 'uploads/slips/slip_349_20250906_145834_93e794.jpg', 'image/jpeg', 26584, '2025-09-06 19:58:34.000000', ''),
	(18, 349, 3, 'uploads/slips/slip_349_20250906_145834_1a5b3d.jpg', 'image/jpeg', 26584, '2025-09-06 19:58:34.000000', ''),
	(19, 349, 3, 'uploads/slips/slip_349_20250906_145834_53f07d.jpg', 'image/jpeg', 26584, '2025-09-06 19:58:34.000000', ''),
	(20, 349, 3, 'uploads/slips/slip_349_20250906_145835_208c65.jpg', 'image/jpeg', 26584, '2025-09-06 19:58:35.000000', ''),
	(21, 349, 3, 'uploads/slips/slip_349_20250906_145835_7ddf8a.jpg', 'image/jpeg', 26584, '2025-09-06 19:58:35.000000', ''),
	(22, 349, 3, 'uploads/slips/slip_349_20250906_145835_7a0a83.jpg', 'image/jpeg', 26584, '2025-09-06 19:58:35.000000', ''),
	(23, 349, 3, 'uploads/slips/slip_349_20250906_145835_f2cd81.jpg', 'image/jpeg', 26584, '2025-09-06 19:58:35.000000', ''),
	(24, 349, 3, 'uploads/slips/slip_349_20250906_145835_587465.jpg', 'image/jpeg', 26584, '2025-09-06 19:58:35.000000', ''),
	(25, 349, 3, 'uploads/slips/slip_349_20250906_145836_be224b.jpg', 'image/jpeg', 26584, '2025-09-06 19:58:36.000000', ''),
	(26, 349, 3, 'uploads/slips/slip_349_20250906_145849_d90801.jpg', 'image/jpeg', 26584, '2025-09-06 19:58:49.000000', ''),
	(27, 349, 3, 'uploads/slips/slip_349_20250906_145850_e32336.jpg', 'image/jpeg', 26584, '2025-09-06 19:58:50.000000', ''),
	(28, 349, 3, 'uploads/slips/slip_349_20250906_145856_40d238.jpg', 'image/jpeg', 26584, '2025-09-06 19:58:56.000000', ''),
	(29, 349, 3, 'uploads/slips/slip_349_20250906_145942_1b6b46.jpg', 'image/jpeg', 26584, '2025-09-06 19:59:42.000000', ''),
	(30, 350, 3, 'uploads/slips/slip_350_20250906_150008_206fcd.jpg', 'image/jpeg', 26584, '2025-09-06 20:00:08.000000', ''),
	(31, 351, 3, 'uploads/slips/slip_351_20250906_150031_1ebed5.jpg', 'image/jpeg', 26584, '2025-09-06 20:00:31.000000', ''),
	(32, 352, 3, 'uploads/slips/slip_352_20250906_150051_5d6615.jpg', 'image/jpeg', 26584, '2025-09-06 20:00:51.000000', ''),
	(33, 353, 3, 'uploads/slips/slip_353_20250906_150140_c0bbd0.jpg', 'image/jpeg', 26584, '2025-09-06 20:01:40.000000', ''),
	(34, 354, 3, 'uploads/slips/slip_354_20250906_150205_d59e4f.jpg', 'image/jpeg', 26584, '2025-09-06 20:02:05.000000', ''),
	(35, 355, 3, 'uploads/slips/slip_355_20250906_150219_1eddea.jpg', 'image/jpeg', 26584, '2025-09-06 20:02:19.000000', ''),
	(36, 356, 3, 'uploads/slips/slip_356_20250906_150233_4be900.jpg', 'image/jpeg', 26584, '2025-09-06 20:02:33.000000', ''),
	(37, 357, 3, 'uploads/slips/slip_357_20250906_150310_89a6c3.jpg', 'image/jpeg', 26584, '2025-09-06 20:03:10.000000', ''),
	(38, 358, 3, 'uploads/slips/slip_358_20250906_150321_5e8f5d.jpg', 'image/jpeg', 26584, '2025-09-06 20:03:21.000000', ''),
	(39, 359, 3, 'uploads/slips/slip_359_20250906_150333_e94d0b.jpg', 'image/jpeg', 26584, '2025-09-06 20:03:33.000000', ''),
	(40, 360, 3, 'uploads/slips/slip_360_20250906_150346_bc32a6.jpg', 'image/jpeg', 26584, '2025-09-06 20:03:46.000000', ''),
	(41, 361, 3, 'uploads/slips/slip_361_20250906_150356_b55ca0.jpg', 'image/jpeg', 26584, '2025-09-06 20:03:56.000000', ''),
	(42, 363, 3, 'uploads/slips/slip_363_20250906_152112_0b917b.jpg', 'image/jpeg', 26584, '2025-09-06 20:21:12.000000', ''),
	(43, 364, 3, 'uploads/slips/slip_364_20250906_152126_2e8bf5.jpg', 'image/jpeg', 26584, '2025-09-06 20:21:26.000000', ''),
	(44, 365, 3, 'uploads/slips/slip_365_20250906_152806_876485.jpg', 'image/jpeg', 26584, '2025-09-06 20:28:06.000000', ''),
	(45, 366, 3, 'uploads/slips/slip_366_20250906_152829_c1ef9e.jpg', 'image/jpeg', 26584, '2025-09-06 20:28:29.000000', ''),
	(46, 367, 3, 'uploads/slips/slip_367_20250906_152951_1e2378.jpg', 'image/jpeg', 26584, '2025-09-06 20:29:51.000000', ''),
	(47, 368, 3, 'uploads/slips/slip_368_20250906_153121_7a65f3.jpg', 'image/jpeg', 26584, '2025-09-06 20:31:21.000000', ''),
	(48, 369, 3, 'uploads/slips/slip_369_20250906_154100_4b331c.jpg', 'image/jpeg', 26584, '2025-09-06 20:41:00.000000', ''),
	(49, 370, 3, 'uploads/slips/slip_370_20250906_154129_c3bb32.jpg', 'image/jpeg', 26584, '2025-09-06 20:41:29.000000', ''),
	(50, 371, 3, 'uploads/slips/slip_371_20250906_154147_2c7a56.jpg', 'image/jpeg', 26584, '2025-09-06 20:41:47.000000', ''),
	(51, 372, 3, 'uploads/slips/slip_372_20250906_155401_b69ab7.jpg', 'image/jpeg', 26584, '2025-09-06 20:54:01.000000', ''),
	(52, 373, 3, 'uploads/slips/slip_373_20250906_155420_f414a3.jpg', 'image/jpeg', 26584, '2025-09-06 20:54:20.000000', ''),
	(53, 374, 3, 'uploads/slips/slip_374_20250906_155437_8c3035.jpg', 'image/jpeg', 26584, '2025-09-06 20:54:37.000000', ''),
	(54, 375, 3, 'uploads/slips/slip_375_20250906_161813_907d8a.jpg', 'image/jpeg', 26584, '2025-09-06 21:18:13.000000', ''),
	(55, 376, 3, 'uploads/slips/slip_376_20250906_161825_779d11.jpg', 'image/jpeg', 26584, '2025-09-06 21:18:25.000000', ''),
	(56, 377, 3, 'uploads/slips/slip_377_20250906_161842_d8097b.jpg', 'image/jpeg', 26584, '2025-09-06 21:18:42.000000', ''),
	(57, 378, 3, 'uploads/slips/slip_378_20250906_161957_3725dc.jpg', 'image/jpeg', 26584, '2025-09-06 21:19:57.000000', ''),
	(58, 387, 3, 'uploads/slips/slip_387_20250906_170625_adc577.jpg', 'image/jpeg', 26584, '2025-09-06 22:06:25.000000', ''),
	(59, 389, 3, 'uploads/slips/slip_389_20250906_181504_aca35b.jpg', 'image/jpeg', 26584, '2025-09-06 23:15:04.000000', ''),
	(60, 403, 3, 'uploads/slips/slip_403_20250906_210453_9b439c.jpg', 'image/jpeg', 26584, '2025-09-07 02:04:53.000000', ''),
	(61, 404, 3, 'uploads/slips/slip_404_20250906_210505_5c71e9.jpg', 'image/jpeg', 26584, '2025-09-07 02:05:05.000000', ''),
	(62, 405, 3, 'uploads/slips/slip_405_20250906_210600_51513c.jpg', 'image/jpeg', 26584, '2025-09-07 02:06:00.000000', ''),
	(63, 406, 3, 'uploads/slips/slip_406_20250906_210621_730034.jpg', 'image/jpeg', 26584, '2025-09-07 02:06:21.000000', ''),
	(64, 411, 3, 'uploads/slips/slip_411_20250906_220232_f564f3.jpg', 'image/jpeg', 26584, '2025-09-07 03:02:32.000000', ''),
	(65, 413, 3, 'uploads/slips/slip_413_20250906_221544_c810b6.jpg', 'image/jpeg', 26584, '2025-09-07 03:15:44.000000', ''),
	(66, 506, 3, 'uploads/slips/slip_506_20250918_080830_4e6d9d.jpg', 'image/jpeg', 67406, '2025-09-18 13:08:30.000000', ''),
	(67, 507, 3, 'uploads/slips/slip_507_20250918_082243_9130e6.jpg', 'image/jpeg', 67576, '2025-09-18 13:22:43.000000', ''),
	(68, 21, 3, 'uploads/slips/slip_21_20250927_114924_1d5bf9.jpg', 'image/jpeg', 63796, '2025-09-27 16:49:24.000000', ''),
	(69, 69, 3, 'uploads/slips/slip_69_20250928_113403_a0db21.jpg', 'image/jpeg', 102240, '2025-09-28 16:34:03.000000', ''),
	(70, 78, 3, 'uploads/slips/slip_78_20250929_155008_c277f2.jpg', 'image/jpeg', 32685, '2025-09-29 20:50:08.000000', '');

-- Dumping structure for table psu_blue_cafe.promotions
CREATE TABLE IF NOT EXISTS `promotions` (
  `promo_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `scope` enum('ORDER','ITEM') NOT NULL DEFAULT 'ITEM',
  `discount_type` enum('PERCENT','FIXED') NOT NULL DEFAULT 'PERCENT',
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_total` decimal(10,2) DEFAULT NULL,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`promo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table psu_blue_cafe.promotions: ~8 rows (approximately)
INSERT INTO `promotions` (`promo_id`, `name`, `scope`, `discount_type`, `discount_value`, `min_order_total`, `max_discount`, `start_at`, `end_at`, `is_active`, `usage_limit`, `used_count`, `created_at`, `updated_at`) VALUES
	(1, 'ลด 5', 'ITEM', 'PERCENT', 5.00, NULL, NULL, '2025-09-08 20:29:00', '2025-09-17 20:30:00', 1, NULL, 0, '2025-09-09 20:30:01', '2025-09-11 19:29:48'),
	(2, 'มัทฉะ', 'ITEM', 'PERCENT', 11.00, NULL, NULL, '2025-06-09 21:07:00', '2025-09-04 21:07:00', 0, NULL, 0, '2025-09-09 21:07:11', '2025-09-11 19:29:31'),
	(3, '10', 'ITEM', 'PERCENT', 10.00, NULL, NULL, '2025-09-08 19:30:00', '2025-09-18 19:30:00', 1, NULL, 0, '2025-09-11 19:30:43', '2025-09-11 19:30:43'),
	(4, 'ฉลองการเเต่งงาน', 'ITEM', 'PERCENT', 10.00, NULL, NULL, '2025-09-01 19:30:00', '2025-09-20 19:30:00', 0, NULL, 0, '2025-09-11 19:35:45', '2025-09-18 12:32:16'),
	(5, 'Gay', 'ITEM', 'PERCENT', 5.00, NULL, NULL, '2025-02-02 13:31:00', '2025-09-04 15:34:00', 1, NULL, 0, '2025-09-18 13:31:54', '2025-09-18 13:31:54'),
	(6, 'PUBG', 'ITEM', 'PERCENT', 5.00, NULL, NULL, '2025-09-16 13:37:00', '2025-09-25 13:37:00', 0, NULL, 0, '2025-09-18 13:37:43', '2025-09-27 01:15:40'),
	(7, 'มัทฉะ', 'ITEM', 'PERCENT', 5.00, NULL, NULL, '2025-09-27 13:34:00', '2025-10-03 13:34:00', 0, NULL, 0, '2025-09-27 13:34:21', '2025-09-27 22:00:59'),
	(8, 'มัทฉะ', 'ITEM', 'PERCENT', 10.00, NULL, NULL, '2025-09-17 21:26:00', '2025-09-30 21:26:00', 1, NULL, 0, '2025-09-28 21:26:57', '2025-09-28 21:26:57');

-- Dumping structure for table psu_blue_cafe.promotion_items
CREATE TABLE IF NOT EXISTS `promotion_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `promo_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_promo_menu` (`promo_id`,`menu_id`),
  KEY `idx_menu` (`menu_id`),
  CONSTRAINT `fk_pi_menu` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`menu_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pi_promo` FOREIGN KEY (`promo_id`) REFERENCES `promotions` (`promo_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table psu_blue_cafe.promotion_items: ~14 rows (approximately)
INSERT INTO `promotion_items` (`id`, `promo_id`, `menu_id`) VALUES
	(1, 1, 9),
	(3, 2, 14),
	(2, 2, 15),
	(4, 3, 10),
	(8, 3, 14),
	(10, 4, 14),
	(7, 4, 15),
	(6, 4, 16),
	(9, 5, 12),
	(12, 7, 9),
	(13, 7, 18),
	(15, 8, 9),
	(14, 8, 15),
	(16, 8, 19);

-- Dumping structure for table psu_blue_cafe.recipe_headers
CREATE TABLE IF NOT EXISTS `recipe_headers` (
  `recipe_id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`recipe_id`),
  KEY `fk_rh_menu` (`menu_id`),
  CONSTRAINT `fk_rh_menu` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`menu_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table psu_blue_cafe.recipe_headers: ~18 rows (approximately)
INSERT INTO `recipe_headers` (`recipe_id`, `menu_id`, `title`, `updated_at`, `created_at`) VALUES
	(2, 9, 'มัทฉะลาเต้', '2025-09-25 18:54:00', '2025-09-25 16:06:17'),
	(3, 10, 'มัทฉะมะพร้าว', '2025-09-25 18:54:05', '2025-09-25 16:25:10'),
	(4, 19, 'นมชมพู', '2025-09-25 18:54:07', '2025-09-25 16:30:12'),
	(11, 8, 'โซดาพาณิชย์', '2025-09-25 18:54:22', '2025-09-25 18:52:03'),
	(12, 13, 'ชามะนาว', '2025-09-25 18:54:44', '2025-09-25 18:52:03'),
	(13, 16, 'บ๊วยโซดา', '2025-09-25 18:58:20', '2025-09-25 18:58:05'),
	(14, 20, 'มะพร้าวโยเกิร์ต ', '2025-09-27 13:59:34', '2025-09-25 18:58:05'),
	(15, 7, 'ชาไทย', '2025-09-25 18:58:37', '2025-09-25 18:58:05'),
	(16, 11, 'ชาเขียวนม', '2025-09-25 19:00:31', '2025-09-25 18:58:05'),
	(17, 23, 'ชาเขียวลิ้นจี่', '2025-09-25 19:00:36', '2025-09-25 18:58:05'),
	(18, 12, 'ชาดำเย็น', '2025-09-25 19:00:47', '2025-09-25 18:58:05'),
	(20, 17, 'โอวันตินเย็น', '2025-09-25 19:01:10', '2025-09-25 18:58:05'),
	(21, 18, 'โกโก้เย็น', '2025-09-25 19:01:16', '2025-09-25 18:58:05'),
	(22, 21, 'นมสดสตรอเบอรี่', '2025-09-25 19:01:21', '2025-09-25 18:58:05'),
	(23, 14, 'Blue Americano ', '2025-09-25 19:01:24', '2025-09-25 18:58:05'),
	(26, 8, 'โซดาพาณิชย์', '2025-09-27 14:07:08', '2025-09-27 14:03:34'),
	(27, 14, 'd', '2025-09-29 00:09:57', '2025-09-29 00:09:57'),
	(28, 14, 'dawd', '2025-09-29 00:10:05', '2025-09-29 00:10:05');

-- Dumping structure for table psu_blue_cafe.recipe_ingredients
CREATE TABLE IF NOT EXISTS `recipe_ingredients` (
  `ingredient_id` int(11) NOT NULL AUTO_INCREMENT,
  `recipe_id` int(11) NOT NULL,
  `step_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `qty` varchar(64) DEFAULT NULL,
  `unit` varchar(64) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ingredient_id`),
  KEY `fk_ri_recipe` (`recipe_id`),
  KEY `fk_ri_step` (`step_id`),
  CONSTRAINT `fk_ri_recipe` FOREIGN KEY (`recipe_id`) REFERENCES `recipe_headers` (`recipe_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ri_step` FOREIGN KEY (`step_id`) REFERENCES `recipe_steps` (`step_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table psu_blue_cafe.recipe_ingredients: ~52 rows (approximately)
INSERT INTO `recipe_ingredients` (`ingredient_id`, `recipe_id`, `step_id`, `name`, `qty`, `unit`, `note`, `sort_order`) VALUES
	(6, 2, 6, 'Matcha Powder', '1', 'Teaspoon', 'ช้อนชา', 1),
	(7, 2, 6, 'Hot Water', '70', 'ml', NULL, 2),
	(8, 2, 8, 'Sweetened Condensed Milk', '30', 'ml', 'นมข้นหวาน', 1),
	(9, 2, 8, 'Non-Dairy Half Creamer Fresh Milk', '30', 'ml', 'นมข้นจืด/ครีมเทียมพร่องไขมันสูตรผสมนมสด', 2),
	(10, 2, 8, 'Milk', '70', 'ml', 'นมสด', 3),
	(11, 3, 11, 'Matcha Powder', '1', 'Teaspoon', 'ช้อนชา', 1),
	(12, 3, 11, 'Hot Water', '70', 'ml', NULL, 2),
	(13, 3, 13, 'Coconut Juice', '100', 'ml', NULL, 1),
	(14, 4, 16, 'Milk', '50', 'ml', 'นมสด', 1),
	(15, 4, 16, 'Pink Syrup', '40', 'ml', 'Hale’s Blue Boy สีแดงรสสละ', 2),
	(16, 4, 16, 'Non-Dairy Half Creamer Fresh Milk', '30', 'ml', 'นมข้นจืด/ครีมเทียมพร่องไขมันสูตรผสมนมสด', 3),
	(17, 4, 16, 'Water', '30', 'ml', NULL, 4),
	(26, 11, 24, 'Blue Hawaii Syrup', '15', 'ml', 'ไซรัปบลูฮาวาย', 1),
	(27, 11, 24, 'Lychee Syrup', '15', 'ml', 'ไซรัปลิ้นจี่', 2),
	(28, 11, 24, 'Soda', '10', 'ml', NULL, 3),
	(29, 11, 26, 'Soda', 'Fill-up', '', 'ใส่จนเต็มแก้ว', 1),
	(30, 12, 27, 'Tea (based)', '100', 'ml', 'ชาที่สกัดเตรียมไว้แล้ว', 1),
	(31, 12, 27, 'Lime Syrup', '30', 'ml', 'น้ำเชื่อม', 2),
	(32, 12, 27, 'Water', '50', 'ml', NULL, 3),
	(33, 13, 29, 'Plum Pickle', '1', 'piece', 'บ๊วย 1 เม็ด', 1),
	(34, 13, 29, 'Plum Pickle Juice', '30', 'ml', 'น้ำบ๊วยดอง', 2),
	(35, 13, 29, 'Syrup', '30', 'ml', 'น้ำเชื่อม', 3),
	(36, 13, 29, 'Hot Water', '10', 'ml', NULL, 4),
	(37, 13, 32, 'Soda', 'Fill-up', '', 'ใส่จนเต็มแก้ว', 1),
	(38, 14, 33, 'Coconut Juice', '70', 'ml', NULL, 1),
	(39, 14, 36, 'Yogurt Syrup', '30', 'ml', NULL, 1),
	(40, 14, 36, 'Non-Dairy Half Creamer Fresh Milk', '10', 'ml', 'นมข้นจืด/ครีมเทียมพร่องไขมันสูตรผสมนมสด', 2),
	(41, 15, 38, 'Sweetened Condensed Milk', '30', 'ml', 'นมข้นหวาน', 1),
	(42, 16, 42, 'Sweetened Condensed Milk', '30', 'ml', 'นมข้นหวาน', 1),
	(43, 17, 46, 'Tea (based)', '100', 'ml', 'ชาที่สกัดเตรียมไว้แล้ว', 1),
	(44, 17, 46, 'Water', '50', 'ml', NULL, 2),
	(45, 17, 46, 'Lychee Syrup', '30', 'ml', 'น้ำเชื่อม', 3),
	(46, 18, 48, 'Tea (based)', '100', 'ml', 'ชาที่สกัดเตรียมไว้แล้ว', 1),
	(47, 18, 48, 'Water', '50', 'ml', NULL, 2),
	(48, 18, 48, 'Syrup', '30', 'ml', 'น้ำเชื่อม', 3),
	(52, 20, 52, 'Ovaltine Powder', '2', 'Teaspoons', 'ช้อนชา', 1),
	(53, 20, 52, 'Hot Water', '100', 'ml', NULL, 2),
	(54, 20, 54, 'Sweetened Condensed Milk', '30', 'ml', 'นมข้นหวาน', 1),
	(55, 20, 54, 'Non-Dairy Half Creamer Fresh Milk', '30', 'ml', 'นมข้นจืด/ครีมเทียมพร่องไขมันสูตรผสมนมสด', 2),
	(56, 21, 57, 'Cocoa Powder', '1', 'Teaspoon', 'ช้อนชา', 1),
	(57, 21, 57, 'Hot Water', '100', 'ml', NULL, 2),
	(58, 21, 59, 'Sweetened Condensed Milk', '30', 'ml', 'นมข้นหวาน', 1),
	(59, 21, 59, 'Non-Dairy Half Creamer Fresh Milk', '30', 'ml', 'นมข้นจืด/ครีมเทียมพร่องไขมันสูตรผสมนมสด', 2),
	(60, 22, 62, 'Strawberry Syrup', '30', 'ml', 'ไซรัปสตรอเบอรี่', 1),
	(61, 22, 64, 'Milk', 'Fill-up', '', 'ใส่จนเต็มแก้ว', 1),
	(62, 23, 65, 'Blue Hawaii Syrup', '15', 'ml', 'ไซรัปบลูฮาวาย', 1),
	(63, 23, 65, 'Lychee Syrup', '15', 'ml', 'ไซรัปลิ้นจี่', 2),
	(64, 23, 65, 'Hot Water', '70', 'ml', NULL, 3),
	(65, 23, 68, 'Espresso Shot', '30', 'ml', 'กาแฟ 1 Shot', 1),
	(68, 26, 71, 'Blue Hawaii Syrup				15 	ml', '15', 'ml', 'ไซรัปบลูฮาวาย', 1),
	(69, 26, 71, 'Lychee Syrup', '15', 'ml', 'ไซรัปลิ้นจี่', 2),
	(70, 26, 71, 'Soda', '10', 'ml', '', 3);

-- Dumping structure for table psu_blue_cafe.recipe_steps
CREATE TABLE IF NOT EXISTS `recipe_steps` (
  `step_id` int(11) NOT NULL AUTO_INCREMENT,
  `recipe_id` int(11) NOT NULL,
  `step_no` int(11) NOT NULL,
  `step_text` text NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`step_id`),
  UNIQUE KEY `uq_recipe_step` (`recipe_id`,`step_no`),
  CONSTRAINT `fk_rs_recipe` FOREIGN KEY (`recipe_id`) REFERENCES `recipe_headers` (`recipe_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table psu_blue_cafe.recipe_steps: ~58 rows (approximately)
INSERT INTO `recipe_steps` (`step_id`, `recipe_id`, `step_no`, `step_text`, `sort_order`) VALUES
	(6, 2, 1, 'ใส่ส่วนผสมลงในแก้วชงเพื่อเป็น Topping', 1),
	(7, 2, 2, 'พักรอไว้', 2),
	(8, 2, 3, 'ใส่ส่วนผสมลงในแก้วเสิร์ฟแล้วคนให้เข้ากันเป็น base ในแก้ว 16 oz.', 3),
	(9, 2, 4, 'เติมน้ำแข็ง', 4),
	(10, 2, 5, 'นำมัทฉะที่ผสมน้ำร้อนไว้มาเทลงเป็น Topping แล้วเสิร์ฟ', 5),
	(11, 3, 1, 'ใส่ส่วนผสมลงในแก้วชงเพื่อเป็น Topping', 1),
	(12, 3, 2, 'พักรอไว้', 2),
	(13, 3, 3, 'ใส่ Coconut Juice 100 ml ลงเป็น base ในแก้วเสิร์ฟ 16 oz.', 3),
	(14, 3, 4, 'เติมน้ำแข็ง', 4),
	(15, 3, 5, 'นำมัทฉะที่ผสมน้ำร้อนไว้มาเทลงเป็น Topping แล้วเสิร์ฟ', 5),
	(16, 4, 1, 'ใส่ส่วนผสมลงในแก้วเสิร์ฟคนให้เข้ากัน', 1),
	(17, 4, 2, 'เติมน้ำแข็ง แล้วเสิร์ฟ', 2),
	(24, 11, 1, 'ใส่ส่วนผสมลงในแก้วเสิร์ฟแล้วคนให้เข้ากันเป็น base', 1),
	(25, 11, 2, 'เติมน้ำแข็ง', 2),
	(26, 11, 3, 'เติม Soda Fill-up (ใส่จนเต็มแก้ว)', 3),
	(27, 12, 1, 'ใส่ส่วนผสมลงในแก้วเสิร์ฟเป็น base', 1),
	(28, 12, 2, 'เติมน้ำแข็งแล้วเสิร์ฟ', 2),
	(29, 13, 1, 'ใส่ส่วนผสมลงในแก้วชง', 1),
	(30, 13, 2, 'เตรียมแก้วเสิร์ฟใส่น้ำแข็งเต็มแก้ว', 2),
	(31, 13, 3, 'เทส่วนที่ผสมไว้แล้วจากแก้วชงลงในแก้วเสิร์ฟ 16 oz.', 3),
	(32, 13, 4, 'เติม Soda Fill-up (ใส่จนเต็มแก้ว)', 4),
	(33, 14, 1, 'ใส่ Coconut Juice 70 ml ลงเป็น base ในแก้วเสิร์ฟ 16 oz.', 1),
	(34, 14, 2, 'เติมน้ำแข็ง', 2),
	(35, 14, 3, 'พักรอไว้', 3),
	(36, 14, 4, 'ใส่ส่วนผสมลงในแก้วชงแล้วคนให้เข้ากัน', 4),
	(37, 14, 5, 'เทส่วนที่ผสมไว้จากแก้วชงลงในแก้วเสิร์ฟ 16 oz. เป็น Topping แล้วเสิร์ฟ', 5),
	(38, 15, 1, 'ใส่ส่วนผสมลงในแก้วเสิร์ฟแล้วคนให้เข้ากันเป็น base ในแก้ว 16 oz.', 1),
	(39, 15, 2, 'ใส่ Tea (based) 100 ml (ชาที่สกัดเตรียมไว้แล้ว)', 2),
	(40, 15, 3, 'เติมน้ำแข็ง', 3),
	(41, 15, 4, 'เติม Non-Dairy Half Creamer Fresh Milk 30 ml เป็น Topping แล้วเสิร์ฟ', 4),
	(42, 16, 1, 'ใส่ส่วนผสมลงในแก้วเสิร์ฟแล้วคนให้เข้ากันเป็น base ในแก้ว 16 oz.', 1),
	(43, 16, 2, 'ใส่ Tea (based) 100 ml (ชาที่สกัดเตรียมไว้แล้ว)', 2),
	(44, 16, 3, 'เติมน้ำแข็ง', 3),
	(45, 16, 4, 'เติม Non-Dairy Half Creamer Fresh Milk 30 ml เป็น Topping แล้วเสิร์ฟ', 4),
	(46, 17, 1, 'ใส่ส่วนผสมลงในแก้วเสิร์ฟแล้วคนให้เข้ากันเป็น base ในแก้ว 16 oz.', 1),
	(47, 17, 2, 'เติมน้ำแข็งแล้วเสิร์ฟ', 2),
	(48, 18, 1, 'ใส่ส่วนผสมลงในแก้วเสิร์ฟแล้วคนให้เข้ากันเป็น base ในแก้ว 16 oz.', 1),
	(49, 18, 2, 'เติมน้ำแข็งแล้วเสิร์ฟ', 2),
	(52, 20, 1, 'ใส่ส่วนผสมลงในแก้วชง', 1),
	(53, 20, 2, 'คนให้เข้ากัน', 2),
	(54, 20, 3, 'เติมส่วนผสมเพิ่ม', 3),
	(55, 20, 4, 'เทลงในแก้วเสิร์ฟ 16 oz. ที่ใส่น้ำแข็งเต็มแก้วเตรียมไว้', 4),
	(56, 20, 5, 'โรยหน้า Topping ด้วยผงโอวัลติน', 5),
	(57, 21, 1, 'ใส่ส่วนผสมลงในแก้วชง', 1),
	(58, 21, 2, 'คนให้เข้ากัน', 2),
	(59, 21, 3, 'เติมส่วนผสมเพิ่ม', 3),
	(60, 21, 4, 'เทลงในแก้วเสิร์ฟ 16 oz. ที่ใส่น้ำแข็งเต็มแก้วเตรียมไว้', 4),
	(61, 21, 5, 'โรยหน้า Topping ด้วยผงโอวัลติน', 5),
	(62, 22, 1, 'ใส่ส่วนผสมลงในแก้วเสิร์ฟเป็น base ในแก้ว 16 oz.', 1),
	(63, 22, 2, 'เติมน้ำแข็งเต็มแก้ว', 2),
	(64, 22, 3, 'ใส่ Milk Fill-up (ใส่จนเต็มแก้ว)', 3),
	(65, 23, 1, 'ใส่ส่วนผสมลงในแก้วชง', 1),
	(66, 23, 2, 'คนให้เข้ากัน', 2),
	(67, 23, 3, 'เทลงในแก้วเสิร์ฟ 16 oz. ที่ใส่น้ำแข็งเต็มแก้วเตรียมไว้', 3),
	(68, 23, 4, 'ใส่ Espresso Shot 30 ml เทลงเป็น Topping แล้วเสิร์ฟ', 4),
	(71, 26, 1, 'ใส่ส่วนผสมลงในแก้วเสิร์ฟแล้วคนให้เข้ากันเป็น base ในแก้ว 16 oz.', 1),
	(72, 26, 2, 'เติมน้ำแข็ง', 2),
	(73, 26, 3, 'เติม 	Soda', 3);

-- Dumping structure for table psu_blue_cafe.toppings
CREATE TABLE IF NOT EXISTS `toppings` (
  `topping_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `base_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`topping_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table psu_blue_cafe.toppings: ~5 rows (approximately)
INSERT INTO `toppings` (`topping_id`, `name`, `base_price`, `is_active`) VALUES
	(1, 'ไข่มุก', 5.00, 1),
	(2, 'เจลลี่', 5.00, 1),
	(3, 'พุดดิ้ง', 7.00, 1),
	(4, 'วิปครีม', 10.00, 1),
	(5, 'เฉาก๊วย', 5.00, 1);

-- Dumping structure for table psu_blue_cafe.users
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `student_ID` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` enum('admin','employee') NOT NULL DEFAULT 'employee',
  `status` enum('ชั่วโมงทุน','ชั่วโมงปกติ') NOT NULL DEFAULT 'ชั่วโมงปกติ',
  `default_hour_type` enum('normal','fund') NOT NULL DEFAULT 'normal',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table psu_blue_cafe.users: ~8 rows (approximately)
INSERT INTO `users` (`user_id`, `username`, `password`, `student_ID`, `name`, `role`, `status`, `default_hour_type`) VALUES
	(1, 'admin1', '$2y$11$EDNN/7MVOWI.nW9XTBKYSuBEVx2emCp3Wh5DlAHuIJclvWBocd6q6', 65010001, 'แอดมินใหญ่', 'admin', 'ชั่วโมงปกติ', 'normal'),
	(2, 'front1', '81dc9bdb52d04dc20036dbd8313ed055', 65010002, 'พนักงานหน้าร้าน', 'employee', 'ชั่วโมงปกติ', 'normal'),
	(3, 'a1', '$2y$11$v0ZsaPtzB.YsMoMlAodhHO5u7LTzS.9nm35QMtb3NPuHZqVII/uz6', 650100021, 'พนักงานหน้าร้าน', 'employee', 'ชั่วโมงปกติ', 'normal'),
	(4, 'awdwad', '$2y$10$Qf8RcDT83CjbbKAUuHz8Uu9X.z0GAuIuqUIuXDCPUgVDYuUCus4sG', 2147483647, 'PUBG', 'employee', 'ชั่วโมงปกติ', 'normal'),
	(5, 'admin', '$2y$10$rCtZwYbc9g3vcp41fFXl0O7SYRQSyVANmqEq.n/bOgF6gYne3Y5ku', 23232, 'dawd', 'employee', 'ชั่วโมงปกติ', 'normal'),
	(6, 'a211111', '$2y$10$NGEtJrTB4vy4Qh0blF7vNe28eNVPLsO66MYWCyjmi9hvUA3dESI2O', 2147483647, '1dad', 'employee', 'ชั่วโมงปกติ', 'normal'),
	(7, 'a3', '$2y$10$LuciTtzwiNMbR2zGMlFXB.D.4CDjDipfaPHK1m8NzIEy5lctHHPPy', 123, 'a3', 'employee', 'ชั่วโมงทุน', 'normal'),
	(8, 'x', '$2y$11$ryNN0B5PzvtCt5rhNI5B0eHN8V8kwtKrQNiwmWmCLMPWQQNYbVN2C', 2147483647, 'Soravit', 'employee', 'ชั่วโมงปกติ', 'normal');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
