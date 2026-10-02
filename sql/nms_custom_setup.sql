-- NMS - Naqeebs Multi Services Custom Setup
-- Update Company Info and Chart of Accounts based on provided requirements

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Update Company Information
-- Note: Assuming logo file will be named nms_logo.png in the project root
UPDATE `companies` SET
    `name` = 'Naqeebs Multi Services',
    `logo` = 'nms_logo.png',
    `ntn` = 'N/A',
    `strn` = 'N/A'
WHERE `id` = 1;

-- 2. Clear existing accounts to prevent duplicates and use the new coding structure
TRUNCATE TABLE `journal_entry_lines`;
DELETE FROM `journal_entries`;
DELETE FROM `accounts`;

-- 3. Insert Custom Chart of Accounts based on the provided image
INSERT INTO `accounts` (`code`, `name`, `type_id`, `description`) VALUES
-- Assets (Type 1)
('A001', 'Cash', 1, 'Current Assets'),
('A002', 'Petty Cash', 1, 'Current Assets'),
('A003', 'Sulaman HBL', 1, 'Current Assets'),
('A004', 'Khakan Bank', 1, 'Current Assets'),
('E004', 'Bike', 1, 'Other Expense / Asset Type'),

-- Expenses (Type 5)
('A005', 'Opening Balance', 5, 'Current Assets / Expense Type'),
('E001', 'Anees Jhang Trip', 5, 'Other Expense'),
('E002', 'Appointments', 5, 'Other Expense'),
('E003', 'Bank Charges', 5, 'Other Expense'),
('E005', 'Breads', 5, 'Other Expense'),
('E006', 'Breakfast', 5, 'Other Expense'),
('E008', 'Communication', 5, 'Other Expense'),
('E009', 'Computer', 5, 'Other Expense'),
('E010', 'Courier', 5, 'Other Expense'),
('E011', 'Curry', 5, 'Other Expense'),
('E012', 'Entertainment', 5, 'Other Expense'),
('E013', 'Gala Event', 5, 'Other Expense'),
('E014', 'Gardner Salary', 5, 'Other Expense'),
('E015', 'Home Expenses', 5, 'Other Expense'),
('E016', 'Khakan Bills', 5, 'Other Expense'),
('E017', 'Khakan Personal', 5, 'Other Expense'),
('E018', 'Meal Staff', 5, 'Other Expense'),
('E019', 'Medical Sulaman', 5, 'Other Expense'),
('E020', 'Medicine Anees', 5, 'Other Expense'),
('E021', 'Medicine Khakan', 5, 'Other Expense'),
('E022', 'Mineral Water', 5, 'Other Expense'),
('E023', 'Misc', 5, 'Other Expense'),
('E024', 'Petrol Anees', 5, 'Other Expense'),
('E025', 'Petty Cash', 5, 'Other Expense'),
('E026', 'Rashion', 5, 'Other Expense'),
('E027', 'Salaries', 5, 'Other Expense'),
('E028', 'Sehri Iftari', 5, 'Other Expense'),
('E029', 'Stationary', 5, 'Other Expense'),
('E030', 'Sulaman HBL', 5, 'Other Expense'),
('E031', 'Sunday Meal', 5, 'Other Expense'),
('E032', 'Tea', 5, 'Other Expense'),
('E033', 'Utility Bills', 5, 'Other Expense'),
('E034', 'Water Tanker', 5, 'Other Expense'),
('E035', 'Web Hosting', 5, 'Other Expense'),

-- Equity (Type 3)
('L0001', 'Capital', 3, 'Long Term Liability / Owner Equity Type'),

-- Liabilities (Type 2)
('L002', 'Abdullah Khan Safi', 2, 'Short Term Liability'),
('L003', 'Danish Munir', 2, 'Short Term Liability');

SET FOREIGN_KEY_CHECKS = 1;
