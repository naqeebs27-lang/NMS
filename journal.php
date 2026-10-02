<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$module = isset($_GET['module']) ? $_GET['module'] : '';

$sql = "SELECT je.*, SUM(jel.debit) as total_amount
        FROM journal_entries je
        LEFT JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
        WHERE 1=1";

if ($start_date) $sql .= " AND je.entry_date >= '" . $conn->real_escape_string($start_date) . "'";
if ($end_date) $sql .= " AND je.entry_date <= '" . $conn->real_escape_string($end_date) . "'";
if ($module) $sql .= " AND je.source_module = '" . $conn->real_escape_string($module) . "'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $sql .= " AND (je.description LIKE '%$s%' OR je.reference LIKE '%$s%')";
}

$sql .= " GROUP BY je.id
        ORDER BY je.entry_date DESC, je.id DESC";
$result = $conn->query($sql);

include 'layouts/header.php';
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">General Journal</h2>
        <p class="text-slate-500">Record of every financial transaction in chronological order.</p>
    </div>
    <div class="flex space-x-3">
        <button onclick="document.getElementById('filterSection').classList.toggle('hidden')" class="bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-50 transition-colors flex items-center no-print">
            <i class="fas fa-filter mr-2"></i> Filter
        </button>
        <button onclick="window.print()" class="bg-white text-slate-700 px-4 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors flex items-center no-print">
            <i class="fas fa-print mr-2"></i> Print Journal
        </button>
        <a href="create_journal.php" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition-colors inline-block no-print">
            <i class="fas fa-plus mr-2"></i> New Journal Entry
        </a>
    </div>
</div>

<!-- Filters Section -->
<div id="filterSection" class="<?php echo ($start_date || $end_date || $search || $module) ? '' : 'hidden'; ?> bg-white p-6 rounded-xl shadow-sm border border-slate-200 mb-8 no-print">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">From Date</label>
            <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm focus:ring-emerald-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">To Date</label>
            <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm focus:ring-emerald-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Module</label>
            <select name="module" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm focus:ring-emerald-500">
                <option value="">All Modules</option>
                <option value="manual" <?php echo $module == 'manual' ? 'selected' : ''; ?>>Manual</option>
                <option value="invoice" <?php echo $module == 'invoice' ? 'selected' : ''; ?>>Invoices</option>
                <option value="bill" <?php echo $module == 'bill' ? 'selected' : ''; ?>>Bills</option>
                <option value="expense" <?php echo $module == 'expense' ? 'selected' : ''; ?>>Expenses</option>
                <option value="payment" <?php echo $module == 'payment' ? 'selected' : ''; ?>>Payments</option>
                <option value="petty cash" <?php echo $module == 'petty cash' ? 'selected' : ''; ?>>Petty Cash</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Ref or desc..." class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm focus:ring-emerald-500">
        </div>
        <div class="flex space-x-2">
            <button type="submit" class="flex-1 bg-slate-800 text-white px-4 py-2 rounded-lg hover:bg-slate-900 transition-colors">
                Apply
            </button>
            <a href="journal.php" class="bg-slate-100 text-slate-600 px-4 py-2 rounded-lg hover:bg-slate-200 transition-colors">
                Reset
            </a>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Date</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Ref #</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Description</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Module</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Amount</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-center no-print">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php
            $overall_debit = 0;
            $overall_credit = 0;
            if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50 transition-colors align-top">
                    <td class="px-6 py-4 text-sm text-slate-600"><?php echo date('d M Y', strtotime($row['entry_date'])); ?></td>
                    <td class="px-6 py-4 text-sm font-mono text-slate-600"><?php echo $row['reference']; ?></td>
                    <td class="px-6 py-4">
                        <p class="text-sm font-semibold text-slate-800"><?php echo $row['description']; ?></p>
                        <div class="mt-2 space-y-1">
                            <?php
                            $lines_sql = "SELECT jel.*, a.name as account_name, a.code as account_code
                                          FROM journal_entry_lines jel
                                          JOIN accounts a ON jel.account_id = a.id
                                          WHERE jel.journal_entry_id = " . $row['id'];
                            $lines_res = $conn->query($lines_sql);
                            while($line = $lines_res->fetch_assoc()):
                                $overall_debit += $line['debit'];
                                $overall_credit += $line['credit'];
                            ?>
                            <div class="flex justify-between text-[11px] <?php echo $line['credit'] > 0 ? 'pl-4 text-slate-500' : 'font-medium text-slate-700'; ?>">
                                <span><?php echo $line['account_code'] . ' - ' . $line['account_name']; ?></span>
                                <span><?php echo $line['debit'] > 0 ? formatPKR($line['debit']) : formatPKR($line['credit']); ?></span>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-xs px-2 py-1 bg-slate-100 text-slate-600 rounded capitalize"><?php echo $row['source_module']; ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm font-bold text-slate-800 text-right">
                        <?php echo formatPKR($row['total_amount']); ?>
                    </td>
                    <td class="px-6 py-4 text-center actions-cell no-print">
                        <a href="edit_journal.php?id=<?php echo $row['id']; ?>" class="text-slate-400 hover:text-blue-600 mx-1" title="Edit Entry">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button class="text-slate-400 hover:text-emerald-600 mx-1" title="View Detail"><i class="fas fa-eye"></i></button>
                        <button onclick="window.print()" class="text-slate-400 hover:text-blue-600 mx-1" title="Print"><i class="fas fa-print"></i></button>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-slate-500 italic">
                        No transactions found matching the selected criteria.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot class="bg-slate-50 border-t-2 border-slate-200 font-bold">
            <tr>
                <td colspan="4" class="px-6 py-4 text-sm text-slate-800 text-right uppercase tracking-wider">Totals for Filtered Period</td>
                <td class="px-6 py-4 text-sm text-slate-800 text-right bg-slate-100/50">
                    <div class="flex justify-end space-x-8">
                        <div>
                            <div class="text-[10px] text-slate-500 uppercase leading-none mb-1">Total Debits</div>
                            <span class="text-emerald-700"><?php echo formatPKR($overall_debit); ?></span>
                        </div>
                        <div>
                            <div class="text-[10px] text-slate-500 uppercase leading-none mb-1">Total Credits</div>
                            <span class="text-blue-700"><?php echo formatPKR($overall_credit); ?></span>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 no-print"></td>
            </tr>
            <?php if (abs($overall_debit - $overall_credit) > 0.01): ?>
            <tr class="bg-red-50">
                <td colspan="4" class="px-6 py-2 text-xs text-red-600 text-right uppercase font-semibold">Out of Balance:</td>
                <td class="px-6 py-2 text-sm text-red-600 text-right font-bold">
                    <?php echo formatPKR(abs($overall_debit - $overall_credit)); ?>
                </td>
                <td class="px-6 py-2 no-print"></td>
            </tr>
            <?php endif; ?>
        </tfoot>
    </table>
</div>

<?php include 'layouts/footer.php'; ?>
