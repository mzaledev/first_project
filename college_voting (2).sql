-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 24, 2026 at 02:47 PM
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
-- Database: `college_voting`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', '12345', '2026-10-21 07:40:25');

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `department` varchar(150) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `status` enum('pending','active','inactive','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`id`, `name`, `department`, `course`, `photo`, `status`, `created_at`, `username`, `password`, `email`) VALUES
(17, 'rashid', 'Emergency Health and Safety', 'Occupational Health and Safety', 'ded517d45998e8410c6ed5c13fe0f91f.jpg', 'active', '2026-10-23 14:14:17', NULL, NULL, NULL),
(18, 'fatma', 'Nursing and Midwifery', 'Nursing and Midwifery', 'aabdbe5102fff31c8e039902c333ffb0.jpg', 'active', '2026-10-23 14:14:43', NULL, NULL, NULL),
(19, 'yussuf', 'Medical Science', 'Medical Laboratory Sciences', '92cfdbce97ef2d01eb6751addafda000.jpg', 'active', '2026-10-24 06:43:04', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `elections`
--

CREATE TABLE `elections` (
  `id` int(11) NOT NULL,
  `election_name` varchar(100) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `status` enum('scheduled','open','closed') NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `elections`
--

INSERT INTO `elections` (`id`, `election_name`, `start_time`, `end_time`, `status`, `created_at`) VALUES
(34, 'PRESDENT ELECTION 2026', '2026-10-24 13:08:00', '2026-10-24 13:20:00', 'scheduled', '2026-10-24 10:07:48');

-- --------------------------------------------------------

--
-- Table structure for table `election_history`
--

CREATE TABLE `election_history` (
  `id` int(11) NOT NULL,
  `election_id` int(11) DEFAULT NULL,
  `election_name` varchar(255) NOT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `total_students` int(11) NOT NULL DEFAULT 0,
  `students_voted` int(11) NOT NULL DEFAULT 0,
  `students_not_voted` int(11) NOT NULL DEFAULT 0,
  `voting_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `archived_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `election_history`
--

INSERT INTO `election_history` (`id`, `election_id`, `election_name`, `start_time`, `end_time`, `total_students`, `students_voted`, `students_not_voted`, `voting_percentage`, `archived_at`) VALUES
(1, 30, 'PRESDENT ELECTION 2026', '2026-10-23 18:20:00', '2026-10-23 19:37:00', 4, 1, 0, 25.00, '2026-10-23 20:44:09');

-- --------------------------------------------------------

--
-- Table structure for table `election_history_results`
--

CREATE TABLE `election_history_results` (
  `id` int(11) NOT NULL,
  `history_id` int(11) NOT NULL,
  `candidate_name` varchar(255) NOT NULL,
  `department` varchar(150) DEFAULT NULL,
  `course` varchar(255) DEFAULT NULL,
  `total_votes` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `election_history_results`
--

INSERT INTO `election_history_results` (`id`, `history_id`, `candidate_name`, `department`, `course`, `total_votes`) VALUES
(1, 1, 'rashid', 'Emergency Health and Safety', 'Occupational Health and Safety', 1),
(2, 1, 'fatma', 'Nursing and Midwifery', 'Nursing and Midwifery', 0);

-- --------------------------------------------------------

--
-- Table structure for table `election_settings`
--

CREATE TABLE `election_settings` (
  `id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `has_voted` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `name`, `username`, `password`, `has_voted`, `status`, `created_at`) VALUES
(1, 'ST001', 'Ahmed Ali', 'ahmed', '12345', 1, 'active', '2026-10-21 07:40:25'),
(2, 'ST002', 'Asha Juma', 'asha', '12345', 1, 'active', '2026-10-21 07:40:25'),
(3, 'ST003', 'lucho', 'lucho', '$2y$10$RzuTv7zHeypEzvrTV3Exveua86u3/kLfDU8KN.BVUNCeW19nT1qnS', 1, 'active', '2026-10-21 07:40:25'),
(7, 'st 0042', 'yussuf omar', 'yusuf', '$2y$10$78Re9CmEH/v3Jc8GVqVKGOAVAsx8W/zXVJwIwrKX710U8wlL4d.I2', 1, 'active', '2026-10-22 10:41:55');

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `election_id` int(11) DEFAULT NULL,
  `candidate_id` int(11) NOT NULL,
  `voted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `votes`
--

INSERT INTO `votes` (`id`, `student_id`, `election_id`, `candidate_id`, `voted_at`) VALUES
(11, 3, 28, 17, '2026-10-23 14:15:52'),
(12, 3, 29, 17, '2026-10-23 14:58:06'),
(13, 3, 30, 17, '2026-10-23 15:20:30'),
(14, 3, 31, 17, '2026-10-23 17:49:01'),
(15, 3, 34, 19, '2026-10-24 10:09:18');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `elections`
--
ALTER TABLE `elections`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `election_history`
--
ALTER TABLE `election_history`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_history_election` (`election_id`);

--
-- Indexes for table `election_history_results`
--
ALTER TABLE `election_history_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_history_results` (`history_id`);

--
-- Indexes for table `election_settings`
--
ALTER TABLE `election_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_election` (`student_id`,`election_id`),
  ADD KEY `idx_votes_election` (`election_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `elections`
--
ALTER TABLE `elections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `election_history`
--
ALTER TABLE `election_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `election_history_results`
--
ALTER TABLE `election_history_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `election_settings`
--
ALTER TABLE `election_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `election_history_results`
--
ALTER TABLE `election_history_results`
  ADD CONSTRAINT `fk_history_results` FOREIGN KEY (`history_id`) REFERENCES `election_history` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
