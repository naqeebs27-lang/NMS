<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Petty Cash Account identification - In NMS it is A002
$acc_res = $conn->query("SELECT id FROM accounts WHERE code = 'A002'");
$petty_cash_account = $acc_res->fetch_assoc();
$account_id = $petty_cash_account['id'] ?? 0;

// Handle Delete Action
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    // Verify it's a petty cash entry before deleting
    $check = $conn->prepare("SELECT source_module FROM journal_entries WHERE id = ?");
    $check->bind_param("i", $delete_id);
    $check->execute();
    $entry = $check->get_result()->fetch_assoc();

    if ($entry && $entry['source_module'] == 'Petty Cash') {
        $result = deleteJournalEntry($conn, $delete_id);
        if ($result['success']) {
            header("Location: petty_cash.php?msg=deleted");
            exit;
        } else {
            $error = "Error deleting entry: " . $result['error'];
        }
    }
}

// Handle Record Expense Action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_expense'])) {
    $expense_account_id = $_POST['expense_account_id'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];
    $date = date('Y-m-d');

    $lines = [
        ['account_id' => $expense_account_id, 'description' => $description, 'debit' => $amount, 'credit' => 0],
        ['account_id' => $account_id, 'description' => $description, 'debit' => 0, 'credit' => $amount]
    ];

    $result = postJournalEntry($conn, $date, 'PCV-' . time(), "Petty Cash: " . $description, 'Petty Cash', $lines);
    if ($result['success']) {
        header("Location: petty_cash.php?msg=success");
        exit;
    } else {
        $error = $result['error'];
    }
}

// Fetch data for the UI
$all_accounts = $conn->query("SELECT a.id, a.code, a.name, at.name as type_name
                             FROM accounts a
                             JOIN account_types at ON a.type_id = at.id
                             ORDER BY at.id ASC, a.code ASC");

$balance = getAccountBalance($account_id, $conn);

// Get recent petty cash transactions
$sql = "SELECT je.id, je.entry_date, je.reference, je.description as je_desc, jel.description as line_desc, jel.debit, jel.credit
        FROM journal_entry_lines jel
        JOIN journal_entries je ON jel.journal_entry_id = je.id
        WHERE jel.account_id = ?
        ORDER BY je.entry_date DESC, je.id DESC LIMIT 20";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $account_id);
$stmt->execute();
$transactions = $stmt->get_result();

include 'layouts/header.php';
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Petty Cash Book</h2>
        <p class="text-slate-500">Manage daily small office expenses and physical cash on hand.</p>
    </div>
    <div class="flex space-x-3">
        <div class="bg-white px-6 py-2 rounded-lg border border-slate-200 shadow-sm flex items-center">
            <span class="text-xs font-semibold text-slate-500 mr-4 uppercase">Current Cash</span>
            <span class="text-xl font-bold text-emerald-600"><?php echo formatPKR($balance); ?></span>
        </div>
        <button class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition-colors shadow-md shadow-emerald-100">
            <i class="fas fa-plus mr-2"></i> Record Expense
        </button>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <?php if($_GET['msg'] == 'success'): ?>
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg flex items-center">
        <i class="fas fa-check-circle mr-2"></i> Petty cash expense posted successfully!
    </div>
    <?php elseif($_GET['msg'] == 'deleted'): ?>
    <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-700 rounded-lg flex items-center">
        <i class="fas fa-trash-alt mr-2"></i> Petty cash entry deleted successfully.
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg flex items-center">
    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Transactions List -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
            <h3 class="font-bold text-slate-700">Recent Cash Movements</h3>
            <button class="text-blue-600 text-sm hover:underline">View Full Ledger</button>
        </div>
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase">Description</th>
                    <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase text-right">Out (Credit)</th>
                    <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase text-right">In (Debit)</th>
                    <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase text-center no-print">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php while($row = $transactions->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 text-sm text-slate-600"><?php echo date('d M Y', strtotime($row['entry_date'])); ?></td>
                    <td class="px-6 py-4 text-sm text-slate-800">
                        <?php echo $row['je_desc']; ?>
                        <span class="block text-[10px] text-slate-400"><?php echo $row['reference']; ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm font-bold text-rose-600 text-right">
                        <?php echo $row['credit'] > 0 ? formatPKR($row['credit']) : '-'; ?>
                    </td>
                    <td class="px-6 py-4 text-sm font-bold text-emerald-600 text-right">
                        <?php echo $row['debit'] > 0 ? formatPKR($row['debit']) : '-'; ?>
                    </td>
                    <td class="px-6 py-4 text-center no-print space-x-2">
                        <a href="edit_journal.php?id=<?php echo $row['id']; ?>" class="text-slate-400 hover:text-blue-600" title="Edit Entry">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="petty_cash.php?delete=<?php echo $row['id']; ?>"
                           onclick="return confirm('Are you sure you want to delete this petty cash entry? This action cannot be undone.')"
                           class="text-slate-400 hover:text-rose-600" title="Delete Entry">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Quick Entry Form -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="font-bold text-slate-800 mb-6 flex items-center">
            <i class="fas fa-bolt text-amber-500 mr-2"></i> Quick Cash Voucher
        </h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="post_expense" value="1">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Select Account</label>
                <select name="expense_account_id" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                    <option value="">Select Account</option>
                    <?php
                    mysqli_data_seek($all_accounts, 0);
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
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Amount (PKR)</label>
                <input type="number" step="0.01" name="amount" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm focus:ring-2 focus:ring-emerald-500 outline-none" placeholder="0.00">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Paid To / Description</label>
                <textarea name="description" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm focus:ring-2 focus:ring-emerald-500 outline-none" rows="2" placeholder="Tea and snacks for guests..."></textarea>
            </div>
            <button type="submit" class="w-full bg-slate-900 text-white py-3 rounded-lg font-bold hover:bg-slate-800 transition-all shadow-lg shadow-slate-200">
                Post Cash Expense
            </button>
        </form>

        <div class="mt-8 p-4 bg-amber-50 rounded-lg border border-amber-100">
            <h4 class="text-xs font-bold text-amber-800 uppercase mb-2">Accounting Rule</h4>
            <p class="text-[11px] text-amber-700 leading-relaxed">
                Recording a petty cash voucher will automatically:<br>
                1. <strong>Debit</strong> the selected account (Increases Expenses/Assets or Decreases Liabilities).<br>
                2. <strong>Credit</strong> the Petty Cash account (A002).
            </p>
        </div>
    </div>
</div>

<?php include 'layouts/footer.php'; ?>
