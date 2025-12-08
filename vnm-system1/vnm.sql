-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 08, 2025 at 02:34 PM
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
(9, 'Mitsubishi Montero Sport', 'DEF 456', 'Mitsubishi ', '2025', 2000.00, 'Jane Smith', 'Diesel', 'Manual', 1, 219323317, '1764352916_A1.jpg', 1, 'The Mitsubishi Montero Sport is a versatile seven-seater SUV balancing a comfortable cabin and modern features with a durable, body-on-frame chassis and advanced Super Select 4WD-II system for reliable off-road capability.'),
(14, 'Ford Ranger Raptor', 'GHI 789', 'Ford', '2024', 2500.00, 'Alex Johnson', 'Diesel', 'Automatic', 0, 419347317, '1764357777_hennessey-velociraptor-500-ford-ranger-raptor.jpg', 1, 'The 2024 Ford Raptor is the pinnacle of factory off-road performance, featuring a potent twin-turbo V6 engine and advanced, dynamically adjustable FOX Live Valve suspension engineered for high-speed dominance across severe desert terrain.'),
(15, 'Mitsubishi L300', 'JKL 001', 'Mitsubishi', '2025', 1200.00, 'Robert Brown', 'Diesel', 'Manual', 0, 519357317, '1764358082_mitsubishi-l300-front-side-view-970171.avif', 1, 'The 2025 Mitsubishi L300 is a highly efficient commercial vehicle, blending a fuel-sipping Euro 4 diesel engine with a rugged, high-payload chassis that minimizes maintenance costs and maximizes operational time.'),
(19, 'Ford Everest', 'XYZ 101', 'Ford', '2024', 2200.00, 'Sarah Connor', 'Diesel', 'Automatic', 1, 600000000, '1764962956_2024-04-2024-ford-everest-sport-4x4-v6-hero-16x9-1.webp', 1, 'The 2024 Ford Everest is a rugged and sophisticated seven-seater SUV, known for its comfortable interior, advanced technology, and excellent off-road capability.');

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
(16, 70, 1, '2025-12-06 17:25:17', 'nine', 10000),
(17, 72, 1, '2025-12-06 17:45:36', 'hjk', 500);

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
  `request_status` enum('Pending','Approved','Rejected','Cancelled','Picked Up','Returned') NOT NULL DEFAULT 'Pending',
  `request_timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_status` enum('Unpaid','Paid','Proof Uploaded') NOT NULL DEFAULT 'Unpaid',
  `rental_lifecycle_status` enum('Scheduled','PickedUp','OnRide','Returned') NOT NULL DEFAULT 'Scheduled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rental_requests`
--

INSERT INTO `rental_requests` (`request_id`, `user_id`, `car_id`, `driver_license_photo`, `rental_date`, `rental_time`, `rental_duration_days`, `total_cost`, `odometer_pickup`, `condition_pickup`, `actual_pickup_datetime`, `payment_proof_path`, `payment_method`, `payment_reference_no`, `admin_notes`, `request_status`, `request_timestamp`, `payment_status`, `rental_lifecycle_status`) VALUES
(70, 15, 19, 'uploads/licenses/license_6933f44795d228.72217544.jpg', '2025-12-25', '20:15:00', 1, 2200.00, NULL, NULL, NULL, 'uploads/payments/proof_70_6933f496b3e48.jpg', 'gcash', '123456789', '', '', '2025-12-06 17:15:51', 'Paid', 'Scheduled'),
(72, 15, 15, 'uploads/licenses/license_6933f917c42986.49132825.jpg', '2025-12-30', '19:40:00', 1, 1200.00, NULL, NULL, NULL, 'uploads/payments/proof_72_6933fb1c45f6e.jpg', 'gcash', '123456', '', '', '2025-12-06 17:36:23', 'Proof Uploaded', 'Scheduled');

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
  `damage_fee` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rental_return_requests`
--

CREATE TABLE `rental_return_requests` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Approved','Denied','Cancelled') NOT NULL DEFAULT 'Pending',
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rental_return_requests`
--

INSERT INTO `rental_return_requests` (`id`, `request_id`, `user_id`, `requested_at`, `status`, `note`) VALUES
(1, 68, 15, '2025-12-06 16:46:51', 'Approved', NULL);

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
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `fullname`, `email`, `phone`, `address`, `license`, `password`) VALUES
(9, 'Gregory Anton Benedict Bailon', 'gabbailon123@gmail.com', '09369500827', 'GG 123', 'GGG 123', '$2y$10$hceGkUXqpXMNsIYDLnw81utIoKxPO1NUSyz1p37j5TKMcCfobeu1G'),
(10, 'Gab Bailon', 'gabbailon5@gmail.com', '09369500827', 'Bacoor Cavite', 'ABC 123', 'gg123456789'),
(11, 'GAB BAILONNN', 'gabbailon12345@gmail.com', '09354431937', 'Bacoor Cavite', 'ACC 123', '$2y$10$qdH.KIJPVzi4GTUjPjwWcOmBkkL2aujEzUHM1/iNi54Fyk6.r1hg.'),
(15, 'ariana grande', 'geromeemmanuel.param@cvsu.edu.ph', '09270277139', 'imus city', 'ABC 123', '$2y$10$BFG6hEgVBji7zTtqxcueSumOKaYAcBqNsfQgc5WKTiW2IhX3/DuWe'),
(16, 'demi lovato', 'demilovato@g.com', '09270277139', 'imus', 'ABC 123', '$2y$10$m29uFu84/iSkvYMjvRiw1eB0m62ql39ax/Av7BrjjzaqChSomJ5xO'),
(17, 'cynthia erivo', 'wickedwitch@gmail.com', '09111123456', 'Emerald city', 'ABC 123', '$2y$10$o5w0jMPHbPs/qVdgyOiCzeUaWINSSLu3tzJb5UM86eNq267mMWgT.');

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
  ADD KEY `request_id` (`request_id`),
  ADD KEY `user_id` (`user_id`);

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
-- AUTO_INCREMENT for table `rental_pickup_details`
--
ALTER TABLE `rental_pickup_details`
  MODIFY `pickup_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `rental_requests`
--
ALTER TABLE `rental_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `rental_return_details`
--
ALTER TABLE `rental_return_details`
  MODIFY `return_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `rental_return_requests`
--
ALTER TABLE `rental_return_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
