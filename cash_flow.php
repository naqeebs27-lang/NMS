<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
include 'layouts/header.php';

// Cash Flow Statement (Direct Method Approximation)
// We look at all transactions involving Cash/Bank accounts (Code 11xx)

function getCashFlowByCategory($conn, $category) {
    // Categories: Operating, Investing, Financing
    $operating_types = [4, 5, 6]; // Revenue, Expense, COS
    $investing_codes = ['1701', '1702', '1703', '1801']; // Fixed Assets
    $financing_types = [2, 3]; // Liabilities (Loans), Equity (but exclude AP/Tax)

    // This is a simplified logic:
    // Operating: Cash vs (Revenue/Expense accounts) + Changes in AR/AP
    // Investing: Cash vs Fixed Asset accounts
    // Financing: Cash vs Capital/Loans

    $total = 0;

    // Get all cash account IDs
    $cash_acc_res = $conn->query("SELECT id FROM accounts WHERE code LIKE '11%'");
    $cash_ids = [];
    while($row = $cash_acc_res->fetch_assoc()) $cash_ids[] = $row['id'];
    $cash_ids_str = implode(',', $cash_ids);

    if ($category == 'Operating') {
        // Cash Received from Customers (Sales/AR)
        $sql = "SELECT SUM(jel.debit - jel.credit) as net
                FROM journal_entry_lines jel
                JOIN journal_entries je ON jel.journal_entry_id = je.id
                JOIN accounts a ON jel.account_id = a.id
                WHERE a.id IN ($cash_ids_str)
                AND (je.source_module = 'Sales' OR je.source_module = 'Payments')";
        $res = $conn->query($sql)->fetch_assoc();
        $total += $res['net'] ?? 0;

        // Cash Paid for Expenses
        $sql = "SELECT SUM(jel.debit - jel.credit) as net
                FROM journal_entry_lines jel
                JOIN journal_entries je ON jel.journal_entry_id = je.id
                JOIN accounts a ON jel.account_id = a.id
                WHERE a.id IN ($cash_ids_str)
                AND (je.source_module = 'Expenses' OR je.source_module = 'Purchases')";
        $res = $conn->query($sql)->fetch_assoc();
        $total += $res['net'] ?? 0;

    } elseif ($category == 'Investing') {
        // Cash vs Fixed Assets
        $sql = "SELECT SUM(jel.debit - jel.credit) as net
                FROM journal_entry_lines jel
                JOIN journal_entries je ON jel.journal_entry_id = je.id
                WHERE jel.account_id IN ($cash_ids_str)
                AND EXISTS (
                    SELECT 1 FROM journal_entry_lines jel2
                    JOIN accounts a2 ON jel2.account_id = a2.id
                    WHERE jel2.journal_entry_id = je.id
                    AND a2.code IN ('1701', '1702', '1703')
                )";
        $res = $conn->query($sql)->fetch_assoc();
        $total = $res['net'] ?? 0;
    } elseif ($category == 'Financing') {
        // Cash vs Equity/Long Term Loans
        $sql = "SELECT SUM(jel.debit - jel.credit) as net
                FROM journal_entry_lines jel
                JOIN journal_entries je ON jel.journal_entry_id = je.id
                WHERE jel.account_id IN ($cash_ids_str)
                AND EXISTS (
                    SELECT 1 FROM journal_entry_lines jel2
                    JOIN accounts a2 ON jel2.account_id = a2.id
                    JOIN account_types at2 ON a2.type_id = at2.id
                    WHERE jel2.journal_entry_id = je.id
                    AND at2.id IN (2, 3) AND a2.code NOT IN ('2101', '2301', '2401')
                )";
        $res = $conn->query($sql)->fetch_assoc();
        $total = $res['net'] ?? 0;
    }

    return $total;
}

$op_cash = getCashFlowByCategory($conn, 'Operating');
$inv_cash = getCashFlowByCategory($conn, 'Investing');
$fin_cash = getCashFlowByCategory($conn, 'Financing');
$net_increase = $op_cash + $inv_cash + $fin_cash;

