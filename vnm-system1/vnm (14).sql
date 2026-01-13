-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 10, 2026 at 07:03 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `vnm`
--

-- --------------------------------------------------------

--
-- Table structure for table `cars`
--

CREATE TABLE `cars` (
  `car_id` int(11) NOT NULL,
  `model` varchar(100) NOT NULL,
  `plate_no` varchar(20) NOT NULL,
  `car_brand` varchar(100) NOT NULL,
  `year` year(4) NOT NULL,
  `daily_rate` decimal(10,2) NOT NULL,
  `owner` varchar(150) NOT NULL,
  `fuel_type` varchar(50) NOT NULL,
  `transmission` varchar(50) NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `location_id` int(11) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `availability` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cars`
--

INSERT INTO `cars` (`car_id`, `model`, `plate_no`, `car_brand`, `year`, `daily_rate`, `owner`, `fuel_type`, `transmission`, `is_available`, `location_id`, `image`, `availability`, `description`) VALUES
(8, 'Toyota All-new Innova', 'ABC 123', 'Toyota', '2025', 1500.00, 'John Doe', 'Diesel', 'Automatic', 1, 319337317, '1764358454_B1.jpg', 1, 'The Toyota All-new Innova 2025 is a popular and spacious seven-to-eight-seater MPV known for blending a comfortable cabin and reliable performance with a durable chassis, making it the ideal versatile vehicle for families and commercial use.'),
(9, 'Mitsubishi Montero Sport', 'DEF 456', 'Mitsubishi ', '2025', 2000.00, 'Jane Smith', 'Diesel', 'Manual', 0, 219323317, '1764352916_A1.jpg', 1, 'The Mitsubishi Montero Sport is a versatile seven-seater SUV balancing a comfortable cabin and modern features with a durable, body-on-frame chassis and advanced Super Select 4WD-II system for reliable off-road capability.'),
(14, 'Ford Ranger Raptor', 'GHI 789', 'Ford', '2024', 2500.00, 'Alex Johnson', 'Diesel', 'Automatic', 0, 419347317, '1764357777_hennessey-velociraptor-500-ford-ranger-raptor.jpg', 1, 'The 2024 Ford Raptor is the pinnacle of factory off-road performance, featuring a potent twin-turbo V6 engine and advanced, dynamically adjustable FOX Live Valve suspension engineered for high-speed dominance across severe desert terrain.'),
(15, 'Mitsubishi L300', 'JKL 001', 'Mitsubishi', '2025', 1200.00, 'Robert Brown', 'Diesel', 'Manual', 0, 519357317, '1764358082_mitsubishi-l300-front-side-view-970171.avif', 1, 'The 2025 Mitsubishi L300 is a highly efficient commercial vehicle, blending a fuel-sipping Euro 4 diesel engine with a rugged, high-payload chassis that minimizes maintenance costs and maximizes operational time.'),
(19, 'Ford Everest', 'XYZ 101', 'Ford', '2024', 2200.00, 'Sarah Connor', 'Diesel', 'Automatic', 0, 600000000, '1764962956_2024-04-2024-ford-everest-sport-4x4-v6-hero-16x9-1.webp', 1, 'The 2024 Ford Everest is a rugged and sophisticated seven-seater SUV, known for its comfortable interior, advanced technology, and excellent off-road capability.');

-- --------------------------------------------------------

--
-- Table structure for table `car_images`
--

CREATE TABLE `car_images` (
  `image_id` int(11) NOT NULL,
  `car_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `car_images`
--

INSERT INTO `car_images` (`image_id`, `car_id`, `image_path`) VALUES
(28, 9, '1764352960_A3.jpg'),
(29, 9, '1764352960_A2.jpg'),
(30, 9, '1764352960_A4.jpg'),
(31, 9, '1764352960_A5.jpg'),
(36, 14, '1764357789_2024-ford-ranger-raptor-119-645bb6259623c.avif'),
(37, 14, '1764357790_ford-ranger-raptor-set-for-q1-2024.webp'),
(38, 14, '1764357790_2024-Ford-Ranger-Raptor-CarScoops-201.jpg'),
(39, 14, '1764357790_006-2024-ford-ranger-raptor-first-drive-front-view-jpg.webp'),
(40, 15, '1764358118_L300-Dashboard-FA.jpg'),
(41, 15, '1764358118_mitsubishi-l300-full-front-view-239623.avif'),
(42, 15, '1764358118_mitsubishi-l300-full-rear-view-376423.avif'),
(43, 15, '1764358118_mitsubishi-l300-side-view-478865.avif'),
(45, 8, '1764358481_B5.jpg'),
(46, 8, '1764358481_B4.jpg'),
(47, 8, '1764358481_B3.jpg'),
(48, 8, '1764358481_B2.jpg'),
(49, 19, '1764963083_2024-04-ford-everest-sport-4x4-my24-stills-17.jpg'),
(50, 19, '1764963083_2024-04-ford-everest-sport-4x4-my24-stills-3.webp'),
(51, 19, '1764963083_2024-04-ford-everest-sport-4x4-my24-stills-25.webp'),
(52, 19, '1764963083_2024-04-ford-everest-sport-4x4-my24-stills-8.webp');

-- --------------------------------------------------------

--
-- Table structure for table `rental_extension_requests`
--

CREATE TABLE `rental_extension_requests` (
  `extension_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `days_to_extend` int(11) NOT NULL,
  `new_end_date` date NOT NULL,
  `additional_cost` decimal(10,2) NOT NULL,
  `payment_proof_path` varchar(255) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference_no` varchar(100) DEFAULT NULL,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Paid_Pending_Approval','Approved','Rejected') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rental_pickup_details`
--

CREATE TABLE `rental_pickup_details` (
  `pickup_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `pickup_admin_id` int(11) DEFAULT NULL,
  `pickup_date_actual` datetime NOT NULL,
  `car_condition_pickup` text DEFAULT NULL,
  `odometer_pickup` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rental_pickup_details`
--

INSERT INTO `rental_pickup_details` (`pickup_id`, `request_id`, `pickup_admin_id`, `pickup_date_actual`, `car_condition_pickup`, `odometer_pickup`) VALUES
(36, 96, NULL, '2025-12-12 00:00:00', 'gg', 14000),
(37, 97, NULL, '2025-12-12 00:00:00', 'gab\r\n', 15000),
(38, 98, NULL, '2025-12-12 00:00:00', 'gg', 15555),
(59, 119, NULL, '2025-12-13 00:00:00', 'ggs', 1000),
(60, 120, NULL, '2025-12-13 00:00:00', 'GGS', 1000),
(72, 132, NULL, '2026-01-09 00:00:00', 'full', 15000),
(73, 133, NULL, '2026-01-09 00:00:00', 'gab', 15000),
(74, 134, NULL, '2026-01-09 00:00:00', 'gg', 15000),
(75, 135, NULL, '2026-01-10 00:00:00', '....', 123),
(76, 136, NULL, '2026-01-10 00:00:00', 'hahaha', 123456),
(77, 137, NULL, '2026-01-10 00:00:00', 'jjj', 12341),
(78, 138, NULL, '2026-01-10 00:00:00', 'ggs', 15000),
(79, 139, NULL, '2026-01-10 00:00:00', 'djwdwdjwg', 1500);

-- --------------------------------------------------------

--
-- Table structure for table `rental_requests`
--

CREATE TABLE `rental_requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `car_id` int(11) NOT NULL,
  `driver_license_photo` varchar(500) DEFAULT NULL,
  `rental_date` date NOT NULL,
  `rental_time` time NOT NULL,
  `rental_duration_days` int(11) NOT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `odometer_pickup` int(11) DEFAULT NULL,
  `condition_pickup` text DEFAULT NULL,
  `actual_pickup_datetime` datetime DEFAULT NULL,
  `payment_proof_path` varchar(255) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference_no` varchar(100) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `request_status` enum('Pending','Approved','Rejected','Cancelled','Picked Up','Returned','Early Return Requested','Early_Return_Approved','Early_Return_Scheduled') NOT NULL DEFAULT 'Pending',
  `request_timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_status` enum('Unpaid','Paid','Proof Uploaded') NOT NULL DEFAULT 'Unpaid',
  `rental_lifecycle_status` enum('Scheduled','PickedUp','OnRide','Returned') NOT NULL DEFAULT 'Scheduled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rental_requests`
--

INSERT INTO `rental_requests` (`request_id`, `user_id`, `car_id`, `driver_license_photo`, `rental_date`, `rental_time`, `rental_duration_days`, `total_cost`, `odometer_pickup`, `condition_pickup`, `actual_pickup_datetime`, `payment_proof_path`, `payment_method`, `payment_reference_no`, `admin_notes`, `request_status`, `request_timestamp`, `payment_status`, `rental_lifecycle_status`) VALUES
(96, 9, 15, 'uploads/licenses/license_693c3fd69838d7.71876636.jpg', '2025-12-13', '13:16:00', 1, 1200.00, NULL, NULL, NULL, 'uploads/payments/proof_96_693c3fe9ea646.jpg', 'gcash', '123', NULL, '', '2025-12-13 00:16:22', 'Proof Uploaded', 'Scheduled'),
(97, 9, 15, 'uploads/licenses/license_693c48f9df3957.52737151.png', '2025-12-12', '00:55:00', 5, 6000.00, NULL, NULL, NULL, 'uploads/payments/proof_97_693c4910427f1.jpg', 'gcash', '123', NULL, '', '2025-12-13 00:55:21', 'Proof Uploaded', 'Scheduled'),
(98, 9, 15, 'uploads/licenses/license_693c495f6775a3.29406395.png', '2025-12-12', '00:55:00', 5, 1200.00, NULL, NULL, NULL, 'uploads/payments/proof_98_693c49908c8d1.jpg', 'gcash', '123', NULL, '', '2025-12-13 00:57:03', 'Proof Uploaded', 'Scheduled'),
(119, 9, 15, 'uploads/licenses/license_693cc2ed12b3e2.84608704.jpg', '2025-12-13', '10:30:00', 10, 12000.00, NULL, NULL, NULL, 'uploads/payments/proof_119_693cc302f3a08.jpg', 'gcash', '123456789', NULL, '', '2025-12-13 09:35:41', 'Proof Uploaded', 'Scheduled'),
(120, 9, 15, 'uploads/licenses/license_693cc4a6278060.99222339.jpg', '2025-12-13', '11:45:00', 10, 12000.00, NULL, NULL, NULL, 'uploads/payments/proof_120_693cc4bb6e900.jpg', 'gcash', '123', NULL, '', '2025-12-13 09:43:02', 'Proof Uploaded', 'Scheduled'),
(132, 16, 19, 'uploads/licenses/license_69612629b143a5.83049655.jpg', '2026-01-10', '11:00:00', 4, 8800.00, NULL, NULL, NULL, 'uploads/payments/proof_132_69612649e91e6.jpg', 'gcash', '123456789', NULL, 'Returned', '2026-01-10 00:00:41', 'Proof Uploaded', 'Scheduled'),
(133, 16, 19, 'uploads/licenses/license_69615c0a65f0b6.27306341.png', '2026-01-22', '10:50:00', 3, 6600.00, NULL, NULL, NULL, 'uploads/payments/proof_133_69615c31b97dc.png', 'gcash', '123456789', NULL, 'Returned', '2026-01-10 03:50:34', 'Proof Uploaded', 'Scheduled'),
(134, 16, 19, 'uploads/licenses/license_69617500a5aec1.89206079.jpg', '2026-01-10', '11:40:00', 4, 8800.00, NULL, NULL, NULL, 'uploads/payments/proof_134_6961751d0a759.jpg', 'gcash', '12345', NULL, 'Returned', '2026-01-10 05:37:04', 'Proof Uploaded', 'Scheduled'),
(135, 16, 19, 'uploads/licenses/license_6961a19114f7d6.57308534.jpg', '2026-01-22', '20:46:00', 1, 2200.00, NULL, NULL, NULL, 'uploads/payments/proof_135_6961a1b727f14.jpg', 'gcash', '1234567890', NULL, 'Returned', '2026-01-10 08:47:13', 'Proof Uploaded', 'Scheduled'),
(136, 16, 19, 'uploads/licenses/license_6961a28bd52720.92028273.jpg', '2026-01-23', '20:50:00', 5, 11000.00, NULL, NULL, NULL, 'uploads/payments/proof_136_6961a2c64d450.jpg', 'gcash', '1234567890', NULL, 'Returned', '2026-01-10 08:51:23', 'Proof Uploaded', 'Scheduled'),
(137, 16, 19, 'uploads/licenses/license_6961a54970f711.76797004.jpg', '2026-01-18', '21:02:00', 6, 13200.00, NULL, NULL, NULL, 'uploads/payments/proof_137_6961a5861c46b.jpg', 'gcash', '123456789', NULL, 'Returned', '2026-01-10 09:03:05', 'Proof Uploaded', 'Scheduled'),
(138, 16, 19, 'uploads/licenses/license_6961ae5d9638f3.48988382.jpg', '2026-01-12', '00:00:00', 5, 11000.00, NULL, NULL, NULL, 'uploads/payments/proof_138_6961ae9199eaf.jpg', 'gcash', '1234567890', NULL, 'Returned', '2026-01-10 09:41:49', 'Proof Uploaded', 'Scheduled'),
(139, 16, 19, 'uploads/licenses/license_6961b1d9650a85.19385916.jpg', '2026-01-10', '22:55:00', 2, 4400.00, NULL, NULL, NULL, 'uploads/payments/proof_139_6961b2293a524.png', 'gcash', '1', NULL, 'Picked Up', '2026-01-10 09:56:41', 'Proof Uploaded', 'Scheduled');

-- --------------------------------------------------------

--
-- Table structure for table `rental_return_details`
--

CREATE TABLE `rental_return_details` (
  `return_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `return_admin_id` int(11) DEFAULT NULL,
  `return_date_actual` datetime NOT NULL,
  `car_condition_return` text DEFAULT NULL,
  `odometer_return` int(11) DEFAULT NULL,
  `damage_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `final_refund_amount` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rental_return_details`
--

INSERT INTO `rental_return_details` (`return_id`, `request_id`, `return_admin_id`, `return_date_actual`, `car_condition_return`, `odometer_return`, `damage_fee`, `final_refund_amount`) VALUES
(49, 132, 1, '2026-01-12 23:00:00', '0', 15001, 0.00, 0.00),
(50, 133, 1, '2026-01-09 20:52:00', '0', 15000, 0.00, 4400.00),
(51, 134, 1, '2026-01-12 11:40:00', '0', 15001, 0.00, 0.00),
(52, 135, 1, '2026-01-10 01:54:00', '0', 155, 0.00, 0.00),
(53, 136, 1, '2026-01-10 01:57:00', '0', 1234156789, 0.00, 8800.00),
(54, 137, 1, '2026-01-10 02:37:00', '0', 121212, 0.00, 11000.00),
(55, 138, 1, '2026-01-10 02:50:00', '0', 15000, 0.00, 8800.00);

-- --------------------------------------------------------

--
-- Table structure for table `rental_return_requests`
--

CREATE TABLE `rental_return_requests` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `requested_at` datetime NOT NULL,
  `total_deducted_cost` decimal(10,2) NOT NULL COMMENT 'Cost based on requested return date',
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `scheduled_return_date` date DEFAULT NULL,
  `scheduled_return_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rental_return_requests`
--

INSERT INTO `rental_return_requests` (`id`, `request_id`, `user_id`, `requested_at`, `total_deducted_cost`, `status`, `scheduled_return_date`, `scheduled_return_time`) VALUES
(34, 132, 16, '2026-01-09 00:00:00', 8800.00, 'Returned', '2026-01-12', '11:00:00'),
(35, 134, 16, '2026-01-09 00:00:00', 8800.00, 'Returned', '2026-01-12', '11:40:00'),
(36, 136, 16, '2026-01-10 00:00:00', 2200.00, 'Returned', '2026-01-11', '20:57:00'),
(37, 137, 16, '2026-01-10 00:00:00', 2200.00, 'Returned', '2026-01-10', '22:36:00'),
(38, 138, 16, '2026-01-10 00:00:00', 2200.00, 'Returned', '2026-01-20', '01:50:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `fullname` varchar(200) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `license` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `status` int(11) DEFAULT 1,
  `is_archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `fullname`, `email`, `phone`, `address`, `license`, `password`, `status`, `is_archived`) VALUES
(9, 'Gregory Anton Benedict Bailon', ' gabbailon123@gmail.com', '09369500827', 'GG 123', 'GGG 123', '123456789', 1, 0),
(10, 'Gab Bailon', 'gabbailon5@gmail.com', '09369500827', 'Bacoor Cavite', 'ABC 123', 'gg123456789', 1, 0),
(11, 'GAB BAILONNN', 'gabbailon12345@gmail.com', '09354431937', 'Bacoor Cavite', 'ACC 123', '$2y$10$qdH.KIJPVzi4GTUjPjwWcOmBkkL2aujEzUHM1/iNi54Fyk6.r1hg.', 1, 0),
(15, 'ariana grande', 'geromeemmanuel.param@cvsu.edu.ph', '09270277139', 'imus city', 'ABC 123', '$2y$10$BFG6hEgVBji7zTtqxcueSumOKaYAcBqNsfQgc5WKTiW2IhX3/DuWe', 1, 0),
(16, 'demi lovatoo', 'demilovato@g.com', '09270277139', 'imus', 'ABC 123', 'gg123456789', 1, 0),
(17, 'cynthia erivoo', 'wickedwitch@gmail.com', '09111123456', 'Emerald city', 'ABC 123', '$2y$10$o5w0jMPHbPs/qVdgyOiCzeUaWINSSLu3tzJb5UM86eNq267mMWgT.', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `usersreset`
--

CREATE TABLE `usersreset` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `reset_code` varchar(6) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cars`
--
ALTER TABLE `cars`
  ADD PRIMARY KEY (`car_id`);

--
-- Indexes for table `car_images`
--
ALTER TABLE `car_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `car_id` (`car_id`);

--
-- Indexes for table `rental_extension_requests`
--
ALTER TABLE `rental_extension_requests`
  ADD PRIMARY KEY (`extension_id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `rental_pickup_details`
--
ALTER TABLE `rental_pickup_details`
  ADD PRIMARY KEY (`pickup_id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `rental_requests`
--
ALTER TABLE `rental_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `car_id` (`car_id`);

--
-- Indexes for table `rental_return_details`
--
ALTER TABLE `rental_return_details`
  ADD PRIMARY KEY (`return_id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `rental_return_requests`
--
ALTER TABLE `rental_return_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `usersreset`
--
ALTER TABLE `usersreset`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cars`
--
ALTER TABLE `cars`
  MODIFY `car_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `car_images`
--
ALTER TABLE `car_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `rental_extension_requests`
--
ALTER TABLE `rental_extension_requests`
  MODIFY `extension_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `rental_pickup_details`
--
ALTER TABLE `rental_pickup_details`
  MODIFY `pickup_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `rental_requests`
--
ALTER TABLE `rental_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;

--
-- AUTO_INCREMENT for table `rental_return_details`
--
ALTER TABLE `rental_return_details`
  MODIFY `return_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `rental_return_requests`
--
ALTER TABLE `rental_return_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `usersreset`
--
ALTER TABLE `usersreset`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `car_images`
--
ALTER TABLE `car_images`
  ADD CONSTRAINT `car_images_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`car_id`) ON DELETE CASCADE;

--
-- Constraints for table `rental_extension_requests`
--
ALTER TABLE `rental_extension_requests`
  ADD CONSTRAINT `extension_fk_rental` FOREIGN KEY (`request_id`) REFERENCES `rental_requests` (`request_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `extension_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `rental_pickup_details`
--
ALTER TABLE `rental_pickup_details`
  ADD CONSTRAINT `rental_pickup_details_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `rental_requests` (`request_id`) ON DELETE CASCADE;

--
-- Constraints for table `rental_requests`
--
ALTER TABLE `rental_requests`
  ADD CONSTRAINT `fk_rental_car` FOREIGN KEY (`car_id`) REFERENCES `cars` (`car_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rental_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `rental_return_details`
--
ALTER TABLE `rental_return_details`
  ADD CONSTRAINT `rental_return_details_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `rental_requests` (`request_id`) ON DELETE CASCADE;

--
-- Constraints for table `rental_return_requests`
--
ALTER TABLE `rental_return_requests`
  ADD CONSTRAINT `rental_return_requests_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `rental_requests` (`request_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
