<?php
function formatCurrency($amount, $currency_code = 'PKR') {
    $symbol = 'Rs. ';
    if ($currency_code !== 'PKR') {
        $symbol = $currency_code . ' ';
    }
    return $symbol . number_format($amount, 2);
}

function formatPKR($amount) {
    return formatCurrency($amount, 'PKR');
}

function convertToBase($amount, $exchange_rate) {
    return $amount * $exchange_rate;
}

function convertFromBase($amount, $exchange_rate) {
    if ($exchange_rate == 0) return 0;
    return $amount / $exchange_rate;
}

function getAccountBalance($account_id, $conn, $start_date = null, $end_date = null) {
    $sql = "SELECT
                SUM(debit) as total_debit,
                SUM(credit) as total_credit,
                at.normal_balance
            FROM journal_entry_lines jel
            JOIN accounts a ON jel.account_id = a.id
            JOIN account_types at ON a.type_id = at.id
            JOIN journal_entries je ON jel.journal_entry_id = je.id
            WHERE (a.id = ? OR a.parent_id = ?)";

    if ($start_date && $end_date) {
        $sql .= " AND je.entry_date BETWEEN ? AND ?";
    } elseif ($end_date) {
        $sql .= " AND je.entry_date <= ?";
    }

    $stmt = $conn->prepare($sql);
    if ($start_date && $end_date) {
        $stmt->bind_param("iiss", $account_id, $account_id, $start_date, $end_date);
    } elseif ($end_date) {
        $stmt->bind_param("iis", $account_id, $account_id, $end_date);
    } else {
        $stmt->bind_param("ii", $account_id, $account_id);
    }

    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    $debit = $result['total_debit'] ?? 0;
    $credit = $result['total_credit'] ?? 0;

    if (($result['normal_balance'] ?? 'Debit') == 'Debit') {
        return $debit - $credit;
    } else {
        return $credit - $debit;
    }
}

function getTotalByType($type_name, $conn, $start_date = null, $end_date = null) {
    $sql = "SELECT
                SUM(jel.debit) as total_debit,
                SUM(jel.credit) as total_credit,
                at.normal_balance
            FROM journal_entry_lines jel
            JOIN accounts a ON jel.account_id = a.id
            JOIN account_types at ON a.type_id = at.id
            JOIN journal_entries je ON jel.journal_entry_id = je.id
            WHERE at.name = ?";

    if ($start_date && $end_date) {
        $sql .= " AND je.entry_date BETWEEN ? AND ?";
    }

    $stmt = $conn->prepare($sql);
    if ($start_date && $end_date) {
        $stmt->bind_param("sss", $type_name, $start_date, $end_date);
    } else {
        $stmt->bind_param("s", $type_name);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    $debit = $result['total_debit'] ?? 0;
    $credit = $result['total_credit'] ?? 0;

    if (($result['normal_balance'] ?? 'Debit') == 'Debit') {
        return $debit - $credit;
    } else {
        return $credit - $debit;
    }
}

function postJournalEntry($conn, $date, $reference, $description, $module, $lines) {
    // lines: array of ['account_id' => x, 'description' => y, 'debit' => d, 'credit' => c]

    $total_debit = 0;
    $total_credit = 0;
    foreach ($lines as $line) {
        $total_debit += (float)($line['debit'] ?? 0);
        $total_credit += (float)($line['credit'] ?? 0);
    }

    if (abs($total_debit - $total_credit) > 0.01) {
        return ['success' => false, 'error' => "Unbalanced journal entry: Debits $total_debit != Credits $total_credit"];
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO journal_entries (entry_date, reference, description, source_module) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $date, $reference, $description, $module);
        $stmt->execute();
        $je_id = $conn->insert_id;

        $stmt_line = $conn->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, description, debit, credit) VALUES (?, ?, ?, ?, ?)");

        foreach ($lines as $line) {
            $stmt_line->bind_param("iisdd", $je_id, $line['account_id'], $line['description'], $line['debit'], $line['credit']);
            $stmt_line->execute();
        }

        $conn->commit();
        return ['success' => true, 'id' => $je_id];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function deleteJournalEntry($conn, $je_id) {
    $conn->begin_transaction();
    try {
        $stmt1 = $conn->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?");
        $stmt1->bind_param("i", $je_id);
        $stmt1->execute();

        $stmt2 = $conn->prepare("DELETE FROM journal_entries WHERE id = ?");
        $stmt2->bind_param("i", $je_id);
        $stmt2->execute();

        $conn->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function checkLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

function isAdmin() {
    return ($_SESSION['user_role'] ?? '') === 'Admin';
}

function logAction($conn, $action, $module, $record_id = null, $details = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, module, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $user_id, $action, $module, $record_id, $details, $ip);
    $stmt->execute();
}

/**
 * Updates stock levels and calculates Weighted Average Cost (WAC)
 * @return float The new average cost
 */
function updateStock($conn, $product_id, $qty_change, $unit_cost, $type, $ref_id) {
    // 1. Get current state
    $stmt = $conn->prepare("SELECT stock_qty, purchase_price FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    $current_qty = $product['stock_qty'];
    $current_avg_cost = (float)$product['purchase_price'];

    $new_qty = $current_qty + $qty_change;
    $new_avg_cost = $current_avg_cost;

    if ($type == 'Purchase' && $new_qty > 0) {
        // (Current Total Value + New Purchase Value) / New Total Qty
        $new_avg_cost = (($current_qty * $current_avg_cost) + ($qty_change * $unit_cost)) / $new_qty;
    }

    // 2. Update product
    $upd = $conn->prepare("UPDATE products SET stock_qty = ?, purchase_price = ? WHERE id = ?");
    $upd->bind_param("idi", $new_qty, $new_avg_cost, $product_id);
    $upd->execute();

    // 3. Record move
    $move = $conn->prepare("INSERT INTO stock_moves (product_id, transaction_type, qty, unit_cost, reference_id) VALUES (?, ?, ?, ?)");
    $move->bind_param("isidi", $product_id, $type, $qty_change, $unit_cost, $ref_id);
    $move->execute();

    return $new_avg_cost;
}
?>