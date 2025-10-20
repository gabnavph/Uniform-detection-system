-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 14, 2025 at 04:19 AM
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
-- Database: `uniform_detection`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('superadmin','officer','viewer') DEFAULT 'officer',
  `status` enum('active','disabled') DEFAULT 'active',
  `password` varchar(255) NOT NULL,
  `fullname` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `email`, `role`, `status`, `password`, `fullname`) VALUES
(1, 'admin', NULL, 'superadmin', 'active', '0192023a7bbd73250516f069df18b500', 'System Administrator'),
(2, 'admin1', 'gabu.baguti69@gmail.com', 'officer', 'active', '7cc1fd2e52e21d2a8c39d60929cdc938', 'juan de');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `penalties_settled` text NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `remarks` varchar(255) DEFAULT NULL,
  `received_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `student_id`, `amount`, `penalties_settled`, `payment_date`, `remarks`, `received_by`) VALUES
(1, 1, 50.00, '[{\"penalty_id\":1,\"applied\":5},{\"penalty_id\":2,\"applied\":5},{\"penalty_id\":3,\"applied\":5},{\"penalty_id\":4,\"applied\":5},{\"penalty_id\":5,\"applied\":5},{\"penalty_id\":6,\"applied\":5},{\"penalty_id\":7,\"applied\":5},{\"penalty_id\":8,\"applied\":5},{\"penalty_id\":9,\"applied\":5},{\"penalty_id\":10,\"applied\":5}]', '2025-10-13 15:16:50', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `penalties`
--

CREATE TABLE `penalties` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `violation` text NOT NULL,
  `charge` decimal(10,2) DEFAULT 5.00,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `date_issued` datetime DEFAULT current_timestamp(),
  `remarks` text DEFAULT NULL,
  `status` enum('unpaid','paid') DEFAULT 'unpaid',
  `or_number` varchar(50) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `penalties`
--

INSERT INTO `penalties` (`id`, `student_id`, `violation`, `charge`, `paid_amount`, `payment_status`, `date_issued`, `remarks`, `status`, `or_number`, `paid_at`) VALUES
(1, 1, 'Incomplete uniform: ID, Top (female_dress/male_dress), Bottom (female_skirt/male_pants), Shoes', 5.00, 5.00, 'paid', '2025-10-13 18:03:37', NULL, 'unpaid', NULL, NULL),
(2, 1, 'Incomplete uniform: ID, Top (female_dress/male_dress), Bottom (female_skirt/male_pants), Shoes', 5.00, 5.00, 'paid', '2025-10-13 18:36:07', NULL, 'unpaid', NULL, NULL),
(3, 1, 'Incomplete uniform: ID, Bottom (female_skirt/male_pants), Shoes', 5.00, 5.00, 'paid', '2025-10-13 18:37:19', NULL, 'unpaid', NULL, NULL),
(4, 1, 'Incomplete uniform: Bottom (female_skirt/male_pants), Shoes', 5.00, 5.00, 'paid', '2025-10-13 18:41:04', NULL, 'unpaid', NULL, NULL),
(5, 1, 'Incomplete uniform: Bottom (female_skirt/male_pants), Shoes', 5.00, 5.00, 'paid', '2025-10-13 18:42:21', NULL, 'unpaid', NULL, NULL),
(6, 1, 'Incomplete uniform: ID, Top (female_dress/male_dress), Bottom (female_skirt/male_pants), Shoes', 5.00, 5.00, 'paid', '2025-10-13 21:29:26', NULL, 'unpaid', NULL, NULL),
(7, 1, 'Incomplete uniform: ID, Top (female_dress/male_dress), Bottom (female_skirt/male_pants), Shoes', 5.00, 5.00, 'paid', '2025-10-13 21:54:48', NULL, 'unpaid', NULL, NULL),
(8, 1, 'Incomplete uniform: ID, Bottom (female_skirt/male_pants), Shoes', 5.00, 5.00, 'paid', '2025-10-13 21:55:18', NULL, 'unpaid', NULL, NULL),
(9, 1, 'Incomplete uniform: ID, Top (female_dress/male_dress), Shoes', 5.00, 5.00, 'paid', '2025-10-13 21:55:59', NULL, 'unpaid', NULL, NULL),
(10, 1, 'Incomplete uniform: ID, Top (female_dress/male_dress), Shoes', 5.00, 5.00, 'paid', '2025-10-13 21:56:34', NULL, 'unpaid', NULL, NULL),
(11, 1, 'Incomplete uniform: ID, Top (female_dress/male_dress), Bottom (female_skirt/male_pants), Shoes', 5.00, 0.00, 'unpaid', '2025-10-13 21:56:58', NULL, 'unpaid', NULL, NULL),
(12, 1, 'Incomplete uniform: Shoes', 5.00, 0.00, 'unpaid', '2025-10-13 22:00:17', NULL, 'unpaid', NULL, NULL),
(13, 1, 'Incomplete uniform: Shoes', 5.00, 0.00, 'unpaid', '2025-10-13 22:01:24', NULL, 'unpaid', NULL, NULL),
(14, 1, 'Incomplete uniform: ID, Top (female_dress/male_dress), Bottom (female_skirt/male_pants)', 5.00, 0.00, 'unpaid', '2025-10-13 23:36:57', NULL, 'unpaid', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES
(1, 'system_name', 'Uniform Monitoring System'),
(2, 'school_name', 'Your School Name'),
(3, 'school_logo', 'admin/assets/images/logo.png'),
(4, 'require_id', '1'),
(5, 'require_shoes', '1'),
(6, 'default_penalty', '5'),
(7, 'smtp_host', 'smtp-relay.brevo.com'),
(8, 'smtp_user', ''),
(9, 'smtp_pass', ''),
(10, 'smtp_sender_name', 'Uniform Monitoring System'),
(11, 'date_format', 'Y-m-d'),
(12, 'report_footer', 'Generated by Uniform Monitoring System');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_code` varchar(100) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `year_level` varchar(50) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `date_created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_code`, `fullname`, `email`, `contact`, `course`, `year_level`, `section`, `photo`, `date_created`) VALUES
(1, '114709080024', 'jan gabriel navallasca', 'jangabrielnavallasca07@gmail.com', '', 'BSIT', '4th Year', 'B', 'uploads/students/stu_1760347404_DSCF8616-Enhanced-NR.jpg', '2025-10-13 17:22:05');

