<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
checkLogin();

$account_id = $_GET['account_id'] ?? null;
if (!$account_id) {
    header("Location: banking.php");
    exit;
}

// Fetch account details
$stmt = $conn->prepare("SELECT * FROM accounts WHERE id = ?");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();

if (!$account) {
    header("Location: banking.php");
    exit;
}

// Handle Statement Submission (Step 1)
$step = 1;
$statement_date = $_POST['statement_date'] ?? date('Y-m-d');
$ending_balance = (float)($_POST['ending_balance'] ?? 0);

if (isset($_POST['start_reconciliation'])) {
    $step = 2;
}

// Handle Finalization (Step 2)
if (isset($_POST['finalize_reconciliation'])) {
    $recon_id = $_POST['reconciliation_id'];
    $cleared_lines = $_POST['cleared_lines'] ?? [];

    $conn->begin_transaction();
    try {
        // Update lines as cleared
        if (!empty($cleared_lines)) {
            $ids = implode(',', array_map('intval', $cleared_lines));
            $conn->query("UPDATE journal_entry_lines SET cleared_at = NOW(), reconciliation_id = $recon_id WHERE id IN ($ids)");
        }

        // Finalize reconciliation record
        $stmt = $conn->prepare("UPDATE reconciliations SET is_finalized = 1 WHERE id = ?");
        $stmt->bind_param("i", $recon_id);
        $stmt->execute();

        $conn->commit();
        logAction($conn, 'Finalized Bank Reconciliation', 'Banking', $recon_id, "Account: " . $account['name']);
        header("Location: banking.php?success=reconciled");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// If starting Step 2, create the recon record
if ($step == 2 && !isset($_POST['finalize_reconciliation'])) {
    $stmt = $conn->prepare("INSERT INTO reconciliations (account_id, statement_date, statement_ending_balance) VALUES (?, ?, ?)");
    $stmt->bind_param("isd", $account_id, $statement_date, $ending_balance);
    $stmt->execute();
    $reconciliation_id = $conn->insert_id;
}

include 'layouts/header.php';
?>

<div class="mb-8">
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="banking.php" class="text-slate-500 hover:text-emerald-600 text-sm font-medium">Banking</a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fas fa-chevron-right text-slate-300 text-xs mx-2"></i>
                    <span class="text-slate-800 text-sm font-bold">Reconcile</span>
                </div>
            </li>
        </ol>
    </nav>
    <h2 class="text-2xl font-bold text-slate-800">Reconcile <?php echo $account['name']; ?></h2>
</div>

<?php if ($step == 1): ?>
<div class="max-w-xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8">
        <form method="POST">
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Statement Ending Date</label>
                    <input type="date" name="statement_date" value="<?php echo date('Y-m-d'); ?>" required
                           class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Statement Ending Balance</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500 text-sm">Rs.</span>
                        <input type="number" step="0.01" name="ending_balance" required
                               class="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none"
                               placeholder="0.00">
                    </div>
                </div>
                <button type="submit" name="start_reconciliation"
                        class="w-full bg-emerald-600 text-white py-3 rounded-lg font-bold hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-100">
                    Start Reconciling
                </button>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<!-- Reconciliation Interface (Step 2) -->
<form method="POST">
    <input type="hidden" name="reconciliation_id" value="<?php echo $reconciliation_id; ?>">
    <input type="hidden" name="ending_balance" id="statement_ending_balance" value="<?php echo $ending_balance; ?>">

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar Summary -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 sticky top-8">
                <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4">Summary</h3>

                <div class="space-y-4">
                    <div>
                        <p class="text-[10px] text-slate-500 uppercase font-bold">Statement Balance</p>
                        <p class="text-xl font-bold text-slate-800"><?php echo formatPKR($ending_balance); ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-500 uppercase font-bold">Cleared Balance</p>
                        <p class="text-xl font-bold text-emerald-600" id="cleared_balance_display">Rs. 0.00</p>
                    </div>
                    <div class="pt-4 border-t border-slate-100">
                        <p class="text-[10px] text-slate-500 uppercase font-bold">Difference</p>
                        <p class="text-xl font-bold text-rose-500" id="difference_display"><?php echo formatPKR($ending_balance); ?></p>
                    </div>
                </div>

                <button type="submit" name="finalize_reconciliation" id="finalize_btn" disabled
                        class="w-full mt-8 bg-slate-200 text-slate-500 py-3 rounded-lg font-bold transition-all cursor-not-allowed">
                    Finish Now
                </button>
            </div>
        </div>

        <!-- Transactions List -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase">Clear</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase">Date</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase">Reference</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase">Description</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase text-right">Debit</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase text-right">Credit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php
                        $lines = $conn->query("SELECT jel.*, je.entry_date, je.reference
                                              FROM journal_entry_lines jel
                                              JOIN journal_entries je ON jel.journal_entry_id = je.id
                                              WHERE jel.account_id = $account_id AND jel.cleared_at IS NULL
                                              ORDER BY je.entry_date ASC");

                        while($row = $lines->fetch_assoc()):
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 text-center">
                                <input type="checkbox" name="cleared_lines[]" value="<?php echo $row['id']; ?>"
                                       data-amount="<?php echo $row['debit'] - $row['credit']; ?>"
                                       class="recon-checkbox w-5 h-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600"><?php echo date('d M, Y', strtotime($row['entry_date'])); ?></td>
                            <td class="px-6 py-4 text-sm font-medium text-slate-800"><?php echo $row['reference']; ?></td>
                            <td class="px-6 py-4 text-sm text-slate-600"><?php echo $row['description']; ?></td>
                            <td class="px-6 py-4 text-sm text-right text-emerald-600"><?php echo $row['debit'] > 0 ? number_format($row['debit'], 2) : '-'; ?></td>
                            <td class="px-6 py-4 text-sm text-right text-rose-600"><?php echo $row['credit'] > 0 ? number_format($row['credit'], 2) : '-'; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.recon-checkbox');
    const clearedDisplay = document.getElementById('cleared_balance_display');
    const diffDisplay = document.getElementById('difference_display');
    const finalizeBtn = document.getElementById('finalize_btn');
    const endingBalance = parseFloat(document.getElementById('statement_ending_balance').value);

    function updateTotals() {
        let cleared = 0;
        checkboxes.forEach(cb => {
            if (cb.checked) {
                cleared += parseFloat(cb.getAttribute('data-amount'));
            }
        });

        const diff = endingBalance - cleared;

        clearedDisplay.innerText = 'Rs. ' + cleared.toLocaleString(undefined, {minimumFractionDigits: 2});
        diffDisplay.innerText = 'Rs. ' + diff.toLocaleString(undefined, {minimumFractionDigits: 2});

        if (Math.abs(diff) < 0.01) {
            diffDisplay.className = 'text-xl font-bold text-emerald-600';
            finalizeBtn.disabled = false;
            finalizeBtn.className = 'w-full mt-8 bg-emerald-600 text-white py-3 rounded-lg font-bold hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-100';
        } else {
            diffDisplay.className = 'text-xl font-bold text-rose-500';
            finalizeBtn.disabled = true;
            finalizeBtn.className = 'w-full mt-8 bg-slate-200 text-slate-500 py-3 rounded-lg font-bold transition-all cursor-not-allowed';
        }
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateTotals);
    });
});
</script>
<?php endif; ?>

<?php include 'layouts/footer.php'; ?>
