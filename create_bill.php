<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
checkLogin();

$suppliers_res = $conn->query("SELECT id, name, company_name FROM suppliers ORDER BY name ASC");
$products_res = $conn->query("SELECT id, name, sku, purchase_price, tax_rate FROM products ORDER BY name ASC");
$expense_accounts_res = $conn->query("SELECT id, code, name FROM accounts WHERE type_id IN (5, 6) ORDER BY code ASC");

// Get AP, Tax and Inventory account IDs
$ap_acc = $conn->query("SELECT id FROM accounts WHERE code = '2101'")->fetch_assoc();
$tax_acc = $conn->query("SELECT id FROM accounts WHERE code = '1501'")->fetch_assoc();
$inv_acc = $conn->query("SELECT id FROM accounts WHERE code = '1301'")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $supplier_id = $_POST['supplier_id'];
    $bill_number = $_POST['bill_number'];
    $bill_date = $_POST['bill_date'];
    $due_date = $_POST['due_date'];

    $product_ids = $_POST['product_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $costs = $_POST['unit_cost'] ?? [];
    $descriptions = $_POST['description'] ?? [];

    $total_bill_amount = 0;
    $total_tax_amount = 0;
    $journal_lines = [];

    $conn->begin_transaction();
    try {
        // We'll create the bill record first to get the ID for stock moves
        // But we need total amount. Let's calculate first.

        $bill_items_to_save = [];
        $inventory_value = 0;
        $expense_value = 0;

        foreach ($product_ids as $i => $pid) {
            if (empty($pid)) continue;

            $qty = (float)$qtys[$i];
            $unit_cost = (float)$costs[$i];
            $tax_rate = 18.00; // Default GST in Pakistan
            $line_tax = ($qty * $unit_cost) * ($tax_rate / 100);
            $line_total = ($qty * $unit_cost) + $line_tax;

            $total_bill_amount += $line_total;
            $total_tax_amount += $line_tax;

            $bill_items_to_save[] = [
                'product_id' => $pid,
                'description' => $descriptions[$i],
                'qty' => $qty,
                'unit_cost' => $unit_cost,
                'tax_amount' => $line_tax,
                'total' => $line_total
            ];

            $inventory_value += ($qty * $unit_cost);
        }

        // Journal Entry Lines
        if ($inventory_value > 0) {
            $journal_lines[] = ['account_id' => $inv_acc['id'], 'description' => "Inventory Purchase - $bill_number", 'debit' => $inventory_value, 'credit' => 0];
        }

        if ($total_tax_amount > 0) {
            $journal_lines[] = ['account_id' => $tax_acc['id'], 'description' => "Input GST on $bill_number", 'debit' => $total_tax_amount, 'credit' => 0];
        }

        $journal_lines[] = ['account_id' => $ap_acc['id'], 'description' => "Supplier Bill $bill_number", 'debit' => 0, 'credit' => $total_bill_amount];

        // 1. Post Journal Entry
        $je_result = postJournalEntry($conn, $bill_date, $bill_number, "Supplier Bill $bill_number", 'Purchases', $journal_lines);
        if (!$je_result['success']) throw new Exception($je_result['error']);
        $je_id = $je_result['id'];

        // 2. Create Bill Record
        $stmt = $conn->prepare("INSERT INTO bills (supplier_id, bill_number, bill_date, due_date, total_amount, status, journal_entry_id) VALUES (?, ?, ?, ?, ?, 'Open', ?)");
        $stmt->bind_param("isssdi", $supplier_id, $bill_number, $bill_date, $due_date, $total_bill_amount, $je_id);
        $stmt->execute();
        $bill_id = $conn->insert_id;

        // 3. Save Items and Update Stock
        $item_stmt = $conn->prepare("INSERT INTO bill_items (bill_id, product_id, description, qty, unit_cost, tax_amount, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($bill_items_to_save as $item) {
            $item_stmt->bind_param("iisdddd", $bill_id, $item['product_id'], $item['description'], $item['qty'], $item['unit_cost'], $item['tax_amount'], $item['total']);
            $item_stmt->execute();

            // Update inventory with WAC
            updateStock($conn, $item['product_id'], $item['qty'], $item['unit_cost'], 'Purchase', $bill_id);
        }

        logAction($conn, 'Record Bill', 'Purchases', $bill_id, "Recorded Bill $bill_number for amount Rs. $total_bill_amount");

        $conn->commit();
        header("Location: bills.php?success=1");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to record bill: " . $e->getMessage();
    }
}

