<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
checkLogin();

$month = $_GET['month'] ?? date('Y-m');
$type = $_GET['type'] ?? 'sales'; // 'sales' for Annexure C, 'purchases' for Annexure A

if ($type == 'sales') {
    // Annexure C: Sales
    $query = "SELECT i.*, c.name as party_name, c.ntn as party_ntn, c.is_filer
              FROM invoices i
              JOIN customers c ON i.customer_id = c.id
              WHERE DATE_FORMAT(i.invoice_date, '%Y-%m') = ?
              ORDER BY i.invoice_date ASC";
} else {
    // Annexure A: Purchases
    $query = "SELECT b.*, s.name as party_name, s.ntn as party_ntn, s.strn as party_strn, s.is_filer, b.bill_number as invoice_number, b.bill_date as invoice_date
              FROM bills b
              JOIN suppliers s ON b.supplier_id = s.id
              WHERE DATE_FORMAT(b.bill_date, '%Y-%m') = ?
              ORDER BY b.bill_date ASC";
}

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $month);
$stmt->execute();
$results = $stmt->get_result();

include 'layouts/header.php';
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">FBR Returns Helper</h2>
        <p class="text-slate-500">Data formatted for IRIS Annexures (<?php echo $type == 'sales' ? 'Annexure C' : 'Annexure A'; ?>)</p>
    </div>
    <div class="flex gap-2">
        <form class="flex gap-2 items-center bg-white p-2 rounded-lg border border-slate-200">
            <input type="month" name="month" value="<?php echo $month; ?>" onchange="this.form.submit()"
                   class="border-none focus:ring-0 text-sm font-bold text-slate-700">
            <select name="type" onchange="this.form.submit()" class="border-none focus:ring-0 text-sm font-bold text-slate-700">
                <option value="sales" <?php echo $type == 'sales' ? 'selected' : ''; ?>>Sales (Annex C)</option>
                <option value="purchases" <?php echo $type == 'purchases' ? 'selected' : ''; ?>>Purchases (Annex A)</option>
            </select>
        </form>
        <button onclick="exportCSV()" class="bg-emerald-600 text-white px-4 py-2 rounded-lg font-bold hover:bg-emerald-700 transition-all flex items-center">
            <i class="fas fa-file-csv mr-2"></i> Export CSV
        </button>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left" id="taxTable">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase">NTN/FTN</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase">Name</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase">Type</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase">Doc #</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase">Date</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase text-right">Value (Excl. Tax)</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase text-right">Sales Tax</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase text-right">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php
            $total_value = 0;
            $total_tax = 0;
            while($row = $results->fetch_assoc()):
                $tax = $row['total_amount'] * (18/118); // Simplified back-calc if not stored separately
                $value = $row['total_amount'] - $tax;
                $total_value += $value;
                $total_tax += $tax;
            ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 text-sm font-mono text-slate-600"><?php echo $row['party_ntn'] ?: 'Consumer'; ?></td>
                <td class="px-6 py-4 text-sm font-medium text-slate-800"><?php echo $row['party_name']; ?></td>
                <td class="px-6 py-4 text-sm text-slate-600"><?php echo $row['is_filer'] ? 'Filer' : 'Non-Filer'; ?></td>
                <td class="px-6 py-4 text-sm font-mono text-slate-800"><?php echo $row['invoice_number']; ?></td>
                <td class="px-6 py-4 text-sm text-slate-600"><?php echo date('d-m-Y', strtotime($row['invoice_date'])); ?></td>
                <td class="px-6 py-4 text-sm text-right text-slate-800"><?php echo number_format($value, 2); ?></td>
                <td class="px-6 py-4 text-sm text-right text-emerald-600 font-bold"><?php echo number_format($tax, 2); ?></td>
                <td class="px-6 py-4 text-sm text-right text-slate-800 font-bold"><?php echo number_format($row['total_amount'], 2); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot class="bg-slate-50 font-bold border-t-2 border-slate-200">
            <tr>
                <td colspan="5" class="px-6 py-4 text-sm text-slate-800">Totals</td>
                <td class="px-6 py-4 text-sm text-right text-slate-800"><?php echo number_format($total_value, 2); ?></td>
                <td class="px-6 py-4 text-sm text-right text-emerald-600"><?php echo number_format($total_tax, 2); ?></td>
                <td class="px-6 py-4 text-sm text-right text-slate-800"><?php echo number_format($total_value + $total_tax, 2); ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<script>
function exportCSV() {
    let csv = [];
    let rows = document.querySelectorAll("#taxTable tr");

    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++)
            row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
        csv.push(row.join(","));
    }

    let csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
    let downloadLink = document.createElement("a");
    downloadLink.download = "FBR_Annexure_<?php echo $type . '_' . $month; ?>.csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
}
</script>

<?php include 'layouts/footer.php'; ?>
