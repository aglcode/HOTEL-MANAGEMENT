-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 22, 2025 at 04:52 PM
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
(17, 'Adobe Premiere ', 'adobe@gmail.com', 'Brgy. Lawa', '09165770822', 18, 1, '101', '3', 'Cash', '', 'BK202510066DDB35', 400, 400, 0, '2025-10-07 00:35:00', '2025-10-07 03:35:00', 'cancelled', '2025-10-06 16:36:06', 'Automatically cancelled - Guest did not check in within 30 minutes of designated time', '2025-10-07 22:37:16'),
(18, 'Zayn', 'kukuhuskar82@gmail.com', '123 Main St.', '09165770987', 18, 1, '101', '3', '0', '', 'BK20251018D32B00', 400, 400, 0, '2025-10-18 21:15:00', '2025-10-19 00:15:00', 'completed', '2025-10-18 13:15:43', NULL, NULL),
(19, 'Zayn', 'kukuhuskar82@gmail.com', '123 Main St.', '09165770987', 18, 1, '101', '3', '0', '', 'BK202510181629DA', 400, 400, 0, '2025-10-18 21:15:00', '2025-10-19 00:15:00', 'completed', '2025-10-18 13:33:09', NULL, NULL),
(20, 'Zayn', 'kukuhuskar82@gmail.com', '123 Main St.', '09165770987', 18, 1, '101', '3', '0', '', 'BK202510187FBD8C', 400, 400, 0, '2025-10-18 21:15:00', '2025-10-19 00:15:00', 'completed', '2025-10-18 13:33:17', NULL, NULL),
(21, 'Bazz', 'kukuhuskar82@gmail.com', '123 Main St.', '09165770822', 22, 1, '101', '3', '0', '', 'BK20251018303620', 400, 400, 0, '2025-10-18 21:33:00', '2025-10-19 00:33:00', 'completed', '2025-10-18 13:34:04', NULL, NULL),
(22, 'Toji', 'kukuhuskar82@gmail.com', 'St 123 Main Street ', '09165770822', 18, 1, '101', '3', '0', '', 'BK202510217A6C86', 400, 400, 0, '2025-10-21 21:19:00', '2025-10-22 00:19:00', 'completed', '2025-10-21 13:19:53', NULL, NULL),
(23, 'Mark', 'kukuhuskar82@gmail.com', 'St 123 Main Street ', '09165770822', 20, 1, '101', '3', '0', '', 'BK20251021C1CB60', 400, 400, 0, '2025-10-21 21:24:00', '2025-10-22 00:24:00', 'completed', '2025-10-21 13:24:28', NULL, NULL),
(24, 'Test', 'lopezcyeanne0318@gmail.com', 'St 123 Main Street ', '09165770827', 18, 1, '101', '3', '0', '', 'BK2025102205F1D2', 400, 400, 0, '2025-10-22 12:42:00', '2025-10-22 15:42:00', 'completed', '2025-10-22 04:47:20', NULL, NULL),
(25, 'Adobong Paksiw', 'kukuhuskar82@gmail.com', 'St 123 Main Street ', '0916-577-0822', 23, 0, '101', '3', '0', '', 'BK20251022E28B1E', 0, 400, 0, '2025-10-22 20:51:00', '2025-10-22 23:51:00', 'completed', '2025-10-22 10:51:11', NULL, NULL);

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
(74, 'Arthur Lewin', 'St 123 Main Street', '+639165770822', 101, '', 3, 400.00, 400.00, 400.00, 'cash', '2025-10-13 19:12:00', '2025-10-13 19:12:11', 'checked_out', '', 2),
(75, 'Erza Scarlet', 'St 123 Main Street', '+639437482374', 101, '', 3, 400.00, 400.00, 400.00, 'cash', '2025-10-13 19:12:39', '2025-10-13 22:12:39', 'checked_out', '', 2),
(76, 'Magenta', 'St 123 Main Street', '+639165770822', 101, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-13 22:20:42', '2025-10-13 22:44:56', 'checked_out', '', 2),
(77, 'Kamado Tanjiro', 'St 123 Main Street', '+639165770822', 101, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-13 22:46:20', '2025-10-13 23:07:48', 'checked_out', '', 2),
(78, 'DING DONG', 'St 123 Main Street', '+639437482374', 101, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-13 23:16:30', '2025-10-13 23:17:37', 'checked_out', '', 2),
(79, 'Aqua', 'St 123 Main Street', '+639165770829', 101, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-13 23:21:34', '2025-10-13 23:22:05', 'checked_out', '', 2),
(80, 'Balmond', 'St 123 Main Street', '+639437482374', 101, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-13 23:25:15', '2025-10-13 23:25:30', 'checked_out', '', 2),
(81, 'Maria', 'St 123 Main Street', '+639165770822', 101, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-13 23:32:10', '2025-10-13 23:32:24', 'checked_out', '', 2),
(82, 'King', 'St 123 Main Street', '+639165770822', 101, '', 5, 640.00, 640.00, 0.00, 'cash', '2025-10-13 23:35:06', '2025-10-13 23:36:18', 'checked_out', '', 2),
(83, 'Macarov', 'St 123 Main Street', '+639165770822', 101, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-13 23:41:25', '2025-10-13 23:44:56', 'checked_out', '', 2),
(84, 'Fred', 'St 123 Main Street', '+639165770822', 102, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-13 23:45:26', '2025-10-13 23:50:28', 'checked_out', '', 2),
(85, 'Karina', 'St 123 Main Street', '+639165770822', 103, '', 4, 420.00, 420.00, 0.00, 'cash', '2025-10-13 23:50:49', '2025-10-13 23:51:10', 'checked_out', '', 2),
(86, 'UI', 'St 123 Main Street', '+639437482374', 103, '', 5, 540.00, 540.00, 0.00, 'cash', '2025-10-13 23:52:47', '2025-10-14 00:33:25', 'checked_out', '', 2),
(87, 'Cath', 'St 123 Main Street', '+639165770822', 102, '', 3, 520.00, 520.00, 0.00, 'cash', '2025-10-14 00:34:13', '2025-10-14 00:34:53', 'checked_out', '', 2),
(88, 'Binch', 'Brgy. Barandal', '+639165770822', 101, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-14 00:36:36', '2025-10-14 00:37:23', 'checked_out', '', 2),
(89, 'Camel', 'St 123 Main Street', '+639165770822', 103, '', 3, 300.00, 300.00, 300.00, 'cash', '2025-10-21 21:36:10', '2025-10-22 00:36:10', 'checked_out', '', 2),
(90, 'Giniling na lumpia', 'St 123 Main Street', '+639165770822', 101, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-22 12:59:21', '2025-10-22 15:59:21', 'checked_out', '', 2),
(91, 'Godzilla', 'St 123 Main Street', '+639165770822', 101, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-22 16:43:34', '2025-10-22 19:43:34', 'checked_out', '', 2),
(92, 'Adobong Paksiw', 'St 123 Main Street', '+639165770822', 101, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-22 21:12:08', '2025-10-23 00:12:08', 'checked_out', '', 2),
(93, 'Guest Avelgest', 'St 123 Main Street', '+639437482374', 102, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-22 23:44:03', '2025-10-23 02:44:03', 'checked_in', '', 2),
(94, 'Everest', 'St 123 Main Street', '+639437482374', 101, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-23 00:47:52', '2025-10-23 03:47:52', 'checked_in', '', 2);

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
(1, 101, NULL, NULL, '321A345D', '2025-10-05 10:16:23', '2025-10-14 04:36:36', 'expired'),
(2, 102, NULL, NULL, 'B7B78413', '2025-10-10 11:58:31', '2025-10-14 04:34:13', 'expired'),
(3, 103, NULL, NULL, '3021063D', '2025-10-10 11:58:36', '2025-10-14 04:52:47', 'expired'),
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
  `status` enum('pending','served') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `room_number`, `category`, `item_name`, `size`, `price`, `quantity`, `status`, `created_at`) VALUES
(3, '101', 'Food', 'Lomi', 'Small', 120.00, 2, 'served', '2025-10-09 10:07:45'),
(4, '101', 'Food', 'Mami', 'Unit', 70.00, 1, 'served', '2025-10-11 15:43:11'),
(5, '102', 'Food', 'Nissin Cup (Chicken)', 'Unit', 40.00, 1, 'served', '2025-10-11 15:56:47'),
(6, '103', 'Food', 'Nissin Cup (Spicy Seafood)', 'Unit', 120.00, 3, 'served', '2025-10-11 16:32:16'),
(7, '103', 'Food', 'Mami', 'Unit', 70.00, 1, 'served', '2025-10-11 16:32:22'),
(8, '104', 'Food', 'Lomi', 'Small', 120.00, 2, 'pending', '2025-10-11 16:41:01'),
(9, '104', 'Non-Food', 'Toothbrush with Toothpaste', 'Unit', 25.00, 1, 'pending', '2025-10-11 16:41:10'),
(10, '101', 'Food', 'Lomi', 'Small', 60.00, 1, 'served', '2025-10-12 04:24:01'),
(11, '101', 'Food', 'Mami', 'Unit', 70.00, 1, 'served', '2025-10-12 04:24:08'),
(12, '102', 'Food', 'Nissin Cup (Chicken)', 'Unit', 400.00, 10, 'served', '2025-10-12 04:26:21'),
(13, '101', 'Food', 'Mami', 'Unit', 70.00, 1, 'served', '2025-10-22 05:00:13'),
(14, '101', 'Food', 'Lomi', 'Small', 120.00, 2, 'served', '2025-10-22 05:00:13'),
(15, '101', 'Food', 'Nissin Cup (Spicy Seafood)', 'Unit', 80.00, 2, 'served', '2025-10-22 08:47:02'),
(16, '101', 'Food', 'Nissin Cup (Beef)', 'Unit', 40.00, 1, 'served', '2025-10-22 08:47:02'),
(17, '101', 'Food', 'Mami', 'Unit', 140.00, 2, 'served', '2025-10-22 08:47:46'),
(18, '101', 'Food', 'Nissin Cup (Spicy Seafood)', 'Unit', 40.00, 1, 'served', '2025-10-22 08:48:36'),
(19, '101', 'Food', 'Nissin Cup (Chicken)', 'Unit', 40.00, 1, 'served', '2025-10-22 08:48:36'),
(20, '101', 'Food', 'Lomi', 'Small', 60.00, 1, 'served', '2025-10-22 08:48:37'),
(21, '101', 'Food', 'Nissin Cup (Beef)', 'Unit', 40.00, 1, 'served', '2025-10-22 08:48:37'),
(22, '101', 'Food', 'Mami', 'Unit', 70.00, 1, 'served', '2025-10-22 08:48:37'),
(23, '101', 'Food', 'Bottled Water (500ml)', 'Unit', 50.00, 2, 'served', '2025-10-22 14:10:57'),
(24, '101', 'Food', 'Coke Mismo', 'Unit', 25.00, 1, 'served', '2025-10-22 14:15:43'),
(25, '101', 'Food', 'Nissin Cup (Beef)', NULL, 80.00, 2, 'served', '2025-10-22 15:39:57'),
(26, '102', 'Food', 'Tapa', NULL, 100.00, 1, 'served', '2025-10-22 15:47:51'),
(27, '102', 'Food', 'Hotdog', NULL, 100.00, 1, 'served', '2025-10-22 15:47:51');

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

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `payment_date`, `amount`, `payment_mode`) VALUES
(1, '2025-10-13 22:44:17', 100.00, 'cash'),
(2, '2025-10-13 22:44:55', 20.00, 'cash'),
(3, '2025-10-13 22:46:47', 100.00, 'cash'),
(4, '2025-10-13 23:07:47', 20.00, 'cash'),
(5, '2025-10-13 23:17:23', 100.00, 'cash'),
(6, '2025-10-13 23:17:37', 20.00, 'cash'),
(7, '2025-10-13 23:21:50', 50.00, 'cash'),
(8, '2025-10-13 23:22:05', 70.00, 'cash'),
(9, '2025-10-13 23:25:30', 120.00, 'cash'),
(10, '2025-10-13 23:32:24', 120.00, 'cash'),
(11, '2025-10-13 23:35:37', 140.00, 'cash'),
(12, '2025-10-13 23:36:18', 100.00, 'cash'),
(13, '2025-10-13 23:41:41', 100.00, 'cash'),
(14, '2025-10-13 23:41:49', 20.00, 'cash'),
(15, '2025-10-13 23:45:39', 100.00, 'cash'),
(16, '2025-10-13 23:46:08', 20.00, 'cash'),
(17, '2025-10-13 23:51:01', 20.00, 'cash'),
(18, '2025-10-13 23:51:10', 100.00, 'cash'),
(19, '2025-10-14 00:33:02', 140.00, 'cash'),
(20, '2025-10-14 00:33:25', 100.00, 'cash'),
(21, '2025-10-14 00:34:35', 100.00, 'cash'),
(22, '2025-10-14 00:34:53', 20.00, 'cash'),
(23, '2025-10-14 00:37:12', 100.00, 'cash'),
(24, '2025-10-14 00:37:23', 20.00, 'cash');

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
(1, 101, 'standard_room', 'booked', '2025-04-26 16:34:08', '2025-10-22 16:47:52', 400.00, 750.00, 1100.00, 1500.00, 120.00),
(2, 102, 'standard_room', 'booked', '2025-04-26 17:37:56', '2025-10-22 15:44:03', 400.00, 750.00, 1100.00, 1500.00, 120.00),
(10, 103, 'standard_room', 'available', '2025-04-28 16:01:31', '2025-10-22 04:59:03', 300.00, 750.00, 1100.00, 1500.00, 120.00),
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
(19, 'Mami', 70.00, 1, 'Food', '2025-10-22 13:18:21'),
(20, 'Nissin Cup (Beef)', 40.00, 24, 'Food', '2025-10-22 13:20:40'),
(21, 'Nissin Cup (Chicken)', 40.00, 24, 'Food', '2025-10-22 13:21:39'),
(22, 'Nissin Cup (Spicy Seafood)', 40.00, 24, 'Food', '2025-10-22 13:21:53'),
(23, 'Longganisa', 100.00, 1, 'Food', '2025-10-22 13:24:23'),
(24, 'Sisig', 100.00, 1, 'Food', '2025-10-22 13:24:52'),
(25, 'Bopis', 100.00, 1, 'Food', '2025-10-22 13:25:05'),
(26, 'Tocino', 100.00, 1, 'Food', '2025-10-22 13:25:17'),
(27, 'Tapa', 100.00, 1, 'Food', '2025-10-22 13:25:29'),
(28, 'Hotdog', 100.00, 1, 'Food', '2025-10-22 13:25:39'),
(29, 'Dinuguan', 115.00, 1, 'Food', '2025-10-22 13:25:51'),
(30, 'Chicken Adobo', 120.00, 1, 'Food', '2025-10-22 13:26:02'),
(31, 'Bicol Express', 125.00, 1, 'Food', '2025-10-22 13:26:12'),
(32, 'Chicharon', 60.00, 1, 'Food', '2025-10-22 13:27:04'),
(33, 'Chicken Skin', 60.00, 1, 'Food', '2025-10-22 13:27:15'),
(34, 'Shanghai (3pcs)', 40.00, 1, 'Food', '2025-10-22 13:27:43'),
(35, 'Gulay (3pcs)', 40.00, 1, 'Food', '2025-10-22 13:28:00'),
(36, 'Toge (4pcs)', 40.00, 1, 'Food', '2025-10-22 13:28:11'),
(37, 'French Fries (BBQ)', 40.00, 1, 'Food', '2025-10-22 13:28:33'),
(38, 'French Fries (Sour Cream)', 40.00, 1, 'Food', '2025-10-22 13:28:42'),
(39, 'French Fries (Cheese)', 40.00, 1, 'Food', '2025-10-22 13:28:55'),
(40, 'Cheese Sticks (12pcs)', 30.00, 1, 'Food', '2025-10-22 13:29:05'),
(41, 'Tinapay (3pcs)', 20.00, 1, 'Food', '2025-10-22 13:29:14'),
(42, 'Tinapay with Spread (3pcs)', 30.00, 1, 'Food', '2025-10-22 13:29:24'),
(43, 'Burger Regular', 35.00, 1, 'Food', '2025-10-22 13:29:35'),
(44, 'Burger with Cheese', 40.00, 1, 'Food', '2025-10-22 13:29:44'),
(45, 'Nagaraya Butter Yellow (Small)', 20.00, 1, 'Food', '2025-10-22 13:29:53'),
(46, 'Nova Country Cheddar (Small)', 25.00, 1, 'Food', '2025-10-22 13:30:03'),
(47, 'Bottled Water (500ml)', 25.00, 24, 'Food', '2025-10-22 13:30:12'),
(48, 'Purified Hot Water Only (Mug)', 10.00, 1, 'Food', '2025-10-22 13:30:23'),
(49, 'Ice Bucket', 40.00, 1, 'Food', '2025-10-22 13:30:33'),
(50, 'Coke Mismo', 25.00, 24, 'Food', '2025-10-22 13:31:23'),
(51, 'Royal Mismo', 25.00, 25, 'Food', '2025-10-22 13:31:32'),
(52, 'Sting Energy Drink', 25.00, 24, 'Food', '2025-10-22 13:31:57'),
(53, 'Dragon Fruit', 70.00, 1, 'Food', '2025-10-22 13:32:33'),
(54, 'Mango', 70.00, 1, 'Food', '2025-10-22 13:32:47'),
(55, 'Cucumber', 70.00, 1, 'Food', '2025-10-22 13:32:57'),
(56, 'Avocado', 70.00, 1, 'Food', '2025-10-22 13:33:08'),
(57, 'Chocolate', 40.00, 1, 'Food', '2025-10-22 13:33:20'),
(58, 'Taro', 40.00, 1, 'Food', '2025-10-22 13:33:32'),
(59, 'Ube', 40.00, 1, 'Food', '2025-10-22 13:33:54'),
(60, 'Strawberry', 40.00, 1, 'Food', '2025-10-22 13:34:04'),
(61, 'Pineapple Juice', 60.00, 1, 'Food', '2025-10-22 13:34:22'),
(62, 'Instant Coffee', 25.00, 1, 'Food', '2025-10-22 13:34:39'),
(63, 'Brewed Coffee', 45.00, 1, 'Food', '2025-10-22 13:34:50'),
(64, 'Hot Tea (Green)', 25.00, 1, 'Food', '2025-10-22 13:35:00'),
(65, 'Hot Tea (Black)', 25.00, 1, 'Food', '2025-10-22 13:35:10'),
(66, 'Milo Hot Chocolate Drink', 25.00, 1, 'Food', '2025-10-22 13:35:21'),
(67, 'Face Mask Disposable', 5.00, 24, 'Cleaning', '2025-10-22 13:35:46'),
(68, 'Toothbrush with Toothpaste', 25.00, 20, 'Cleaning', '2025-10-22 13:36:10'),
(69, 'Colgate Toothpaste', 20.00, 24, 'Cleaning', '2025-10-22 13:36:38'),
(70, 'Modess All Night Extra Long Pad', 20.00, 24, 'Cleaning', '2025-10-22 13:36:51'),
(71, 'Sunsilk', 15.00, 24, 'Cleaning', '2025-10-22 13:37:04'),
(72, 'Creamsilk Shampoo', 15.00, 24, 'Cleaning', '2025-10-22 13:37:14'),
(73, 'Palmolive Anti-Dandruff', 15.00, 24, 'Cleaning', '2025-10-22 13:37:26'),
(74, 'Dove', 15.00, 24, 'Cleaning', '2025-10-22 13:37:41'),
(75, 'Empress Keratin', 15.00, 24, 'Cleaning', '2025-10-22 13:37:52'),
(76, 'Creamsilk Conditioner', 15.00, 24, 'Cleaning', '2025-10-22 13:38:02'),
(77, 'Trust Condom (3pcs)', 60.00, 24, 'Cleaning', '2025-10-22 13:38:18'),
(78, 'Disposable Spoon', 2.00, 24, 'Cleaning', '2025-10-22 13:38:41'),
(79, 'Disposable Fork', 2.00, 24, 'Cleaning', '2025-10-22 13:38:53');

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
(21, 'Makima', 'makima', 'makima@gmail.com', '$2y$10$iWMMQr/lEHmycuWaujWSGOxt9qp1qsl1wpsl5iQ5qoV.P2dC6atH2', 'Receptionist', 'approved', NULL, NULL);

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `checkins`
--
ALTER TABLE `checkins`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

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
