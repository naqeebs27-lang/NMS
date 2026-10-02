-- Multi-Currency Support

CREATE TABLE IF NOT EXISTS `currencies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(3) NOT NULL,
  `name` varchar(50) NOT NULL,
  `symbol` varchar(10) DEFAULT NULL,
  `exchange_rate` decimal(15,6) DEFAULT 1.000000,
  `is_default` tinyint(1) DEFAULT 0,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `currencies` (`code`, `name`, `symbol`, `exchange_rate`, `is_default`) VALUES
('PKR', 'Pakistani Rupee', 'Rs.', 1.000000, 1),
('USD', 'US Dollar', '$', 285.500000, 0),
('AED', 'UAE Dirham', 'AED', 77.700000, 0),
('GBP', 'British Pound', '£', 355.200000, 0);

ALTER TABLE `journal_entries` ADD COLUMN `currency_id` INT(11) DEFAULT NULL;
ALTER TABLE `journal_entries` ADD COLUMN `exchange_rate` decimal(15,6) DEFAULT 1.000000;
ALTER TABLE `journal_entry_lines` ADD COLUMN `fc_debit` decimal(15,2) DEFAULT 0.00;
ALTER TABLE `journal_entry_lines` ADD COLUMN `fc_credit` decimal(15,2) DEFAULT 0.00;

ALTER TABLE `invoices` ADD COLUMN `currency_id` INT(11) DEFAULT NULL;
ALTER TABLE `invoices` ADD COLUMN `exchange_rate` decimal(15,6) DEFAULT 1.000000;
ALTER TABLE `bills` ADD COLUMN `currency_id` INT(11) DEFAULT NULL;
ALTER TABLE `bills` ADD COLUMN `exchange_rate` decimal(15,6) DEFAULT 1.000000;
