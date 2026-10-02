<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
checkLogin();

$customers_res = $conn->query("SELECT id, name, company_name FROM customers ORDER BY name ASC");
$products_res = $conn->query("SELECT id, name, sku, sale_price, purchase_price, tax_rate FROM products ORDER BY name ASC");

// Get Account IDs
$ar_acc = $conn->query("SELECT id FROM accounts WHERE code = '1201'")->fetch_assoc();
$rev_acc = $conn->query("SELECT id FROM accounts WHERE code = '4101'")->fetch_assoc();
$tax_acc = $conn->query("SELECT id FROM accounts WHERE code = '2301'")->fetch_assoc();
$inv_acc = $conn->query("SELECT id FROM accounts WHERE code = '1301'")->fetch_assoc();
$cogs_acc = $conn->query("SELECT id FROM accounts WHERE code = '5101'")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_id = $_POST['customer_id'];
    $inv_number = $_POST['invoice_number'];
    $inv_date = $_POST['invoice_date'];
    $due_date = $_POST['due_date'];

    $product_ids = $_POST['product_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $rates = $_POST['unit_price'] ?? [];
    $descriptions = $_POST['description'] ?? [];

    $total_invoice_amount = 0;
    $total_tax_amount = 0;
    $total_cogs = 0;
    $invoice_items_to_save = [];

    $conn->begin_transaction();
    try {
        foreach ($product_ids as $i => $pid) {
            if (empty($pid)) continue;

            $qty = (float)$qtys[$i];
            $unit_price = (float)$rates[$i];

            // Fetch product details for tax and WAC
            $stmt = $conn->prepare("SELECT tax_rate, purchase_price FROM products WHERE id = ?");
            $stmt->bind_param("i", $pid);
            $stmt->execute();
            $p_data = $stmt->get_result()->fetch_assoc();

            $tax_rate = $p_data['tax_rate'];
            $current_wac = (float)$p_data['purchase_price'];

            $line_tax = ($qty * $unit_price) * ($tax_rate / 100);
            $line_total = ($qty * $unit_price) + $line_tax;
            $line_cogs = $qty * $current_wac;

            $total_invoice_amount += $line_total;
            $total_tax_amount += $line_tax;
            $total_cogs += $line_cogs;

            $invoice_items_to_save[] = [
                'product_id' => $pid,
                'description' => $descriptions[$i],
                'qty' => $qty,
                'unit_price' => $unit_price,
                'tax_amount' => $line_tax,
                'total' => $line_total,
                'cogs' => $line_cogs
            ];
        }

        $subtotal = $total_invoice_amount - $total_tax_amount;

        // Construct Journal Entry
        $je_desc = "Sales Invoice $inv_number";
        $journal_lines = [
            ['account_id' => $ar_acc['id'], 'description' => $je_desc, 'debit' => $total_invoice_amount, 'credit' => 0],
            ['account_id' => $rev_acc['id'], 'description' => $je_desc, 'debit' => 0, 'credit' => $subtotal]
        ];

        if ($total_tax_amount > 0) {
            $journal_lines[] = ['account_id' => $tax_acc['id'], 'description' => "Output GST on $inv_number", 'debit' => 0, 'credit' => $total_tax_amount];
        }

        // Inventory & COGS Entry (Integrated WAC)
        if ($total_cogs > 0) {
            $journal_lines[] = ['account_id' => $cogs_acc['id'], 'description' => "COGS for $inv_number", 'debit' => $total_cogs, 'credit' => 0];
            $journal_lines[] = ['account_id' => $inv_acc['id'], 'description' => "Inventory Reduction - $inv_number", 'debit' => 0, 'credit' => $total_cogs];
        }

        // 1. Post Journal Entry
        $je_result = postJournalEntry($conn, $inv_date, $inv_number, $je_desc, 'Sales', $journal_lines);
        if (!$je_result['success']) throw new Exception($je_result['error']);
        $je_id = $je_result['id'];

        // 2. Create Invoice Record
        $stmt = $conn->prepare("INSERT INTO invoices (customer_id, invoice_number, invoice_date, due_date, total_amount, status, journal_entry_id) VALUES (?, ?, ?, ?, ?, 'Sent', ?)");
        $stmt->bind_param("isssdi", $customer_id, $inv_number, $inv_date, $due_date, $total_invoice_amount, $je_id);
        $stmt->execute();
        $invoice_id = $conn->insert_id;

        // 3. Save Items and Update Stock
        $item_stmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, product_id, description, qty, unit_price, tax_amount, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($invoice_items_to_save as $item) {
            $item_stmt->bind_param("iisdddd", $invoice_id, $item['product_id'], $item['description'], $item['qty'], $item['unit_price'], $item['tax_amount'], $item['total']);
            $item_stmt->execute();

            // Reduce stock (Negative qty)
            updateStock($conn, $item['product_id'], -$item['qty'], 0, 'Sale', $invoice_id);
        }

        logAction($conn, 'Create Invoice', 'Sales', $invoice_id, "Generated Invoice $inv_number for amount Rs. $total_invoice_amount");

        $conn->commit();
        header("Location: invoices.php?success=1");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to create invoice: " . $e->getMessage();
    }
}

include 'layouts/header.php';
?>

<div class="mb-8 flex items-center justify-between">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">New Sales Invoice</h2>
        <p class="text-slate-500">Generate invoice, update inventory, and record COGS automatically.</p>
    </div>
    <a href="invoices.php" class="text-slate-500 hover:text-slate-700 font-medium"><i class="fas fa-arrow-left mr-2"></i> Back to List</a>
