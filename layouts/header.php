<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/functions.php';
checkLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NMS Accounting - Naqeebs Multi Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .sidebar-item:hover { background-color: rgba(255,255,255,0.05); }
        .sidebar-active { background-color: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; color: #10b981 !important; }
        .sidebar-active i { color: #10b981; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }

        @media print {
            .no-print,
            aside,
            header,
            .mb-8.flex.justify-between,
            #filterSection,
            button,
            .actions-cell {
                display: none !important;
            }
            body { background-color: white !important; height: auto !important; overflow: visible !important; }
            main { padding: 0 !important; overflow: visible !important; }
            .flex-1 { overflow: visible !important; }
            .bg-white { border: none !important; box-shadow: none !important; }
            .rounded-xl { border-radius: 0 !important; }
            table { width: 100% !important; border: 1px solid #e2e8f0 !important; }
            th, td { border-bottom: 1px solid #e2e8f0 !important; padding: 8px !important; }
            .flex.h-screen { display: block !important; height: auto !important; }
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50">
    <!-- Sidebar -->
    <aside class="w-72 bg-slate-950 text-slate-300 flex-shrink-0 hidden lg:flex flex-col border-r border-slate-800 no-print">
        <div class="p-6 flex items-center space-x-3">
            <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center overflow-hidden shadow-lg shadow-blue-900/20">
                <img src="nms_logo.png" alt="NMS Logo" class="w-full h-full object-contain">
            </div>
            <div>
                <h1 class="text-lg font-bold text-white leading-none">NMS</h1>
                <p class="text-[10px] text-slate-500 uppercase tracking-widest mt-1 font-semibold">Naqeebs Multi Services</p>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-4 py-4 space-y-1">
            <a href="index.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'sidebar-active text-white' : ''; ?>">
                <i class="fas fa-th-large w-5 mr-3"></i> Dashboard
            </a>

            <div class="pt-4 pb-2 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Sales</div>
            <a href="customers.php" class="flex items-center px-4 py-2 text-sm rounded-lg sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'sidebar-active text-white' : ''; ?>">
                <i class="fas fa-users w-5 mr-3"></i> Customers
            </a>
            <a href="products.php" class="flex items-center px-4 py-2 text-sm rounded-lg sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'sidebar-active text-white' : ''; ?>">
                <i class="fas fa-box w-5 mr-3"></i> Products & Services
            </a>
            <a href="invoices.php" class="flex items-center px-4 py-2 text-sm rounded-lg sidebar-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['invoices.php', 'create_invoice.php']) ? 'sidebar-active text-white' : ''; ?>">
                <i class="fas fa-file-invoice-dollar w-5 mr-3"></i> Invoices
            </a>

            <div class="pt-4 pb-2 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Purchases</div>
            <a href="suppliers.php" class="flex items-center px-4 py-2 text-sm rounded-lg sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? 'sidebar-active text-white' : ''; ?>">
                <i class="fas fa-truck w-5 mr-3"></i> Suppliers
            </a>
            <a href="bills.php" class="flex items-center px-4 py-2 text-sm rounded-lg sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'bills.php' ? 'sidebar-active text-white' : ''; ?>">
                <i class="fas fa-file-invoice w-5 mr-3"></i> Bills
            </a>
            <a href="expenses.php" class="flex items-center px-4 py-2 text-sm rounded-lg sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'expenses.php' ? 'sidebar-active text-white' : ''; ?>">
                <i class="fas fa-receipt w-5 mr-3"></i> Expenses
            </a>

            <div class="pt-4 pb-2 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Banking</div>
            <a href="banking.php" class="flex items-center px-4 py-2 text-sm rounded-lg sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'banking.php' ? 'sidebar-active text-white' : ''; ?>">
                <i class="fas fa-university w-5 mr-3"></i> Bank Accounts
            </a>
            <a href="petty_cash.php" class="flex items-center px-4 py-2 text-sm rounded-lg sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'petty_cash.php' ? 'sidebar-active text-white' : ''; ?>">
                <i class="fas fa-wallet w-5 mr-3"></i> Petty Cash
            </a>

            <div class="pt-4 pb-2 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Accounting</div>
            <a href="chart_of_accounts.php" class="flex items-center px-4 py-2 text-sm rounded-lg sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'chart_of_accounts.php' ? 'sidebar-active text-white' : ''; ?>">
                <i class="fas fa-list-ul w-5 mr-3"></i> Chart of Accounts
            </a>
            <a href="journal.php" class="flex items-center px-4 py-2 text-sm rounded-lg sidebar-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['journal.php', 'create_journal.php']) ? 'sidebar-active text-white' : ''; ?>">
                <i class="fas fa-book w-5 mr-3"></i> General Journal
            </a>
            <a href="ledger.php" class="flex items-center px-4 py-2 text-sm rounded-lg sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'ledger.php' ? 'sidebar-active text-white' : ''; ?>">
                <i class="fas fa-book-open w-5 mr-3"></i> General Ledger
            </a>
            <a href="fix_opening.php" class="flex items-center px-4 py-2 text-sm rounded-lg sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'fix_opening.php' ? 'sidebar-active text-white' : ''; ?>">
                <i class="fas fa-rotate w-5 mr-3"></i> Reset Opening Data
            </a>
            <a href="fix_opening.php" class="flex items-center px-4 py-2 text-sm rounded-lg sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'fix_opening.php' ? 'sidebar-active text-white' : ''; ?>">
                <i class="fas fa-scale-balanced w-5 mr-3"></i> Set Opening balance
            </a>

            <div class="pt-4 pb-2 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Reports</div>
            <a href="income_statement.php" class="flex items-center px-4 py-2 text-sm rounded-lg sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'income_statement.php' ? 'sidebar-active text-white' : ''; ?>">
                <i class="fas fa-chart-bar w-5 mr-3"></i> Profit & Loss
            </a>
            <a href="balance_sheet.php" class="flex items-center px-4 py-2 text-sm rounded-lg sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'balance_sheet.php' ? 'sidebar-active text-white' : ''; ?>">
                <i class="fas fa-balance-scale w-5 mr-3"></i> Balance Sheet
            </a>
            <a href="trial_balance.php" class="flex items-center px-4 py-2 text-sm rounded-lg sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'trial_balance.php' ? 'sidebar-active text-white' : ''; ?>">
                <i class="fas fa-list-check w-5 mr-3"></i> Trial Balance
            </a>
        </nav>

        <div class="p-4 bg-slate-900 border-t border-slate-800">
            <div class="flex items-center p-2 rounded-lg bg-slate-950/50">
                <div class="w-8 h-8 rounded bg-emerald-500 flex items-center justify-center text-white text-xs font-bold"><?php echo substr($_SESSION['full_name'] ?? 'U', 0, 1); ?></div>
                <div class="ml-3 overflow-hidden">
                    <p class="text-xs font-bold text-white truncate"><?php echo $_SESSION['full_name'] ?? 'User'; ?></p>
                    <p class="text-[10px] text-slate-500 truncate"><?php echo $_SESSION['user_role'] ?? 'Accountant'; ?></p>
                </div>
                <a href="logout.php" class="ml-auto text-slate-500 hover:text-rose-500"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
        <!-- Top Navigation -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-8 flex-shrink-0 z-10 no-print">
            <div class="flex items-center flex-1">
                <button class="lg:hidden mr-4 text-slate-500 hover:text-slate-700 transition-colors">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>

            <div class="flex items-center space-x-6">
                <div class="flex items-center space-x-4 relative group">
                    <button class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-emerald-700 shadow-lg shadow-emerald-100 transition-all flex items-center">
                        <i class="fas fa-plus mr-2 text-[10px]"></i> Create New
                    </button>
                    <!-- Dropdown Menu -->
                    <div class="absolute right-0 top-full mt-2 w-56 bg-white rounded-xl shadow-xl border border-slate-100 py-2 hidden group-hover:block z-50">
                        <a href="create_invoice.php" class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                            <i class="fas fa-file-invoice-dollar w-5 mr-3 text-slate-400"></i> Sales Invoice
                        </a>
                        <a href="create_bill.php" class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                            <i class="fas fa-file-invoice w-5 mr-3 text-slate-400"></i> Supplier Bill
                        </a>
                        <a href="record_expense.php" class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                            <i class="fas fa-receipt w-5 mr-3 text-slate-400"></i> Expense
                        </a>
                        <a href="petty_cash.php" class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                            <i class="fas fa-wallet w-5 mr-3 text-slate-400"></i> Petty Cash Voucher
                        </a>
                        <a href="create_journal.php" class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                            <i class="fas fa-book w-5 mr-3 text-slate-400"></i> Journal Entry
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-y-auto p-4 md:p-8">
