-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 18, 2025 at 05:37 PM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u594297719_hotel_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_by` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `message`, `created_by`, `created_at`) VALUES
(18, 'Sample Announcement', 'This is a test.', 'Admin', '2025-09-22 20:58:57');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `guest_name` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `num_people` int(11) DEFAULT NULL,
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `guest_name`, `email`, `address`, `telephone`, `age`, `num_people`, `room_number`, `duration`, `payment_mode`, `reference_number`, `booking_token`, `amount_paid`, `total_price`, `change_amount`, `start_date`, `end_date`, `status`, `created_at`, `cancellation_reason`, `cancelled_by`, `cancelled_at`) VALUES
(51, 'anynnn', 'aomine12@gmail.com', 'Brgy. Matabang na Dagat', '0912-312-3131', 18, 3, '101', '3', 'Cash', '', 'BK20251109A2547A', 400, 400, 0, '2025-11-09 21:25:00', '2025-11-10 00:25:00', 'completed', '2025-11-09 13:27:54', NULL, NULL, NULL),
(52, 'Natt', 'kukuhuskar82@gmail.com', 'Brgy. Punta, Calamba City', '0916-577-0822', 22, 1, '102', '3', 'GCash', '5454364765723', 'BK2025111051DAC3', 400, 400, 0, '2025-11-10 22:00:00', '2025-11-11 01:00:00', 'completed', '2025-11-10 12:27:33', NULL, NULL, NULL),
(54, 'Art', 'kukuhuskar82@gmail.com', 'Brgy. Bunggo', '0916-577-0822', 22, 1, '101', '3', 'Cash', '', 'BK202511125267E3', 400, 400, 0, '2025-11-13 01:00:00', '2025-11-13 04:00:00', 'completed', '2025-11-12 16:46:13', NULL, NULL, NULL),
(57, 'Gennifer', 'gitarraapartelle@gmail.com', 'Brgy. Di kana nya mahal', '0948-346-5389', 22, 0, '104', '3', '0', '', 'BK20251114001212', 0, 400, 0, '2025-11-14 20:30:00', '2025-11-14 23:30:00', 'completed', '2025-11-14 12:18:14', NULL, NULL, NULL),
(58, 'Madagscar', 'gitarraapartelle@gmail.com', 'Brgy. Bunggo', '0916-577-0822', 22, 1, '102', '3', 'GCash', '4324324322323', 'BK20251114CCF591', 400, 400, 0, '2025-11-15 00:30:00', '2025-11-15 03:30:00', 'completed', '2025-11-14 16:36:44', NULL, NULL, NULL),
(59, 'Juls', 'gitarraapartelle@gmail.com', 'Brgy. Bunggo', '0916-577-0822', 21, 1, '102', '3', 'GCash', '4332432453543', 'BK2025111499F173', 400, 400, 0, '2025-11-15 03:00:00', '2025-11-15 06:00:00', 'completed', '2025-11-14 18:02:17', NULL, NULL, NULL),
(70, 'Loren ', 'fajardoloren23@gmail.com', 'bunggo', '0912-117-9227', 22, 0, '107', '48', '0', '1234567890123', 'BK2025111605D01E', 560, 2800, 0, '2025-11-17 00:29:00', '2025-11-19 00:29:00', 'completed', '2025-11-16 16:30:04', 'Automatically cancelled - Guest did not check in within 30 minutes of designated time', NULL, '2025-11-17 04:07:38'),
(71, 'lorena', 'fajardoloren23@gmail.com', 'gitna', '0912-117-9227', 20, 0, '107', '3', '0', '9999990000000', 'BK202511161F234D', 80, 400, 0, '2025-11-17 00:33:00', '2025-11-17 03:33:00', 'completed', '2025-11-16 16:33:45', NULL, NULL, NULL),
(75, 'Arthur Leny', 'webc26696@gmail.com', 'St 123 Main Street', '0916-577-0822', 21, 1, '101', '3', 'GCash', '5489456645644', 'BK202511161A4F13', 400, 400, 0, '2025-11-17 05:24:00', '2025-11-17 08:24:00', 'completed', '2025-11-16 18:24:33', 'I have erraands to do', 1, '2025-11-17 04:08:14'),
(76, 'Poe', 'gitarraapartelle@gmail.com', 'Brgy. Bunggo', '0916-577-0822', 21, 1, '102', '3', 'GCash', '8239568927434', 'BK202511160EA63A', 400, 400, 0, '2025-11-17 04:30:00', '2025-11-17 07:30:00', 'completed', '2025-11-16 18:34:08', NULL, NULL, NULL),
(77, 'Mr Kyowa', 'ricsonfm@yahoo.com', 'Calamba Laguna ', '0906-444-7495', 33, 0, '106', '3', '0', '', 'BK202511170305FA', 80, 400, 0, '2025-11-17 11:18:00', '2025-11-17 14:18:00', 'completed', '2025-11-17 00:22:35', NULL, NULL, NULL),
(78, 'Mr Tea ', 'ricsonfm@gmail.com', 'Tokyo Japan ', '0906-444-7495', 45, 0, '101', '3', '0', '', 'BK2025111733B122', 80, 400, 0, '2025-11-25 08:25:00', '2025-11-25 11:25:00', 'completed', '2025-11-17 00:25:57', NULL, NULL, NULL),
(80, 'Test', 'kukuhuskar82@gmail.com', 'St 123 Main Street', '0916-577-0822', 22, 0, '102', '3', '0', '5465646456546', 'BK20251117C60D22', 80, 400, 0, '2025-11-17 23:00:00', '2025-11-18 02:00:00', 'completed', '2025-11-17 13:15:15', NULL, NULL, NULL),
(82, 'Tungtung Sahur', 'webc26696@gmail.com', 'Bunggo', '0916-577-0822', 21, 1, '104', '3', 'GCash', '5645465474511', 'BK20251117294D02', 400, 400, 0, '2025-11-17 23:00:00', '2025-11-18 02:00:00', 'completed', '2025-11-17 13:20:18', NULL, NULL, NULL),
(87, 'Cyeanne Jade Lopez', 'lopezcyeanne0318@gmail.com', 'Calamba City, Laguna', '0991-530-6045', 18, 0, '102', '6', '0', '0839292368383', 'BK202511182A0083', 150, 750, 0, '2025-11-18 13:02:00', '2025-11-18 19:02:00', 'completed', '2025-11-18 05:03:03', 'I change my mind', 2, '2025-11-18 05:25:28');

-- --------------------------------------------------------

--
-- Table structure for table `cards`
--

CREATE TABLE `cards` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `code` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `cards`
--

INSERT INTO `cards` (`id`, `room_id`, `code`, `created_at`) VALUES
(0, 1, '69 1F 64 12', '2025-11-17 04:12:07'),
(0, 1, 'F3 8B B8 38', '2025-11-17 05:24:54');

-- --------------------------------------------------------

--
-- Table structure for table `checkins`
--

CREATE TABLE `checkins` (
  `id` int(11) NOT NULL,
  `guest_name` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `telephone` varchar(15) NOT NULL,
  `room_number` int(11) NOT NULL,
  `room_type` varchar(50) NOT NULL,
  `stay_duration` int(11) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `change_amount` decimal(10,2) NOT NULL,
  `payment_mode` enum('cash','gcash') NOT NULL,
  `check_in_date` datetime NOT NULL,
  `check_out_date` datetime NOT NULL,
  `status` enum('scheduled','checked_in','checked_out') DEFAULT 'scheduled',
  `gcash_reference` varchar(100) DEFAULT NULL,
  `receptionist_id` int(11) DEFAULT NULL,
  `last_modified` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `previous_charges` decimal(10,2) DEFAULT 0.00,
  `rebooked_from` int(11) DEFAULT NULL,
  `is_rebooked` tinyint(1) DEFAULT 0,
  `tapped_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `checkins`