</div>

<?php if (isset($error)): ?>
<div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg flex items-center">
    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
</div>
<?php endif; ?>

<form method="POST" id="invoiceForm">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Main Invoice Details -->
        <div class="lg:col-span-3 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Customer *</label>
                        <select name="customer_id" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm focus:ring-emerald-500">
                            <option value="">Select a Customer</option>
                            <?php while($c = $customers_res->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo $c['name']; ?> (<?php echo $c['company_name']; ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Invoice #</label>
                        <input type="text" name="invoice_number" required value="INV-<?php echo strtoupper(substr(uniqid(), 7)); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Invoice Date</label>
                        <input type="date" name="invoice_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm">
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase w-1/3">Product</th>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-center w-24">Qty</th>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right w-32">Unit Price</th>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right w-32">Amount</th>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase w-12"></th>
                        </tr>
                    </thead>
                    <tbody id="invoiceItems" class="divide-y divide-slate-100">
                        <tr class="item-row">
                            <td class="px-6 py-4">
                                <select name="product_id[]" required class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-sm product-select">
                                    <option value="">Select Product</option>
                                    <?php
                                    mysqli_data_seek($products_res, 0);
                                    while($p = $products_res->fetch_assoc()): ?>
                                    <option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['sale_price']; ?>">
                                        <?php echo $p['name']; ?> (<?php echo $p['sku']; ?>)
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                                <input type="text" name="description[]" placeholder="Additional details..." class="w-full mt-2 bg-transparent border-none text-[10px] text-slate-400 focus:ring-0 p-0">
                            </td>
                            <td class="px-6 py-4">
                                <input type="number" name="qty[]" value="1" step="1" class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-sm text-center qty-input">
                            </td>
                            <td class="px-6 py-4">
                                <input type="number" name="unit_price[]" step="0.01" value="0.00" class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-sm text-right price-input">
                            </td>
                            <td class="px-6 py-4 text-sm font-bold text-slate-800 text-right line-total">0.00</td>
                            <td class="px-6 py-4 text-center">
                                <button type="button" class="text-slate-300 hover:text-rose-500 remove-row"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="p-4 bg-slate-50 border-t border-slate-100">
                    <button type="button" id="addRow" class="text-emerald-600 text-sm font-semibold hover:text-emerald-700">
                        <i class="fas fa-plus mr-1"></i> Add Line Item
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary & Actions -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 sticky top-8">
                <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Invoice Summary</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm text-slate-600">
                        <span>Subtotal</span>
                        <span id="summarySubtotal">Rs. 0.00</span>
                    </div>
                    <div class="flex justify-between text-sm text-slate-600">
                        <span>Sales Tax (18%)</span>
                        <span id="summaryTax">Rs. 0.00</span>
                    </div>
                    <div class="flex justify-between text-xl font-black text-slate-900 pt-3 border-t">
                        <span>Total Amount</span>
                        <span id="summaryTotal">Rs. 0.00</span>
                    </div>
                </div>

                <button type="submit" class="w-full bg-emerald-600 text-white py-4 rounded-lg font-bold hover:bg-emerald-700 shadow-lg shadow-emerald-100 transition-all mt-8">
                    Post Invoice
                </button>

                <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-100">
                    <h4 class="text-[10px] font-bold text-blue-800 uppercase mb-1 flex items-center">
                        <i class="fas fa-calculator mr-1"></i> Accounting Note
                    </h4>
                    <p class="text-[10px] text-blue-700 leading-tight">
                        This will record <strong>Sales Revenue</strong> and automatically deduct <strong>COGS</strong> from your inventory value.
                    </p>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.getElementById('invoiceItems');
    const addRowBtn = document.getElementById('addRow');
    const TAX_RATE = 0.18;

    function updateTotals() {
        let subtotal = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input').value || 0);
            const price = parseFloat(row.querySelector('.price-input').value || 0);
            const total = qty * price;
            row.querySelector('.line-total').innerText = total.toLocaleString(undefined, {minimumFractionDigits: 2});
            subtotal += total;
        });

        const tax = subtotal * TAX_RATE;
        const grandTotal = subtotal + tax;

        document.getElementById('summarySubtotal').innerText = 'Rs. ' + subtotal.toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('summaryTax').innerText = 'Rs. ' + tax.toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('summaryTotal').innerText = 'Rs. ' + grandTotal.toLocaleString(undefined, {minimumFractionDigits: 2});
    }

    addRowBtn.addEventListener('click', () => {
        const newRow = tableBody.firstElementChild.cloneNode(true);
        newRow.querySelectorAll('input').forEach(i => i.value = i.type === 'number' ? '0' : '');
        newRow.querySelector('.line-total').innerText = '0.00';
        tableBody.appendChild(newRow);
    });

    tableBody.addEventListener('change', (e) => {
        if (e.target.classList.contains('product-select')) {
            const option = e.target.selectedOptions[0];
            const price = option.getAttribute('data-price');
            const row = e.target.closest('tr');
            row.querySelector('.price-input').value = price;
            updateTotals();
        }
    });

    tableBody.addEventListener('input', updateTotals);

    tableBody.addEventListener('click', (e) => {
        if (e.target.closest('.remove-row')) {
            const rows = document.querySelectorAll('.item-row');
            if (rows.length > 1) {
                e.target.closest('tr').remove();
                updateTotals();
            }
        }
    });

    updateTotals();
});
</script>

<?php include 'layouts/footer.php'; ?>
