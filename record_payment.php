<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$type = $_GET['type'] ?? 'customer'; // customer or supplier
$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: " . ($type == 'customer' ? 'invoices.php' : 'bills.php'));
    exit;
}

if ($type == 'customer') {
    $item = $conn->query("SELECT i.*, c.name as party_name FROM invoices i JOIN customers c ON i.customer_id = c.id WHERE i.id = $id")->fetch_assoc();
    $party_label = "Customer";
    $ref_prefix = "INV";
    $ap_ar_code = '1201'; // AR
    $bank_cash_type = 'Debit'; // Payment received (Bank DR, AR CR)
} else {
    $item = $conn->query("SELECT b.*, s.name as party_name FROM bills b JOIN suppliers s ON b.supplier_id = s.id WHERE b.id = $id")->fetch_assoc();
    $party_label = "Supplier";
    $ref_prefix = "BILL";
    $ap_ar_code = '2101'; // AP
}

$banks = $conn->query("SELECT id, code, name FROM accounts WHERE code LIKE '11%' ORDER BY code ASC");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $payment_date = $_POST['payment_date'];
    $bank_account_id = $_POST['bank_account_id'];
    $amount = $_POST['amount'];
    $reference = $_POST['reference'];

    $conn->begin_transaction();
    try {
        $ap_ar_acc = $conn->query("SELECT id FROM accounts WHERE code = '$ap_ar_code'")->fetch_assoc();

        $je_desc = "Payment for $ref_prefix #" . ($type == 'customer' ? $item['invoice_number'] : $item['bill_number']);

        if ($type == 'customer') {
            // Customer Payment: DR Bank, CR AR
            $lines = [
                ['account_id' => $bank_account_id, 'description' => $je_desc, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $ap_ar_acc['id'], 'description' => $je_desc, 'debit' => 0, 'credit' => $amount]
            ];
        } else {
            // Supplier Payment: DR AP, CR Bank
            $lines = [
                ['account_id' => $ap_ar_acc['id'], 'description' => $je_desc, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $bank_account_id, 'description' => $je_desc, 'debit' => 0, 'credit' => $amount]
            ];
        }

        $je_result = postJournalEntry($conn, $payment_date, $reference, $je_desc, 'Payments', $lines);
        if (!$je_result['success']) throw new Exception($je_result['error']);

        // Update status
        if ($type == 'customer') {
            $conn->query("UPDATE invoices SET status = 'Paid' WHERE id = $id");
        } else {
            $conn->query("UPDATE bills SET status = 'Paid' WHERE id = $id");
        }

        $conn->commit();
        header("Location: " . ($type == 'customer' ? 'invoices.php' : 'bills.php') . "?payment_success=1");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

include 'layouts/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Record Payment</h2>
            <p class="text-slate-500">Log a payment for <?php echo $party_label; ?>: <?php echo $item['party_name']; ?></p>
        </div>
        <a href="<?php echo ($type == 'customer' ? 'invoices.php' : 'bills.php'); ?>" class="text-slate-500 hover:text-slate-700 font-medium">Cancel</a>
    </div>

    <?php if (isset($error)): ?>
    <div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <div class="bg-white p-8 rounded-xl shadow-sm border border-slate-200">
        <form method="POST" class="space-y-6">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Payment Date</label>
                    <input type="date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Reference / Cheque #</label>
                    <input type="text" name="reference" required placeholder="e.g. CHQ-9982" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Deposit To / Payment From</label>
                <select name="bank_account_id" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5">
                    <?php while($bank = $banks->fetch_assoc()): ?>
                    <option value="<?php echo $bank['id']; ?>"><?php echo $bank['code']; ?> - <?php echo $bank['name']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Amount to Pay (PKR)</label>
                <input type="number" name="amount" step="0.01" required value="<?php echo $item['total_amount']; ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-lg font-bold text-emerald-700">
                <p class="text-xs text-slate-400 mt-1">Full amount for <?php echo ($type == 'customer' ? 'Invoice' : 'Bill'); ?> #<?php echo ($type == 'customer' ? $item['invoice_number'] : $item['bill_number']); ?> is <?php echo formatPKR($item['total_amount']); ?></p>
            </div>

            <button type="submit" class="w-full bg-emerald-600 text-white py-3 rounded-lg font-bold hover:bg-emerald-700 transition-colors">
                Confirm Payment
            </button>
        </form>
    </div>
</div>

<?php include 'layouts/footer.php'; ?>
