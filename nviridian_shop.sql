-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 28, 2025 at 05:29 PM
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
-- Database: `nviridian_shop`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `add_product` (IN `p_name` VARCHAR(255), IN `p_description` TEXT, IN `p_price` DECIMAL(10,2), IN `p_stock` INT, IN `p_image` VARCHAR(255))   BEGIN
    INSERT INTO products (name, description, price, stock, image)
    VALUES (p_name, p_description, p_price, p_stock, p_image);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `CancelUserOrders` (IN `p_user_id` INT)   BEGIN
    -- First delete related order_items
    DELETE FROM order_items 
    WHERE order_id IN (
        SELECT id FROM orders WHERE user_id = p_user_id
    );

    -- Then delete the orders
    DELETE FROM orders WHERE user_id = p_user_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `convert_total_by_currency` (IN `php_amount` DECIMAL(10,2), IN `currency_code` VARCHAR(10), OUT `converted_amount` DECIMAL(10,2), OUT `exchange_rate` DECIMAL(10,4))   BEGIN
    CASE currency_code
        WHEN 'PHP' THEN SET exchange_rate = 1.00;
        WHEN 'USD' THEN SET exchange_rate = 1 / 56.50;
        WHEN 'KRW' THEN SET exchange_rate = 1 / 0.041;
        WHEN 'YEN' THEN SET exchange_rate = 1 / 0.38;
        WHEN 'THB' THEN SET exchange_rate = 1 / 1.74;
        WHEN 'CNY' THEN SET exchange_rate = 1 / 7.88;
        ELSE SET exchange_rate = 1.00;
    END CASE;

    SET converted_amount = php_amount * exchange_rate;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `delete_product` (IN `p_id` INT)   BEGIN
    DELETE FROM products WHERE id = p_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `modify_product` (IN `p_id` INT, IN `p_name` VARCHAR(255), IN `p_description` TEXT, IN `p_price` DECIMAL(10,2), IN `p_stock` INT, IN `p_image` VARCHAR(255))   BEGIN
    UPDATE products
    SET name = p_name,
        description = p_description,
        price = p_price,
        stock = p_stock,
        image = p_image
    WHERE id = p_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `order_create_logs`
--

CREATE TABLE `order_create_logs` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_create_logs`
--

INSERT INTO `order_create_logs` (`id`, `order_id`, `user_id`, `created_at`) VALUES
(1, 52, 6, '2025-07-28 19:36:48'),
(2, 53, 7, '2025-07-28 21:24:45'),
(3, 54, 7, '2025-07-28 21:25:42'),
(4, 55, 7, '2025-07-28 21:25:59'),
(5, 56, 7, '2025-07-28 21:26:28'),
(6, 57, 7, '2025-07-28 21:27:50'),
(7, 58, 7, '2025-07-28 21:28:09');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 1, 1, 5, 150000.00),
(2, 2, 1, 1, 150000.00),
(3, 3, 1, 1, 150000.00),
(4, 4, 1, 4, 150000.00),
(5, 5, 9, 1, 1809.00),
(6, 5, 13, 2, 3000.00),
(56, 52, 10, 1, 500.00),
(59, 54, 10, 1, 500.00),
(60, 55, 10, 1, 500.00),
(61, 56, 10, 1, 500.00),
(62, 57, 10, 1, 500.00),
(63, 58, 6, 1, 7000.00);

-- --------------------------------------------------------

--
-- Table structure for table `price_logs`
--

CREATE TABLE `price_logs` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `old_price` decimal(10,2) DEFAULT NULL,
  `new_price` decimal(10,2) DEFAULT NULL,
  `change_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `price_logs`
--

