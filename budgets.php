<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
include 'layouts/header.php';

// Handle budget updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_budget'])) {
    $account_id = $_POST['account_id'];
    $amount = $_POST['amount'];
    $fiscal_year = '2023-24'; // Hardcoded for demo

    $stmt = $conn->prepare("INSERT INTO budgets (account_id, fiscal_year, monthly_amount)
                            VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE monthly_amount = VALUES(monthly_amount)");
    $stmt->bind_param("isd", $account_id, $fiscal_year, $amount);
    $stmt->execute();
    $success_msg = "Budget updated successfully.";
}

// Get all Revenue and Expense accounts for budgeting
$accounts_res = $conn->query("SELECT a.id, a.code, a.name, at.name as type_name, b.monthly_amount
                              FROM accounts a
                              JOIN account_types at ON a.type_id = at.id
                              LEFT JOIN budgets b ON a.id = b.account_id AND b.fiscal_year = '2023-24'
                              WHERE at.id IN (4, 5, 6)
                              ORDER BY at.id, a.code ASC");

?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Budget Management</h2>
        <p class="text-slate-500">Set monthly targets for Revenue and Expenses for FY 2023-24.</p>
    </div>
</div>

<?php if (isset($success_msg)): ?>
<div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg flex items-center">
    <i class="fas fa-check-circle mr-2"></i> <?php echo $success_msg; ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Account Code</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Account Name</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Type</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right w-64">Monthly Budget (PKR)</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase w-24 text-center">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php while($row = $accounts_res->fetch_assoc()): ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <form method="POST">
                    <input type="hidden" name="account_id" value="<?php echo $row['id']; ?>">
                    <td class="px-6 py-4 text-sm font-mono text-slate-600"><?php echo $row['code']; ?></td>
                    <td class="px-6 py-4 text-sm font-medium text-slate-800"><?php echo $row['name']; ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-[10px] font-bold rounded-full uppercase <?php echo $row['type_name'] == 'Revenue' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                            <?php echo $row['type_name']; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <input type="number" step="0.01" name="amount" value="<?php echo $row['monthly_amount'] ?: '0.00'; ?>" class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-sm text-right focus:ring-emerald-500">
                    </td>
                    <td class="px-6 py-4 text-center">
                        <button type="submit" name="update_budget" class="text-emerald-600 hover:text-emerald-800 font-bold text-sm">
                            Save
                        </button>
                    </td>
                </form>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include 'layouts/footer.php'; ?>
