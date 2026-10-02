<?php
require_once 'includes/config.php';
header('Content-Type: text/plain');

$accounts = $conn->query("SELECT id, code, name FROM accounts WHERE code IN ('A001', 'A002', 'A003', 'L0001') OR name LIKE '%Capital%' OR name LIKE '%Cash%'");
while($row = $accounts->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Code: " . $row['code'] . " | Name: " . $row['name'] . "\n";
}
?>