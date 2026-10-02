<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
include 'layouts/header.php';

// Check if user is Admin
if (!isAdmin()) {
    echo "<div class='p-8 text-center bg-rose-50 text-rose-700 rounded-xl border border-rose-200'>
            <i class='fas fa-lock text-4xl mb-4'></i>
            <h2 class='text-xl font-bold'>Access Denied</h2>
            <p>Only administrators can view the system audit trail.</p>
          </div>";
    include 'layouts/footer.php';
    exit;
}

$sql = "SELECT al.*, u.full_name as user_name
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC LIMIT 500";
$result = $conn->query($sql);
?>

<div class="mb-8">
    <h2 class="text-2xl font-bold text-slate-800">System Audit Trail</h2>
    <p class="text-slate-500">Security log of all critical actions performed in the system.</p>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Timestamp</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">User</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Action</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Module</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Details</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">IP Address</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 text-sm text-slate-600"><?php echo date('d M Y, H:i:s', strtotime($row['created_at'])); ?></td>
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <div class="w-7 h-7 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 text-[10px] font-bold mr-2">
                                <?php echo substr($row['user_name'] ?? 'S', 0, 1); ?>
                            </div>
                            <span class="text-sm font-medium text-slate-800"><?php echo $row['user_name'] ?: 'System'; ?></span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded uppercase
                            <?php
                            if(strpos($row['action'], 'Create') !== false) echo 'bg-emerald-100 text-emerald-700';
                            elseif(strpos($row['action'], 'Delete') !== false) echo 'bg-rose-100 text-rose-700';
                            else echo 'bg-blue-100 text-blue-700';
                            ?>">
                            <?php echo $row['action']; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600"><?php echo $row['module']; ?></td>
                    <td class="px-6 py-4 text-sm text-slate-500 max-w-xs truncate" title="<?php echo $row['details']; ?>">
                        <?php echo $row['details']; ?>
                    </td>
                    <td class="px-6 py-4 text-xs font-mono text-slate-400"><?php echo $row['ip_address']; ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-slate-400">No logs found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'layouts/footer.php'; ?>
