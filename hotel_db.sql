-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 13, 2025 at 01:03 AM
-- Server version: 8.4.3
-- PHP Version: 8.2.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hotel_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_by` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `message`, `created_by`, `created_at`) VALUES
(18, 'Sample Announcement', 'This is a test.', 'Admin', '2025-09-22 20:58:57'),
(19, 'Check In/out Logic Error', 'May mga error sa check in/out, status, and logic. If naka checked out na si guest na pu-pull out padin from DB as \'In use\' or \'Active\'. \r\n\r\n- Gelo', 'Admin', '2025-10-11 07:47:14');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int NOT NULL,
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
  `cancelled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `guest_name`, `email`, `address`, `telephone`, `age`, `num_people`, `room_number`, `duration`, `payment_mode`, `reference_number`, `booking_token`, `amount_paid`, `total_price`, `change_amount`, `start_date`, `end_date`, `status`, `created_at`, `cancellation_reason`, `cancelled_at`) VALUES
(9, 'Aldrick Dulnuan', NULL, 'Calamba City, Laguna', '09123456789', 21, NULL, '101', '6', 'Cash', '', NULL, 750, 750, NULL, '2025-08-06 13:00:00', '2025-08-06 19:00:00', 'completed', '2025-08-08 11:11:18', NULL, NULL),
(10, 'Maria', 'calopez@ccc.edu.ph', 'Calamba City, Laguna', '09123456789', 21, 1, '103', '12', '0', '12456542566', 'BK202508083EAAF8', 1100, 1100, 0, '2025-08-08 20:00:00', '2025-08-09 08:00:00', 'cancelled', '2025-08-08 11:14:56', 'The guest didn\'t go at the right time and date', '2025-08-12 17:07:23'),
(13, 'elvin', 'erreyes@ccc.edu.ph', 'halang', '09761090017', 51, 1, '101', '48', 'Cash', '', 'BK20250811F04207', 130, 120, 10, '2025-08-12 15:15:00', '2025-08-14 15:15:00', 'cancelled', '2025-08-11 07:17:35', 'Didn\'t go at the right time', '2025-08-12 17:19:59'),
(17, 'Adobe Premiere ', 'adobe@gmail.com', 'Brgy. Lawa', '09165770822', 18, 1, '101', '3', 'Cash', '', 'BK202510066DDB35', 400, 400, 0, '2025-10-07 00:35:00', '2025-10-07 03:35:00', 'cancelled', '2025-10-06 16:36:06', 'Automatically cancelled - Guest did not check in within 30 minutes of designated time', '2025-10-07 22:37:16');

-- --------------------------------------------------------

--
-- Table structure for table `checkins`
--

