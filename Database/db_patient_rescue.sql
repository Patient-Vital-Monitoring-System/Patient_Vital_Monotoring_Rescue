-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 02, 2026 at 05:08 PM
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
-- Database: `db_patient_rescue`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `activity` text DEFAULT NULL,
  `log_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `username`, `activity`, `log_time`) VALUES
(1, 'admin', 'Logged into the system', '2026-03-02 15:10:16'),
(2, 'admin', 'Visited Dashboard', '2026-03-02 15:10:16'),
(3, 'admin', 'Visited Dashboard', '2026-03-02 15:10:30'),
(4, 'admin', 'Logged into the system', '2026-03-02 15:12:37'),
(5, 'admin', 'Visited Dashboard', '2026-03-02 15:16:24'),
(6, 'admin', 'Visited Dashboard', '2026-03-02 15:20:13'),
(7, 'admin', 'Visited Dashboard', '2026-03-02 15:20:39'),
(8, 'admin', 'Logged into the system', '2026-03-02 15:22:10'),
(9, 'admin', 'Visited Dashboard', '2026-03-02 15:22:10'),
(10, 'admin', 'Visited Dashboard', '2026-03-02 15:22:50'),
(11, 'admin', 'Visited Dashboard', '2026-03-02 15:23:10'),
(12, 'admin', 'Visited Dashboard', '2026-03-02 15:23:15'),
(13, 'admin', 'Visited Dashboard', '2026-03-02 15:28:17'),
(14, 'admin', 'Logged into the system', '2026-03-02 15:28:28'),
(15, 'admin', 'Visited Dashboard', '2026-03-02 15:28:28'),
(16, 'admin', 'Visited Dashboard', '2026-03-02 15:28:42'),
(17, 'admin', 'Visited Dashboard', '2026-03-02 15:28:58'),
(18, 'admin', 'Visited Dashboard', '2026-03-02 15:30:25'),
(19, 'admin', 'Visited Dashboard', '2026-03-02 15:32:20'),
(20, 'admin', 'Visited Dashboard', '2026-03-02 15:33:00'),
(21, 'admin', 'Visited Dashboard', '2026-03-02 15:33:01'),
(22, 'admin', 'Visited Dashboard', '2026-03-02 15:33:12'),
(23, 'admin', 'Logged into the system', '2026-03-02 15:33:51'),
(24, 'admin', 'Visited Dashboard', '2026-03-02 15:33:51'),
(25, 'admin', 'Visited Dashboard', '2026-03-02 15:37:27'),
(26, 'admin', 'Visited Dashboard', '2026-03-02 15:38:31'),
(27, 'admin', 'Visited Dashboard', '2026-03-02 15:41:35'),
(28, 'admin', 'Visited Dashboard', '2026-03-02 15:41:54'),
(29, 'admin', 'Visited Dashboard', '2026-03-02 15:45:03'),
(30, 'admin', 'Viewed Live Monitoring', '2026-03-02 15:45:06'),
(31, 'admin', 'Visited Dashboard', '2026-03-02 15:45:15'),
(32, 'admin', 'Visited Dashboard', '2026-03-02 15:47:03'),
(33, 'admin', 'Viewed Live Monitoring', '2026-03-02 15:47:07'),
(34, 'admin', 'Viewed Alerts', '2026-03-02 15:47:11'),
(35, 'admin', 'Added note for patient: Clint Tams', '2026-03-02 15:47:38'),
(36, 'admin', 'Visited Dashboard', '2026-03-02 15:47:58'),
(37, 'admin', 'Visited Dashboard', '2026-03-02 15:48:01');

-- --------------------------------------------------------

--
-- Table structure for table `alerts`
--

CREATE TABLE `alerts` (
  `alert_id` int(11) NOT NULL,
  `patient_name` varchar(100) DEFAULT NULL,
  `alert_message` text DEFAULT NULL,
  `alert_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `patient_id` int(11) NOT NULL,
  `patient_name` varchar(100) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `oxygen_level` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_notes`
--

CREATE TABLE `patient_notes` (
  `note_id` int(11) NOT NULL,
  `patient_name` varchar(100) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_notes`
--

INSERT INTO `patient_notes` (`note_id`, `patient_name`, `note`, `created_by`, `created_at`) VALUES
(1, 'Clint Tams', 'He has ankle injury', 'admin', '2026-03-02 15:47:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) DEFAULT 'rescuer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `role`) VALUES
(1, 'admin', '0192023a7bbd73250516f069df18b500', 'rescuer');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`alert_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`patient_id`);

--
-- Indexes for table `patient_notes`
--
ALTER TABLE `patient_notes`
  ADD PRIMARY KEY (`note_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `alerts`
--
ALTER TABLE `alerts`
  MODIFY `alert_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_notes`
--
ALTER TABLE `patient_notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
