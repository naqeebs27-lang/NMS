<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Handle Add/Edit Account
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $code = $_POST['code'] ?? '';
    $name = $_POST['name'] ?? '';
    $type_id = $_POST['type_id'] ?? '';
    $description = $_POST['description'] ?? '';
    $account_id = $_POST['account_id'] ?? '';

    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO accounts (code, name, type_id, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $code, $name, $type_id, $description);
        $stmt->execute();
    } elseif ($action === 'edit') {
        $stmt = $conn->prepare("UPDATE accounts SET code = ?, name = ?, type_id = ?, description = ? WHERE id = ?");
        $stmt->bind_param("ssisi", $code, $name, $type_id, $description, $account_id);
        $stmt->execute();
    }
    header("Location: chart_of_accounts.php");
    exit;
}

include 'layouts/header.php';

$sql = "SELECT a.*, at.name as type_name, at.normal_balance
        FROM accounts a
        JOIN account_types at ON a.type_id = at.id
        ORDER BY a.code ASC";
$result = $conn->query($sql);

$types_result = $conn->query("SELECT * FROM account_types ORDER BY name ASC");
$types = [];
while ($t = $types_result->fetch_assoc()) {
    $types[] = $t;
}
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Chart of Accounts</h2>
        <p class="text-slate-500">Manage your business accounts and their categories.</p>
    </div>
    <button onclick="openModal('add')" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition-colors">
        <i class="fas fa-plus mr-2"></i> Add Account
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Code</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Account Name</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Type</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Balance</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-center">Status</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php while($row = $result->fetch_assoc()):
                $balance = getAccountBalance($row['id'], $conn);
            ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 text-sm font-medium text-slate-600"><?php echo $row['code']; ?></td>
                <td class="px-6 py-4 text-sm font-semibold text-slate-800"><?php echo $row['name']; ?></td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs font-medium rounded-full
                        <?php
                        switch($row['type_name']) {
                            case 'Asset': echo 'bg-blue-50 text-blue-700'; break;
                            case 'Liability': echo 'bg-amber-50 text-amber-700'; break;
                            case 'Equity': echo 'bg-purple-50 text-purple-700'; break;
                            case 'Revenue': echo 'bg-emerald-50 text-emerald-700'; break;
                            case 'Expense': echo 'bg-rose-50 text-rose-700'; break;
                            case 'Cost of Sales': echo 'bg-orange-50 text-orange-700'; break;
                            case 'Bank': echo 'bg-sky-50 text-sky-700'; break;
                        }
                        ?>">
                        <?php echo $row['type_name']; ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-sm font-bold text-slate-800 text-right">
                    <?php echo formatPKR($balance); ?>
                </td>
                <td class="px-6 py-4 text-center actions-cell">
                    <span class="w-2 h-2 rounded-full inline-block bg-emerald-500 mr-1"></span>
                    <span class="text-xs text-slate-600">Active</span>
                </td>
                <td class="px-6 py-4 text-center actions-cell">
                    <button onclick='openModal("edit", <?php echo json_encode($row); ?>)' class="text-slate-400 hover:text-emerald-600 mx-2"><i class="fas fa-edit"></i></button>
                    <a href="ledger.php?account_id=<?php echo $row['id']; ?>" class="text-slate-400 hover:text-blue-600 mx-2"><i class="fas fa-history"></i></a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Add/Edit Modal -->
<div id="accountModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        <form method="POST">
            <input type="hidden" name="action" id="modalAction" value="add">
            <input type="hidden" name="account_id" id="modalAccountId" value="">

            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-800" id="modalTitle">Add New Account</h3>
                <button type="button" onclick="closeModal()" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Account Code</label>
                    <input type="text" name="code" id="modalCode" required class="w-full rounded-lg border-slate-200 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Account Name</label>
                    <input type="text" name="name" id="modalName" required class="w-full rounded-lg border-slate-200 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Account Type</label>
                    <select name="type_id" id="modalTypeId" required class="w-full rounded-lg border-slate-200 focus:ring-emerald-500 focus:border-emerald-500">
                        <?php foreach($types as $type): ?>
                            <option value="<?php echo $type['id']; ?>"><?php echo $type['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea name="description" id="modalDescription" class="w-full rounded-lg border-slate-200 focus:ring-emerald-500 focus:border-emerald-500" rows="2"></textarea>
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                <button type="button" onclick="closeModal()" class="px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-200 rounded-lg transition-colors">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors">Save Account</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(action, account = null) {
    document.getElementById('modalAction').value = action;
    if (action === 'edit' && account) {
        document.getElementById('modalTitle').innerText = 'Edit Account';
        document.getElementById('modalAccountId').value = account.id;
        document.getElementById('modalCode').value = account.code;
        document.getElementById('modalName').value = account.name;
        document.getElementById('modalTypeId').value = account.type_id;
        document.getElementById('modalDescription').value = account.description;
    } else {
        document.getElementById('modalTitle').innerText = 'Add New Account';
        document.getElementById('modalAccountId').value = '';
        document.getElementById('modalCode').value = '';
        document.getElementById('modalName').value = '';
        document.getElementById('modalDescription').value = '';
    }
    document.getElementById('accountModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('accountModal').classList.add('hidden');
}
</script>

<?php include 'layouts/footer.php'; ?>
