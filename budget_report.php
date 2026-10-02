<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
include 'layouts/header.php';

// Budget vs Actual Report
// Current Month Range
$start_date = date('Y-m-01');
$end_date = date('Y-m-t');
$fiscal_year = '2023-24';

$sql = "SELECT
            a.id, a.code, a.name, at.name as type_name, at.normal_balance,
            b.monthly_amount as budget,
            (SELECT SUM(jel.debit) FROM journal_entry_lines jel JOIN journal_entries je ON jel.journal_entry_id = je.id WHERE jel.account_id = a.id AND je.entry_date BETWEEN '$start_date' AND '$end_date') as actual_debit,
            (SELECT SUM(jel.credit) FROM journal_entry_lines jel JOIN journal_entries je ON jel.journal_entry_id = je.id WHERE jel.account_id = a.id AND je.entry_date BETWEEN '$start_date' AND '$end_date') as actual_credit
        FROM accounts a
        JOIN account_types at ON a.type_id = at.id
        LEFT JOIN budgets b ON a.id = b.account_id AND b.fiscal_year = '$fiscal_year'
        WHERE at.id IN (4, 5, 6)
        ORDER BY at.id, a.code ASC";

$result = $conn->query($sql);

?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Budget vs Actual Report</h2>
        <p class="text-slate-500">Performance for <?php echo date('F Y'); ?>.</p>
    </div>
    <div class="flex space-x-2">
        <a href="budgets.php" class="bg-white border border-slate-200 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-50">
            Edit Budgets
        </a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Account</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Budget</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Actual</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Variance</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-center w-32">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php while($row = $result->fetch_assoc()):
                $budget = (float)$row['budget'];
                $actual = 0;
                if ($row['normal_balance'] == 'Debit') {
                    $actual = (float)$row['actual_debit'] - (float)$row['actual_credit'];
                } else {
                    $actual = (float)$row['actual_credit'] - (float)$row['actual_debit'];
                }

                $variance = $actual - $budget;
                $is_revenue = ($row['type_name'] == 'Revenue');

                // For Revenue: Positive variance is good.
                // For Expense: Negative variance is good.
                $is_favorable = $is_revenue ? ($variance >= 0) : ($variance <= 0);
                if ($budget == 0) $is_favorable = true; // Neutral if no budget
            ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <p class="text-sm font-bold text-slate-800"><?php echo $row['name']; ?></p>
                    <p class="text-[10px] text-slate-400 uppercase font-mono"><?php echo $row['code']; ?> • <?php echo $row['type_name']; ?></p>
                </td>
                <td class="px-6 py-4 text-sm text-slate-600 text-right font-medium">
                    <?php echo formatPKR($budget); ?>
                </td>
                <td class="px-6 py-4 text-sm text-slate-800 text-right font-bold">
                    <?php echo formatPKR($actual); ?>
                </td>
                <td class="px-6 py-4 text-sm text-right font-bold <?php echo $is_favorable ? 'text-emerald-600' : 'text-rose-600'; ?>">
                    <?php echo ($variance > 0 ? '+' : '') . formatPKR($variance); ?>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-1 text-[9px] font-black rounded uppercase tracking-tighter
                        <?php echo $is_favorable ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                        <?php echo $is_favorable ? 'Favorable' : 'Unfavorable'; ?>
                    </span>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include 'layouts/footer.php'; ?>
