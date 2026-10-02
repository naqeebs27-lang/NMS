<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Add new table for suppliers if not exists
$conn->query("CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `ntn` varchar(50) DEFAULT NULL,
  `strn` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text,
  `balance` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Seed demo suppliers if empty
$check = $conn->query("SELECT id FROM suppliers LIMIT 1");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO suppliers (name, company_name, ntn, strn, email, phone, address) VALUES
    ('Kamran Office Supplies', 'Kamran OS (Pvt) Ltd', '7654321-9', '1234567890123', 'sales@kamranos.pk', '051-5551234', 'Saddar, Rawalpindi'),
    ('Metro Distribution', 'Metro Pak Group', '8765432-8', '2345678901234', 'info@metropak.pk', '021-34567890', 'SITE Area, Karachi'),
    ('PTCL', 'Pakistan Telecommunication Company', '1234567-0', '3456789012345', 'billing@ptcl.net.pk', '1218', 'G-8/4, Islamabad')");
}

// Update balance display to be dynamic from ledger
function getPartyBalance($conn, $party_name, $account_code) {
    $sql = "SELECT SUM(jel.credit) - SUM(jel.debit) as balance
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

$result = $conn->query("SELECT * FROM suppliers ORDER BY name ASC");

include 'layouts/header.php';
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Suppliers</h2>
        <p class="text-slate-500">Manage your vendors and their payables.</p>
    </div>
    <a href="add_supplier.php" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition-colors inline-flex items-center">
        <i class="fas fa-plus mr-2"></i> New Supplier
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Supplier Name</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Company / Tax Info</th>
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
                    <div class="flex items-center mt-1 space-x-2">
                        <p class="text-[10px] text-slate-400 font-mono">NTN: <?php echo $row['ntn'] ?: 'N/A'; ?> | STRN: <?php echo $row['strn'] ?: 'N/A'; ?></p>
                        <span class="px-1.5 py-0.5 text-[8px] font-bold rounded uppercase <?php echo ($row['is_filer'] ?? 1) ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                            <?php echo ($row['is_filer'] ?? 1) ? 'Filer' : 'Non-Filer'; ?>
                        </span>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm text-slate-600"><i class="fas fa-phone text-[10px] mr-1"></i> <?php echo $row['phone']; ?></p>
                    <p class="text-xs text-slate-400"><?php echo $row['email']; ?></p>
                </td>
                <td class="px-6 py-4 text-sm font-bold text-slate-800 text-right">
                    <?php echo formatPKR(getPartyBalance($conn, $row['name'], '2101')); ?>
                </td>
                <td class="px-6 py-4 text-center actions-cell">
                    <button class="text-slate-400 hover:text-emerald-600 mx-1"><i class="fas fa-edit"></i></button>
                    <?php
                    $ap_acc = $conn->query("SELECT id FROM accounts WHERE code = '2101'")->fetch_assoc();
                    ?>
                    <a href="ledger.php?account_id=<?php echo $ap_acc['id']; ?>&search=<?php echo urlencode($row['name']); ?>" class="text-slate-400 hover:text-blue-600 mx-1" title="View Ledger"><i class="fas fa-history"></i></a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include 'layouts/footer.php'; ?>
