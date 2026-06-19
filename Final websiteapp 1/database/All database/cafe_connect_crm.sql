-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 19, 2026 at 03:15 PM
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
-- Database: `cafe_connect_crm`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `actor_type` enum('customer','staff','system','guest') NOT NULL DEFAULT 'system',
  `actor_id` int(11) DEFAULT NULL,
  `actor_role` varchar(40) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(80) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `metadata_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `actor_type`, `actor_id`, `actor_role`, `action`, `entity_type`, `entity_id`, `ip_address`, `user_agent`, `metadata_json`, `created_at`) VALUES
(1, 'staff', 6, 'admin', 'staff_auth_login', 'staff_login_session', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '[]', '2026-06-18 17:43:15'),
(2, 'staff', 6, 'admin', 'pos_session_open', 'pos_session', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"opening_cash_amount\":0}', '2026-06-18 17:43:17'),
(3, 'staff', 6, 'admin', 'pos_session_close', 'pos_session', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"expected_cash_amount\":0,\"closing_cash_amount\":0,\"cash_difference_amount\":0}', '2026-06-18 17:50:43'),
(4, 'staff', 6, 'admin', 'staff_auth_logout', 'staff_login_session', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '[]', '2026-06-18 17:50:43'),
(5, 'staff', 6, 'admin', 'staff_auth_login', 'staff_login_session', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '[]', '2026-06-18 17:50:50'),
(6, 'staff', 6, 'admin', 'pos_session_open', 'pos_session', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"opening_cash_amount\":0}', '2026-06-18 17:50:53'),
(7, 'staff', 6, 'admin', 'staff_save', 'staff', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"staff_name\":\"Admin Cafe Connect\"}', '2026-06-18 17:51:04'),
(8, 'staff', 6, 'admin', 'pos_session_close', 'pos_session', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"expected_cash_amount\":0,\"closing_cash_amount\":0,\"cash_difference_amount\":0}', '2026-06-18 17:51:06'),
(9, 'staff', 6, 'admin', 'staff_auth_logout', 'staff_login_session', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '[]', '2026-06-18 17:51:06'),
(10, 'staff', 6, 'admin', 'staff_auth_login', 'staff_login_session', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '[]', '2026-06-18 17:51:12'),
(11, 'staff', 6, 'admin', 'pos_session_open', 'pos_session', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"opening_cash_amount\":0}', '2026-06-18 17:51:14'),
(12, 'staff', 6, 'admin', 'staff_auth_login', 'staff_login_session', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '[]', '2026-06-18 17:52:02'),
(13, 'staff', 6, 'admin', 'pos_session_open', 'pos_session', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"opening_cash_amount\":0}', '2026-06-18 17:52:04'),
(14, 'staff', 6, NULL, 'website_order_status_update', 'website_order', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"status\":\"paid\",\"invoice_id\":6}', '2026-06-18 17:52:09'),
(15, 'staff', 6, 'admin', 'pos_session_close', 'pos_session', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"expected_cash_amount\":0,\"closing_cash_amount\":0,\"cash_difference_amount\":0}', '2026-06-18 17:52:11'),
(16, 'staff', 6, 'admin', 'staff_auth_logout', 'staff_login_session', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '[]', '2026-06-18 17:52:11'),
(17, 'customer', 1, NULL, 'member_login', 'customer', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '[]', '2026-06-18 17:52:17'),
(18, 'customer', 1, NULL, 'checkout_pending', 'invoice', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"sales_channel\":\"website\",\"total_amount\":346500,\"payment_method\":\"e_wallet\"}', '2026-06-18 17:52:57'),
(19, 'customer', 1, NULL, 'checkout_pending', 'invoice', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"sales_channel\":\"website\",\"total_amount\":495000,\"payment_method\":\"cash\"}', '2026-06-18 17:54:02'),
(20, 'customer', 1, NULL, 'member_logout', 'customer', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '[]', '2026-06-18 17:54:04'),
(21, 'staff', 6, 'admin', 'staff_auth_login', 'staff_login_session', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '[]', '2026-06-18 17:54:09'),
(22, 'staff', 6, 'admin', 'pos_session_open', 'pos_session', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"opening_cash_amount\":0}', '2026-06-18 17:54:14'),
(23, 'staff', 6, NULL, 'website_order_status_update', 'website_order', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"status\":\"paid\",\"invoice_id\":8}', '2026-06-18 17:54:16'),
(24, 'staff', 6, 'admin', 'pos_session_close', 'pos_session', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"expected_cash_amount\":0,\"closing_cash_amount\":0,\"cash_difference_amount\":0}', '2026-06-18 17:54:18'),
(25, 'staff', 6, 'admin', 'staff_auth_logout', 'staff_login_session', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '[]', '2026-06-18 17:54:18'),
(26, 'customer', 1, NULL, 'member_login', 'customer', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '[]', '2026-06-18 17:54:23'),
(27, 'customer', 1, NULL, 'member_logout', 'customer', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '[]', '2026-06-18 18:10:05'),
(28, 'customer', 1, NULL, 'member_login', 'customer', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '[]', '2026-06-18 18:12:45'),
(29, 'customer', 1, NULL, 'checkout_pending', 'invoice', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"sales_channel\":\"website\",\"total_amount\":99000,\"payment_method\":\"e_wallet\"}', '2026-06-18 18:12:53'),
(30, 'customer', 1, NULL, 'checkout_pending', 'invoice', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"sales_channel\":\"website\",\"total_amount\":99000,\"payment_method\":\"e_wallet\"}', '2026-06-18 18:12:53'),
(31, 'customer', 1, NULL, 'member_logout', 'customer', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '[]', '2026-06-18 18:13:26'),
(32, 'staff', 6, 'admin', 'staff_auth_login', 'staff_login_session', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '[]', '2026-06-18 18:13:32'),
(33, 'staff', 6, 'admin', 'pos_session_open', 'pos_session', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"opening_cash_amount\":0}', '2026-06-18 18:13:36'),
(34, 'staff', 6, 'admin', 'staff_auth_login', 'staff_login_session', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '[]', '2026-06-18 22:57:22'),
(35, 'staff', 6, 'admin', 'pos_session_open', 'pos_session', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{\"opening_cash_amount\":0}', '2026-06-18 22:57:24');

-- --------------------------------------------------------

--
-- Table structure for table `auth_lockouts`
--

CREATE TABLE `auth_lockouts` (
  `id` int(11) NOT NULL,
  `scope` varchar(60) NOT NULL,
  `identity_hash` char(64) NOT NULL,
  `identity_label` varchar(160) DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_failed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `branch_name` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `district` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `branch_name`, `address`, `district`, `status`, `created_at`) VALUES
(1, 'Coffee Connect - Cầu Giấy', '144 Xuân Thủy, Cầu Giấy, Hà Nội', 'Cầu Giấy', 'active', '2026-06-18 17:37:27'),
(2, 'Coffee Connect - Hoàn Kiếm', '25 Hàng Bài, Hoàn Kiếm, Hà Nội', 'Hoàn Kiếm', 'active', '2026-06-18 17:37:27'),
(3, 'Coffee Connect - Tây Hồ', '45 Xuân Diệu, Tây Hồ, Hà Nội', 'Tây Hồ', 'active', '2026-06-18 17:37:27'),
(4, 'Coffee Connect - Số 1', 'Số 1 Trịnh Văn Bô, Nam Từ Liêm, Hà Nội', 'Trịnh Văn Bô', 'active', '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `branch_inventory`
--

CREATE TABLE `branch_inventory` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `min_stock_level` int(11) NOT NULL DEFAULT 0,
  `last_updated` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branch_inventory`
--

