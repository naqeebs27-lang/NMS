<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Create expenses table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_date` date NOT NULL,
  `account_id` int(11) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `bank_account_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text,
  `reference` varchar(100) DEFAULT NULL,
  `journal_entry_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`),
  FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$result = $conn->query("SELECT e.*, a.name as expense_account_name, ba.name as bank_name
                        FROM expenses e
                        JOIN accounts a ON e.account_id = a.id
                        LEFT JOIN accounts ba ON e.bank_account_id = ba.id
                        ORDER BY e.expense_date DESC");

include 'layouts/header.php';
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Expenses</h2>
        <p class="text-slate-500">Track operating costs and direct cash/bank expenditures.</p>
    </div>
    <a href="record_expense.php" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition-colors shadow-lg shadow-emerald-100 inline-flex items-center">
        <i class="fas fa-plus mr-2"></i> Record Expense
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Date</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Category / Account</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Payment Via</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Ref / Description</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Amount</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 text-sm text-slate-600"><?php echo date('d M Y', strtotime($row['expense_date'])); ?></td>
                    <td class="px-6 py-4 text-sm font-semibold text-slate-800"><?php echo $row['expense_account_name']; ?></td>
                    <td class="px-6 py-4">
                        <span class="text-xs px-2 py-1 bg-blue-50 text-blue-700 rounded font-medium">
                            <?php echo $row['bank_name'] ?: $row['payment_method']; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-sm text-slate-800"><?php echo $row['description']; ?></p>
                        <p class="text-[10px] text-slate-400 font-mono"><?php echo $row['reference']; ?></p>
                    </td>
                    <td class="px-6 py-4 text-sm font-bold text-rose-600 text-right">
                        <?php echo formatPKR($row['amount']); ?>
                    </td>
                    <td class="px-6 py-4 text-center actions-cell">
                        <button class="text-slate-400 hover:text-emerald-600 mx-1"><i class="fas fa-eye"></i></button>
                        <button class="text-slate-400 hover:text-rose-600 mx-1"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                        <i class="fas fa-wallet text-4xl mb-3 block text-slate-200"></i>
                        No expenses recorded yet.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'layouts/footer.php'; ?>
