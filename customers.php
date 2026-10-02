<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Add new table for customers if not exists (in a real app this would be in schema.sql)
$conn->query("CREATE TABLE IF NOT EXISTS `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `ntn` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text,
  `balance` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Seed demo customers if empty
$check = $conn->query("SELECT id FROM customers LIMIT 1");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO customers (name, company_name, ntn, email, phone, address) VALUES
    ('Ali Ahmed', 'Islamabad Electronics', '1234567-1', 'ali@isb-elec.pk', '0300-1234567', 'Blue Area, Islamabad'),
    ('Sara Khan', 'Punjab Mart', '2345678-2', 'sara@pjmart.pk', '0321-7654321', 'Gulberg III, Lahore'),
    ('Zubair Sheikh', 'Karachi Traders', '3456789-3', 'zubair@khi-traders.pk', '0333-9876543', 'I.I. Chundrigar Road, Karachi')");
}

// Update balance display to be dynamic from ledger
function getPartyBalance($conn, $party_name, $account_code) {
    $sql = "SELECT SUM(jel.debit) - SUM(jel.credit) as balance
            FROM journal_entry_lines jel
            JOIN journal_entries je ON jel.journal_entry_id = je.id
            JOIN accounts a ON jel.account_id = a.id
            WHERE a.code = ? AND je.description LIKE ?";
    $stmt = $conn->prepare($sql);
    $search = "%$party_name%";
    $stmt->bind_param("ss", $account_code, $search);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res['balance'] ?? 0;
}


$result = $conn->query("SELECT * FROM customers ORDER BY name ASC");
include 'layouts/header.php';
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Customers</h2>
        <p class="text-slate-500">Manage your business clients and their receivables.</p>
    </div>
    <a href="add_customer.php" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition-colors inline-flex items-center">
        <i class="fas fa-plus mr-2"></i> New Customer
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Customer Name</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Company / NTN</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Contact</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Balance</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php while($row = $result->fetch_assoc()): ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-xs mr-3">
                            <?php echo substr($row['name'], 0, 1); ?>
                        </div>
                        <span class="text-sm font-semibold text-slate-800"><?php echo $row['name']; ?></span>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm text-slate-600"><?php echo $row['company_name']; ?></p>
                    <p class="text-[10px] text-slate-400 font-mono">NTN: <?php echo $row['ntn'] ?: 'N/A'; ?></p>
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm text-slate-600"><i class="fas fa-phone text-[10px] mr-1"></i> <?php echo $row['phone']; ?></p>
                    <p class="text-xs text-slate-400"><?php echo $row['email']; ?></p>
                </td>
                <td class="px-6 py-4 text-sm font-bold text-slate-800 text-right">
                    <?php echo formatPKR(getPartyBalance($conn, $row['name'], '1201')); ?>
                </td>
                <td class="px-6 py-4 text-center actions-cell">
                    <button class="text-slate-400 hover:text-emerald-600 mx-1"><i class="fas fa-edit"></i></button>
                    <?php
                    $ar_acc = $conn->query("SELECT id FROM accounts WHERE code = '1201'")->fetch_assoc();
                    ?>
                    <a href="ledger.php?account_id=<?php echo $ar_acc['id']; ?>&search=<?php echo urlencode($row['name']); ?>" class="text-slate-400 hover:text-blue-600 mx-1"><i class="fas fa-file-invoice"></i></a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include 'layouts/footer.php'; ?>
