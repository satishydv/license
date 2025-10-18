ALTER TABLE applications DROP INDEX uq_application_no;

ALTER TABLE `applications` MODIFY COLUMN `cover_class` VARCHAR(255) NULL DEFAULT NULL;


ALTER TABLE `vendors`
  MODIFY COLUMN `amount` decimal(10,2) NULL DEFAULT NULL,
  MODIFY COLUMN `pay_amount` decimal(10,2) NULL DEFAULT NULL,
  MODIFY COLUMN `mode_of_payment` ENUM('cash','upi','bank-transfer') NULL DEFAULT NULL;




  ALTER TABLE `dto`
  MODIFY COLUMN `amount` decimal(10,2) NULL DEFAULT NULL,
  MODIFY COLUMN `pay_amount` decimal(10,2) NULL DEFAULT NULL;