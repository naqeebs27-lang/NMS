<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Reference used for opening balances
$ob_ref = 'OB-2025';
$ob_date = '2025-08-01';

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_balances'])) {
    $balances = $_POST['balance'] ?? []; // account_id => amount
    $conn->begin_transaction();
    try {
        // 1. Find or create the balancing account (Equity/Capital)
        // We'll use L0001 (Capital) as the default balancing account if it exists
        $cap_res = $conn->query("SELECT id FROM accounts WHERE code = 'L0001' LIMIT 1");
        $capital_account = $cap_res->fetch_assoc();
        $capital_id = $capital_account['id'] ?? 0;

        if (!$capital_id) {
            throw new Exception("Balancing account (Capital L0001) not found.");
        }

        // 2. Delete existing Opening Balance entry
        $conn->query("DELETE jel FROM journal_entry_lines jel JOIN journal_entries je ON jel.journal_entry_id = je.id WHERE je.reference = '$ob_ref'");
        $conn->query("DELETE FROM journal_entries WHERE reference = '$ob_ref'");

        // 3. Prepare lines
        $lines = [];
        $net_balance = 0; // To balance against Capital

        foreach ($balances as $acc_id => $amount) {
            $amount = (float)$amount;
            if ($amount == 0) continue;

            // Get account type to determine if it's naturally Debit or Credit
            $acc_info = $conn->query("SELECT at.normal_balance FROM accounts a JOIN account_types at ON a.type_id = at.id WHERE a.id = $acc_id")->fetch_assoc();
            $normal = $acc_info['normal_balance'] ?? 'Debit';

            if ($normal == 'Debit') {
                $lines[] = ['account_id' => $acc_id, 'description' => 'Opening Balance', 'debit' => $amount, 'credit' => 0];
                $net_balance += $amount;
            } else {
                $lines[] = ['account_id' => $acc_id, 'description' => 'Opening Balance', 'debit' => 0, 'credit' => $amount];
                $net_balance -= $amount;
            }
        }

        // 4. Add balancing line to Capital
        if ($net_balance != 0) {
            if ($net_balance > 0) {
                // Total Debits > Credits, so Credit Capital
                $lines[] = ['account_id' => $capital_id, 'description' => 'Opening Balance Offset', 'debit' => 0, 'credit' => abs($net_balance)];
            } else {
                // Total Credits > Debits, so Debit Capital
                $lines[] = ['account_id' => $capital_id, 'description' => 'Opening Balance Offset', 'debit' => abs($net_balance), 'credit' => 0];
            }
        }

        if (!empty($lines)) {
            $res = postJournalEntry($conn, $ob_date, $ob_ref, 'Initial System Opening Balances', 'manual', $lines);
            if (!$res['success']) throw new Exception($res['error']);
        }

        $conn->commit();
        $success = "Opening balances updated successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// Get all accounts and their current opening balance from Ref OB-2025
$sql = "SELECT a.id, a.code, a.name, at.name as type_name, at.normal_balance,
               (SELECT SUM(jel.debit) FROM journal_entry_lines jel JOIN journal_entries je ON jel.journal_entry_id = je.id WHERE jel.account_id = a.id AND je.reference = '$ob_ref') as ob_debit,
               (SELECT SUM(jel.credit) FROM journal_entry_lines jel JOIN journal_entries je ON jel.journal_entry_id = je.id WHERE jel.account_id = a.id AND je.reference = '$ob_ref') as ob_credit
        FROM accounts a
        JOIN account_types at ON a.type_id = at.id
        WHERE a.code != 'L0001'
        ORDER BY a.code ASC";
$accounts = $conn->query($sql);

include 'layouts/header.php';
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Modify Opening Balances</h2>
        <p class="text-slate-500">Set the initial balances for all accounts as of <?php echo date('d M Y', strtotime($ob_date)); ?>.</p>
    </div>
    <div class="flex space-x-3">
        <a href="chart_of_accounts.php" class="bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-50 transition-colors">
            Back to Chart
        </a>
    </div>
</div>

<?php if (isset($success)): ?>
<div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg flex items-center">
    <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg flex items-center">
    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
</div>
<?php endif; ?>

<form method="POST">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase w-32">Code</th>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Account Name</th>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Type</th>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right w-64">Opening Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php while($row = $accounts->fetch_assoc()):
                    $val = 0;
                    if ($row['normal_balance'] == 'Debit') {
                        $val = $row['ob_debit'] - $row['ob_credit'];
                    } else {
                        $val = $row['ob_credit'] - $row['ob_debit'];
                    }
                ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 text-sm font-mono text-slate-600"><?php echo $row['code']; ?></td>
                    <td class="px-6 py-4 text-sm font-semibold text-slate-800"><?php echo $row['name']; ?></td>
                    <td class="px-6 py-4 text-xs text-slate-500"><?php echo $row['type_name']; ?></td>
                    <td class="px-6 py-4">
                        <input type="number" step="0.01" name="balance[<?php echo $row['id']; ?>]"
                               value="<?php echo number_format($val, 2, '.', ''); ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm text-right focus:ring-emerald-500">
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="bg-slate-50 p-6 rounded-xl border border-dashed border-slate-300 mb-8">
        <div class="flex items-start">
            <div class="bg-amber-100 p-2 rounded-lg text-amber-600 mr-4">
                <i class="fas fa-info-circle"></i>
            </div>
            <div>
                <h4 class="font-bold text-slate-800 text-sm">Balancing Logic</h4>
                <p class="text-xs text-slate-600 mt-1 leading-relaxed">
                    The software will automatically calculate the total Net Worth and post the difference to <strong>Capital (L0001)</strong>
                    to ensure your books stay balanced. All existing 'OB-2025' entries will be replaced with these new values.
                </p>
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" name="save_balances" class="bg-emerald-600 text-white px-8 py-3 rounded-xl hover:bg-emerald-700 shadow-lg shadow-emerald-100 transition-all font-bold">
            Update All Opening Balances
        </button>
    </div>
</form>

<?php include 'layouts/footer.php'; ?>
