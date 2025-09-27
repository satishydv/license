-- Vendor table creation SQL statement
CREATE TABLE `vendors` (
  `vendor_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone_no` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pay_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `mode_of_payment` enum('cash','upi','bank-transfer') NOT NULL,
  `total_customer` int(11) NOT NULL DEFAULT 0,
  `receipt_image_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`vendor_id`),
  KEY `idx_phone_no` (`phone_no`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
