<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
checkLogin();

$customers_res = $conn->query("SELECT id, name, company_name FROM customers ORDER BY name ASC");
$products_res = $conn->query("SELECT id, name, sku, sale_price, tax_rate FROM products ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_id = $_POST['customer_id'];
    $frequency = $_POST['frequency'];
    $start_date = $_POST['start_date'];
    $description = $_POST['description_main'];

    $product_ids = $_POST['product_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $rates = $_POST['unit_price'] ?? [];
    $line_descs = $_POST['description'] ?? [];

    $total_subtotal = 0;
    $total_tax = 0;
    $items = [];

    foreach ($product_ids as $i => $pid) {
        if (empty($pid)) continue;
        $qty = (float)$qtys[$i];
        $rate = (float)$rates[$i];

        // Fetch tax rate for product
        $p_stmt = $conn->prepare("SELECT tax_rate FROM products WHERE id = ?");
        $p_stmt->bind_param("i", $pid);
        $p_stmt->execute();
        $p_tax = $p_stmt->get_result()->fetch_assoc()['tax_rate'] ?? 0;

        $line_subtotal = $qty * $rate;
        $line_tax = $line_subtotal * ($p_tax / 100);

        $total_subtotal += $line_subtotal;
        $total_tax += $line_tax;

        $items[] = [
            'product_id' => $pid,
            'qty' => $qty,
            'rate' => $rate,
            'description' => $line_descs[$i]
        ];
    }

    $total_amount = $total_subtotal + $total_tax;

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO recurring_invoices (customer_id, frequency, start_date, next_generation_date, status, subtotal, tax_amount, total_amount, description) VALUES (?, ?, ?, ?, 'Active', ?, ?, ?, ?)");
        $stmt->bind_param("isssddds", $customer_id, $frequency, $start_date, $start_date, $total_subtotal, $total_tax, $total_amount, $description);
        $stmt->execute();
        $ri_id = $conn->insert_id;

        $item_stmt = $conn->prepare("INSERT INTO recurring_invoice_items (recurring_invoice_id, product_id, description, qty, unit_price) VALUES (?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $item_stmt->bind_param("iisdd", $ri_id, $item['product_id'], $item['description'], $item['qty'], $item['rate']);
            $item_stmt->execute();
        }

        $conn->commit();
        header("Location: recurring_invoices.php?success=1");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

include 'layouts/header.php';
?>

<div class="mb-8 flex items-center justify-between">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">New Recurring Invoice</h2>
        <p class="text-slate-500">Set up a template for automated future billing.</p>
    </div>
    <a href="recurring_invoices.php" class="text-slate-500 hover:text-slate-700 font-medium"><i class="fas fa-arrow-left mr-2"></i> Back</a>
</div>

<?php if (isset($error)): ?>
<div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg"><?php echo $error; ?></div>
<?php endif; ?>

<form method="POST">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Customer</label>
                        <select name="customer_id" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm">
                            <option value="">Select Customer</option>
                            <?php while($c = $customers_res->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo $c['name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Frequency</label>
                        <select name="frequency" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm">
                            <option value="Weekly">Weekly</option>
                            <option value="Monthly" selected>Monthly</option>
                            <option value="Quarterly">Quarterly</option>
                            <option value="Yearly">Yearly</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Internal Note</label>
                        <input type="text" name="description_main" placeholder="e.g. Monthly Retainer for IT Support" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm">
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Product</th>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-center w-24">Qty</th>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right w-32">Rate</th>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase w-12"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody" class="divide-y divide-slate-100">
                        <tr class="item-row">
                            <td class="px-6 py-4">
                                <select name="product_id[]" required class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-sm product-select">
                                    <option value="">Select Product</option>
                                    <?php
                                    mysqli_data_seek($products_res, 0);
                                    while($p = $products_res->fetch_assoc()): ?>
                                    <option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['sale_price']; ?>">
                                        <?php echo $p['name']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                                <input type="text" name="description[]" placeholder="Line description..." class="w-full mt-2 bg-transparent border-none text-[10px] text-slate-400 focus:ring-0 p-0">
                            </td>
                            <td class="px-6 py-4">
                                <input type="number" name="qty[]" value="1" step="0.01" class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-sm text-center">
                            </td>
                            <td class="px-6 py-4">
                                <input type="number" name="unit_price[]" step="0.01" value="0.00" class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-sm text-right price-input">
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button type="button" class="text-slate-300 hover:text-rose-500 remove-row"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="p-4 bg-slate-50 border-t border-slate-100">
                    <button type="button" id="addItem" class="text-emerald-600 text-sm font-semibold hover:text-emerald-700">
                        <i class="fas fa-plus mr-1"></i> Add Line Item
                    </button>
                </div>
            </div>
        </div>

        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 sticky top-8">
                <button type="submit" class="w-full bg-emerald-600 text-white py-4 rounded-lg font-bold hover:bg-emerald-700 shadow-lg shadow-emerald-100 transition-all mb-4">
                    Save Recurring Template
                </button>
                <p class="text-[10px] text-slate-500 text-center">
                    Invoices will be automatically generated on the scheduled dates based on this template.
                </p>
            </div>
        </div>
    </div>
</form>

<script>
document.getElementById('addItem').addEventListener('click', () => {
    const row = document.querySelector('.item-row').cloneNode(true);
    row.querySelectorAll('input').forEach(i => i.value = i.type === 'number' ? '0' : '');
    document.getElementById('itemsBody').appendChild(row);
});

document.getElementById('itemsBody').addEventListener('change', (e) => {
    if (e.target.classList.contains('product-select')) {
        const price = e.target.selectedOptions[0].dataset.price;
        e.target.closest('tr').querySelector('.price-input').value = price;
    }
});

document.getElementById('itemsBody').addEventListener('click', (e) => {
    if (e.target.closest('.remove-row')) {
        if (document.querySelectorAll('.item-row').length > 1) {
            e.target.closest('tr').remove();
        }
    }
});
</script>

<?php include 'layouts/header.php'; ?>
