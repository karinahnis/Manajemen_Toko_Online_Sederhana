-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.30 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for basic_online_store
CREATE DATABASE IF NOT EXISTS `basic_online_store` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `basic_online_store`;

-- Dumping structure for table basic_online_store.categories
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `create_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `update_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table basic_online_store.categories: ~0 rows (approximately)
INSERT INTO `categories` (`id`, `name`, `description`, `create_at`, `update_at`) VALUES
	(1, 'Facial Wash', NULL, '2025-06-08 09:50:57', '2025-06-08 09:50:57'),
	(2, 'Toner', NULL, '2025-06-10 17:19:23', '2025-06-10 17:19:23'),
	(3, 'Moisturizer', NULL, '2025-06-11 04:22:06', '2025-06-11 04:22:06'),
	(4, 'Sunscreen', NULL, '2025-06-13 02:25:23', '2025-06-13 02:25:23');

-- Dumping structure for procedure basic_online_store.get_best_selling_prodcuts
DELIMITER //
CREATE PROCEDURE `get_best_selling_prodcuts`()
BEGIN
SELECT
p.id, p.name, SUM(oi.quantity) AS total_sold
FROM 
order_items oi
JOIN 
product p ON oi.product_id = p.id
GROUP BY
p.id, p.name
ORDER BY
total_sold DESC
LIMIT 10;
END//
DELIMITER ;

-- Dumping structure for table basic_online_store.orders
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `order_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','paid','shipped','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'pending',
  `total_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(50) DEFAULT 'Transfer Bank',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table basic_online_store.orders: ~3 rows (approximately)
INSERT INTO `orders` (`id`, `user_id`, `order_date`, `status`, `total_amount`, `payment_method`) VALUES
	(13, NULL, '2025-06-14 06:10:32', 'completed', 291000.00, 'Cash'),
	(14, NULL, '2025-06-14 06:13:37', 'completed', 411000.00, 'Cash'),
	(15, NULL, '2025-06-14 06:19:06', 'completed', 150000.00, 'Cash');

