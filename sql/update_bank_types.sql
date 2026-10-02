-- Add Bank type if it doesn't exist
INSERT INTO `account_types` (`id`, `name`, `normal_balance`)
SELECT 7, 'Bank', 'Debit'
WHERE NOT EXISTS (SELECT 1 FROM `account_types` WHERE `name` = 'Bank');

-- Update specific NMS accounts to 'Bank' type
-- Assuming A001, A003, A004 should be Bank accounts
UPDATE `accounts` SET `type_id` = (SELECT id FROM `account_types` WHERE `name` = 'Bank')
WHERE `code` IN ('A001', 'A003', 'A004');

-- Ensure A002 (Petty Cash) is also treated appropriately if needed,
-- but you specifically mentioned Cash, Sulaman HBL, and Khakan Bank.
