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

-- Dumping structure for table psu_blue_cafe.categories
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table psu_blue_cafe.categories: ~5 rows (approximately)
INSERT INTO `categories` (`category_id`, `category_name`) VALUES
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
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`menu_id`),
  KEY `fk_menu_category` (`category_id`),
  CONSTRAINT `fk_menu_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table psu_blue_cafe.menu: ~17 rows (approximately)
INSERT INTO `menu` (`menu_id`, `category_id`, `name`, `price`, `description`, `image`, `is_active`) VALUES
	(7, 2, 'ชาไทย', 40.00, NULL, '1755537032_3d7ed429ef.jpg', 1),
	(8, 3, 'โซดาพาณิชย์', 40.00, NULL, '1755537089_9564a8f184.png', 1),
	(9, 1, 'มัทฉะลาเต้', 40.00, NULL, '1756375083_7f688ce59c.jpg', 1),
	(10, 1, 'มัทฉะมะพร้าว', 40.00, NULL, '1756375212_b66ec3274f.webp', 1),
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
	(23, 5, 'ชาเขียวลิ้นจี่', 40.00, NULL, '1756375918_63045893fa.png', 1);

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

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
