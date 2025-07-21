-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 21, 2025 at 12:51 PM
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
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `currency_id` int(11) NOT NULL,
  `currency_code` varchar(10) NOT NULL,
  `symbol` varchar(5) NOT NULL,
  `exchange_rate_to_usd` decimal(10,4) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'PHP',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total`, `currency`, `created_at`) VALUES
(7, 6, 54990.00, 'PHP', '2025-07-21 10:11:19');

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
(9, 7, 11, 1, 54990.00);

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
(6, 'Wireless keyboard', 'leek, responsive, and built for comfort. Features low-profile keys, reliable wireless connection, and impressive battery life—ideal for efficient typing wherever you are.', 7000.00, 27, 'product_6872dacebffaf0.75700177.jpg'),
(7, 'Acer', 'Powerful and reliable for work, school, or play. Sleek design, fast performance, and long battery life at a great value.', 80000.00, 3, 'product_6872dbf89d2a67.13215666.jpg'),
(8, 'Headset', 'Comfortable to wear, clear sound, and easy to use. Great for calls, music, or gaming. Just plug in or connect with Bluetooth.', 5000.00, 25, 'product_6872dcc0351d82.69723116.jpg'),
(9, 'Bavin Powerbank', 'Fast charging with USB‑C Power Delivery. Good for phones and tablets, small enough to carry.', 1809.00, 55, 'product_6872dd0761f7d0.77593932.jpg'),
(10, 'Emeet 1080P Webcam with Microphone', 'Full HD 1080p at 30 fps with sharp fixed-focus and a wide 90° field-of-view—great for clear, natural video in well-lit rooms', 500.00, 12, 'product_6872dd92e744c2.80511758.jpg'),
(11, 'iPhone 16', 'Fast. Great for photos, videos, and smooth everyday use.', 54990.00, 8, 'product_6872ddfca5c2a8.18796598.jpg'),
(13, 'UBL Speaker', 'Compact, portable, and powerful sound. Enjoy crisp audio with Bluetooth streaming, long battery life, and easy setup—great for music on the go.', 3000.00, 34, 'product_687360bd5baa76.93512447.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `transaction_logs`
--

CREATE TABLE `transaction_logs` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `amount` decimal(10,2) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'fronice lastimo', 'lastimosfronic@gmail.com', '1234', 'admin'),
(2, 'Fritz Palumbarit', 'fritz@gmail.com', '1234', 'staff'),
(3, 'hailee', 'haileesteinfeld@gmail.com', '1234', 'customer'),
(5, 'Amamiya ren', 'dree@gmail.com', '123456', 'customer'),
(6, 'res ntt', 'yesnt@gmail.com', 'yesnt', 'staff');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`currency_id`),
  ADD UNIQUE KEY `currency_code` (`currency_code`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transaction_logs`
--
ALTER TABLE `transaction_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

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
-- AUTO_INCREMENT for table `currencies`
--
ALTER TABLE `currencies`
  MODIFY `currency_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `transaction_logs`
--
ALTER TABLE `transaction_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `transaction_logs`
--
ALTER TABLE `transaction_logs`
  ADD CONSTRAINT `transaction_logs_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
