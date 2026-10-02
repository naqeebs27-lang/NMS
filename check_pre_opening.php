<?php
require_once 'includes/config.php';
header('Content-Type: text/plain');

echo "--- TRANSACTIONS BEFORE 2025-08-01 ---\n";
$res = $conn->query("SELECT je.id, je.entry_date, je.reference, je.description, SUM(jel.debit) as total
                     FROM journal_entries je
                     JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
                     WHERE je.entry_date < '2025-08-01'
                     GROUP BY je.id");

if ($res && $res->num_rows > 0) {
    while($row = $res->fetch_assoc()) {
        echo "ID: {$row['id']} | Date: {$row['entry_date']} | Ref: {$row['reference']} | Desc: {$row['description']} | Total: {$row['total']}\n";
    }
} else {
    echo "No transactions found before 2025-08-01.\n";
}

echo "\n--- ACCOUNT BALANCES BEFORE 2025-08-01 ---\n";
$res = $conn->query("SELECT a.code, a.name, SUM(jel.debit) as debits, SUM(jel.credit) as credits
                     FROM accounts a
                     LEFT JOIN journal_entry_lines jel ON a.id = jel.account_id
                     LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
                     WHERE je.entry_date < '2025-08-01'
                     GROUP BY a.id
                     HAVING (debits - credits) != 0 OR (credits - debits) != 0");

while($row = $res->fetch_assoc()) {
    echo "Code: {$row['code']} | Name: {$row['name']} | Debits: {$row['debits']} | Credits: {$row['credits']} | Net: " . ($row['debits'] - $row['credit']) . "\n";
}
?>