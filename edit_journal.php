<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $entry_date = $_POST['entry_date'];
    $reference = $_POST['reference'];
    $description = $_POST['description'];

    $accounts = $_POST['account_id'];
    $debits = $_POST['debit'];
    $credits = $_POST['credit'];
    $line_descriptions = $_POST['line_description'];

    $total_debit = array_sum($debits);
    $total_credit = array_sum($credits);

    if (abs($total_debit - $total_credit) < 0.01 && $total_debit > 0) {
        $conn->begin_transaction();
        try {
            // Update main entry
            $stmt = $conn->prepare("UPDATE journal_entries SET entry_date = ?, reference = ?, description = ? WHERE id = ?");
            $stmt->bind_param("sssi", $entry_date, $reference, $description, $id);
            $stmt->execute();

            // Delete old lines
            $conn->query("DELETE FROM journal_entry_lines WHERE journal_entry_id = $id");

            // Insert new lines
            $stmt_line = $conn->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, description, debit, credit) VALUES (?, ?, ?, ?, ?)");

            foreach ($accounts as $i => $acc_id) {
                if ($debits[$i] > 0 || $credits[$i] > 0) {
                    $stmt_line->bind_param("iisdd", $id, $acc_id, $line_descriptions[$i], $debits[$i], $credits[$i]);
                    $stmt_line->execute();
                }
            }

            $conn->commit();
            header("Location: journal.php?success=edit");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = "Total Debits ($total_debit) must equal Total Credits ($total_credit).";
    }
}

// Fetch existing data
$stmt = $conn->prepare("SELECT * FROM journal_entries WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$entry = $stmt->get_result()->fetch_assoc();

if (!$entry) {
    die("Entry not found.");
}

$lines_res = $conn->query("SELECT * FROM journal_entry_lines WHERE journal_entry_id = $id");
$existing_lines = [];
while($l = $lines_res->fetch_assoc()) $existing_lines[] = $l;

include 'layouts/header.php';
$accounts_res = $conn->query("SELECT id, name, code FROM accounts WHERE is_active = 1 ORDER BY code ASC");
$accounts_list = [];
while($a = $accounts_res->fetch_assoc()) $accounts_list[] = $a;
?>

<div class="mb-8">
    <h2 class="text-2xl font-bold text-slate-800">Edit Journal Entry</h2>
    <p class="text-slate-500">Modify an existing transaction.</p>
</div>

<?php if (isset($error)): ?>
<div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg flex items-center">
    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
</div>
<?php endif; ?>

<form method="POST" id="journalForm">
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Transaction Date *</label>
                <input type="date" name="entry_date" required value="<?php echo $entry['entry_date']; ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Reference / Voucher #</label>
                <input type="text" name="reference" value="<?php echo htmlspecialchars($entry['reference']); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Memo / Description</label>
                <input type="text" name="description" value="<?php echo htmlspecialchars($entry['description']); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm focus:ring-emerald-500">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
        <table class="w-full text-left border-collapse" id="linesTable">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase w-1/3">Account</th>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Line Description</th>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right w-32">Debit</th>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right w-32">Credit</th>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase w-16 text-center"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100" id="linesBody">
                <?php foreach($existing_lines as $line): ?>
                <tr>
                    <td class="px-6 py-3">
                        <select name="account_id[]" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm focus:ring-emerald-500">
                            <option value="">Select Account</option>
                            <?php foreach($accounts_list as $acc): ?>
                            <option value="<?php echo $acc['id']; ?>" <?php echo $acc['id'] == $line['account_id'] ? 'selected' : ''; ?>><?php echo $acc['code'] . ' - ' . $acc['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="px-6 py-3">
                        <input type="text" name="line_description[]" value="<?php echo htmlspecialchars($line['description']); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm">
                    </td>
                    <td class="px-6 py-3">
                        <input type="number" step="0.01" name="debit[]" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm text-right debit-input" value="<?php echo number_format($line['debit'], 2, '.', ''); ?>">
                    </td>
                    <td class="px-6 py-3">
                        <input type="number" step="0.01" name="credit[]" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm text-right credit-input" value="<?php echo number_format($line['credit'], 2, '.', ''); ?>">
                    </td>
                    <td class="px-6 py-3 text-center">
                        <button type="button" class="text-rose-400 hover:text-rose-600 remove-line"><i class="fas fa-times"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-slate-50 border-t border-slate-200 font-bold">
                <tr>
                    <td colspan="2" class="px-6 py-4 text-right text-slate-600">Totals</td>
                    <td class="px-6 py-4 text-right text-slate-800" id="totalDebit">0.00</td>
                    <td class="px-6 py-4 text-right text-slate-800" id="totalCredit">0.00</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <div class="p-4 bg-slate-50 border-t border-slate-100">
            <button type="button" id="addLine" class="text-emerald-600 text-sm font-semibold hover:text-emerald-700">
                <i class="fas fa-plus mr-1"></i> Add Another Line
            </button>
        </div>
    </div>

    <div class="flex justify-between items-center">
        <a href="journal.php" class="text-slate-500 hover:text-slate-700 font-medium">Cancel and Go Back</a>
        <div class="space-x-3">
            <button type="submit" class="bg-emerald-600 text-white px-8 py-2.5 rounded-lg hover:bg-emerald-700 shadow-md shadow-emerald-100 transition-all font-bold">Update Journal Entry</button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const linesBody = document.getElementById('linesBody');
    const addLineBtn = document.getElementById('addLine');

    function calculateTotals() {
        let debits = 0;
        let credits = 0;
        document.querySelectorAll('.debit-input').forEach(input => debits += parseFloat(input.value || 0));
        document.querySelectorAll('.credit-input').forEach(input => credits += parseFloat(input.value || 0));

        document.getElementById('totalDebit').innerText = debits.toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('totalCredit').innerText = credits.toLocaleString(undefined, {minimumFractionDigits: 2});
    }

    linesBody.addEventListener('input', calculateTotals);

    addLineBtn.addEventListener('click', () => {
        const firstRow = linesBody.rows[0];
        const newRow = firstRow.cloneNode(true);
        newRow.querySelectorAll('input').forEach(input => input.value = input.type === 'number' ? '0.00' : '');
        newRow.querySelector('select').value = '';
        linesBody.appendChild(newRow);
    });

    linesBody.addEventListener('click', (e) => {
        if (e.target.closest('.remove-line')) {
            if (linesBody.rows.length > 2) {
                e.target.closest('tr').remove();
                calculateTotals();
            }
        }
    });

    calculateTotals();
});
</script>

<?php include 'layouts/footer.php'; ?>
