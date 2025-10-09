-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 09, 2025 at 03:54 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.16

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
(18, 'DEV TEST', 'This is a dev test.', 'Admin', '2025-09-26 05:28:53');

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
(41, 'ryan', 'jumpercraft1@gmail.com', 'bunggo', '09123456123', 18, 2, '101', '3', 'Cash', '', 'BK20251009519A0B', 400, 400, 0, '2025-10-09 11:54:00', '2025-10-09 14:54:00', 'upcoming', '2025-10-09 03:53:09', NULL, NULL);

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
  `gcash_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receptionist_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `checkins`
--

INSERT INTO `checkins` (`id`, `guest_name`, `address`, `telephone`, `room_number`, `room_type`, `stay_duration`, `total_price`, `amount_paid`, `change_amount`, `payment_mode`, `check_in_date`, `check_out_date`, `gcash_reference`, `receptionist_id`) VALUES
(109, '1211', 'bunggo', '09123456789', 101, '', 3, 400.00, 400.00, 400.00, 'cash', '2025-10-09 11:34:30', '2025-10-09 11:52:34', '', 2),
(110, 'ryan', 'bunggo', '09123456789', 101, '', 3, 400.00, 400.00, 400.00, 'cash', '2025-10-09 11:53:23', '2025-10-09 14:53:23', '', 2);

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
(5, 101, NULL, NULL, '316D7F5F', '2025-10-07 22:23:57', '2035-10-07 22:23:57', 'expired');

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
(1, 101, 'standard_room', 'booked', '2025-04-26 16:34:08', '2025-10-09 03:53:23', 400.00, 750.00, 1100.00, 1500.00, 120.00),
(2, 102, 'standard_room', 'available', '2025-04-26 17:37:56', '2025-10-05 15:14:06', 400.00, 750.00, 1100.00, 1500.00, 120.00),
(10, 103, 'standard_room', 'available', '2025-04-28 16:01:31', '2025-09-28 09:08:16', 300.00, 750.00, 1100.00, 1500.00, 120.00),
(12, 104, 'standard_room', 'available', '2025-04-29 13:29:43', '2025-10-05 00:16:42', 400.00, 750.00, 1100.00, 1500.00, 120.00),
(13, 106, 'twin_room', 'available', '2025-05-07 06:15:36', '2025-06-24 04:56:41', 400.00, 750.00, 1100.00, 1500.00, 120.00);

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
-- Table structure for table `stays`
--

CREATE TABLE `stays` (
  `id` int NOT NULL,
  `guest_name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text,
  `telephone` varchar(20) DEFAULT NULL,
  `age` int DEFAULT NULL,
  `num_people` int DEFAULT NULL,
  `room_number` int NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `payment_mode` enum('cash','gcash') DEFAULT 'cash',
  `reference_number` varchar(100) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `change_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('upcoming','booked','checked_in','completed','cancelled','no_show') DEFAULT 'upcoming',
  `source` enum('online','walkin') DEFAULT 'walkin',
  `booking_token` varchar(50) DEFAULT NULL,
  `cancellation_reason` text,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
(2, 1, 'in', 20, '', '2025-08-04 03:52:16', 'in');

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
(1, 'Broom', 150.00, 20, 'Cleaning', '2025-07-09 14:07:48'),
(3, 'Toilet Papers', 100.00, 8, 'Cleaning', '2025-07-09 14:56:50'),
(5, 'Towels', 20.00, 30, 'Cleaning', '2025-08-04 03:51:34');

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
  `status` enum('pending','approved','denied') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `username`, `email`, `password`, `role`, `status`) VALUES
(1, 'Sung Jin-woo', 'jinwhoo', 'example@email.com', '$2y$10$SWb8Zhwunhl3t7HHsLst.O31OwvI1Tie5xsX.kVKPV0KnG5ok7JOm', 'Admin', 'approved'),
(2, 'Baki Hanma', 'bakii', 'example@email.com', '$2y$10$zKqannJ7cL97TAumEeByUukNozIwEb.AGsP//jD7Fmgo0CPyL2Q6K', 'Receptionist', 'approved'),
(3, 'Megumi Fushiguro', 'meg_umi', 'example@email.com', '$2y$10$YQ3y5JtTR65YitLygESF3.GuuqddiFkikr5byhF0/GSUINDZf1lwO', 'Receptionist', 'approved'),
(9, 'Angel', 'angels', 'example@email.com', '$2y$10$H6tYQO141eRMx/Mv559Ru.RMW6BP.Y3wgC3ayrKZnM0CqMXMuSKIe', 'Receptionist', 'approved'),
(12, 'aw', 'qw', 'jumpercraft1@gmail.com', '$2y$10$y/Mt5Wf/oQSRJ80HjYVtLO1kmKBMAKoaYgpFUMO3FKFnLLhol6yx2', 'Receptionist', 'pending');

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
-- Indexes for table `stays`
--
ALTER TABLE `stays`
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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `checkins`
--
ALTER TABLE `checkins`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `stays`
--
ALTER TABLE `stays`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stock_logs`
--
ALTER TABLE `stock_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplies`
--
ALTER TABLE `supplies`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
