<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $expense_date = $_POST['expense_date'];
    $account_id = $_POST['account_id'];
    $amount = $_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $bank_account_id = !empty($_POST['bank_account_id']) ? $_POST['bank_account_id'] : null;
    $description = $_POST['description'];
    $reference = $_POST['reference'];

    $lines = [
        ['account_id' => $account_id, 'description' => $description, 'debit' => $amount, 'credit' => 0],
        ['account_id' => ($bank_account_id ?: 1), 'description' => $description, 'debit' => 0, 'credit' => $amount]
    ];

    $result = postJournalEntry($conn, $expense_date, $reference ?: 'EXP-' . time(), "Expense: $description", 'Expenses', $lines);

    if ($result['success']) {
        header("Location: expenses.php?msg=success");
        exit;
    } else {
        $error = $result['error'];
    }
}

// Fetch ALL accounts for the category dropdown, grouped by type
$all_accounts = $conn->query("SELECT a.id, a.code, a.name, at.name as type_name
                             FROM accounts a
                             JOIN account_types at ON a.type_id = at.id
                             ORDER BY at.id ASC, a.code ASC");

// Fetch Cash/Bank accounts for the "Paid From" dropdown
$bank_accounts = $conn->query("SELECT id, code, name FROM accounts WHERE type_id = 1 ORDER BY code ASC");

include 'layouts/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Record Expense</h2>
            <p class="text-slate-500">Log a new business expenditure.</p>
        </div>
        <a href="expenses.php" class="text-slate-500 hover:text-slate-800"><i class="fas fa-times text-xl"></i></a>
    </div>

    <?php if(isset($error)): ?>
        <div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Date *</label>
                <input type="date" name="expense_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Amount (PKR) *</label>
                <input type="number" step="0.01" name="amount" required class="w-full px-4 py-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500" placeholder="0.00">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Account / Category *</label>
                <select name="account_id" required class="w-full px-4 py-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value="">Select Account</option>
                    <?php
                    $current_type = '';
                    while($acc = $all_accounts->fetch_assoc()):
                        if ($current_type != $acc['type_name']):
                            if ($current_type != '') echo '</optgroup>';
                            $current_type = $acc['type_name'];
                            echo '<optgroup label="' . $current_type . '">';
                        endif;
                    ?>
                        <option value="<?php echo $acc['id']; ?>"><?php echo $acc['code'] . ' - ' . $acc['name']; ?></option>
                    <?php endwhile; echo '</optgroup>'; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Payment Method</label>
                <select name="payment_method" class="w-full px-4 py-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value="Cash">Cash</option>
                    <option value="Bank">Bank Transfer / Cheque</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Paid From *</label>
                <select name="bank_account_id" required class="w-full px-4 py-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value="">Select Payment Account</option>
                    <?php while($acc = $bank_accounts->fetch_assoc()): ?>
                        <option value="<?php echo $acc['id']; ?>"><?php echo $acc['code'] . ' - ' . $acc['name']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Description / Paid To</label>
                <input type="text" name="description" required class="w-full px-4 py-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500" placeholder="e.g. Fuel for generator">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Reference</label>
                <input type="text" name="reference" placeholder="Bill No / Receipt No" class="w-full px-4 py-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
        </div>

        <div class="flex justify-end pt-4">
            <button type="submit" class="bg-emerald-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-100">
                Record Expense
            </button>
        </div>
    </form>
</div>

<?php include 'layouts/footer.php'; ?>