INSERT INTO `price_logs` (`id`, `product_id`, `old_price`, `new_price`, `change_time`) VALUES
(1, 13, 3000.00, 30020.00, '2025-07-28 19:02:46'),
(2, 8, 5000.00, 50020.00, '2025-07-28 19:03:02'),
(3, 10, 500.00, 500.00, '2025-07-28 19:36:48'),
(4, 11, 54990.00, 514990.00, '2025-07-28 19:39:58'),
(5, 11, 514990.00, 514990.00, '2025-07-28 19:45:01'),
(6, 11, 514990.00, 514990.00, '2025-07-28 19:45:16'),
(7, 9, 1809.00, 1809.00, '2025-07-28 21:24:45'),
(8, 10, 500.00, 500.00, '2025-07-28 21:24:45'),
(9, 10, 500.00, 500.00, '2025-07-28 21:25:42'),
(10, 10, 500.00, 500.00, '2025-07-28 21:25:59'),
(11, 10, 500.00, 500.00, '2025-07-28 21:26:28'),
(12, 10, 500.00, 500.00, '2025-07-28 21:27:50'),
(13, 6, 7000.00, 7000.00, '2025-07-28 21:28:09');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `stock`, `image`) VALUES
(6, 'Wireless keyboard', 'leek, responsive, and built for comfort. Features low-profile keys, reliable wireless connection, and impressive battery life—ideal for efficient typing wherever you are.', 7000.00, 26, 'product_6872dacebffaf0.75700177.jpg'),
(7, 'Acer', 'Powerful and reliable for work, school, or play. Sleek design, fast performance, and long battery life at a great value.', 80000.00, 3, 'product_6872dbf89d2a67.13215666.jpg'),
(8, 'Headset', 'Comfortable to wear, clear sound, and easy to use. Great for calls, music, or gaming. Just plug in or connect with Bluetooth.', 50020.00, 25, 'product_6872dcc0351d82.69723116.jpg'),
(9, 'Bavin Powerbank', 'Fast charging with USB‑C Power Delivery. Good for phones and tablets, small enough to carry.', 1809.00, 51, 'product_6872dd0761f7d0.77593932.jpg'),
(10, 'Emeet 1080P Webcam with Microphone', 'Full HD 1080p at 30 fps with sharp fixed-focus and a wide 90° field-of-view—great for clear, natural video in well-lit rooms', 500.00, 6, 'product_6872dd92e744c2.80511758.jpg'),
(11, 'iPhone 16', 'Fast. Great for photos, videos, and smooth everyday use.', 514990.00, 30, 'product_6872ddfca5c2a8.18796598.jpg');

--
-- Triggers `products`
--
DELIMITER $$
CREATE TRIGGER `log_price_change` AFTER UPDATE ON `products` FOR EACH ROW BEGIN
  INSERT INTO price_logs (product_id, old_price, new_price, change_time)
  VALUES (OLD.id, OLD.price, NEW.price, NOW());
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `log_product_add` AFTER INSERT ON `products` FOR EACH ROW BEGIN
  INSERT INTO product_add_logs (product_id, name, added_time)
  VALUES (NEW.id, NEW.name, NOW());
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `log_product_deletion` AFTER DELETE ON `products` FOR EACH ROW BEGIN
  INSERT INTO product_delete_logs (product_id, name, deleted_time)
  VALUES (OLD.id, OLD.name, NOW());
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `log_stock_change` AFTER UPDATE ON `products` FOR EACH ROW BEGIN
  IF OLD.stock != NEW.stock THEN
    INSERT INTO stock_logs (product_id, old_stock, new_stock, change_time)
    VALUES (OLD.id, OLD.stock, NEW.stock, NOW());
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `product_add_logs`
--

CREATE TABLE `product_add_logs` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `added_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_add_logs`
--

INSERT INTO `product_add_logs` (`id`, `product_id`, `name`, `added_time`) VALUES
(1, 14, 'Earphone', '2025-07-28 19:42:01');

-- --------------------------------------------------------

--
-- Table structure for table `product_delete_logs`
--

