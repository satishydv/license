-- Make application_no, payment fields, and receipt paths optional in applications table

-- Make application_no optional (allow NULL)
ALTER TABLE `applications` MODIFY COLUMN `application_no` VARCHAR(100) NULL;

-- Make payment fields optional (allow NULL)
ALTER TABLE `applications` MODIFY COLUMN `amount` DECIMAL(10,2) NULL;
ALTER TABLE `applications` MODIFY COLUMN `pay_amount` DECIMAL(10,2) NULL;
ALTER TABLE `applications` MODIFY COLUMN `mode_of_payment` ENUM('cash', 'upi', 'bank-transfer') NULL;

-- Make receipt paths optional (allow NULL)
ALTER TABLE `applications` MODIFY COLUMN `license_attachment_path` VARCHAR(255) NULL;
ALTER TABLE `applications` MODIFY COLUMN `payment_receipt_path` VARCHAR(255) NULL;
