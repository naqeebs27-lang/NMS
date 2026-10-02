<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
include 'layouts/header.php';

// Calculate Dashboard values (Current Month)
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

$total_income = getTotalByType('Revenue', $conn, $month_start, $month_end);
$total_expenses = getTotalByType('Expense', $conn, $month_start, $month_end) + getTotalByType('Cost of Sales', $conn, $month_start, $month_end);
$net_profit = $total_income - $total_expenses;

// Get Bank/Cash Balances (A001 to A004)
$bank_res = $conn->query("SELECT SUM(IF(at.normal_balance='Debit', jel.debit-jel.credit, jel.credit-jel.debit)) as balance
                         FROM journal_entry_lines jel
                         JOIN accounts a ON jel.account_id = a.id
                         JOIN account_types at ON a.type_id = at.id
                         WHERE a.code IN ('A001', 'A002', 'A003', 'A004')");
$bank_balance = $bank_res->fetch_assoc()['balance'] ?? 0;

// Receivables (Any account in Assets type that isn't cash/bank - or specific if known)
$receivable_res = $conn->query("SELECT SUM(jel.debit - jel.credit) as balance
                               FROM journal_entry_lines jel
                               JOIN accounts a ON jel.account_id = a.id
                               WHERE a.type_id = 1 AND a.code NOT IN ('A001', 'A002', 'A003', 'A004')");
$accounts_receivable = $receivable_res->fetch_assoc()['balance'] ?? 0;

// Payables (Type 2 Liabilities)
$payable_res = $conn->query("SELECT SUM(jel.credit - jel.debit) as balance
                            FROM journal_entry_lines jel
                            JOIN accounts a ON jel.account_id = a.id
                            WHERE a.type_id = 2");
$accounts_payable = $payable_res->fetch_assoc()['balance'] ?? 0;

?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4 mb-8">
    <!-- Summary Cards -->
    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-100">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 bg-emerald-100 text-emerald-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-arrow-trend-up"></i>
            </div>
        </div>
        <h3 class="text-slate-500 text-[11px] font-bold uppercase tracking-wider">Income (Month)</h3>
        <p class="text-xl font-black text-slate-800 mt-1"><?php echo formatPKR($total_income); ?></p>
    </div>

    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-100">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 bg-rose-100 text-rose-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-arrow-trend-down"></i>
            </div>
        </div>
        <h3 class="text-slate-500 text-[11px] font-bold uppercase tracking-wider">Expenses (Month)</h3>
        <p class="text-xl font-black text-slate-800 mt-1"><?php echo formatPKR($total_expenses); ?></p>
    </div>

    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-100">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-chart-line"></i>
            </div>
        </div>
        <h3 class="text-slate-500 text-[11px] font-bold uppercase tracking-wider">Net Profit (Month)</h3>
        <p class="text-xl font-black text-slate-800 mt-1"><?php echo formatPKR($net_profit); ?></p>
    </div>

    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-100">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 bg-amber-100 text-amber-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-university"></i>
            </div>
        </div>
        <h3 class="text-slate-500 text-[11px] font-bold uppercase tracking-wider">Bank Balance</h3>
        <p class="text-xl font-black text-slate-800 mt-1"><?php echo formatPKR($bank_balance); ?></p>
    </div>

    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-100">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-hand-holding-dollar"></i>
            </div>
        </div>
        <h3 class="text-slate-500 text-[11px] font-bold uppercase tracking-wider">Receivables</h3>
        <p class="text-xl font-black text-slate-800 mt-1"><?php echo formatPKR($accounts_receivable); ?></p>
    </div>

    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-100">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 bg-slate-100 text-slate-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
        </div>
        <h3 class="text-slate-500 text-[11px] font-bold uppercase tracking-wider">Payables</h3>
        <p class="text-xl font-black text-slate-800 mt-1"><?php echo formatPKR($accounts_payable); ?></p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Main Chart -->
    <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-slate-100">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-800">Income vs Expenses</h3>
        </div>
        <div class="h-[300px]">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Quick Actions & Recent Transactions -->
    <div class="space-y-8">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-2 gap-3">
                <a href="create_invoice.php" class="flex flex-col items-center justify-center p-4 bg-slate-50 hover:bg-emerald-50 hover:text-emerald-700 rounded-xl transition-all group">
                    <i class="fas fa-plus-circle text-xl mb-2 text-slate-400 group-hover:text-emerald-500"></i>
                    <span class="text-xs font-semibold">New Invoice</span>
                </a>
                <a href="create_bill.php" class="flex flex-col items-center justify-center p-4 bg-slate-50 hover:bg-emerald-50 hover:text-emerald-700 rounded-xl transition-all group">
                    <i class="fas fa-receipt text-xl mb-2 text-slate-400 group-hover:text-emerald-500"></i>
                    <span class="text-xs font-semibold">Add Bill</span>
                </a>
                <a href="create_journal.php" class="flex flex-col items-center justify-center p-4 bg-slate-50 hover:bg-emerald-50 hover:text-emerald-700 rounded-xl transition-all group">
                    <i class="fas fa-exchange-alt text-xl mb-2 text-slate-400 group-hover:text-emerald-500"></i>
                    <span class="text-xs font-semibold">Journal Entry</span>
                </a>
                <a href="income_statement.php" class="flex flex-col items-center justify-center p-4 bg-slate-50 hover:bg-emerald-50 hover:text-emerald-700 rounded-xl transition-all group">
                    <i class="fas fa-file-contract text-xl mb-2 text-slate-400 group-hover:text-emerald-500"></i>
                    <span class="text-xs font-semibold">Reports</span>
                </a>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Recent Transactions</h3>
            <div class="space-y-4">
                <?php
                $recent_sql = "SELECT je.*, SUM(jel.debit) as amount
                               FROM journal_entries je
                               JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
                               GROUP BY je.id
                               ORDER BY je.entry_date DESC LIMIT 5";
                $recent_res = $conn->query($recent_sql);
                while($row = $recent_res->fetch_assoc()):
                ?>
                <div class="flex items-center justify-between border-b border-slate-50 pb-3 last:border-0 last:pb-0">
                    <div>
                        <p class="text-sm font-semibold text-slate-800"><?php echo $row['description']; ?></p>
                        <p class="text-xs text-slate-400"><?php echo date('M d, Y', strtotime($row['entry_date'])); ?> • <?php echo $row['reference']; ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-slate-800"><?php echo formatPKR($row['amount']); ?></p>
                        <span class="text-[10px] px-2 py-0.5 bg-slate-100 text-slate-600 rounded-full uppercase"><?php echo $row['source_module']; ?></span>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Get data for the last 6 months for the chart
$chart_labels = [];
$chart_income = [];
$chart_expenses = [];

for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $display_month = date('M', strtotime("-$i months"));
    $start = $month . '-01';
    $end = date('Y-m-t', strtotime($start));

    $chart_labels[] = $display_month;
    $chart_income[] = getTotalByType('Revenue', $conn, $start, $end);
    $chart_expenses[] = getTotalByType('Expense', $conn, $start, $end) + getTotalByType('Cost of Sales', $conn, $start, $end);
}
?>

<script>
const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: 'Income',
            data: <?php echo json_encode($chart_income); ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true,
            tension: 0.4
        }, {
            label: 'Expenses',
            data: <?php echo json_encode($chart_expenses); ?>,
            borderColor: '#f43f5e',
            backgroundColor: 'rgba(244, 63, 94, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
            x: { grid: { display: false } }
        }
    }
});
</script>

<?php include 'layouts/footer.php'; ?>
