<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
checkLogin();

$query = "SELECT a.*,
          (SELECT MAX(statement_date) FROM reconciliations WHERE account_id = a.id) as last_reconciled,
          (SELECT SUM(debit - credit) FROM journal_entry_lines WHERE account_id = a.id) as balance
          FROM accounts a
          JOIN account_types at ON a.type_id = at.id
          WHERE at.name = 'Bank'
          ORDER BY a.code ASC";
$banks = $conn->query($query);

include 'layouts/header.php';
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Bank Accounts</h2>
        <p class="text-slate-500">Manage your bank balances and reconciliations</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php while($bank = $banks->fetch_assoc()): ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-shadow">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center text-emerald-600">
                    <i class="fas fa-university text-xl"></i>
                </div>
                <span class="text-xs font-semibold px-2 py-1 rounded bg-slate-100 text-slate-600 uppercase">
                    <?php echo $bank['code']; ?>
                </span>
            </div>

            <h3 class="text-lg font-bold text-slate-800 mb-1"><?php echo $bank['name']; ?></h3>
            <p class="text-sm text-slate-500 mb-6"><?php echo $bank['description']; ?></p>

            <div class="space-y-3">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Current Balance:</span>
                    <span class="font-bold text-slate-800"><?php echo formatPKR($bank['balance'] ?? 0); ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Last Reconciled:</span>
                    <span class="text-slate-800"><?php echo $bank['last_reconciled'] ? date('d M, Y', strtotime($bank['last_reconciled'])) : 'Never'; ?></span>
                </div>
            </div>
        </div>

        <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex gap-2 no-print">
            <a href="ledger.php?account_id=<?php echo $bank['id']; ?>" class="flex-1 text-center py-2 px-4 bg-white border border-slate-200 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                View Ledger
            </a>
            <a href="reconcile.php?account_id=<?php echo $bank['id']; ?>" class="flex-1 text-center py-2 px-4 bg-emerald-600 rounded-lg text-sm font-semibold text-white hover:bg-emerald-700 transition-colors shadow-sm">
                Reconcile
            </a>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<?php include 'layouts/footer.php'; ?>
