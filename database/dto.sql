-- DTO table creation SQL statement
CREATE TABLE `dto` (
  `dto_id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pay_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `receipt` varchar(500) DEFAULT NULL,
  `no_of_applicant` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`dto_id`),
  KEY `idx_date` (`date`),
  KEY `idx_amount` (`amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
