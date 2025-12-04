-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 20, 2025 at 08:23 PM
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
  `car_brand` varchar(100) NOT NULL,
  `year` year(4) NOT NULL,
  `daily_rate` decimal(10,2) NOT NULL,
  `fuel_type` varchar(50) NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `location_id` int(11) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `availability` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cars`
--

INSERT INTO `cars` (`car_id`, `model`, `car_brand`, `year`, `daily_rate`, `fuel_type`, `is_available`, `location_id`, `image`, `availability`) VALUES
(8, 'Toyota Innova', 'Toyota', '2023', 1500.00, 'Diesel', 1, 319337317, '1763666254_toyota-innova-facelift-2023-1677055189.jpg', 1),
(9, 'Mitsubishi Montero Sport', 'Mitsubishi ', '2025', 2000.00, 'Diesel', 1, 219323317, '1763666240_mitsubishi-montero-sport-front-angle-low-view-683021.avif', 0),
(10, 'Nissan Navara', 'Nissan', '2024', 2000.00, 'Diesel', 1, 23117317, '1763666230_2018_Nissan_Navara_Tekna_DCi_Automatic_2.3.jpg', 2);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cars`
--
ALTER TABLE `cars`
  ADD PRIMARY KEY (`car_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cars`
--
ALTER TABLE `cars`
  MODIFY `car_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