CREATE TABLE `product_delete_logs` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_delete_logs`
--

INSERT INTO `product_delete_logs` (`id`, `product_id`, `name`, `deleted_time`) VALUES
(1, 13, 'UBL Speaker', '2025-07-28 19:30:25'),
(2, 14, 'Earphone', '2025-07-28 19:43:21');

-- --------------------------------------------------------

--
-- Table structure for table `stock_logs`
--

CREATE TABLE `stock_logs` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `old_stock` int(11) DEFAULT NULL,
  `new_stock` int(11) DEFAULT NULL,
  `change_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_logs`
--

INSERT INTO `stock_logs` (`id`, `product_id`, `old_stock`, `new_stock`, `change_time`) VALUES
(1, 10, 12, 11, '2025-07-28 19:36:48'),
(2, 11, 5, 4, '2025-07-28 19:45:01'),
(3, 11, 4, 30, '2025-07-28 19:45:16'),
(4, 9, 52, 51, '2025-07-28 21:24:45'),
(5, 10, 11, 10, '2025-07-28 21:24:45'),
(6, 10, 10, 9, '2025-07-28 21:25:42'),
(7, 10, 9, 8, '2025-07-28 21:25:59'),
(8, 10, 8, 7, '2025-07-28 21:26:28'),
(9, 10, 7, 6, '2025-07-28 21:27:50'),
(10, 6, 27, 26, '2025-07-28 21:28:09');

-- --------------------------------------------------------

--
-- Table structure for table `transaction_log`
--

CREATE TABLE `transaction_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'PHP',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transaction_log`
--

INSERT INTO `transaction_log` (`id`, `user_id`, `total`, `currency`, `created_at`) VALUES
(52, 6, 500.00, 'PHP', '2025-07-28 11:36:48'),
(54, 7, 500.00, 'PHP', '2025-07-28 13:25:42'),
(55, 7, 500.00, 'PHP', '2025-07-28 13:25:59'),
(56, 7, 500.00, 'PHP', '2025-07-28 13:26:28'),
(57, 7, 500.00, 'PHP', '2025-07-28 13:27:50'),
(58, 7, 7000.00, 'PHP', '2025-07-28 13:28:09');

--
-- Triggers `transaction_log`
--
DELIMITER $$
CREATE TRIGGER `log_order_creation` AFTER INSERT ON `transaction_log` FOR EACH ROW BEGIN
  INSERT INTO order_create_logs (order_id, user_id, created_at)
  VALUES (NEW.id, NEW.user_id, NOW());
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','staff','customer') DEFAULT 'customer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`) VALUES
(1, 'francine lastimosa', 'lastimosarisch@gmail.com', '1234', 'admin'),
(2, 'Fritz Palumbarit', 'fritz@gmail.com', '1234', 'customer'),
(3, 'hailee', 'haileesteinfeld@gmail.com', '1234', 'staff'),
(5, 'Andre magbitang', 'dree@gmail.com', '123456', 'staff'),
(6, 'res ntt', 'yesnt@gmail.com', 'yesnt', 'customer'),
(7, 'ren amamiya', 'amamiyaren@gmail.com', 'test', 'customer'),
(8, 'julie', 'julie@gmail.com', '1234', 'customer');

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `log_user_register` AFTER INSERT ON `users` FOR EACH ROW BEGIN
  INSERT INTO user_register_logs (user_id, email, registered_at)
  VALUES (NEW.id, NEW.email, NOW());
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_register_logs`
--

CREATE TABLE `user_register_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `registered_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_register_logs`
--

INSERT INTO `user_register_logs` (`id`, `user_id`, `email`, `registered_at`) VALUES
(1, 8, 'julie@gmail.com', '2025-07-28 19:25:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `order_create_logs`
--
ALTER TABLE `order_create_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `price_logs`
--
ALTER TABLE `price_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_add_logs`
--
ALTER TABLE `product_add_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_delete_logs`
--
ALTER TABLE `product_delete_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_logs`
--
ALTER TABLE `stock_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transaction_log`
--
ALTER TABLE `transaction_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_register_logs`
--
ALTER TABLE `user_register_logs`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `order_create_logs`
--
ALTER TABLE `order_create_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `price_logs`
--
ALTER TABLE `price_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `product_add_logs`
--
ALTER TABLE `product_add_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `product_delete_logs`
--
ALTER TABLE `product_delete_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `stock_logs`
--
ALTER TABLE `stock_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `transaction_log`
--
ALTER TABLE `transaction_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_register_logs`
--
ALTER TABLE `user_register_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