// Opening Balance (Start of Year/Period)
$opening_bal = 2500000; // Demo hardcoded or calculated from OB entry
$closing_bal = $opening_bal + $net_increase;

?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Statement of Cash Flows</h2>
        <p class="text-slate-500">Tracking physical cash movement across the business.</p>
    </div>
    <div class="flex space-x-3">
        <button class="bg-white border border-slate-200 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-50">
            <i class="fas fa-print mr-2"></i> Print
        </button>
        <button class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-emerald-700">
            <i class="fas fa-download mr-2"></i> Export PDF
        </button>
    </div>
</div>

<div class="max-w-4xl mx-auto space-y-8">
    <!-- Summary Header -->
    <div class="bg-emerald-900 rounded-2xl p-8 text-white shadow-xl">
        <div class="flex justify-between items-end">
            <div>
                <p class="text-emerald-300 text-xs font-bold uppercase tracking-widest mb-1">Net Cash Position</p>
                <h3 class="text-4xl font-bold"><?php echo formatPKR($closing_bal); ?></h3>
            </div>
            <div class="text-right">
                <p class="text-emerald-300 text-xs font-bold uppercase tracking-widest mb-1">Net Change</p>
                <p class="text-xl font-bold <?php echo $net_increase >= 0 ? 'text-emerald-400' : 'text-rose-400'; ?>">
                    <?php echo ($net_increase >= 0 ? '+' : '') . formatPKR($net_increase); ?>
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="px-8 py-4 text-sm font-bold text-slate-800">Cash Flow Activity</th>
                    <th class="px-8 py-4 text-sm font-bold text-slate-800 text-right">Amount (PKR)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <!-- Operating -->
                <tr class="bg-slate-50/50">
                    <td class="px-8 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Operating Activities</td>
                    <td></td>
                </tr>
                <tr>
                    <td class="px-12 py-4 text-sm text-slate-600">Net Cash from Operations (Sales - Expenses)</td>
                    <td class="px-8 py-4 text-sm font-bold text-slate-800 text-right"><?php echo formatPKR($op_cash); ?></td>
                </tr>

                <!-- Investing -->
                <tr class="bg-slate-50/50">
                    <td class="px-8 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Investing Activities</td>
                    <td></td>
                </tr>
                <tr>
                    <td class="px-12 py-4 text-sm text-slate-600">Purchase/Sale of Property & Equipment</td>
                    <td class="px-8 py-4 text-sm font-bold text-slate-800 text-right"><?php echo formatPKR($inv_cash); ?></td>
                </tr>

                <!-- Financing -->
                <tr class="bg-slate-50/50">
                    <td class="px-8 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Financing Activities</td>
                    <td></td>
                </tr>
                <tr>
                    <td class="px-12 py-4 text-sm text-slate-600">Owner's Capital, Loans, and Drawings</td>
                    <td class="px-8 py-4 text-sm font-bold text-slate-800 text-right"><?php echo formatPKR($fin_cash); ?></td>
                </tr>

                <!-- Totals -->
                <tr class="bg-slate-50 border-t-2 border-slate-200">
                    <td class="px-8 py-4 text-sm font-bold text-slate-800">Net Increase/(Decrease) in Cash</td>
                    <td class="px-8 py-4 text-sm font-extrabold text-slate-900 text-right border-t-2 border-slate-200"><?php echo formatPKR($net_increase); ?></td>
                </tr>
                <tr>
                    <td class="px-8 py-4 text-sm text-slate-500 italic">Cash at Beginning of Period</td>
                    <td class="px-8 py-4 text-sm text-slate-500 text-right"><?php echo formatPKR($opening_bal); ?></td>
                </tr>
                <tr class="bg-emerald-50">
                    <td class="px-8 py-5 text-base font-bold text-emerald-900">Cash at End of Period</td>
                    <td class="px-8 py-5 text-base font-black text-emerald-900 text-right"><?php echo formatPKR($closing_bal); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php include 'layouts/footer.php'; ?>
