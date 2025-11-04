-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 03, 2025 at 08:01 PM
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
  `cancelled_by` int DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `guest_name`, `email`, `address`, `telephone`, `age`, `num_people`, `room_number`, `duration`, `payment_mode`, `reference_number`, `booking_token`, `amount_paid`, `total_price`, `change_amount`, `start_date`, `end_date`, `status`, `created_at`, `cancellation_reason`, `cancelled_by`, `cancelled_at`) VALUES
(9, 'Aldrick Dulnuan', NULL, 'Calamba City, Laguna', '09123456789', 21, NULL, '101', '6', 'Cash', '', NULL, 750, 750, NULL, '2025-08-06 13:00:00', '2025-08-06 19:00:00', 'completed', '2025-08-08 11:11:18', NULL, NULL, NULL),
(10, 'Maria', 'calopez@ccc.edu.ph', 'Calamba City, Laguna', '09123456789', 21, 1, '103', '12', '0', '12456542566', 'BK202508083EAAF8', 1100, 1100, 0, '2025-08-08 20:00:00', '2025-08-09 08:00:00', 'cancelled', '2025-08-08 11:14:56', 'The guest didn\'t go at the right time and date', NULL, '2025-08-12 17:07:23'),
(13, 'elvin', 'erreyes@ccc.edu.ph', 'halang', '09761090017', 51, 1, '101', '48', 'Cash', '', 'BK20250811F04207', 130, 120, 10, '2025-08-12 15:15:00', '2025-08-14 15:15:00', 'cancelled', '2025-08-11 07:17:35', 'Didn\'t go at the right time', NULL, '2025-08-12 17:19:59'),
(17, 'Adobe Premiere ', 'adobe@gmail.com', 'Brgy. Lawa', '09165770822', 18, 1, '101', '3', 'Cash', '', 'BK202510066DDB35', 400, 400, 0, '2025-10-07 00:35:00', '2025-10-07 03:35:00', 'cancelled', '2025-10-06 16:36:06', 'Automatically cancelled - Guest did not check in within 30 minutes of designated time', NULL, '2025-10-07 22:37:16'),
(18, 'Zayn', 'kukuhuskar82@gmail.com', '123 Main St.', '09165770987', 18, 1, '101', '3', '0', '', 'BK20251018D32B00', 400, 400, 0, '2025-10-18 21:15:00', '2025-10-19 00:15:00', 'completed', '2025-10-18 13:15:43', NULL, NULL, NULL),
(19, 'Zayn', 'kukuhuskar82@gmail.com', '123 Main St.', '09165770987', 18, 1, '101', '3', '0', '', 'BK202510181629DA', 400, 400, 0, '2025-10-18 21:15:00', '2025-10-19 00:15:00', 'completed', '2025-10-18 13:33:09', NULL, NULL, NULL),
(20, 'Zayn', 'kukuhuskar82@gmail.com', '123 Main St.', '09165770987', 18, 1, '101', '3', '0', '', 'BK202510187FBD8C', 400, 400, 0, '2025-10-18 21:15:00', '2025-10-19 00:15:00', 'completed', '2025-10-18 13:33:17', NULL, NULL, NULL),
(21, 'Bazz', 'kukuhuskar82@gmail.com', '123 Main St.', '09165770822', 22, 1, '101', '3', '0', '', 'BK20251018303620', 400, 400, 0, '2025-10-18 21:33:00', '2025-10-19 00:33:00', 'completed', '2025-10-18 13:34:04', NULL, NULL, NULL),
(22, 'Toji', 'kukuhuskar82@gmail.com', 'St 123 Main Street ', '09165770822', 18, 1, '101', '3', '0', '', 'BK202510217A6C86', 400, 400, 0, '2025-10-21 21:19:00', '2025-10-22 00:19:00', 'completed', '2025-10-21 13:19:53', NULL, NULL, NULL),
(23, 'Mark', 'kukuhuskar82@gmail.com', 'St 123 Main Street ', '09165770822', 20, 1, '101', '3', '0', '', 'BK20251021C1CB60', 400, 400, 0, '2025-10-21 21:24:00', '2025-10-22 00:24:00', 'completed', '2025-10-21 13:24:28', NULL, NULL, NULL),
(24, 'Test', 'lopezcyeanne0318@gmail.com', 'St 123 Main Street ', '09165770827', 18, 1, '101', '3', '0', '', 'BK2025102205F1D2', 400, 400, 0, '2025-10-22 12:42:00', '2025-10-22 15:42:00', 'completed', '2025-10-22 04:47:20', NULL, NULL, NULL),
(25, 'Adobong Paksiw', 'kukuhuskar82@gmail.com', 'St 123 Main Street ', '0916-577-0822', 23, 0, '101', '3', '0', '', 'BK20251022E28B1E', 0, 400, 0, '2025-10-22 20:51:00', '2025-10-22 23:51:00', 'completed', '2025-10-22 10:51:11', NULL, NULL, NULL),
(26, 'Leon', 'webc26696@gmail.com', 'St 123 Main Street ', '0916-577-0822', 18, 0, '101', '3', '0', '', 'BK20251023B1AC3A', 0, 400, 0, '2025-10-23 14:57:00', '2025-10-23 17:57:00', 'completed', '2025-10-23 06:57:53', NULL, NULL, NULL),
(28, 'DomengKite', 'kukuhuskar82@gmail.com', 'St 123 Main Street ', '0916-577-0822', 18, 0, '101', '3', '0', '', 'BK2025102605180F', 0, 400, 0, '2025-10-26 17:00:00', '2025-10-26 20:00:00', 'completed', '2025-10-26 09:00:54', NULL, NULL, NULL),
(29, 'Yu Zhong', 'webc26696@gmail.com', 'St 123 Main Street ', '0916-577-0822', 18, 0, '103', '3', '0', '', 'BK2025102694A719', 0, 300, 0, '2025-10-26 17:25:00', '2025-10-26 20:25:00', 'completed', '2025-10-26 09:25:48', 'Test', 2, '2025-10-26 17:27:02'),
(30, 'Lancelot', 'aangelo1236@gmail.com', 'St 123 Main Street ', '0916-577-0822', 18, 1, '101', '3', 'Cash', '', 'BK202510266CE494', 400, 400, 0, '2025-10-26 21:28:00', '2025-10-27 00:28:00', 'cancelled', '2025-10-26 09:28:38', 'sdsads', 2, '2025-10-28 23:29:03'),
(31, 'Navia', 'kukuhuskar82@gmail.com', 'St 123 Main Street ', '0916-577-0827', 18, 0, '102', '6', '0', '', 'BK202510284A402F', 0, 750, 0, '2025-10-28 23:37:00', '2025-10-29 05:37:00', 'completed', '2025-10-28 15:37:35', NULL, NULL, NULL),
(32, 'Rafael', 'aangelo1236@gmail.com', 'St 123 Main Street ', '0916-577-0827', 18, 0, '103', '6', '0', '', 'BK20251028CBFB51', 0, 750, 0, '2025-10-28 23:38:00', '2025-10-29 05:38:00', 'completed', '2025-10-28 15:38:45', NULL, NULL, NULL),
(33, 'Nefer', 'webc26696@gmail.com', 'St 123 Main Street ', '0916-577-0827', 19, 0, '102', '3', '0', '', 'BK202510288AC53C', 0, 400, 0, '2025-10-29 07:48:00', '2025-10-29 10:48:00', 'cancelled', '2025-10-28 15:49:52', 'Test', 2, '2025-10-29 00:00:46'),
(34, 'Raiden', 'gi366317@gmail.com', 'St 123 Main Street ', '0916-577-0822', 22, 0, '104', '3', '0', '', 'BK20251028AE3FC5', 0, 400, 0, '2025-10-29 15:52:00', '2025-10-29 18:52:00', 'cancelled', '2025-10-28 15:52:56', 'Test', 2, '2025-10-28 23:54:54'),
(35, 'Zigs', 'gitarraapartelle@gmail.com', 'St 123 Main Street ', '0916-577-0822', 22, 0, '103', '3', '0', '', 'BK2025102852338B', 0, 300, 0, '2025-10-29 00:12:00', '2025-10-29 03:12:00', 'cancelled', '2025-10-28 16:13:08', 'test', 2, '2025-10-29 00:27:59'),
(36, 'Andrei', 'gitarraapartelle@gmail.com', 'St 123 Main Street ', '0916-577-0822', 22, 0, '104', '3', '0', '', 'BK20251028E7CE48', 0, 400, 0, '2025-10-29 00:29:00', '2025-10-29 03:29:00', 'cancelled', '2025-10-28 16:29:29', 'Test Reason', 2, '2025-10-29 00:29:58'),
(37, 'Sample Sample', 'kukuhuskar82@gmail.com', 'St 123 Main Street ', '0916-577-0827', 22, 2, '102', '3', 'GCash', '3232434324442', 'BK202510305BE5C4', 400, 400, 0, '2025-10-31 04:24:00', '2025-10-31 07:24:00', 'cancelled', '2025-10-30 16:26:13', 'Cancel', 2, '2025-10-31 00:26:39'),
(38, 'Glamour', 'kukuhuskar82@gmail.com', 'St 123 Main Street ', '0916-577-0822', 22, 2, '101', '3', 'GCash', '4342323434434', 'BK20251030ECDF40', 400, 400, 0, '2025-10-31 04:42:00', '2025-10-31 07:42:00', 'cancelled', '2025-10-30 16:43:42', 'Cancel', 2, '2025-10-31 00:44:03'),
(39, 'Guest', 'kukuhuskar82@gmail.com', 'St 123 Main Street ', '0916-577-0822', 18, 2, '101', '3', 'GCash', '4343443434344', 'BK202511011C9297', 400, 400, 0, '2025-11-03 00:03:00', '2025-11-03 03:03:00', 'cancelled', '2025-11-01 16:04:17', NULL, NULL, NULL);

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
  `receptionist_id` int DEFAULT NULL,
  `last_modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `previous_charges` decimal(10,2) DEFAULT '0.00',
  `rebooked_from` int DEFAULT NULL,
  `is_rebooked` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `checkins`
--

INSERT INTO `checkins` (`id`, `guest_name`, `address`, `telephone`, `room_number`, `room_type`, `stay_duration`, `total_price`, `amount_paid`, `change_amount`, `payment_mode`, `check_in_date`, `check_out_date`, `status`, `gcash_reference`, `receptionist_id`, `last_modified`, `previous_charges`, `rebooked_from`, `is_rebooked`) VALUES
(70, 'Joji', 'Brgy. Lawa', '+639483743434', 101, '', 3, 400.00, 400.00, 400.00, 'gcash', '2025-10-12 12:23:39', '2025-10-12 15:23:39', 'checked_out', '31321323133', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(71, 'OZI', 'BLK 5, LOT5, MALIGAYA VILLAGE, BRGY. LAWA, CALAMBA CITY, LAGUNA', '+639165770829', 102, '', 3, 400.00, 400.00, 400.00, 'cash', '2025-10-12 12:25:59', '2025-10-12 15:25:59', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(74, 'Arthur Lewin', 'St 123 Main Street', '+639165770822', 101, '', 3, 400.00, 400.00, 400.00, 'cash', '2025-10-13 19:12:00', '2025-10-13 19:12:11', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(75, 'Erza Scarlet', 'St 123 Main Street', '+639437482374', 101, '', 3, 400.00, 400.00, 400.00, 'cash', '2025-10-13 19:12:39', '2025-10-13 22:12:39', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(76, 'Magenta', 'St 123 Main Street', '+639165770822', 101, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-13 22:20:42', '2025-10-13 22:44:56', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(77, 'Kamado Tanjiro', 'St 123 Main Street', '+639165770822', 101, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-13 22:46:20', '2025-10-13 23:07:48', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(78, 'DING DONG', 'St 123 Main Street', '+639437482374', 101, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-13 23:16:30', '2025-10-13 23:17:37', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(79, 'Aqua', 'St 123 Main Street', '+639165770829', 101, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-13 23:21:34', '2025-10-13 23:22:05', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(80, 'Balmond', 'St 123 Main Street', '+639437482374', 101, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-13 23:25:15', '2025-10-13 23:25:30', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(81, 'Maria', 'St 123 Main Street', '+639165770822', 101, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-13 23:32:10', '2025-10-13 23:32:24', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(82, 'King', 'St 123 Main Street', '+639165770822', 101, '', 5, 640.00, 640.00, 0.00, 'cash', '2025-10-13 23:35:06', '2025-10-13 23:36:18', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(83, 'Macarov', 'St 123 Main Street', '+639165770822', 101, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-13 23:41:25', '2025-10-13 23:44:56', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(84, 'Fred', 'St 123 Main Street', '+639165770822', 102, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-13 23:45:26', '2025-10-13 23:50:28', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(85, 'Karina', 'St 123 Main Street', '+639165770822', 103, '', 4, 420.00, 420.00, 0.00, 'cash', '2025-10-13 23:50:49', '2025-10-13 23:51:10', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(86, 'UI', 'St 123 Main Street', '+639437482374', 103, '', 5, 540.00, 540.00, 0.00, 'cash', '2025-10-13 23:52:47', '2025-10-14 00:33:25', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(87, 'Cath', 'St 123 Main Street', '+639165770822', 102, '', 3, 520.00, 520.00, 0.00, 'cash', '2025-10-14 00:34:13', '2025-10-14 00:34:53', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(88, 'Binch', 'Brgy. Barandal', '+639165770822', 101, '', 4, 520.00, 520.00, 0.00, 'cash', '2025-10-14 00:36:36', '2025-10-14 00:37:23', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(89, 'Camel', 'St 123 Main Street', '+639165770822', 103, '', 3, 300.00, 300.00, 300.00, 'cash', '2025-10-21 21:36:10', '2025-10-22 00:36:10', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(90, 'Giniling na lumpia', 'St 123 Main Street', '+639165770822', 101, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-22 12:59:21', '2025-10-22 15:59:21', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(91, 'Godzilla', 'St 123 Main Street', '+639165770822', 101, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-22 16:43:34', '2025-10-22 19:43:34', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(92, 'Adobong Paksiw', 'St 123 Main Street', '+639165770822', 101, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-22 21:12:08', '2025-10-23 00:12:08', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(93, 'Guest Avelgest', 'St 123 Main Street', '+639437482374', 102, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-22 23:44:03', '2025-10-23 02:44:03', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(94, 'Everest', 'St 123 Main Street', '+639437482374', 101, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-23 00:47:52', '2025-10-23 03:47:52', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(95, 'Leon', 'St 123 Main Street', '+639165770829', 101, '', 3, 400.00, 400.00, 0.00, 'gcash', '2025-10-23 15:01:11', '2025-10-23 18:01:11', 'checked_out', '31321323133', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(96, 'Arlott', 'St 123 Main Street', '+639165770822', 102, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-26 16:42:22', '2025-10-26 19:42:22', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(97, 'DomengKite', 'St 123 Main Street', '+639165770822', 101, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-26 17:02:37', '2025-10-26 20:02:37', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(98, 'Yu Zhong', 'St 123 Main Street', '+639165770822', 103, '', 3, 300.00, 400.00, 100.00, 'cash', '2025-10-26 17:26:45', '2025-10-26 20:26:45', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(99, 'Silver Surfer', 'St 123 Main Street', '+639165770821', 101, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-28 22:34:29', '2025-10-29 01:34:29', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(100, 'Navia', 'St 123 Main Street', '+639165770822', 102, '', 6, 750.00, 750.00, 0.00, 'cash', '2025-10-28 23:39:41', '2025-10-29 05:39:41', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(101, 'Rafael', 'St 123 Main Street', '+639165770829', 103, '', 3, 300.00, 400.00, 100.00, 'cash', '2025-10-28 23:44:27', '2025-10-29 00:06:50', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(102, 'Izagani', 'St 123 Main Street', '+639165770823', 101, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-29 18:28:51', '2025-10-29 21:28:51', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(103, 'Sample', 'St 123 Main Street', '+639165770822', 101, '', 3, 400.00, 400.00, 0.00, 'gcash', '2025-10-30 23:38:41', '2025-10-31 02:38:41', 'checked_out', '12345678910', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(105, 'Leo', 'St 123 Main Street', '+639437482374', 102, '', 3, 400.00, 400.00, 0.00, 'gcash', '2025-10-31 00:06:31', '2025-10-31 03:06:31', 'checked_out', '3345354365646', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(106, 'Sample Sample', 'St 123 Main Street', '+639165770821', 101, '', 4, 520.00, 400.00, 0.00, 'gcash', '2025-10-31 15:24:21', '2025-10-31 19:24:21', 'checked_out', '2321321323213', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(107, 'Bane Jane', 'St 123 Main Street', '+639165770821', 101, '', 4, 520.00, 400.00, 0.00, 'gcash', '2025-10-31 22:04:05', '2025-11-01 02:04:05', 'checked_out', '2432424324324', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(108, 'uniso', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-31 18:22:00', '2025-10-31 21:22:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(109, 'Rebooking Test', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-31 18:30:00', '2025-10-31 21:30:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(110, 'Rebook', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-31 18:37:00', '2025-10-31 21:37:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(111, 'Nukue', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-31 18:46:00', '2025-10-31 21:46:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(112, 'SpongeBob', 'Bikini Bottom', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-31 18:50:00', '2025-10-31 21:50:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(113, 'Nodsa', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-31 19:02:00', '2025-10-31 22:02:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(114, 'Eren Yeager', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-10-31 19:08:00', '2025-10-31 22:08:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(115, 'cooked', 'St 123 Main Street', '+639165770821', 101, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 03:19:03', '2025-11-01 06:19:03', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(116, 'Gigz', 'St 123 Main Street', '+639165770821', 102, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 04:48:26', '2025-11-01 04:48:34', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(117, 'Shalla Bal', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 00:12:00', '2025-11-01 03:12:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(118, 'Testing', 'St 123 Main Street', '+639165770822', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 00:16:00', '2025-11-01 03:16:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(119, 'fdffddassdasddsadsa', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 409.00, 9.00, 'cash', '2025-11-01 00:19:00', '2025-11-01 03:19:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(120, 'dsadda', 'St 123 Main Street', '+639165770822', 103, 'standard_room', 3, 300.00, 400.00, 100.00, 'cash', '2025-11-01 00:15:00', '2025-11-01 03:15:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(121, 'rebooking', 'St 123 Main Street', '+639165770821', 103, 'standard_room', 3, 300.00, 400.00, 100.00, 'cash', '2025-11-04 12:21:00', '2025-11-01 19:23:04', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(122, 'transfer', 'St 123 Main Street', '+639165770822', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 11:25:00', '2025-11-01 14:25:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(123, 'rebooking test', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 02:27:00', '2025-11-01 05:27:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(124, 'gumana kana plsss', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 03:30:00', '2025-11-01 06:30:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(125, 'Choujin', 'St 123 Main Street', '+639165770822', 104, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 03:35:00', '2025-11-01 06:35:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(126, 'Sora', 'St 123 Main Street', '+639165770821', 104, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 03:35:00', '2025-11-01 06:35:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(127, 'gitarra apartelle', 'St 123 Main Street', '+639165770821', 103, 'standard_room', 6, 750.00, 750.00, 0.00, 'cash', '2025-11-01 07:37:00', '2025-11-01 13:37:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(128, 'paksiw', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 11:45:00', '2025-11-01 14:45:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(129, 'Fern', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 11:54:00', '2025-11-01 14:54:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(130, 'Denji', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 11:58:00', '2025-11-01 14:58:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(131, 'Makima', 'St 123 Main Street', '+639165770821', 103, 'standard_room', 3, 300.00, 400.00, 100.00, 'cash', '2025-11-01 11:59:00', '2025-11-01 14:59:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(132, 'same room', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 12:01:00', '2025-11-01 15:01:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(133, 'Marvis', 'St 123 Main Street', '+639165770822', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 12:06:00', '2025-11-01 15:06:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(134, 'Power', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 12:15:00', '2025-11-01 15:15:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(135, 'test test test', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 12:20:00', '2025-11-01 15:20:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(136, 'Roxanne', 'St 123 Main Street', '+639165770822', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 12:22:00', '2025-11-01 15:22:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(137, 'Gelow', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 15:40:00', '2025-11-01 18:40:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(138, 'Bane', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 15:47:00', '2025-11-01 18:47:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(139, 'Chase Atlantic', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 15:50:00', '2025-11-01 18:50:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(140, 'Bane', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 15:53:00', '2025-11-01 18:53:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(141, 'hahaha', 'St 123 Main Street', '+639165770822', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 16:01:00', '2025-11-01 19:01:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(142, 'Rebookbok', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 16:07:00', '2025-11-01 19:07:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(143, 'Check', 'St 123 Main Street', '+639165770821', 103, 'standard_room', 3, 300.00, 400.00, 100.00, 'cash', '2025-11-01 16:15:00', '2025-11-01 19:15:00', 'checked_out', '', 2, '2025-11-01 16:24:32', 0.00, NULL, 0),
(144, 'Binz', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 02:59:00', '2025-11-01 05:59:00', 'checked_out', '', 2, '2025-11-01 16:32:35', 0.00, NULL, 0),
(145, 'Anna', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 16:37:00', '2025-11-01 19:37:00', 'checked_out', '', 2, '2025-11-01 16:43:36', 0.00, NULL, 0),
(146, 'DADA', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 15:44:00', '2025-11-01 18:44:00', 'checked_out', '', 2, '2025-11-01 16:47:21', 0.00, NULL, 0),
(147, 'HEHEHE', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 03:55:00', '2025-11-01 06:55:00', 'checked_out', '', 2, '2025-11-01 17:04:53', 0.00, NULL, 0),
(148, 'Bossing', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 04:13:00', '2025-11-01 07:13:00', 'checked_out', '', 2, '2025-11-01 17:13:54', 0.00, NULL, 0),
(149, 'DeepSeek', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 05:20:00', '2025-11-01 08:20:00', 'checked_out', '', 2, '2025-11-01 17:20:53', 0.00, NULL, 0),
(150, 'GEGE', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 17:29:00', '2025-11-01 20:29:00', 'checked_out', '', 2, '2025-11-01 17:43:45', 0.00, NULL, 0),
(151, 'Ress', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 17:56:00', '2025-11-01 20:56:00', 'checked_out', '', 2, '2025-11-01 18:02:38', 0.00, NULL, 0),
(152, 'DADADADA', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 18:03:00', '2025-11-01 21:03:00', 'checked_out', '', 2, '2025-11-01 18:09:10', 0.00, NULL, 0),
(153, 'Karina', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 18:09:00', '2025-11-01 21:09:00', 'checked_out', '', 2, '2025-11-01 18:24:22', 400.00, 153, 1),
(154, 'gsggsfsd', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 18:25:00', '2025-11-01 21:25:00', 'checked_out', '', 2, '2025-11-01 18:33:16', 400.00, 154, 1),
(155, 'TEST', 'St 123 Main Street', '+639165770822', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-01 18:33:00', '2025-11-01 21:33:00', 'checked_out', '', 2, '2025-11-01 18:33:46', 400.00, 155, 1),
(156, 'PAGONG', 'St 123 Main Street', '+639165770821', 103, 'standard_room', 6, 750.00, 750.00, 0.00, 'cash', '2025-11-02 03:52:00', '2025-11-02 03:07:13', 'checked_out', '', 2, '2025-11-01 19:07:13', 300.00, 156, 1),
(157, 'ELEPANTE', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 5, 750.00, 750.00, 0.00, 'cash', '2025-11-02 04:06:00', '2025-11-02 09:06:00', 'checked_out', '', 2, '2025-11-02 08:54:13', 400.00, 157, 1),
(158, 'KABAYO', 'St 123 Main Street', '+639165770821', 101, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-02 03:15:20', '2025-11-02 06:15:20', 'checked_out', '', 2, '2025-11-02 08:54:13', 0.00, NULL, 0),
(159, 'Zayn', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-02 19:57:00', '2025-11-02 17:47:39', 'checked_out', '', 2, '2025-11-02 09:47:39', 400.00, 159, 1),
(160, 'BABA', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-02 20:24:00', '2025-11-02 17:47:37', 'checked_out', '', 2, '2025-11-02 09:47:37', 400.00, 160, 1),
(161, 'Gusion', 'St 123 Main Street', '+639165770821', 103, 'standard_room', 3, 300.00, 300.00, 0.00, 'cash', '2025-11-02 20:37:00', '2025-11-02 17:47:35', 'checked_out', '', 2, '2025-11-02 09:47:35', 300.00, 161, 1),
(162, 'Ryzen', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-02 20:49:00', '2025-11-02 18:45:34', 'checked_out', '', 2, '2025-11-02 10:45:34', 400.00, 162, 1),
(163, 'ticktock', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-02 21:15:00', '2025-11-02 18:45:31', 'checked_out', '', 2, '2025-11-02 10:45:31', 400.00, 163, 1),
(164, 'DITO SIM', 'St 123 Main Street', '+639165770821', 101, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-02 18:45:50', '2025-11-02 18:49:33', 'checked_out', '', 2, '2025-11-02 10:49:33', 0.00, NULL, 0),
(165, 'gdggdgdfg', 'St 123 Main Street', '+639165770821', 101, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-02 18:49:53', '2025-11-02 21:49:53', 'checked_out', '', 2, '2025-11-02 14:08:55', 0.00, NULL, 0),
(166, 'Pancit Canton', 'St 123 Main Street', '+639165770821', 102, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-02 19:53:55', '2025-11-02 22:53:55', 'checked_out', '', 2, '2025-11-02 17:41:18', 0.00, NULL, 0),
(167, 'hfdgfgd', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-03 11:41:00', '2025-11-03 14:41:00', 'checked_out', '', 2, '2025-11-03 07:18:46', 0.00, 167, 0),
(168, 'French Fries', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-03 07:02:00', '2025-11-03 10:02:00', 'checked_out', '', 2, '2025-11-03 07:18:46', 0.00, 168, 0),
(170, 'Tidal Wave', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 6, 400.00, 800.00, 0.00, 'cash', '2025-11-03 16:23:45', '2025-11-03 16:30:09', 'checked_out', '', 2, '2025-11-03 08:30:09', 400.00, 170, 1),
(171, 'Giyu', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 9, 400.00, 1200.00, 0.00, 'cash', '2025-11-03 16:30:29', '2025-11-03 22:08:37', 'checked_out', '', 2, '2025-11-03 14:08:37', 800.00, 171, 1),
(172, 'Makima', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 6, 400.00, 800.00, 0.00, 'cash', '2025-11-03 20:59:33', '2025-11-03 22:08:40', 'checked_out', '', 2, '2025-11-03 14:08:40', 400.00, 172, 1),
(173, 'Danji', 'St 123 Main Street', '+639165770821', 104, 'standard_room', 6, 400.00, 800.00, 0.00, 'cash', '2025-11-03 21:46:52', '2025-11-03 22:08:42', 'checked_out', '', 2, '2025-11-03 14:08:42', 400.00, 173, 1),
(174, 'Harvey', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 6, 400.00, 800.00, 0.00, 'cash', '2025-11-03 22:08:57', '2025-11-03 22:19:59', 'checked_out', '', 2, '2025-11-03 14:19:59', 400.00, 174, 1),
(175, 'Minnie', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 6, 400.00, 800.00, 0.00, 'cash', '2025-11-03 22:20:14', '2025-11-04 03:34:18', 'checked_out', '', 2, '2025-11-03 19:34:18', 400.00, 175, 1),
(176, 'Gabrielle', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 7, 520.00, 800.00, 0.00, 'cash', '2025-11-03 22:45:28', '2025-11-04 03:13:10', 'checked_out', '', 2, '2025-11-03 19:13:10', 400.00, 176, 1),
(177, 'Boa Hancock', 'St 123 Main Street', '+639165770821', 104, '', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-04 02:41:24', '2025-11-04 05:41:24', 'checked_out', '', 2, '2025-11-03 18:41:41', 0.00, NULL, 0),
(178, 'dsadadss', 'St 123 Main Street', '+639165770821', 103, 'standard_room', 6, 300.00, 700.00, 100.00, 'cash', '2025-11-04 02:44:37', '2025-11-04 03:13:07', 'checked_out', '', 2, '2025-11-03 19:13:07', 300.00, 178, 1),
(179, 'Hotdog', 'St 123 Main Street', '+639165770821', 104, 'standard_room', 3, 400.00, 800.00, 0.00, 'cash', '2025-11-04 09:11:00', '2025-11-04 03:13:02', 'checked_out', '', 2, '2025-11-03 19:13:02', 400.00, 179, 1),
(180, 'Coffee', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 800.00, 0.00, 'cash', '2025-11-04 09:13:00', '2025-11-04 03:14:38', 'checked_out', '', 2, '2025-11-03 19:14:38', 400.00, 180, 1),
(181, 'ddwesd', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 6, 400.00, 800.00, 0.00, 'cash', '2025-11-04 03:16:35', '2025-11-04 03:18:09', 'checked_out', '', 2, '2025-11-03 19:18:09', 400.00, 181, 1),
(182, 'Gitara', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 800.00, 0.00, 'cash', '2025-11-05 10:18:00', '2025-11-04 03:19:37', 'checked_out', '', 2, '2025-11-03 19:19:37', 400.00, 182, 1),
(183, 'Bugs', 'St 123 Main Street', '+639165770821', 101, 'standard_room', 6, 400.00, 800.00, 0.00, 'cash', '2025-11-04 03:34:35', '2025-11-04 09:34:35', 'checked_in', '', 2, '2025-11-03 19:34:47', 400.00, 183, 1),
(184, 'Code', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 3, 400.00, 800.00, 0.00, 'cash', '2025-11-04 09:35:00', '2025-11-04 03:49:20', 'checked_out', '', 2, '2025-11-03 19:49:20', 400.00, 184, 1),
(185, 'Feed', 'St 123 Main Street', '+639165770821', 102, 'standard_room', 6, 400.00, 800.00, 0.00, 'cash', '2025-11-04 03:49:34', '2025-11-04 09:49:34', 'checked_in', '', 2, '2025-11-03 19:49:47', 400.00, 185, 1);

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
(1, 101, NULL, NULL, '321A345D', '2025-10-05 10:16:23', '2025-11-01 02:04:05', 'expired'),
(2, 102, NULL, NULL, 'B7B78413', '2025-10-10 11:58:31', '2025-10-14 04:34:13', 'expired'),
(3, 103, NULL, NULL, '3021063D', '2025-10-10 11:58:36', '2025-10-14 04:52:47', 'expired'),
(4, 104, NULL, NULL, '0B5CE489', '2025-10-12 00:40:48', '2035-10-12 00:40:48', 'expired'),
(7, 103, NULL, NULL, '3021063D', '2025-11-01 19:15:14', '2025-11-01 03:15:00', 'expired'),
(8, 103, NULL, NULL, '3021063D', '2025-11-01 19:21:43', '2025-11-04 15:21:00', 'expired'),
(9, 102, NULL, NULL, 'B7B78413', '2025-11-01 19:25:47', '2025-11-01 14:25:00', 'expired'),
(10, 102, NULL, NULL, 'B7B78413', '2025-11-01 19:27:36', '2025-11-01 05:27:00', 'expired'),
(11, 101, NULL, NULL, '321A345D', '2025-11-01 19:30:58', '2025-11-01 06:30:00', 'expired'),
(12, 104, NULL, NULL, '0B5CE489', '2025-11-01 19:35:14', '2025-11-01 06:35:00', 'expired'),
(13, 104, NULL, NULL, '0B5CE489', '2025-11-01 19:36:04', '2025-11-01 06:35:00', 'expired'),
(14, 103, NULL, NULL, '3021063D', '2025-11-01 19:38:28', '2025-11-01 13:37:00', 'expired'),
(15, 102, NULL, NULL, 'B7B78413', '2025-11-01 19:46:03', '2025-11-01 14:45:00', 'expired'),
(16, 102, NULL, NULL, 'B7B78413', '2025-11-01 19:54:40', '2025-11-01 14:54:00', 'expired'),
(17, 102, NULL, NULL, 'B7B78413', '2025-11-01 19:58:57', '2025-11-01 14:58:00', 'expired'),
(18, 103, NULL, NULL, '3021063D', '2025-11-01 20:00:05', '2025-11-01 14:59:00', 'expired'),
(19, 101, NULL, NULL, '321A345D', '2025-11-01 20:01:31', '2025-11-01 15:01:00', 'expired'),
(20, 101, NULL, NULL, '321A345D', '2025-11-01 20:06:28', '2025-11-01 15:06:00', 'expired'),
(21, 101, NULL, NULL, '321A345D', '2025-11-01 20:15:35', '2025-11-01 15:15:00', 'expired'),
(22, 101, NULL, NULL, '321A345D', '2025-11-01 20:20:20', '2025-11-01 15:20:00', 'expired'),
(23, 101, NULL, NULL, '321A345D', '2025-11-01 20:22:25', '2025-11-01 15:22:00', 'expired'),
(24, 101, NULL, NULL, '321A345D', '2025-11-01 23:40:22', '2025-11-01 18:40:00', 'expired'),
(25, 101, NULL, NULL, '321A345D', '2025-11-01 23:47:11', '2025-11-01 18:47:00', 'expired'),
(26, 101, NULL, NULL, '321A345D', '2025-11-01 23:50:56', '2025-11-01 18:50:00', 'expired'),
(27, 101, NULL, NULL, '321A345D', '2025-11-01 23:53:53', '2025-11-01 18:53:00', 'expired'),
(28, 101, NULL, NULL, '321A345D', '2025-11-02 00:01:55', '2025-11-01 19:01:00', 'expired'),
(29, 102, NULL, NULL, 'C2985DCF', '2025-11-02 00:07:25', '2025-11-01 19:07:00', 'expired'),
(30, 103, NULL, NULL, 'B7E99169', '2025-11-02 00:15:58', '2025-11-01 19:15:00', 'expired'),
(31, 101, NULL, NULL, 'A643D6D3', '2025-11-02 00:25:18', '2025-11-01 19:25:00', 'expired'),
(32, 101, NULL, NULL, 'ED2E3CA6', '2025-11-02 00:26:41', '2025-11-01 22:58:00', 'expired'),
(33, 102, NULL, NULL, 'EA93E946', '2025-11-02 00:27:58', '2025-11-02 02:58:00', 'expired'),
(34, 102, NULL, NULL, 'D9EE435A', '2025-11-02 00:32:35', '2025-11-01 05:59:00', 'expired'),
(35, 102, NULL, NULL, '04A7E573', '2025-11-02 00:37:57', '2025-11-01 19:37:00', 'expired'),
(36, 101, NULL, NULL, 'FC72FADB', '2025-11-02 00:44:43', '2025-11-01 18:44:00', 'expired'),
(37, 101, NULL, NULL, '5D570E60', '2025-11-02 01:04:53', '2025-11-01 06:55:00', 'expired'),
(38, 101, NULL, NULL, 'A1E41147', '2025-11-02 01:13:54', '2025-11-01 07:13:00', 'expired'),
(39, 101, NULL, NULL, 'A92AB82C', '2025-11-02 01:20:53', '2025-11-01 08:20:00', 'expired'),
(40, 102, NULL, NULL, '3D247E3A', '2025-11-02 01:29:36', '2025-11-01 20:29:00', 'expired'),
(41, 102, NULL, NULL, '572F587B', '2025-11-02 01:56:29', '2025-11-01 20:56:00', 'expired'),
(42, 101, NULL, NULL, 'E0DCC2C1', '2025-11-02 02:03:10', '2025-11-01 21:03:00', 'expired'),
(43, 101, NULL, NULL, 'C8E4301B', '2025-11-02 02:09:34', '2025-11-01 21:09:00', 'expired'),
(44, 101, NULL, NULL, '074CEE6B', '2025-11-02 02:25:11', '2025-11-01 21:25:00', 'expired'),
(45, 101, NULL, NULL, 'E0933A6F', '2025-11-02 02:33:43', '2025-11-01 21:33:00', 'expired'),
(46, 103, NULL, NULL, 'A6128B91', '2025-11-02 02:52:49', '2025-11-02 09:52:00', 'expired'),
(47, 102, NULL, NULL, '6E67EAEA', '2025-11-02 03:06:49', '2025-11-02 09:06:00', 'expired'),
(48, 101, NULL, NULL, '9C1C1127', '2025-11-02 16:57:26', '2025-11-02 22:57:00', 'expired'),
(49, 102, NULL, NULL, '4BB0E9B7', '2025-11-02 17:24:39', '2025-11-02 23:24:00', 'expired'),
(50, 103, NULL, NULL, 'B9F9C68F', '2025-11-02 17:37:14', '2025-11-02 23:37:00', 'expired'),
(51, 102, NULL, NULL, '59DED484', '2025-11-02 18:16:06', '2025-11-03 00:15:00', 'expired'),
(52, 101, NULL, NULL, '0AFDB317', '2025-11-03 01:41:53', '2025-11-03 09:41:00', 'expired'),
(53, 101, NULL, NULL, '9163C5D4', '2025-11-03 01:47:54', '2025-11-03 14:41:00', 'expired'),
(54, 102, NULL, NULL, '9E759A35', '2025-11-03 02:02:38', '2025-11-03 10:02:00', 'expired'),
(55, 101, NULL, NULL, '4A5F1CCD', '2025-11-03 16:08:45', '2025-11-03 22:05:00', 'expired'),
(56, 102, NULL, NULL, '30011CCF', '2025-11-03 16:23:45', '2025-11-03 22:23:45', 'expired'),
(57, 101, NULL, NULL, '860E0B97', '2025-11-03 16:30:29', '2025-11-03 22:30:29', 'expired'),
(58, 101, NULL, NULL, '8E0AAAC5', '2025-11-03 16:30:29', '2025-11-04 01:30:29', 'expired'),
(59, 102, NULL, NULL, '6CBE6A27', '2025-11-03 20:59:33', '2025-11-04 02:59:33', 'expired'),
(60, 104, NULL, NULL, 'CD0A946B', '2025-11-03 21:46:52', '2025-11-04 03:46:52', 'expired'),
(61, 101, NULL, NULL, '21598DB5', '2025-11-03 22:08:57', '2025-11-04 04:08:57', 'expired'),
(62, 101, NULL, NULL, 'D7ABB44C', '2025-11-03 22:20:14', '2025-11-04 04:20:14', 'expired'),
(63, 102, NULL, NULL, 'EE4369AE', '2025-11-03 22:45:28', '2025-11-04 05:45:28', 'expired'),
(64, 103, NULL, NULL, '3DE70B15', '2025-11-04 02:44:37', '2025-11-04 08:44:37', 'expired'),
(65, 104, NULL, NULL, 'E1119877', '2025-11-04 09:11:00', '2025-11-04 12:11:00', 'expired'),
(66, 102, NULL, NULL, '4C794F51', '2025-11-04 09:13:00', '2025-11-04 12:13:00', 'expired'),
(67, 102, NULL, NULL, '3125E285', '2025-11-04 03:16:35', '2025-11-04 09:16:35', 'expired'),
(68, 102, NULL, NULL, 'ADF1875A', '2025-11-05 10:18:00', '2025-11-05 13:18:00', 'expired'),
(69, 101, NULL, NULL, 'FCFE7051', '2025-11-04 03:34:35', '2025-11-04 09:34:35', 'expired'),
(70, 102, NULL, NULL, '02D32CF2', '2025-11-04 09:35:00', '2025-11-04 12:35:00', 'expired'),
(71, 102, NULL, NULL, 'C161968A', '2025-11-04 03:49:34', '2025-11-04 09:49:34', 'expired');

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
  `status` enum('available','booked','occupied','maintenance') NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `price_3hrs` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_6hrs` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_12hrs` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_24hrs` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_ot` decimal(10,2) NOT NULL DEFAULT '0.00',
  `is_archived` tinyint(1) DEFAULT '0',
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_number`, `room_type`, `status`, `created_at`, `updated_at`, `price_3hrs`, `price_6hrs`, `price_12hrs`, `price_24hrs`, `price_ot`, `is_archived`, `archived_at`) VALUES
(1, 101, 'standard_room', 'booked', '2025-04-26 16:34:08', '2025-11-03 19:34:50', 400.00, 750.00, 1100.00, 1500.00, 120.00, 0, NULL),
(2, 102, 'standard_room', 'booked', '2025-04-26 17:37:56', '2025-11-03 19:49:54', 400.00, 750.00, 1100.00, 1500.00, 120.00, 0, NULL),
(10, 103, 'standard_room', 'available', '2025-04-28 16:01:31', '2025-11-03 19:13:07', 300.00, 750.00, 1100.00, 1500.00, 120.00, 0, NULL),
(12, 104, 'standard_room', 'available', '2025-04-29 13:29:43', '2025-11-03 19:13:02', 400.00, 750.00, 1100.00, 1500.00, 120.00, 0, NULL),
(13, 106, 'twin_room', 'available', '2025-05-07 06:15:36', '2025-10-11 07:28:10', 400.00, 750.00, 1100.00, 1500.00, 120.00, 0, NULL),
(18, 107, 'single', 'available', '2025-09-29 09:08:45', '2025-10-25 11:22:28', 400.00, 600.00, 1200.00, 1400.00, 120.00, 1, '2025-10-25 19:22:28'),
(20, 108, 'executive_room', 'maintenance', '2025-09-29 09:32:33', '2025-10-25 11:22:24', 400.00, 800.00, 1200.00, 1600.00, 120.00, 1, '2025-10-25 19:22:24');

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
  `category` enum('Food','Non-Food') NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_archived` tinyint(1) DEFAULT '0',
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `supplies`
--

INSERT INTO `supplies` (`id`, `name`, `price`, `quantity`, `category`, `image`, `created_at`, `is_archived`, `archived_at`) VALUES
(19, 'Mami', 70.00, 1, 'Food', 'uploads/supplies/supply_68ff37a894d9b3.97607039.jpg', '2025-10-22 13:18:21', 0, NULL),
(20, 'Nissin Cup (Beef)', 40.00, 24, 'Food', 'uploads/supplies/supply_68ff37fc4b04e0.63711444.png', '2025-10-22 13:20:40', 0, NULL),
(21, 'Nissin Cup (Chicken)', 40.00, 24, 'Food', 'uploads/supplies/supply_68ff3809aefe56.60252740.png', '2025-10-22 13:21:39', 0, NULL),
(22, 'Nissin Cup (Spicy Seafood)', 40.00, 24, 'Food', 'uploads/supplies/supply_68ff381ac28a38.07370695.png', '2025-10-22 13:21:53', 0, NULL),
(23, 'Longganisa', 100.00, 1, 'Food', 'uploads/supplies/supply_68ff37934b8021.21181346.jpg', '2025-10-22 13:24:23', 0, NULL),
(24, 'Sisig', 100.00, 1, 'Food', 'uploads/supplies/supply_68ff3974612648.05165579.jpg', '2025-10-22 13:24:52', 0, NULL),
(25, 'Bopis', 100.00, 1, 'Food', 'uploads/supplies/supply_68ff349aaf6c67.87436409.jpg', '2025-10-22 13:25:05', 0, NULL),
(26, 'Tocino', 100.00, 1, 'Food', 'uploads/supplies/supply_68ff39e19a28e3.57674370.jpg', '2025-10-22 13:25:17', 0, NULL),
(27, 'Tapa', 100.00, 1, 'Food', 'uploads/supplies/supply_68ff39a4323225.55753748.jpg', '2025-10-22 13:25:29', 0, NULL),
(28, 'Hotdog', 100.00, 1, 'Food', 'uploads/supplies/supply_68ff375cf2f9f0.00664409.jpg', '2025-10-22 13:25:39', 0, NULL),
(29, 'Dinuguan', 115.00, 1, 'Food', 'uploads/supplies/supply_68ff369cdd71b6.51963784.jpg', '2025-10-22 13:25:51', 0, NULL),
(30, 'Chicken Adobo', 120.00, 1, 'Food', 'uploads/supplies/supply_68ff3658483796.23761165.jpg', '2025-10-22 13:26:02', 0, NULL),
(31, 'Bicol Express', 125.00, 1, 'Food', 'uploads/supplies/supply_68ff348c751117.28466060.jpg', '2025-10-22 13:26:12', 0, NULL),
(32, 'Chicharon', 60.00, 1, 'Food', 'uploads/supplies/supply_68ff3649a3c2a0.07966117.jpg', '2025-10-22 13:27:04', 0, NULL),
(33, 'Chicken Skin', 60.00, 1, 'Food', 'uploads/supplies/supply_68ff36657d4872.90385227.jpg', '2025-10-22 13:27:15', 0, NULL),
(34, 'Lumpia Shanghai (3pcs)', 40.00, 1, 'Food', 'uploads/supplies/supply_68ff3965790bb8.14859109.jpg', '2025-10-22 13:27:43', 0, NULL),
(35, 'Lumpia Gulay (3pcs)', 40.00, 1, 'Food', 'uploads/supplies/supply_68ff36ed84e144.27296168.jpg', '2025-10-22 13:28:00', 0, NULL),
(36, 'Lumpia Toge (4pcs)', 40.00, 1, 'Food', 'uploads/supplies/supply_68ff39f237ee67.89717331.jpg', '2025-10-22 13:28:11', 0, NULL),
(37, 'French Fries (BBQ)', 40.00, 1, 'Food', 'uploads/supplies/supply_68ff36be4b4dd2.99065457.jpg', '2025-10-22 13:28:33', 0, NULL),
(38, 'French Fries (Sour Cream)', 40.00, 1, 'Food', 'uploads/supplies/supply_68ff36dc67e511.86363245.jpg', '2025-10-22 13:28:42', 0, NULL),
(39, 'French Fries (Cheese)', 40.00, 1, 'Food', 'uploads/supplies/supply_68ff36cd2b0320.57674002.jpg', '2025-10-22 13:28:55', 0, NULL),
(40, 'Cheese Sticks (12pcs)', 30.00, 1, 'Food', 'uploads/supplies/supply_68ff3640840637.34043748.jpg', '2025-10-22 13:29:05', 0, NULL),
(41, 'Tinapay (3pcs)', 20.00, 1, 'Food', 'uploads/supplies/supply_68ff39c61a3083.14425898.jpg', '2025-10-22 13:29:14', 0, NULL),
(42, 'Tinapay with Spread (3pcs)', 30.00, 1, 'Food', 'uploads/supplies/supply_68ff39d30c4186.10180480.jpg', '2025-10-22 13:29:24', 0, NULL),
(43, 'Burger Regular', 35.00, 1, 'Food', 'uploads/supplies/supply_68ff3509cff855.85557016.jpg', '2025-10-22 13:29:35', 0, NULL),
(44, 'Burger with Cheese', 40.00, 1, 'Food', 'uploads/supplies/supply_68ff3516eb2ee6.76324882.jpg', '2025-10-22 13:29:44', 0, NULL),
(45, 'Nagaraya Butter Yellow (Small)', 20.00, 1, 'Food', 'uploads/supplies/supply_68ff37d7d7ef22.44277588.jpg', '2025-10-22 13:29:53', 0, NULL),
(46, 'Nova Country Cheddar (Small)', 25.00, 1, 'Food', 'uploads/supplies/supply_68ff38f4b980f1.07596393.jpg', '2025-10-22 13:30:03', 0, NULL),
(47, 'Bottled Water (500ml)', 25.00, 24, 'Food', 'uploads/supplies/supply_68ff34a5bbfd01.11289135.jpg', '2025-10-22 13:30:12', 0, NULL),
(48, 'Purified Hot Water Only (Mug)', 10.00, 1, 'Food', 'uploads/supplies/supply_68ff39301695d4.93440684.jpg', '2025-10-22 13:30:23', 0, NULL),
(49, 'Ice Bucket', 40.00, 1, 'Food', 'uploads/supplies/supply_68ff376aef5347.04138990.jpg', '2025-10-22 13:30:33', 0, NULL),
(50, 'Coke Mismo', 25.00, 24, 'Food', 'uploads/supplies/supply_68ff367ba77765.87807350.jpg', '2025-10-22 13:31:23', 0, NULL),
(51, 'Royal Mismo', 25.00, 25, 'Food', 'uploads/supplies/supply_68ff3941715fc1.55098385.jpg', '2025-10-22 13:31:32', 0, NULL),
(52, 'Sting Energy Drink', 25.00, 24, 'Food', 'uploads/supplies/supply_68ff3985d76dd2.88652574.jpg', '2025-10-22 13:31:57', 0, NULL),
(53, 'Dragon Fruit', 70.00, 1, 'Food', 'uploads/supplies/supply_68ff36ad0d79a3.91348885.jpg', '2025-10-22 13:32:33', 0, NULL),
(54, 'Mango Shake', 70.00, 1, 'Food', 'uploads/supplies/supply_68ff37b8f1d644.90294469.jpg', '2025-10-22 13:32:47', 0, NULL),
(55, 'Cucumber', 70.00, 1, 'Food', 'uploads/supplies/supply_68ff368a8fa5c7.80292192.jpg', '2025-10-22 13:32:57', 0, NULL),
(56, 'Avocado', 70.00, 1, 'Food', 'uploads/supplies/supply_68ff3478540c99.33246993.jpg', '2025-10-22 13:33:08', 0, NULL),
(57, 'Chocolate', 40.00, 1, 'Food', 'uploads/supplies/supply_68ff36701c7554.54484218.jpg', '2025-10-22 13:33:20', 0, NULL),
(58, 'Taro Shake', 40.00, 1, 'Food', 'uploads/supplies/supply_68ff39b3776053.65042527.jpg', '2025-10-22 13:33:32', 0, NULL),
(59, 'Ube', 40.00, 1, 'Food', 'uploads/supplies/supply_68ff39fe755a11.05381470.jpg', '2025-10-22 13:33:54', 0, NULL),
(60, 'Strawberry', 40.00, 1, 'Food', 'uploads/supplies/supply_68ff3997847878.86483393.jpg', '2025-10-22 13:34:04', 0, NULL),
(61, 'Pineapple Juice', 60.00, 1, 'Food', 'uploads/supplies/supply_68ff390262fc77.43212004.jpg', '2025-10-22 13:34:22', 0, NULL),
(62, 'Instant Coffee', 25.00, 1, 'Food', 'uploads/supplies/supply_68ff3777ad7437.15270214.jpg', '2025-10-22 13:34:39', 0, NULL),
(63, 'Brewed Coffee', 45.00, 1, 'Food', 'uploads/supplies/supply_68ff34aeb12708.47672763.jpg', '2025-10-22 13:34:50', 0, NULL),
(64, 'Hot Tea (Green)', 25.00, 1, 'Food', 'uploads/supplies/supply_68ff374b5c4a73.43090841.jpg', '2025-10-22 13:35:00', 0, NULL),
(65, 'Hot Tea (Black)', 25.00, 1, 'Food', 'uploads/supplies/supply_68ff373bbed965.40930517.jpg', '2025-10-22 13:35:10', 0, NULL),
(66, 'Milo Hot Chocolate Drink', 25.00, 1, 'Food', 'uploads/supplies/supply_68ff37c89ac397.65594133.jpg', '2025-10-22 13:35:21', 0, NULL),
(67, 'Face Mask Disposable', 5.00, 24, 'Non-Food', 'uploads/supplies/supply_68ff3a82d13425.05023995.jpg', '2025-10-22 13:35:46', 0, NULL),
(68, 'Toothbrush with Toothpaste', 25.00, 20, 'Non-Food', 'uploads/supplies/supply_68ff3abab954a5.90200679.jpg', '2025-10-22 13:36:10', 0, NULL),
(69, 'Colgate Toothpaste', 20.00, 24, 'Non-Food', 'uploads/supplies/supply_68ff3a0d861fa6.62439705.jpg', '2025-10-22 13:36:38', 0, NULL),
(70, 'Modess All Night Extra Long Pad', 20.00, 24, 'Non-Food', 'uploads/supplies/supply_68ff3a930e1fc3.39885073.jpg', '2025-10-22 13:36:51', 0, NULL),
(71, 'Sunsilk', 15.00, 24, 'Non-Food', 'uploads/supplies/supply_68ff3aab9c36b9.24660915.jpg', '2025-10-22 13:37:04', 0, NULL),
(72, 'Creamsilk Shampoo', 15.00, 24, 'Non-Food', 'uploads/supplies/supply_68ff3a2bd3f498.97130291.jpg', '2025-10-22 13:37:14', 0, NULL),
(73, 'Palmolive Anti-Dandruff', 15.00, 24, 'Non-Food', 'uploads/supplies/supply_68ff3a9f306ee5.13936343.jpg', '2025-10-22 13:37:26', 0, NULL),
(74, 'Dove', 15.00, 24, 'Non-Food', 'uploads/supplies/supply_68ff3a60cea8e2.10018981.jpg', '2025-10-22 13:37:41', 0, NULL),
(75, 'Empress Keratin', 15.00, 24, 'Non-Food', 'uploads/supplies/supply_68ff3a6e592e58.91457731.jpg', '2025-10-22 13:37:52', 0, NULL),
(76, 'Creamsilk Conditioner', 15.00, 24, 'Non-Food', 'uploads/supplies/supply_68ff3a1eb95da9.04643937.jpg', '2025-10-22 13:38:02', 0, NULL),
(77, 'Trust Condom (3pcs)', 60.00, 24, 'Non-Food', 'uploads/supplies/supply_68ff3acda6e2b9.69825791.jpg', '2025-10-22 13:38:18', 0, NULL),
(78, 'Disposable Spoon', 2.00, 24, 'Non-Food', 'uploads/supplies/supply_68ff3a44e7b8b2.45244134.jpg', '2025-10-22 13:38:41', 0, NULL),
(79, 'Disposable Fork', 2.00, 24, 'Non-Food', 'uploads/supplies/supply_68ff3a37e38b37.79910859.jpg', '2025-10-22 13:38:53', 0, NULL),
(81, 'SUPPLY ARCHIVED 1', 30.00, 1, 'Non-Food', NULL, '2025-10-25 11:23:01', 1, '2025-10-25 19:23:17'),
(82, 'SUPPLY ARCHIVED 2', 40.00, 1, 'Non-Food', NULL, '2025-10-25 11:23:08', 1, '2025-10-25 19:23:15');

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
  `reset_expires` datetime DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT '0',
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `username`, `email`, `password`, `role`, `status`, `reset_token`, `reset_expires`, `is_archived`, `archived_at`) VALUES
(1, 'Sung Jin-woo', 'jinwhoo', 'example@email.com', '$2y$10$SWb8Zhwunhl3t7HHsLst.O31OwvI1Tie5xsX.kVKPV0KnG5ok7JOm', 'Admin', 'approved', NULL, NULL, 0, NULL),
(2, 'Baki Hanma', 'bakii', 'example@email.com', '$2y$10$zKqannJ7cL97TAumEeByUukNozIwEb.AGsP//jD7Fmgo0CPyL2Q6K', 'Receptionist', 'approved', NULL, NULL, 0, NULL),
(3, 'Megumi Fushiguro', 'meg_umi', 'example@email.com', '$2y$10$YQ3y5JtTR65YitLygESF3.GuuqddiFkikr5byhF0/GSUINDZf1lwO', 'Receptionist', 'approved', NULL, NULL, 0, NULL),
(9, 'Angel', 'angels', 'example@email.com', '$2y$10$H6tYQO141eRMx/Mv559Ru.RMW6BP.Y3wgC3ayrKZnM0CqMXMuSKIe', 'Receptionist', 'approved', NULL, NULL, 0, NULL),
(12, 'dev test', 'devtest', 'devtest@gmail.com', '$2y$10$/Z.vroBzK8Hs.YVAdgbeOebq9V8Y/KWh/D0fmIQi2izX4J4WT61sa', 'Receptionist', 'pending', NULL, NULL, 0, NULL),
(13, 'dev test two', 'devtest2', 'devtest2@gmail.com', '$2y$10$XHqW8K/xBRUlkBeh/k/MOeh09sVLgVkFf8B4FYaplXDRijllQJany', 'Receptionist', 'pending', NULL, NULL, 0, NULL),
(14, 'John Doe ', 'john', 'johndoe@gmail.com', '$2y$10$civ0BziV5mRIMw74tA55auch53rQWh/OzkwOnWhAWQ3d4KhJd1T5O', 'Receptionist', 'pending', NULL, NULL, 1, '2025-10-27 17:28:29'),
(15, 'Jane Smith', 'Jane', 'janesmith@gmail.com', '$2y$10$yzohZ7y9tNgsT9lvUthO0.wOdB6BVscssBZ1hR9WxGvkTCRyKfY3K', 'Receptionist', 'pending', NULL, NULL, 0, NULL),
(16, 'Emily Carter', 'Emily', 'emilycarter@gmail.com', '$2y$10$NW9eYV84Y6ZIxqZvp1p/ZOFxEKNAnBLwgT/voyQyWjonuebkoPuPe', 'Receptionist', 'pending', NULL, NULL, 0, NULL),
(17, 'David Brown', 'David', 'davidbrown@gmail.com', '$2y$10$U7dy9bciBLhUV.85JPyKCecaFAL/N7aUJ3QllCH/9MVG.78IE8/v.', 'Receptionist', 'pending', NULL, NULL, 1, '2025-10-27 17:28:26'),
(18, 'Michael Johnson', 'michael', 'michaeljohnson@gmail.com', '$2y$10$BGSXU2IlDvzQ9CjQu6qIsOzFI8F00i96qRN2wN4FeKQNsJfPFZKly', 'Receptionist', 'pending', NULL, NULL, 0, NULL),
(19, 'Toby Maguire', 'toby', 'toby@gmail.com', '$2y$10$DNG1bkEITaZunq6phIfPIOhJWNwiwhJgtPvbP.iSPWU5g985/iwYW', 'Guest', 'pending', NULL, NULL, 0, NULL);

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `cancelled_by` (`cancelled_by`);

--
-- Indexes for table `checkins`
--
ALTER TABLE `checkins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_number` (`room_number`),
  ADD KEY `idx_rebooked_from` (`rebooked_from`),
  ADD KEY `idx_guest_date` (`guest_name`,`check_in_date`);

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `checkins`
--
ALTER TABLE `checkins`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=186;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`user_id`);

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
