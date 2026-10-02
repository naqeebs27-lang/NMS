<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

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
            $stmt = $conn->prepare("INSERT INTO journal_entries (entry_date, reference, description, source_module) VALUES (?, ?, ?, 'Manual')");
            $stmt->bind_param("sss", $entry_date, $reference, $description);
            $stmt->execute();
            $je_id = $conn->insert_id;

            $stmt_line = $conn->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, description, debit, credit) VALUES (?, ?, ?, ?, ?)");

            foreach ($accounts as $i => $acc_id) {
                if ($debits[$i] > 0 || $credits[$i] > 0) {
                    $stmt_line->bind_param("iisdd", $je_id, $acc_id, $line_descriptions[$i], $debits[$i], $credits[$i]);
                    $stmt_line->execute();
                }
            }

            $conn->commit();
            header("Location: journal.php?success=1");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = "Total Debits ($total_debit) must equal Total Credits ($total_credit).";
    }
}

include 'layouts/header.php';
$accounts_res = $conn->query("SELECT id, name, code FROM accounts WHERE is_active = 1 ORDER BY code ASC");
$accounts_list = [];
while($a = $accounts_res->fetch_assoc()) $accounts_list[] = $a;
?>

<div class="mb-8">
    <h2 class="text-2xl font-bold text-slate-800">New Journal Entry</h2>
    <p class="text-slate-500">Record a manual double-entry transaction.</p>
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
                <input type="date" name="entry_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Reference / Voucher #</label>
                <input type="text" name="reference" placeholder="e.g. JV-001" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Memo / Description</label>
                <input type="text" name="description" placeholder="General description of the entry" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm focus:ring-emerald-500">
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
                <?php for($i=0; $i<3; $i++): ?>
                <tr>
                    <td class="px-6 py-3">
                        <select name="account_id[]" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm focus:ring-emerald-500">
                            <option value="">Select Account</option>
                            <?php foreach($accounts_list as $acc): ?>
                            <option value="<?php echo $acc['id']; ?>"><?php echo $acc['code'] . ' - ' . $acc['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="px-6 py-3">
                        <input type="text" name="line_description[]" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm">
                    </td>
                    <td class="px-6 py-3">
                        <input type="number" step="0.01" name="debit[]" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm text-right debit-input" value="0.00">
                    </td>
                    <td class="px-6 py-3">
                        <input type="number" step="0.01" name="credit[]" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-sm text-right credit-input" value="0.00">
                    </td>
                    <td class="px-6 py-3 text-center">
                        <button type="button" class="text-rose-400 hover:text-rose-600 remove-line"><i class="fas fa-times"></i></button>
                    </td>
                </tr>
                <?php endfor; ?>
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
            <button type="reset" class="bg-white border border-slate-200 text-slate-700 px-6 py-2.5 rounded-lg hover:bg-slate-50 transition-colors">Reset</button>
            <button type="submit" class="bg-emerald-600 text-white px-8 py-2.5 rounded-lg hover:bg-emerald-700 shadow-md shadow-emerald-100 transition-all font-bold">Post Journal Entry</button>
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

        const postBtn = document.querySelector('button[type="submit"]');
        if (Math.abs(debits - credits) < 0.01 && debits > 0) {
            postBtn.disabled = false;
            postBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            // We could disable it, but better to let PHP handle it or show a UI warning
        }
    }

    linesBody.addEventListener('input', calculateTotals);

    addLineBtn.addEventListener('click', () => {
        const newRow = linesBody.rows[0].cloneNode(true);
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