include 'layouts/header.php';
?>

<div class="mb-8 flex items-center justify-between">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Record Supplier Bill</h2>
        <p class="text-slate-500">Record a new purchase, update inventory (WAC), and accounts payable.</p>
    </div>
    <a href="bills.php" class="text-slate-500 hover:text-slate-700 font-medium"><i class="fas fa-arrow-left mr-2"></i> Back to List</a>
</div>

<?php if (isset($error)): ?>
<div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg flex items-center">
    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
</div>
<?php endif; ?>

<form method="POST" id="billForm">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Main Bill Details -->
        <div class="lg:col-span-3 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Supplier *</label>
                        <select name="supplier_id" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm focus:ring-emerald-500">
                            <option value="">Select a Supplier</option>
                            <?php while($s = $suppliers_res->fetch_assoc()): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo $s['name']; ?> (<?php echo $s['company_name']; ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Bill Number *</label>
                        <input type="text" name="bill_number" required value="BILL-<?php echo strtoupper(substr(uniqid(), 7)); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Bill Date *</label>
                        <input type="date" name="bill_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Due Date *</label>
                        <input type="date" name="due_date" required value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm">
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase w-1/3">Product / Description</th>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-center w-24">Qty</th>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right w-32">Unit Cost</th>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right w-32">Total</th>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase w-12"></th>
                        </tr>
                    </thead>
                    <tbody id="billItems" class="divide-y divide-slate-100">
                        <tr class="item-row">
                            <td class="px-6 py-4">
                                <select name="product_id[]" required class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-sm product-select">
                                    <option value="">Select Product</option>
                                    <?php
                                    mysqli_data_seek($products_res, 0);
                                    while($p = $products_res->fetch_assoc()): ?>
                                    <option value="<?php echo $p['id']; ?>" data-cost="<?php echo $p['purchase_price']; ?>">
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
                                <input type="number" name="unit_cost[]" step="0.01" value="0.00" class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-sm text-right cost-input">
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
                <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Bill Summary</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm text-slate-600">
                        <span>Subtotal (Excl. Tax)</span>
                        <span id="summarySubtotal">Rs. 0.00</span>
                    </div>
                    <div class="flex justify-between text-sm text-slate-600">
                        <span>Input GST (18%)</span>
                        <span id="summaryTax">Rs. 0.00</span>
                    </div>
                    <div class="flex justify-between text-xl font-black text-slate-900 pt-3 border-t">
                        <span>Total Payable</span>
                        <span id="summaryTotal">Rs. 0.00</span>
                    </div>
                </div>

                <button type="submit" class="w-full bg-emerald-600 text-white py-4 rounded-lg font-bold hover:bg-emerald-700 shadow-lg shadow-emerald-100 transition-all mt-8">
                    Post Supplier Bill
                </button>

                <div class="mt-6 p-4 bg-amber-50 rounded-lg border border-amber-100">
                    <h4 class="text-[10px] font-bold text-amber-800 uppercase mb-1 flex items-center">
                        <i class="fas fa-info-circle mr-1"></i> Inventory Impact
                    </h4>
                    <p class="text-[10px] text-amber-700 leading-tight">
                        Costs entered will update the <strong>Weighted Average Cost (WAC)</strong> for selected products.
                    </p>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.getElementById('billItems');
    const addRowBtn = document.getElementById('addRow');
    const TAX_RATE = 0.18;

    function updateTotals() {
        let subtotal = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input').value || 0);
            const cost = parseFloat(row.querySelector('.cost-input').value || 0);
            const total = qty * cost;
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
            const cost = option.getAttribute('data-cost');
            const row = e.target.closest('tr');
            row.querySelector('.cost-input').value = cost;
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
