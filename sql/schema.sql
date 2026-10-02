-- Pakistan-Focused Double-Entry Accounting Software Schema
-- Optimized for XAMPP (MySQL/MariaDB)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- 1. Companies Table
CREATE TABLE IF NOT EXISTS `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `ntn` varchar(50) DEFAULT NULL,
  `strn` varchar(50) DEFAULT NULL,
  `address` text,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `fiscal_year_start` date DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'PKR',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Account Types
CREATE TABLE IF NOT EXISTS `account_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `normal_balance` enum('Debit','Credit') NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `account_types` (`id`, `name`, `normal_balance`) VALUES
(1, 'Asset', 'Debit'),
(2, 'Liability', 'Credit'),
(3, 'Equity', 'Credit'),
(4, 'Revenue', 'Credit'),
(5, 'Expense', 'Debit'),
(6, 'Cost of Sales', 'Debit');

-- 3. Chart of Accounts
CREATE TABLE IF NOT EXISTS `accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  FOREIGN KEY (`type_id`) REFERENCES `account_types`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Customers & Suppliers
CREATE TABLE IF NOT EXISTS `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `ntn` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `ntn` varchar(50) DEFAULT NULL,
  `strn` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Products & Inventory
CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `sale_price` decimal(15,2) DEFAULT 0.00,
  `purchase_price` decimal(15,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 18.00,
  `stock_qty` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Journal System
CREATE TABLE IF NOT EXISTS `journal_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_date` date NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `description` text,
  `source_module` varchar(50) DEFAULT 'Manual',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `journal_entry_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `journal_entry_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `description` text,
  `debit` decimal(15,2) DEFAULT 0.00,
  `credit` decimal(15,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Sales & Purchases
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `status` enum('Draft','Sent','Paid','Partially Paid','Overdue','Cancelled') DEFAULT 'Draft',
  `journal_entry_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`),
  FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SEEDING CHART OF ACCOUNTS (Detailed Pakistan SME Standard)
INSERT INTO `accounts` (`code`, `name`, `type_id`, `description`) VALUES
-- ASSETS (1000s)
('1101', 'Cash on Hand', 1, 'Physical cash in office'),
('1102', 'Petty Cash', 1, 'Small cash for minor expenses'),
('1103', 'HBL Bank - 123456789', 1, 'Habib Bank Main Account'),
('1104', 'Meezan Bank - 987654321', 1, 'Islamic Bank Account'),
('1201', 'Accounts Receivable', 1, 'Customer balances'),
('1301', 'Inventory Asset', 1, 'Value of stock on hand'),
('1401', 'Prepaid Rent', 1, 'Advance rent paid'),
('1501', 'Sales Tax Receivable', 1, 'Input GST paid on purchases'),
('1601', 'Staff Advances', 1, 'Loans/Advances to employees'),
('1701', 'Furniture & Fixtures', 1, 'Office furniture'),
('1702', 'Computer Equipment', 1, 'Laptops, Servers, Printers'),
('1703', 'Motor Vehicles', 1, 'Company cars and bikes'),
('1801', 'Accumulated Depreciation', 1, 'Contra asset account'),

-- LIABILITIES (2000s)
('2101', 'Accounts Payable', 2, 'Supplier balances'),
('2201', 'Salaries Payable', 2, 'Accrued employee salaries'),
('2301', 'Sales Tax Payable', 2, 'Output GST collected on sales'),
('2401', 'Withholding Tax Payable', 2, 'Tax withheld for FBR'),
('2501', 'Bank Loan', 2, 'Long term bank liabilities'),
('2601', 'Accrued Expenses', 2, 'Unpaid bills at period end'),

-- EQUITY (3000s)
('3101', 'Owner Capital', 3, 'Initial Investment'),
('3201', 'Owner Drawings', 3, 'Personal withdrawals'),
('3301', 'Retained Earnings', 3, 'Accumulated profits'),

-- REVENUE (4000s)
('4101', 'Sales Revenue', 4, 'Income from trading'),
('4201', 'Service Revenue', 4, 'Income from services'),
('4301', 'Other Income', 4, 'Miscellaneous revenue'),
('4401', 'Sales Discounts', 4, 'Discounts given to customers'),

-- COST OF SALES (5000s)
('5101', 'Cost of Goods Sold', 6, 'Direct cost of items sold'),
('5201', 'Purchase Cost', 6, 'Amount paid for stock'),
('5301', 'Freight Inwards', 6, 'Transportation cost for stock'),

-- EXPENSES (6000s-7000s)
('6101', 'Salaries Expense', 5, 'Monthly payroll'),
('6201', 'Rent Expense', 5, 'Office/Warehouse rent'),
('6301', 'Electricity Expense', 5, 'WAPDA/K-Electric bills'),
('6401', 'Water and Gas', 5, 'Utilities'),
('6501', 'Internet & Phone', 5, 'PTCL/Mobile/Fiber costs'),
('6601', 'Transport & Fuel', 5, 'Travel and vehicle fuel'),
('6801', 'Office Supplies', 5, 'Stationery and small items'),
('6901', 'Marketing & Ads', 5, 'Promotion costs'),
('7001', 'Repairs & Maintenance', 5, 'Office upkeep'),
('7101', 'Bank Charges', 5, 'Account maintenance and fees'),
('7201', 'Depreciation Expense', 5, 'Non-cash asset cost allocation'),
('7301', 'Professional Fees', 5, 'Legal and Audit charges');

-- DEMO DATA SEEDING
INSERT INTO `companies` (`name`, `ntn`, `strn`, `address`, `city`, `province`, `fiscal_year_start`) VALUES
('Kamran Trading & Services', '1234567-8', '9876543210987', 'Blue Area, Jinnah Avenue', 'Islamabad', 'Federal Capital', '2023-07-01');

INSERT INTO `customers` (`name`, `company_name`, `ntn`) VALUES
('Islamabad Electronics', 'IE (Pvt) Ltd', '1111111-1'),
('Punjab Mart', 'PM Retailers', '2222222-2');

INSERT INTO `suppliers` (`name`, `company_name`, `ntn`, `strn`) VALUES
('Pakistan Office Supplies', 'POS Ltd', '7777777-7', '1122334455667'),
('PTCL', 'PTCL Official', '0000000-0', '0000000000000');

-- Opening Balances Entry
INSERT INTO `journal_entries` (`entry_date`, `reference`, `description`, `source_module`) VALUES
('2023-07-01', 'OB-2023', 'Opening Balances', 'Opening Balance');

SET @je_id = LAST_INSERT_ID();
INSERT INTO `journal_entry_lines` (`journal_entry_id`, `account_id`, `debit`, `credit`) VALUES
(@je_id, (SELECT id FROM accounts WHERE code='1103'), 2500000.00, 0.00), -- HBL
(@je_id, (SELECT id FROM accounts WHERE code='3101'), 0.00, 2500000.00); -- Capital

-- Petty Cash Demo Entries
INSERT INTO `journal_entries` (`entry_date`, `reference`, `description`, `source_module`) VALUES
('2023-07-10', 'PCV-001', 'Office Refreshments', 'Petty Cash');
SET @pc_id = LAST_INSERT_ID();
INSERT INTO `journal_entry_lines` (`journal_entry_id`, `account_id`, `debit`, `credit`) VALUES
(@pc_id, (SELECT id FROM accounts WHERE code='6801'), 2500.00, 0.00), -- Office Supplies
(@pc_id, (SELECT id FROM accounts WHERE code='1102'), 0.00, 2500.00); -- Petty Cash (Note: usually initialized first)

COMMIT;
