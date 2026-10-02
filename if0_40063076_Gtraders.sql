-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql104.infinityfree.com
-- Generation Time: Oct 01, 2026 at 08:39 PM
-- Server version: 11.4.13-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_40063076_Gtraders`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_history`
--

CREATE TABLE `account_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` datetime NOT NULL,
  `account_balance` decimal(15,2) DEFAULT 0.00,
  `invested_balance` decimal(15,2) DEFAULT 0.00,
  `total_expected_interest` decimal(15,2) DEFAULT 0.00
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `active_sessions`
--

CREATE TABLE `active_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_start` datetime NOT NULL,
  `session_end` datetime DEFAULT NULL,
  `data_in` bigint(20) DEFAULT 0,
  `data_out` bigint(20) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','super_admin') NOT NULL DEFAULT 'admin',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(50) DEFAULT NULL,
  `last_logout` datetime DEFAULT NULL,
  `dashboard_theme` varchar(10) NOT NULL DEFAULT 'dark',
  `can_edit_balances` tinyint(1) DEFAULT 0,
  `super_admin_pass` varchar(255) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `email`, `password`, `role`, `created_at`, `last_login`, `last_ip`, `last_logout`, `dashboard_theme`, `can_edit_balances`, `super_admin_pass`, `full_name`) VALUES
(14, 'Briz', 'ngelecheibrian89@gmail.com', '$2y$12$8orlww/icUCih5CRz3eJnOKMpBz7mtJO8Y32nv3/gqe40KPeSsRYG', 'admin', '2025-10-26 05:56:52', NULL, NULL, NULL, 'dark', 0, NULL, 'Briz'),
(18, 'GIBAL', 'gkhalibson11@gmail.com', '$2y$12$VTmIBJlCP1IOYoAfSKFPU.urcrKGelCmUKcx83TBLvSWf8zvtmpKK', 'super_admin', '2026-06-10 10:08:58', NULL, NULL, NULL, 'dark', 0, NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_log`
--

CREATE TABLE `admin_activity_log` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_audit_log`
--

CREATE TABLE `admin_audit_log` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `admin_username` varchar(100) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `admin_audit_log`
--

INSERT INTO `admin_audit_log` (`id`, `admin_id`, `admin_username`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 18, 'GIBAL', 'ADD_FEE', 'Manually added fee of Ksh 250 for 2026-09-29. Source: deposit', '102.203.137.244', '2026-09-29 13:43:17'),
(2, 18, 'GIBAL', 'ADD_FEE', 'Manually added fee of Ksh 500 for 2026-09-29. Source: manual', '102.203.137.244', '2026-09-29 13:44:10'),
(3, 18, 'GIBAL', 'ADD_FEE', 'Manually added fee of Ksh 333 for 2026-09-29. Source: investment', '102.203.137.244', '2026-09-29 13:46:03'),
(4, 18, 'GIBAL', 'UPDATE_SYSTEM_STATUS', 'Changed system mode to \'maintenance\'', '102.203.137.244', '2026-09-30 11:03:47'),
(5, 18, 'GIBAL', 'UPDATE_SYSTEM_STATUS', 'Changed system mode to \'operational\'', '102.203.137.244', '2026-09-30 11:04:19'),
(6, 18, 'GIBAL', 'UPDATE_SYSTEM_STATUS', 'Changed system mode to \'operational\'', '102.203.137.244', '2026-09-30 12:04:02'),
(7, 18, 'GIBAL', 'UPDATE_SYSTEM_STATUS', 'Changed system mode to \'operational\'', '102.203.137.244', '2026-09-30 12:28:41'),
(8, 18, 'GIBAL', 'UPDATE_PROFILE', 'Updated profile for user #48', '102.203.137.244', '2026-09-30 13:42:19'),
(9, 18, 'GIBAL', 'UPDATE_PROFILE', 'Updated profile for user #54', '102.203.137.244', '2026-09-30 13:42:42'),
(10, 18, 'GIBAL', 'ADJUST_BALANCE', 'Adjusted balances for user #42. Reason: Bonus | Account: 4529.50?5529.5 | Invested: 19782.50?19782.5 | Referral: 224.36?224.36', '102.203.137.244', '2026-10-01 13:28:46');

-- --------------------------------------------------------

--
-- Table structure for table `admin_chats`
--

CREATE TABLE `admin_chats` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sender_type` enum('admin','user') DEFAULT 'admin',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `admin_chats`
--

INSERT INTO `admin_chats` (`id`, `admin_id`, `user_id`, `message`, `sender_type`, `is_read`, `created_at`) VALUES
(1, NULL, 42, 'Hello', 'user', 1, '2026-09-30 12:52:52'),
(2, 18, 42, 'Hello how can we help you today Eric', 'admin', 1, '2026-09-30 12:54:14'),
(3, NULL, 42, 'how do make an investment', 'user', 1, '2026-09-30 13:07:30'),
(4, NULL, 42, 'and withdrawals', 'user', 1, '2026-09-30 13:07:40'),
(5, 18, 42, 'Hello how can we help you today Eric', 'admin', 1, '2026-09-30 13:09:46'),
(6, NULL, 42, 'ok', 'user', 1, '2026-09-30 13:11:35'),
(7, 18, 42, 'scroll down to the investment plan section and click on invest', 'admin', 1, '2026-09-30 13:12:45'),
(8, NULL, 42, 'okay got it thanks', 'user', 1, '2026-09-30 13:13:15'),
(9, 18, 42, 'scroll down to the investment plan section and click on invest', 'admin', 1, '2026-09-30 13:13:46'),
(10, NULL, 44, 'Hello', 'user', 1, '2026-09-30 15:59:31'),
(11, 18, 44, 'Hello how can we help you today.', 'admin', 1, '2026-09-30 16:00:07'),
(12, 18, 42, 'Hey', 'admin', 1, '2026-10-01 01:20:55'),
(13, NULL, 42, 'your welcome', 'user', 1, '2026-10-01 01:24:00'),
(14, 18, 42, 'ok', 'admin', 1, '2026-10-01 01:24:34'),
(15, NULL, 42, 'kk', 'user', 1, '2026-10-01 01:25:00'),
(16, NULL, 42, 'okay have a good day', 'user', 1, '2026-10-01 01:26:01'),
(17, 18, 42, 'you too', 'admin', 1, '2026-10-01 11:03:23'),
(18, NULL, 42, 'Thanks', 'user', 1, '2026-10-01 11:04:12'),
(19, 18, 42, 'welcome', 'admin', 1, '2026-10-01 11:04:27'),
(20, NULL, 42, 'Hello', 'user', 1, '2026-10-01 14:25:55'),
(21, 18, 42, 'hi how can i be of help today', 'admin', 0, '2026-10-01 14:26:39');

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action_type` varchar(50) DEFAULT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `action_time` datetime NOT NULL,
  `ip` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `admin_id`, `action_type`, `target_user_id`, `details`, `ip_address`, `action_time`, `ip`) VALUES
