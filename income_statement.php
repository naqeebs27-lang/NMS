<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
include 'layouts/header.php';

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');


$revenue = getTotalByType('Revenue', $conn, $start_date, $end_date);
$cos = getTotalByType('Cost of Sales', $conn, $start_date, $end_date);
$gross_profit = $revenue - $cos;

$operating_expenses = getTotalByType('Expense', $conn, $start_date, $end_date);
$net_profit = $gross_profit - $operating_expenses;
?>

<div class="mb-8 flex justify-between items-center no-print">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Profit & Loss Statement</h2>
        <p class="text-slate-500">Summary of revenues, costs, and expenses incurred during a specific period.</p>
    </div>
    <div class="flex items-center space-x-3">
        <form method="GET" class="flex items-center space-x-2">
            <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="bg-white border border-slate-200 rounded-lg p-2 text-sm">
            <span class="text-slate-400">to</span>
            <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="bg-white border border-slate-200 rounded-lg p-2 text-sm">
            <button type="submit" class="bg-slate-800 text-white px-4 py-2 rounded-lg hover:bg-slate-900 transition-colors text-sm">Generate</button>
        </form>
        <button onclick="window.print()" class="bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-50 transition-colors text-sm">
            <i class="fas fa-print mr-2"></i> Print Report
        </button>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 max-w-4xl mx-auto overflow-hidden">
    <div class="p-8 border-b border-slate-200 bg-slate-50 text-center relative">
        <div class="absolute left-8 top-8 w-16 h-16 opacity-10">
            <img src="nms_logo.png" alt="" class="w-full h-full object-contain">
        </div>
        <h3 class="text-2xl font-bold text-slate-800 uppercase tracking-tight"><?php echo $company_name; ?></h3>
        <p class="text-slate-600 font-medium">Profit & Loss Statement</p>
        <p class="text-sm text-slate-500">For the period <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></p>
    </div>

    <div class="p-8 space-y-8">
        <!-- Revenue Section -->
        <section>
            <h4 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2 mb-4">Revenue</h4>
            <?php
            $rev_accounts = $conn->query("SELECT a.id, a.name, a.code FROM accounts a JOIN account_types at ON a.type_id = at.id WHERE at.name = 'Revenue'");
            while($acc = $rev_accounts->fetch_assoc()):
                $bal = getAccountBalance($acc['id'], $conn, $start_date, $end_date);
                if($bal == 0) continue;
            ?>
            <div class="flex justify-between py-2 text-slate-700">
                <span><?php echo $acc['name']; ?></span>
                <span><?php echo number_format($bal, 2); ?></span>
            </div>
            <?php endwhile; ?>
            <div class="flex justify-between py-3 font-bold text-slate-900 border-t border-slate-100 mt-2 bg-slate-50/50 px-2 rounded">
                <span>Total Revenue</span>
                <span class="underline decoration-double decoration-slate-300"><?php echo number_format($revenue, 2); ?></span>
            </div>
        </section>

        <!-- Cost of Sales Section -->
        <?php if($cos > 0): ?>
        <section>
            <h4 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2 mb-4">Cost of Sales</h4>
            <div class="flex justify-between py-2 text-slate-700">
                <span>Purchases & Direct Costs</span>
                <span>(<?php echo number_format($cos, 2); ?>)</span>
            </div>
            <div class="flex justify-between py-3 font-bold text-slate-900 border-t border-slate-100 mt-2">
                <span>Gross Profit</span>
                <span class="border-b-2 border-slate-900"><?php echo number_format($gross_profit, 2); ?></span>
            </div>
        </section>
        <?php endif; ?>

        <!-- Operating Expenses Section -->
        <section>
            <h4 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2 mb-4">Operating Expenses</h4>
            <?php
            $exp_accounts = $conn->query("SELECT a.id, a.name, a.code FROM accounts a JOIN account_types at ON a.type_id = at.id WHERE at.name = 'Expense'");
            while($acc = $exp_accounts->fetch_assoc()):
                $bal = getAccountBalance($acc['id'], $conn, $start_date, $end_date);
                if($bal == 0) continue;
            ?>
            <div class="flex justify-between py-2 text-slate-700">
                <span><?php echo $acc['name']; ?></span>
                <span><?php echo number_format($bal, 2); ?></span>
            </div>
            <?php endwhile; ?>
            <div class="flex justify-between py-3 font-bold text-slate-900 border-t border-slate-100 mt-2 bg-slate-50/50 px-2 rounded">
                <span>Total Operating Expenses</span>
                <span class="underline decoration-slate-300">(<?php echo number_format($operating_expenses, 2); ?>)</span>
            </div>
        </section>

        <!-- Net Profit Section -->
        <section class="pt-6 border-t-2 border-slate-200">
            <div class="flex justify-between py-4 text-xl font-extrabold <?php echo $net_profit >= 0 ? 'text-emerald-600' : 'text-rose-600'; ?> bg-slate-900 rounded-lg px-6">
                <span>NET PROFIT / (LOSS)</span>
                <span><?php echo formatPKR($net_profit); ?></span>
            </div>
        </section>
    </div>

    <div class="p-6 bg-slate-50 border-t border-slate-200 text-xs text-slate-400 text-center">
        Report generated on <?php echo date('d M Y H:i:s'); ?>
    </div>
</div>

<?php include 'layouts/footer.php'; ?>