--

INSERT INTO `checkins` (`id`, `guest_name`, `address`, `telephone`, `room_number`, `room_type`, `stay_duration`, `total_price`, `amount_paid`, `change_amount`, `payment_mode`, `check_in_date`, `check_out_date`, `status`, `gcash_reference`, `receptionist_id`, `last_modified`, `previous_charges`, `rebooked_from`, `is_rebooked`, `tapped_at`) VALUES
(252, 'anynnn', 'bunggo', '+639123123131', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-09 21:28:33', '2025-11-10 00:28:33', '', '', 2, '2025-11-13 15:49:18', 0.00, NULL, 0, NULL),
(256, 'Kise Aomine', 'Brgy. Matabang na Dagat', '+639565656565', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-10 23:28:32', '2025-11-11 02:28:32', '', '', NULL, '2025-11-13 15:49:18', 0.00, NULL, 0, NULL),
(257, 'test', 'brgy. Maitin na Lupa', '+639123123123', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-12 09:26:09', '2025-11-12 12:26:09', '', '', 2, '2025-11-15 12:51:19', 0.00, NULL, 0, NULL),
(258, 'test', 'anyyy', '+639123123131', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-12 09:42:45', '2025-11-12 12:42:45', 'checked_out', '', 1, '2025-11-12 06:45:54', 0.00, NULL, 0, NULL),
(259, 'Piolo Pascual', 'Makati, City', '+630912345678', 101, 'standard_room', 6, 750.00, 1000.00, 250.00, 'cash', '2025-11-12 14:50:08', '2025-11-12 20:50:07', '', '', 2, '2025-11-13 15:49:18', 0.00, NULL, 0, NULL),
(260, 'Wenzy', 'St 123 Main Street', '+639165770829', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'gcash', '2025-11-12 15:25:47', '2025-11-12 18:25:47', 'checked_out', '2146885693214', 2, '2025-11-12 12:33:31', 0.00, NULL, 0, NULL),
(261, 'Kise Aomine', 'Brgy. Bunggo', '+639767675467', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-12 22:55:49', '2025-11-13 01:55:49', 'checked_out', '', NULL, '2025-11-13 06:40:54', 0.00, NULL, 0, NULL),
(262, 'Kise Aomine', 'Brgy. Matabang na Dagat', '+639767675467', 101, 'standard_room', 6, 750.00, 750.00, 0.00, 'cash', '2025-11-13 15:23:33', '2025-11-13 21:23:33', '', '', NULL, '2025-11-13 15:49:18', 0.00, NULL, 0, NULL),
(264, 'aljur', 'brgy. La mesa', '+639123123188', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-14 17:28:49', '2025-11-14 20:28:49', '', '', 2, '2025-11-15 12:51:19', 0.00, NULL, 0, NULL),
(267, 'Kise Aomine', 'Brgy. Matabang na Dagat', '+639767675467', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-15 00:18:12', '2025-11-15 01:18:45', '', '', NULL, '2025-11-15 12:51:19', 0.00, NULL, 0, NULL),
(269, 'test', 'Brgy. Matabang na Dagat', '+639232312313', 103, 'standard_room', 3, 300.00, 400.00, 100.00, 'cash', '2025-11-15 01:35:43', '2025-11-15 01:37:06', 'checked_out', '', NULL, '2025-11-14 17:37:06', 0.00, NULL, 0, NULL),
(270, 'Kise Aomine', 'Brgy. Matabang na Dagat', '+639767675467', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-15 01:40:01', '2025-11-15 01:41:30', '', '', NULL, '2025-11-15 12:51:19', 0.00, NULL, 0, NULL),
(271, 'aljur', 'brgy. La mesa', '+639767675467', 101, 'standard_room', 6, 750.00, 750.00, 0.00, 'cash', '2025-11-15 01:42:43', '2025-11-15 01:45:04', '', '', NULL, '2025-11-15 12:51:19', 0.00, NULL, 0, NULL),
(272, 'Kise Aomine', 'Brgy. Matabang na Dagat', '+639767675467', 102, 'standard_room', 12, 1100.00, 1100.00, 0.00, 'cash', '2025-11-15 01:43:08', '2025-11-15 01:53:06', 'checked_out', '', NULL, '2025-11-14 17:53:06', 0.00, NULL, 0, NULL),
(273, 'Kise Aomine', 'Brgy. Matabang na Dagat', '+639232312313', 103, 'standard_room', 6, 750.00, 750.00, 0.00, 'cash', '2025-11-15 01:58:27', '2025-11-15 02:00:09', 'checked_out', '', NULL, '2025-11-14 18:00:09', 0.00, NULL, 0, NULL),
(274, 'Kise Aomine', 'Brgy. Bunggo', '+639767675467', 103, 'standard_room', 3, 300.00, 300.00, 0.00, 'cash', '2025-11-15 02:01:58', '2025-11-15 02:05:49', 'checked_out', '', NULL, '2025-11-14 18:05:49', 0.00, NULL, 0, NULL),
(275, 'Kise Aomine', 'Brgy. Matabang na Dagat', '+639767675467', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-15 02:18:26', '2025-11-15 02:20:12', '', '', NULL, '2025-11-15 12:51:19', 0.00, NULL, 0, NULL),
(276, 'Kise Aomine', 'dawdaw2', '+639232312313', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-15 02:24:36', '2025-11-15 02:25:28', '', '', NULL, '2025-11-15 12:51:19', 0.00, NULL, 0, NULL),
(277, 'Kise Aomine', 'Brgy. Matabang na Dagat', '+639767675467', 106, 'twin_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-15 02:27:42', '2025-11-15 02:32:59', 'checked_out', '', NULL, '2025-11-14 18:32:59', 0.00, NULL, 0, NULL),
(278, 'Kazuha', 'Brgy. Matabang na Dagat', '+639232312313', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-15 02:30:58', '2025-11-15 02:33:36', '', '', NULL, '2025-11-15 12:51:19', 0.00, NULL, 0, NULL),
(279, 'Kise Aomine', 'Brgy. Matabang na Dagat', '+639767675467', 101, 'standard_room', 24, 1500.00, 1500.00, 0.00, 'cash', '2025-11-15 17:29:24', '2025-11-16 17:29:24', '', '', NULL, '2025-11-15 12:51:19', 0.00, NULL, 0, NULL),
(280, 'Asahi', 'brgy. La Mesa', '+639767675467', 101, 'standard_room', 6, 750.00, 750.00, 0.00, 'cash', '2025-11-15 21:07:40', '2025-11-16 00:55:29', 'checked_out', '', 1, '2025-11-15 16:55:29', 0.00, NULL, 0, NULL),
(281, 'aljur', 'Brgy. Matabang na Baha', '+639787867867', 103, 'standard_room', 3, 300.00, 300.00, 0.00, 'cash', '2025-11-15 22:36:56', '2025-11-16 01:36:56', 'checked_out', '', 2, '2025-11-15 17:42:50', 0.00, NULL, 0, NULL),
(282, 'test25', 'Brgy. Bunggo', '+639767675467', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-15 23:54:45', '2025-11-16 00:12:40', 'checked_out', '', 2, '2025-11-15 16:12:40', 0.00, NULL, 0, NULL),
(283, 'testtt', 'bunggo', '+639123123131', 104, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-16 00:24:55', '2025-11-16 03:24:55', 'checked_out', '', 2, '2025-11-15 23:49:53', 0.00, NULL, 0, NULL),
(284, 'test27', 'Brgy. Bunggo', '+639123123139', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-16 00:26:48', '2025-11-16 00:56:40', 'checked_out', '', 2, '2025-11-15 16:56:40', 0.00, NULL, 0, NULL),
(285, 'test31', 'dawdaw2', '+639123123133', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-16 00:58:22', '2025-11-16 03:58:22', 'checked_out', '', 2, '2025-11-15 23:49:53', 0.00, NULL, 0, NULL),
(286, 'Chaewon', 'Brgy. Matabang na Dagat', '+639232312313', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-16 19:57:08', '2025-11-16 22:57:08', 'checked_out', '', NULL, '2025-11-16 15:10:30', 0.00, NULL, 0, NULL),
(287, 'Kise Aomine', 'Brgy. Bunggo', '+639767675467', 103, 'standard_room', 6, 750.00, 750.00, 0.00, 'cash', '2025-11-16 20:00:46', '2025-11-17 02:00:46', 'checked_out', '', NULL, '2025-11-16 18:18:07', 0.00, NULL, 0, NULL),
(288, 'Loren', 'bunggo', '+630912117922', 107, 'single', 24, 1400.00, 1500.00, 100.00, 'cash', '2025-11-17 00:31:42', '2025-11-18 00:31:42', 'checked_out', '', 2, '2025-11-17 20:23:17', 0.00, NULL, 0, NULL),
(289, 'test35', 'Brgy. Bunggo', '+639123123132', 104, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-17 01:07:42', '2025-11-16 18:23:13', 'checked_out', '', 2, '2025-11-16 18:23:13', 0.00, NULL, 0, NULL),
(290, 'Mr Tea', 'Quae necessitatibus', '+630923424242', 101, 'standard_room', 3, 400.00, 500.00, 100.00, 'cash', '2025-11-17 11:57:16', '2025-11-17 12:06:50', 'checked_out', '', 2, '2025-11-17 04:06:50', 0.00, NULL, 0, '2025-11-17 04:01:35'),
(291, 'Vega Punk', 'Egg Island', '+639324242342', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'gcash', '2025-11-17 12:09:41', '2025-11-17 15:09:41', 'checked_out', '3423423424234', 1, '2025-11-17 08:20:39', 0.00, NULL, 0, '2025-11-17 05:31:56'),
(292, 'Hanni', 'Brgy. Bunggo', '+639123123132', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'gcash', '2025-11-17 16:27:42', '2025-11-17 16:34:53', 'checked_out', '5645676163467', 2, '2025-11-17 08:34:53', 0.00, NULL, 0, NULL),
(293, 'Kasper Riley', 'Excepturi dolores un', '+639213131231', 101, 'standard_room', 24, 1500.00, 2000.00, 500.00, 'cash', '2025-11-17 19:20:00', '2025-11-18 10:24:09', 'checked_out', '', NULL, '2025-11-18 02:24:09', 0.00, NULL, 0, '2025-11-18 01:47:21'),
(294, 'alhur', 'anyyyy', '+639123123133', 102, 'standard_room', 6, 750.00, 750.00, 0.00, 'cash', '2025-11-17 21:18:25', '2025-11-18 03:18:25', 'checked_out', '', NULL, '2025-11-17 20:23:17', 0.00, NULL, 0, NULL),
(295, 'Test Tap', 'Brgy. Barandal', '+639165770821', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-18 06:28:43', '2025-11-18 09:28:43', 'checked_out', '', 2, '2025-11-18 01:38:30', 0.00, NULL, 0, NULL),
(296, 'krysthlle daoitan', 'lawa', '+630916177747', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'gcash', '2025-11-18 11:36:43', '2025-11-18 03:47:34', 'checked_out', '9039747637483', NULL, '2025-11-18 03:47:34', 0.00, NULL, 0, '2025-11-18 03:38:33'),
(297, 'krysthlle daoitan', 'lawa', '+630916177747', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'gcash', '2025-11-18 11:48:33', '2025-11-18 03:51:01', 'checked_out', '8297982479779', NULL, '2025-11-18 03:51:01', 0.00, NULL, 0, NULL),
(298, 'Sean Doctora', 'Calamba City, Laguna', '+63912345678', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'gcash', '2025-11-18 12:09:22', '2025-11-18 13:29:34', 'checked_out', '0421891753838', 2, '2025-11-18 05:29:34', 0.00, NULL, 0, NULL),
(299, 'Aldrick Dulnuan', 'Calamba City, Laguna', '+630976109001', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-18 13:31:00', '2025-11-18 13:33:57', 'checked_out', '', 2, '2025-11-18 05:33:57', 0.00, NULL, 0, '2025-11-18 05:33:22'),
(300, 'Cyeanne Jade  Lopez', 'Calamba City, Laguna', '+630912345678', 101, 'standard_room', 7, 400.00, 800.00, 0.00, 'cash', '2025-11-18 13:42:17', '2025-11-18 05:45:53', 'checked_out', '', 2, '2025-11-18 05:45:53', 520.00, NULL, 1, NULL),
(301, 'Hev Abits', 'Philippines', '+630912345678', 101, 'standard_room', 7, 400.00, 800.00, 0.00, 'cash', '2025-11-18 13:51:31', '2025-11-18 05:52:20', 'checked_out', '', 2, '2025-11-18 05:52:20', 520.00, NULL, 1, NULL),
(302, 'Chimpanzinee Bananinee', 'Philippines', '+630912345678', 103, 'standard_room', 3, 420.00, 420.00, 0.00, 'cash', '2025-11-18 13:54:51', '2025-11-18 15:50:02', 'checked_out', '', 2, '2025-11-18 07:50:02', 0.00, NULL, 0, NULL),
(303, 'Jasmine', 'B20. L29, Milan St., Calamba Hills Ph.2', '+630912345678', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'gcash', '2025-11-18 14:20:54', '2025-11-18 06:32:01', 'checked_out', '1634629999999', 2, '2025-11-18 06:32:01', 0.00, NULL, 0, NULL),
(304, 'Bruce Lee', 'America', '+630976109001', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'gcash', '2025-11-18 14:34:00', '2025-11-18 14:35:46', 'checked_out', '0421891753222', 2, '2025-11-18 06:35:46', 0.00, NULL, 0, '2025-11-18 06:35:26'),
(305, 'Juan Luna', 'America', '+630912345678', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-18 14:40:51', '2025-11-18 14:46:47', 'checked_out', '', 2, '2025-11-18 06:46:47', 0.00, NULL, 0, '2025-11-18 06:46:30'),
(306, 'Nami', 'America', '+630912345678', 101, 'standard_room', 20, 750.00, 1850.00, 0.00, 'gcash', '2025-11-18 15:04:18', '2025-11-18 07:07:46', 'checked_out', '4513848416347', 2, '2025-11-18 07:07:46', 1340.00, NULL, 1, NULL),
(307, 'telay dapitan', 'lawa', '+630916967422', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-18 15:58:41', '2025-11-18 16:02:22', 'checked_out', '', 2, '2025-11-18 08:02:22', 0.00, NULL, 0, '2025-11-18 08:01:49'),
(308, 'krystelle ganda', 'lawa lang', '+630916967422', 101, 'standard_room', 3, 520.00, 1000.00, 600.00, 'cash', '2025-11-18 16:03:21', '2025-11-18 17:42:15', 'checked_out', '', 2, '2025-11-18 09:42:15', 0.00, NULL, 0, '2025-11-18 09:35:36'),
(309, 'test39', 'Brgy. Bunggo', '+630912312313', 104, 'standard_room', 3, 640.00, 640.00, 0.00, 'gcash', '2025-11-18 17:11:37', '2025-11-18 09:39:43', 'checked_out', '4513848416347', 2, '2025-11-18 09:39:43', 0.00, NULL, 0, NULL),
(310, 'Aldrick Dulnuan', 'Calamba City, Laguna', '+630912345678', 102, 'standard_room', 3, 400.00, 1000.00, 600.00, 'cash', '2025-11-18 17:26:09', '2025-11-18 18:15:37', 'checked_out', '1634629109226', 31, '2025-11-18 10:15:37', 0.00, NULL, 0, NULL),
(311, 'Aldrick Dulnuan', 'Calamba City, Laguna', '+630912345678', 101, 'standard_room', 6, 750.00, 750.00, 0.00, 'gcash', '2025-11-18 17:48:54', '2025-11-18 17:52:24', 'checked_out', '1634629109226', 31, '2025-11-18 09:52:24', 0.00, NULL, 0, NULL),
(312, 'Loki', 'B20. L29, Milan St., Calamba Hills Ph.2', '+630912345678', 101, 'standard_room', 6, 750.00, 750.00, 0.00, 'gcash', '2025-11-18 17:54:39', '2025-11-18 10:08:17', 'checked_out', '1634629109226', 31, '2025-11-18 10:08:17', 0.00, NULL, 0, NULL),
(313, 'James Bond', 'Philippines', '+630912345678', 101, 'standard_room', 3, 400.00, 500.00, 100.00, 'cash', '2025-11-18 18:10:28', '2025-11-18 10:15:51', 'checked_out', '', 31, '2025-11-18 10:15:51', 0.00, NULL, 0, NULL),
(314, 'guest A.', 'Brgy. Bunggo', '+639565656565', 101, 'standard_room', 6, 750.00, 1000.00, 250.00, 'cash', '2025-11-18 18:20:52', '2025-11-18 10:28:52', 'checked_out', '', 2, '2025-11-18 10:28:52', 0.00, NULL, 0, '2025-11-18 10:26:59'),
(315, 'guest B.', 'Brgy. Matabang na Dagat', '+639123123131', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-18 18:31:16', '2025-11-18 10:31:24', 'checked_out', '', 2, '2025-11-18 10:31:24', 0.00, NULL, 0, NULL),
(316, 'Loki', 'Calamba City, Laguna', '+630912345678', 101, 'standard_room', 6, 750.00, 750.00, 0.00, 'gcash', '2025-11-18 18:32:09', '2025-11-18 18:33:00', 'checked_out', '1634629109226', 31, '2025-11-18 10:33:00', 0.00, NULL, 0, NULL),
(317, 'Sanji', 'Majada Out, Calamba, Laguna', '+630976109001', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'gcash', '2025-11-18 18:33:55', '2025-11-18 18:34:42', 'checked_out', '0421891753000', 31, '2025-11-18 10:34:42', 0.00, NULL, 0, '2025-11-18 10:34:11'),
(318, 'guest C.', 'brgy. La mesa', '+630912312313', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-18 18:35:35', '2025-11-18 10:35:39', 'checked_out', '', 2, '2025-11-18 10:35:39', 0.00, NULL, 0, NULL),
(319, 'guest D.', 'Brgy. Matabang na Dagat', '+639123123132', 104, 'standard_room', 7, 400.00, 900.00, 500.00, 'gcash', '2025-11-18 18:40:25', '2025-11-18 10:46:10', 'checked_out', '5637867878678', 2, '2025-11-18 10:46:10', 520.00, NULL, 1, NULL),
(320, 'aljur', 'Brgy. Bunggo', '+639565656565', 101, 'standard_room', 4, 520.00, 520.00, 0.00, 'cash', '2025-11-18 18:46:36', '2025-11-18 19:12:17', 'checked_out', '', 2, '2025-11-18 11:12:17', 0.00, NULL, 0, NULL),
(321, 'guest E.', 'Brgy. Matabang na Dagat', '+639565656565', 106, 'twin_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-18 18:57:40', '2025-11-18 10:58:50', 'checked_out', '', 2, '2025-11-18 10:58:50', 0.00, NULL, 0, NULL),
(322, 'guest F.', 'bunggo', '+639565656565', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-18 19:00:06', '2025-11-18 11:00:26', 'checked_out', '', 2, '2025-11-18 11:00:26', 0.00, NULL, 0, NULL),
(323, 'Loki', 'Calamba City, Laguna', '+630912345678', 101, 'standard_room', 3, 400.00, 500.00, 100.00, 'cash', '2025-11-18 19:13:03', '2025-11-18 19:14:56', 'checked_out', '', 31, '2025-11-18 11:14:56', 0.00, NULL, 0, '2025-11-18 11:14:22'),
(324, 'guest G.', 'Brgy. Matabang na Dagat', '+639565656565', 106, 'twin_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-18 19:23:11', '2025-11-18 19:25:00', 'checked_out', '', NULL, '2025-11-18 11:25:00', 0.00, NULL, 0, NULL),
(325, 'Shin Taro', 'Brgy. Matabang na Dagat', '+639232312313', 101, 'standard_room', 16, 400.00, 1500.00, 0.00, 'cash', '2025-11-18 19:23:36', '2025-11-18 11:37:56', 'checked_out', '', NULL, '2025-11-18 11:37:56', 1220.00, NULL, 1, NULL),
(326, 'guest H.', 'brgy. La mesa', '+639123123133', 104, 'standard_room', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-18 19:25:33', '2025-11-18 11:26:25', 'checked_out', '', NULL, '2025-11-18 11:26:25', 0.00, NULL, 0, NULL),
(327, 'Testtt', 'Brgy. Bunggo', '+639165770821', 102, 'standard_room', 7, 520.00, 800.00, 0.00, 'cash', '2025-11-18 19:31:00', '2025-11-18 11:31:30', 'checked_out', '', 2, '2025-11-18 11:31:30', 400.00, NULL, 1, NULL),
(328, 'Guest AA', 'Brgy. Bunggo', '+639165770821', 107, 'single', 3, 400.00, 400.00, 0.00, 'cash', '2025-11-18 19:36:17', '2025-11-18 11:36:21', 'checked_out', '', 2, '2025-11-18 11:36:21', 0.00, NULL, 0, NULL),
(329, 'Guest Bb', 'Brgy. Bunggo', '+639165770821', 106, 'twin_room', 7, 520.00, 920.00, 400.00, 'cash', '2025-11-18 19:40:03', '2025-11-18 11:40:26', 'checked_out', '', 2, '2025-11-18 11:40:26', 400.00, NULL, 1, NULL),
(330, 'Murasakibara', 'brgy. La Mesa', '+639123123132', 101, 'standard_room', 7, 870.00, 870.00, 0.00, 'cash', '2025-11-18 19:40:34', '2025-11-18 20:20:54', 'checked_out', '', NULL, '2025-11-18 12:20:54', 0.00, NULL, 0, '2025-11-18 12:13:56'),
(331, 'Guest CC', 'Brgy. Bunggo', '+639650825435', 104, 'standard_room', 3, 400.00, 400.00, 0.00, 'gcash', '2025-11-18 19:56:16', '2025-11-18 11:57:19', 'checked_out', '3132132313354', 2, '2025-11-18 11:57:19', 0.00, NULL, 0, NULL),
(332, 'Guest EE', 'Brgy. Bunggo', '+639437482374', 107, 'single', 3, 400.00, 400.00, 0.00, 'gcash', '2025-11-18 19:58:09', '2025-11-18 22:58:09', 'checked_out', '7658654545455', 2, '2025-11-18 16:18:44', 0.00, NULL, 0, NULL),
(333, 'dawda', 'Brgy. Matabang na Dagat', '+639767675467', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'gcash', '2025-11-19 00:19:57', '2025-11-19 00:20:05', 'checked_out', '4545377827827', 2, '2025-11-18 16:20:05', 0.00, NULL, 0, NULL),
(334, 'dasdwda', 'Brgy. Bunggo', '+639123123131', 101, 'standard_room', 3, 400.00, 400.00, 0.00, 'gcash', '2025-11-19 00:25:18', '2025-11-19 00:25:23', 'checked_out', '7822452787257', 2, '2025-11-18 16:25:23', 0.00, NULL, 0, NULL),
(335, 'Guest H', 'Brgy. Bunggo', '+639437482374', 101, 'standard_room', 7, 520.00, 920.00, 400.00, 'cash', '2025-11-19 00:52:41', '2025-11-18 16:54:06', 'checked_out', '', 2, '2025-11-18 16:54:06', 400.00, NULL, 1, NULL),
(336, 'Guest K', 'Brgy. Bunggo', '+639437482374', 102, 'standard_room', 3, 400.00, 400.00, 0.00, 'gcash', '2025-11-19 00:53:24', '2025-11-18 16:54:09', 'checked_out', '1569872256435', 2, '2025-11-18 16:54:09', 0.00, NULL, 0, NULL);

--
-- Triggers `checkins`
--
DELIMITER $$
CREATE TRIGGER `delete_remarks_on_checkout` AFTER UPDATE ON `checkins` FOR EACH ROW BEGIN
    IF NEW.status = 'checked_out' AND OLD.status <> 'checked_out' THEN
        DELETE FROM remarks
        WHERE checkin_id = NEW.id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `complaint_text` text DEFAULT NULL,
  `status` enum('pending','resolved') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `guest_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `type` enum('feedback','complaint') NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','resolved') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `guests`
--

CREATE TABLE `guests` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `keycards`
--

CREATE TABLE `keycards` (
  `id` int(11) NOT NULL,
  `room_number` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `guest_id` int(11) DEFAULT NULL,
  `qr_code` varchar(255) NOT NULL,
  `valid_from` datetime NOT NULL,
  `valid_to` datetime NOT NULL,
  `status` enum('active','expired','revoked') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `keycards`
--

INSERT INTO `keycards` (`id`, `room_number`, `booking_id`, `guest_id`, `qr_code`, `valid_from`, `valid_to`, `status`) VALUES
(101, 101, NULL, NULL, '4A95D878', '2025-11-09 09:59:00', '2025-11-09 20:00:58', 'expired'),
(102, 102, NULL, NULL, 'DFD5A84B', '2025-11-09 10:04:38', '2035-11-09 10:04:38', 'expired'),
(103, 101, NULL, NULL, 'DA377247', '2025-11-10 03:59:03', '2025-11-09 20:00:58', 'expired'),
(104, 101, NULL, NULL, '2F1AED62', '2025-11-13 21:28:14', '2025-11-18 17:42:17', 'expired'),
(105, 104, NULL, NULL, '1337D9D3', '2025-11-14 14:22:08', '2025-11-18 22:40:25', 'expired'),
(106, 103, NULL, NULL, '01A58E9C', '2025-11-14 17:35:56', '2025-11-18 17:54:51', 'expired'),
(107, 106, NULL, NULL, 'D8853289', '2025-11-14 18:28:29', '2035-11-14 18:28:29', 'expired'),
(108, 101, NULL, NULL, '0D89B2F7', '2025-11-18 13:42:17', '2025-11-18 17:51:31', 'expired'),
(109, 101, NULL, NULL, 'D161ADEE', '2025-11-18 13:51:31', '2025-11-19 05:04:18', 'expired'),
(110, 101, NULL, NULL, '39B6CBA3', '2025-11-18 15:04:18', '2025-11-19 08:23:36', 'expired'),
(111, 104, NULL, NULL, '9A9C9518', '2025-11-18 18:40:25', '2025-11-19 01:40:25', 'expired'),
(112, 102, NULL, NULL, 'F66754C7', '2025-11-18 19:31:00', '2025-11-19 02:31:00', 'expired'),
(113, 101, NULL, NULL, '2B2DAFB6', '2025-11-18 19:23:36', '2025-11-19 02:40:34', 'expired'),
(114, 106, NULL, NULL, 'D2DE4618', '2025-11-18 19:40:03', '2025-11-19 02:40:03', 'expired'),
(115, 107, NULL, NULL, '367B120E', '2025-11-18 12:22:02', '2035-11-18 12:22:02', 'expired'),
(116, 101, NULL, NULL, 'DE24B4F9', '2025-11-19 00:52:41', '2025-11-19 07:52:41', 'expired');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `checkin_id` int(11) NOT NULL,
  `supply_id` int(11) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `category` varchar(50) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `size` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `status` enum('pending','preparing','prepared','served') NOT NULL DEFAULT 'pending',
  `prepare_start_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `payment_date` datetime NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_mode` enum('cash','gcash') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `payment_date`, `amount`, `payment_mode`) VALUES
(26, '2025-11-18 07:49:58', 120.00, 'cash'),
(27, '2025-11-18 09:39:42', 240.00, 'gcash'),
(28, '2025-11-18 10:42:56', 100.00, 'gcash'),
(29, '2025-11-18 10:47:15', 100.00, 'gcash'),
(30, '2025-11-18 11:00:26', 80.00, 'cash'),
(31, '2025-11-18 11:12:15', 20.00, 'cash'),
(32, '2025-11-18 11:40:26', 120.00, 'cash'),
(33, '2025-11-18 12:20:51', 120.00, 'cash'),
(34, '2025-11-18 16:53:54', 100.00, 'cash'),
(35, '2025-11-18 16:54:02', 20.00, 'cash');

-- --------------------------------------------------------

--
-- Table structure for table `receptionist_profiles`
--

CREATE TABLE `receptionist_profiles` (
  `profile_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `dob` date NOT NULL,
  `place_of_birth` varchar(100) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `emergency_contact_name` varchar(100) NOT NULL,
  `emergency_contact` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `receptionist_profiles`
--

INSERT INTO `receptionist_profiles` (`profile_id`, `user_id`, `full_name`, `contact`, `dob`, `place_of_birth`, `gender`, `emergency_contact_name`, `emergency_contact`, `address`, `profile_picture`, `created_at`) VALUES
(1, 2, 'Baki Hanma', '09123456789', '2000-02-14', 'Kyoto, Japan', 'Male', 'Seijuro Hanma', '091212121212', 'Calamba City, Laguna', 'receptionist_2.jpg', '2025-06-16 03:05:05');

-- --------------------------------------------------------

--
-- Table structure for table `remarks`
--

CREATE TABLE `remarks` (
  `id` int(11) NOT NULL,
  `checkin_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_number` int(11) NOT NULL,
  `room_type` enum('single','twin_room','standard_room','studio','suite','queen_room','executive_room','suites','accessible_room','hollywood_twin_room','king_room','studio_hotel_rooms','villa','double_hotel_rooms','honeymoon_suite','penthouse_suite','single_hotel_rooms','adjoining_room','presidential_suite','connecting_rooms','quad_room','deluxe_room','double_room','triple_room') NOT NULL,
  `status` enum('available','booked','occupied','maintenance') NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `price_3hrs` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_6hrs` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_12hrs` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_24hrs` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_ot` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_archived` tinyint(1) DEFAULT 0,
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_number`, `room_type`, `status`, `created_at`, `updated_at`, `price_3hrs`, `price_6hrs`, `price_12hrs`, `price_24hrs`, `price_ot`, `is_archived`, `archived_at`) VALUES
(1, 101, 'standard_room', 'available', '2025-04-26 16:34:08', '2025-11-18 16:54:06', 400.00, 750.00, 1100.00, 1500.00, 120.00, 0, NULL),
(2, 102, 'standard_room', 'available', '2025-04-26 17:37:56', '2025-11-18 16:54:09', 400.00, 750.00, 1100.00, 1500.00, 120.00, 0, NULL),
(3, 103, 'standard_room', 'available', '2025-04-28 16:01:31', '2025-11-18 07:50:02', 300.00, 750.00, 1100.00, 1500.00, 120.00, 0, NULL),
(4, 104, 'standard_room', 'available', '2025-04-29 13:29:43', '2025-11-18 11:57:19', 400.00, 750.00, 1100.00, 1500.00, 120.00, 0, NULL),
(5, 106, 'twin_room', 'available', '2025-05-07 06:15:36', '2025-11-18 11:40:26', 400.00, 750.00, 1100.00, 1500.00, 120.00, 0, NULL),
(6, 107, 'single', 'available', '2025-09-29 09:08:45', '2025-11-18 17:12:11', 400.00, 600.00, 1200.00, 1400.00, 120.00, 0, NULL),
(7, 108, 'executive_room', 'maintenance', '2025-09-29 09:32:33', '2025-11-18 17:12:55', 400.00, 800.00, 1200.00, 1600.00, 120.00, 0, NULL),
(22, 109, 'single', 'maintenance', '2025-11-18 17:13:29', '2025-11-18 17:13:34', 400.00, 800.00, 1200.00, 1600.00, 120.00, 1, '2025-11-18 17:13:34');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `age` int(11) NOT NULL,
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
  `id` int(11) NOT NULL,
  `supply_id` int(11) NOT NULL,
  `action` enum('in','out') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `action_type` enum('in','out') NOT NULL DEFAULT 'in'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplies`
--

CREATE TABLE `supplies` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `category` enum('Food','Non-Food') NOT NULL,
  `type` enum('Noodles','Rice Meals','Lumpia','Snacks','Water','Ice','Softdrinks','Shakes','Juice','Coffee','Teas','Other Drinks','Essentials','Dental Care','Feminine Hygiene','Shampoo','Conditioner','Personal Protection','Disposable Utensils') NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('available','unavailable') NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `is_archived` tinyint(1) DEFAULT 0,
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `supplies`
--

INSERT INTO `supplies` (`id`, `name`, `price`, `quantity`, `category`, `type`, `image`, `status`, `created_at`, `is_archived`, `archived_at`) VALUES
(1, 'Mami', 70.00, 999, 'Food', 'Noodles', 'uploads/supplies/Mami.png', 'available', '2025-11-01 03:59:55', 0, NULL),
(2, 'Nissin Cup (Beef)', 40.00, 0, 'Food', 'Noodles', 'uploads/supplies/Nissin Beef.png', 'unavailable', '2025-11-01 03:59:55', 0, NULL),
(3, 'Nissin Cup (Chicken)', 40.00, 0, 'Food', 'Noodles', 'uploads/supplies/Nissin Chicken.png', 'unavailable', '2025-11-01 03:59:55', 0, NULL),
(4, 'Nissin Cup (Spicy Seafood)', 40.00, 0, 'Food', 'Noodles', 'uploads/supplies/Nissin Spicy Seafood.png', 'unavailable', '2025-11-01 03:59:55', 0, NULL),
(5, 'Longganisa', 100.00, 999, 'Food', 'Rice Meals', 'uploads/supplies/Longganisa.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(6, 'Sisig', 100.00, 999, 'Food', 'Rice Meals', 'uploads/supplies/Sisig.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(7, 'Bopis', 100.00, 999, 'Food', 'Rice Meals', 'uploads/supplies/Bopis.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(8, 'Tocino', 100.00, 999, 'Food', 'Rice Meals', 'uploads/supplies/Tocino.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(9, 'Tapa', 100.00, 999, 'Food', 'Rice Meals', 'uploads/supplies/Tapa.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(10, 'Hotdog', 100.00, 999, 'Food', 'Rice Meals', 'uploads/supplies/Hotdog.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(11, 'Dinuguan', 115.00, 999, 'Food', 'Rice Meals', 'uploads/supplies/Dinuguan.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(12, 'Chicken Adobo', 120.00, 999, 'Food', 'Rice Meals', 'uploads/supplies/Chicken Adobo.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(13, 'Bicol Express', 125.00, 999, 'Food', 'Rice Meals', 'uploads/supplies/Bicol Express.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(14, 'Chicharon', 60.00, 999, 'Food', 'Rice Meals', 'uploads/supplies/Chicharon.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(15, 'Chicken Skin', 60.00, 999, 'Food', 'Rice Meals', 'uploads/supplies/Chicken Skin.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(16, 'Shanghai (3pcs)', 40.00, 999, 'Food', 'Lumpia', 'uploads/supplies/Lumpia Shanghai.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(17, 'Gulay (3pcs)', 40.00, 999, 'Food', 'Lumpia', 'uploads/supplies/Lumpia Gulay.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(18, 'Toge (4pcs)', 40.00, 999, 'Food', 'Lumpia', 'uploads/supplies/Lumpia Toge.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(19, 'French Fries (BBQ)', 40.00, 999, 'Food', 'Snacks', 'uploads/supplies/French Fries BBQ.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(20, 'French Fries (Sour Cream)', 40.00, 999, 'Food', 'Snacks', 'uploads/supplies/French Fries Sour Cream.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(21, 'French Fries (Cheese)', 40.00, 999, 'Food', 'Snacks', 'uploads/supplies/French Fries Cheese.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(22, 'Cheese Sticks (12pcs)', 30.00, 999, 'Food', 'Snacks', 'uploads/supplies/Cheese Sticks 12pcs.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(23, 'Tinapay (3pcs)', 20.00, 999, 'Food', 'Snacks', 'uploads/supplies/Tinapay 3pcs.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(24, 'Tinapay with Spread (3pcs)', 30.00, 999, 'Food', 'Snacks', 'uploads/supplies/Tinapay with Spread 3pcs.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(25, 'Burger Regular', 35.00, 999, 'Food', 'Snacks', 'uploads/supplies/Burger Regular.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(26, 'Burger with Cheese', 40.00, 999, 'Food', 'Snacks', 'uploads/supplies/Burger with Cheese.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(27, 'Nagaraya Butter Yellow (Small)', 20.00, 12, 'Food', 'Snacks', 'uploads/supplies/Nagaraya Butter Yellow Small.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(28, 'Nova Country Cheddar (Small)', 25.00, 11, 'Food', 'Snacks', 'uploads/supplies/Nova Country Cheddar Small.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(29, 'Tortillos BBQ (100g)', 40.00, 8, 'Food', 'Snacks', 'uploads/supplies/Tortillos BBQ.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(30, 'Bottled Water (500ml)', 25.00, 12, 'Food', 'Water', 'uploads/supplies/Bottled Water.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(31, 'Purified Hot Water Only (Mug)', 10.00, 12, 'Food', 'Water', 'uploads/supplies/Purified Hot Water Mug.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(32, 'Ice Bucket', 40.00, 12, 'Food', 'Ice', 'uploads/supplies/Ice Bucket.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(33, 'Coke Mismo', 25.00, 12, 'Food', 'Softdrinks', 'uploads/supplies/Coke Mismo.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(34, 'Royal Mismo', 25.00, 12, 'Food', 'Softdrinks', 'uploads/supplies/Royal Mismo.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(35, 'Dragon Fruit', 70.00, 999, 'Food', 'Shakes', 'uploads/supplies/Dragon Fruit Shake.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(36, 'Mango', 70.00, 999, 'Food', 'Shakes', 'uploads/supplies/Mango Shake.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(37, 'Cucumber', 70.00, 999, 'Food', 'Shakes', 'uploads/supplies/Cucumber Shake.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(38, 'Avocado', 70.00, 999, 'Food', 'Shakes', 'uploads/supplies/Avocado Shake.jpg', 'unavailable', '2025-11-01 03:59:55', 0, NULL),
(39, 'Chocolate', 40.00, 999, 'Food', 'Shakes', 'uploads/supplies/Chocolate Shake.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(40, 'Taro', 40.00, 999, 'Food', 'Shakes', 'uploads/supplies/Taro Shake.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(41, 'Ube', 40.00, 999, 'Food', 'Shakes', 'uploads/supplies/Ube Shake.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(42, 'Strawberry', 40.00, 999, 'Food', 'Shakes', 'uploads/supplies/Strawberry Shake.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(43, 'Pineapple Juice', 60.00, 11, 'Food', 'Juice', 'uploads/supplies/Pineapple Juice.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(44, 'Instant Coffee', 25.00, 12, 'Food', 'Coffee', 'uploads/supplies/Instant Coffee.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(45, 'Brewed Coffee', 45.00, 12, 'Food', 'Coffee', 'uploads/supplies/Brewed Coffee.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(46, 'Hot Tea (Green)', 25.00, 11, 'Food', 'Teas', 'uploads/supplies/Hot Tea Green.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(47, 'Hot Tea (Black)', 25.00, 12, 'Food', 'Teas', 'uploads/supplies/Hot Tea Black.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(48, 'Milo Hot Chocolate Drink', 25.00, 12, 'Food', 'Other Drinks', 'uploads/supplies/Milo Hot Chocolate.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(49, 'Face Mask Disposable', 5.00, 12, 'Non-Food', 'Essentials', 'uploads/supplies/Face Mask Disposable.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(50, 'Toothbrush with Toothpaste', 25.00, 12, 'Non-Food', 'Dental Care', 'uploads/supplies/Toothbrush with Toothpaste.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(51, 'Colgate Toothpaste', 20.00, 12, 'Non-Food', 'Dental Care', 'uploads/supplies/Colgate Toothpaste.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(52, 'Modess All Night Extra Long Pad', 20.00, 12, 'Non-Food', 'Feminine Hygiene', 'uploads/supplies/Modess All Night Extra Long Pad.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(53, 'Sunsilk', 15.00, 12, 'Non-Food', 'Shampoo', 'uploads/supplies/Sunsilk Shampoo.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(54, 'Creamsilk', 15.00, 12, 'Non-Food', 'Shampoo', 'uploads/supplies/Creamsilk Shampoo.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(55, 'Palmolive Anti-Dandruff', 15.00, 12, 'Non-Food', 'Shampoo', 'uploads/supplies/Palmolive Anti-Dandruff Shampoo.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(56, 'Dove', 15.00, 12, 'Non-Food', 'Shampoo', 'uploads/supplies/Dove Shampoo.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(57, 'Empress Keratin', 15.00, 11, 'Non-Food', 'Conditioner', 'uploads/supplies/Empress Keratin Conditioner.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(58, 'Creamsilk Conditioner', 15.00, 12, 'Non-Food', 'Conditioner', 'uploads/supplies/Creamsilk Conditioner.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(59, 'Trust Condom (3pcs)', 60.00, 12, 'Non-Food', 'Personal Protection', 'uploads/supplies/Trust Condom Boxed 3pcs.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(60, 'Disposable Spoon', 2.50, 11, 'Non-Food', 'Disposable Utensils', 'uploads/supplies/Disposable Spoon.jpg', 'available', '2025-11-01 03:59:55', 0, NULL),
(61, 'Disposable Fork', 2.50, 12, 'Non-Food', 'Disposable Utensils', 'uploads/supplies/Disposable Fork.jpg', 'available', '2025-11-01 03:59:55', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Receptionist','Guest') NOT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `username`, `email`, `password`, `role`, `status`, `reset_token`, `reset_expires`, `is_archived`, `archived_at`) VALUES
(1, 'Sung Jin-woo', 'jinwhoo', 'example@email.com', '$2y$10$SWb8Zhwunhl3t7HHsLst.O31OwvI1Tie5xsX.kVKPV0KnG5ok7JOm', 'Admin', 'approved', NULL, NULL, 0, NULL),
(2, 'Baki Hanma', 'bakii', 'example@email.com', '$2y$10$zKqannJ7cL97TAumEeByUukNozIwEb.AGsP//jD7Fmgo0CPyL2Q6K', 'Receptionist', 'approved', NULL, NULL, 0, NULL),
(3, 'Megumi Fushiguro', 'meg_umi', 'example@email.com', '$2y$10$YQ3y5JtTR65YitLygESF3.GuuqddiFkikr5byhF0/GSUINDZf1lwO', 'Receptionist', 'approved', NULL, NULL, 1, '2025-11-18 17:10:40'),
(9, 'Angel', 'angels', 'example@email.com', '$2y$10$H6tYQO141eRMx/Mv559Ru.RMW6BP.Y3wgC3ayrKZnM0CqMXMuSKIe', 'Receptionist', 'approved', NULL, NULL, 1, '2025-11-18 17:10:21'),
(12, 'dev test', 'devtest', 'devtest@gmail.com', '$2y$10$/Z.vroBzK8Hs.YVAdgbeOebq9V8Y/KWh/D0fmIQi2izX4J4WT61sa', 'Receptionist', 'pending', NULL, NULL, 1, '2025-11-18 17:10:47'),
(13, 'dev test two', 'devtest2', 'devtest2@gmail.com', '$2y$10$XHqW8K/xBRUlkBeh/k/MOeh09sVLgVkFf8B4FYaplXDRijllQJany', 'Receptionist', 'pending', NULL, NULL, 1, '2025-11-18 17:10:44'),
(14, 'John Doe ', 'john', 'johndoe@gmail.com', '$2y$10$civ0BziV5mRIMw74tA55auch53rQWh/OzkwOnWhAWQ3d4KhJd1T5O', 'Receptionist', 'pending', NULL, NULL, 1, '2025-10-27 17:28:29'),
(15, 'Jane Smith', 'Jane', 'janesmith@gmail.com', '$2y$10$yzohZ7y9tNgsT9lvUthO0.wOdB6BVscssBZ1hR9WxGvkTCRyKfY3K', 'Receptionist', 'pending', NULL, NULL, 1, '2025-11-18 17:11:49'),
(16, 'Emily Carter', 'Emily', 'emilycarter@gmail.com', '$2y$10$NW9eYV84Y6ZIxqZvp1p/ZOFxEKNAnBLwgT/voyQyWjonuebkoPuPe', 'Receptionist', 'pending', NULL, NULL, 1, '2025-11-18 17:11:24'),
(17, 'David Brown', 'David', 'davidbrown@gmail.com', '$2y$10$U7dy9bciBLhUV.85JPyKCecaFAL/N7aUJ3QllCH/9MVG.78IE8/v.', 'Receptionist', 'pending', NULL, NULL, 1, '2025-10-27 17:28:26'),
(18, 'Michael Johnson', 'michael', 'michaeljohnson@gmail.com', '$2y$10$BGSXU2IlDvzQ9CjQu6qIsOzFI8F00i96qRN2wN4FeKQNsJfPFZKly', 'Receptionist', 'pending', NULL, NULL, 1, '2025-11-18 17:11:37'),
(30, 'Cyeanne Jade Looez', 'Cyeanne_Jade', 'lopezcyeanne0318@gmail.com', '$2y$10$GNoGIU.6cwLROg2zwqFAhOikWCDSIVi/z881a75c18o/TOAADqOTq', 'Receptionist', 'approved', '0cbe5ed1589dfbf0c887300a179f324d70ac53be0af6ded74c271bd113695827', '2025-11-11 15:13:56', 0, NULL),
(31, 'Krystelle Dapitan', 'krystelle', 'krystelledapitan06@gmail.com', '$2y$10$r8jOx/t3Q.ArezK2xtODMuHYD58RrDtMFyIur/31WCdOrlihG7BEm', 'Receptionist', 'approved', NULL, NULL, 0, NULL);

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
-- Indexes for table `remarks`
--
ALTER TABLE `remarks`
  ADD PRIMARY KEY (`id`);

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
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `supplies`
--
ALTER TABLE `supplies`
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `checkins`
--
ALTER TABLE `checkins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=337;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `guests`
--
ALTER TABLE `guests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `keycards`
--
ALTER TABLE `keycards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `receptionist_profiles`
--
ALTER TABLE `receptionist_profiles`
  MODIFY `profile_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `remarks`
--
ALTER TABLE `remarks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `stock_logs`
--
ALTER TABLE `stock_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplies`
--
ALTER TABLE `supplies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