-- --------------------------------------------------------

--
-- Table structure for table `uniform_logs`
--

CREATE TABLE `uniform_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `detected_items` text NOT NULL,
  `status` enum('complete','incomplete') NOT NULL,
  `detected_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `uniform_logs`
--

INSERT INTO `uniform_logs` (`id`, `student_id`, `detected_items`, `status`, `detected_at`) VALUES
(1, 1, '[]', 'incomplete', '2025-10-13 18:03:37'),
(2, 1, '[]', 'incomplete', '2025-10-13 18:36:07'),
(3, 1, '[\"female_dress\"]', 'incomplete', '2025-10-13 18:37:19'),
(4, 1, '[\"male_dress\"]', 'incomplete', '2025-10-13 18:41:04'),
(5, 1, '[\"male_dress\"]', 'incomplete', '2025-10-13 18:42:21'),
(6, 1, '[]', 'incomplete', '2025-10-13 21:29:26'),
(7, 1, '[]', 'incomplete', '2025-10-13 21:54:47'),
(8, 1, '[\"male_dress\"]', 'incomplete', '2025-10-13 21:55:18'),
(9, 1, '[\"male_pants\"]', 'incomplete', '2025-10-13 21:55:59'),
(10, 1, '[\"male_pants\"]', 'incomplete', '2025-10-13 21:56:34'),
(11, 1, '[]', 'incomplete', '2025-10-13 21:56:58'),
(12, 1, '[\"ID\",\"male_dress\",\"male_pants\"]', 'incomplete', '2025-10-13 22:00:17'),
(13, 1, '[\"ID\",\"male_dress\",\"male_pants\"]', 'incomplete', '2025-10-13 22:01:24'),
(14, 1, '[\"ID\",\"male_dress\",\"male_pants\"]', 'complete', '2025-10-13 22:26:32'),
(15, 1, '[]', 'incomplete', '2025-10-13 23:36:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `penalties`
--
ALTER TABLE `penalties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_code` (`student_code`);

--
-- Indexes for table `uniform_logs`
--
ALTER TABLE `uniform_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `penalties`
--
ALTER TABLE `penalties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `uniform_logs`
--
ALTER TABLE `uniform_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `penalties`
--
ALTER TABLE `penalties`
  ADD CONSTRAINT `penalties_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `uniform_logs`
--
ALTER TABLE `uniform_logs`
  ADD CONSTRAINT `uniform_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
