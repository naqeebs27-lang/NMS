<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if tables exist, if not, create them (fallback if sql file wasn't run)
$conn->query("CREATE TABLE IF NOT EXISTS `recurring_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `frequency` enum('Weekly', 'Monthly', 'Quarterly', 'Yearly') NOT NULL,
  `start_date` date NOT NULL,
  `next_generation_date` date NOT NULL,
  `last_generation_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Active', 'Paused', 'Completed') DEFAULT 'Active',
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `description` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$result = $conn->query("SELECT ri.*, c.name as customer_name
                        FROM recurring_invoices ri
                        JOIN customers c ON ri.customer_id = c.id
                        ORDER BY ri.next_generation_date ASC");

include 'layouts/header.php';
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Recurring Invoices</h2>
        <p class="text-slate-500">Automate your billing for subscriptions and retainers.</p>
    </div>
    <div class="flex space-x-3">
        <a href="process_recurring.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors inline-block">
            <i class="fas fa-cog mr-2"></i> Process Due Now
        </a>
        <a href="create_recurring_invoice.php" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition-colors inline-block">
            <i class="fas fa-plus mr-2"></i> New Recurring Invoice
        </a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Customer</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Frequency</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Next Date</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Status</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Amount</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 text-sm text-slate-800 font-medium"><?php echo $row['customer_name']; ?></td>
                    <td class="px-6 py-4 text-sm text-slate-600"><?php echo $row['frequency']; ?></td>
                    <td class="px-6 py-4 text-sm text-slate-600"><?php echo date('d M Y', strtotime($row['next_generation_date'])); ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-[10px] font-bold rounded-full uppercase
                            <?php
                            switch($row['status']) {
                                case 'Active': echo 'bg-emerald-100 text-emerald-700'; break;
                                case 'Paused': echo 'bg-amber-100 text-amber-700'; break;
                                case 'Completed': echo 'bg-slate-100 text-slate-600'; break;
                            }
                            ?>">
                            <?php echo $row['status']; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm font-bold text-slate-800 text-right">
                        <?php echo formatPKR($row['total_amount']); ?>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <a href="edit_recurring.php?id=<?php echo $row['id']; ?>" class="text-slate-400 hover:text-emerald-600 mx-1"><i class="fas fa-edit"></i></a>
                        <button class="text-slate-400 hover:text-rose-600 mx-1"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                        <i class="fas fa-rotate text-4xl mb-3 block text-slate-200"></i>
                        No recurring invoices set up.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'layouts/footer.php'; ?>
