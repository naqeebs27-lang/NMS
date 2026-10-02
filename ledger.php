<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
include 'layouts/header.php';

$account_id = isset($_GET['account_id']) ? intval($_GET['account_id']) : null;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$search = isset($_GET['search']) ? $_GET['search'] : '';

$accounts_res = $conn->query("SELECT id, name, code FROM accounts ORDER BY code ASC");

$ledger_data = [];
$account_info = null;
$opening_balance = 0;

if ($account_id) {
    // Get account info
    $stmt = $conn->prepare("SELECT a.*, at.normal_balance FROM accounts a JOIN account_types at ON a.type_id = at.id WHERE a.id = ?");
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $account_info = $stmt->get_result()->fetch_assoc();

    // Calculate opening balance before start_date
    $stmt = $conn->prepare("SELECT SUM(debit) as debits, SUM(credit) as credits FROM journal_entry_lines jel JOIN journal_entries je ON jel.journal_entry_id = je.id WHERE jel.account_id = ? AND je.entry_date < ?");
    $stmt->bind_param("is", $account_id, $start_date);
    $stmt->execute();
    $ob_res = $stmt->get_result()->fetch_assoc();

    if ($account_info['normal_balance'] == 'Debit') {
        $opening_balance = ($ob_res['debits'] ?? 0) - ($ob_res['credits'] ?? 0);
    } else {
        $opening_balance = ($ob_res['credits'] ?? 0) - ($ob_res['debits'] ?? 0);
    }

$search = isset($_GET['search']) ? $_GET['search'] : '';

// ... earlier code ...

    // Get transactions in range
    $sql = "SELECT je.entry_date, je.reference, je.description as je_desc, jel.description as line_desc, jel.debit, jel.credit
                            FROM journal_entry_lines jel
                            JOIN journal_entries je ON jel.journal_entry_id = je.id
                            WHERE jel.account_id = ? AND je.entry_date BETWEEN ? AND ?";
    if ($search) {
        $sql .= " AND (je.description LIKE ? OR jel.description LIKE ?)";
    }
    $sql .= " ORDER BY je.entry_date ASC, je.id ASC";

    $stmt = $conn->prepare($sql);
    if ($search) {
        $search_param = "%$search%";
        $stmt->bind_param("issss", $account_id, $start_date, $end_date, $search_param, $search_param);
    } else {
        $stmt->bind_param("iss", $account_id, $start_date, $end_date);
    }
    $stmt->execute();
    $ledger_data = $stmt->get_result();
}
?>

<div class="mb-8">
    <h2 class="text-2xl font-bold text-slate-800">General Ledger</h2>
    <p class="text-slate-500">Detailed record of all transactions for a specific account.</p>
</div>

<!-- Filters -->
<div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 mb-8">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Select Account</label>
            <select name="account_id" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm focus:ring-emerald-500">
                <option value="">-- Choose Account --</option>
                <?php while($acc = $accounts_res->fetch_assoc()): ?>
                    <option value="<?php echo $acc['id']; ?>" <?php echo $account_id == $acc['id'] ? 'selected' : ''; ?>>
                        <?php echo $acc['code'] . ' - ' . $acc['name']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">From Date</label>
            <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">To Date</label>
            <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Search (Memo)</label>
            <input type="text" name="search" value="<?php echo $search; ?>" placeholder="e.g. Ali Ahmed" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm focus:ring-emerald-500">
        </div>
        <div>
            <button type="submit" class="w-full bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition-colors">
                View Ledger
            </button>
        </div>
    </form>
</div>

<?php if ($account_id && $account_info): ?>
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="p-6 border-b border-slate-200 flex justify-between items-center bg-slate-50">
        <div>
            <h3 class="text-lg font-bold text-slate-800"><?php echo $account_info['name']; ?> (<?php echo $account_info['code']; ?>)</h3>
            <p class="text-sm text-slate-500">Statement Period: <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?></p>
        </div>
        <div class="text-right">
            <span class="text-xs font-semibold text-slate-500 uppercase block">Account Type</span>
            <span class="text-sm font-bold text-emerald-600"><?php echo $account_info['normal_balance']; ?> Normal Balance</span>
        </div>
    </div>

    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Date</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Reference</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Description</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Debit</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Credit</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Balance</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <!-- Opening Balance Row -->
            <tr class="bg-slate-50/50">
                <td class="px-6 py-4 text-sm text-slate-400"><?php echo date('d M Y', strtotime($start_date)); ?></td>
                <td class="px-6 py-4 text-sm font-mono text-slate-400">-</td>
                <td class="px-6 py-4 text-sm font-semibold text-slate-600 italic">Opening Balance B/F</td>
                <td class="px-6 py-4 text-right">-</td>
                <td class="px-6 py-4 text-right">-</td>
                <td class="px-6 py-4 text-sm font-bold text-slate-800 text-right"><?php echo formatPKR($opening_balance); ?></td>
            </tr>

            <?php
            $running_balance = $opening_balance;
            $total_debit = 0;
            $total_credit = 0;
            while($row = $ledger_data->fetch_assoc()):
                $total_debit += $row['debit'];
                $total_credit += $row['credit'];
                if ($account_info['normal_balance'] == 'Debit') {
                    $running_balance += ($row['debit'] - $row['credit']);
                } else {
                    $running_balance += ($row['credit'] - $row['debit']);
                }
            ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 text-sm text-slate-600"><?php echo date('d M Y', strtotime($row['entry_date'])); ?></td>
                <td class="px-6 py-4 text-sm font-mono text-slate-600"><?php echo $row['reference']; ?></td>
                <td class="px-6 py-4 text-sm text-slate-800"><?php echo $row['je_desc'] . ($row['line_desc'] ? ' - ' . $row['line_desc'] : ''); ?></td>
                <td class="px-6 py-4 text-sm text-slate-800 text-right"><?php echo $row['debit'] > 0 ? formatPKR($row['debit']) : '-'; ?></td>
                <td class="px-6 py-4 text-sm text-slate-800 text-right"><?php echo $row['credit'] > 0 ? formatPKR($row['credit']) : '-'; ?></td>
                <td class="px-6 py-4 text-sm font-bold text-slate-800 text-right"><?php echo formatPKR($running_balance); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot class="bg-slate-50 border-t border-slate-200 font-bold">
            <tr>
                <td colspan="3" class="px-6 py-4 text-sm text-slate-800 text-right">Totals for Period</td>
                <td class="px-6 py-4 text-sm text-slate-800 text-right"><?php echo formatPKR($total_debit); ?></td>
                <td class="px-6 py-4 text-sm text-slate-800 text-right"><?php echo formatPKR($total_credit); ?></td>
                <td class="px-6 py-4 text-sm text-slate-800 text-right">-</td>
            </tr>
            <tr class="bg-slate-100/50">
                <td colspan="5" class="px-6 py-4 text-sm font-bold text-slate-800 text-right uppercase tracking-wider">Closing Balance</td>
                <td class="px-6 py-4 text-sm font-extrabold text-emerald-600 text-right bg-emerald-50"><?php echo formatPKR($running_balance); ?></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php else: ?>
<div class="bg-white p-12 rounded-xl shadow-sm border border-slate-200 text-center">
    <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-400">
        <i class="fas fa-book-open text-2xl"></i>
    </div>
    <h3 class="text-lg font-semibold text-slate-800">Select an account to view ledger</h3>
    <p class="text-slate-500">Please choose an account from the filters above to see its detailed transaction history.</p>
</div>
<?php endif; ?>

<?php include 'layouts/footer.php'; ?>
