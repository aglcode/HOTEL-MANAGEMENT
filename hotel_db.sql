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


-- Dumping database structure for hotel_db
CREATE DATABASE IF NOT EXISTS `hotel_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `hotel_db`;

-- Dumping structure for table hotel_db.announcements
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_by` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table hotel_db.announcements: ~0 rows (approximately)

-- Dumping structure for table hotel_db.bookings
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `guest_name` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text,
  `telephone` varchar(20) DEFAULT NULL,
  `age` int DEFAULT NULL,
  `num_people` int DEFAULT NULL,
  `room_number` varchar(10) DEFAULT NULL,
  `duration` varchar(10) DEFAULT NULL,
  `payment_mode` varchar(10) DEFAULT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `booking_token` varchar(20) DEFAULT NULL,
  `amount_paid` double DEFAULT NULL,
  `total_price` double DEFAULT NULL,
  `change_amount` double DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'upcoming',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `cancellation_reason` text,
  `cancelled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table hotel_db.bookings: ~3 rows (approximately)
INSERT INTO `bookings` (`id`, `guest_name`, `email`, `address`, `telephone`, `age`, `num_people`, `room_number`, `duration`, `payment_mode`, `reference_number`, `booking_token`, `amount_paid`, `total_price`, `change_amount`, `start_date`, `end_date`, `status`, `created_at`, `cancellation_reason`, `cancelled_at`) VALUES
	(9, 'Aldrick Dulnuan', NULL, 'Calamba City, Laguna', '09123456789', 21, NULL, '101', '6', 'Cash', '', NULL, 750, 750, NULL, '2025-08-06 13:00:00', '2025-08-06 19:00:00', 'booked', '2025-08-08 11:11:18', NULL, NULL),
	(10, 'Maria', 'calopez@ccc.edu.ph', 'Calamba City, Laguna', '09123456789', 21, 1, '103', '12', '0', '12456542566', 'BK202508083EAAF8', 1100, 1100, 0, '2025-08-08 20:00:00', '2025-08-09 08:00:00', 'cancelled', '2025-08-08 11:14:56', 'The guest didn\'t go at the right time and date', '2025-08-12 17:07:23'),
	(13, 'elvin', 'erreyes@ccc.edu.ph', 'halang', '09761090017', 51, 1, '101', '48', 'Cash', '', 'BK20250811F04207', 130, 120, 10, '2025-08-12 15:15:00', '2025-08-14 15:15:00', 'cancelled', '2025-08-11 07:17:35', 'Didn\'t go at the right time', '2025-08-12 17:19:59');


-- Dumping structure for table hotel_db.rooms
CREATE TABLE IF NOT EXISTS `rooms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `room_number` int NOT NULL,
  `room_type` enum('single','twin_room','standard_room','studio','suite','queen_room','executive_room','suites','accessible_room','hollywood_twin_room','king_room','studio_hotel_rooms','villa','double_hotel_rooms','honeymoon_suite','penthouse_suite','single_hotel_rooms','adjoining_room','presidential_suite','connecting_rooms','quad_room','deluxe_room','double_room','triple_room') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `status` enum('available','booked','maintenance') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `price_3hrs` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_6hrs` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_12hrs` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_24hrs` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_ot` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `room_number` (`room_number`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table hotel_db.rooms: ~2 rows (approximately)
INSERT INTO `rooms` (`id`, `room_number`, `room_type`, `status`, `created_at`, `updated_at`, `price_3hrs`, `price_6hrs`, `price_12hrs`, `price_24hrs`, `price_ot`) VALUES
	(1, 101, 'standard_room', 'available', '2025-04-26 16:34:08', '2025-08-11 07:44:12', 400.00, 750.00, 1100.00, 1500.00, 120.00),
	(2, 102, 'standard_room', 'available', '2025-04-26 17:37:56', '2025-08-08 11:34:02', 400.00, 750.00, 1100.00, 1500.00, 120.00),
	(10, 103, 'standard_room', 'available', '2025-04-28 16:01:31', '2025-08-08 05:46:13', 300.00, 750.00, 1100.00, 1500.00, 120.00),
	(12, 104, 'standard_room', 'available', '2025-04-29 13:29:43', '2025-05-02 17:18:40', 400.00, 750.00, 1100.00, 1500.00, 120.00),
	(13, 106, 'twin_room', 'available', '2025-05-07 06:15:36', '2025-06-24 04:56:41', 400.00, 750.00, 1100.00, 1500.00, 120.00);

-- Dumping structure for table hotel_db.checkins
CREATE TABLE IF NOT EXISTS `checkins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `guest_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `room_number` int NOT NULL,
  `room_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stay_duration` int NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `change_amount` decimal(10,2) NOT NULL,
  `payment_mode` enum('cash','gcash') COLLATE utf8mb4_unicode_ci NOT NULL,
  `check_in_date` datetime NOT NULL,
  `check_out_date` datetime NOT NULL,
  `gcash_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receptionist_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `room_number` (`room_number`),
  CONSTRAINT `checkins_ibfk_1` FOREIGN KEY (`room_number`) REFERENCES `rooms` (`room_number`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hotel_db.checkins: ~9 rows (approximately)
INSERT INTO `checkins` (`id`, `guest_name`, `address`, `telephone`, `room_number`, `room_type`, `stay_duration`, `total_price`, `amount_paid`, `change_amount`, `payment_mode`, `check_in_date`, `check_out_date`, `gcash_reference`, `receptionist_id`) VALUES
	(34, 'Sean', 'Majada Out, Calamba, Laguna', '09123456789', 102, '', 6, 750.00, 750.00, 50.00, 'cash', '2025-08-01 18:27:40', '2025-08-04 15:38:52', '', 1),
	(35, 'Nami', 'Calamba City, Laguna', '09123456789', 101, '', 3, 400.00, 400.00, 100.00, 'cash', '2025-08-04 12:44:38', '2025-08-11 15:44:12', '', 1),
	(36, 'Loki', 'Calamba City, Laguna', '09123456789', 102, '', 3, 400.00, 400.00, 100.00, 'cash', '2025-08-04 14:54:58', '2025-08-04 15:38:52', '', 2),
	(37, 'Sanji', 'Calamba City, Laguna', '09123456789', 102, '', 3, 400.00, 400.00, 100.00, 'cash', '2025-08-04 15:50:22', '2025-08-04 16:02:23', '', 2),
	(38, 'Jose', 'Calamba City, Laguna', '09123456789', 101, '', 6, 750.00, 750.00, 250.00, 'cash', '2025-08-04 16:03:49', '2025-08-11 15:44:12', '', 2),
	(39, 'Sam', 'Calamba City, Laguna', '09123456789', 101, '', 6, 750.00, 750.00, 250.00, 'cash', '2025-08-06 13:00:46', '2025-08-11 15:44:12', '', NULL),
	(40, 'Jose', 'Calamba City, Laguna', '09123456789', 101, '', 7, 883.33, 750.00, 50.00, 'cash', '2025-08-06 17:16:50', '2025-08-11 15:44:12', '', NULL),
	(41, 'John', 'Calamba City, Laguna', '09123456789', 103, '', 8, 950.00, 750.00, 0.00, 'gcash', '2025-08-07 12:58:29', '2025-08-07 21:58:29', '1634629109226', 2),
	(42, 'Carlo', 'Calamba City, Laguna', '09123456789', 101, '', 7, 883.33, 750.00, 250.00, 'cash', '2025-08-07 13:41:01', '2025-08-11 15:44:12', '', 2),
	(43, 'James', 'Majada Out, Calamba, Laguna', '09123456789', 102, '', 25, 1633.33, 1500.00, 0.00, 'gcash', '2025-08-07 13:41:55', '2025-08-08 14:41:55', '1634629109226', 2),
	(44, 'Maria', 'Calamba City, Laguna', '09123456789', 101, '', 7, 883.33, 750.00, 50.00, 'cash', '2025-08-11 13:27:52', '2025-08-11 15:44:12', '', 2),
	(45, 'Nami', 'Calamba City, Laguna', '09123456789', 101, '', 24, 1500.00, 1500.00, 0.00, 'cash', '2025-08-11 15:42:54', '2025-08-11 15:44:12', '', 2);

-- Dumping structure for table hotel_db.complaints
CREATE TABLE IF NOT EXISTS `complaints` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(255) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `complaint_text` text,
  `status` enum('pending','resolved') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table hotel_db.complaints: ~0 rows (approximately)

-- Dumping structure for table hotel_db.feedback
CREATE TABLE IF NOT EXISTS `feedback` (
  `id` int NOT NULL AUTO_INCREMENT,
  `guest_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` enum('feedback','complaint') COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('pending','resolved') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table hotel_db.feedback: ~0 rows (approximately)

-- Dumping structure for table hotel_db.guests
CREATE TABLE IF NOT EXISTS `guests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table hotel_db.guests: ~0 rows (approximately)

-- Dumping structure for table hotel_db.payments
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payment_date` datetime NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_mode` enum('cash','gcash') NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table hotel_db.payments: ~0 rows (approximately)


-- Dumping structure for table hotel_db.users
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Receptionist','Guest') NOT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table hotel_db.users: ~4 rows (approximately)
INSERT INTO `users` (`user_id`, `name`, `username`, `email`, `password`, `role`, `status`) VALUES
	(1, 'Sung Jin-woo', 'jinwhoo', 'example@email.com', '$2y$10$SWb8Zhwunhl3t7HHsLst.O31OwvI1Tie5xsX.kVKPV0KnG5ok7JOm', 'Admin', 'approved'),
	(2, 'Baki Hanma', 'bakii', 'example@email.com', '$2y$10$zKqannJ7cL97TAumEeByUukNozIwEb.AGsP//jD7Fmgo0CPyL2Q6K', 'Receptionist', 'approved'),
	(3, 'Megumi Fushiguro', 'meg_umi', 'example@email.com', '$2y$10$YQ3y5JtTR65YitLygESF3.GuuqddiFkikr5byhF0/GSUINDZf1lwO', 'Receptionist', 'approved'),
	(9, 'Angel', 'angels', 'example@email.com', '$2y$10$H6tYQO141eRMx/Mv559Ru.RMW6BP.Y3wgC3ayrKZnM0CqMXMuSKIe', 'Receptionist', 'approved');


-- Dumping structure for table hotel_db.receptionist_profiles
CREATE TABLE IF NOT EXISTS `receptionist_profiles` (
  `profile_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `dob` date NOT NULL,
  `place_of_birth` varchar(100) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `emergency_contact_name` varchar(100) NOT NULL,
  `emergency_contact` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`profile_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `receptionist_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table hotel_db.receptionist_profiles: ~0 rows (approximately)
INSERT INTO `receptionist_profiles` (`profile_id`, `user_id`, `full_name`, `contact`, `dob`, `place_of_birth`, `gender`, `emergency_contact_name`, `emergency_contact`, `address`, `profile_picture`, `created_at`) VALUES
	(1, 2, 'Baki Hanma', '09123456789', '2000-02-14', 'Kyoto, Japan', 'Male', 'Seijuro Hanma', '091212121212', 'Calamba City, Laguna', 'receptionist_2.jpg', '2025-06-16 03:05:05');

-- Dumping structure for table hotel_db.staff
CREATE TABLE IF NOT EXISTS `staff` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `age` int NOT NULL,
  `sex` enum('Male','Female') NOT NULL,
  `address` text NOT NULL,
  `contact_number` varchar(15) NOT NULL,
  `position` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table hotel_db.staff: ~2 rows (approximately)
INSERT INTO `staff` (`id`, `name`, `age`, `sex`, `address`, `contact_number`, `position`) VALUES
	(3, 'Sung Jin-woo', 20, 'Male', 'Calamba City, Laguna', '09912345678', 'Manager'),
	(4, 'Baki Hanma', 23, 'Male', 'Calamba City, Laguna', '09912345678', 'Receptionist');

  -- Dumping structure for table hotel_db.supplies
CREATE TABLE IF NOT EXISTS `supplies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `category` enum('Cleaning','Maintenance','Food') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table hotel_db.supplies: ~2 rows (approximately)
INSERT INTO `supplies` (`id`, `name`, `price`, `quantity`, `category`, `created_at`) VALUES
	(1, 'Broom', 150.00, 20, 'Cleaning', '2025-07-09 14:07:48'),
	(3, 'Toilet Papers', 100.00, 8, 'Cleaning', '2025-07-09 14:56:50'),
	(5, 'Towels', 20.00, 30, 'Cleaning', '2025-08-04 03:51:34');


-- Dumping structure for table hotel_db.stock_logs
CREATE TABLE IF NOT EXISTS `stock_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `supply_id` int NOT NULL,
  `action` enum('in','out') NOT NULL,
  `quantity` int NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `action_type` enum('in','out') NOT NULL DEFAULT 'in',
  PRIMARY KEY (`id`),
  KEY `supply_id` (`supply_id`),
  CONSTRAINT `stock_logs_ibfk_1` FOREIGN KEY (`supply_id`) REFERENCES `supplies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table hotel_db.stock_logs: ~1 rows (approximately)
INSERT INTO `stock_logs` (`id`, `supply_id`, `action`, `quantity`, `reason`, `created_at`, `action_type`) VALUES
	(1, 1, 'out', 2, '', '2025-07-16 05:45:03', 'in'),
	(2, 1, 'in', 20, '', '2025-08-04 03:52:16', 'in');


/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;