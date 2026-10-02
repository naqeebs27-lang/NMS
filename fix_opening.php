<?php
require_once 'includes/config.php';

echo "<div style='font-family:sans-serif; padding:20px; border:1px solid #ccc; border-radius:8px; max-width:600px; margin:20px auto;'>";
echo "<h2 style='color:#991b1b;'>NMS Global Transaction Reset</h2>";
echo "<p style='color:#666;'>This utility will delete ALL transaction data to prepare for a fresh start on <b>October 1, 2026</b>.</p>";
echo "<p style='color:#0369a1; font-weight:bold;'>Preserved Data: Chart of Accounts, Customers, Suppliers, and Products.</p>";

$conn->begin_transaction();
try {
    // Disable foreign key checks for truncation
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

    // List of transaction tables to clear
    $tables = [
        'journal_entry_lines',
        'journal_entries',
        'bill_items',
        'bills',
        'invoice_items',
        'invoices',
        'payments',
        'stock_moves',
        'audit_logs',
        'reconciliations'
    ];

    foreach ($tables as $table) {
        // Check if table exists before truncating
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check->num_rows > 0) {
            $conn->query("TRUNCATE TABLE $table");
            echo "<p style='color:green; margin:5px 0;'>✓ Cleared $table</p>";
        }
    }

    // Reset stock quantities in products to 0
    $conn->query("UPDATE products SET stock_qty = 0");
    echo "<p style='color:green; margin:5px 0;'>✓ Reset all Product Stock to zero.</p>";

    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    $conn->commit();

    echo "<div style='background:#ecfdf5; border:1px solid #d1fae5; padding:15px; border-radius:6px; color:#065f46; margin-top:20px;'>";
    echo "<h3 style='margin-top:0;'>System Ready for Fresh Start!</h3>";
    echo "<p>All historical records (including Petty Cash) have been removed.</p>";
    echo "<p><b>Next Step:</b> Go to the <b>Opening Balances</b> page to enter your figures as of <b>01-10-2026</b>.</p>";
    echo "</div>";

    echo "<p style='margin-top:20px;'>
            <a href='opening_balances.php' style='display:inline-block; background:#059669; color:white; padding:10px 20px; text-decoration:none; border-radius:6px; font-weight:bold;'>Set Opening Balances</a>
            <a href='index.php' style='display:inline-block; background:#0f172a; color:white; padding:10px 20px; text-decoration:none; border-radius:6px; margin-left:10px;'>Go to Dashboard</a>
          </p>";

} catch (Exception $e) {
    $conn->rollback();
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    echo "<div style='background:#fef2f2; border:1px solid #fee2e2; padding:15px; border-radius:6px; color:#991b1b;'>";
    echo "<strong>Error during reset:</strong> " . $e->getMessage();
    echo "</div>";
}
echo "</div>";
?>