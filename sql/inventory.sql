-- Inventory Valuation System (Weighted Average Method)

ALTER TABLE `products` ADD COLUMN `asset_account_id` INT(11) DEFAULT NULL;
ALTER TABLE `products` ADD COLUMN `cogs_account_id` INT(11) DEFAULT NULL;
ALTER TABLE `products` ADD COLUMN `income_account_id` INT(11) DEFAULT NULL;

-- Track stock movements
CREATE TABLE IF NOT EXISTS `stock_moves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `transaction_type` enum('Purchase', 'Sale', 'Adjustment', 'Return') NOT NULL,
  `qty` int(11) NOT NULL,
  `unit_cost` decimal(15,2) NOT NULL,
  `reference_id` int(11) DEFAULT NULL, -- invoice_id or bill_id
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `description` text,
  `qty` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) NOT NULL,
  `total` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bill_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `description` text,
  `qty` int(11) NOT NULL,
  `unit_cost` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) NOT NULL,
  `total` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`bill_id`) REFERENCES `bills`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
