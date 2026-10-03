-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 24, 2026 at 06:03 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `courier`
--

-- --------------------------------------------------------

--
-- Table structure for table `courier_requests`
--

CREATE TABLE `courier_requests` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `partner_id` int(11) DEFAULT NULL,
  `parcel_name` varchar(200) NOT NULL,
  `parcel_weight` decimal(5,2) DEFAULT NULL,
  `parcel_description` text DEFAULT NULL,
  `from_location` varchar(255) NOT NULL,
  `to_location` varchar(255) NOT NULL,
  `from_lat` decimal(10,7) DEFAULT NULL,
  `from_lng` decimal(10,7) DEFAULT NULL,
  `to_lat` decimal(10,7) DEFAULT NULL,
  `to_lng` decimal(10,7) DEFAULT NULL,
  `pickup_address` text NOT NULL,
  `delivery_address` text NOT NULL,
  `receiver_name` varchar(100) DEFAULT NULL,
  `receiver_phone` varchar(20) DEFAULT NULL,
  `receiver_email` varchar(150) DEFAULT NULL,
  `reward_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','accepted','picked_up','awaiting_payment','payment_done','in_transit','delivered','cancelled') DEFAULT 'pending',
  `pickup_otp` varchar(6) DEFAULT NULL,
  `delivery_otp` varchar(6) DEFAULT NULL,
  `pickup_otp_verified` tinyint(1) DEFAULT 0,
  `delivery_otp_verified` tinyint(1) DEFAULT 0,
  `admin_verified` tinyint(1) DEFAULT 0,
  `razorpay_order_id` varchar(100) DEFAULT NULL,
  `razorpay_payment_id` varchar(100) DEFAULT NULL,
  `payment_status` enum('unpaid','pending','paid','released') DEFAULT 'unpaid',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `partner_routes`
--

CREATE TABLE `partner_routes` (
  `id` int(11) NOT NULL,
  `partner_id` int(11) NOT NULL,
  `from_location` varchar(255) NOT NULL,
  `to_location` varchar(255) NOT NULL,
  `from_lat` decimal(10,7) DEFAULT NULL,
  `from_lng` decimal(10,7) DEFAULT NULL,
  `to_lat` decimal(10,7) DEFAULT NULL,
  `to_lng` decimal(10,7) DEFAULT NULL,
  `travel_date` date NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `id` int(11) NOT NULL,
  `courier_id` int(11) NOT NULL,
  `rated_by` int(11) NOT NULL,
  `rated_user` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `review` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tracking_updates`
--

CREATE TABLE `tracking_updates` (
  `id` int(11) NOT NULL,
  `courier_id` int(11) NOT NULL,
  `status` varchar(100) NOT NULL,
  `note` text DEFAULT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `traveler_locations`
--

CREATE TABLE `traveler_locations` (
  `id` int(11) NOT NULL,
  `courier_id` int(11) NOT NULL,
  `partner_id` int(11) NOT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `accuracy` float DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `aadhaar_img` varchar(255) DEFAULT NULL,
  `photo_img` varchar(255) DEFAULT NULL,
  `bank_account` varchar(30) DEFAULT NULL,
  `ifsc_code` varchar(15) DEFAULT NULL,
  `branch_name` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('customer','partner','admin') DEFAULT 'customer',
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `aadhaar_img`, `photo_img`, `bank_account`, `ifsc_code`, `branch_name`, `password`, `role`, `is_verified`, `created_at`) VALUES
(1, 'Admin', 'admin@courier.com', '9999999999', NULL, NULL, NULL, NULL, NULL, '$2y$10$.LIrmfhA4MaK5NYN2ceSheN7VTn8r5KL2yIs/JFqozMjG/9xHvRpO', 'admin', 1, '2026-03-22 20:25:49');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `courier_requests`
--
ALTER TABLE `courier_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `partner_id` (`partner_id`);

--
-- Indexes for table `partner_routes`
--
ALTER TABLE `partner_routes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `partner_id` (`partner_id`);

--
-- Indexes for table `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `courier_id` (`courier_id`);

--
-- Indexes for table `tracking_updates`
--
ALTER TABLE `tracking_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `courier_id` (`courier_id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `traveler_locations`
--
ALTER TABLE `traveler_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_courier` (`courier_id`),
  ADD KEY `partner_id` (`partner_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `courier_requests`
--
ALTER TABLE `courier_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `partner_routes`
--
ALTER TABLE `partner_routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tracking_updates`
--
ALTER TABLE `tracking_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `traveler_locations`
--
ALTER TABLE `traveler_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `courier_requests`
--
ALTER TABLE `courier_requests`
  ADD CONSTRAINT `courier_requests_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `courier_requests_ibfk_2` FOREIGN KEY (`partner_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `partner_routes`
--
ALTER TABLE `partner_routes`
  ADD CONSTRAINT `partner_routes_ibfk_1` FOREIGN KEY (`partner_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `ratings`
--
ALTER TABLE `ratings`
  ADD CONSTRAINT `ratings_ibfk_1` FOREIGN KEY (`courier_id`) REFERENCES `courier_requests` (`id`);

--
-- Constraints for table `tracking_updates`
--
ALTER TABLE `tracking_updates`
  ADD CONSTRAINT `tracking_updates_ibfk_1` FOREIGN KEY (`courier_id`) REFERENCES `courier_requests` (`id`),
  ADD CONSTRAINT `tracking_updates_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `traveler_locations`
--
ALTER TABLE `traveler_locations`
  ADD CONSTRAINT `traveler_locations_ibfk_1` FOREIGN KEY (`courier_id`) REFERENCES `courier_requests` (`id`),
  ADD CONSTRAINT `traveler_locations_ibfk_2` FOREIGN KEY (`partner_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
