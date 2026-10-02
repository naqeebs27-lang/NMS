-- This script provides a way to recalculate party balances if needed.
-- In a real double-entry system, balances are derived from the ledger.

-- Update customer balances based on AR account entries
UPDATE customers c
SET c.balance = (
    SELECT SUM(jel.debit) - SUM(jel.credit)
    FROM journal_entry_lines jel
    JOIN journal_entries je ON jel.journal_entry_id = je.id
    WHERE jel.account_id = (SELECT id FROM accounts WHERE code = '1201')
    AND je.description LIKE CONCAT('%', c.name, '%')
);

-- Update supplier balances based on AP account entries
UPDATE suppliers s
SET s.balance = (
    SELECT SUM(jel.credit) - SUM(jel.debit)
    FROM journal_entry_lines jel
    JOIN journal_entries je ON jel.journal_entry_id = je.id
    WHERE jel.account_id = (SELECT id FROM accounts WHERE code = '2101')
    AND je.description LIKE CONCAT('%', s.name, '%')
);
