-- Add phone_no field to users table
ALTER TABLE `users` ADD COLUMN `phone_no` VARCHAR(20) NULL AFTER `name`;

-- Add index on phone_no for better performance
ALTER TABLE `users` ADD INDEX `idx_phone_no` (`phone_no`);