(354, 14, 'Login', NULL, 'Admin Briz logged in', '105.161.196.102', '2025-10-27 05:05:26', NULL),
(358, 14, 'Login', NULL, 'Admin Briz logged in', '41.90.176.212', '2025-10-27 12:14:03', NULL),
(708, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '105.164.128.187', '2026-06-10 03:11:24', NULL),
(709, 18, 'reject', 42, 'Rejected deposit ID 220', '105.164.128.187', '2026-06-10 03:11:41', NULL),
(710, 18, 'reject', 42, 'Rejected deposit ID 221', '105.164.128.187', '2026-06-10 03:11:43', NULL),
(711, 18, 'approve', 42, 'Approved withdrawal ID 52', '105.164.128.187', '2026-06-10 03:11:51', NULL),
(712, 18, 'approve', 42, 'Approved withdrawal ID 53', '105.164.128.187', '2026-06-10 03:11:54', NULL),
(713, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '105.164.117.142', '2026-06-10 09:43:45', NULL),
(714, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.0.25.112', '2026-06-23 05:46:49', NULL),
(715, 18, 'system_toggle', NULL, 'System set to maintenance', '102.0.25.112', '2026-06-23 05:48:55', NULL),
(716, 18, 'system_toggle', NULL, 'System set to operational', '102.0.25.112', '2026-06-23 05:50:46', NULL),
(717, 18, 'approve', 97, 'Approved deposit ID 224', '102.0.25.112', '2026-06-23 05:52:48', NULL),
(718, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '105.164.128.128', '2026-06-29 07:30:04', NULL),
(719, 18, 'approve', 42, 'Approved deposit ID 225', '105.164.128.128', '2026-06-29 07:33:04', NULL),
(720, 18, 'reject', 97, 'Rejected deposit ID 222', '105.164.128.128', '2026-06-29 07:33:08', NULL),
(721, 18, 'reject', 97, 'Rejected deposit ID 223', '105.164.128.128', '2026-06-29 07:33:09', NULL),
(722, 18, 'reject', 97, 'Rejected withdrawal ID 54', '105.164.128.128', '2026-06-29 07:36:44', NULL),
(723, 18, 'approve', 42, 'Approved withdrawal ID 55', '105.164.128.128', '2026-06-29 07:36:52', NULL),
(724, 18, 'system_toggle', NULL, 'System set to maintenance', '105.164.128.128', '2026-06-29 08:07:02', NULL),
(725, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.18', '2026-07-25 08:17:58', NULL),
(726, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '149.22.84.151', '2026-09-06 14:12:33', NULL),
(727, 18, 'system_toggle', NULL, 'System set to operational', '149.22.84.151', '2026-09-06 14:30:06', NULL),
(728, 18, 'reject', 98, 'Rejected withdrawal ID 56', '149.22.84.151', '2026-09-06 14:39:20', NULL),
(729, 18, 'system_toggle', NULL, 'System set to maintenance', '149.22.84.151', '2026-09-06 15:00:20', NULL),
(730, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '217.138.209.27', '2026-09-07 03:38:28', NULL),
(731, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '217.138.209.27', '2026-09-07 03:39:27', NULL),
(732, 18, 'system_toggle', NULL, 'System set to operational', '217.138.209.27', '2026-09-07 03:40:14', NULL),
(733, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.244', '2026-09-15 11:57:08', NULL),
(734, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.244', '2026-09-18 16:23:42', NULL),
(735, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.244', '2026-09-29 12:28:11', NULL),
(736, 18, 'approve', 42, 'Approved deposit ID 226', '102.203.137.244', '2026-09-29 12:28:35', NULL),
(737, 18, 'approve', 42, 'Approved withdrawal ID 59', '102.203.137.244', '2026-09-29 12:29:07', NULL),
(738, 18, 'approve', 44, 'Approved withdrawal ID 57', '102.203.137.244', '2026-09-29 12:29:12', NULL),
(739, 18, 'approve', 42, 'Approved withdrawal ID 58', '102.203.137.244', '2026-09-29 12:29:16', NULL),
(740, 18, '2FA Failed', NULL, 'Incorrect OTP attempt 1', '102.203.137.244', '2026-09-29 15:03:18', NULL),
(741, 18, '2FA Failed', NULL, 'Incorrect OTP attempt 2', '102.203.137.244', '2026-09-29 15:03:38', NULL),
(742, 18, '2FA OTP Resent', NULL, 'OTP resent to admin', '102.203.137.244', '2026-09-29 15:03:45', NULL),
(743, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.244', '2026-09-29 15:04:12', NULL),
(744, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.244', '2026-09-29 17:59:38', NULL),
(745, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.244', '2026-09-29 18:00:08', NULL),
(746, 18, 'approve', 42, 'Approved deposit ID 227', '102.203.137.244', '2026-09-29 18:02:21', NULL),
(747, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.244', '2026-09-30 08:46:36', NULL),
(748, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.244', '2026-09-30 08:47:26', NULL),
(749, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.244', '2026-09-30 08:51:42', NULL),
(750, 18, 'system_toggle', NULL, 'System set to maintenance', '102.203.137.244', '2026-09-30 09:36:40', NULL),
(751, 18, 'system_toggle', NULL, 'System set to operational', '102.203.137.244', '2026-09-30 09:37:35', NULL),
(752, 18, 'approve', 42, 'Approved deposit ID 228', '102.203.137.244', '2026-09-30 10:52:01', NULL),
(753, 18, 'approve', 42, 'Approved withdrawal ID 1', '102.203.137.244', '2026-09-30 10:54:53', NULL),
(754, 18, 'approve', 42, 'Approved deposit ID 229', '102.203.137.244', '2026-09-30 12:00:50', NULL),
(755, 18, 'approve', 42, 'Approved withdrawal ID 2', '102.203.137.244', '2026-09-30 12:31:06', NULL),
(756, 18, '2FA Failed', NULL, 'Incorrect OTP attempt 1', '102.203.137.244', '2026-09-30 13:40:51', NULL),
(757, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.244', '2026-09-30 13:41:24', NULL),
(758, 18, 'approve', 44, 'Approved deposit ID 230', '102.203.137.244', '2026-09-30 15:54:01', NULL),
(759, 18, 'approve', 44, 'Approved withdrawal ID 3', '102.203.137.244', '2026-09-30 15:56:23', NULL),
(760, 18, 'approve', 42, 'Approved deposit ID 231', '102.203.137.244', '2026-09-30 16:13:16', NULL),
(761, 18, 'approve', 42, 'Approved withdrawal ID 4', '102.203.137.244', '2026-09-30 16:17:21', NULL),
(762, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '31.14.252.5', '2026-10-01 00:34:15', NULL),
(763, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.244', '2026-10-01 04:16:07', NULL),
(764, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.244', '2026-10-01 04:16:49', NULL),
(765, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.244', '2026-10-01 08:46:28', NULL),
(766, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.244', '2026-10-01 10:33:32', NULL),
(767, 18, 'reject', 44, 'Rejected withdrawal ID 5', '102.203.137.244', '2026-10-01 11:34:38', NULL),
(768, 18, 'approve', 44, 'Approve withdrawal ID 6', '102.203.137.244', '2026-10-01 12:25:49', NULL),
(769, 18, 'dispute', 42, 'Dispute withdrawal ID 7', '102.203.137.244', '2026-10-01 12:26:11', NULL),
(770, 18, '2FA Failed', NULL, 'Incorrect OTP attempt 1', '102.203.137.244', '2026-10-01 13:04:56', NULL),
(771, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.244', '2026-10-01 13:05:15', NULL),
(772, 18, 'approve', 42, 'Approve withdrawal ID 8', '102.203.137.244', '2026-10-01 14:24:14', NULL),
(773, 18, 'approve', 42, 'Approve withdrawal ID 9', '102.203.137.244', '2026-10-01 14:25:17', NULL),
(774, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.244', '2026-10-01 14:28:20', NULL),
(775, 18, '2FA OTP Resent', NULL, 'OTP resent to admin', '102.203.137.244', '2026-10-01 17:04:56', NULL),
(776, 18, '2FA Verified', NULL, 'Admin passed email OTP verification', '102.203.137.244', '2026-10-01 17:05:33', NULL),
(777, 18, 'approve', 44, 'Approved deposit ID 240', '102.203.137.244', '2026-10-01 17:06:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `admin_wallet`
--

CREATE TABLE `admin_wallet` (
  `id` int(11) NOT NULL,
  `fees` decimal(15,2) DEFAULT 0.00,
  `withdrawn_fees` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_wallet_log`
--

CREATE TABLE `admin_wallet_log` (
  `id` int(11) NOT NULL,
  `amount` decimal(15,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `balance_logs`
--

CREATE TABLE `balance_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `old_balance` decimal(15,2) NOT NULL,
  `new_balance` decimal(15,2) NOT NULL,
  `change_amount` decimal(15,2) NOT NULL,
  `action_type` enum('credit','debit','edit') NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `broadcast_notifications`
--

CREATE TABLE `broadcast_notifications` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','success','urgent') DEFAULT 'info',
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `target_user_id` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `broadcast_notifications`
--

INSERT INTO `broadcast_notifications` (`id`, `title`, `message`, `type`, `created_at`, `created_by`, `target_user_id`) VALUES
(1, 'New Update', 'You are now using our new version.', 'info', '2026-09-30 13:16:36', 18, NULL),
(2, 'New Update', 'You are now using our new version.', 'info', '2026-09-30 13:24:47', 18, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `btc_deposits`
--

CREATE TABLE `btc_deposits` (
  `id` int(11) NOT NULL,
  `transaction_code` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `invoice_id` varchar(128) DEFAULT NULL,
  `amount` decimal(16,8) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `deposit_method` varchar(50) NOT NULL DEFAULT 'payoneer',
  `btc_address` varchar(255) DEFAULT NULL,
  `amount_ksh` decimal(15,2) NOT NULL DEFAULT 0.00,
  `applied_to_balance` tinyint(1) DEFAULT 0,
  `credited` tinyint(1) DEFAULT 0,
  `mpesa_transaction_code` varchar(50) DEFAULT NULL,
  `converted_amount` double DEFAULT 0,
  `provider_reference` varchar(128) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `btc_deposits`
--

INSERT INTO `btc_deposits` (`id`, `transaction_code`, `user_id`, `invoice_id`, `amount`, `status`, `processed_at`, `created_at`, `deposit_method`, `btc_address`, `amount_ksh`, `applied_to_balance`, `credited`, `mpesa_transaction_code`, `converted_amount`, `provider_reference`, `updated_at`) VALUES
(217, NULL, 42, '472602238L786781D', '75.00000000', 'credited', '2025-12-06 13:55:43', '2025-12-06 13:53:04', 'paypal', 'https://www.paypal.com/checkoutnow?token=472602238L786781D', '9375.00', 0, 0, NULL, 0, NULL, NULL),
(214, NULL, 42, 'KEUUEI7HBE', '5.00000000', 'rejected', '2025-12-05 03:19:57', '2025-12-05 03:04:23', 'mpesa', 'KEUUEI7HBE', '625.00', 0, 0, NULL, 0, NULL, NULL),
(215, NULL, 42, '7BH63741MV443535B', '9.00000000', 'rejected', '2025-12-05 03:19:54', '2025-12-05 03:19:07', 'paypal', 'https://www.paypal.com/checkoutnow?token=7BH63741MV443535B', '1125.00', 0, 0, NULL, 0, NULL, NULL),
(216, NULL, 44, 'TL5D50567X', '5.00000000', 'rejected', '2025-12-05 07:21:37', '2025-12-05 07:18:55', 'mpesa', 'TL5D50567X', '625.00', 0, 0, NULL, 0, NULL, NULL),
(213, NULL, 42, 'KEUUEI7HBE', '5.00000000', 'rejected', '2025-12-05 03:19:59', '2025-12-05 03:04:20', 'mpesa', 'KEUUEI7HBE', '625.00', 0, 0, NULL, 0, NULL, NULL),
(210, NULL, 42, 'TYEC55FERG', '5.00000000', 'rejected', '2025-12-03 05:59:27', '2025-12-03 05:37:27', 'mpesa', 'TYEC55FERG', '625.00', 0, 0, NULL, 0, NULL, NULL),
(211, NULL, 42, 'OYEC65FERT', '8.00000000', 'credited', '2025-12-03 06:00:24', '2025-12-03 06:00:14', 'mpesa', 'OYEC65FERT', '1000.00', 0, 0, NULL, 0, NULL, NULL),
(212, NULL, 44, 'TL4D5023OT', '12.72000000', 'credited', '2025-12-04 09:19:08', '2025-12-04 08:36:35', 'mpesa', 'TL4D5023OT', '1590.00', 0, 0, NULL, 0, NULL, NULL),
(207, NULL, 42, 'TEUUEI7HBE', '20.00000000', 'credited', '2025-11-16 21:38:56', '2025-11-16 21:38:28', 'mpesa', 'TEUUEI7HBE', '2500.00', 0, 0, NULL, 0, NULL, NULL),
(208, NULL, 42, 'OYEC55FERT', '10.00000000', 'credited', '2025-11-26 13:56:50', '2025-11-26 13:56:21', 'mpesa', 'OYEC55FERT', '1300.00', 0, 1, NULL, 0, NULL, NULL),
(209, NULL, 42, 'TYEC55FERG', '8.00000000', 'rejected', '2025-12-03 05:59:30', '2025-12-03 15:04:33', 'mpesa', 'TYEC55FERG', '1000.00', 0, 0, NULL, 0, NULL, NULL),
(205, NULL, 42, '9H3253395X309930C', '45.00000000', 'credited', '2025-11-12 21:10:12', '2025-11-12 21:07:12', 'paypal', 'https://www.paypal.com/checkoutnow?token=9H3253395X309930C', '5625.00', 0, 0, NULL, 0, NULL, NULL),
(206, NULL, 42, 'TYEC55FERT', '5.00000000', 'rejected', '2025-11-13 01:35:06', '2025-11-13 01:29:58', 'mpesa', 'TYEC55FERT', '625.00', 0, 0, NULL, 0, NULL, NULL),
(204, NULL, 42, '3ET04260M2297434S', '30.00000000', 'credited', '2025-11-09 01:07:55', '2025-11-09 01:07:38', 'paypal', 'https://www.paypal.com/checkoutnow?token=3ET04260M2297434S', '3750.00', 0, 0, NULL, 0, NULL, NULL),
(203, NULL, 48, 'TK85N9LCKE', '5.00000000', 'credited', '2025-11-07 18:30:24', '2025-11-07 17:33:22', 'mpesa', 'TK85N9LCKE', '625.00', 0, 0, NULL, 0, NULL, NULL),
(218, NULL, 44, '93V60174WL289902P', '7.50000000', 'credited', '2025-12-09 00:28:31', '2025-12-08 23:27:03', 'paypal', 'https://www.paypal.com/checkoutnow?token=93V60174WL289902P', '937.50', 0, 0, NULL, 0, NULL, NULL),
(219, NULL, 42, '0LV33465NX077973F', '80.00000000', 'rejected', '2026-02-02 13:11:07', '2026-01-24 02:13:46', 'paypal', 'https://www.paypal.com/checkoutnow?token=0LV33465NX077973F', '10000.00', 0, 0, NULL, 0, NULL, NULL),
(220, NULL, 42, 'TYEC55FERK', '7.00000000', 'rejected', '2026-06-10 03:11:41', '2026-06-01 06:33:57', 'mpesa', 'TYEC55FERK', '875.00', 0, 0, NULL, 0, NULL, NULL),
(221, NULL, 42, '5GG35759415120903', '7.00000000', 'rejected', '2026-06-10 03:11:43', '2026-06-01 06:34:23', 'paypal', 'https://www.paypal.com/checkoutnow?token=5GG35759415120903', '875.00', 0, 0, NULL, 0, NULL, NULL),
(222, NULL, 97, 'Yfbthyjyjhyj', '5.00000000', 'rejected', '2026-06-29 07:33:08', '2026-06-23 05:50:30', 'mpesa', 'Yfbthyjyjhyj', '625.00', 0, 0, NULL, 0, NULL, NULL),
(223, NULL, 97, 'Yfbthyjyjhyj', '5.00000000', 'rejected', '2026-06-29 07:33:09', '2026-06-23 05:50:45', 'mpesa', 'Yfbthyjyjhyj', '625.00', 0, 0, NULL, 0, NULL, NULL),
(224, NULL, 97, 'Trewghin', '10.00000000', 'credited', '2026-06-23 05:52:48', '2026-06-23 05:51:59', 'mpesa', 'Trewghin', '1250.00', 0, 0, NULL, 0, NULL, NULL),
(225, NULL, 42, '4S118912RE473620W', '5.00000000', 'credited', '2026-06-29 07:33:04', '2026-06-29 07:26:38', 'paypal', 'https://www.paypal.com/checkoutnow?token=4S118912RE473620W', '625.00', 0, 0, NULL, 0, NULL, NULL),
(226, NULL, 42, 'TREC#$FERT', '30.00000000', 'credited', '2026-09-29 12:28:35', '2026-09-29 00:34:27', 'mpesa', 'TREC#$FERT', '3750.00', 0, 0, NULL, 0, NULL, NULL),
(227, NULL, 42, 'UITD58F2I1', '10.00000000', 'credited', '2026-09-29 18:02:21', '2026-09-29 18:01:50', 'mpesa', 'UITD58F2I1', '1250.00', 0, 0, NULL, 0, NULL, NULL),
(228, NULL, 42, 'ITIWA5EK', '35.00000000', 'credited', '2026-09-30 10:52:01', '2026-09-30 10:51:46', 'mpesa', 'ITIWA5EK', '4375.00', 0, 0, NULL, 0, NULL, NULL),
(229, NULL, 42, 'TYEC55FERK', '45.00000000', 'credited', '2026-09-30 12:00:50', '2026-09-30 12:00:16', 'mpesa', 'TYEC55FERK', '5625.00', 0, 0, NULL, 0, NULL, NULL),
(230, NULL, 44, 'UITBSKSVGN', '100.00000000', 'credited', '2026-09-30 15:54:01', '2026-09-30 15:53:09', 'mpesa', 'UITBSKSVGN', '12500.00', 0, 0, NULL, 0, NULL, NULL),
(231, NULL, 42, 'TYEC55FERT', '60.00000000', 'credited', '2026-09-30 16:13:16', '2026-09-30 16:13:06', 'mpesa', 'TYEC55FERT', '7500.00', 0, 0, NULL, 0, NULL, NULL),
(232, NULL, 42, 'GIBAL_747173367', '1000.00000000', 'completed', NULL, '2026-09-30 22:36:14', 'paystack', 'GIBAL_747173367', '1000.00', 0, 1, NULL, 0, NULL, NULL),
(233, NULL, 42, 'GIBAL_808986051', '3000.00000000', 'completed', NULL, '2026-09-30 22:36:46', 'paystack', 'GIBAL_808986051', '3000.00', 0, 1, NULL, 0, NULL, NULL),
(234, NULL, 42, 'GIBAL_914518134', '625.00000000', 'completed', NULL, '2026-09-30 22:38:16', 'paystack', 'GIBAL_914518134', '625.00', 0, 1, NULL, 0, NULL, NULL),
(235, NULL, 44, 'GIBAL_995219835', '3125.00000000', 'completed', NULL, '2026-09-30 22:40:25', 'paystack', 'GIBAL_995219835', '3125.00', 0, 1, NULL, 0, NULL, NULL),
(236, NULL, 42, 'GIBAL_857140738', '1125.00000000', 'completed', NULL, '2026-10-01 00:28:03', 'paystack', 'GIBAL_857140738', '1125.00', 0, 1, NULL, 0, NULL, NULL),
(237, NULL, 42, 'GIBAL_451967333', '4625.00000000', 'completed', NULL, '2026-10-01 11:09:39', 'paystack', 'GIBAL_451967333', '4625.00', 0, 1, NULL, 0, NULL, NULL),
(238, NULL, 44, 'GIBAL_335525484', '875.00000000', 'completed', NULL, '2026-10-01 11:21:07', 'paystack', 'GIBAL_335525484', '875.00', 0, 1, NULL, 0, NULL, NULL),
(239, NULL, 106, 'GIBAL_271850769', '2500.00000000', 'completed', NULL, '2026-10-01 14:37:29', 'paystack', 'GIBAL_271850769', '2500.00', 0, 1, NULL, 0, NULL, NULL),
(240, NULL, 44, NULL, '5.00000000', 'credited', '2026-10-01 17:06:00', '2026-10-01 16:50:16', 'paypal', '#', '625.00', 0, 0, NULL, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `user_email` varchar(150) DEFAULT NULL,
  `user_phone` varchar(50) DEFAULT NULL,
  `admin_name` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `sender` enum('user','admin') NOT NULL,
  `status` enum('open','closed') DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `category` enum('complaint','feedback') NOT NULL DEFAULT 'complaint',
  `message` text NOT NULL,
  `status` enum('open','in_progress','resolved','rejected') DEFAULT 'open',
  `admin_response` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`id`, `user_id`, `username`, `subject`, `category`, `message`, `status`, `admin_response`, `created_at`, `updated_at`) VALUES
(1, 42, 'test_user', 'Test Complaint', 'complaint', 'This is a test complaint for debugging', 'resolved', 'chonjo', '2026-10-01 03:32:53', '2026-10-01 11:01:20'),
(2, 42, NULL, 'thanks', 'feedback', 'asanteni', 'resolved', NULL, '2026-10-01 11:05:37', '2026-10-01 11:06:56'),
(3, 106, NULL, 'Smooth site', 'feedback', 'Appreciate how good the site is. thank you.?', 'resolved', NULL, '2026-10-01 14:40:31', '2026-10-01 14:41:12');

-- --------------------------------------------------------

--
-- Table structure for table `daily_fees`
--

CREATE TABLE `daily_fees` (
  `id` int(11) NOT NULL,
  `fee_amount` decimal(15,2) NOT NULL,
  `fee_date` datetime NOT NULL,
  `source` varchar(100) DEFAULT 'investment',
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `daily_fees`
--

INSERT INTO `daily_fees` (`id`, `fee_amount`, `fee_date`, `source`, `description`, `created_at`, `user_id`) VALUES
(1, '43.40', '2026-09-30 04:34:48', 'investment', NULL, '2026-09-29 18:34:49', 42),
(2, '105.00', '2026-09-30 20:55:59', 'investment', NULL, '2026-09-30 10:56:00', 42),
(3, '122.50', '2026-10-01 01:54:36', 'investment', NULL, '2026-09-30 15:54:36', 44),
(4, '297.50', '2026-10-01 01:55:21', 'investment', NULL, '2026-09-30 15:55:21', 44),
(5, '175.00', '2026-10-01 02:14:05', 'investment', NULL, '2026-09-30 16:14:05', 42),
(6, '420.00', '2026-10-01 21:12:28', 'investment', NULL, '2026-10-01 11:12:28', 42),
(7, '122.50', '2026-10-01 21:32:45', 'investment', NULL, '2026-10-01 11:32:46', 44),
(8, '122.50', '2026-10-01 23:24:43', 'investment', NULL, '2026-10-01 13:24:43', 42),
(9, '122.50', '2026-10-02 00:21:05', 'investment', NULL, '2026-10-01 14:21:05', 42),
(10, '87.50', '2026-10-02 00:38:36', 'investment', NULL, '2026-10-01 14:38:36', 106);

-- --------------------------------------------------------

--
-- Table structure for table `deposits`
--

CREATE TABLE `deposits` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `amount_ksh` decimal(15,2) NOT NULL DEFAULT 0.00,
  `deposit_method` varchar(50) NOT NULL DEFAULT 'manual',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `mpesa_receipt` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `deposits`
--

INSERT INTO `deposits` (`id`, `user_id`, `amount`, `amount_ksh`, `deposit_method`, `status`, `mpesa_receipt`, `created_at`) VALUES
(1, 42, '25.00', '3125.00', 'paypal', 'pending', 'https://www.paypal.com/checkoutnow?token=65W39620WK705005F', '2026-10-01 07:14:25');

-- --------------------------------------------------------

--
-- Table structure for table `disputes`
--

CREATE TABLE `disputes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `transaction_ref` varchar(255) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','in_progress','resolved','rejected') DEFAULT 'pending',
  `admin_response` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `disputes`
--

INSERT INTO `disputes` (`id`, `user_id`, `transaction_ref`, `amount`, `message`, `status`, `admin_response`, `created_at`) VALUES
(1, 42, 'GIBAL_914518134', '1000.00', 'My money was deducted but never reflected in my account', 'resolved', 'completed', '2026-10-01 00:30:33'),
(2, 42, 'GIBAL_914518134', '500.00', 'Nothing in account', 'rejected', 'Sorted', '2026-10-01 08:52:41'),
(3, 42, 'GIBAL_914518134', '1250.00', 'dolaraaaa', 'resolved', 'Ndo izoooooo', '2026-10-01 10:40:53'),
(4, 44, 'IUSBDKVISBK', '895.00', 'Not reflecting in my account', 'resolved', NULL, '2026-10-01 11:18:25'),
(5, 44, 'Ifcsssdfgg', '5000.00', 'Feeesrtg', 'rejected', NULL, '2026-10-01 11:22:06');

-- --------------------------------------------------------

--
-- Table structure for table `financial_ledger`
--

CREATE TABLE `financial_ledger` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_type` varchar(64) NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `reference_type` varchar(64) NOT NULL,
  `reference_id` varchar(128) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `investments`
--

CREATE TABLE `investments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `net_amount` decimal(15,2) NOT NULL,
  `fee` decimal(15,2) NOT NULL,
  `expected_interest` decimal(15,2) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `interest` decimal(10,2) DEFAULT 0.00,
  `admin_id` int(11) DEFAULT NULL,
  `processed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `matured_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `investments`
--

INSERT INTO `investments` (`id`, `user_id`, `plan_id`, `amount`, `net_amount`, `fee`, `expected_interest`, `start_date`, `end_date`, `status`, `interest`, `admin_id`, `processed`, `created_at`, `matured_at`) VALUES
(4, 42, 33, '1240.00', '1196.60', '43.40', '299.15', '2026-09-30 04:34:48', '2026-10-01 04:34:48', 'matured', '0.00', NULL, 0, '2026-09-30 01:34:49', NULL),
(5, 42, 33, '3000.00', '2895.00', '105.00', '723.75', '2026-09-30 20:55:59', '2026-10-01 20:55:59', 'matured', '0.00', NULL, 0, '2026-09-30 17:55:59', NULL),
(6, 44, 33, '3500.00', '3377.50', '122.50', '844.38', '2026-10-01 01:54:36', '2026-10-02 01:54:36', 'matured', '0.00', NULL, 0, '2026-09-30 22:54:36', NULL),
(7, 44, 34, '8500.00', '8202.50', '297.50', '2870.88', '2026-10-01 01:55:21', '2026-10-04 01:55:21', 'active', '0.00', NULL, 0, '2026-09-30 22:55:21', NULL),
(8, 42, 34, '5000.00', '4825.00', '175.00', '1688.75', '2026-10-01 02:14:05', '2026-10-04 02:14:05', 'active', '0.00', NULL, 0, '2026-09-30 23:14:05', NULL),
(9, 42, 35, '12000.00', '11580.00', '420.00', '5211.00', '2026-10-01 21:12:28', '2026-10-08 21:12:28', 'active', '0.00', NULL, 0, '2026-10-01 18:12:28', NULL),
(10, 44, 33, '3500.00', '3377.50', '122.50', '844.38', '2026-10-01 21:32:45', '2026-10-02 21:32:45', 'active', '0.00', NULL, 0, '2026-10-01 18:32:46', NULL),
(11, 42, 33, '3500.00', '3377.50', '122.50', '844.38', '2026-10-01 23:24:43', '2026-10-02 23:24:43', 'active', '0.00', NULL, 0, '2026-10-01 20:24:43', NULL),
(12, 42, 33, '3500.00', '3377.50', '122.50', '844.38', '2026-10-02 00:21:05', '2026-10-03 00:21:05', 'active', '0.00', NULL, 0, '2026-10-01 21:21:05', NULL),
(13, 106, 33, '2500.00', '2412.50', '87.50', '603.13', '2026-10-02 00:38:36', '2026-10-03 00:38:36', 'active', '0.00', NULL, 0, '2026-10-01 21:38:36', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `investment_events`
--

CREATE TABLE `investment_events` (
  `id` int(11) NOT NULL,
  `investment_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_type` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `info` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages_sent`
--

CREATE TABLE `messages_sent` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message_text` text DEFAULT NULL,
  `date_sent` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `id` int(11) NOT NULL,
  `package_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_change_requests`
--

CREATE TABLE `password_change_requests` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `new_password_hash` varchar(255) NOT NULL,
  `twofa_code` varchar(10) NOT NULL,
  `status` enum('pending_2fa','pending_approval','approved','rejected') DEFAULT 'pending_2fa',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `password_change_requests`
--

INSERT INTO `password_change_requests` (`id`, `admin_id`, `new_password_hash`, `twofa_code`, `status`, `created_at`) VALUES
(1, 18, '$2y$12$aBihEpj/pP125iJfb06NtuURpqHh2HuKqg5kGSIgnBGJsuLUVgpn6', '274163', 'pending_2fa', '2026-09-30 11:35:38'),
(2, 18, '$2y$12$BaKTwTf.w0AIKHyJCCNpw.40jw0p3J3wdhr.poJQIgM.JLFQER55y', '293100', 'rejected', '2026-09-30 11:55:04');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `method` varchar(50) DEFAULT NULL,
  `payment_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `paypal_deposits`
--

CREATE TABLE `paypal_deposits` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` varchar(255) NOT NULL,
  `amount_usd` decimal(12,2) NOT NULL,
  `amount_kes` decimal(12,2) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `credited` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plans`
--

CREATE TABLE `plans` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `min_amount` decimal(15,2) NOT NULL,
  `max_amount` decimal(15,2) NOT NULL,
  `color` varchar(20) DEFAULT '#FFD700',
  `duration_hours` decimal(5,2) NOT NULL DEFAULT 24.00 COMMENT 'Plan duration in hours'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `plans`
--

INSERT INTO `plans` (`id`, `name`, `duration_days`, `interest_rate`, `min_amount`, `max_amount`, `color`, `duration_hours`) VALUES
(33, 'Silver', 1, '25.00', '800.00', '3500.00', '#C0C0C0', '24.00'),
(34, 'Gold', 3, '35.00', '5000.00', '8500.00', '#FFD700', '72.00'),
(35, 'Lotus', 7, '45.00', '10000.00', '25000.00', '#E5E4E2', '168.00'),
(36, 'VIP', 14, '55.00', '40000.00', '100000.00', '#FF4500', '504.00');

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `referrer_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referral_bonus`
--

CREATE TABLE `referral_bonus` (
  `id` int(11) NOT NULL,
  `referrer_id` int(11) NOT NULL,
  `referred_user_id` int(11) NOT NULL,
  `investment_id` int(11) NOT NULL,
  `bonus_amount` decimal(12,2) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `referral_bonus`
--

INSERT INTO `referral_bonus` (`id`, `referrer_id`, `referred_user_id`, `investment_id`, `bonus_amount`, `created_at`) VALUES
(1, 42, 44, 6, '50.66', '2026-09-30 15:54:36'),
(2, 42, 44, 7, '123.04', '2026-09-30 15:55:21'),
(3, 42, 44, 10, '50.66', '2026-10-01 11:32:46');

-- --------------------------------------------------------

--
-- Table structure for table `referral_bonus_log`
--

CREATE TABLE `referral_bonus_log` (
  `id` int(11) NOT NULL,
  `referrer_id` int(11) NOT NULL,
  `referred_user_id` int(11) NOT NULL,
  `referred_id` int(11) NOT NULL,
  `investment_id` int(11) DEFAULT NULL,
  `bonus_amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`) VALUES
(1, 'fee_percentage', '3.5');

-- --------------------------------------------------------

--
-- Table structure for table `system_event_log`
--

CREATE TABLE `system_event_log` (
  `id` int(11) NOT NULL,
  `event_uid` varchar(255) NOT NULL,
  `event_type` varchar(120) NOT NULL DEFAULT 'event',
  `category` varchar(50) NOT NULL DEFAULT 'system',
  `actor_type` varchar(20) NOT NULL DEFAULT 'system',
  `actor_id` int(11) NOT NULL DEFAULT 0,
  `actor_label` varchar(255) NOT NULL DEFAULT '',
  `target_type` varchar(80) NOT NULL DEFAULT '',
  `target_id` int(11) NOT NULL DEFAULT 0,
  `target_label` varchar(255) NOT NULL DEFAULT '',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(80) NOT NULL DEFAULT '',
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `user_agent` varchar(255) NOT NULL DEFAULT '',
  `source_table` varchar(80) NOT NULL DEFAULT '',
  `source_id` int(11) NOT NULL DEFAULT 0,
  `occurred_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_event_log`
--

INSERT INTO `system_event_log` (`id`, `event_uid`, `event_type`, `category`, `actor_type`, `actor_id`, `actor_label`, `target_type`, `target_id`, `target_label`, `amount`, `status`, `details`, `ip_address`, `user_agent`, `source_table`, `source_id`, `occurred_at`, `created_at`) VALUES
(1, 'log:02b19dc4d65cd029eb1e470b4507eb58', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_monitor.php', '0.00', '', 'GET /admin_monitor.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-01 13:49:03', '2026-10-01 13:49:03'),
(2, 'log:a97b352264927af68dadab7d90ea39bf', 'http_get', 'system', 'admin', 18, 'GIBAL', 'http_request', 0, 'install_event_logging.php', '0.00', '', 'GET /install_event_logging.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-01 13:50:07', '2026-10-01 13:50:07'),
(3, 'log:e1217179c9f37657a24874c0e0dc2cb0', 'http_get', 'system', 'admin', 18, 'GIBAL', 'http_request', 0, 'install_event_logging.php', '0.00', '', 'GET /install_event_logging.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-01 13:51:02', '2026-10-01 13:51:02'),
(4, 'log:4ea25378c0748d1f828c2c17aa830659', 'http_get', 'users', 'admin', 18, 'GIBAL', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-01 13:55:50', '2026-10-01 13:55:50'),
(5, 'log:8174a46600302441a03755f29c80b46a', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'withdraw.php', '0.00', '', 'GET /withdraw.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-01 13:56:03', '2026-10-01 13:56:03'),
(6, 'log:29c4c564f1b2f20acd52b86549e3e559', 'http_post', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'withdraw.php', '0.00', '', 'POST /withdraw.php | POST: {\"amount\":\"1500\",\"withdraw_token\":\"***\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-01 13:56:26', '2026-10-01 13:56:26'),
(7, 'log:696441d145fbaa04cf15bb11f16c5957', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'withdraw.php', '0.00', '', 'GET /withdraw.php?success=1 | GET: {\"success\":\"1\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-01 13:56:27', '2026-10-01 13:56:27'),
(8, 'log:8e44df1e2a105f07e73556db67396709', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'withdraw.php', '0.00', '', 'GET /withdraw.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-01 13:56:31', '2026-10-01 13:56:31'),
(9, 'log:6bee8b29659688de9d474736489cdcec', 'http_get', 'users', 'admin', 18, 'GIBAL', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-01 13:57:24', '2026-10-01 13:57:24'),
(10, 'log:5c310883594632bfa4df54974fc89bca', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_dashboard.php', '0.00', '', 'GET /admin_dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-01 13:59:00', '2026-10-01 13:59:00'),
(11, 'log:7bc131c0c16da8aea129a5feb96aef87', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_monitor.php', '0.00', '', 'GET /admin_monitor.php?em_ajax=export&category=all&actor_type=all&search=&from=&to= | GET: {\"em_ajax\":\"export\",\"category\":\"all\",\"actor_type\":\"all\",\"search\":\"\",\"from\":\"\",\"to\":\"\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-01 14:00:17', '2026-10-01 14:00:17'),
(12, 'evt:271df814973250a0f72c53a9:1790889029.07', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_monitor.php', '0.00', '', 'GET /admin_monitor.php?tab=feed | GET: {\"tab\":\"feed\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:10:29', '2026-10-01 14:10:29'),
(13, 'evt:8b2853b5fb93ca50183bf626:1790889050.06', 'installer_test_event', 'system', 'admin', 18, 'GIBAL', 'installer', 0, 'Event Logging Installer', '0.00', '', 'Safe installer test event. InfinityFree trigger method is disabled and replaced with PHP logging + scanner.', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'install_event_logging', 0, '2026-10-02 00:10:50', '2026-10-01 14:10:50'),
(14, 'evt:ad95127bb0e79e4eada2af21:1790889493.02', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_monitor.php', '0.00', '', 'GET /admin_monitor.php?tab=settings | GET: {\"tab\":\"settings\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:18:13', '2026-10-01 14:18:13'),
(15, 'evt:129219f1fe09486bdf49f351:1790889604.54', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_monitor.php', '0.00', '', 'GET /admin_monitor.php?tab=feed | GET: {\"tab\":\"feed\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:20:04', '2026-10-01 14:20:04'),
(16, 'evt:ef71c6fe55fdf12197dc97ab:1790889628.52', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'invest.php', '0.00', '', 'GET /invest.php?plan_id=33 | GET: {\"plan_id\":\"33\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:20:28', '2026-10-01 14:20:28'),
(17, 'evt:21448198b99bcb564a541f3d:1790889665.67', 'http_post', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'invest.php', '0.00', '', 'POST /invest.php?plan_id=33 | GET: {\"plan_id\":\"33\"} | POST: {\"plan_id\":\"33\",\"amount\":\"3500\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:21:05', '2026-10-01 14:21:05'),
(18, 'evt:5a08da43fbd87a6f9e21a406:1790889667.26', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'invest.php', '0.00', '', 'GET /invest.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:21:07', '2026-10-01 14:21:07'),
(19, 'scan:investments:12:8f54b5577e708caba8694344f180d1d6', 'db_change', 'finance', 'user', 42, 'User #42', 'investments', 12, 'Investments #12', '3500.00', 'active', '{\"id\":\"12\",\"user_id\":\"42\",\"plan_id\":\"33\",\"amount\":\"3500.00\",\"net_amount\":\"3377.50\",\"fee\":\"122.50\",\"expected_interest\":\"844.38\",\"start_date\":\"2026-10-02 00:21:05\",\"end_date\":\"2026-10-03 00:21:05\",\"status\":\"active\",\"interest\":\"0.00\",\"admin_id\":\"\",\"processed\":\"0\",\"created_at\":\"2026-10-01 14:21:05\",\"matured_at\":\"\"}', '', '', 'investments', 12, '2026-10-01 14:21:05', '2026-10-01 14:21:11'),
(20, 'scan:transactions:12:149fce02fa7b88e881ed35e2ee57d32f', 'db_change', 'finance', 'user', 42, 'User #42', 'transactions', 12, 'Transactions #12', '3377.50', 'PENDING', '{\"id\":\"12\",\"user_id\":\"42\",\"transaction_id\":\"\",\"type\":\"debit\",\"amount\":\"3377.50\",\"description\":\"Invested Ksh 3,377.50 in plan Silver\",\"payment_method\":\"PayPal\",\"paypal_txn_id\":\"\",\"status\":\"PENDING\",\"date\":\"2026-10-01 14:21:05\",\"created_at\":\"2026-10-02 00:21:05\",\"amount_kes\":\"0.00\",\"deposit_method\":\"btcpay\"}', '', '', 'transactions', 12, '2026-10-02 00:21:05', '2026-10-01 14:21:11'),
(21, 'scan:daily_fees:9:3a83155b2561b5bf772cd179e655314a', 'db_change', 'finance', 'user', 42, 'User #42', 'daily_fees', 9, 'Daily fees #9', '122.50', '', '{\"id\":\"9\",\"fee_amount\":\"122.50\",\"fee_date\":\"2026-10-02 00:21:05\",\"source\":\"investment\",\"description\":\"\",\"created_at\":\"2026-10-01 14:21:05\",\"user_id\":\"42\"}', '', '', 'daily_fees', 9, '2026-10-01 14:21:05', '2026-10-01 14:21:11'),
(22, 'evt:0838043c06aec38dd7ec4f44:1790889746.85', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_monitor.php', '0.00', '', 'GET /admin_monitor.php?tab=feed | GET: {\"tab\":\"feed\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:22:26', '2026-10-01 14:22:26'),
(23, 'evt:85aec207488d9dae6787da21:1790889836.08', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:23:56', '2026-10-01 14:23:56'),
(24, 'evt:0d36fdcf2e6b1f29c7b8130c:1790889840.79', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'withdraw.php', '0.00', '', 'GET /withdraw.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:24:00', '2026-10-01 14:24:00'),
(25, 'evt:e06c604b5b5592abe7a37b06:1790889850.04', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'withdrawals_requested.php', '0.00', '', 'GET /withdrawals_requested.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:24:10', '2026-10-01 14:24:10'),
(26, 'evt:5e833736c41a6132ebbfb3b7:1790889854.58', 'http_post_approve', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'withdrawals_requested.php', '0.00', '', 'POST /withdrawals_requested.php?status=all&start_date=&end_date= | GET: {\"status\":\"all\",\"start_date\":\"\",\"end_date\":\"\"} | POST: {\"csrf_token\":\"***\",\"withdraw_id\":\"8\",\"action\":\"approve\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:24:14', '2026-10-01 14:24:14'),
(27, 'evt:51a15a4c25d1e767fdec4a8c:1790889854.8', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'withdrawals_requested.php', '0.00', '', 'GET /withdrawals_requested.php?status=all&start_date=&end_date= | GET: {\"status\":\"all\",\"start_date\":\"\",\"end_date\":\"\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:24:14', '2026-10-01 14:24:14'),
(28, 'scan:withdrawals:8:742e585f8ccd83dc17afbd8ca78e471d', 'db_change', 'finance', 'user', 42, 'User #42', 'withdrawals', 8, 'Withdrawals #8', '1500.00', 'approved', '{\"id\":\"8\",\"user_id\":\"42\",\"amount\":\"1500.00\",\"status\":\"approved\",\"processed_at\":\"2026-10-01 14:24:14\",\"created_at\":\"2026-10-01 13:56:26\",\"balance_at_request\":\"0.00\"}', '', '', 'withdrawals', 8, '2026-10-01 14:24:14', '2026-10-01 14:24:18'),
(29, 'evt:cd3e0444b9ae4c87bc45c228:1790889893.26', 'http_post', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'withdraw.php', '0.00', '', 'POST /withdraw.php | POST: {\"amount\":\"500\",\"withdraw_token\":\"***\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:24:53', '2026-10-01 14:24:53'),
(30, 'evt:daa47b5cafe8358b6093c05a:1790889894.74', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'withdraw.php', '0.00', '', 'GET /withdraw.php?success=1 | GET: {\"success\":\"1\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:24:54', '2026-10-01 14:24:54'),
(31, 'evt:7c7ec4843d376e3ee3be25af:1790889898.28', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'withdraw.php', '0.00', '', 'GET /withdraw.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:24:58', '2026-10-01 14:24:58'),
(32, 'scan:withdrawals:9:70020721f7fd1139d7aa5560eb987192', 'db_change', 'finance', 'user', 42, 'User #42', 'withdrawals', 9, 'Withdrawals #9', '500.00', 'pending', '{\"id\":\"9\",\"user_id\":\"42\",\"amount\":\"500.00\",\"status\":\"pending\",\"processed_at\":\"\",\"created_at\":\"2026-10-01 14:24:53\",\"balance_at_request\":\"0.00\"}', '', '', 'withdrawals', 9, '2026-10-01 14:24:53', '2026-10-01 14:24:58'),
(33, 'evt:76e087c9a7bac40a7736bcf0:1790889917.04', 'http_post_approve', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'withdrawals_requested.php', '0.00', '', 'POST /withdrawals_requested.php?status=all&start_date=&end_date= | GET: {\"status\":\"all\",\"start_date\":\"\",\"end_date\":\"\"} | POST: {\"csrf_token\":\"***\",\"withdraw_id\":\"9\",\"action\":\"approve\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:25:17', '2026-10-01 14:25:17'),
(34, 'evt:19f60560641c5c3d9098ec7f:1790889917.25', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'withdrawals_requested.php', '0.00', '', 'GET /withdrawals_requested.php?status=all&start_date=&end_date= | GET: {\"status\":\"all\",\"start_date\":\"\",\"end_date\":\"\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:25:17', '2026-10-01 14:25:17'),
(35, 'scan:withdrawals:9:d634d651ee601343bb518b646d5ea6b5', 'db_change', 'finance', 'user', 42, 'User #42', 'withdrawals', 9, 'Withdrawals #9', '500.00', 'approved', '{\"id\":\"9\",\"user_id\":\"42\",\"amount\":\"500.00\",\"status\":\"approved\",\"processed_at\":\"2026-10-01 14:25:17\",\"created_at\":\"2026-10-01 14:24:53\",\"balance_at_request\":\"0.00\"}', '', '', 'withdrawals', 9, '2026-10-01 14:25:17', '2026-10-01 14:25:23'),
(36, 'evt:4537c1a7c19179b688f2f58f:1790889947.73', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:25:47', '2026-10-01 14:25:47'),
(37, 'evt:346b7d28a974811446550749:1790889955.43', 'http_post', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'POST /dashboard.php | POST: {\"user_chat_message\":\"Hello\",\"user_send_chat\":\"\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:25:55', '2026-10-01 14:25:55'),
(38, 'evt:dd6c262ead37d0f7c27c47f5:1790889955.62', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:25:55', '2026-10-01 14:25:55'),
(39, 'scan:admin_chats:20:250678ab73b41ec44fb296d2b798c5d3', 'db_change', 'support', 'user', 42, 'User #42', 'admin_chats', 20, 'Admin chats #20', '0.00', '', '{\"id\":\"20\",\"admin_id\":\"\",\"user_id\":\"42\",\"message\":\"Hello\",\"sender_type\":\"user\",\"is_read\":\"0\",\"created_at\":\"2026-10-01 14:25:55\"}', '', '', 'admin_chats', 20, '2026-10-01 14:25:55', '2026-10-01 14:25:58'),
(40, 'evt:d3219385dbbe8db36ca54bf1:1790889982.75', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_settings.php', '0.00', '', 'GET /admin_settings.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:26:22', '2026-10-01 14:26:22'),
(41, 'evt:9c2e04f115428c3815edd06a:1790889989.05', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_settings.php', '0.00', '', 'GET /admin_settings.php?chat_user=42 | GET: {\"chat_user\":\"42\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:26:29', '2026-10-01 14:26:29'),
(42, 'evt:0b326af260807aed3013631a:1790889999.84', 'http_post', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_settings.php', '0.00', '', 'POST /admin_settings.php | POST: {\"ajax_send_chat\":\"1\",\"chat_user_id\":\"42\",\"chat_message\":\"hi how can i be of help today\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:26:39', '2026-10-01 14:26:39'),
(43, 'scan:admin_chats:21:5c3020c420c96b217ea742015872eea3', 'db_change', 'support', 'admin', 18, 'Admin #18', 'admin_chats', 21, 'Admin chats #21', '0.00', '', '{\"id\":\"21\",\"admin_id\":\"18\",\"user_id\":\"42\",\"message\":\"hi how can i be of help today\",\"sender_type\":\"admin\",\"is_read\":\"0\",\"created_at\":\"2026-10-01 14:26:39\"}', '', '', 'admin_chats', 21, '2026-10-01 14:26:39', '2026-10-01 14:26:43'),
(44, 'evt:2fc3ad185b28005672645ae0:1790890020.5', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:00', '2026-10-01 14:27:00'),
(45, 'evt:4d68a6a0224f22f898fbfe4a:1790890020.71', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:00', '2026-10-01 14:27:00'),
(46, 'evt:a8abd2d16a39b9e50054ef17:1790890021.7', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:01', '2026-10-01 14:27:01'),
(47, 'evt:33ed73d024544871aa5cbc7e:1790890022.69', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:02', '2026-10-01 14:27:02');
INSERT INTO `system_event_log` (`id`, `event_uid`, `event_type`, `category`, `actor_type`, `actor_id`, `actor_label`, `target_type`, `target_id`, `target_label`, `amount`, `status`, `details`, `ip_address`, `user_agent`, `source_table`, `source_id`, `occurred_at`, `created_at`) VALUES
(48, 'evt:38797ee9643242fba0ee8d66:1790890023.68', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:03', '2026-10-01 14:27:03'),
(49, 'evt:fabb6e017ce8024712ec1c7c:1790890023.7', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:03', '2026-10-01 14:27:03'),
(50, 'evt:1200eb28aca01fbab29443d5:1790890024.45', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:04', '2026-10-01 14:27:04'),
(51, 'evt:0069a27586664c6fa74b619c:1790890024.7', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:04', '2026-10-01 14:27:04'),
(52, 'evt:3b7888702c40b2bcbe83bd8f:1790890025.46', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:05', '2026-10-01 14:27:05'),
(53, 'evt:ba01d84adb8cdfbbbbc0131e:1790890025.69', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:05', '2026-10-01 14:27:05'),
(54, 'evt:6cefc87f089ae0fb7b7bd91b:1790890026.62', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:06', '2026-10-01 14:27:06'),
(55, 'evt:21e77b2eb8f8473ac8906082:1790890027.68', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:07', '2026-10-01 14:27:07'),
(56, 'evt:07f427ab826879b4c3176bbb:1790890028.71', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:08', '2026-10-01 14:27:08'),
(57, 'evt:d1d427eb4e9e14b5f2900d97:1790890028.73', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:08', '2026-10-01 14:27:08'),
(58, 'evt:95e0053c9e6f1839a03c4b9d:1790890029.67', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:09', '2026-10-01 14:27:09'),
(59, 'evt:bc35122f58f9864876463802:1790890030.46', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:10', '2026-10-01 14:27:10'),
(60, 'evt:5cf28a3efd2bb397b2366b90:1790890030.68', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:10', '2026-10-01 14:27:10'),
(61, 'evt:ae0192e57a7bf21d3e0cefae:1790890031.7', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:11', '2026-10-01 14:27:11'),
(62, 'evt:ac09957949a94090ad21fce4:1790890032.7', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:12', '2026-10-01 14:27:12'),
(63, 'evt:904c17e2aabf30e64a128546:1790890033.71', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:13', '2026-10-01 14:27:13'),
(64, 'evt:2ef87a0d0983c4873ffef92d:1790890033.72', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:13', '2026-10-01 14:27:13'),
(65, 'evt:e16e80701df9c93187b2e9c0:1790890034.73', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:14', '2026-10-01 14:27:14'),
(66, 'evt:79e8a030309a40acc72a9c39:1790890035.03', 'http_get', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_monitor.php', '0.00', '', 'GET /admin_monitor.php?tab=feed | GET: {\"tab\":\"feed\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:15', '2026-10-01 14:27:15'),
(67, 'evt:52b17657d80dc605fc3df163:1790890035.26', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:15', '2026-10-01 14:27:15'),
(68, 'evt:7b56d3c344b760e2389a4dad:1790890035.61', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:15', '2026-10-01 14:27:15'),
(69, 'evt:591c336b0e1fb5d84b6f53da:1790890040.41', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:20', '2026-10-01 14:27:20'),
(70, 'evt:d714f5eb4635ff37744394d3:1790890041.97', 'http_get', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_dashboard.php', '0.00', '', 'GET /admin_dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:21', '2026-10-01 14:27:22'),
(71, 'evt:b547f1f8b715a9f4fe9bcc53:1790890042.18', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:22', '2026-10-01 14:27:22'),
(72, 'evt:a5169a81ebd68b93fd8f5375:1790890069.98', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'POST /admin_login.php | POST: {\"identifier\":\"gkhalibson11@gmail.com\",\"password\":\"***\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:49', '2026-10-01 14:27:50'),
(73, 'evt:e3ee49a26a45ef0ddc0d5aeb:1790890073.78', 'http_get', 'system', 'guest', 0, 'Guest', 'http_request', 0, 'verify_otp.php', '0.00', '', 'GET /verify_otp.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:27:53', '2026-10-01 14:27:53'),
(74, 'evt:7eb7254c756fa6604e3afbb5:1790890099.84', 'http_post', 'system', 'guest', 0, 'Guest', 'http_request', 0, 'verify_otp.php', '0.00', '', 'POST /verify_otp.php | POST: {\"otp_code\":\"***\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:28:19', '2026-10-01 14:28:19'),
(75, 'evt:e4efa0c6a0083cf74e589b8e:1790890100.06', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_dashboard.php', '0.00', '', 'GET /admin_dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:28:20', '2026-10-01 14:28:20'),
(76, 'evt:f964399b6deadaae8ebb3799:1790890107.24', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_dashboard.php', '0.00', '', 'GET /admin_dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:28:27', '2026-10-01 14:28:27'),
(77, 'evt:fc4c77559e029afc73a8d8ad:1790890114.82', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_monitor.php', '0.00', '', 'GET /admin_monitor.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:28:34', '2026-10-01 14:28:34'),
(78, 'evt:26459aba7e6affcd5e12cee7:1790890219.5', 'http_get', 'users', 'admin', 18, 'GIBAL', 'http_request', 0, 'user_fees.php', '0.00', '', 'GET /user_fees.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:30:19', '2026-10-01 14:30:19'),
(79, 'evt:65aad1610c2390d76a23fcd5:1790890309.57', 'auth_login_request', 'security', 'admin', 18, 'GIBAL', 'http_request', 0, 'login.php', '0.00', '', 'GET /login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:31:49', '2026-10-01 14:31:49'),
(80, 'evt:64173a4765015b188ea97002:1790890465.65', 'auth_login_request', 'security', 'admin', 18, 'GIBAL', 'http_request', 0, 'login.php', '0.00', '', 'GET /login.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:34:25', '2026-10-01 14:34:25'),
(81, 'evt:57b5422d122b1dd32cfb8728:1790890487.2', 'auth_login_request', 'security', 'admin', 18, 'GIBAL', 'http_request', 0, 'login.php', '0.00', '', 'POST /login.php | POST: {\"email\":\"awinorich@gmail.com\",\"password\":\"***\",\"login\":\"\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:34:47', '2026-10-01 14:34:47'),
(82, 'evt:7a4143ea4b02e4d4470f056e:1790890487.72', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:34:47', '2026-10-01 14:34:47'),
(83, 'scan:users:106:6eff16cf699fa4f1a65fd39fa3c52ec7', 'db_change', 'users', 'user', 106, 'User #106', 'users', 106, 'Users #106', '0.00', '', '{\"id\":\"106\",\"name\":\"Awino Richard\",\"username\":\"Awino\",\"email\":\"awinorich@gmail.com\",\"phone\":\"+254795520827\",\"password\":\"***\",\"account_balance\":\"0.00\",\"pending_withdrawals\":\"0.00\",\"invested_balance\":\"0.00\",\"created_at\":\"2026-10-01 14:34:21\",\"fullname\":\"\",\"referrer_id\":\"\",\"referral_code\":\"D5EF7AEE\",\"admin_id\":\"\",\"total_expected_interest\":\"0.00\",\"referral_balance\":\"0.00\",\"referral_wallet\":\"0.00\",\"referred_by\":\"\",\"ref_code\":\"\",\"balance\":\"0.00\",\"accepted_terms_at\":\"2026-10-02 00:34:21\",\"accepted_terms_ip\":\"102.203.137.244\",\"accepted_terms_user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"role\":\"user\",\"kyc_status\":\"Not Submitted\"}', '', '', 'users', 106, '2026-10-01 14:34:21', '2026-10-01 14:35:04'),
(84, 'evt:9fd3917a612360c520839c64:1790890538.75', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_dashboard.php', '0.00', '', 'GET /admin_dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:35:38', '2026-10-01 14:35:38'),
(85, 'evt:f91b037d62e7a58133fd469a:1790890544.66', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_settings.php', '0.00', '', 'GET /admin_settings.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:35:44', '2026-10-01 14:35:44'),
(86, 'evt:dc4ac515c9d39dcdb05b232f:1790890553', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'deposit.php', '0.00', '', 'GET /deposit.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:35:53', '2026-10-01 14:35:53'),
(87, 'evt:f63d8b5f38ec44ab3c06d314:1790890622.65', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'deposit.php', '0.00', '', 'GET /deposit.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:37:02', '2026-10-01 14:37:02'),
(88, 'evt:0608e1b1f39ad26021d93939:1790890648.9', 'http_get', 'users', 'admin', 18, 'GIBAL', 'http_request', 0, 'paystack_verify.php', '0.00', '', 'GET /paystack_verify.php?reference=GIBAL_271850769&user_id=106 | GET: {\"reference\":\"GIBAL_271850769\",\"user_id\":\"106\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:37:28', '2026-10-01 14:37:29'),
(89, 'evt:e0b16ef2f79c3c4ee6e519b0:1790890649.33', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'deposit.php', '0.00', '', 'GET /deposit.php?status=success&msg=Deposit+of+Ksh+2%2C500.00+successful%21 | GET: {\"status\":\"success\",\"msg\":\"Deposit of Ksh 2,500.00 successful!\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:37:29', '2026-10-01 14:37:29'),
(90, 'evt:e7ae3ac01b1f43b67f59b599:1790890659.41', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:37:39', '2026-10-01 14:37:39'),
(91, 'evt:e72112f77279a0fcb46a2086:1790890690.31', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_settings.php', '0.00', '', 'GET /admin_settings.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:38:10', '2026-10-01 14:38:10'),
(92, 'evt:a28df7d2cdf5b6c98b856398:1790890699.23', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_dashboard.php', '0.00', '', 'GET /admin_dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:38:19', '2026-10-01 14:38:19'),
(93, 'evt:e62411e43b64ba2dc1f89b62:1790890708.09', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'invest.php', '0.00', '', 'GET /invest.php?plan_id=33 | GET: {\"plan_id\":\"33\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:38:28', '2026-10-01 14:38:28'),
(94, 'evt:0878a6466b290dc5d5f06a57:1790890716.25', 'http_post', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'invest.php', '0.00', '', 'POST /invest.php?plan_id=33 | GET: {\"plan_id\":\"33\"} | POST: {\"plan_id\":\"33\",\"amount\":\"2500\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:38:36', '2026-10-01 14:38:36'),
(95, 'evt:5a1c78621c509949021d0b50:1790890718.86', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'invest.php', '0.00', '', 'GET /invest.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:38:38', '2026-10-01 14:38:39'),
(96, 'scan:investments:13:c67f5b355e0ad3189eb73e41e2567c79', 'db_change', 'finance', 'user', 106, 'User #106', 'investments', 13, 'Investments #13', '2500.00', 'active', '{\"id\":\"13\",\"user_id\":\"106\",\"plan_id\":\"33\",\"amount\":\"2500.00\",\"net_amount\":\"2412.50\",\"fee\":\"87.50\",\"expected_interest\":\"603.13\",\"start_date\":\"2026-10-02 00:38:36\",\"end_date\":\"2026-10-03 00:38:36\",\"status\":\"active\",\"interest\":\"0.00\",\"admin_id\":\"\",\"processed\":\"0\",\"created_at\":\"2026-10-01 14:38:36\",\"matured_at\":\"\"}', '', '', 'investments', 13, '2026-10-01 14:38:36', '2026-10-01 14:38:43');
INSERT INTO `system_event_log` (`id`, `event_uid`, `event_type`, `category`, `actor_type`, `actor_id`, `actor_label`, `target_type`, `target_id`, `target_label`, `amount`, `status`, `details`, `ip_address`, `user_agent`, `source_table`, `source_id`, `occurred_at`, `created_at`) VALUES
(97, 'scan:transactions:13:13afa05c7464ab75b3f604e160790b28', 'db_change', 'finance', 'user', 106, 'User #106', 'transactions', 13, 'Transactions #13', '2412.50', 'PENDING', '{\"id\":\"13\",\"user_id\":\"106\",\"transaction_id\":\"\",\"type\":\"debit\",\"amount\":\"2412.50\",\"description\":\"Invested Ksh 2,412.50 in plan Silver\",\"payment_method\":\"PayPal\",\"paypal_txn_id\":\"\",\"status\":\"PENDING\",\"date\":\"2026-10-01 14:38:36\",\"created_at\":\"2026-10-02 00:38:36\",\"amount_kes\":\"0.00\",\"deposit_method\":\"btcpay\"}', '', '', 'transactions', 13, '2026-10-02 00:38:36', '2026-10-01 14:38:43'),
(98, 'scan:daily_fees:10:458f041c35bf54fb5e2f5cc6d2d3d17b', 'db_change', 'finance', 'user', 106, 'User #106', 'daily_fees', 10, 'Daily fees #10', '87.50', '', '{\"id\":\"10\",\"fee_amount\":\"87.50\",\"fee_date\":\"2026-10-02 00:38:36\",\"source\":\"investment\",\"description\":\"\",\"created_at\":\"2026-10-01 14:38:36\",\"user_id\":\"106\"}', '', '', 'daily_fees', 10, '2026-10-01 14:38:36', '2026-10-01 14:38:43'),
(99, 'evt:c9f72f2f475df81585f7c82d:1790890755.97', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:39:15', '2026-10-01 14:39:16'),
(100, 'evt:7cfee07ffc1cf7c0e1992c4a:1790890785.15', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php?dismiss_notif=2 | GET: {\"dismiss_notif\":\"2\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:39:45', '2026-10-01 14:39:45'),
(101, 'evt:13241e08eafa81a7b2584379:1790890785.34', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:39:45', '2026-10-01 14:39:45'),
(102, 'evt:95a9699974ae5463bab1402c:1790890788.72', 'http_get', 'support', 'admin', 18, 'GIBAL', 'http_request', 0, 'tickets.php', '0.00', '', 'GET /tickets.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:39:48', '2026-10-01 14:39:48'),
(103, 'evt:dc05e005ce91a42b86cdb34d:1790890830.96', 'http_post', 'support', 'admin', 18, 'GIBAL', 'http_request', 0, 'tickets.php', '0.00', '', 'POST /tickets.php | POST: {\"ticket_choice\":\"feedback\",\"subject\":\"Smooth site\",\"transaction_ref\":\"\",\"amount\":\"\",\"message\":\"Appreciate how good the site is. thank you.\\u2764\",\"create_ticket\":\"\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:40:30', '2026-10-01 14:40:31'),
(104, 'evt:1a0bfe0945c2264a04a47fa6:1790890831.17', 'http_get', 'support', 'admin', 18, 'GIBAL', 'http_request', 0, 'tickets.php', '0.00', '', 'GET /tickets.php?view=complaint-3 | GET: {\"view\":\"complaint-3\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:40:31', '2026-10-01 14:40:31'),
(105, 'evt:7509dc0656e13906426ae449:1790890835.25', 'http_get', 'support', 'admin', 18, 'GIBAL', 'http_request', 0, 'tickets.php', '0.00', '', 'GET /tickets.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:40:35', '2026-10-01 14:40:35'),
(106, 'evt:cb413f37a7de4d2c2824e095:1790890839.46', 'http_get', 'support', 'admin', 18, 'GIBAL', 'http_request', 0, 'tickets.php', '0.00', '', 'GET /tickets.php?view=complaint-3 | GET: {\"view\":\"complaint-3\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:40:39', '2026-10-01 14:40:39'),
(107, 'scan:complaints:3:67ec74252c890726e51ce031f83a1541', 'db_change', 'support', 'user', 106, 'User #106', 'complaints', 3, 'Complaints #3', '0.00', 'open', '{\"id\":\"3\",\"user_id\":\"106\",\"username\":\"\",\"subject\":\"Smooth site\",\"category\":\"feedback\",\"message\":\"Appreciate how good the site is. thank you.?\",\"status\":\"open\",\"admin_response\":\"\",\"created_at\":\"2026-10-01 14:40:31\",\"updated_at\":\"2026-10-01 14:40:31\"}', '', '', 'complaints', 3, '2026-10-01 14:40:31', '2026-10-01 14:40:45'),
(108, 'evt:78b42e9633d6d1dbc549d54d:1790890851.99', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_settings.php', '0.00', '', 'GET /admin_settings.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:40:51', '2026-10-01 14:40:52'),
(109, 'evt:ebe9f31fc0b3e26c0ec7ec35:1790890861.22', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_settings.php', '0.00', '', 'GET /admin_settings.php?view_ticket=complaint-3 | GET: {\"view_ticket\":\"complaint-3\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:41:01', '2026-10-01 14:41:01'),
(110, 'evt:21cb786dff9854be27465607:1790890870.09', 'http_post_send_message', 'support', 'admin', 18, 'GIBAL', 'http_request', 0, 'ticket_api.php', '0.00', '', 'POST /ticket_api.php | POST: {\"action\":\"send_message\",\"context\":\"admin\",\"ticket_type\":\"complaint\",\"ticket_id\":\"3\",\"message\":\"Most welcom\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:41:10', '2026-10-01 14:41:10'),
(111, 'evt:b5947437bcaf4e443c467c42:1790890872.11', 'http_post_update_status', 'support', 'admin', 18, 'GIBAL', 'http_request', 0, 'ticket_api.php', '0.00', '', 'POST /ticket_api.php | POST: {\"action\":\"update_status\",\"context\":\"admin\",\"ticket_type\":\"complaint\",\"ticket_id\":\"3\",\"status\":\"resolved\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:41:12', '2026-10-01 14:41:12'),
(112, 'scan:complaints:3:5a19c8a573de094a5a9a6206ee5e5661', 'db_change', 'support', 'user', 106, 'User #106', 'complaints', 3, 'Complaints #3', '0.00', 'resolved', '{\"id\":\"3\",\"user_id\":\"106\",\"username\":\"\",\"subject\":\"Smooth site\",\"category\":\"feedback\",\"message\":\"Appreciate how good the site is. thank you.?\",\"status\":\"resolved\",\"admin_response\":\"\",\"created_at\":\"2026-10-01 14:40:31\",\"updated_at\":\"2026-10-01 14:41:12\"}', '', '', 'complaints', 3, '2026-10-01 14:41:12', '2026-10-01 14:41:18'),
(113, 'scan:ticket_followups:52:a6a6cf569d80e096a43ae6c2eb36528d', 'db_change', 'support', 'admin', 18, 'Admin #18', 'ticket_followups', 52, 'Ticket followups #52', '0.00', '', '{\"id\":\"52\",\"ticket_type\":\"complaint\",\"ticket_id\":\"3\",\"user_id\":\"\",\"admin_id\":\"18\",\"message\":\"Most welcom\",\"created_at\":\"2026-10-01 14:41:10\"}', '', '', 'ticket_followups', 52, '2026-10-01 14:41:10', '2026-10-01 14:41:18'),
(114, 'evt:116deec367b0a3491c420edb:1790890903.06', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_monitor.php', '0.00', '', 'GET /admin_monitor.php?tab=feed&search=106&category=all&actor_type=all&from=&to= | GET: {\"tab\":\"feed\",\"search\":\"106\",\"category\":\"all\",\"actor_type\":\"all\",\"from\":\"\",\"to\":\"\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:41:43', '2026-10-01 14:41:43'),
(115, 'evt:210168ea9c8f3903f8749f18:1790890923.86', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_monitor.php', '0.00', '', 'GET /admin_monitor.php?tab=feed&search=User+%23106&category=all&actor_type=all&from=&to= | GET: {\"tab\":\"feed\",\"search\":\"User #106\",\"category\":\"all\",\"actor_type\":\"all\",\"from\":\"\",\"to\":\"\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:42:03', '2026-10-01 14:42:04'),
(116, 'evt:17cbc91de806a1964d7fa276:1790890938.13', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_monitor.php', '0.00', '', 'GET /admin_monitor.php?tab=feed&search=GIBAL&category=all&actor_type=all&from=&to= | GET: {\"tab\":\"feed\",\"search\":\"GIBAL\",\"category\":\"all\",\"actor_type\":\"all\",\"from\":\"\",\"to\":\"\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:42:18', '2026-10-01 14:42:18'),
(117, 'evt:3a69160dd0231d5f9e866ae6:1790890962.32', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_monitor.php', '0.00', '', 'GET /admin_monitor.php?tab=feed&search=&category=all&actor_type=all&from=&to= | GET: {\"tab\":\"feed\",\"search\":\"\",\"category\":\"all\",\"actor_type\":\"all\",\"from\":\"\",\"to\":\"\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:42:42', '2026-10-01 14:42:42'),
(118, 'scan:system_status:1:ca6e664af89b043ffb08d31438c60307', 'db_change', 'system', 'system', 0, 'Database Change', 'system_status', 1, 'System status #1', '0.00', 'operational', '{\"id\":\"1\",\"mode\":\"operational\",\"message\":\"We are performing a scheduled maintenance.\",\"updated_at\":\"2026-09-30 22:04:02\",\"notice\":\"\"}', '', '', 'system_status', 1, '2026-09-30 22:04:02', '2026-10-02 00:47:24'),
(119, 'evt:137254d1ae3bb7fceda45c57:1790891312.76', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:48:32', '2026-10-01 14:48:32'),
(120, 'evt:d70cb68e9b98917ef30d9ecc:1790891507.12', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_dashboard.php', '0.00', '', 'GET /admin_dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 00:51:47', '2026-10-01 14:51:47'),
(121, 'evt:37ba6a54bde7805394249794:1790892725.6', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php?dismiss_notif=1 | GET: {\"dismiss_notif\":\"1\"}', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 01:12:05', '2026-10-01 15:12:05'),
(122, 'evt:39d5e6428115f5ff22c98300:1790892725.81', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'http_request', 0, '2026-10-02 01:12:05', '2026-10-01 15:12:05'),
(123, 'evt:015002f3622a843ccf1acc13:1790898525.98', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 02:48:45', '2026-10-01 16:48:46'),
(124, 'evt:5420d583130747717b98b5dd:1790898531.4', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 02:48:51', '2026-10-01 16:48:51'),
(125, 'evt:5b99f6ccb201ac39cd9a1b17:1790898577.08', 'http_get', 'finance', 'user', 44, 'User #44', 'http_request', 0, 'deposit.php', '0.00', '', 'GET /deposit.php', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 02:49:37', '2026-10-01 16:49:37'),
(126, 'evt:48621e48e108e7f1c1e76c86:1790898580.05', 'http_get', 'finance', 'guest', 0, 'Guest', 'http_request', 0, 'deposit.php', '0.00', '', 'GET /deposit.php', '142.250.32.99', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', 'http_request', 0, '2026-10-02 02:49:40', '2026-10-01 16:49:40'),
(127, 'evt:32c84f0fddfbc8e5379b52ee:1790898580.07', 'http_get', 'finance', 'guest', 0, 'Guest', 'http_request', 0, 'deposit.php', '0.00', '', 'GET /deposit.php', '142.250.32.98', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', 'http_request', 0, '2026-10-02 02:49:40', '2026-10-01 16:49:40'),
(128, 'evt:4384eb375fc778d84d3164a2:1790898580.5', 'auth_login_request', 'security', 'guest', 0, 'Guest', 'http_request', 0, 'login.php', '0.00', '', 'GET /login.php', '142.250.32.99', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', 'http_request', 0, '2026-10-02 02:49:40', '2026-10-01 16:49:40'),
(129, 'evt:07f3a2d8058fb3cac2ba9dbf:1790898580.58', 'auth_login_request', 'security', 'guest', 0, 'Guest', 'http_request', 0, 'login.php', '0.00', '', 'GET /login.php', '142.250.32.99', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', 'http_request', 0, '2026-10-02 02:49:40', '2026-10-01 16:49:40'),
(130, 'evt:af2c82998f0ece0020bbb937:1790898614.23', 'http_post', 'finance', 'user', 44, 'User #44', 'http_request', 0, 'deposit.php', '0.00', '', 'POST /deposit.php | POST: {\"method\":\"paypal\",\"amount\":\"5\"}', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 02:50:14', '2026-10-01 16:50:14'),
(131, 'evt:3d348af147be8d385b396afc:1790898617.87', 'http_get', 'finance', 'user', 44, 'User #44', 'http_request', 0, 'deposit.php', '0.00', '', 'GET /deposit.php?status=success&msg=PayPal+order+created! | GET: {\"status\":\"success\",\"msg\":\"PayPal order created!\"}', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 02:50:17', '2026-10-01 16:50:18'),
(132, 'evt:fc7c90a392ca1d9037375cdb:1790898626.79', 'http_get', 'finance', 'user', 44, 'User #44', 'http_request', 0, 'deposit.php', '0.00', '', 'GET /deposit.php?status=success&msg=PayPal+order+created! | GET: {\"status\":\"success\",\"msg\":\"PayPal order created!\"}', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 02:50:26', '2026-10-01 16:50:27'),
(133, 'evt:2995ac0aa3f9ef50952e9ee6:1790898639.47', 'http_get', 'finance', 'user', 44, 'User #44', 'http_request', 0, 'deposit.php', '0.00', '', 'GET /deposit.php?status=success&msg=PayPal+order+created! | GET: {\"status\":\"success\",\"msg\":\"PayPal order created!\"}', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 02:50:39', '2026-10-01 16:50:39'),
(134, 'evt:2566825bb1b23d74abb58675:1790898660.7', 'auth_login_request', 'admin', 'user', 44, 'User #44', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 02:51:00', '2026-10-01 16:51:01'),
(135, 'evt:1f3e32171c15de9ed4dc0159:1790898687.21', 'auth_login_request', 'admin', 'user', 44, 'User #44', 'http_request', 0, 'admin_login.php', '0.00', '', 'POST /admin_login.php | POST: {\"identifier\":\"gkhalibson11@gmail.com\",\"password\":\"***\"}', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 02:51:27', '2026-10-01 16:51:27'),
(136, 'evt:d9ccacd492a36a833ff401e4:1790898701.57', 'auth_login_request', 'admin', 'user', 44, 'User #44', 'http_request', 0, 'admin_login.php', '0.00', '', 'POST /admin_login.php | POST: {\"identifier\":\"gkhalibson11@gmail.com\",\"password\":\"***\"}', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 02:51:41', '2026-10-01 16:51:41'),
(137, 'evt:a8e3ee08ff4a3b69bf2b7ccb:1790898716.86', 'auth_login_request', 'admin', 'user', 44, 'User #44', 'http_request', 0, 'admin_login.php', '0.00', '', 'POST /admin_login.php | POST: {\"identifier\":\"gkhalibson11@gmail.com\",\"password\":\"***\"}', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 02:51:56', '2026-10-01 16:51:57'),
(138, 'evt:71eca5056cbfc32d3195328e:1790898732.1', 'auth_login_request', 'admin', 'user', 44, 'User #44', 'http_request', 0, 'admin_login.php', '0.00', '', 'POST /admin_login.php | POST: {\"identifier\":\"gkhalibson11@gmail.com\",\"password\":\"***\"}', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 02:52:12', '2026-10-01 16:52:12'),
(139, 'evt:5e65a0ab4f064258ea034420:1790898833.87', 'auth_login_request', 'admin', 'user', 44, 'User #44', 'http_request', 0, 'admin_login.php', '0.00', '', 'POST /admin_login.php | POST: {\"identifier\":\"gkhalibson11@gmail.com\",\"password\":\"***\"}', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 02:53:53', '2026-10-01 16:53:54'),
(140, 'evt:538bf325d1fb9a58a9c07c39:1790898837.71', 'http_get', 'system', 'user', 44, 'User #44', 'http_request', 0, 'verify_otp.php', '0.00', '', 'GET /verify_otp.php', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 02:53:57', '2026-10-01 16:53:58');
INSERT INTO `system_event_log` (`id`, `event_uid`, `event_type`, `category`, `actor_type`, `actor_id`, `actor_label`, `target_type`, `target_id`, `target_label`, `amount`, `status`, `details`, `ip_address`, `user_agent`, `source_table`, `source_id`, `occurred_at`, `created_at`) VALUES
(141, 'evt:a7a9626369575a11afb806bc:1790898839.56', 'http_get', 'system', 'guest', 0, 'Guest', 'http_request', 0, 'verify_otp.php', '0.00', '', 'GET /verify_otp.php', '142.250.32.100', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', 'http_request', 0, '2026-10-02 02:53:59', '2026-10-01 16:53:59'),
(142, 'evt:6c12de64114ebac2044fdd81:1790898839.61', 'auth_login_request', 'admin', 'guest', 0, 'Guest', 'http_request', 0, 'admin_login.php', '0.00', '', 'GET /admin_login.php', '142.250.32.100', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', 'http_request', 0, '2026-10-02 02:53:59', '2026-10-01 16:53:59'),
(143, 'evt:fe0f6821d565377e566f0dd1:1790899480.31', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 03:04:40', '2026-10-01 17:04:40'),
(144, 'evt:b41e155f8757f35da410380b:1790899493.73', 'http_get', 'system', 'user', 44, 'User #44', 'http_request', 0, 'verify_otp.php', '0.00', '', 'GET /verify_otp.php', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 03:04:53', '2026-10-01 17:04:53'),
(145, 'evt:9990b19c66e250f71f7411fb:1790899495.72', 'http_post', 'system', 'user', 44, 'User #44', 'http_request', 0, 'verify_otp.php', '0.00', '', 'POST /verify_otp.php | POST: {\"resend_otp\":\"***\"}', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 03:04:55', '2026-10-01 17:04:55'),
(146, 'evt:17d3bc500d31f279bb4b7867:1790899534.01', 'http_post', 'system', 'user', 44, 'User #44', 'http_request', 0, 'verify_otp.php', '0.00', '', 'POST /verify_otp.php | POST: {\"otp_code\":\"***\"}', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 03:05:34', '2026-10-01 17:05:33'),
(147, 'evt:ee4ce2ba6cc55dac4783f489:1790899534.55', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_dashboard.php', '0.00', '', 'GET /admin_dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 03:05:34', '2026-10-01 17:05:34'),
(148, 'evt:37bb0e386adf4ecc42f25229:1790899549.1', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_deposits.php', '0.00', '', 'GET /admin_deposits.php', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 03:05:49', '2026-10-01 17:05:48'),
(149, 'evt:66b9bd87f54ad032413415e2:1790899560.28', 'http_post_approve', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_deposits.php', '0.00', '', 'POST /admin_deposits.php | POST: {\"deposit_id\":\"240\",\"action\":\"approve\",\"ajax\":\"1\"}', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 03:06:00', '2026-10-01 17:06:00'),
(150, 'evt:e1f7c2980fea495e4a43e999:1790899564.15', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_dashboard.php', '0.00', '', 'GET /admin_dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 03:06:04', '2026-10-01 17:06:03'),
(151, 'evt:8708ab9043714630176b6f97:1790899583.66', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_settings.php', '0.00', '', 'GET /admin_settings.php', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 03:06:23', '2026-10-01 17:06:23'),
(152, 'evt:82668f57594108f2ce2319f5:1790899622.32', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_monitor.php', '0.00', '', 'GET /admin_monitor.php', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 03:07:02', '2026-10-01 17:07:02'),
(153, 'evt:067df71aa603a9cc885b8c37:1790899667.43', 'http_get', 'admin', 'admin', 18, 'GIBAL', 'http_request', 0, 'admin_dashboard.php', '0.00', '', 'GET /admin_dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 03:07:47', '2026-10-01 17:07:47'),
(154, 'evt:d850d4444ade7d220c8d161d:1790899676.72', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'deposit.php', '0.00', '', 'GET /deposit.php?status=success&msg=PayPal+order+created! | GET: {\"status\":\"success\",\"msg\":\"PayPal order created!\"}', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 03:07:56', '2026-10-01 17:07:56'),
(155, 'evt:7057c8cb55a76ed77b9477fc:1790900671.96', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'deposit.php', '0.00', '', 'GET /deposit.php?status=success&msg=PayPal+order+created!&i=1 | GET: {\"status\":\"success\",\"msg\":\"PayPal order created!\",\"i\":\"1\"}', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 03:24:31', '2026-10-01 17:24:31'),
(156, 'evt:29711e2c834f5396125cbeb3:1790900678.86', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 03:24:38', '2026-10-01 17:24:38'),
(157, 'evt:a3e0f76c4a071b089eb2d2f6:1790900681.33', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '142.250.32.98', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', 'http_request', 0, '2026-10-02 03:24:41', '2026-10-01 17:24:41'),
(158, 'evt:a8b426bbfc84414bbd7d8706:1790900681.61', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '142.250.32.98', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', 'http_request', 0, '2026-10-02 03:24:41', '2026-10-01 17:24:41'),
(159, 'evt:e9d827c690dc9036b08e9cc3:1790900681.65', 'http_get', 'users', 'guest', 0, 'Guest', 'http_request', 0, 'dashboard.php', '0.00', '', 'GET /dashboard.php', '142.250.32.99', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', 'http_request', 0, '2026-10-02 03:24:41', '2026-10-01 17:24:41'),
(160, 'evt:47efe524f50209628964616c:1790900684.53', 'http_get', 'finance', 'admin', 18, 'GIBAL', 'http_request', 0, 'deposit.php', '0.00', '', 'GET /deposit.php', '102.203.137.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'http_request', 0, '2026-10-02 03:24:44', '2026-10-01 17:24:44');

-- --------------------------------------------------------

--
-- Table structure for table `system_event_log_state`
--

CREATE TABLE `system_event_log_state` (
  `source_table` varchar(80) NOT NULL,
  `last_source_id` bigint(20) NOT NULL DEFAULT 0,
  `last_updated_at` datetime DEFAULT NULL,
  `last_synced_at` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_event_scan_state`
--

CREATE TABLE `system_event_scan_state` (
  `source_table` varchar(80) NOT NULL,
  `last_source_id` bigint(20) NOT NULL DEFAULT 0,
  `last_updated_at` datetime DEFAULT NULL,
  `last_synced_at` datetime DEFAULT NULL,
  `baseline_done` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_event_scan_state`
--

INSERT INTO `system_event_scan_state` (`source_table`, `last_source_id`, `last_updated_at`, `last_synced_at`, `baseline_done`) VALUES
('admins', 18, NULL, '2026-10-01 17:07:44', 1),
('admin_audit_log', 10, NULL, '2026-10-01 17:07:44', 1),
('admin_chats', 21, '2026-10-01 14:26:39', '2026-10-01 17:07:44', 1),
('broadcast_notifications', 2, NULL, '2026-10-01 17:07:44', 1),
('complaints', 3, '2026-10-01 14:41:12', '2026-10-01 17:07:44', 1),
('daily_fees', 10, '2026-10-01 14:38:36', '2026-10-01 17:07:44', 1),
('deposits', 1, NULL, '2026-10-01 17:07:44', 1),
('disputes', 5, NULL, '2026-10-01 17:07:44', 1),
('investments', 13, '2026-10-01 14:38:36', '2026-10-01 17:07:44', 1),
('password_change_requests', 2, NULL, '2026-10-01 17:07:44', 1),
('plans', 36, NULL, '2026-10-01 17:07:44', 1),
('system_status', 1, '2026-09-30 22:04:02', '2026-10-01 17:07:44', 1),
('ticket_followups', 52, '2026-10-01 14:41:10', '2026-10-01 17:07:44', 1),
('transactions', 13, '2026-10-02 00:38:36', '2026-10-01 17:07:44', 1),
('users', 106, '2026-10-01 14:34:21', '2026-10-01 17:07:44', 1),
('withdrawals', 9, '2026-10-01 14:25:17', '2026-10-01 17:07:44', 1);

-- --------------------------------------------------------

--
-- Table structure for table `system_status`
--

CREATE TABLE `system_status` (
  `id` int(11) NOT NULL,
  `mode` enum('operational','maintenance','paused') NOT NULL DEFAULT 'operational',
  `message` varchar(255) NOT NULL DEFAULT '',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notice` varchar(255) DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `system_status`
--

INSERT INTO `system_status` (`id`, `mode`, `message`, `updated_at`, `notice`) VALUES
(1, 'operational', 'We are performing a scheduled maintenance.', '2026-09-30 19:04:02', '');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_followups`
--

CREATE TABLE `ticket_followups` (
  `id` int(11) NOT NULL,
  `ticket_type` enum('complaint','dispute') NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ticket_followups`
--

INSERT INTO `ticket_followups` (`id`, `ticket_type`, `ticket_id`, `user_id`, `admin_id`, `message`, `created_at`) VALUES
(1, 'dispute', 1, 42, NULL, 'Still nothing', '2026-10-01 00:55:43'),
(2, 'dispute', 1, 42, NULL, 'Still nothing on my end', '2026-10-01 01:06:01'),
(3, 'dispute', 42, NULL, 18, 'Your all set now', '2026-10-01 01:19:07'),
(4, 'dispute', 1, 42, NULL, 'Heyyyy', '2026-10-01 01:21:41'),
(5, 'dispute', 42, NULL, 18, 'Hey', '2026-10-01 01:22:18'),
(6, 'dispute', 42, NULL, 18, 'confirm', '2026-10-01 01:30:45'),
(7, 'dispute', 42, NULL, 18, 'hh', '2026-10-01 01:37:44'),
(8, 'dispute', 42, NULL, 18, 'ggggg', '2026-10-01 02:38:30'),
(9, 'dispute', 42, NULL, 18, 'fvabfbnmn', '2026-10-01 03:08:31'),
(10, 'dispute', 42, NULL, 18, ',kmkn', '2026-10-01 03:27:03'),
(11, 'complaint', 1, NULL, 18, 'let me work on it', '2026-10-01 03:39:35'),
(12, 'dispute', 1, NULL, 18, 'on it', '2026-10-01 03:39:54'),
(13, 'complaint', 42, NULL, 18, 'were sorry abt that', '2026-10-01 03:41:17'),
(14, 'dispute', 1, NULL, 18, 'on it', '2026-10-01 03:56:20'),
(15, 'complaint', 42, NULL, 18, 'okay', '2026-10-01 03:56:51'),
(16, 'dispute', 1, NULL, 18, 'on it', '2026-10-01 03:57:26'),
(17, 'dispute', 1, NULL, 18, 'Were done', '2026-10-01 03:57:45'),
(18, 'complaint', 42, NULL, 18, 'done', '2026-10-01 04:08:49'),
(19, 'complaint', 42, NULL, 18, 'b', '2026-10-01 08:47:01'),
(20, 'dispute', 42, NULL, 18, 'n', '2026-10-01 08:49:55'),
(21, 'dispute', 42, NULL, 18, 'okay', '2026-10-01 08:53:30'),
(22, 'dispute', 2, 42, NULL, 'still', '2026-10-01 08:54:20'),
(23, 'dispute', 42, NULL, 18, 'hold', '2026-10-01 08:54:50'),
(24, 'dispute', 42, NULL, 18, 'hhhhh', '2026-10-01 09:28:21'),
(25, 'dispute', 2, 42, NULL, 'hhhhhhhhhhhhgagsvlcihguiadc\'ins\'vinadfpiuvn;ion ;afks vjmfovrkl ;wijn;fioval jk\r\n klncfukahe ljne\r\n.jfmlv\r\n d,ovjergio jw', '2026-10-01 09:30:01'),
(26, 'dispute', 42, NULL, 18, 'ok ok', '2026-10-01 09:30:22'),
(27, 'dispute', 42, NULL, 18, 'hhh', '2026-10-01 09:38:17'),
(28, 'dispute', 42, NULL, 18, 'hhvhv', '2026-10-01 09:38:30'),
(29, 'dispute', 2, 42, NULL, 'hhhhhhhhhhhhgagsvlcihguiadc\'ins\'vinadfpiuvn;ion ;afks vjmfovrkl ;wijn;fioval jk\r\n klncfukahe ljne\r\n.jfmlv\r\n d,ovjergio jw', '2026-10-01 09:38:58'),
(30, 'dispute', 42, NULL, 18, 'kljhvuvkuy', '2026-10-01 09:39:15'),
(31, 'dispute', 42, NULL, 18, 'cool', '2026-10-01 09:52:22'),
(32, 'dispute', 2, 42, NULL, 'hhhhhhhhhhhhgagsvlcihguiadc\'ins\'vinadfpiuvn;ion ;afks vjmfovrkl ;wijn;fioval jk\r\n klncfukahe ljne\r\n.jfmlv\r\n d,ovjergio jw', '2026-10-01 09:52:50'),
(33, 'dispute', 2, NULL, 18, 'ok', '2026-10-01 10:33:53'),
(34, 'dispute', 1, NULL, 18, 'cool', '2026-10-01 10:35:28'),
(35, 'dispute', 1, 42, NULL, 'thanks', '2026-10-01 10:35:51'),
(36, 'dispute', 1, 42, NULL, 'thanks', '2026-10-01 10:36:22'),
(37, 'dispute', 2, NULL, 18, 'how is it now', '2026-10-01 10:37:42'),
(38, 'dispute', 2, 42, NULL, 'done', '2026-10-01 10:38:00'),
(39, 'dispute', 3, NULL, 18, 'whats thee issue', '2026-10-01 10:41:36'),
(40, 'dispute', 3, 42, NULL, 'hazifiki?', '2026-10-01 10:41:53'),
(41, 'complaint', 1, 42, NULL, 'hey', '2026-10-01 10:49:27'),
(42, 'complaint', 1, NULL, 18, 'what now', '2026-10-01 10:49:49'),
(43, 'complaint', 1, 42, NULL, 'ok cool', '2026-10-01 10:50:09'),
(44, 'complaint', 1, 42, NULL, 'wozaaaa', '2026-10-01 11:00:45'),
(45, 'complaint', 1, NULL, 18, 'rada', '2026-10-01 11:01:02'),
(46, 'complaint', 2, 42, NULL, 'huh', '2026-10-01 11:06:23'),
(47, 'complaint', 2, NULL, 18, 'much appreciated', '2026-10-01 11:06:55'),
(48, 'dispute', 4, NULL, 18, 'okay lemme check it out', '2026-10-01 11:19:36'),
(49, 'dispute', 4, 44, NULL, 'Sawa sawa', '2026-10-01 11:19:48'),
(50, 'dispute', 4, 44, NULL, 'Asante', '2026-10-01 11:19:54'),
(51, 'dispute', 4, NULL, 18, 'Done', '2026-10-01 11:20:11'),
(52, 'complaint', 3, NULL, 18, 'Most welcom', '2026-10-01 14:41:10');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `type` varchar(10) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'PayPal',
  `paypal_txn_id` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'PENDING',
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  `amount_kes` decimal(15,2) NOT NULL DEFAULT 0.00,
  `deposit_method` varchar(50) NOT NULL DEFAULT 'btcpay'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `transaction_id`, `type`, `amount`, `description`, `payment_method`, `paypal_txn_id`, `status`, `date`, `created_at`, `amount_kes`, `deposit_method`) VALUES
(4, 42, NULL, 'debit', '1196.60', 'Invested Ksh 1,196.60 in plan Silver', 'PayPal', NULL, 'PENDING', '2026-09-29 18:34:49', '2026-09-30 04:34:48', '0.00', 'btcpay'),
(5, 42, NULL, 'debit', '2895.00', 'Invested Ksh 2,895.00 in plan Silver', 'PayPal', NULL, 'PENDING', '2026-09-30 10:55:59', '2026-09-30 20:55:59', '0.00', 'btcpay'),
(6, 44, NULL, 'debit', '3377.50', 'Invested Ksh 3,377.50 in plan Silver', 'PayPal', NULL, 'PENDING', '2026-09-30 15:54:36', '2026-10-01 01:54:36', '0.00', 'btcpay'),
(7, 44, NULL, 'debit', '8202.50', 'Invested Ksh 8,202.50 in plan Gold', 'PayPal', NULL, 'PENDING', '2026-09-30 15:55:21', '2026-10-01 01:55:21', '0.00', 'btcpay'),
(8, 42, NULL, 'debit', '4825.00', 'Invested Ksh 4,825.00 in plan Gold', 'PayPal', NULL, 'PENDING', '2026-09-30 16:14:05', '2026-10-01 02:14:05', '0.00', 'btcpay'),
(9, 42, NULL, 'debit', '11580.00', 'Invested Ksh 11,580.00 in plan Lotus', 'PayPal', NULL, 'PENDING', '2026-10-01 11:12:28', '2026-10-01 21:12:28', '0.00', 'btcpay'),
(10, 44, NULL, 'debit', '3377.50', 'Invested Ksh 3,377.50 in plan Silver', 'PayPal', NULL, 'PENDING', '2026-10-01 11:32:46', '2026-10-01 21:32:45', '0.00', 'btcpay'),
(11, 42, NULL, 'debit', '3377.50', 'Invested Ksh 3,377.50 in plan Silver', 'PayPal', NULL, 'PENDING', '2026-10-01 13:24:43', '2026-10-01 23:24:43', '0.00', 'btcpay'),
(12, 42, NULL, 'debit', '3377.50', 'Invested Ksh 3,377.50 in plan Silver', 'PayPal', NULL, 'PENDING', '2026-10-01 14:21:05', '2026-10-02 00:21:05', '0.00', 'btcpay'),
(13, 106, NULL, 'debit', '2412.50', 'Invested Ksh 2,412.50 in plan Silver', 'PayPal', NULL, 'PENDING', '2026-10-01 14:38:36', '2026-10-02 00:38:36', '0.00', 'btcpay');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `account_balance` decimal(15,2) DEFAULT 0.00,
  `pending_withdrawals` decimal(15,2) DEFAULT 0.00,
  `invested_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `fullname` varchar(255) NOT NULL DEFAULT '',
  `referrer_id` int(11) DEFAULT NULL,
  `referral_code` varchar(20) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `total_expected_interest` decimal(15,2) NOT NULL DEFAULT 0.00,
  `referral_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `referral_wallet` decimal(15,2) NOT NULL DEFAULT 0.00,
  `referred_by` int(11) DEFAULT NULL,
  `ref_code` varchar(50) DEFAULT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `accepted_terms_at` datetime DEFAULT NULL,
  `accepted_terms_ip` varchar(45) DEFAULT NULL,
  `accepted_terms_user_agent` varchar(255) DEFAULT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `kyc_status` enum('Not Submitted','Pending','Approved','Rejected') DEFAULT 'Not Submitted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `email`, `phone`, `password`, `account_balance`, `pending_withdrawals`, `invested_balance`, `created_at`, `fullname`, `referrer_id`, `referral_code`, `admin_id`, `total_expected_interest`, `referral_balance`, `referral_wallet`, `referred_by`, `ref_code`, `balance`, `accepted_terms_at`, `accepted_terms_ip`, `accepted_terms_user_agent`, `role`, `kyc_status`) VALUES
(42, 'Eric Mbondo', 'Eric', 'bondoeric11n@gmail.com', '254795520828', '$2y$10$bFsmrdU2h8faS8yrXR3bzu9xwPHpgi7G3sEcDWeemoejNeHesB/Jq', '29.50', '0.00', '23160.00', '2025-10-14 06:11:17', '', NULL, 'FEF287C1', NULL, '8588.51', '0.00', '224.36', NULL, NULL, '0.00', NULL, NULL, NULL, 'user', 'Not Submitted'),
(44, 'Leah ndolo', 'Leah5566', 'leahndolo@gmail.com', '+254722777687', '$2y$10$iwMi8MzzZJejZ4fy6egtmONvhY5n6SEeUDh9JcyIHFIkLsSkAvbOW', '4841.88', '0.00', '11580.00', '2025-10-14 08:02:29', '', NULL, 'B6E95A03', NULL, '3715.26', '0.00', '0.00', 42, NULL, '0.00', NULL, NULL, NULL, 'user', 'Not Submitted'),
(48, 'Brian  ngelechei', 'Briz', 'ngelecheibrian89@gmail.com', '+254759177151', '$2y$10$6TgF5txDjYg6v6Mr3ZTILO.QrmSI/HbP4uZGDIcLKXex4YK1pAemW', '0.00', '0.00', '0.00', '2025-10-15 10:09:25', '', NULL, NULL, NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', NULL, NULL, NULL, 'user', 'Not Submitted'),
(51, 'Milton Karim', 'mkmk452', 'miltonkarim20@gmail.com', '+254702803499', '$2y$10$Z58wWmE9KNHmY8gdDyvu7eTLZ5qEZw8h3RPh9GFR2W3zQL69jZZcW', '0.00', '0.00', '0.00', '2025-10-20 08:35:42', '', NULL, 'CE71ADE4', NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', NULL, NULL, NULL, 'user', 'Not Submitted'),
(53, 'Sharon Njoki', 'Watiri', 'sharonwatiri@gmail.com', '+254115640113', '$2y$10$BdeTg/Mi5kK9.ljh3MML0eCuuDFrG4R9dsVs14MYr0HMrKSyz3Zam', '0.00', '0.00', '0.00', '2025-10-20 10:22:31', '', NULL, '29CFCF8C', NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', NULL, NULL, NULL, 'user', 'Not Submitted'),
(54, 'Sheldon kipkosgei', 'Shellfx', 'sheldonkipss@gmail.com', '+254707734684', '$2y$10$6uBpjrh4QUlv1xg05CPwYuwgSD3q5MGRr69T4EKbYlo6dqj4xMd9i', '0.00', '0.00', '0.00', '2025-10-25 03:08:15', '', NULL, 'E5EA1524', NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', NULL, NULL, NULL, 'user', 'Not Submitted'),
(55, 'Damaris Akulwa', 'Akulwa ', 'akulwadamaris420@gmail.com', '+254742367022', '$2y$10$0Fr1lq6qLh9rS/Y7BS2keO.HMesowaJTj1WPMedN7gicJumfLgGFO', '0.00', '0.00', '0.00', '2025-10-25 05:04:57', '', NULL, '50E89D8F', NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', NULL, NULL, NULL, 'user', 'Not Submitted'),
(57, 'Laon Kennedy ', 'Vidollar ', 'laonkennedy01@gmail.com', '+254796395614', '$2y$10$0xAJxnymmojavRY6g66gJ.oAlbJ432tyr1L9Pjtck07f8KW9p/A.y', '0.00', '0.00', '0.00', '2025-10-27 12:00:17', '', NULL, 'C02C70D9', NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', NULL, NULL, NULL, 'user', 'Not Submitted'),
(58, 'Sanirei Kelvin Rikoinet ', 'Kdpjnr', 'kelvinrikoinet@gmail.com', '+254113866584', '$2y$10$YGB97H0m9KcJLQX21mgjEOwiUsXSJ3bF4AJA42ZwgyjZf6Wi5MO9S', '0.00', '0.00', '0.00', '2025-10-28 10:08:32', '', NULL, 'C14BDFFA', NULL, '0.00', '0.00', '0.00', 57, NULL, '0.00', NULL, NULL, NULL, 'user', 'Not Submitted'),
(59, 'Moses ambundo ', 'nadhhian', 'ambundo35@gmail.com', '0713990211', '$2y$10$WuMKjgJ.YLaTa2Jl/n9n8eIjTgEPi/AO/kz1i42bDwDVEfln81LKa', '0.00', '0.00', '0.00', '2025-10-29 09:43:00', '', NULL, 'F0194EBC', NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', NULL, NULL, NULL, 'user', 'Not Submitted'),
(60, 'Winfred', 'Nzilani makenzi', 'winfrednzilani12@gmail.com', '0726090154', '$2y$10$cpElIqYuYHjO8ffZBEidfONIsWV.RoxklnvFvykNNlhKyqQGysowK', '0.00', '0.00', '0.00', '2025-10-30 08:52:10', '', NULL, '3D94C4DD', NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', NULL, NULL, NULL, 'user', 'Not Submitted'),
(61, 'Diana Rose Mwazighe', 'Zighedee', 'zighediana503@gmail.com', '+254746044258', '$2y$10$7l6/vI.Pq5jZLC/zWwA64OF/3iiVYtZPWYA.2VBjwUjhfhzvXfWIu', '0.00', '0.00', '0.00', '2025-10-31 06:43:15', '', NULL, '90835F2C', NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', NULL, NULL, NULL, 'user', 'Not Submitted'),
(62, 'Anita Ndanu ', 'Anitamuindi@2025', 'anitamuindi59@gmail.com', '+254703583263', '$2y$10$oMATW6o5I2vYgeeVrOFvUOE4Ll33XT2iHqt7YjaHfo4QARTDjK5Du', '0.00', '0.00', '0.00', '2025-10-31 10:52:55', '', NULL, '9356B450', NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', NULL, NULL, NULL, 'user', 'Not Submitted'),
(63, 'DESMOND EDDY OTIENO ', 'DESMOND96', 'desmondeddy96@gmail.com', '+254719212346', '$2y$10$2F.Kc6KwV0CzAd9i5ewxuuKXLIitRmnEvTwr/U6GVY7uSqakS7ioe', '0.00', '0.00', '0.00', '2025-11-01 07:07:23', '', NULL, 'B91443AA', NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', NULL, NULL, NULL, 'user', 'Not Submitted'),
(64, 'cheryot Kipling ', 'kipli.. ', 'cheruiyotkiprotich983@gmail.com', '0743787096', '$2y$10$uiZtS3ATORfieD21EE6RA.7VASfKH9EQoAr0symYfvkgby9nZADYq', '0.00', '0.00', '0.00', '2025-11-02 19:24:06', '', NULL, 'D3826FC5', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', NULL, NULL, NULL, 'user', 'Not Submitted'),
(65, 'Jay melody', 'Jay', 'alvinliam69@gmail.com', '+254758295670', '$2y$10$fcB9SjBdPc5jTSIfrx51B.giynggewHMncaZqekUAaO2uPaGp/9vG', '0.00', '0.00', '0.00', '2025-11-02 20:39:54', '', NULL, '14293762', NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', NULL, NULL, NULL, 'user', 'Not Submitted'),
(66, 'Kevin Jackson ', 'OBARE ', 'jacksonkevin030@gmail.com', '+254795386942', '$2y$10$SePMvWdkO8HCWmAM8nOzcO2Jk4OwQNyNi8EaW58kmpBTdKIbC5Z7u', '0.00', '0.00', '0.00', '2025-11-05 08:59:03', '', NULL, '50C98969', NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', NULL, NULL, NULL, 'user', 'Not Submitted'),
(74, 'Haron rary', 'Rary', 'raryharon@gmail.com', '0111824395', '$2y$10$fYUKzQY7aALAHE5VWNWzpuNqSYiQ1cnwgEPqFDDAldJi1v6gM9qJK', '0.00', '0.00', '0.00', '2025-11-09 01:20:08', '', NULL, '28008B7A', NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', '2025-11-09 12:20:08', '102.210.25.158', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(75, 'Black Omwami', 'BOmwami', 'quickfixke@gmail.com', '+254718426952', '$2y$10$Txk6m9zeq5ZErTRA/tnHYeN2RHyWXNkYmeFurfvjfS7vJ8IcFbxh6', '0.00', '0.00', '0.00', '2025-11-09 01:55:14', '', NULL, 'DE328A53', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2025-11-09 12:55:14', '102.0.16.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'user', 'Not Submitted'),
(76, 'GIBAL KHALIBSON', 'GIBAL', 'gibalkhalibson@gmail.com', '+254795520828', '$2y$10$w9akiSEZbtSmCMXd9Z9hUuLgoslpvJjdeIeIqkXNMv2fk99Vtnzfu', '0.00', '0.00', '0.00', '2025-11-13 01:23:17', '', NULL, 'B7E25F4F', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2025-11-13 12:23:17', '41.90.178.228', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'user', 'Not Submitted'),
(77, 'Derrick Ochieng', 'Manadecko', 'Manadecko@gmail.com', '0701967017', '$2y$10$k7Aec3giGuO4OdNFo8CV..Oi7Z2Ky7JU1YSRRNtq1pZC.UwQeFRU2', '0.00', '0.00', '0.00', '2025-11-17 01:34:02', '', NULL, '7D064D7F', NULL, '0.00', '0.00', '0.00', 65, NULL, '0.00', '2025-11-17 12:34:02', '102.216.85.27', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(78, 'Kelvin', '_blicky.s', 'kkingori420@gmail.com', '+254795504220', '$2y$10$OHFEykcBWSuVhZfUAviB0OfJW5zI92282oNLGo.Djybr7yNT.msAy', '0.00', '0.00', '0.00', '2025-11-17 02:13:56', '', NULL, '55236AEB', NULL, '0.00', '0.00', '0.00', 65, NULL, '0.00', '2025-11-17 13:13:56', '102.216.85.27', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(79, 'Charles Saidim', 'tsbac', 'tsbacsaidim@gmail.com', '0728049673', '$2y$10$GOksjd0LFDfsBLYhiURBre9.zjNOOrGW8x.Bi6/RcKlwjGlyLLJnK', '0.00', '0.00', '0.00', '2025-11-17 02:15:50', '', NULL, 'F1FC5FE9', NULL, '0.00', '0.00', '0.00', 65, NULL, '0.00', '2025-11-17 13:15:50', '102.216.85.27', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(80, 'Kelvin', 'Blicky', 'kkingori849@gmail.com', '+254756756818', '$2y$10$PDv69IdGzGxw0c8NDv.FGupfRMCPQ3JZNmWdIjRkh/hcg8H0hUwce', '0.00', '0.00', '0.00', '2025-11-17 02:19:31', '', NULL, 'E127C8C9', NULL, '0.00', '0.00', '0.00', 65, NULL, '0.00', '2025-11-17 13:19:31', '102.216.85.27', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(81, 'Teddy', 'hubristeddy', 'teddyndeto@gmail.com', '0711236072', '$2y$10$pFADiiJgvsRSLVFaRNMHq./h3X5X7nbvBMfPV3jZU8X76x5/i248.', '0.00', '0.00', '0.00', '2025-11-17 02:31:43', '', NULL, '412C8CC1', NULL, '0.00', '0.00', '0.00', 65, NULL, '0.00', '2025-11-17 13:31:43', '41.90.187.150', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(82, 'Margaret Wanjiru Muturi', 'Wincie', 'shirumathenge88@gmail.com', '+254792647050', '$2y$10$UcUMgadf1k11rxxj1DcUH.h84pFFLkDu.YxcIra.Svg2GpPP9CDvu', '0.00', '0.00', '0.00', '2025-11-17 04:40:31', '', NULL, '76E13C49', NULL, '0.00', '0.00', '0.00', 53, NULL, '0.00', '2025-11-17 15:40:31', '188.51.216.240', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(83, 'Collins Otieno', 'Collins', 'lil.toxicspike1738@gmail.com', '0799198762', '$2y$10$zfo8LW7G8pbfz8B2gVbumOaME0YWOAHK8xmcGw.rJRaCC1d4AsU5K', '0.00', '0.00', '0.00', '2025-11-17 04:40:34', '', NULL, '71DB51A8', NULL, '0.00', '0.00', '0.00', 65, NULL, '0.00', '2025-11-17 15:40:34', '102.0.14.18', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_7_12 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6.1 Mobile/15E148 Safari/604.1', 'user', 'Not Submitted'),
(84, 'Emmanuel', 'Manu', 'emmanueltashan39@gmail.com', '+254750671926', '$2y$10$5d8fIGx0mVEAXm9/3RT3newOD7Q43kFx//d3YidFIOx8fP9ur/USu', '0.00', '0.00', '0.00', '2025-11-17 13:36:32', '', NULL, 'D2E1AC01', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2025-11-18 00:36:32', '196.250.215.153', 'Mozilla/5.0 (Linux; U; Android 11; en-us; Infinix X6511G Build/RP1A.200720.011) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.85 Mobile Safari/537.36 PHX/19.8', 'user', 'Not Submitted'),
(85, 'Dick oburu bogonko', 'Kaizen', 'bogonkodick@gmail.com', '0790886443', '$2y$10$ftEzIfHos2nzoOcAOZLw..8Uf5O3Cyr7AfMcK666adCpskoIKgTVu', '0.00', '0.00', '0.00', '2025-11-18 12:47:51', '', NULL, 'FADAF874', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2025-11-18 23:47:51', '105.161.154.96', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(86, 'DAVID', 'MUSAU', 'geckohunter7@gmail.com', '0705811134', '$2y$10$Dzqpkwym8igYma/tnFM4ceNoKlnfWxcf7UeQxSEeLBuGGnAHE.e7W', '0.00', '0.00', '0.00', '2025-11-21 05:38:56', '', NULL, '8DF6C409', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2025-11-21 16:38:56', '41.90.187.98', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'user', 'Not Submitted'),
(87, 'Carolyne Muthoni', 'Carole', 'caroleck31@gmail.com', '0720597136', '$2y$10$XwJgknedcGM2R3n4qmarz.XQWw17mAKFb34hbRQwsGFks3qKvi9..', '0.00', '0.00', '0.00', '2025-12-05 06:13:44', '', NULL, '81E0AF8B', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2025-12-05 17:13:44', '196.96.208.81', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_7_12 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6.1 Mobile/15E148 Safari/604.1', 'user', 'Not Submitted'),
(88, 'Esther Awino', 'Asta', 'leravke24@gmail.com', '+254743958424', '$2y$10$NCUW16dQvK7IKIRl1pXsYOh7mYUcS/8vPqMQYxxDFbxfGeu/cBj/S', '0.00', '0.00', '0.00', '2025-12-15 08:15:54', '', NULL, '3D2F6634', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2025-12-15 19:15:54', '41.90.211.238', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(89, 'John njuguna', 'Jaynet001', 'njugunajohn161@gmail.com', '0702212559', '$2y$10$oQvERIZIo555uxdmJhU.3uR2yH7.q5u7m0ctbvoWl2VZY8ceHWiOG', '0.00', '0.00', '0.00', '2025-12-17 07:07:15', '', NULL, '31B6E08F', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2025-12-17 18:07:16', '41.90.177.185', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(90, 'Karisa', 'Hamisi', 'karisahamisi801@gmail.com', '0712409964', '$2y$10$N/bob2rYxhMw.8YiewQPSO5PuFe73frxERmWKIDA4u9WCPFH6S8gS', '0.00', '0.00', '0.00', '2025-12-20 01:38:54', '', NULL, '240E9CA9', NULL, '0.00', '0.00', '0.00', 44, NULL, '0.00', '2025-12-20 12:38:54', '196.207.185.142', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(91, 'Ruth Walowe Tabani', 'Tabani', 'ruthtabani18@gmail.com', '+254796734787', '$2y$10$CFcMQH.nvpz4S97lbtrSzeLZ7nmC2BE1yGPZDl5ikRoHQOGa0BH72', '0.00', '0.00', '0.00', '2026-02-04 05:04:54', '', NULL, '0A533C20', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2026-02-04 16:04:54', '129.222.147.71', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(92, 'Nyawira Muthoni', 'Ibhar.m', 'muthonimaina860@gmail.com', '+254748763421', '$2y$10$eZpyvxUUCGQ.CG6JLybhheGuKRl4AiuMvOeDTMOUH4lvGZ5sh3vb.', '0.00', '0.00', '0.00', '2026-02-14 10:12:50', '', NULL, 'E4486816', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2026-02-14 21:12:50', '105.160.115.82', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(93, 'David Mutisya', 'DM', 'Loyalty.isp.254@gmail.com', '0791382115', '$2y$10$BFNLinHba4gcJnPiwb5UqeLDN8rZJuPjDjIHztu2f3JHslv7qma2W', '0.00', '0.00', '0.00', '2026-02-19 03:55:35', '', NULL, '6CC6C1CD', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2026-02-19 14:55:35', '129.222.147.12', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36', 'user', 'Not Submitted'),
(94, 'Tiku', 'Scarface', 'tikukelvin88@gmail.com', '0115372350', '$2y$10$X.iI1FVxdajifMb5j/MiruCOk4DdD1VvlVCHuikJoki9VSbWrt9Ee', '0.00', '0.00', '0.00', '2026-05-15 00:59:49', '', NULL, 'CE436A05', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2026-05-15 10:59:49', '154.159.252.243', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(95, 'Martin Kuria', 'HomeZ', 'martinkuria567@gmail.com', '0140729280', '$2y$10$kbU.UtMrEadWO/CsGxgZrOwobH.e1Rs8BeX4bw1WEWR6s.hTONArC', '0.00', '0.00', '0.00', '2026-05-15 02:01:21', '', NULL, 'CDC1E127', NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', '2026-05-15 12:01:21', '154.159.252.243', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(96, 'Elisha Barasa', 'Elisha', 'barasaelisha48@gmail.com', '0748115897', '$2y$10$cznXMOD90FS3UCXRa1IC9OHNxFVwPWyc0sE1De.GCVIaYacCE.m6C', '0.00', '0.00', '0.00', '2026-05-29 21:43:12', '', NULL, '6990DCE1', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2026-05-30 07:43:11', '102.213.92.14', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(97, 'Elisha Barasa', 'Elisha', 'barasaelisha488@gmail.com', '+254748115897', '$2y$10$yDPaclf5Q.3PiGwD3OCAz.uiGXY6PyuV2fAHFiDRDfwGnXEUrYLBm', '0.00', '0.00', '0.00', '2026-06-22 07:19:08', '', NULL, '66F86B0A', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2026-06-22 17:19:08', '102.213.92.14', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(98, 'Tina Kipas', '@Twinkie', 'kipastina6461@gmail.com', '0741923995', '$2y$10$DaLAOocJ8F3BGadk0A1CouSrthz1w61IoTbLAODzYRZ1bz1x1SC/.', '0.00', '0.00', '0.00', '2026-09-06 13:50:40', '', NULL, '58B1B5EE', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2026-09-06 23:50:40', '105.164.5.72', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(99, 'Macbrian', 'Breezy', 'Briannyangosi@gmail.com', '0743616093', '$2y$10$ODynz3k2DtVYkKZkmgCIFerNsP2St/VF6fljKiKgSg5ZFmZQ/vXK.', '0.00', '0.00', '0.00', '2026-09-15 11:48:11', '', NULL, '8BC5ADE8', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2026-09-15 21:48:12', '105.164.128.16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'user', 'Not Submitted'),
(100, 'Leon', 'Stypid genius', 'stypid05@gmail.com', '+254733542033', '$2y$10$dgR2yc4Lnw1kxtnleJ1jV.HOQA06tKhFUCU6Z6CfqEBP/AJ4Jh/G6', '0.00', '0.00', '0.00', '2026-09-15 12:00:14', '', NULL, '790766E7', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2026-09-15 22:00:15', '154.159.252.145', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'user', 'Not Submitted'),
(101, 'SAMANTHA AYOMA', 'SIANZWI', 'samanthaayoma80@gmail.com', '+254745925576', '$2y$10$QLS2b4UUdLGWwCBZQ0MZheLp7B63YFDf3v2J2FNNYJeqFDYMWBF.e', '0.00', '0.00', '0.00', '2026-09-15 12:24:29', '', NULL, '23A57750', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2026-09-15 22:24:29', '197.186.39.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(102, 'HOPE SONKOI', 'Hope', 'hopesonkoi@gmail.com', '0706820372', '$2y$12$ScXj0roKKQ5SX43IScz5wuCj6Hd0dXls5vXN1SURM7zwwW0i/.IcK', '0.00', '0.00', '0.00', '2026-09-29 11:47:29', '', NULL, 'E596A263', NULL, '0.00', '0.00', '0.00', NULL, NULL, '0.00', '2026-09-29 21:47:28', '196.202.187.177', 'Mozilla/5.0 (Linux; U; Android 12; en-ie; CPH2471 Build/SP1A.210812.016) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.5970.168 Mobile Safari/537.36 HeyTapBrowser/45.14.8.1', 'user', 'Not Submitted'),
(103, 'Kelvin', 'Munene', 'muneenek@gmail.com', '0705644194', '$2y$12$GTSCqszXpL2mJ7GpN93lUOtDOlJRJBGIj/qIOKx/RO6Z4APhlDGAu', '0.00', '0.00', '0.00', '2026-09-30 08:35:57', '', NULL, 'F65B4DC0', NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', '2026-09-30 18:35:57', '197.248.54.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(104, 'Kelvin', 'Munene', 'kelvinmuneene@gmail.com', '0705644194', '$2y$12$2KRnOKZZfmeTySRlinBWF.uFQ9TJZAVV7OpO79W5KYoz6RR/gvI5u', '0.00', '0.00', '0.00', '2026-09-30 08:36:48', '', NULL, 'EBB7068B', NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', '2026-09-30 18:36:48', '197.248.54.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted'),
(105, 'Brian Ngelechei', 'Briz', 'brianngelechei01@gmail.com', '0701776332', '$2y$12$7GZKQsDXxXz5kIxbIfWuNOXitMnH2Ce3V.p/Uch3mFTf.lqYH2FeS', '0.00', '0.00', '0.00', '2026-10-01 11:45:34', '', NULL, '648F449F', NULL, '0.00', '0.00', '0.00', 42, NULL, '0.00', '2026-10-01 21:45:33', '154.159.237.161', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', 'user', 'Not Submitted');
INSERT INTO `users` (`id`, `name`, `username`, `email`, `phone`, `password`, `account_balance`, `pending_withdrawals`, `invested_balance`, `created_at`, `fullname`, `referrer_id`, `referral_code`, `admin_id`, `total_expected_interest`, `referral_balance`, `referral_wallet`, `referred_by`, `ref_code`, `balance`, `accepted_terms_at`, `accepted_terms_ip`, `accepted_terms_user_agent`, `role`, `kyc_status`) VALUES
(106, 'Awino Richard', 'Awino', 'awinorich@gmail.com', '+254795520827', '$2y$12$5t2FI.WHVLQWzDzu57yaf.1SDMy45/yyAazqHwMdmrup34n39WtIi', '0.00', '0.00', '2412.50', '2026-10-01 14:34:21', '', NULL, 'D5EF7AEE', NULL, '603.13', '0.00', '0.00', NULL, NULL, '0.00', '2026-10-02 00:34:21', '102.203.137.244', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'user', 'Not Submitted');

-- --------------------------------------------------------

--
-- Table structure for table `user_chats`
--

CREATE TABLE `user_chats` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `message` text NOT NULL,
  `sender` enum('user','admin') NOT NULL,
  `status` enum('active','ended','archived') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_notification_reads`
--

CREATE TABLE `user_notification_reads` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `dismissed_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `user_notification_reads`
--

INSERT INTO `user_notification_reads` (`id`, `user_id`, `notification_id`, `dismissed_at`) VALUES
(1, 42, 1, '2026-09-30 13:17:14'),
(2, 42, 2, '2026-09-30 13:27:28'),
(3, 44, 2, '2026-09-30 15:51:22'),
(4, 44, 1, '2026-09-30 15:51:26'),
(5, 106, 2, '2026-10-01 14:39:45'),
(6, 106, 1, '2026-10-01 15:12:05');

-- --------------------------------------------------------

--
-- Table structure for table `user_packages`
--

CREATE TABLE `user_packages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `active` tinyint(1) DEFAULT 1
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `balance_at_request` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `withdrawals`
--

INSERT INTO `withdrawals` (`id`, `user_id`, `amount`, `status`, `processed_at`, `created_at`, `balance_at_request`) VALUES
(1, 42, '1340.00', 'approved', '2026-09-30 10:54:53', '2026-09-30 10:53:42', '0.00'),
(2, 42, '1080.00', 'approved', '2026-09-30 12:31:06', '2026-09-30 12:01:42', '0.00'),
(3, 44, '150.00', 'approved', '2026-09-30 15:56:23', '2026-09-30 15:55:43', '0.00'),
(4, 42, '2400.00', 'approved', '2026-09-30 16:17:21', '2026-09-30 16:16:56', '0.00'),
(5, 44, '750.00', 'rejected', '2026-10-01 11:34:38', '2026-10-01 11:34:09', '0.00'),
(6, 44, '750.00', 'approved', '2026-10-01 12:25:49', '2026-10-01 11:34:53', '0.00'),
(7, 42, '1000.00', '', '2026-10-01 12:26:11', '2026-10-01 12:09:32', '0.00'),
(8, 42, '1500.00', 'approved', '2026-10-01 14:24:14', '2026-10-01 13:56:26', '0.00'),
(9, 42, '500.00', 'approved', '2026-10-01 14:25:17', '2026-10-01 14:24:53', '0.00');

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals_requested`
--

CREATE TABLE `withdrawals_requested` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `request_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_history`
--
ALTER TABLE `account_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_chats`
--
ALTER TABLE `admin_chats`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `admin_wallet`
--
ALTER TABLE `admin_wallet`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_wallet_log`
--
ALTER TABLE `admin_wallet_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `balance_logs`
--
ALTER TABLE `balance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `broadcast_notifications`
--
ALTER TABLE `broadcast_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `btc_deposits`
--
ALTER TABLE `btc_deposits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `daily_fees`
--
ALTER TABLE `daily_fees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `deposits`
--
ALTER TABLE `deposits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `disputes`
--
ALTER TABLE `disputes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `investments`
--
ALTER TABLE `investments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indexes for table `investment_events`
--
ALTER TABLE `investment_events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_change_requests`
--
ALTER TABLE `password_change_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `paypal_deposits`
--
ALTER TABLE `paypal_deposits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `referrer_id` (`referrer_id`);

--
-- Indexes for table `referral_bonus`
--
ALTER TABLE `referral_bonus`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referral_bonus_log`
--
ALTER TABLE `referral_bonus_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `referrer_id` (`referrer_id`),
  ADD KEY `referred_id` (`referred_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

--
-- Indexes for table `system_event_log`
--
ALTER TABLE `system_event_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_uid` (`event_uid`) USING HASH,
  ADD KEY `idx_occurred` (`occurred_at`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_actor` (`actor_type`,`actor_id`),
  ADD KEY `idx_source` (`source_table`,`source_id`);

--
-- Indexes for table `system_event_log_state`
--
ALTER TABLE `system_event_log_state`
  ADD PRIMARY KEY (`source_table`);

--
-- Indexes for table `system_event_scan_state`
--
ALTER TABLE `system_event_scan_state`
  ADD PRIMARY KEY (`source_table`);

--
-- Indexes for table `system_status`
--
ALTER TABLE `system_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ticket_followups`
--
ALTER TABLE `ticket_followups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_lookup` (`ticket_type`,`ticket_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `referral_code` (`referral_code`),
  ADD KEY `fk_admin` (`admin_id`),
  ADD KEY `accepted_terms_at` (`accepted_terms_at`);

--
-- Indexes for table `user_chats`
--
ALTER TABLE `user_chats`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_notification_reads`
--
ALTER TABLE `user_notification_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_notif` (`user_id`,`notification_id`);

--
-- Indexes for table `user_packages`
--
ALTER TABLE `user_packages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `package_id` (`package_id`);

--
-- Indexes for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `withdrawals_requested`
--
ALTER TABLE `withdrawals_requested`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_history`
--
ALTER TABLE `account_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `admin_chats`
--
ALTER TABLE `admin_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=778;

--
-- AUTO_INCREMENT for table `admin_wallet`
--
ALTER TABLE `admin_wallet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_wallet_log`
--
ALTER TABLE `admin_wallet_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `balance_logs`
--
ALTER TABLE `balance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `broadcast_notifications`
--
ALTER TABLE `broadcast_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `btc_deposits`
--
ALTER TABLE `btc_deposits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=241;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `daily_fees`
--
ALTER TABLE `daily_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `deposits`
--
ALTER TABLE `deposits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `disputes`
--
ALTER TABLE `disputes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `investments`
--
ALTER TABLE `investments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `investment_events`
--
ALTER TABLE `investment_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_change_requests`
--
ALTER TABLE `password_change_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paypal_deposits`
--
ALTER TABLE `paypal_deposits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referral_bonus`
--
ALTER TABLE `referral_bonus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `referral_bonus_log`
--
ALTER TABLE `referral_bonus_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_event_log`
--
ALTER TABLE `system_event_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT for table `system_status`
--
ALTER TABLE `system_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ticket_followups`
--
ALTER TABLE `ticket_followups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `user_chats`
--
ALTER TABLE `user_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_notification_reads`
--
ALTER TABLE `user_notification_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_packages`
--
ALTER TABLE `user_packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `withdrawals_requested`
--
ALTER TABLE `withdrawals_requested`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `balance_logs`
--
ALTER TABLE `balance_logs`
  ADD CONSTRAINT `balance_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `balance_logs_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `investments`
--
ALTER TABLE `investments`
  ADD CONSTRAINT `investments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `investments_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `referrals`
--
ALTER TABLE `referrals`
  ADD CONSTRAINT `referrals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `referrals_ibfk_2` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `user_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `withdrawals_requested`
--
ALTER TABLE `withdrawals_requested`
  ADD CONSTRAINT `withdrawals_requested_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `withdrawals_requested_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
