-- Pakistan Specific Tax Updates
-- Adding Filer Status to Parties

ALTER TABLE `customers` ADD COLUMN `is_filer` TINYINT(1) DEFAULT 1;
ALTER TABLE `suppliers` ADD COLUMN `is_filer` TINYINT(1) DEFAULT 1;

-- WHT Rates Table (Simplified for SME)
CREATE TABLE IF NOT EXISTS `tax_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(100) NOT NULL,
  `filer_rate` decimal(5,2) NOT NULL,
  `non_filer_rate` decimal(5,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tax_rates` (`category`, `filer_rate`, `non_filer_rate`) VALUES
('Goods', 4.50, 9.00),
('Services', 10.00, 20.00),
('Rent', 15.00, 25.00);
