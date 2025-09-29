-- Add vendor field to applications table
ALTER TABLE `applications` ADD COLUMN `vendor` VARCHAR(255) NULL AFTER `mode_of_payment`;

-- Add index on vendor for better performance
ALTER TABLE `applications` ADD INDEX `idx_vendor` (`vendor`);
