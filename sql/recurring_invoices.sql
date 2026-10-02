-- Recurring Invoices and Automated Billing

CREATE TABLE IF NOT EXISTS `recurring_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `frequency` enum('Weekly', 'Monthly', 'Quarterly', 'Yearly') NOT NULL,
  `start_date` date NOT NULL,
  `next_generation_date` date NOT NULL,
  `last_generation_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Active', 'Paused', 'Completed') DEFAULT 'Active',
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `description` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `recurring_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recurring_invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `description` text,
  `qty` decimal(15,2) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`recurring_invoice_id`) REFERENCES `recurring_invoices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
