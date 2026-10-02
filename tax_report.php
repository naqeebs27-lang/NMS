<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
include 'layouts/header.php';

// Get Input Tax (Receivable) - Account 1501
$input_tax = getAccountBalance(15, $conn); // Assuming ID 15 is 1501 based on typical seed order
// Better to fetch by code to be safe
$input_tax_acc = $conn->query("SELECT id FROM accounts WHERE code = '1501'")->fetch_assoc();
$input_tax = getAccountBalance($input_tax_acc['id'], $conn);

// Get Output Tax (Payable) - Account 2301
$output_tax_acc = $conn->query("SELECT id FROM accounts WHERE code = '2301'")->fetch_assoc();
$output_tax = getAccountBalance($output_tax_acc['id'], $conn);

// Get Withholding Tax - Account 2401
$wht_acc = $conn->query("SELECT id FROM accounts WHERE code = '2401'")->fetch_assoc();
$wht_payable = getAccountBalance($wht_acc['id'], $conn);

$net_gst = $output_tax - $input_tax;
?>

<div class="mb-8">
    <h2 class="text-2xl font-bold text-slate-800">Pakistan Tax Summary</h2>
    <p class="text-slate-500">Overview of Sales Tax (GST) and Withholding Tax for FBR compliance.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <h3 class="text-slate-500 text-sm font-medium mb-1">Total Output GST</h3>
        <p class="text-2xl font-bold text-slate-800"><?php echo formatPKR($output_tax); ?></p>
        <p class="text-[10px] text-slate-400 mt-2 uppercase font-bold tracking-wider">Collected on Sales</p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <h3 class="text-slate-500 text-sm font-medium mb-1">Total Input GST</h3>
        <p class="text-2xl font-bold text-slate-800"><?php echo formatPKR($input_tax); ?></p>
        <p class="text-[10px] text-slate-400 mt-2 uppercase font-bold tracking-wider">Paid on Purchases</p>
    </div>
    <div class="p-6 rounded-xl shadow-sm border <?php echo $net_gst >= 0 ? 'bg-rose-50 border-rose-100' : 'bg-emerald-50 border-emerald-100'; ?>">
        <h3 class="<?php echo $net_gst >= 0 ? 'text-rose-600' : 'text-emerald-600'; ?> text-sm font-medium mb-1">Net GST <?php echo $net_gst >= 0 ? 'Payable' : 'Refundable'; ?></h3>
        <p class="text-2xl font-bold <?php echo $net_gst >= 0 ? 'text-rose-700' : 'text-emerald-700'; ?>"><?php echo formatPKR(abs($net_gst)); ?></p>
        <p class="text-[10px] text-slate-400 mt-2 uppercase font-bold tracking-wider">Current Period Liability</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
        <h3 class="font-bold text-slate-800">GST Transaction History</h3>
        <button class="text-emerald-600 text-sm font-bold hover:underline"><i class="fas fa-file-export mr-1"></i> Export for FBR</button>
    </div>
    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Date</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Reference</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Description</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Input Tax</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Output Tax</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php
            $sql = "SELECT je.entry_date, je.reference, jel.description, jel.debit, jel.credit, a.code
                    FROM journal_entry_lines jel
                    JOIN journal_entries je ON jel.journal_entry_id = je.id
                    JOIN accounts a ON jel.account_id = a.id
                    WHERE a.code IN ('1501', '2301')
                    ORDER BY je.entry_date DESC";
            $res = $conn->query($sql);
            if ($res->num_rows > 0):
                while($row = $res->fetch_assoc()):
            ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 text-sm text-slate-600"><?php echo date('d M Y', strtotime($row['entry_date'])); ?></td>
                <td class="px-6 py-4 text-sm font-mono font-bold text-slate-800"><?php echo $row['reference']; ?></td>
                <td class="px-6 py-4 text-sm text-slate-600"><?php echo $row['description']; ?></td>
                <td class="px-6 py-4 text-sm font-medium text-emerald-600 text-right">
                    <?php echo $row['code'] == '1501' ? formatPKR($row['debit']) : '-'; ?>
                </td>
                <td class="px-6 py-4 text-sm font-medium text-rose-600 text-right">
                    <?php echo $row['code'] == '2301' ? formatPKR($row['credit']) : '-'; ?>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
                <td colspan="5" class="px-6 py-12 text-center text-slate-400">No tax transactions found.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="bg-slate-900 rounded-xl p-8 text-white">
    <div class="flex flex-col md:flex-row md:items-center justify-between">
        <div>
            <h3 class="text-xl font-bold mb-2">Withholding Tax (WHT) Summary</h3>
            <p class="text-slate-400 text-sm">Amount withheld from suppliers to be deposited to FBR treasury.</p>
        </div>
        <div class="mt-4 md:mt-0 text-right">
            <p class="text-3xl font-bold text-emerald-400"><?php echo formatPKR($wht_payable); ?></p>
            <span class="text-[10px] uppercase font-bold text-slate-500 tracking-widest">Total WHT Payable</span>
        </div>
    </div>
</div>

<?php include 'layouts/footer.php'; ?>
