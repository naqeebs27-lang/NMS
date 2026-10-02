<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
checkLogin();

$id = $_GET['id'] ?? 0;
$ri_stmt = $conn->prepare("SELECT * FROM recurring_invoices WHERE id = ?");
$ri_stmt->bind_param("i", $id);
$ri_stmt->execute();
$ri = $ri_stmt->get_result()->fetch_assoc();

if (!$ri) {
    header("Location: recurring_invoices.php");
    exit;
}

$customers_res = $conn->query("SELECT id, name, company_name FROM customers ORDER BY name ASC");
$products_res = $conn->query("SELECT id, name, sku, sale_price, tax_rate FROM products ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $status = $_POST['status'];
    $frequency = $_POST['frequency'];
    $next_date = $_POST['next_generation_date'];

    $conn->begin_transaction();
    try {
        $upd = $conn->prepare("UPDATE recurring_invoices SET status = ?, frequency = ?, next_generation_date = ? WHERE id = ?");
        $upd->bind_param("sssi", $status, $frequency, $next_date, $id);
        $upd->execute();

        $conn->commit();
        header("Location: recurring_invoices.php?updated=1");
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
        <h2 class="text-2xl font-bold text-slate-800">Edit Recurring Invoice</h2>
        <p class="text-slate-500">Update frequency or status for this template.</p>
    </div>
    <a href="recurring_invoices.php" class="text-slate-500 hover:text-slate-700 font-medium"><i class="fas fa-arrow-left mr-2"></i> Back</a>
</div>

<form method="POST">
    <div class="max-w-2xl bg-white p-8 rounded-xl shadow-sm border border-slate-200">
        <div class="grid grid-cols-1 gap-6">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Customer</label>
                <input type="text" value="<?php
                    mysqli_data_seek($customers_res, 0);
                    while($c = $customers_res->fetch_assoc()) {
                        if ($c['id'] == $ri['customer_id']) echo $c['name'];
                    }
                ?>" disabled class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm text-slate-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Frequency</label>
                    <select name="frequency" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm">
                        <option value="Weekly" <?php echo $ri['frequency'] == 'Weekly' ? 'selected' : ''; ?>>Weekly</option>
                        <option value="Monthly" <?php echo $ri['frequency'] == 'Monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="Quarterly" <?php echo $ri['frequency'] == 'Quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                        <option value="Yearly" <?php echo $ri['frequency'] == 'Yearly' ? 'selected' : ''; ?>>Yearly</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                    <select name="status" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm">
                        <option value="Active" <?php echo $ri['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Paused" <?php echo $ri['status'] == 'Paused' ? 'selected' : ''; ?>>Paused</option>
                        <option value="Completed" <?php echo $ri['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Next Generation Date</label>
                <input type="date" name="next_generation_date" value="<?php echo $ri['next_generation_date']; ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm">
            </div>

            <div class="pt-4">
                <button type="submit" class="bg-emerald-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-emerald-700 transition-all">
                    Update Template
                </button>
            </div>
        </div>
    </div>
</form>

<?php include 'layouts/footer.php'; ?>