-- Dumping structure for table basic_online_store.order_items
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `price_at_order` decimal(10,2) NOT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table basic_online_store.order_items: ~7 rows (approximately)
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price_at_order`, `price`) VALUES
	(15, 13, 5, 1, 162000.00, NULL),
	(16, 13, 7, 1, 129000.00, NULL),
	(17, 14, 4, 1, 161000.00, NULL),
	(18, 14, 8, 1, 44000.00, NULL),
	(19, 14, 1, 1, 44000.00, NULL),
	(20, 14, 5, 1, 162000.00, NULL),
	(21, 15, 2, 1, 150000.00, NULL);

-- Dumping structure for table basic_online_store.products
CREATE TABLE IF NOT EXISTS `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `description` text,
  `price` decimal(10,2) DEFAULT NULL,
  `category_id` int NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `stock` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_products_category` (`category_id`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table basic_online_store.products: ~8 rows (approximately)
INSERT INTO `products` (`id`, `name`, `description`, `price`, `category_id`, `image_url`, `stock`, `created_at`, `is_active`, `updated_at`) VALUES
	(1, 'Glad2Glow Low pH Face Wash Cleanser GelBlueberry Ceramide', 'jjj', 44000.00, 1, 'img/product_images/g2g.png', 7, '2025-06-08 10:02:18', 1, '2025-06-13 23:13:37'),
	(2, 'Avoskin Miraculous Refining Toner 100ml', 'voskin Miraculous Refining Toner merupakan salah satu toner eksfoliasi dari Avoskin yang memiliki kandungan AHA-BHA-PHA-PGA yang dipadukan dengan Niacinamide, Tea Tree 2%, Witch Hazel, dan Aloe Vera. Perpaduan kandungan tersebut membuat produk ini efektif untuk memaksimalkan proses eksfoliasi kulit sekaligus menjaga kelembapan kulit, menyamarkan noda hitam, menyamarkan tampilan pori-pori, membantu mencerahkan kulit, dan membuat kulit tampak lebih halus', 150000.00, 2, 'img/product_images/avoskin.png', 8, '2025-06-10 17:22:22', 1, '2025-06-13 23:19:06'),
	(3, 'SOMETHINC Low pH Gentle Jelly Cleanser ', 'Pembersih wajah / Facial Wash / Sabun Muka Vegan bertekstur jelly dengan kandungan gentle yang diformulasikan dengan Japanese Mugwort, Tea Tree, Centella, dan Peppermint serta teruji secara klinis dapat menyimbangkan pH kulit tanpa membuat kulit kering, tertarik & merusak barier kulit / skin barrier.Ukuran: 15 ml | 100 ml | 350ml. No BPOM: NA18211200050. Manfaat:Membersihkan debu, kotoran, & minyak berlebih pada kulit. Menenangkan kulit kembali :Menjaga kulit lebih cerah dan halus, Meminimalisasi terjadinya reaksi sensitisasi .', 97000.00, 1, 'img/product_images/product_1618206010_Somethinc__800x800.jpg', 30, '2025-06-11 03:22:44', 1, '2025-06-13 11:55:27'),
	(4, 'COSRX Centella Water Alcohol-Free Toner Skin Care - 150 ML', 'Toner dengan 82% air mineral dari Jeju dan 10% ekstrak air daun Centella Asiatica yang berfungsi untuk menenangkan kulit yang iritasi karena jerawat/stress, menghidrasi, dan menutrisi kulit dengan vitamin dan mineral. Bahan Utama:Mineral Water from JEJU 82%, Centella Asiatica Leaf Water 10%.Bahan lainnya:Mineral Water, Centella Asiatica Leaf Water, Butylene Glycol, 1,2-Hexanediol, Betaine, Panthenol, Allantoin, Sodium Hyaluronate, Ethyl Hexanediol.BPOM:', 161000.00, 2, 'img/product_images/OIP.jpeg', 19, '2025-06-11 04:26:53', 1, '2025-06-13 23:13:37'),
	(5, 'SKIN1004 Madagascar Centella Tone Brightening Boosting Toner 210ml  ', ' Ekstrak Centella Asiatica Memberikan perawatan menenangkan yang mendalam. MADEWHITE Kandungan pencerah yang dipatenkan yang dapat membersihkan kulit,Kndungan perawatan sel kulit mati yang dapat menghilangkan sel kulit mati dan meningkatkan penyerapan kelembaban.-O Ethyl Ascorbic Acid: Turunan Vitamin C dan memberikan perawatan mencerahkan. No.BPOM : NA26211200834', 162000.00, 2, 'img/product_images/SKIN1004-Madagascar-Centella-Tone-Brightening-Boosting-Toner-210ml-price-in-Bangladesh.jpg', 23, '2025-06-11 04:35:35', 1, '2025-06-13 23:13:37'),
	(6, 'SKINTIFIC - Panthenol Gentle Gel Cleanser 120ml ', 'Gentle Gel Cleanser. Mengkombinasikan  dan Amino Acid sehingga membersihkan hingga ke dalam pori-pori dan membantu menghilangkan kotoran, kelebihan minyak dan membantu mencegah pori tersumbat. Membuat kulit terus terhidrasi dan menjadikan kulit lebih lembut dan halus. Ukuran: 120 ml. No BPOM: NA11231200537. Manfaat: Kulit Bersih & Terasa Segar Terhidrasi, Membantu menenangkan kulit, Menyegarkan & melembabkan kulit. Hero Ingredients:  membantu menenangkan kulit, memberi kelembaban untuk kulit halus dan lembut.   Amino Acid : Memberikan kelembaban pada kulit dan membersihkan dari kotoran tanpa menjadikan kulit terasa kering Ceramide : Asam lemak yang berperan dalam menjaga kelembaban kulit dan menjaga skin barrier.', 98000.00, 1, 'img/product_images/product_image-1693833416.jpeg', 23, '2025-06-11 04:42:13', 1, '2025-06-13 13:28:06'),
	(7, 'SKINTIFIC - MSH Niacinamide Brightening Moisturizer Gel 30g', 'MSH Niacinamide Brightening Moisture Gel, with its lightweight texture, absorbs quickly and helps in oil control. Formulated with the novel SKINTIFIC exclusive MSH Niacinamide combined with two lightweight and highly effective brightening agents, Alpha Arbutin and Tranexamic Acid. Helps in significantly brightens the skin. Clinically proven to be 10 times more effective than regular niacinamide in reducing dark spots and blackheads. It also enriched with Centella Asiatica and 5X Ceramide, that provides a soothing effect on the skin while preserving the strength of the skin barrier.', 129000.00, 3, 'img/product_images/SKINTIFIC_MSH_Niacinamide_Brightening_Moisture_Gel_30gr_(1).jpg', 13, '2025-06-11 04:46:30', 1, '2025-06-13 23:10:32'),
	(8, 'Glad2Glow Blueberry Moisturizer 5% Ceramide ', 'Moisturizer dengan ekstrak blueberry dan Ceramide yang berfungsi untuk merawat  kulit sensitif. Memiliki tekstur gel ringan yang mudah meresap, melembabkan kulit dengan baik dan memberikan sensasi cooling dengan aroma blueberry yang menyegarkan. Cocok untuk kulit kering, berminyak, maupun sensitif.Manfaat: Menjaga skin barrier kulit, Membantu merawat kulit sensitif, Membantu menyejukkan kulit, Menjaga hidrasi dan kelembapan kulit, Hero Ingredients: Blueberry extract : sebagai anti-oxidant Ceramide : menjaga skin barrier kulit.', 44000.00, 3, 'img/product_images/830a9a62b393927cf261245223b48762.jpeg', 13, '2025-06-11 04:50:08', 1, '2025-06-13 23:13:37');

-- Dumping structure for table basic_online_store.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(225) NOT NULL,
  `email` varchar(225) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table basic_online_store.users: ~1 rows (approximately)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES
	(1, 'Admin', 'admin@gmail.com', '$2y$10$1faCdN2716uY3f6LhrkuvuPLm/3pY7qeiQH70PocJB0SWO2yYG1cu', '2025-06-13 17:45:11', '2025-06-13 17:54:41');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
