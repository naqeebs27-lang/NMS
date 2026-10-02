<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_rate'])) {
    $id = $_POST['currency_id'];
    $rate = $_POST['exchange_rate'];
    $stmt = $conn->prepare("UPDATE currencies SET exchange_rate = ? WHERE id = ?");
    $stmt->bind_param("di", $rate, $id);
    $stmt->execute();
    header("Location: currencies.php?success=1");
    exit;
}

$result = $conn->query("SELECT * FROM currencies ORDER BY is_default DESC, code ASC");
include 'layouts/header.php';
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Currencies</h2>
        <p class="text-slate-500">Manage exchange rates for multi-currency transactions.</p>
    </div>
    <button class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition-colors">
        <i class="fas fa-plus mr-2"></i> Add Currency
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Code</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Currency Name</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Exchange Rate (to PKR)</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Default</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php while($row = $result->fetch_assoc()): ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 text-sm font-bold text-slate-800"><?php echo $row['code']; ?></td>
                <td class="px-6 py-4 text-sm text-slate-600"><?php echo $row['name']; ?> (<?php echo $row['symbol']; ?>)</td>
                <td class="px-6 py-4 text-sm text-slate-800">
                    <form method="POST" class="flex items-center space-x-2">
                        <input type="hidden" name="currency_id" value="<?php echo $row['id']; ?>">
                        <input type="number" name="exchange_rate" step="0.000001" value="<?php echo $row['exchange_rate']; ?>" class="bg-slate-50 border border-slate-200 rounded p-1 text-sm w-32" <?php echo $row['is_default'] ? 'disabled' : ''; ?>>
                        <?php if(!$row['is_default']): ?>
                        <button type="submit" name="update_rate" class="text-emerald-600 hover:text-emerald-700 text-xs font-bold">Update</button>
                        <?php endif; ?>
                    </form>
                </td>
                <td class="px-6 py-4">
                    <?php if($row['is_default']): ?>
                    <span class="px-2 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-full uppercase">Base Currency</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-center">
                    <button class="text-slate-400 hover:text-emerald-600 mx-1"><i class="fas fa-edit"></i></button>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include 'layouts/footer.php'; ?>
