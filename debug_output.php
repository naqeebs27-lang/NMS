<?php
require_once 'includes/config.php';
$output = "";

$output .= "--- ACCOUNTS ---\n";
$res = $conn->query("SELECT id, code, name FROM accounts");
while($row = $res->fetch_assoc()) {
    $output .= "ID: {$row['id']} | Code: {$row['code']} | Name: {$row['name']}\n";
}

$output .= "\n--- OPENING JOURNAL ENTRIES ---\n";
$res = $conn->query("SELECT * FROM journal_entries WHERE entry_date = '2025-08-01' OR description LIKE '%Opening%'");
while($row = $res->fetch_assoc()) {
    $output .= "JE ID: {$row['id']} | Date: {$row['entry_date']} | Ref: {$row['reference']} | Desc: {$row['description']}\n";
    $lines = $conn->query("SELECT jel.*, a.name FROM journal_entry_lines jel JOIN accounts a ON jel.account_id = a.id WHERE journal_entry_id = {$row['id']}");
    while($line = $lines->fetch_assoc()) {
        $output .= "  - Account: {$line['name']} | Debit: {$line['debit']} | Credit: {$line['credit']}\n";
    }
}

file_put_contents('debug_log.txt', $output);
echo "Done";
?>