INSERT INTO `branch_inventory` (`id`, `branch_id`, `product_id`, `stock_quantity`, `min_stock_level`, `last_updated`) VALUES
(1, 1, 1, 36, 20, '2026-05-13 07:00:00'),
(2, 1, 2, 18, 20, '2026-05-13 07:00:00'),
(3, 1, 5, 11, 12, '2026-05-13 07:00:00'),
(4, 1, 7, 9, 12, '2026-05-13 07:00:00'),
(5, 2, 1, 24, 20, '2026-05-13 07:00:00'),
(6, 2, 2, 28, 20, '2026-05-13 07:00:00'),
(7, 2, 4, 22, 15, '2026-05-13 07:00:00'),
(8, 2, 5, 18, 12, '2026-05-13 07:00:00'),
(9, 2, 7, 16, 12, '2026-05-13 07:00:00'),
(10, 2, 8, 14, 10, '2026-05-13 07:00:00'),
(11, 2, 9, 12, 8, '2026-05-13 07:00:00'),
(12, 3, 1, 20, 20, '2026-05-13 07:00:00'),
(13, 3, 2, 18, 20, '2026-05-13 07:00:00'),
(14, 3, 3, 12, 15, '2026-05-13 07:00:00'),
(15, 3, 4, 16, 15, '2026-05-13 07:00:00'),
(16, 3, 5, 14, 12, '2026-05-13 07:00:00'),
(17, 3, 6, 15, 12, '2026-05-13 07:00:00'),
(18, 3, 8, 12, 10, '2026-05-13 07:00:00'),
(19, 3, 9, 10, 8, '2026-05-13 07:00:00'),
(20, 4, 1, 28, 20, '2026-06-18 18:12:53'),
(21, 4, 2, 24, 20, '2026-06-18 17:52:57'),
(22, 4, 3, 30, 15, '2026-05-13 07:00:00'),
(23, 4, 4, 30, 15, '2026-05-13 07:00:00'),
(24, 4, 5, 28, 12, '2026-06-18 18:12:53'),
(25, 4, 6, 25, 12, '2026-05-13 07:00:00'),
(26, 4, 7, 25, 12, '2026-05-13 07:00:00'),
(27, 4, 8, 24, 10, '2026-05-13 07:00:00'),
(28, 4, 9, 24, 8, '2026-05-13 07:00:00'),
(29, 1, 3, 24, 12, '2026-05-13 07:00:00'),
(30, 2, 3, 24, 12, '2026-05-13 07:00:00'),
(31, 3, 7, 24, 8, '2026-05-13 07:00:00'),
(32, 1, 8, 24, 8, '2026-05-13 07:00:00'),
(33, 1, 9, 24, 8, '2026-05-13 07:00:00'),
(34, 1, 6, 24, 8, '2026-05-13 07:00:00'),
(35, 2, 6, 24, 8, '2026-05-13 07:00:00'),
(36, 1, 4, 24, 12, '2026-05-13 07:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `branch_material_inventory`
--

CREATE TABLE `branch_material_inventory` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `stock_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `min_stock_level` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `last_updated` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branch_material_inventory`
--

INSERT INTO `branch_material_inventory` (`id`, `branch_id`, `material_id`, `stock_quantity`, `min_stock_level`, `unit_cost`, `last_updated`) VALUES
(1, 1, 1, 42.00, 20.00, 0.00, '2026-05-13 07:30:00'),
(2, 2, 1, 28.00, 20.00, 0.00, '2026-05-13 07:30:00'),
(3, 4, 1, 35.78, 20.00, 0.00, '2026-06-18 18:12:53'),
(4, 3, 1, 18.00, 20.00, 0.00, '2026-05-13 07:30:00'),
(5, 1, 2, 16.00, 20.00, 0.00, '2026-05-13 07:30:00'),
(6, 2, 2, 16.00, 20.00, 0.00, '2026-05-13 07:30:00'),
(7, 4, 2, 21.78, 20.00, 0.00, '2026-06-18 17:52:57'),
(8, 3, 2, 14.00, 20.00, 0.00, '2026-05-13 07:30:00'),
(9, 1, 3, 85.00, 50.00, 0.00, '2026-05-13 07:30:00'),
(10, 2, 3, 62.00, 50.00, 0.00, '2026-05-13 07:30:00'),
(11, 4, 3, 82.84, 50.00, 0.00, '2026-06-18 18:12:53'),
(12, 3, 3, 48.00, 50.00, 0.00, '2026-05-13 07:30:00'),
(13, 1, 4, 12.00, 10.00, 0.00, '2026-05-13 07:30:00'),
(14, 2, 4, 18.00, 10.00, 0.00, '2026-05-13 07:30:00'),
(15, 4, 4, 11.98, 10.00, 0.00, '2026-06-18 18:12:53'),
(16, 3, 4, 12.00, 10.00, 0.00, '2026-05-13 07:30:00'),
(17, 1, 5, 9.00, 12.00, 0.00, '2026-05-13 07:30:00'),
(18, 2, 5, 9.00, 12.00, 0.00, '2026-05-13 07:30:00'),
(19, 4, 5, 16.00, 12.00, 0.00, '2026-05-13 07:30:00'),
(20, 3, 5, 9.00, 12.00, 0.00, '2026-05-13 07:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `campaign_recipients`
--

CREATE TABLE `campaign_recipients` (
  `id` int(11) NOT NULL,
  `marketing_email_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `voucher_id` int(11) DEFAULT NULL,
  `delivery_status` enum('queued','sent','opened','clicked','failed') NOT NULL DEFAULT 'queued',
  `sent_at` datetime DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `clicked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `campaign_recipients`
--

INSERT INTO `campaign_recipients` (`id`, `marketing_email_id`, `customer_id`, `voucher_id`, `delivery_status`, `sent_at`, `opened_at`, `clicked_at`) VALUES
(1, 1, 1, 1, 'clicked', '2026-05-01 07:05:00', '2026-05-01 07:25:00', '2026-05-01 07:26:00'),
(2, 1, 5, 5, 'opened', '2026-05-01 07:05:00', '2026-05-01 11:10:00', NULL),
(3, 2, 1, 2, 'clicked', '2026-05-01 08:05:00', '2026-05-01 09:00:00', '2026-05-01 09:05:00'),
(4, 2, 4, 4, 'opened', '2026-05-01 08:05:00', '2026-05-01 10:00:00', NULL),
(5, 3, 6, 6, 'sent', '2026-05-01 09:05:00', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cash_transactions`
--

CREATE TABLE `cash_transactions` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `pos_session_id` int(11) DEFAULT NULL,
  `transaction_type` enum('in','out') NOT NULL,
  `reason` varchar(180) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cash_transactions`
--

INSERT INTO `cash_transactions` (`id`, `branch_id`, `staff_id`, `pos_session_id`, `transaction_type`, `reason`, `amount`, `created_at`) VALUES
(1, 1, 2, NULL, 'in', 'Opening cash float', 1000000.00, '2026-05-13 07:00:00'),
(2, 1, 2, 4, 'out', 'Buy small supplies', 120000.00, '2026-05-13 11:20:00'),
(3, 1, 2, 4, 'in', 'Cash order correction', 70000.00, '2026-05-13 10:07:00'),
(4, 2, 7, NULL, 'in', 'Hoan Kiem opening float', 1500000.00, '2026-05-13 08:00:00'),
(5, 2, 7, NULL, 'out', 'Hoan Kiem local delivery fee', 90000.00, '2026-05-13 12:10:00'),
(6, 3, 8, NULL, 'in', 'Tay Ho opening float', 1200000.00, '2026-05-13 09:00:00'),
(7, 3, 8, NULL, 'out', 'Tay Ho ice purchase', 65000.00, '2026-05-13 13:20:00'),
(8, 4, 15, NULL, 'in', 'Trinh Van Bo opening float', 1000000.00, '2026-05-13 07:00:00'),
(9, 4, 15, NULL, 'out', 'Trinh Van Bo packaging purchase', 110000.00, '2026-05-13 10:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `membership_tier_id` int(11) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `preferred_channel` enum('pos','website','delivery','email','zalo','sms') NOT NULL DEFAULT 'pos',
  `password_hash` varchar(255) DEFAULT NULL,
  `last_visit_date` date DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `current_points` int(11) NOT NULL DEFAULT 0,
  `total_spending` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `membership_tier_id`, `customer_name`, `phone_number`, `email`, `gender`, `birth_date`, `address`, `preferred_channel`, `password_hash`, `last_visit_date`, `last_login_at`, `current_points`, `total_spending`, `status`, `created_at`, `updated_at`) VALUES
(1, 3, 'Nguyễn An', '0900000001', 'nguyen.an@example.test', 'male', '1997-05-20', 'Cầu Giấy, Hà Nội', 'website', '$2y$10$GYSHMAyIAElDlRoQCD9UvuLScN1/FXrtywJv3nnaeOugRkrCq6rr.', '2026-06-18', '2026-06-18 18:12:44', 469, 4695000.00, 'active', '2026-06-18 17:37:27', '2026-06-18 18:12:44'),
(2, 2, 'Trần Bình', '0900000002', 'tran.binh@example.test', 'male', '1995-08-12', 'Hoàn Kiếm, Hà Nội', 'pos', '$2y$10$GYSHMAyIAElDlRoQCD9UvuLScN1/FXrtywJv3nnaeOugRkrCq6rr.', '2026-05-11', NULL, 180, 1850000.00, 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(3, 1, 'Lê Chi', '0900000003', 'le.chi@example.test', 'female', '2000-01-04', 'Tây Hồ, Hà Nội', 'pos', '$2y$10$GYSHMAyIAElDlRoQCD9UvuLScN1/FXrtywJv3nnaeOugRkrCq6rr.', '2026-05-08', NULL, 60, 650000.00, 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(4, 3, 'Phạm Dung', '0900000004', 'pham.dung@example.test', 'female', '1991-05-02', 'Đống Đa, Hà Nội', 'email', '$2y$10$GYSHMAyIAElDlRoQCD9UvuLScN1/FXrtywJv3nnaeOugRkrCq6rr.', '2026-06-18', NULL, 523, 5439000.00, 'active', '2026-06-18 17:37:27', '2026-06-18 17:52:09'),
(5, 2, 'Hoàng Gia', '0900000005', 'hoang.gia@example.test', 'other', '1998-05-29', 'Thanh Xuân, Hà Nội', 'zalo', '$2y$10$GYSHMAyIAElDlRoQCD9UvuLScN1/FXrtywJv3nnaeOugRkrCq6rr.', '2026-04-01', NULL, 140, 1450000.00, 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(6, 1, 'Vũ Hoa', '0900000006', 'vu.hoa@example.test', 'female', '1993-11-18', 'Cầu Giấy, Hà Nội', 'sms', '$2y$10$GYSHMAyIAElDlRoQCD9UvuLScN1/FXrtywJv3nnaeOugRkrCq6rr.', '2026-03-25', NULL, 30, 320000.00, 'inactive', '2026-06-18 17:37:27', '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `customer_favorites`
--

CREATE TABLE `customer_favorites` (
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_favorites`
--

INSERT INTO `customer_favorites` (`customer_id`, `product_id`, `created_at`) VALUES
(1, 1, '2026-06-18 17:37:27'),
(1, 9, '2026-06-18 17:37:27'),
(2, 2, '2026-06-18 17:37:27'),
(4, 8, '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `customer_interactions`
--

CREATE TABLE `customer_interactions` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `interaction_type` enum('pos_visit','website_order','email_sent','voucher_redeemed','feedback','care_call') NOT NULL,
  `interaction_note` varchar(255) NOT NULL,
  `sentiment` enum('positive','neutral','negative') NOT NULL DEFAULT 'neutral',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_interactions`
--

INSERT INTO `customer_interactions` (`id`, `customer_id`, `staff_id`, `invoice_id`, `interaction_type`, `interaction_note`, `sentiment`, `created_at`) VALUES
(1, 1, NULL, 1, 'website_order', 'Member ordered through website and used first-order voucher.', 'positive', '2026-05-10 09:32:00'),
(2, 2, 2, 2, 'pos_visit', 'Cashier checkout with member lookup.', 'positive', '2026-05-11 14:12:00'),
(3, 4, 5, NULL, 'email_sent', 'Gold campaign email sent.', 'neutral', '2026-05-01 08:00:00'),
(4, 6, 5, NULL, 'email_sent', 'Reactivation email sent.', 'neutral', '2026-05-01 08:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `customer_password_resets`
--

CREATE TABLE `customer_password_resets` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `request_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_reviews`
--

CREATE TABLE `customer_reviews` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `customer_title` varchar(150) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL DEFAULT 5,
  `review_text` text NOT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `status` enum('draft','published','hidden') NOT NULL DEFAULT 'published',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_reviews`
--

INSERT INTO `customer_reviews` (`id`, `customer_name`, `customer_title`, `rating`, `review_text`, `avatar_path`, `status`, `created_at`) VALUES
(1, 'Nguyễn An', 'Gold member', 5, 'Member lookup, voucher và website checkout đã kết nối trong cùng một luồng.', 'assets/images/avatar-1.png', 'published', '2026-05-12 10:00:00'),
(2, 'Trần Bình', 'Khách quen buổi sáng', 5, 'Thu ngân áp điểm và xem lịch sử đơn hàng rất nhanh.', 'assets/images/avatar-2.png', 'published', '2026-05-11 16:00:00'),
(3, 'Phạm Dung', 'Remote worker', 4, 'Menu website và POS tại quầy dùng chung dữ liệu sản phẩm.', 'assets/images/avatar-3.png', 'published', '2026-05-10 15:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `customer_segments`
--

CREATE TABLE `customer_segments` (
  `id` int(11) NOT NULL,
  `segment_code` varchar(50) NOT NULL,
  `segment_name` varchar(120) NOT NULL,
  `rule_description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_segments`
--

INSERT INTO `customer_segments` (`id`, `segment_code`, `segment_name`, `rule_description`, `created_at`) VALUES
(1, 'new_member', 'New member', 'Customers with spending below 1,000,000 VND.', '2026-06-18 17:37:27'),
(2, 'loyal_gold', 'Loyal Gold', 'Gold tier customers with recent visits.', '2026-06-18 17:37:27'),
(3, 'birthday_may', 'May birthday', 'Customers with a May birthday.', '2026-06-18 17:37:27'),
(4, 'reactivation', 'Reactivation', 'Inactive customers for campaign outreach.', '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `customer_segment_memberships`
--

CREATE TABLE `customer_segment_memberships` (
  `customer_id` int(11) NOT NULL,
  `segment_id` int(11) NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `source` enum('manual','auto') NOT NULL DEFAULT 'auto'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_segment_memberships`
--

INSERT INTO `customer_segment_memberships` (`customer_id`, `segment_id`, `assigned_at`, `source`) VALUES
(1, 2, '2026-06-18 17:37:27', 'auto'),
(1, 3, '2026-06-18 17:37:27', 'auto'),
(4, 2, '2026-06-18 17:37:27', 'auto'),
(4, 3, '2026-06-18 17:37:27', 'auto'),
(5, 3, '2026-06-18 17:37:27', 'auto'),
(6, 4, '2026-06-18 17:37:27', 'auto');

-- --------------------------------------------------------

--
-- Table structure for table `dining_tables`
--

CREATE TABLE `dining_tables` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `area_name` varchar(80) NOT NULL DEFAULT 'Main',
  `seat_count` int(11) NOT NULL DEFAULT 2,
  `status` enum('available','occupied','reserved','inactive') NOT NULL DEFAULT 'available',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dining_tables`
--

INSERT INTO `dining_tables` (`id`, `branch_id`, `table_name`, `area_name`, `seat_count`, `status`, `created_at`) VALUES
(1, 1, 'T01', 'Window', 2, 'available', '2026-06-18 17:37:27'),
(2, 1, 'T02', 'Window', 4, 'occupied', '2026-06-18 17:37:27'),
(3, 1, 'T03', 'Main', 4, 'available', '2026-06-18 17:37:27'),
(4, 1, 'T04', 'Main', 6, 'occupied', '2026-06-18 17:37:27'),
(5, 1, 'T05', 'Garden', 2, 'available', '2026-06-18 17:37:27'),
(6, 2, 'H01', 'Lobby', 2, 'available', '2026-06-18 17:37:27'),
(7, 2, 'H02', 'Lobby', 4, 'occupied', '2026-06-18 17:37:27'),
(8, 2, 'H03', 'Balcony', 2, 'available', '2026-06-18 17:37:27'),
(9, 2, 'H04', 'Main', 6, 'available', '2026-06-18 17:37:27'),
(10, 2, 'H05', 'Main', 4, 'available', '2026-06-18 17:37:27'),
(11, 3, 'W01', 'Lake', 2, 'available', '2026-06-18 17:37:27'),
(12, 3, 'W02', 'Lake', 4, 'occupied', '2026-06-18 17:37:27'),
(13, 3, 'W03', 'Main', 4, 'available', '2026-06-18 17:37:27'),
(14, 3, 'W04', 'Terrace', 6, 'available', '2026-06-18 17:37:27'),
(15, 4, 'B01', 'Ground floor', 2, 'available', '2026-06-18 17:37:27'),
(16, 4, 'B02', 'Ground floor', 4, 'occupied', '2026-06-18 17:37:27'),
(17, 4, 'B03', 'Study corner', 4, 'available', '2026-06-18 17:37:27'),
(18, 4, 'B04', 'Balcony', 6, 'available', '2026-06-18 17:37:27'),
(19, 4, 'B05', 'Takeaway', 2, 'available', '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_materials`
--

CREATE TABLE `inventory_materials` (
  `id` int(11) NOT NULL,
  `material_name` varchar(150) NOT NULL,
  `unit` varchar(30) NOT NULL,
  `stock_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `min_stock_level` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `supplier_name` varchar(150) DEFAULT NULL,
  `last_updated` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_materials`
--

INSERT INTO `inventory_materials` (`id`, `material_name`, `unit`, `stock_quantity`, `min_stock_level`, `unit_cost`, `supplier_name`, `last_updated`, `status`) VALUES
(1, 'Arabica beans', 'kg', 123.78, 20.00, 190000.00, 'Highland Supply', '2026-06-18 18:12:53', 'active'),
(2, 'Robusta beans', 'kg', 67.78, 20.00, 120000.00, 'Dak Lak Roaster', '2026-06-18 17:52:57', 'active'),
(3, 'Fresh milk', 'litre', 277.84, 50.00, 30000.00, 'Daily Milk', '2026-06-18 18:12:53', 'active'),
(4, 'Tea leaves', 'kg', 53.98, 10.00, 160000.00, 'Lotus Tea Farm', '2026-06-18 18:12:53', 'active'),
(5, 'Croissant dough', 'pack', 9.00, 12.00, 25000.00, 'Bakery Partner', '2026-05-13 07:00:00', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `pos_session_id` int(11) DEFAULT NULL,
  `service_order_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `voucher_id` int(11) DEFAULT NULL,
  `sales_channel` enum('pos','website','delivery') NOT NULL DEFAULT 'pos',
  `invoice_date` date NOT NULL,
  `invoice_time` time NOT NULL,
  `bill_started_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `subtotal_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `membership_discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `voucher_discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `points_earned` int(11) NOT NULL DEFAULT 0,
  `payment_method` enum('cash','card','e_wallet') NOT NULL,
  `status` enum('pending','paid','cancelled','refunded') NOT NULL DEFAULT 'paid',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `branch_id`, `staff_id`, `pos_session_id`, `service_order_id`, `customer_id`, `voucher_id`, `sales_channel`, `invoice_date`, `invoice_time`, `bill_started_at`, `paid_at`, `subtotal_amount`, `membership_discount_amount`, `voucher_discount_amount`, `total_amount`, `points_earned`, `payment_method`, `status`, `created_at`) VALUES
(1, 1, 2, NULL, NULL, 1, 3, 'website', '2026-05-10', '09:30:00', '2026-05-10 09:30:00', '2026-05-10 09:32:00', 165000.00, 16500.00, 15000.00, 133500.00, 13, 'e_wallet', 'paid', '2026-05-10 09:32:00'),
(2, 1, 2, 1, NULL, 2, NULL, 'pos', '2026-05-11', '14:10:00', '2026-05-11 14:10:00', '2026-05-11 14:12:00', 145000.00, 7250.00, 0.00, 137750.00, 13, 'cash', 'paid', '2026-05-11 14:12:00'),
(3, 2, 18, NULL, NULL, 4, NULL, 'pos', '2026-05-12', '18:05:00', '2026-05-12 18:05:00', '2026-05-12 18:08:00', 262000.00, 26200.00, 0.00, 235800.00, 23, 'card', 'paid', '2026-05-12 18:08:00'),
(4, 3, 8, NULL, NULL, 5, NULL, 'delivery', '2026-05-13', '08:40:00', '2026-05-13 08:40:00', '2026-05-13 08:43:00', 110000.00, 5500.00, 0.00, 104500.00, 10, 'e_wallet', 'paid', '2026-05-13 08:43:00'),
(5, 1, 2, 4, NULL, NULL, NULL, 'pos', '2026-05-13', '10:05:00', '2026-05-13 10:05:00', '2026-05-13 10:07:00', 70000.00, 0.00, 0.00, 70000.00, 0, 'cash', 'paid', '2026-05-13 10:07:00'),
(6, 4, 15, NULL, NULL, 4, NULL, 'website', '2026-05-13', '11:15:00', '2026-05-13 11:15:00', '2026-06-18 17:52:09', 139000.00, 0.00, 0.00, 139000.00, 13, 'cash', 'paid', '2026-05-13 11:15:00'),
(7, 4, 2, NULL, NULL, 1, NULL, 'website', '2026-06-18', '17:52:57', '2026-06-18 17:52:57', NULL, 385000.00, 38500.00, 0.00, 346500.00, 0, 'e_wallet', 'pending', '2026-06-18 17:52:57'),
(8, 4, 2, NULL, NULL, 1, NULL, 'website', '2026-06-18', '17:54:02', '2026-06-18 17:54:02', '2026-06-18 17:54:16', 550000.00, 55000.00, 0.00, 495000.00, 49, 'cash', 'paid', '2026-06-18 17:54:02'),
(9, 4, 2, NULL, NULL, 1, NULL, 'website', '2026-06-18', '18:12:53', '2026-06-18 18:12:53', NULL, 110000.00, 11000.00, 0.00, 99000.00, 0, 'e_wallet', 'pending', '2026-06-18 18:12:53'),
(10, 4, 2, NULL, NULL, 1, NULL, 'website', '2026-06-18', '18:12:53', '2026-06-18 18:12:53', NULL, 110000.00, 11000.00, 0.00, 99000.00, 0, 'e_wallet', 'pending', '2026-06-18 18:12:53');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_details`
--

CREATE TABLE `invoice_details` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `size` enum('S','M','L') DEFAULT NULL,
  `topping` varchar(100) DEFAULT NULL,
  `line_total` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoice_details`
--

INSERT INTO `invoice_details` (`id`, `invoice_id`, `product_id`, `quantity`, `unit_price`, `size`, `topping`, `line_total`) VALUES
(1, 1, 1, 2, 55000.00, 'M', NULL, 110000.00),
(2, 1, 4, 1, 45000.00, 'M', NULL, 45000.00),
(3, 2, 2, 3, 35000.00, 'M', NULL, 105000.00),
(4, 2, 7, 1, 42000.00, NULL, NULL, 42000.00),
(5, 3, 9, 3, 68000.00, 'L', NULL, 204000.00),
(6, 3, 8, 1, 58000.00, NULL, NULL, 58000.00),
(7, 4, 3, 1, 60000.00, 'M', NULL, 60000.00),
(8, 4, 5, 1, 48000.00, 'M', NULL, 48000.00),
(9, 5, 2, 2, 35000.00, 'M', NULL, 70000.00),
(10, 6, 1, 1, 55000.00, 'M', NULL, 55000.00),
(11, 6, 7, 2, 42000.00, NULL, 'Warm', 84000.00),
(12, 7, 2, 11, 35000.00, 'L', 'Nhiều đá', 385000.00),
(13, 8, 1, 10, 55000.00, 'L', NULL, 550000.00),
(14, 9, 5, 1, 55000.00, 'L', NULL, 55000.00),
(15, 9, 1, 1, 55000.00, 'M', NULL, 55000.00),
(16, 10, 5, 1, 55000.00, 'L', NULL, 55000.00),
(17, 10, 1, 1, 55000.00, 'M', NULL, 55000.00);

-- --------------------------------------------------------

--
-- Table structure for table `invoice_refunds`
--

CREATE TABLE `invoice_refunds` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `pos_session_id` int(11) DEFAULT NULL,
  `refund_amount` decimal(12,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `status` enum('approved','rejected') NOT NULL DEFAULT 'approved',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_point_transactions`
--

CREATE TABLE `loyalty_point_transactions` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `transaction_type` enum('earn','redeem','adjust') NOT NULL,
  `points` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loyalty_point_transactions`
--

INSERT INTO `loyalty_point_transactions` (`id`, `customer_id`, `invoice_id`, `transaction_type`, `points`, `description`, `created_at`) VALUES
(1, 1, 1, 'earn', 13, 'Earned points from invoice #1', '2026-05-10 09:32:00'),
(2, 2, 2, 'earn', 13, 'Earned points from invoice #2', '2026-05-11 14:12:00'),
(3, 4, 3, 'earn', 23, 'Earned points from invoice #3', '2026-05-12 18:08:00'),
(4, 5, 4, 'earn', 10, 'Earned points from invoice #4', '2026-05-13 08:43:00'),
(5, 4, 6, 'earn', 13, 'Earned points from COD order #6', '2026-06-18 17:52:09'),
(6, 1, 8, 'earn', 49, 'Earned points from COD order #8', '2026-06-18 17:54:16');

-- --------------------------------------------------------

--
-- Table structure for table `marketing_emails`
--

CREATE TABLE `marketing_emails` (
  `id` int(11) NOT NULL,
  `promotion_id` int(11) DEFAULT NULL,
  `created_by_staff_id` int(11) NOT NULL,
  `email_subject` varchar(200) NOT NULL,
  `email_body` text NOT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `status` enum('draft','scheduled','sent','cancelled') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `marketing_emails`
--

INSERT INTO `marketing_emails` (`id`, `promotion_id`, `created_by_staff_id`, `email_subject`, `email_body`, `scheduled_at`, `sent_at`, `status`, `created_at`) VALUES
(1, 1, 5, 'May birthday reward', 'Birthday members receive a Cafe Connect voucher.', '2026-05-01 07:00:00', '2026-05-01 07:05:00', 'sent', '2026-06-18 17:37:27'),
(2, 2, 5, 'Gold member week', 'Gold members receive 20 percent discount.', '2026-05-01 08:00:00', '2026-05-01 08:05:00', 'sent', '2026-06-18 17:37:27'),
(3, 4, 5, 'Return coffee call', 'Inactive members receive a reactivation offer.', '2026-05-01 09:00:00', '2026-05-01 09:05:00', 'sent', '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `membership_tiers`
--

CREATE TABLE `membership_tiers` (
  `id` int(11) NOT NULL,
  `tier_name` varchar(50) NOT NULL,
  `min_total_spending` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `membership_tiers`
--

INSERT INTO `membership_tiers` (`id`, `tier_name`, `min_total_spending`, `discount_rate`, `description`, `created_at`) VALUES
(1, 'Bronze', 0.00, 0.00, 'Default tier for new members.', '2026-06-18 17:37:27'),
(2, 'Silver', 1000000.00, 5.00, 'Returning customer tier with checkout discount.', '2026-06-18 17:37:27'),
(3, 'Gold', 3000000.00, 10.00, 'High-value member tier with stronger checkout discount.', '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_subscribers`
--

CREATE TABLE `newsletter_subscribers` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subscriber_name` varchar(150) DEFAULT NULL,
  `status` enum('active','unsubscribed') NOT NULL DEFAULT 'active',
  `subscribed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `newsletter_subscribers`
--

INSERT INTO `newsletter_subscribers` (`id`, `email`, `subscriber_name`, `status`, `subscribed_at`) VALUES
(1, 'nguyen.an@example.test', 'Nguyễn An', 'active', '2026-06-18 17:37:27'),
(2, 'marketing.demo@example.test', 'Demo Subscriber', 'active', '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `payment_method` enum('cash','card','e_wallet') NOT NULL,
  `payment_provider` varchar(80) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `transaction_reference` varchar(120) DEFAULT NULL,
  `status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'paid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `invoice_id`, `payment_method`, `payment_provider`, `amount`, `paid_at`, `transaction_reference`, `status`) VALUES
(1, 1, 'e_wallet', 'Demo Momo', 133500.00, '2026-05-10 09:32:00', 'WEB-000001', 'paid'),
(2, 2, 'cash', NULL, 137750.00, '2026-05-11 14:12:00', NULL, 'paid'),
(3, 3, 'card', 'Demo card', 235800.00, '2026-05-12 18:08:00', 'CARD-000003', 'paid'),
(4, 4, 'e_wallet', 'Demo ZaloPay', 104500.00, '2026-05-13 08:43:00', 'DEL-000004', 'paid'),
(5, 5, 'cash', NULL, 70000.00, '2026-05-13 10:07:00', NULL, 'paid'),
(6, 6, 'cash', 'COD', 139000.00, '2026-06-18 17:52:09', 'WEB-000006', 'paid'),
(7, 7, 'e_wallet', 'MoMo Sandbox', 346500.00, NULL, 'WEBSITE-000007', 'pending'),
(8, 8, 'cash', 'Cash on Delivery', 495000.00, '2026-06-18 17:54:16', 'WEBSITE-000008', 'paid'),
(9, 9, 'e_wallet', 'MoMo Sandbox', 99000.00, NULL, 'WEBSITE-000009', 'pending'),
(10, 10, 'e_wallet', 'MoMo Sandbox', 99000.00, NULL, 'WEBSITE-000010', 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `payment_transactions`
--

CREATE TABLE `payment_transactions` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `invoice_id` int(11) NOT NULL,
  `provider` varchar(40) NOT NULL DEFAULT 'momo',
  `provider_order_id` varchar(200) NOT NULL,
  `provider_request_id` varchar(80) NOT NULL,
  `provider_transaction_id` varchar(80) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `pay_url` text DEFAULT NULL,
  `deeplink` text DEFAULT NULL,
  `qr_code_url` text DEFAULT NULL,
  `result_code` int(11) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `status` enum('created','pending','paid','failed','cancelled') NOT NULL DEFAULT 'created',
  `raw_request_json` text DEFAULT NULL,
  `raw_response_json` text DEFAULT NULL,
  `raw_ipn_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_transactions`
--

INSERT INTO `payment_transactions` (`id`, `payment_id`, `invoice_id`, `provider`, `provider_order_id`, `provider_request_id`, `provider_transaction_id`, `amount`, `pay_url`, `deeplink`, `qr_code_url`, `result_code`, `message`, `status`, `raw_request_json`, `raw_response_json`, `raw_ipn_json`, `created_at`, `updated_at`) VALUES
(1, 7, 7, 'momo', 'CCINV-7-1781779977', 'REQ-7-1781779977', NULL, 346500.00, 'https://test-payment.momo.vn/v2/gateway/pay?t=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi03LTE3ODE3Nzk5Nzc&s=065d1324e16895ee5da188e48abced2736be78de3ccf3d8b7e3648c588fe83eb', 'momo://app?action=payWithApp&isScanQR=false&scanQR=false&serviceType=app&sid=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi03LTE3ODE3Nzk5Nzc&v=3.0', '00020101021226110007vn.momo38620010A00000072701320006970454011899MM26169O000001570208QRIBFTTA530370454063465005802VN62500515MMT6Ck4yG4mYyQR070100822Cafe Connect invoice 76304eea5', 0, 'Thành công.', 'pending', '{\"partnerCode\":\"MOMOBKUN20180529\",\"requestType\":\"captureWallet\",\"ipnUrl\":\"http://localhost/C%C3%A1c%20h%E1%BB%87%20th%E1%BB%91ng%20th%C3%B4ng%20tin%20doanh%20nghi%E1%BB%87p/Cafe-Connect-POS/Final%20websiteapp%201/api/payment-momo-ipn\",\"redirectUrl\":\"http://localhost/C%C3%A1c%20h%E1%BB%87%20th%E1%BB%91ng%20th%C3%B4ng%20tin%20doanh%20nghi%E1%BB%87p/Cafe-Connect-POS/Final%20websiteapp%201/payment/momo-return?invoice_id=7\",\"orderId\":\"CCINV-7-1781779977\",\"amount\":346500,\"orderInfo\":\"Cafe Connect invoice #7\",\"requestId\":\"REQ-7-1781779977\",\"extraData\":\"eyJpbnZvaWNlX2lkIjo3LCJjdXN0b21lcl9pZCI6MX0=\",\"lang\":\"vi\",\"signature\":\"18fb92c690c8298c9fae95ca0019be96e1ecad44a06670747bb0909cc66f7097\"}', '{\"partnerCode\":\"MOMOBKUN20180529\",\"orderId\":\"CCINV-7-1781779977\",\"requestId\":\"REQ-7-1781779977\",\"amount\":346500,\"responseTime\":1781779982343,\"message\":\"Thành công.\",\"resultCode\":0,\"payUrl\":\"https://test-payment.momo.vn/v2/gateway/pay?t=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi03LTE3ODE3Nzk5Nzc&s=065d1324e16895ee5da188e48abced2736be78de3ccf3d8b7e3648c588fe83eb\",\"deeplink\":\"momo://app?action=payWithApp&isScanQR=false&scanQR=false&serviceType=app&sid=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi03LTE3ODE3Nzk5Nzc&v=3.0\",\"qrCodeUrl\":\"00020101021226110007vn.momo38620010A00000072701320006970454011899MM26169O000001570208QRIBFTTA530370454063465005802VN62500515MMT6Ck4yG4mYyQR070100822Cafe Connect invoice 76304eea5\",\"applink\":\"momo://app?action=payWithApp&isScanQR=false&scanQR=false&serviceType=app&sid=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi03LTE3ODE3Nzk5Nzc&v=3.0\",\"deeplinkMiniApp\":\"momo://app?action=payWithApp&isScanQR=false&scanQR=false&serviceType=miniapp&sid=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi03LTE3ODE3Nzk5Nzc&v=3.0\",\"signature\":\"747e648ac425dee0b9abad9c803865f7d867e2db29c849bad136655e2867cbcf\"}', NULL, '2026-06-18 17:52:57', '2026-06-18 17:53:02'),
(2, 9, 9, 'momo', 'CCINV-9-1781781173', 'REQ-9-1781781173', NULL, 99000.00, 'https://test-payment.momo.vn/v2/gateway/pay?t=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi05LTE3ODE3ODExNzM&s=1877acee3bbab4896d72f876fcfe77a23e2c21b9af1f991f0966f9ef16d2c30a', 'momo://app?action=payWithApp&isScanQR=false&scanQR=false&serviceType=app&sid=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi05LTE3ODE3ODExNzM&v=3.0', '00020101021226110007vn.momo38630010A000000727013300069710250119PMC26169000000000790208QRIBFTTA53037045405990005802VN62500515MMTsTqpcrhmWnQR070100822Cafe Connect invoice 963042490', 0, 'Thành công.', 'pending', '{\"partnerCode\":\"MOMOBKUN20180529\",\"requestType\":\"captureWallet\",\"ipnUrl\":\"http://localhost/C%C3%A1c%20h%E1%BB%87%20th%E1%BB%91ng%20th%C3%B4ng%20tin%20doanh%20nghi%E1%BB%87p/Cafe-Connect-POS/Final%20websiteapp%201/api/payment-momo-ipn\",\"redirectUrl\":\"http://localhost/C%C3%A1c%20h%E1%BB%87%20th%E1%BB%91ng%20th%C3%B4ng%20tin%20doanh%20nghi%E1%BB%87p/Cafe-Connect-POS/Final%20websiteapp%201/payment/momo-return?invoice_id=9\",\"orderId\":\"CCINV-9-1781781173\",\"amount\":99000,\"orderInfo\":\"Cafe Connect invoice #9\",\"requestId\":\"REQ-9-1781781173\",\"extraData\":\"eyJpbnZvaWNlX2lkIjo5LCJjdXN0b21lcl9pZCI6MX0=\",\"lang\":\"vi\",\"signature\":\"78f6e9bac801168601c078699df5458f7f2ea19ff1d2b2fc5db7bc706299c305\"}', '{\"partnerCode\":\"MOMOBKUN20180529\",\"orderId\":\"CCINV-9-1781781173\",\"requestId\":\"REQ-9-1781781173\",\"amount\":99000,\"responseTime\":1781781173854,\"message\":\"Thành công.\",\"resultCode\":0,\"payUrl\":\"https://test-payment.momo.vn/v2/gateway/pay?t=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi05LTE3ODE3ODExNzM&s=1877acee3bbab4896d72f876fcfe77a23e2c21b9af1f991f0966f9ef16d2c30a\",\"deeplink\":\"momo://app?action=payWithApp&isScanQR=false&scanQR=false&serviceType=app&sid=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi05LTE3ODE3ODExNzM&v=3.0\",\"qrCodeUrl\":\"00020101021226110007vn.momo38630010A000000727013300069710250119PMC26169000000000790208QRIBFTTA53037045405990005802VN62500515MMTsTqpcrhmWnQR070100822Cafe Connect invoice 963042490\",\"applink\":\"momo://app?action=payWithApp&isScanQR=false&scanQR=false&serviceType=app&sid=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi05LTE3ODE3ODExNzM&v=3.0\",\"deeplinkMiniApp\":\"momo://app?action=payWithApp&isScanQR=false&scanQR=false&serviceType=miniapp&sid=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi05LTE3ODE3ODExNzM&v=3.0\",\"signature\":\"c1492968a93f1118347450e97288ffd37c60f144bfa2f7e119225a6d58f598a0\"}', NULL, '2026-06-18 18:12:53', '2026-06-18 18:12:53'),
(3, 10, 10, 'momo', 'CCINV-10-1781781173', 'REQ-10-1781781173', NULL, 99000.00, 'https://test-payment.momo.vn/v2/gateway/pay?t=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi0xMC0xNzgxNzgxMTcz&s=39c4469edca78492ccaf348ba877918f4effce9e537938bc996f339fb27e3e76', 'momo://app?action=payWithApp&isScanQR=false&scanQR=false&serviceType=app&sid=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi0xMC0xNzgxNzgxMTcz&v=3.0', '00020101021226110007vn.momo38620010A00000072701320006970454011899MM26169O000001600208QRIBFTTA53037045405990005802VN62510515MMT4Ec1WQpFj5QR070100823Cafe Connect invoice 10630463e2', 0, 'Thành công.', 'pending', '{\"partnerCode\":\"MOMOBKUN20180529\",\"requestType\":\"captureWallet\",\"ipnUrl\":\"http://localhost/C%C3%A1c%20h%E1%BB%87%20th%E1%BB%91ng%20th%C3%B4ng%20tin%20doanh%20nghi%E1%BB%87p/Cafe-Connect-POS/Final%20websiteapp%201/api/payment-momo-ipn\",\"redirectUrl\":\"http://localhost/C%C3%A1c%20h%E1%BB%87%20th%E1%BB%91ng%20th%C3%B4ng%20tin%20doanh%20nghi%E1%BB%87p/Cafe-Connect-POS/Final%20websiteapp%201/payment/momo-return?invoice_id=10\",\"orderId\":\"CCINV-10-1781781173\",\"amount\":99000,\"orderInfo\":\"Cafe Connect invoice #10\",\"requestId\":\"REQ-10-1781781173\",\"extraData\":\"eyJpbnZvaWNlX2lkIjoxMCwiY3VzdG9tZXJfaWQiOjF9\",\"lang\":\"vi\",\"signature\":\"f191c278019c1319a64b9cc096c21c7fcb4c36a81cdbd94ef333ea448ac38698\"}', '{\"partnerCode\":\"MOMOBKUN20180529\",\"orderId\":\"CCINV-10-1781781173\",\"requestId\":\"REQ-10-1781781173\",\"amount\":99000,\"responseTime\":1781781174506,\"message\":\"Thành công.\",\"resultCode\":0,\"payUrl\":\"https://test-payment.momo.vn/v2/gateway/pay?t=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi0xMC0xNzgxNzgxMTcz&s=39c4469edca78492ccaf348ba877918f4effce9e537938bc996f339fb27e3e76\",\"deeplink\":\"momo://app?action=payWithApp&isScanQR=false&scanQR=false&serviceType=app&sid=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi0xMC0xNzgxNzgxMTcz&v=3.0\",\"qrCodeUrl\":\"00020101021226110007vn.momo38620010A00000072701320006970454011899MM26169O000001600208QRIBFTTA53037045405990005802VN62510515MMT4Ec1WQpFj5QR070100823Cafe Connect invoice 10630463e2\",\"applink\":\"momo://app?action=payWithApp&isScanQR=false&scanQR=false&serviceType=app&sid=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi0xMC0xNzgxNzgxMTcz&v=3.0\",\"deeplinkMiniApp\":\"momo://app?action=payWithApp&isScanQR=false&scanQR=false&serviceType=miniapp&sid=TU9NT0JLVU4yMDE4MDUyOXxDQ0lOVi0xMC0xNzgxNzgxMTcz&v=3.0\",\"signature\":\"200ed20a8338eacbfc73a9505a85b3b9c333372bb3c73a3c6ace9b9313e21c79\"}', NULL, '2026-06-18 18:12:53', '2026-06-18 18:12:54');

-- --------------------------------------------------------

--
-- Table structure for table `pos_activity_logs`
--

CREATE TABLE `pos_activity_logs` (
  `id` int(11) NOT NULL,
  `pos_session_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `staff_role` enum('waiter','cashier','barista','owner','manager','marketing','admin') NOT NULL,
  `action_type` varchar(60) NOT NULL,
  `entity_type` varchar(60) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status_from` varchar(40) DEFAULT NULL,
  `status_to` varchar(40) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pos_activity_logs`
--

INSERT INTO `pos_activity_logs` (`id`, `pos_session_id`, `staff_id`, `staff_role`, `action_type`, `entity_type`, `entity_id`, `product_id`, `quantity`, `amount`, `status_from`, `status_to`, `note`, `created_at`) VALUES
(1, 1, 2, 'cashier', 'session_login', 'pos_session', 1, NULL, 0.00, 1000000.00, NULL, 'open', 'POS login', '2026-05-11 07:00:00'),
(2, 1, 2, 'cashier', 'checkout', 'invoice', 2, NULL, 4.00, 137750.00, NULL, 'cash', 'Direct POS checkout', '2026-05-11 14:12:00'),
(3, 1, 2, 'cashier', 'session_logout', 'pos_session', 1, NULL, 0.00, 1137750.00, 'open', 'closed', 'POS logout', '2026-05-11 15:00:00'),
(4, 2, 1, 'waiter', 'session_login', 'pos_session', 2, NULL, 0.00, 0.00, NULL, 'open', 'POS login', '2026-05-13 07:05:00'),
(5, 2, 1, 'waiter', 'order_created', 'service_order', 1, NULL, 3.00, 158000.00, NULL, 'preparing', 'OD-101', '2026-05-13 09:12:00'),
(6, 2, 1, 'waiter', 'order_created', 'service_order', 2, NULL, 4.00, 154000.00, NULL, 'ready', 'OD-102', '2026-05-13 09:25:00'),
(7, 3, 3, 'barista', 'kitchen_ready', 'service_order_item', 3, 7, 2.00, 84000.00, 'preparing', 'ready', 'OD-102 - Croissant Butter', '2026-05-13 09:34:00'),
(8, 3, 3, 'barista', 'kitchen_ready', 'service_order_item', 4, 2, 2.00, 70000.00, 'preparing', 'ready', 'OD-102 - Vietnamese Phin Coffee', '2026-05-13 09:34:00'),
(9, 4, 2, 'cashier', 'checkout', 'invoice', 5, NULL, 2.00, 70000.00, NULL, 'cash', 'Direct POS checkout', '2026-05-13 10:07:00'),
(10, 4, 2, 'cashier', 'cash_transaction', 'cash_transaction', 2, NULL, 0.00, 120000.00, NULL, 'out', 'Buy small supplies', '2026-05-13 11:20:00'),
(11, 5, 6, 'admin', 'session_login', 'pos_session', 5, NULL, 0.00, 0.00, NULL, NULL, 'POS login', '2026-06-18 17:43:17'),
(12, 5, 6, 'admin', 'session_logout', 'pos_session', 5, NULL, 0.00, 0.00, NULL, NULL, 'POS logout', '2026-06-18 17:50:43'),
(13, 6, 6, 'admin', 'session_login', 'pos_session', 6, NULL, 0.00, 0.00, NULL, NULL, 'POS login', '2026-06-18 17:50:53'),
(14, 6, 6, 'admin', 'staff_save', 'staff', 6, NULL, 0.00, 0.00, NULL, 'admin', 'Admin Cafe Connect', '2026-06-18 17:51:04'),
(15, 6, 6, 'admin', 'session_logout', 'pos_session', 6, NULL, 0.00, 0.00, NULL, NULL, 'POS logout', '2026-06-18 17:51:06'),
(16, 7, 6, 'admin', 'session_login', 'pos_session', 7, NULL, 0.00, 0.00, NULL, NULL, 'POS login', '2026-06-18 17:51:14'),
(17, 8, 6, 'admin', 'session_login', 'pos_session', 8, NULL, 0.00, 0.00, NULL, NULL, 'POS login', '2026-06-18 17:52:04'),
(18, 8, 6, 'admin', 'website_order_paid', 'website_order', 3, NULL, 0.00, 139000.00, 'pending', 'paid', 'Invoice #6', '2026-06-18 17:52:09'),
(19, 8, 6, 'admin', 'session_logout', 'pos_session', 8, NULL, 0.00, 0.00, NULL, NULL, 'POS logout', '2026-06-18 17:52:11'),
(20, 9, 6, 'admin', 'session_login', 'pos_session', 9, NULL, 0.00, 0.00, NULL, NULL, 'POS login', '2026-06-18 17:54:14'),
(21, 9, 6, 'admin', 'website_order_paid', 'website_order', 5, NULL, 0.00, 495000.00, 'pending', 'paid', 'Invoice #8', '2026-06-18 17:54:16'),
(22, 9, 6, 'admin', 'session_logout', 'pos_session', 9, NULL, 0.00, 0.00, NULL, NULL, 'POS logout', '2026-06-18 17:54:18'),
(23, 10, 6, 'admin', 'session_login', 'pos_session', 10, NULL, 0.00, 0.00, NULL, NULL, 'POS login', '2026-06-18 18:13:36'),
(24, 11, 6, 'admin', 'session_login', 'pos_session', 11, NULL, 0.00, 0.00, NULL, NULL, 'POS login', '2026-06-18 22:57:24');

-- --------------------------------------------------------

--
-- Table structure for table `pos_sessions`
--

CREATE TABLE `pos_sessions` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `staff_login_session_id` int(11) DEFAULT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `session_token` varchar(80) NOT NULL,
  `staff_role` enum('waiter','cashier','barista','owner','manager','marketing','admin') NOT NULL,
  `opened_at` datetime NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  `last_seen_at` datetime DEFAULT NULL,
  `login_ip` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `opening_cash_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expected_cash_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `closing_cash_amount` decimal(12,2) DEFAULT NULL,
  `cash_difference_amount` decimal(12,2) DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `closed_reason` enum('manual','timeout','system') DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pos_sessions`
--

INSERT INTO `pos_sessions` (`id`, `branch_id`, `staff_id`, `staff_login_session_id`, `shift_id`, `session_token`, `staff_role`, `opened_at`, `closed_at`, `last_seen_at`, `login_ip`, `user_agent`, `opening_cash_amount`, `expected_cash_amount`, `closing_cash_amount`, `cash_difference_amount`, `status`, `closed_reason`, `notes`) VALUES
(1, 1, 2, NULL, 2, 'demo-cashier-session-20260511', 'cashier', '2026-05-11 07:00:00', '2026-05-11 15:00:00', '2026-05-11 14:58:00', NULL, NULL, 1000000.00, 1137750.00, 1137750.00, 0.00, 'closed', 'manual', 'Demo cashier checkout session.'),
(2, 1, 1, NULL, 1, 'demo-waiter-session-20260513', 'waiter', '2026-05-13 07:05:00', '2026-05-13 15:00:00', '2026-05-13 14:55:00', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 'closed', 'manual', 'Demo waiter service session.'),
(3, 1, 3, NULL, 3, 'demo-barista-session-20260513', 'barista', '2026-05-13 07:00:00', '2026-05-13 15:00:00', '2026-05-13 14:57:00', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 'closed', 'manual', 'Demo barista kitchen session.'),
(4, 1, 2, NULL, 2, 'demo-cashier-session-20260513', 'cashier', '2026-05-13 07:00:00', '2026-05-13 15:00:00', '2026-05-13 14:58:00', NULL, NULL, 1000000.00, 1020000.00, 1020000.00, 0.00, 'closed', 'manual', 'Demo cashier cash session.'),
(5, 2, 6, 1, NULL, '63a10bc1f3ca08cf46b3c08085df4f546ab0bc1658e7d3f1', 'admin', '2026-06-18 17:43:17', '2026-06-18 17:50:43', '2026-06-18 17:50:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 0.00, 0.00, 0.00, 0.00, 'closed', 'manual', ''),
(6, 2, 6, 2, NULL, '7b3c25ca12d77234a957c2b1f03522282e1e1fe9d6b33529', 'admin', '2026-06-18 17:50:53', '2026-06-18 17:51:06', '2026-06-18 17:51:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 0.00, 0.00, 0.00, 0.00, 'closed', 'manual', ''),
(7, 4, 6, 3, NULL, 'cc4d150be901514afbe1da4be5f0748a24e4003642fe83d9', 'admin', '2026-06-18 17:51:14', '2026-06-18 17:52:04', '2026-06-18 17:52:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 0.00, 0.00, NULL, NULL, 'closed', 'system', 'Closed by new login.'),
(8, 4, 6, 4, NULL, '29c33081370206ed533c5949f0402c2d42de8c81455f408e', 'admin', '2026-06-18 17:52:04', '2026-06-18 17:52:11', '2026-06-18 17:52:11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 0.00, 0.00, 0.00, 0.00, 'closed', 'manual', ''),
(9, 4, 6, 5, NULL, '48378934c33ac6cfd3583095fe4d770ad66f1668432f2d7d', 'admin', '2026-06-18 17:54:14', '2026-06-18 17:54:18', '2026-06-18 17:54:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 0.00, 0.00, 0.00, 0.00, 'closed', 'manual', ''),
(10, 4, 6, 6, NULL, 'd3baacc62bab74bf66522a1bb6b5f8bc4ea7ffa890683458', 'admin', '2026-06-18 18:13:36', '2026-06-18 22:57:14', '2026-06-18 22:57:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 0.00, 0.00, NULL, NULL, 'closed', 'timeout', 'Session closed after inactivity.'),
(11, 4, 6, 7, NULL, '772a176342848426d3bb994c4719c3a05ad0556ed118c93d', 'admin', '2026-06-18 22:57:24', NULL, '2026-06-18 22:58:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 0.00, 0.00, NULL, NULL, 'open', NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `category` varchar(40) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `take_note` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_name`, `category`, `price`, `take_note`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Signature Brown Latte', 'coffee', 55000.00, 'Espresso, fresh milk and brown sugar foam.', 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(2, 'Vietnamese Phin Coffee', 'coffee', 35000.00, 'Strong phin brew for daily members.', 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(3, 'Cold Brew Citrus', 'coffee', 60000.00, 'Cold brew with orange peel and tonic finish.', 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(4, 'Lotus Oolong Tea', 'tea', 45000.00, 'Light oolong tea with lotus aroma.', 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(5, 'Peach Lemongrass Tea', 'tea', 48000.00, 'Fresh peach, lemongrass and tea jelly.', 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(6, 'Mango Yogurt Smoothie', 'smoothie', 65000.00, 'Mango, yogurt and light cream.', 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(7, 'Croissant Butter', 'food', 42000.00, 'Warm butter croissant.', 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(8, 'Tiramisu Cup', 'food', 58000.00, 'Coffee cream dessert cup.', 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(9, 'May Bloom Macchiato', 'seasonal', 68000.00, 'Limited May campaign drink.', 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id` int(11) NOT NULL,
  `category_code` varchar(40) NOT NULL,
  `category_name` varchar(120) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`id`, `category_code`, `category_name`, `display_order`, `status`, `created_at`) VALUES
(1, 'coffee', 'Coffee', 1, 'active', '2026-06-18 17:37:27'),
(2, 'tea', 'Tea', 2, 'active', '2026-06-18 17:37:27'),
(3, 'smoothie', 'Smoothie', 3, 'active', '2026-06-18 17:37:27'),
(4, 'food', 'Food', 4, 'active', '2026-06-18 17:37:27'),
(5, 'seasonal', 'Seasonal', 5, 'active', '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `alt_text` varchar(180) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_path`, `alt_text`, `is_primary`, `display_order`, `created_at`) VALUES
(1, 1, 'assets/images/coffee-1.png', 'Signature Brown Latte', 1, 1, '2026-06-18 17:37:27'),
(2, 2, 'assets/images/coffee-2.png', 'Vietnamese Phin Coffee', 1, 1, '2026-06-18 17:37:27'),
(3, 3, 'assets/images/Cold Brew Citrus.jpg', 'Cold Brew Citrus', 1, 1, '2026-06-18 17:37:27'),
(4, 4, 'assets/images/Lotus Oolong Tea.jpg', 'Lotus Oolong Tea', 1, 1, '2026-06-18 17:37:27'),
(5, 5, 'assets/images/Peach Lemongrass Tea.jpg', 'Peach Lemongrass Tea', 1, 1, '2026-06-18 17:37:27'),
(6, 6, 'assets/images/Mango Yogurt Smoothie.jpg', 'Mango Yogurt Smoothie', 1, 1, '2026-06-18 17:37:27'),
(7, 7, 'assets/images/Croissant Butter.jpg', 'Croissant Butter', 1, 1, '2026-06-18 17:37:27'),
(8, 8, 'assets/images/Tiramisu Cup.jpg', 'Tiramisu Cup', 1, 1, '2026-06-18 17:37:27'),
(9, 9, 'assets/images/May Bloom Macchiato.jpg', 'May Bloom Macchiato', 1, 1, '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `product_size_prices`
--

CREATE TABLE `product_size_prices` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size` enum('S','M','L') NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_size_prices`
--

INSERT INTO `product_size_prices` (`id`, `product_id`, `size`, `price`, `created_at`, `updated_at`) VALUES
(1, 1, 'S', 49000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(2, 1, 'M', 55000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(3, 1, 'L', 62000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(4, 2, 'S', 30000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(5, 2, 'M', 35000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(6, 2, 'L', 42000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(7, 3, 'S', 54000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(8, 3, 'M', 60000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(9, 3, 'L', 68000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(10, 4, 'S', 40000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(11, 4, 'M', 45000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(12, 4, 'L', 52000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(13, 5, 'S', 43000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(14, 5, 'M', 48000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(15, 5, 'L', 55000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(16, 6, 'S', 59000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(17, 6, 'M', 65000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(18, 6, 'L', 73000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(19, 7, 'S', 42000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(20, 7, 'M', 42000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(21, 7, 'L', 42000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(22, 8, 'S', 58000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(23, 8, 'M', 58000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(24, 8, 'L', 58000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(25, 9, 'S', 62000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(26, 9, 'M', 68000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42'),
(27, 9, 'L', 76000.00, '2026-06-18 18:05:42', '2026-06-18 18:05:42');

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `id` int(11) NOT NULL,
  `promotion_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `target_segment` enum('all','bronze','silver','gold','birthday','inactive') NOT NULL DEFAULT 'all',
  `campaign_channel` enum('pos','website','email','zalo','sms','omnichannel') NOT NULL DEFAULT 'omnichannel',
  `discount_type` enum('fixed','percentage') NOT NULL DEFAULT 'fixed',
  `discount_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `voucher_quantity` int(11) NOT NULL DEFAULT 0,
  `usage_limit_per_customer` int(11) NOT NULL DEFAULT 1,
  `claim_code` varchar(50) DEFAULT NULL,
  `distribution_type` enum('auto_issue','claim_code') NOT NULL DEFAULT 'claim_code',
  `status` enum('draft','active','cancelled','completed') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `promotions`
--

INSERT INTO `promotions` (`id`, `promotion_name`, `description`, `start_date`, `end_date`, `target_segment`, `campaign_channel`, `discount_type`, `discount_value`, `voucher_quantity`, `usage_limit_per_customer`, `claim_code`, `distribution_type`, `status`, `created_at`) VALUES
(1, 'May Birthday Reward', 'Birthday voucher for May members.', '2026-05-01', '2026-05-31', 'birthday', 'omnichannel', 'fixed', 20000.00, 3, 1, 'BDAY-MAY', 'auto_issue', 'active', '2026-06-18 17:37:27'),
(2, 'Gold Member Week', 'Gold members receive 20 percent off.', '2026-05-01', '2026-06-15', 'gold', 'omnichannel', 'percentage', 20.00, 2, 1, 'GOLD-WEEK', 'auto_issue', 'active', '2026-06-18 17:37:27'),
(3, 'Website First Order', 'Fixed discount for website checkout.', '2026-05-01', '2026-06-30', 'all', 'website', 'fixed', 15000.00, 4, 1, 'WEB-FIRST', 'claim_code', 'active', '2026-06-18 17:37:27'),
(4, 'Return Coffee Call', 'Reactivate inactive customers.', '2026-05-01', '2026-06-15', 'inactive', 'email', 'percentage', 15.00, 2, 1, 'RETURN-CALL', 'auto_issue', 'active', '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `receipt_print_logs`
--

CREATE TABLE `receipt_print_logs` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `pos_session_id` int(11) DEFAULT NULL,
  `receipt_type` enum('html','pdf','thermal') NOT NULL DEFAULT 'html',
  `printed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recipes`
--

CREATE TABLE `recipes` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `recipe_name` varchar(160) NOT NULL,
  `yield_quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `recipes`
--

INSERT INTO `recipes` (`id`, `product_id`, `recipe_name`, `yield_quantity`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Signature Brown Latte recipe', 1.00, 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(2, 2, 'Vietnamese Phin Coffee recipe', 1.00, 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(3, 3, 'Cold Brew Citrus recipe', 1.00, 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(4, 4, 'Lotus Oolong Tea recipe', 1.00, 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(5, 5, 'Peach Lemongrass Tea recipe', 1.00, 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(6, 6, 'Mango Yogurt Smoothie recipe', 1.00, 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(7, 7, 'Croissant Butter recipe', 1.00, 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(8, 8, 'Tiramisu Cup recipe', 1.00, 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27'),
(9, 9, 'May Bloom Macchiato recipe', 1.00, 'active', '2026-06-18 17:37:27', '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `recipe_items`
--

CREATE TABLE `recipe_items` (
  `id` int(11) NOT NULL,
  `recipe_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity_per_unit` decimal(12,4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `recipe_items`
--

INSERT INTO `recipe_items` (`id`, `recipe_id`, `material_id`, `quantity_per_unit`) VALUES
(1, 1, 1, 0.0180),
(2, 1, 3, 0.1800),
(3, 2, 2, 0.0200),
(4, 3, 1, 0.0200),
(5, 4, 4, 0.0100),
(6, 5, 4, 0.0120),
(7, 6, 3, 0.1200),
(8, 7, 5, 1.0000),
(9, 8, 3, 0.0800),
(10, 9, 1, 0.0180),
(11, 9, 3, 0.1500);

-- --------------------------------------------------------

--
-- Table structure for table `schema_migrations`
--

CREATE TABLE `schema_migrations` (
  `id` int(11) NOT NULL,
  `migration_name` varchar(150) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schema_migrations`
--

INSERT INTO `schema_migrations` (`id`, `migration_name`, `applied_at`) VALUES
(1, '001_security_operations', '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `service_orders`
--

CREATE TABLE `service_orders` (
  `id` int(11) NOT NULL,
  `order_code` varchar(40) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `table_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `waiter_id` int(11) DEFAULT NULL,
  `cashier_id` int(11) DEFAULT NULL,
  `status` enum('draft','preparing','ready','served','paid','cancelled') NOT NULL DEFAULT 'draft',
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_orders`
--

INSERT INTO `service_orders` (`id`, `order_code`, `branch_id`, `table_id`, `customer_id`, `waiter_id`, `cashier_id`, `status`, `note`, `created_at`, `updated_at`) VALUES
(1, 'OD-101', 1, 2, 2, 1, NULL, 'preparing', 'Less ice for tea.', '2026-05-13 09:12:00', '2026-06-18 17:37:27'),
(2, 'OD-102', 1, 4, NULL, 1, NULL, 'ready', 'Guest at main table.', '2026-05-13 09:25:00', '2026-06-18 17:37:27'),
(3, 'OD-103', 2, 7, 1, 7, NULL, 'served', 'Waiting for cashier.', '2026-05-13 09:40:00', '2026-06-18 17:37:27'),
(4, 'OD-104', 3, 12, 3, 11, NULL, 'preparing', 'Lake view guests, less sugar.', '2026-05-13 10:05:00', '2026-06-18 17:37:27'),
(5, 'OD-105', 4, 16, 4, 14, NULL, 'ready', 'Pickup after meeting.', '2026-05-13 10:20:00', '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `service_order_items`
--

CREATE TABLE `service_order_items` (
  `id` int(11) NOT NULL,
  `service_order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `size` enum('S','M','L') DEFAULT NULL,
  `topping` varchar(100) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `line_total` decimal(12,2) NOT NULL,
  `kitchen_status` enum('waiting','preparing','ready','served','cancelled') NOT NULL DEFAULT 'waiting',
  `preparing_started_at` datetime DEFAULT NULL,
  `ready_at` datetime DEFAULT NULL,
  `served_at` datetime DEFAULT NULL,
  `prepared_by_staff_id` int(11) DEFAULT NULL,
  `prepared_by_session_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_order_items`
--

INSERT INTO `service_order_items` (`id`, `service_order_id`, `product_id`, `quantity`, `unit_price`, `size`, `topping`, `note`, `line_total`, `kitchen_status`, `preparing_started_at`, `ready_at`, `served_at`, `prepared_by_staff_id`, `prepared_by_session_id`, `created_at`) VALUES
(1, 1, 1, 2, 55000.00, 'M', NULL, NULL, 110000.00, 'preparing', NULL, NULL, NULL, NULL, NULL, '2026-06-18 17:37:27'),
(2, 1, 5, 1, 48000.00, 'M', 'Tea jelly', 'Less ice', 48000.00, 'waiting', NULL, NULL, NULL, NULL, NULL, '2026-06-18 17:37:27'),
(3, 2, 7, 2, 42000.00, NULL, NULL, 'Warm', 84000.00, 'ready', '2026-05-13 09:26:00', '2026-05-13 09:34:00', NULL, 3, 3, '2026-06-18 17:37:27'),
(4, 2, 2, 2, 35000.00, 'M', NULL, NULL, 70000.00, 'ready', '2026-05-13 09:26:00', '2026-05-13 09:34:00', NULL, 3, 3, '2026-06-18 17:37:27'),
(5, 3, 9, 1, 68000.00, 'L', NULL, NULL, 68000.00, 'served', '2026-05-13 09:42:00', '2026-05-13 09:48:00', '2026-05-13 09:55:00', 3, 3, '2026-06-18 17:37:27'),
(6, 3, 8, 1, 58000.00, NULL, NULL, NULL, 58000.00, 'served', '2026-05-13 09:42:00', '2026-05-13 09:48:00', '2026-05-13 09:55:00', 3, 3, '2026-06-18 17:37:27'),
(7, 4, 3, 1, 59000.00, 'M', 'Orange peel', 'Less sugar', 59000.00, 'preparing', NULL, NULL, NULL, NULL, NULL, '2026-06-18 17:37:27'),
(8, 4, 6, 1, 62000.00, 'M', NULL, NULL, 62000.00, 'waiting', NULL, NULL, NULL, NULL, NULL, '2026-06-18 17:37:27'),
(9, 5, 1, 1, 55000.00, 'M', NULL, NULL, 55000.00, 'ready', NULL, NULL, NULL, NULL, NULL, '2026-06-18 17:37:27'),
(10, 5, 7, 2, 42000.00, NULL, NULL, 'Warm', 84000.00, 'ready', NULL, NULL, NULL, NULL, NULL, '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `staff_code` varchar(30) DEFAULT NULL,
  `staff_name` varchar(150) NOT NULL,
  `staff_role` enum('waiter','cashier','barista','owner','manager','marketing','admin') NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `pin_hash` varchar(255) DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `branch_id`, `staff_code`, `staff_name`, `staff_role`, `phone_number`, `email`, `password_hash`, `pin_hash`, `last_login_at`, `status`, `created_at`) VALUES
(1, 1, 'WAIT001', 'Lan Waiter', 'waiter', '0911000001', 'waiter.cg@cafeconnect.test', '$2y$10$QwFwO4XjEW26RoZpdkEzQO3.z3XXBM07prVOQ4tJmeyDCHlWhBJP2', '$2y$10$iFWi.LVcgdaiG0OXn6JEP.4ltj2nA5Et1y.OLpgb.7te8H.ok/DMa', NULL, 'active', '2026-06-18 17:37:27'),
(2, 1, 'CASH001', 'Thu Cashier', 'cashier', '0911000002', 'cashier.cg@cafeconnect.test', '$2y$10$9QTM/PpwlcUt9PiJ0/7YUuoPy0ScvzpVRBXywgLT52ubkEAK9U/Cy', '$2y$10$eJQDWrlKk.JMLtWzWO.Gou9K19lly5lFOhrizvbBhm3/H08zLm2bu', NULL, 'active', '2026-06-18 17:37:27'),
(3, 1, 'BAR001', 'Nam Barista', 'barista', '0911000003', 'barista.cg@cafeconnect.test', '$2y$10$IMpvFn5IiRPSmFLQmHsxTu5OzEs40Niuu4xK/4W7D2aq8QAMdc9kK', '$2y$10$CZdu8/GpHphOnEEFWcWIAuP.ludGSFENmJBcdjZw5G3E9kmg4zo82', NULL, 'active', '2026-06-18 17:37:27'),
(4, 1, 'OWNER001', 'Quan Owner', 'owner', '0911000004', 'owner@cafeconnect.test', '$2y$10$0/F1n/505DoUWwBEms02DedyWxOSHgF8Zp7sZzIq7YKqr18o51oW6', '$2y$10$7IzwKkNVNq9Pr3UMs//Wzu/GBGy22NWCpa9zQPttLelxw7WHLwNMO', NULL, 'active', '2026-06-18 17:37:27'),
(5, 1, 'MKT001', 'Mai Marketing', 'marketing', '0911000005', 'marketing@cafeconnect.test', '$2y$10$7djsKTIFZfId5x9cAltr1OdDVYj38ITHwX47OELdkr8NZfJahVWRu', '$2y$10$ssYdjYMjlknoUaveNFcG3uVqlYG5SWlROjsFF1RUVPXsSFlSjDb9i', NULL, 'active', '2026-06-18 17:37:27'),
(6, 4, 'ADMIN001', 'Admin Cafe Connect', 'admin', '0911000006', 'admin@cafeconnect.test', '$2y$10$pmXHjW6UEfKqgikX7WPGIOUuYoaBgAAbcU5vOaTLE8JYAvBVRCvDS', '$2y$10$.ukkwldVixPyKxxS98kNIuVddF62mkCiwSuauWh3SsLwTDnFuvFbq', '2026-06-18 22:57:24', 'active', '2026-06-18 17:37:27'),
(7, 2, 'MGR001', 'Minh Manager', 'manager', '0911000007', 'manager.hk@cafeconnect.test', '$2y$10$36SgQt1h.SB1cS8FNkOhKOT9wFgnvR92ZMCndoJwJTuNoieuD5CdS', '$2y$10$nf.hLWOj.eCyYMcA6Hzwnu1I51wlP/TT77R9sn5Rr3r.QyUFvp3iu', NULL, 'active', '2026-06-18 17:37:27'),
(8, 3, 'CASH002', 'Chau Cashier', 'cashier', '0911000008', 'cashier.th@cafeconnect.test', '$2y$10$WdKVUvDaVE9pW6epCBczveq9CxzgzesEchhzsHDwXJO5qHWqFjq1K', '$2y$10$TAufgWV2APrepNznSgULh.bu3eFd08Vk0PkrAg16NRVghK9eTo4TW', NULL, 'active', '2026-06-18 17:37:27'),
(9, 2, 'WAIT002', 'Hoa Waiter HK', 'waiter', '0911000009', 'waiter.hk@cafeconnect.test', '$2y$10$8Hhoos5UEPC9FLOoW1u7MOv3IvbPSadUeOWhYmsgH1gQYumltX.Xu', '$2y$10$cNf0reppZqrprchE24/iM.lP6krnv3rdNOjWuz/MEOOk4WEhV.1um', NULL, 'active', '2026-06-18 17:37:27'),
(10, 2, 'BAR002', 'Phuc Barista HK', 'barista', '0911000010', 'barista.hk@cafeconnect.test', '$2y$10$rgUOdW4DO6w/aShnxxkgZ.NKJp6jVX7/WjDMQUKIGQQMT7BFjU3du', '$2y$10$DxIZqiVpwxpAWZ2mCMsI8uKiZN5mGtMiLS66hGweWD2TqAHMSQXt6', NULL, 'active', '2026-06-18 17:37:27'),
(11, 3, 'WAIT003', 'Linh Waiter TH', 'waiter', '0911000011', 'waiter.th@cafeconnect.test', '$2y$10$lXchGvKq4Qy0IgNNYEoQQedvGN6pTiits/L93HV3JKbENavk09yx2', '$2y$10$GupvlblyX6cZpU/G50et5.wq.eyow93saVrshnYCKsinwAkxyt9wS', NULL, 'active', '2026-06-18 17:37:27'),
(12, 3, 'BAR003', 'Son Barista TH', 'barista', '0911000012', 'barista.th@cafeconnect.test', '$2y$10$H7XMRp63sVPB954MvHywSOhrGJM2cYIClnd2dx4gZtApPj1RsqZVy', '$2y$10$G82LEC8jaMr1fdWEmbv0s.XniGYXgm95yzBdUwrSuud4XxUWdH9wu', NULL, 'active', '2026-06-18 17:37:27'),
(13, 3, 'MGR003', 'Hanh Manager TH', 'manager', '0911000013', 'manager.th@cafeconnect.test', '$2y$10$YHTnNUa1oKabxZuy8DCcx.OISsOiR7vW.6rzkUuIf9YOaxubfPLO6', '$2y$10$aFHlIwchNBPDVxBapjkWQOWDdkn4gLMgXKSSO6fSImig7cZry4g6O', NULL, 'active', '2026-06-18 17:37:27'),
(14, 4, 'WAIT004', 'An Waiter TVB', 'waiter', '0911000014', 'waiter.tvb@cafeconnect.test', '$2y$10$3M3EHTsq2mZ7BBnHeOEDp.AlOJ1AWWGOHh68orMPhEexzUzpaI4hW', '$2y$10$GG6VWifTGVyzibYGplLpQenuD1aPjWqzRIPAbQgM0ss.rxqzVGQW2', NULL, 'active', '2026-06-18 17:37:27'),
(15, 4, 'CASH004', 'Binh Cashier TVB', 'cashier', '0911000015', 'cashier.tvb@cafeconnect.test', '$2y$10$0/Ui52nD74o74F/hoSJDfeAa4UjQq6GoPyDk0pBt5a1Pkl3MrlMvC', '$2y$10$6z//8sPOrNBx06lbfig/R.W6w7BI9gwIKa95E42NoDDJZFeAVqoFq', NULL, 'active', '2026-06-18 17:37:27'),
(16, 4, 'BAR004', 'Khoa Barista TVB', 'barista', '0911000016', 'barista.tvb@cafeconnect.test', '$2y$10$gCX3sgHzTK7ZM.SeeX8sn.HgzN.8zWMdn.im5owq2OlXfSq7XvI3e', '$2y$10$XQSgQ6yVHgONKM/SPPThc.fDfYTvwVjbkk6R.gygloe5lyQOZn3he', NULL, 'active', '2026-06-18 17:37:27'),
(17, 4, 'MGR004', 'Trang Manager TVB', 'manager', '0911000017', 'manager.tvb@cafeconnect.test', '$2y$10$1HnbrZn6s12QZncRb.usN.uWBe5P1IuRP6GiBIR9IDFi8yVIjdVPu', '$2y$10$PXAuEoF6iDazWYdx4DFwyeLaPwWb6eyxp/YMHUjSujBLyPkNTJ.9m', NULL, 'active', '2026-06-18 17:37:27'),
(18, 2, 'CASH003', 'Vy Cashier HK', 'cashier', '0911000018', 'cashier.hk@cafeconnect.test', '$2y$10$AyCBHLZ5JqoQFYNPR8yh3uHo/ItV.nICCNtkddvVFqTeA.ocI8pmG', '$2y$10$8g97wu/./rac7ayJEyu8CeAOgGjY0gKPmhrbExMZwdXgSdYrKVrLK', NULL, 'active', '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `staff_login_sessions`
--

CREATE TABLE `staff_login_sessions` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `auth_token` varchar(80) NOT NULL,
  `logged_in_at` datetime NOT NULL,
  `last_seen_at` datetime DEFAULT NULL,
  `logged_out_at` datetime DEFAULT NULL,
  `status` enum('active','logged_out','expired') NOT NULL DEFAULT 'active',
  `login_ip` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_login_sessions`
--

INSERT INTO `staff_login_sessions` (`id`, `staff_id`, `auth_token`, `logged_in_at`, `last_seen_at`, `logged_out_at`, `status`, `login_ip`, `user_agent`) VALUES
(1, 6, '5f72447064bf94c3b08f3a769e38b13f53f715cbbaac4a4d', '2026-06-18 17:43:15', '2026-06-18 17:50:43', '2026-06-18 17:50:43', 'logged_out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
(2, 6, '3f5d54ddbcdac7827be171894cfb57373eeb46a20863e351', '2026-06-18 17:50:50', '2026-06-18 17:51:06', '2026-06-18 17:51:06', 'logged_out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
(3, 6, 'dba5a2ad27d115f2a8860dbc8676f6ec63cfa23f6d6451ed', '2026-06-18 17:51:12', '2026-06-18 17:52:02', '2026-06-18 17:52:02', 'logged_out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
(4, 6, 'e1b3b7dfdb2ed951887e479c40a84f034e2c922ca7ede78e', '2026-06-18 17:52:02', '2026-06-18 17:52:11', '2026-06-18 17:52:11', 'logged_out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
(5, 6, '865e4c0b0187bb43f1646fcc72af20c7ac832abf73bd9951', '2026-06-18 17:54:09', '2026-06-18 17:54:18', '2026-06-18 17:54:18', 'logged_out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
(6, 6, 'c311e1c7ccc0843c317ac1c5deb2960defa85dfc170b09da', '2026-06-18 18:13:32', '2026-06-18 22:57:22', '2026-06-18 22:57:22', 'logged_out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
(7, 6, '487ed7911d36c1edb14e43dcda1ddd2facb2671321622f71', '2026-06-18 22:57:22', '2026-06-18 22:58:08', '2026-06-18 22:58:08', 'expired', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `staff_shifts`
--

CREATE TABLE `staff_shifts` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `shift_name` varchar(80) NOT NULL,
  `starts_at` time NOT NULL,
  `ends_at` time NOT NULL,
  `work_date` date DEFAULT NULL,
  `status` enum('active','closed') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_shifts`
--

INSERT INTO `staff_shifts` (`id`, `staff_id`, `shift_name`, `starts_at`, `ends_at`, `work_date`, `status`, `created_at`) VALUES
(1, 1, 'Morning floor', '07:00:00', '15:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27'),
(2, 2, 'Morning cashier', '07:00:00', '15:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27'),
(3, 3, 'Bar station', '07:00:00', '15:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27'),
(4, 4, 'Owner review', '09:00:00', '18:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27'),
(5, 5, 'Campaign desk', '09:00:00', '17:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27'),
(6, 6, 'Admin support', '09:00:00', '18:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27'),
(7, 7, 'Manager shift', '13:00:00', '21:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27'),
(8, 8, 'Evening cashier', '13:00:00', '21:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27'),
(9, 9, 'Hoan Kiem floor', '08:00:00', '16:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27'),
(10, 10, 'Hoan Kiem bar', '08:00:00', '16:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27'),
(11, 11, 'Tay Ho floor', '09:00:00', '17:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27'),
(12, 12, 'Tay Ho bar', '09:00:00', '17:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27'),
(13, 13, 'Tay Ho manager', '13:00:00', '21:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27'),
(14, 14, 'Trinh Van Bo floor', '07:00:00', '15:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27'),
(15, 15, 'Trinh Van Bo cashier', '07:00:00', '15:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27'),
(16, 16, 'Trinh Van Bo bar', '07:00:00', '15:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27'),
(17, 17, 'Trinh Van Bo manager', '13:00:00', '21:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27'),
(18, 18, 'Hoan Kiem cashier', '13:00:00', '21:00:00', '2026-05-13', 'active', '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `movement_code` varchar(50) NOT NULL,
  `material_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `pos_session_id` int(11) DEFAULT NULL,
  `movement_type` enum('import','sales_export','waste_export','adjustment') NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `supplier_name` varchar(150) DEFAULT NULL,
  `batch_code` varchar(80) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `movement_code`, `material_id`, `branch_id`, `staff_id`, `pos_session_id`, `movement_type`, `quantity`, `unit_cost`, `total_amount`, `supplier_name`, `batch_code`, `expiry_date`, `note`, `created_at`) VALUES
(1, 'IM-001', 1, 1, 7, NULL, 'import', 20.00, 0.00, 3800000.00, NULL, NULL, NULL, 'Weekly bean import.', '2026-05-12 08:00:00'),
(2, 'SA-002', 3, 1, 3, 3, 'sales_export', 12.00, 0.00, 0.00, NULL, NULL, NULL, 'Milk used in morning shift.', '2026-05-13 12:00:00'),
(3, 'WA-003', 5, 1, 7, NULL, 'waste_export', 2.00, 0.00, 0.00, NULL, NULL, NULL, 'Damaged pastry packs.', '2026-05-13 13:30:00'),
(4, 'IM-004', 1, 2, 7, NULL, 'import', 12.00, 0.00, 2280000.00, NULL, NULL, NULL, 'Hoan Kiem bean replenishment.', '2026-05-12 08:20:00'),
(5, 'SA-005', 3, 2, 10, NULL, 'sales_export', 8.00, 0.00, 0.00, NULL, NULL, NULL, 'Milk used by Hoan Kiem bar.', '2026-05-13 11:45:00'),
(6, 'IM-006', 4, 3, 13, NULL, 'import', 6.00, 0.00, 960000.00, NULL, NULL, NULL, 'Tay Ho tea stock import.', '2026-05-12 09:10:00'),
(7, 'WA-007', 3, 3, 12, NULL, 'waste_export', 3.00, 0.00, 0.00, NULL, NULL, NULL, 'Expired milk bottles.', '2026-05-13 14:00:00'),
(8, 'IM-008', 2, 4, 17, NULL, 'import', 18.00, 0.00, 2160000.00, NULL, NULL, NULL, 'Trinh Van Bo robusta import.', '2026-05-12 08:45:00'),
(9, 'SA-009', 1, 4, 16, NULL, 'sales_export', 10.00, 0.00, 0.00, NULL, NULL, NULL, 'Arabica used by Trinh Van Bo bar.', '2026-05-13 12:25:00'),
(10, 'SALE-7-2', 2, 4, 2, NULL, 'sales_export', 0.22, 0.00, 0.00, NULL, NULL, NULL, 'Auto consume for invoice #7 - Robusta beans', '2026-06-18 17:52:57'),
(11, 'SALE-8-1', 1, 4, 2, NULL, 'sales_export', 0.18, 0.00, 0.00, NULL, NULL, NULL, 'Auto consume for invoice #8 - Arabica beans', '2026-06-18 17:54:02'),
(12, 'SALE-8-3', 3, 4, 2, NULL, 'sales_export', 1.80, 0.00, 0.00, NULL, NULL, NULL, 'Auto consume for invoice #8 - Fresh milk', '2026-06-18 17:54:02'),
(13, 'SALE-9-1', 1, 4, 2, NULL, 'sales_export', 0.02, 0.00, 0.00, NULL, NULL, NULL, 'Auto consume for invoice #9 - Arabica beans', '2026-06-18 18:12:53'),
(14, 'SALE-9-3', 3, 4, 2, NULL, 'sales_export', 0.18, 0.00, 0.00, NULL, NULL, NULL, 'Auto consume for invoice #9 - Fresh milk', '2026-06-18 18:12:53'),
(15, 'SALE-9-4', 4, 4, 2, NULL, 'sales_export', 0.01, 0.00, 0.00, NULL, NULL, NULL, 'Auto consume for invoice #9 - Tea leaves', '2026-06-18 18:12:53'),
(16, 'SALE-10-1', 1, 4, 2, NULL, 'sales_export', 0.02, 0.00, 0.00, NULL, NULL, NULL, 'Auto consume for invoice #10 - Arabica beans', '2026-06-18 18:12:53'),
(17, 'SALE-10-3', 3, 4, 2, NULL, 'sales_export', 0.18, 0.00, 0.00, NULL, NULL, NULL, 'Auto consume for invoice #10 - Fresh milk', '2026-06-18 18:12:53'),
(18, 'SALE-10-4', 4, 4, 2, NULL, 'sales_export', 0.01, 0.00, 0.00, NULL, NULL, NULL, 'Auto consume for invoice #10 - Tea leaves', '2026-06-18 18:12:53');

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL,
  `voucher_code` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `promotion_id` int(11) NOT NULL,
  `release_date` date NOT NULL,
  `expiration_date` date NOT NULL,
  `status` enum('issued','active','reserved','redeemed','expired','cancelled') NOT NULL DEFAULT 'issued',
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vouchers`
--

INSERT INTO `vouchers` (`id`, `voucher_code`, `customer_id`, `promotion_id`, `release_date`, `expiration_date`, `status`, `used_at`, `created_at`) VALUES
(1, 'BDAY-NAN-001', 1, 1, '2026-05-01', '2026-05-31', 'active', NULL, '2026-06-18 17:37:27'),
(2, 'GOLD-NAN-002', 1, 2, '2026-05-01', '2026-06-15', 'issued', NULL, '2026-06-18 17:37:27'),
(3, 'WEB-NAN-003', 1, 3, '2026-05-01', '2026-06-30', 'redeemed', '2026-05-10 09:30:00', '2026-06-18 17:37:27'),
(4, 'GOLD-PDU-004', 4, 2, '2026-05-01', '2026-06-15', 'active', NULL, '2026-06-18 17:37:27'),
(5, 'BDAY-HGI-005', 5, 1, '2026-05-01', '2026-05-31', 'issued', NULL, '2026-06-18 17:37:27'),
(6, 'REAC-VHO-006', 6, 4, '2026-05-01', '2026-06-15', 'active', NULL, '2026-06-18 17:37:27'),
(7, 'WEB-TBI-007', 2, 3, '2026-05-01', '2026-06-30', 'issued', NULL, '2026-06-18 17:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `website_orders`
--

CREATE TABLE `website_orders` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `fulfillment_type` enum('pickup','delivery') NOT NULL DEFAULT 'pickup',
  `order_status` enum('pending','paid','preparing','ready','delivering','completed','cancelled') NOT NULL DEFAULT 'paid',
  `receiver_email` varchar(150) DEFAULT NULL,
  `receiver_name` varchar(150) DEFAULT NULL,
  `receiver_phone` varchar(20) DEFAULT NULL,
  `delivery_address` varchar(255) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `district` varchar(120) DEFAULT NULL,
  `ward` varchar(120) DEFAULT NULL,
  `customer_note` varchar(255) DEFAULT NULL,
  `requested_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `website_orders`
--

INSERT INTO `website_orders` (`id`, `invoice_id`, `customer_id`, `fulfillment_type`, `order_status`, `receiver_email`, `receiver_name`, `receiver_phone`, `delivery_address`, `city`, `district`, `ward`, `customer_note`, `requested_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'pickup', 'completed', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Seeded from sample invoice', '2026-05-10 09:32:00', '2026-05-10 09:32:00', '2026-06-18 17:37:27'),
(2, 4, 5, 'delivery', 'completed', 'sample.customer@example.test', 'Khách giao hàng mẫu', '0900000000', 'Sample delivery address, Phường mẫu, Quận mẫu, Hà Nội', 'Hà Nội', 'Quận mẫu', 'Phường mẫu', 'Seeded from sample invoice', '2026-05-13 08:43:00', '2026-05-13 08:43:00', '2026-06-18 17:37:27'),
(3, 6, 4, 'pickup', 'paid', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Seeded from sample invoice', NULL, '2026-05-13 11:15:00', '2026-06-18 17:52:09'),
(4, 7, 1, 'pickup', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-18 17:52:57', '2026-06-18 17:52:57'),
(5, 8, 1, 'pickup', 'paid', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-18 17:54:02', '2026-06-18 17:54:16'),
(6, 9, 1, 'pickup', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-18 18:12:53', '2026-06-18 18:12:53'),
(7, 10, 1, 'pickup', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-18 18:12:53', '2026-06-18 18:12:53');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_logs_actor` (`actor_type`,`actor_id`,`created_at`),
  ADD KEY `idx_audit_logs_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_audit_logs_action_date` (`action`,`created_at`);

--
-- Indexes for table `auth_lockouts`
--
ALTER TABLE `auth_lockouts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_auth_lockouts_scope_identity` (`scope`,`identity_hash`),
  ADD KEY `idx_auth_lockouts_locked_until` (`locked_until`),
  ADD KEY `idx_auth_lockouts_scope_updated` (`scope`,`updated_at`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_branches_name` (`branch_name`);

--
-- Indexes for table `branch_inventory`
--
ALTER TABLE `branch_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_branch_inventory_branch_product` (`branch_id`,`product_id`),
  ADD KEY `fk_branch_inventory_product` (`product_id`);

--
-- Indexes for table `branch_material_inventory`
--
ALTER TABLE `branch_material_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_branch_material_inventory` (`branch_id`,`material_id`),
  ADD KEY `idx_branch_material_inventory_material` (`material_id`);

--
-- Indexes for table `campaign_recipients`
--
ALTER TABLE `campaign_recipients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_campaign_recipients_email_customer` (`marketing_email_id`,`customer_id`),
  ADD KEY `idx_campaign_recipients_status` (`delivery_status`),
  ADD KEY `fk_campaign_recipients_customer` (`customer_id`),
  ADD KEY `fk_campaign_recipients_voucher` (`voucher_id`);

--
-- Indexes for table `cash_transactions`
--
ALTER TABLE `cash_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cash_transactions_created` (`created_at`),
  ADD KEY `fk_cash_transactions_branch` (`branch_id`),
  ADD KEY `fk_cash_transactions_staff` (`staff_id`),
  ADD KEY `fk_cash_transactions_session` (`pos_session_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_customers_phone` (`phone_number`),
  ADD UNIQUE KEY `uq_customers_email` (`email`),
  ADD KEY `idx_customers_tier` (`membership_tier_id`);

--
-- Indexes for table `customer_favorites`
--
ALTER TABLE `customer_favorites`
  ADD PRIMARY KEY (`customer_id`,`product_id`),
  ADD KEY `fk_customer_favorites_product` (`product_id`);

--
-- Indexes for table `customer_interactions`
--
ALTER TABLE `customer_interactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_interactions_customer_date` (`customer_id`,`created_at`),
  ADD KEY `fk_customer_interactions_staff` (`staff_id`),
  ADD KEY `fk_customer_interactions_invoice` (`invoice_id`);

--
-- Indexes for table `customer_password_resets`
--
ALTER TABLE `customer_password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_customer_password_resets_token` (`token_hash`),
  ADD KEY `idx_customer_password_resets_customer` (`customer_id`),
  ADD KEY `idx_customer_password_resets_expires` (`expires_at`);

--
-- Indexes for table `customer_reviews`
--
ALTER TABLE `customer_reviews`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_segments`
--
ALTER TABLE `customer_segments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_customer_segments_code` (`segment_code`);

--
-- Indexes for table `customer_segment_memberships`
--
ALTER TABLE `customer_segment_memberships`
  ADD PRIMARY KEY (`customer_id`,`segment_id`),
  ADD KEY `idx_customer_segments_segment` (`segment_id`);

--
-- Indexes for table `dining_tables`
--
ALTER TABLE `dining_tables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dining_tables_branch_name` (`branch_id`,`table_name`),
  ADD KEY `idx_dining_tables_status` (`status`);

--
-- Indexes for table `inventory_materials`
--
ALTER TABLE `inventory_materials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_materials_name` (`material_name`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invoices_date_branch` (`invoice_date`,`branch_id`),
  ADD KEY `idx_invoices_customer_date` (`customer_id`,`invoice_date`),
  ADD KEY `idx_invoices_channel_date` (`sales_channel`,`invoice_date`),
  ADD KEY `idx_invoices_service_order` (`service_order_id`),
  ADD KEY `idx_invoices_pos_session_paid` (`pos_session_id`,`paid_at`),
  ADD KEY `fk_invoices_branch` (`branch_id`),
  ADD KEY `fk_invoices_staff` (`staff_id`),
  ADD KEY `fk_invoices_voucher` (`voucher_id`);

--
-- Indexes for table `invoice_details`
--
ALTER TABLE `invoice_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invoice_details_product` (`product_id`),
  ADD KEY `fk_invoice_details_invoice` (`invoice_id`);

--
-- Indexes for table `invoice_refunds`
--
ALTER TABLE `invoice_refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invoice_refunds_invoice` (`invoice_id`),
  ADD KEY `idx_invoice_refunds_staff_date` (`staff_id`,`created_at`),
  ADD KEY `fk_invoice_refunds_session` (`pos_session_id`);

--
-- Indexes for table `loyalty_point_transactions`
--
ALTER TABLE `loyalty_point_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loyalty_customer_date` (`customer_id`,`created_at`),
  ADD KEY `fk_loyalty_point_transactions_invoice` (`invoice_id`);

--
-- Indexes for table `marketing_emails`
--
ALTER TABLE `marketing_emails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_marketing_emails_promotion_status` (`promotion_id`,`status`),
  ADD KEY `fk_marketing_emails_staff` (`created_by_staff_id`);

--
-- Indexes for table `membership_tiers`
--
ALTER TABLE `membership_tiers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_membership_tiers_name` (`tier_name`);

--
-- Indexes for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_newsletter_subscribers_email` (`email`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payments_invoice_method` (`invoice_id`,`payment_method`);

--
-- Indexes for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payment_transactions_order` (`provider_order_id`),
  ADD KEY `idx_payment_transactions_invoice` (`invoice_id`,`created_at`),
  ADD KEY `idx_payment_transactions_payment` (`payment_id`);

--
-- Indexes for table `pos_activity_logs`
--
ALTER TABLE `pos_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pos_activity_session_action` (`pos_session_id`,`action_type`,`created_at`),
  ADD KEY `idx_pos_activity_staff_date` (`staff_id`,`created_at`),
  ADD KEY `fk_pos_activity_product` (`product_id`);

--
-- Indexes for table `pos_sessions`
--
ALTER TABLE `pos_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pos_sessions_token` (`session_token`),
  ADD KEY `idx_pos_sessions_staff_status` (`staff_id`,`status`),
  ADD KEY `idx_pos_sessions_branch_date` (`branch_id`,`opened_at`),
  ADD KEY `idx_pos_sessions_auth_session` (`staff_login_session_id`),
  ADD KEY `fk_pos_sessions_shift` (`shift_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_products_name` (`product_name`),
  ADD KEY `idx_products_category_status` (`category`,`status`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_product_categories_code` (`category_code`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_images_primary` (`product_id`,`is_primary`);

--
-- Indexes for table `product_size_prices`
--
ALTER TABLE `product_size_prices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_product_size_prices_product_size` (`product_id`,`size`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_promotions_claim_code` (`claim_code`),
  ADD KEY `idx_promotions_status_date` (`status`,`start_date`,`end_date`);

--
-- Indexes for table `receipt_print_logs`
--
ALTER TABLE `receipt_print_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_receipt_print_logs_invoice` (`invoice_id`,`printed_at`),
  ADD KEY `fk_receipt_print_logs_staff` (`staff_id`),
  ADD KEY `fk_receipt_print_logs_session` (`pos_session_id`);

--
-- Indexes for table `recipes`
--
ALTER TABLE `recipes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_recipes_product` (`product_id`);

--
-- Indexes for table `recipe_items`
--
ALTER TABLE `recipe_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_recipe_items_recipe_material` (`recipe_id`,`material_id`),
  ADD KEY `idx_recipe_items_material` (`material_id`);

--
-- Indexes for table `schema_migrations`
--
ALTER TABLE `schema_migrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_schema_migrations_name` (`migration_name`);

--
-- Indexes for table `service_orders`
--
ALTER TABLE `service_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_service_orders_code` (`order_code`),
  ADD KEY `idx_service_orders_active` (`status`,`created_at`),
  ADD KEY `fk_service_orders_branch` (`branch_id`),
  ADD KEY `fk_service_orders_table` (`table_id`),
  ADD KEY `fk_service_orders_customer` (`customer_id`),
  ADD KEY `fk_service_orders_waiter` (`waiter_id`),
  ADD KEY `fk_service_orders_cashier` (`cashier_id`);

--
-- Indexes for table `service_order_items`
--
ALTER TABLE `service_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service_order_items_order` (`service_order_id`),
  ADD KEY `idx_service_order_items_kitchen` (`kitchen_status`),
  ADD KEY `idx_service_order_items_prepared_by` (`prepared_by_staff_id`,`ready_at`),
  ADD KEY `fk_service_order_items_product` (`product_id`),
  ADD KEY `fk_service_order_items_prepared_session` (`prepared_by_session_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_staff_code` (`staff_code`),
  ADD UNIQUE KEY `uq_staff_email` (`email`),
  ADD KEY `idx_staff_branch_role` (`branch_id`,`staff_role`);

--
-- Indexes for table `staff_login_sessions`
--
ALTER TABLE `staff_login_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_staff_login_sessions_token` (`auth_token`),
  ADD KEY `idx_staff_login_sessions_staff_status` (`staff_id`,`status`);

--
-- Indexes for table `staff_shifts`
--
ALTER TABLE `staff_shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_shifts_staff_status` (`staff_id`,`status`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_stock_movements_code` (`movement_code`),
  ADD KEY `idx_stock_movements_created` (`created_at`),
  ADD KEY `fk_stock_movements_material` (`material_id`),
  ADD KEY `fk_stock_movements_branch` (`branch_id`),
  ADD KEY `fk_stock_movements_staff` (`staff_id`),
  ADD KEY `fk_stock_movements_session` (`pos_session_id`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vouchers_code` (`voucher_code`),
  ADD KEY `idx_vouchers_customer_status` (`customer_id`,`status`),
  ADD KEY `idx_vouchers_promotion_status` (`promotion_id`,`status`);

--
-- Indexes for table `website_orders`
--
ALTER TABLE `website_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_website_orders_invoice` (`invoice_id`),
  ADD KEY `idx_website_orders_customer_status` (`customer_id`,`order_status`),
  ADD KEY `idx_website_orders_status_created` (`order_status`,`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `auth_lockouts`
--
ALTER TABLE `auth_lockouts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `branch_inventory`
--
ALTER TABLE `branch_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `branch_material_inventory`
--
ALTER TABLE `branch_material_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `campaign_recipients`
--
ALTER TABLE `campaign_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `cash_transactions`
--
ALTER TABLE `cash_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customer_interactions`
--
ALTER TABLE `customer_interactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `customer_password_resets`
--
ALTER TABLE `customer_password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_reviews`
--
ALTER TABLE `customer_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `customer_segments`
--
ALTER TABLE `customer_segments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `dining_tables`
--
ALTER TABLE `dining_tables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `inventory_materials`
--
ALTER TABLE `inventory_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `invoice_details`
--
ALTER TABLE `invoice_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `invoice_refunds`
--
ALTER TABLE `invoice_refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loyalty_point_transactions`
--
ALTER TABLE `loyalty_point_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `marketing_emails`
--
ALTER TABLE `marketing_emails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `membership_tiers`
--
ALTER TABLE `membership_tiers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pos_activity_logs`
--
ALTER TABLE `pos_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `pos_sessions`
--
ALTER TABLE `pos_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `product_size_prices`
--
ALTER TABLE `product_size_prices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `receipt_print_logs`
--
ALTER TABLE `receipt_print_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recipes`
--
ALTER TABLE `recipes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `recipe_items`
--
ALTER TABLE `recipe_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `schema_migrations`
--
ALTER TABLE `schema_migrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `service_orders`
--
ALTER TABLE `service_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `service_order_items`
--
ALTER TABLE `service_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `staff_login_sessions`
--
ALTER TABLE `staff_login_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `staff_shifts`
--
ALTER TABLE `staff_shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `website_orders`
--
ALTER TABLE `website_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `branch_inventory`
--
ALTER TABLE `branch_inventory`
  ADD CONSTRAINT `fk_branch_inventory_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_branch_inventory_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `branch_material_inventory`
--
ALTER TABLE `branch_material_inventory`
  ADD CONSTRAINT `fk_branch_material_inventory_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_branch_material_inventory_material` FOREIGN KEY (`material_id`) REFERENCES `inventory_materials` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `campaign_recipients`
--
ALTER TABLE `campaign_recipients`
  ADD CONSTRAINT `fk_campaign_recipients_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_campaign_recipients_email` FOREIGN KEY (`marketing_email_id`) REFERENCES `marketing_emails` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_campaign_recipients_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `cash_transactions`
--
ALTER TABLE `cash_transactions`
  ADD CONSTRAINT `fk_cash_transactions_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cash_transactions_session` FOREIGN KEY (`pos_session_id`) REFERENCES `pos_sessions` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cash_transactions_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customers_membership_tier` FOREIGN KEY (`membership_tier_id`) REFERENCES `membership_tiers` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `customer_favorites`
--
ALTER TABLE `customer_favorites`
  ADD CONSTRAINT `fk_customer_favorites_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_customer_favorites_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `customer_interactions`
--
ALTER TABLE `customer_interactions`
  ADD CONSTRAINT `fk_customer_interactions_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_customer_interactions_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_customer_interactions_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `customer_password_resets`
--
ALTER TABLE `customer_password_resets`
  ADD CONSTRAINT `fk_customer_password_resets_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `customer_segment_memberships`
--
ALTER TABLE `customer_segment_memberships`
  ADD CONSTRAINT `fk_customer_segment_memberships_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_customer_segment_memberships_segment` FOREIGN KEY (`segment_id`) REFERENCES `customer_segments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `dining_tables`
--
ALTER TABLE `dining_tables`
  ADD CONSTRAINT `fk_dining_tables_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoices_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_invoices_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_invoices_pos_session` FOREIGN KEY (`pos_session_id`) REFERENCES `pos_sessions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_invoices_service_order` FOREIGN KEY (`service_order_id`) REFERENCES `service_orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_invoices_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_invoices_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `invoice_details`
--
ALTER TABLE `invoice_details`
  ADD CONSTRAINT `fk_invoice_details_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_invoice_details_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `invoice_refunds`
--
ALTER TABLE `invoice_refunds`
  ADD CONSTRAINT `fk_invoice_refunds_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_invoice_refunds_session` FOREIGN KEY (`pos_session_id`) REFERENCES `pos_sessions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_invoice_refunds_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `loyalty_point_transactions`
--
ALTER TABLE `loyalty_point_transactions`
  ADD CONSTRAINT `fk_loyalty_point_transactions_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_loyalty_point_transactions_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `marketing_emails`
--
ALTER TABLE `marketing_emails`
  ADD CONSTRAINT `fk_marketing_emails_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_marketing_emails_staff` FOREIGN KEY (`created_by_staff_id`) REFERENCES `staff` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD CONSTRAINT `fk_payment_transactions_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payment_transactions_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `pos_activity_logs`
--
ALTER TABLE `pos_activity_logs`
  ADD CONSTRAINT `fk_pos_activity_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pos_activity_session` FOREIGN KEY (`pos_session_id`) REFERENCES `pos_sessions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pos_activity_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `pos_sessions`
--
ALTER TABLE `pos_sessions`
  ADD CONSTRAINT `fk_pos_sessions_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pos_sessions_shift` FOREIGN KEY (`shift_id`) REFERENCES `staff_shifts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pos_sessions_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pos_sessions_staff_login_session` FOREIGN KEY (`staff_login_session_id`) REFERENCES `staff_login_sessions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category`) REFERENCES `product_categories` (`category_code`) ON UPDATE CASCADE;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_size_prices`
--
ALTER TABLE `product_size_prices`
  ADD CONSTRAINT `fk_product_size_prices_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `receipt_print_logs`
--
ALTER TABLE `receipt_print_logs`
  ADD CONSTRAINT `fk_receipt_print_logs_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_receipt_print_logs_session` FOREIGN KEY (`pos_session_id`) REFERENCES `pos_sessions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_receipt_print_logs_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `recipes`
--
ALTER TABLE `recipes`
  ADD CONSTRAINT `fk_recipes_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `recipe_items`
--
ALTER TABLE `recipe_items`
  ADD CONSTRAINT `fk_recipe_items_material` FOREIGN KEY (`material_id`) REFERENCES `inventory_materials` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_recipe_items_recipe` FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `service_orders`
--
ALTER TABLE `service_orders`
  ADD CONSTRAINT `fk_service_orders_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_service_orders_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_service_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_service_orders_table` FOREIGN KEY (`table_id`) REFERENCES `dining_tables` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_service_orders_waiter` FOREIGN KEY (`waiter_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `service_order_items`
--
ALTER TABLE `service_order_items`
  ADD CONSTRAINT `fk_service_order_items_order` FOREIGN KEY (`service_order_id`) REFERENCES `service_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_service_order_items_prepared_session` FOREIGN KEY (`prepared_by_session_id`) REFERENCES `pos_sessions` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_service_order_items_prepared_staff` FOREIGN KEY (`prepared_by_staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_service_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `fk_staff_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `staff_login_sessions`
--
ALTER TABLE `staff_login_sessions`
  ADD CONSTRAINT `fk_staff_login_sessions_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `staff_shifts`
--
ALTER TABLE `staff_shifts`
  ADD CONSTRAINT `fk_staff_shifts_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_stock_movements_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_movements_material` FOREIGN KEY (`material_id`) REFERENCES `inventory_materials` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_movements_session` FOREIGN KEY (`pos_session_id`) REFERENCES `pos_sessions` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_movements_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD CONSTRAINT `fk_vouchers_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_vouchers_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `website_orders`
--
ALTER TABLE `website_orders`
  ADD CONSTRAINT `fk_website_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_website_orders_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
