<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
checkLogin();

$query = "SELECT p.*,
          (SELECT SUM(qty) FROM stock_moves WHERE product_id = p.id AND transaction_type IN ('Purchase', 'Adjustment', 'Return')) as total_in,
          (SELECT SUM(ABS(qty)) FROM stock_moves WHERE product_id = p.id AND transaction_type = 'Sale') as total_out
          FROM products p
          ORDER BY p.name ASC";
$products = $conn->query($query);

include 'layouts/header.php';
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Inventory Valuation Report</h2>
        <p class="text-slate-500">Stock levels and valuation based on Weighted Average Cost (WAC).</p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-lg font-bold hover:bg-slate-200 transition-all">
            <i class="fas fa-print mr-2"></i> Print Report
        </button>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <?php
    $stats = $conn->query("SELECT SUM(stock_qty * purchase_price) as total_val, COUNT(*) as total_items FROM products")->fetch_assoc();
    ?>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <h3 class="text-slate-500 text-sm font-medium mb-1">Total Inventory Value</h3>
        <p class="text-2xl font-bold text-slate-800"><?php echo formatPKR($stats['total_val'] ?? 0); ?></p>
        <p class="text-[10px] text-slate-400 mt-2 uppercase font-bold">At Weighted Average Cost</p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <h3 class="text-slate-500 text-sm font-medium mb-1">Total Unique Items</h3>
        <p class="text-2xl font-bold text-slate-800"><?php echo $stats['total_items']; ?></p>
        <p class="text-[10px] text-slate-400 mt-2 uppercase font-bold">In Catalog</p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <h3 class="text-slate-500 text-sm font-medium mb-1">Low Stock Items</h3>
        <?php $low_stock = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_qty <= 5")->fetch_assoc(); ?>
        <p class="text-2xl font-bold text-amber-600"><?php echo $low_stock['count']; ?></p>
        <p class="text-[10px] text-slate-400 mt-2 uppercase font-bold">Threshold: 5 units</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase">SKU / Product</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase text-center">In</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase text-center">Out</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase text-center">On Hand</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase text-right">Avg. Cost</th>
                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase text-right">Total Value</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php while($p = $products->fetch_assoc()):
                $line_val = $p['stock_qty'] * $p['purchase_price'];
            ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <p class="text-sm font-bold text-slate-800"><?php echo $p['name']; ?></p>
                    <p class="text-[10px] text-slate-400 font-mono"><?php echo $p['sku']; ?></p>
                </td>
                <td class="px-6 py-4 text-center text-sm text-slate-600"><?php echo number_format($p['total_in'] ?? 0); ?></td>
                <td class="px-6 py-4 text-center text-sm text-slate-600"><?php echo number_format($p['total_out'] ?? 0); ?></td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-1 text-xs font-bold rounded <?php echo $p['stock_qty'] <= 5 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'; ?>">
                        <?php echo number_format($p['stock_qty']); ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-right text-slate-600"><?php echo number_format($p['purchase_price'], 2); ?></td>
                <td class="px-6 py-4 text-sm font-bold text-right text-slate-800"><?php echo number_format($line_val, 2); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include 'layouts/footer.php'; ?>
