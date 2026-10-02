<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Seed demo products if empty
$check = $conn->query("SELECT id FROM products LIMIT 1");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO products (name, sku, sale_price, purchase_price, tax_rate, stock_qty) VALUES
    ('Dell Latitude Laptop', 'DELL-L-5420', 145000.00, 120000.00, 18.00, 10),
    ('HP Laserjet Printer', 'HP-LJ-M404n', 65000.00, 52000.00, 18.00, 5),
    ('Office Stationery Kit', 'ST-KIT-01', 2500.00, 1800.00, 18.00, 50),
    ('Networking Service', 'SRV-NET-HR', 5000.00, 0.00, 0.00, 0)");
}

$result = $conn->query("SELECT * FROM products ORDER BY name ASC");

include 'layouts/header.php';
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Products & Services</h2>
        <p class="text-slate-500">Manage your inventory items and service offerings.</p>
    </div>
    <button class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition-colors">
        <i class="fas fa-plus mr-2"></i> Add Product
    </button>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <h3 class="text-xs font-bold text-slate-500 uppercase mb-2">Total Items</h3>
        <p class="text-2xl font-bold text-slate-800"><?php echo $result->num_rows; ?></p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <h3 class="text-xs font-bold text-slate-500 uppercase mb-2">Low Stock Alerts</h3>
        <p class="text-2xl font-bold text-amber-600">2</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Product Name / SKU</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Sale Price</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Cost</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-center">Stock</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-center">Tax</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php while($row = $result->fetch_assoc()): ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <p class="text-sm font-semibold text-slate-800"><?php echo $row['name']; ?></p>
                    <p class="text-[10px] text-slate-400 font-mono"><?php echo $row['sku']; ?></p>
                </td>
                <td class="px-6 py-4 text-sm font-bold text-slate-800 text-right">
                    <?php echo formatPKR($row['sale_price']); ?>
                </td>
                <td class="px-6 py-4 text-sm text-slate-600 text-right">
                    <?php echo formatPKR($row['purchase_price']); ?>
                </td>
                <td class="px-6 py-4 text-center actions-cell">
                    <span class="px-2 py-1 text-xs font-bold rounded <?php echo $row['stock_qty'] <= 5 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700'; ?>">
                        <?php echo $row['stock_qty']; ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-center text-sm text-slate-600">
                    <?php echo $row['tax_rate']; ?>%
                </td>
                <td class="px-6 py-4 text-center actions-cell">
                    <button class="text-slate-400 hover:text-emerald-600 mx-1"><i class="fas fa-edit"></i></button>
                    <button class="text-slate-400 hover:text-blue-600 mx-1"><i class="fas fa-chart-line"></i></button>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include 'layouts/footer.php'; ?>
