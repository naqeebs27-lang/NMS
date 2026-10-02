<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
include 'layouts/header.php';

$as_of_date = isset($_GET['as_of_date']) ? $_GET['as_of_date'] : date('Y-m-d');

$sql = "SELECT a.id, a.code, a.name, at.normal_balance,
               SUM(jel.debit) as total_debit,
               SUM(jel.credit) as total_credit
        FROM accounts a
        JOIN account_types at ON a.type_id = at.id
        LEFT JOIN journal_entry_lines jel ON a.id = jel.account_id
        LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
        WHERE je.entry_date <= ? OR je.id IS NULL
        GROUP BY a.id
        ORDER BY a.code ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $as_of_date);
$stmt->execute();
$result = $stmt->get_result();

$grand_total_debit = 0;
$grand_total_credit = 0;
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Trial Balance</h2>
        <p class="text-slate-500">A statement of all debits and credits in the double-entry ledger.</p>
    </div>
    <form method="GET" class="flex items-center space-x-2">
        <input type="date" name="as_of_date" value="<?php echo $as_of_date; ?>" class="bg-white border border-slate-200 rounded-lg p-2 text-sm">
        <button type="submit" class="bg-slate-800 text-white px-4 py-2 rounded-lg hover:bg-slate-900 transition-colors text-sm">Update</button>
        <button type="button" onclick="window.print()" class="bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-50 transition-colors text-sm">
            <i class="fas fa-print"></i>
        </button>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="p-6 border-b border-slate-200 bg-slate-50 text-center">
        <h3 class="text-xl font-bold text-slate-800 uppercase"><?php echo $company_name; ?></h3>
        <p class="text-slate-600 font-medium">Trial Balance</p>
        <p class="text-sm text-slate-500">As of <?php echo date('F d, Y', strtotime($as_of_date)); ?></p>
    </div>

    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Code</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Account Name</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Debit (PKR)</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Credit (PKR)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php while($row = $result->fetch_assoc()):
                $debit = $row['total_debit'] ?? 0;
                $credit = $row['total_credit'] ?? 0;

                // Only show accounts with activity or opening balances
                if ($debit == 0 && $credit == 0) continue;

                $grand_total_debit += $debit;
                $grand_total_credit += $credit;
            ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 text-sm font-medium text-slate-600"><?php echo $row['code']; ?></td>
                <td class="px-6 py-4 text-sm font-semibold text-slate-800"><?php echo $row['name']; ?></td>
                <td class="px-6 py-4 text-sm text-slate-800 text-right"><?php echo $debit > 0 ? number_format($debit, 2) : '-'; ?></td>
                <td class="px-6 py-4 text-sm text-slate-800 text-right"><?php echo $credit > 0 ? number_format($credit, 2) : '-'; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot class="bg-slate-900 text-white">
            <tr>
                <td colspan="2" class="px-6 py-4 text-sm font-bold uppercase tracking-wider">Total</td>
                <td class="px-6 py-4 text-sm font-bold text-right"><?php echo number_format($grand_total_debit, 2); ?></td>
                <td class="px-6 py-4 text-sm font-bold text-right"><?php echo number_format($grand_total_credit, 2); ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php if (abs($grand_total_debit - $grand_total_credit) > 0.01): ?>
<div class="mt-6 p-4 bg-rose-50 border border-rose-200 rounded-lg flex items-center text-rose-700">
    <i class="fas fa-exclamation-triangle mr-3 text-xl"></i>
    <div>
        <p class="font-bold">Unbalanced Books!</p>
        <p class="text-sm">Total debits do not equal total credits. Difference: <?php echo formatPKR(abs($grand_total_debit - $grand_total_credit)); ?></p>
    </div>
</div>
<?php else: ?>
<div class="mt-6 p-4 bg-emerald-50 border border-emerald-200 rounded-lg flex items-center text-emerald-700">
    <i class="fas fa-check-circle mr-3 text-xl"></i>
    <div>
        <p class="font-bold">Books are Balanced</p>
        <p class="text-sm">Double-entry accounting integrity verified for this period.</p>
    </div>
</div>
<?php endif; ?>

<?php include 'layouts/footer.php'; ?>
