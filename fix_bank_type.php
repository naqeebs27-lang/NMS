<?php
require_once 'includes/config.php';

// 1. Add 'Bank' type if it doesn't exist
$check = $conn->query("SELECT id FROM account_types WHERE name = 'Bank'");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO account_types (name, normal_balance) VALUES ('Bank', 'Debit')");
    echo "Added 'Bank' account type.<br>";
} else {
    echo "'Bank' account type already exists.<br>";
}

// Get the ID of 'Bank' type
$res = $conn->query("SELECT id FROM account_types WHERE name = 'Bank'");
$bank_type_id = $res->fetch_assoc()['id'];

// 2. Update specific accounts to 'Bank' type
$accounts_to_update = ['A001', 'A003', 'A004'];
$codes = "'" . implode("','", $accounts_to_update) . "'";
$conn->query("UPDATE accounts SET type_id = $bank_type_id WHERE code IN ($codes)");

echo "Updated accounts " . implode(", ", $accounts_to_update) . " to Bank type.<br>";
?>
