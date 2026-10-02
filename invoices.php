<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Create invoices table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `status` enum('Draft','Sent','Paid','Partially Paid','Overdue','Cancelled') DEFAULT 'Draft',
  `journal_entry_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$result = $conn->query("SELECT i.*, c.name as customer_name
                        FROM invoices i
                        JOIN customers c ON i.customer_id = c.id
                        ORDER BY i.invoice_date DESC");

include 'layouts/header.php';
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Sales Invoices</h2>
        <p class="text-slate-500">Track your sales and manage customer receivables.</p>
    </div>
    <a href="create_invoice.php" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition-colors inline-block">
        <i class="fas fa-plus mr-2"></i> Create Invoice
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Date</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Invoice #</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Customer</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Status</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Total Amount</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 text-sm text-slate-600"><?php echo date('d M Y', strtotime($row['invoice_date'])); ?></td>
                    <td class="px-6 py-4 text-sm font-mono font-bold text-slate-800"><?php echo $row['invoice_number']; ?></td>
                    <td class="px-6 py-4 text-sm text-slate-800 font-medium"><?php echo $row['customer_name']; ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-[10px] font-bold rounded-full uppercase
                            <?php
                            switch($row['status']) {
                                case 'Paid': echo 'bg-emerald-100 text-emerald-700'; break;
                                case 'Draft': echo 'bg-slate-100 text-slate-600'; break;
                                case 'Sent': echo 'bg-blue-100 text-blue-700'; break;
                                case 'Overdue': echo 'bg-rose-100 text-rose-700'; break;
                            }
                            ?>">
                            <?php echo $row['status']; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm font-bold text-slate-800 text-right">
                        <?php echo formatPKR($row['total_amount']); ?>
                    </td>
                    <td class="px-6 py-4 text-center actions-cell">
                        <button class="text-slate-400 hover:text-emerald-600 mx-1"><i class="fas fa-eye"></i></button>
                        <a href="record_payment.php?type=customer&id=<?php echo $row['id']; ?>" class="text-slate-400 hover:text-blue-600 mx-1" title="Record Payment"><i class="fas fa-money-bill-wave"></i></a>
                        <button class="text-slate-400 hover:text-slate-600 mx-1"><i class="fas fa-print"></i></button>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                        <i class="fas fa-file-invoice text-4xl mb-3 block text-slate-200"></i>
                        No invoices found. <a href="create_invoice.php" class="text-emerald-600 font-bold hover:underline">Create your first invoice</a>.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'layouts/footer.php'; ?>
