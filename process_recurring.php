<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$today = date('Y-m-d');
$query = "SELECT ri.*, c.name as customer_name FROM recurring_invoices ri
          JOIN customers c ON ri.customer_id = c.id
          WHERE ri.status = 'Active' AND ri.next_generation_date <= ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $today);
$stmt->execute();
$due_invoices = $stmt->get_result();

$processed_count = 0;

// Accounts for JE
$ar_acc = $conn->query("SELECT id FROM accounts WHERE code = '1201'")->fetch_assoc();
$rev_acc = $conn->query("SELECT id FROM accounts WHERE code = '4101'")->fetch_assoc();
$tax_acc = $conn->query("SELECT id FROM accounts WHERE code = '2301'")->fetch_assoc();

while ($ri = $due_invoices->fetch_assoc()) {
    $conn->begin_transaction();
    try {
        $inv_number = "REC-" . strtoupper(substr(uniqid(), 7));
        $inv_date = $ri['next_generation_date'];
        $due_date = date('Y-m-d', strtotime($inv_date . ' + 15 days'));

        // Construct Journal Entry
        $je_desc = "Recurring Invoice $inv_number - " . $ri['description'];
        $journal_lines = [
            ['account_id' => $ar_acc['id'], 'description' => $je_desc, 'debit' => $ri['total_amount'], 'credit' => 0],
            ['account_id' => $rev_acc['id'], 'description' => $je_desc, 'debit' => 0, 'credit' => $ri['subtotal']]
        ];

        if ($ri['tax_amount'] > 0) {
            $journal_lines[] = ['account_id' => $tax_acc['id'], 'description' => "Output GST on $inv_number", 'debit' => 0, 'credit' => $ri['tax_amount']];
        }

        $je_result = postJournalEntry($conn, $inv_date, $inv_number, $je_desc, 'Sales', $journal_lines);
        if (!$je_result['success']) throw new Exception($je_result['error']);
        $je_id = $je_result['id'];

        // Create Invoice Record
        $stmt_inv = $conn->prepare("INSERT INTO invoices (customer_id, invoice_number, invoice_date, due_date, total_amount, status, journal_entry_id) VALUES (?, ?, ?, ?, ?, 'Sent', ?)");
        $stmt_inv->bind_param("isssdi", $ri['customer_id'], $inv_number, $inv_date, $due_date, $ri['total_amount'], $je_id);
        $stmt_inv->execute();
        $invoice_id = $conn->insert_id;

        // Copy items
        $items_res = $conn->query("SELECT * FROM recurring_invoice_items WHERE recurring_invoice_id = " . $ri['id']);
        $item_stmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, product_id, description, qty, unit_price, tax_amount, total) VALUES (?, ?, ?, ?, ?, ?, ?)");

        while ($item = $items_res->fetch_assoc()) {
            // Get tax rate for line item (assume fixed for simplicity in this loop or fetch)
            $p_stmt = $conn->prepare("SELECT tax_rate FROM products WHERE id = ?");
            $p_stmt->bind_param("i", $item['product_id']);
            $p_stmt->execute();
            $tax_rate = $p_stmt->get_result()->fetch_assoc()['tax_rate'] ?? 18.00;

            $line_tax = ($item['qty'] * $item['unit_price']) * ($tax_rate / 100);
            $line_total = ($item['qty'] * $item['unit_price']) + $line_tax;

            $item_stmt->bind_param("iisdddd", $invoice_id, $item['product_id'], $item['description'], $item['qty'], $item['unit_price'], $line_tax, $line_total);
            $item_stmt->execute();
        }

        // Calculate next date
        $next_date = $inv_date;
        switch ($ri['frequency']) {
            case 'Weekly': $next_date = date('Y-m-d', strtotime($inv_date . ' + 1 week')); break;
            case 'Monthly': $next_date = date('Y-m-d', strtotime($inv_date . ' + 1 month')); break;
            case 'Quarterly': $next_date = date('Y-m-d', strtotime($inv_date . ' + 3 months')); break;
            case 'Yearly': $next_date = date('Y-m-d', strtotime($inv_date . ' + 1 year')); break;
        }

        $upd_ri = $conn->prepare("UPDATE recurring_invoices SET last_generation_date = ?, next_generation_date = ? WHERE id = ?");
        $upd_ri->bind_param("ssi", $inv_date, $next_date, $ri['id']);
        $upd_ri->execute();

        $conn->commit();
        $processed_count++;
    } catch (Exception $e) {
        $conn->rollback();
        // Log error
    }
}

header("Location: recurring_invoices.php?processed=$processed_count");
exit;
?>
