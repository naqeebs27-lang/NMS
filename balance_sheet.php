<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
include 'layouts/header.php';

$as_of_date = isset($_GET['as_of_date']) ? $_GET['as_of_date'] : date('Y-m-d');


// Calculate Net Profit up to this date for Retained Earnings
function getNetProfitToDate($as_of, $conn) {
    $revenue = getTotalByType('Revenue', $conn, null, $as_of);
    $cos = getTotalByType('Cost of Sales', $conn, null, $as_of);
    $expense = getTotalByType('Expense', $conn, null, $as_of);

    return $revenue - $cos - $expense;
}

$total_assets = 0;
$total_liabilities = 0;
$total_equity = 0;
$current_year_earnings = getNetProfitToDate($as_of_date, $conn);
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Balance Sheet</h2>
        <p class="text-slate-500">Summary of financial position: Assets, Liabilities, and Equity.</p>
    </div>
    <form method="GET" class="flex items-center space-x-2">
        <input type="date" name="as_of_date" value="<?php echo $as_of_date; ?>" class="bg-white border border-slate-200 rounded-lg p-2 text-sm">
        <button type="submit" class="bg-slate-800 text-white px-4 py-2 rounded-lg hover:bg-slate-900 transition-colors text-sm">Update</button>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 max-w-4xl mx-auto overflow-hidden">
    <div class="p-8 border-b border-slate-200 bg-slate-50 text-center">
        <h3 class="text-2xl font-bold text-slate-800 uppercase tracking-tight"><?php echo $company_name; ?></h3>
        <p class="text-slate-600 font-medium">Balance Sheet</p>
        <p class="text-sm text-slate-500">As at <?php echo date('F d, Y', strtotime($as_of_date)); ?></p>
    </div>

    <div class="p-8 space-y-12">
        <!-- ASSETS SECTION -->
        <section>
            <h4 class="text-lg font-bold text-slate-900 border-b-2 border-slate-900 pb-2 mb-6">ASSETS</h4>

            <div class="space-y-6">
                <div>
                    <h5 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Current Assets</h5>
                    <?php
                    $asset_accounts = $conn->query("SELECT a.id, a.name, a.code FROM accounts a JOIN account_types at ON a.type_id = at.id WHERE (at.name = 'Asset' OR at.name = 'Bank') AND (a.code LIKE '11%' OR a.code LIKE '12%' OR a.code LIKE '13%' OR a.code LIKE '15%' OR a.code LIKE 'A0%')");
                    while($acc = $asset_accounts->fetch_assoc()):
                        $bal = getAccountBalance($acc['id'], $conn, null, $as_of_date);
                        if($bal == 0) continue;
                        $total_assets += $bal;
                    ?>
                    <div class="flex justify-between py-2 text-slate-700 pl-4 border-b border-slate-50">
                        <span><?php echo $acc['name']; ?></span>
                        <span><?php echo number_format($bal, 2); ?></span>
                    </div>
                    <?php endwhile; ?>
                </div>

                <div>
                    <h5 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Fixed Assets</h5>
                    <?php
                    $fixed_assets = $conn->query("SELECT a.id, a.name, a.code FROM accounts a JOIN account_types at ON a.type_id = at.id WHERE at.name = 'Asset' AND a.code LIKE '14%'");
                    while($acc = $fixed_assets->fetch_assoc()):
                        $bal = getAccountBalance($acc['id'], $conn, null, $as_of_date);
                        if($bal == 0) continue;
                        $total_assets += $bal;
                    ?>
                    <div class="flex justify-between py-2 text-slate-700 pl-4 border-b border-slate-50">
                        <span><?php echo $acc['name']; ?></span>
                        <span><?php echo number_format($bal, 2); ?></span>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="flex justify-between py-4 font-bold text-slate-900 bg-slate-100 px-4 rounded-lg mt-6">
                <span>TOTAL ASSETS</span>
                <span class="underline decoration-double"><?php echo formatPKR($total_assets); ?></span>
            </div>
        </section>

        <!-- LIABILITIES & EQUITY SECTION -->
        <section>
            <h4 class="text-lg font-bold text-slate-900 border-b-2 border-slate-900 pb-2 mb-6">LIABILITIES & EQUITY</h4>

            <div class="space-y-8">
                <!-- Liabilities -->
                <div>
                    <h5 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Liabilities</h5>
                    <?php
                    $liability_accounts = $conn->query("SELECT a.id, a.name, a.code FROM accounts a JOIN account_types at ON a.type_id = at.id WHERE at.name = 'Liability'");
                    while($acc = $liability_accounts->fetch_assoc()):
                        $bal = getAccountBalance($acc['id'], $conn, null, $as_of_date);
                        if($bal == 0) continue;
                        $total_liabilities += $bal;
                    ?>
                    <div class="flex justify-between py-2 text-slate-700 pl-4 border-b border-slate-50">
                        <span><?php echo $acc['name']; ?></span>
                        <span><?php echo number_format($bal, 2); ?></span>
                    </div>
                    <?php endwhile; ?>
                    <div class="flex justify-between py-2 font-semibold text-slate-700 bg-slate-50 px-4 rounded mt-2">
                        <span>Total Liabilities</span>
                        <span><?php echo number_format($total_liabilities, 2); ?></span>
                    </div>
                </div>

                <!-- Equity -->
                <div>
                    <h5 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Equity</h5>
                    <?php
                    $equity_accounts = $conn->query("SELECT a.id, a.name, a.code FROM accounts a JOIN account_types at ON a.type_id = at.id WHERE at.name = 'Equity'");
                    while($acc = $equity_accounts->fetch_assoc()):
                        $bal = getAccountBalance($acc['id'], $conn, null, $as_of_date);
                        if($bal == 0) continue;
                        $total_equity += $bal;
                    ?>
                    <div class="flex justify-between py-2 text-slate-700 pl-4 border-b border-slate-50">
                        <span><?php echo $acc['name']; ?></span>
                        <span><?php echo number_format($bal, 2); ?></span>
                    </div>
                    <?php endwhile; ?>
                    <!-- Retained Earnings / Current Period -->
                    <div class="flex justify-between py-2 text-slate-700 pl-4 border-b border-slate-50">
                        <span>Current Year Earnings</span>
                        <span><?php echo number_format($current_year_earnings, 2); ?></span>
                    </div>
                    <?php $total_equity += $current_year_earnings; ?>

                    <div class="flex justify-between py-2 font-semibold text-slate-700 bg-slate-50 px-4 rounded mt-2">
                        <span>Total Equity</span>
                        <span><?php echo number_format($total_equity, 2); ?></span>
                    </div>
                </div>
            </div>

            <div class="flex justify-between py-4 font-bold text-slate-900 bg-slate-900 text-white px-6 rounded-lg mt-8">
                <span>TOTAL LIABILITIES & EQUITY</span>
                <span class="underline decoration-double"><?php echo formatPKR($total_liabilities + $total_equity); ?></span>
            </div>
        </section>
    </div>

    <!-- Integrity Check -->
    <?php if (abs($total_assets - ($total_liabilities + $total_equity)) > 0.01): ?>
    <div class="m-8 p-4 bg-rose-50 border border-rose-200 rounded-lg flex items-center text-rose-700">
        <i class="fas fa-exclamation-triangle mr-3 text-xl"></i>
        <div>
            <p class="font-bold">Accounting Equation Violation!</p>
            <p class="text-sm">Assets must equal Liabilities + Equity. Difference: <?php echo formatPKR(abs($total_assets - ($total_liabilities + $total_equity))); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <div class="p-6 bg-slate-50 border-t border-slate-200 text-xs text-slate-400 text-center">
        Report generated on <?php echo date('d M Y H:i:s'); ?>
    </div>
</div>

<?php include 'layouts/footer.php'; ?>