CREATE TABLE `checkins` (
  `id` int NOT NULL,
  `guest_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `room_number` int NOT NULL,
  `room_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stay_duration` int NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `change_amount` decimal(10,2) NOT NULL,
  `payment_mode` enum('cash','gcash') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `check_in_date` datetime NOT NULL,
  `check_out_date` datetime NOT NULL,
  `status` enum('scheduled','checked_in','checked_out') COLLATE utf8mb4_unicode_ci DEFAULT 'scheduled',
  `gcash_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receptionist_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `checkins`
--

INSERT INTO `checkins` (`id`, `guest_name`, `address`, `telephone`, `room_number`, `room_type`, `stay_duration`, `total_price`, `amount_paid`, `change_amount`, `payment_mode`, `check_in_date`, `check_out_date`, `status`, `gcash_reference`, `receptionist_id`) VALUES
(70, 'Joji', 'Brgy. Lawa', '+639483743434', 101, '', 3, 400.00, 400.00, 400.00, 'gcash', '2025-10-12 12:23:39', '2025-10-12 15:23:39', 'checked_out', '31321323133', 2),
(71, 'OZI', 'BLK 5, LOT5, MALIGAYA VILLAGE, BRGY. LAWA, CALAMBA CITY, LAGUNA', '+639165770829', 102, '', 3, 400.00, 400.00, 400.00, 'cash', '2025-10-12 12:25:59', '2025-10-12 15:25:59', 'checked_out', '', 2),
(72, 'San Miguel', 'Brgy. Barandal', '+639165770822', 101, '', 3, 400.00, 400.00, 400.00, 'cash', '2025-10-12 21:10:40', '2025-10-13 00:10:40', 'checked_out', '', 2),
(73, 'John Doe', '123 Main ST.', '+639898989843', 101, '', 4, 533.33, 400.00, 400.00, 'cash', '2025-10-13 08:58:43', '2025-10-13 09:02:00', 'checked_out', '', 2);

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` int NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `complaint_text` text,
  `status` enum('pending','resolved') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int NOT NULL,
  `guest_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` enum('feedback','complaint') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('pending','resolved') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `guests`
--

CREATE TABLE `guests` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `keycards`
--

CREATE TABLE `keycards` (
  `id` int NOT NULL,
  `room_number` int NOT NULL,
  `booking_id` int DEFAULT NULL,
  `guest_id` int DEFAULT NULL,
  `qr_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valid_from` datetime NOT NULL,
  `valid_to` datetime NOT NULL,
  `status` enum('active','expired','revoked') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `keycards`
--

INSERT INTO `keycards` (`id`, `room_number`, `booking_id`, `guest_id`, `qr_code`, `valid_from`, `valid_to`, `status`) VALUES
(1, 101, NULL, NULL, '321A345D', '2025-10-05 10:16:23', '2025-10-13 12:58:43', 'expired'),
(2, 102, NULL, NULL, 'B7B78413', '2025-10-10 11:58:31', '2035-10-10 11:58:31', 'active'),
(3, 103, NULL, NULL, '3021063D', '2025-10-10 11:58:36', '2025-10-11 23:19:45', 'active'),
(4, 104, NULL, NULL, '0B5CE489', '2025-10-12 00:40:48', '2035-10-12 00:40:48', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `category` varchar(50) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `size` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int DEFAULT '1',
  `mode_payment` enum('cash','gcash') NOT NULL,
  `ref_number` varchar(14) DEFAULT NULL,
  `status` enum('pending','served') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `room_number`, `category`, `item_name`, `size`, `price`, `quantity`, `mode_payment`, `ref_number`, `status`, `created_at`) VALUES
(3, '101', 'Food', 'Lomi', 'Small', 120.00, 2, 'cash', NULL, 'served', '2025-10-09 10:07:45'),
(4, '101', 'Food', 'Mami', 'Unit', 70.00, 1, 'cash', NULL, 'served', '2025-10-11 15:43:11'),
(5, '102', 'Food', 'Nissin Cup (Chicken)', 'Unit', 40.00, 1, 'cash', NULL, 'served', '2025-10-11 15:56:47'),
(6, '103', 'Food', 'Nissin Cup (Spicy Seafood)', 'Unit', 120.00, 3, 'cash', NULL, 'served', '2025-10-11 16:32:16'),
(7, '103', 'Food', 'Mami', 'Unit', 70.00, 1, 'cash', NULL, 'served', '2025-10-11 16:32:22'),
(8, '104', 'Food', 'Lomi', 'Small', 120.00, 2, 'cash', NULL, 'pending', '2025-10-11 16:41:01'),
(9, '104', 'Non-Food', 'Toothbrush with Toothpaste', 'Unit', 25.00, 1, 'cash', NULL, 'pending', '2025-10-11 16:41:10'),
(10, '101', 'Food', 'Lomi', 'Small', 60.00, 1, 'cash', NULL, 'served', '2025-10-12 04:24:01'),
(11, '101', 'Food', 'Mami', 'Unit', 70.00, 1, 'cash', NULL, 'served', '2025-10-12 04:24:08'),
(12, '102', 'Food', 'Nissin Cup (Chicken)', 'Unit', 400.00, 10, 'cash', NULL, 'served', '2025-10-12 04:26:21');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int NOT NULL,
  `payment_date` datetime NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_mode` enum('cash','gcash') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `receptionist_profiles`
--

CREATE TABLE `receptionist_profiles` (
  `profile_id` int NOT NULL,
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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `receptionist_profiles`
--

INSERT INTO `receptionist_profiles` (`profile_id`, `user_id`, `full_name`, `contact`, `dob`, `place_of_birth`, `gender`, `emergency_contact_name`, `emergency_contact`, `address`, `profile_picture`, `created_at`) VALUES
(1, 2, 'Baki Hanma', '09123456789', '2000-02-14', 'Kyoto, Japan', 'Male', 'Seijuro Hanma', '091212121212', 'Calamba City, Laguna', 'receptionist_2.jpg', '2025-06-16 03:05:05');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int NOT NULL,
  `room_number` int NOT NULL,
  `room_type` enum('single','twin_room','standard_room','studio','suite','queen_room','executive_room','suites','accessible_room','hollywood_twin_room','king_room','studio_hotel_rooms','villa','double_hotel_rooms','honeymoon_suite','penthouse_suite','single_hotel_rooms','adjoining_room','presidential_suite','connecting_rooms','quad_room','deluxe_room','double_room','triple_room') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `status` enum('available','booked','maintenance') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `price_3hrs` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_6hrs` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_12hrs` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_24hrs` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_ot` decimal(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_number`, `room_type`, `status`, `created_at`, `updated_at`, `price_3hrs`, `price_6hrs`, `price_12hrs`, `price_24hrs`, `price_ot`) VALUES
(1, 101, 'standard_room', 'available', '2025-04-26 16:34:08', '2025-10-13 01:02:00', 400.00, 750.00, 1100.00, 1500.00, 120.00),
(2, 102, 'standard_room', 'available', '2025-04-26 17:37:56', '2025-10-12 13:09:28', 400.00, 750.00, 1100.00, 1500.00, 120.00),
(10, 103, 'standard_room', 'available', '2025-04-28 16:01:31', '2025-10-12 04:22:57', 300.00, 750.00, 1100.00, 1500.00, 120.00),
(12, 104, 'standard_room', 'available', '2025-04-29 13:29:43', '2025-10-12 04:22:57', 400.00, 750.00, 1100.00, 1500.00, 120.00),
(13, 106, 'twin_room', 'available', '2025-05-07 06:15:36', '2025-10-11 07:28:10', 400.00, 750.00, 1100.00, 1500.00, 120.00),
(18, 107, 'single', 'available', '2025-09-29 09:08:45', '2025-09-29 09:30:18', 400.00, 600.00, 1200.00, 1400.00, 120.00),
(20, 108, 'executive_room', 'maintenance', '2025-09-29 09:32:33', '2025-09-29 09:32:33', 400.00, 800.00, 1200.00, 1600.00, 120.00),
(21, 110, 'standard_room', 'available', '2025-10-07 14:54:39', '2025-10-11 07:28:10', 400.00, 600.00, 1200.00, 1800.00, 120.00);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `age` int NOT NULL,
  `sex` enum('Male','Female') NOT NULL,
  `address` text NOT NULL,
  `contact_number` varchar(15) NOT NULL,
  `position` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `name`, `age`, `sex`, `address`, `contact_number`, `position`) VALUES
(3, 'Sung Jin-woo', 20, 'Male', 'Calamba City, Laguna', '09912345678', 'Manager'),
(4, 'Baki Hanma', 23, 'Male', 'Calamba City, Laguna', '09912345678', 'Receptionist');

-- --------------------------------------------------------

--
-- Table structure for table `stock_logs`
--

CREATE TABLE `stock_logs` (
  `id` int NOT NULL,
  `supply_id` int NOT NULL,
  `action` enum('in','out') NOT NULL,
  `quantity` int NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `action_type` enum('in','out') NOT NULL DEFAULT 'in'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `stock_logs`
--

INSERT INTO `stock_logs` (`id`, `supply_id`, `action`, `quantity`, `reason`, `created_at`, `action_type`) VALUES
(1, 1, 'out', 2, '', '2025-07-16 05:45:03', 'in'),
(2, 1, 'in', 20, '', '2025-08-04 03:52:16', 'in'),
(3, 6, 'in', 5, '', '2025-09-22 08:42:55', 'in'),
(4, 1, 'in', 5, '', '2025-09-22 08:45:03', 'out');

-- --------------------------------------------------------

--
-- Table structure for table `supplies`
--

CREATE TABLE `supplies` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `category` enum('Cleaning','Maintenance','Food') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `supplies`
--

INSERT INTO `supplies` (`id`, `name`, `price`, `quantity`, `category`, `created_at`) VALUES
(1, 'Broom', 150.00, 15, 'Cleaning', '2025-07-09 14:07:48'),
(3, 'Toilet Papers', 100.00, 2, 'Cleaning', '2025-07-09 14:56:50'),
(5, 'Towels', 20.00, 30, 'Cleaning', '2025-08-04 03:51:34'),
(6, 'Tissues', 250.00, 15, 'Cleaning', '2025-09-22 08:20:45'),
(11, 'Test', 300.00, 20, 'Maintenance', '2025-09-29 18:21:59'),
(14, 'Cleaning Spray', 300.00, 20, 'Cleaning', '2025-09-29 18:33:51'),
(16, 'Hammer', 250.00, 1, 'Maintenance', '2025-09-29 19:05:34'),
(18, 'Pork Steak', 70.00, 10, 'Food', '2025-10-12 04:56:36');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Receptionist','Guest') NOT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `username`, `email`, `password`, `role`, `status`, `reset_token`, `reset_expires`) VALUES
(1, 'Sung Jin-woo', 'jinwhoo', 'example@email.com', '$2y$10$SWb8Zhwunhl3t7HHsLst.O31OwvI1Tie5xsX.kVKPV0KnG5ok7JOm', 'Admin', 'approved', NULL, NULL),
(2, 'Baki Hanma', 'bakii', 'example@email.com', '$2y$10$zKqannJ7cL97TAumEeByUukNozIwEb.AGsP//jD7Fmgo0CPyL2Q6K', 'Receptionist', 'approved', NULL, NULL),
(3, 'Megumi Fushiguro', 'meg_umi', 'example@email.com', '$2y$10$YQ3y5JtTR65YitLygESF3.GuuqddiFkikr5byhF0/GSUINDZf1lwO', 'Receptionist', 'approved', NULL, NULL),
(9, 'Angel', 'angels', 'example@email.com', '$2y$10$H6tYQO141eRMx/Mv559Ru.RMW6BP.Y3wgC3ayrKZnM0CqMXMuSKIe', 'Receptionist', 'approved', NULL, NULL),
(12, 'dev test', 'devtest', 'devtest@gmail.com', '$2y$10$/Z.vroBzK8Hs.YVAdgbeOebq9V8Y/KWh/D0fmIQi2izX4J4WT61sa', 'Receptionist', 'pending', NULL, NULL),
(13, 'dev test two', 'devtest2', 'devtest2@gmail.com', '$2y$10$XHqW8K/xBRUlkBeh/k/MOeh09sVLgVkFf8B4FYaplXDRijllQJany', 'Receptionist', 'pending', NULL, NULL),
(14, 'John Doe ', 'john', 'johndoe@gmail.com', '$2y$10$civ0BziV5mRIMw74tA55auch53rQWh/OzkwOnWhAWQ3d4KhJd1T5O', 'Receptionist', 'pending', NULL, NULL),
(15, 'Jane Smith', 'Jane', 'janesmith@gmail.com', '$2y$10$yzohZ7y9tNgsT9lvUthO0.wOdB6BVscssBZ1hR9WxGvkTCRyKfY3K', 'Receptionist', 'pending', NULL, NULL),
(16, 'Emily Carter', 'Emily', 'emilycarter@gmail.com', '$2y$10$NW9eYV84Y6ZIxqZvp1p/ZOFxEKNAnBLwgT/voyQyWjonuebkoPuPe', 'Receptionist', 'pending', NULL, NULL),
(17, 'David Brown', 'David', 'davidbrown@gmail.com', '$2y$10$U7dy9bciBLhUV.85JPyKCecaFAL/N7aUJ3QllCH/9MVG.78IE8/v.', 'Receptionist', 'pending', NULL, NULL),
(18, 'Michael Johnson', 'michael', 'michaeljohnson@gmail.com', '$2y$10$BGSXU2IlDvzQ9CjQu6qIsOzFI8F00i96qRN2wN4FeKQNsJfPFZKly', 'Receptionist', 'pending', NULL, NULL),
(19, 'Toby Maguire', 'toby', 'toby@gmail.com', '$2y$10$DNG1bkEITaZunq6phIfPIOhJWNwiwhJgtPvbP.iSPWU5g985/iwYW', 'Guest', 'pending', NULL, NULL),
(21, 'Makima', 'makima', 'makima@gmail.com', '$2y$10$iWMMQr/lEHmycuWaujWSGOxt9qp1qsl1wpsl5iQ5qoV.P2dC6atH2', 'Receptionist', 'approved', NULL, NULL),
(27, 'Angelo Almonte', 'Gelo', 'kukuhuskar82@gmail.com', '$2y$10$DzWczH5EfdcDIfFAmTSL/.R3xXeZuTKmqlO/tKrSWdmSsLTrZys7G', 'Receptionist', 'approved', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `checkins`
--
ALTER TABLE `checkins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_number` (`room_number`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `guests`
--
ALTER TABLE `guests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `keycards`
--
ALTER TABLE `keycards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_number` (`room_number`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `keycards_ibfk_3` (`guest_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `receptionist_profiles`
--
ALTER TABLE `receptionist_profiles`
  ADD PRIMARY KEY (`profile_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD UNIQUE KEY `room_number` (`room_number`) USING BTREE;

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_logs`
--
ALTER TABLE `stock_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supply_id` (`supply_id`);

--
-- Indexes for table `supplies`
--
ALTER TABLE `supplies`
  ADD PRIMARY KEY (`id`) USING BTREE;

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `checkins`
--
ALTER TABLE `checkins`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `guests`
--
ALTER TABLE `guests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `keycards`
--
ALTER TABLE `keycards`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `receptionist_profiles`
--
ALTER TABLE `receptionist_profiles`
  MODIFY `profile_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `stock_logs`
--
ALTER TABLE `stock_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `supplies`
--
ALTER TABLE `supplies`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `checkins`
--
ALTER TABLE `checkins`
  ADD CONSTRAINT `checkins_ibfk_1` FOREIGN KEY (`room_number`) REFERENCES `rooms` (`room_number`);

--
-- Constraints for table `keycards`
--
ALTER TABLE `keycards`
  ADD CONSTRAINT `keycards_ibfk_1` FOREIGN KEY (`room_number`) REFERENCES `rooms` (`room_number`),
  ADD CONSTRAINT `keycards_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `keycards_ibfk_3` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `receptionist_profiles`
--
ALTER TABLE `receptionist_profiles`
  ADD CONSTRAINT `receptionist_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_logs`
--
ALTER TABLE `stock_logs`
  ADD CONSTRAINT `stock_logs_ibfk_1` FOREIGN KEY (`supply_id`) REFERENCES `supplies` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
