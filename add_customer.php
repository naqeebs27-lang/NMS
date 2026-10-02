<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $company_name = $_POST['company_name'];
    $ntn = $_POST['ntn'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    $stmt = $conn->prepare("INSERT INTO customers (name, company_name, ntn, email, phone, address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $name, $company_name, $ntn, $email, $phone, $address);

    if ($stmt->execute()) {
        header("Location: customers.php?msg=success");
        exit;
    }
}

include 'layouts/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">New Customer</h2>
            <p class="text-slate-500">Create a new customer profile.</p>
        </div>
        <a href="customers.php" class="text-slate-500 hover:text-slate-800"><i class="fas fa-times text-xl"></i></a>
    </div>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Customer Name *</label>
                <input type="text" name="name" required class="w-full px-4 py-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Company Name</label>
                <input type="text" name="company_name" class="w-full px-4 py-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">NTN Number</label>
                <input type="text" name="ntn" class="w-full px-4 py-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500" placeholder="0000000-0">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Email</label>
                <input type="email" name="email" class="w-full px-4 py-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Phone</label>
                <input type="text" name="phone" class="w-full px-4 py-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Address</label>
                <textarea name="address" rows="3" class="w-full px-4 py-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
            </div>
        </div>

        <div class="flex justify-end pt-4">
            <button type="submit" class="bg-emerald-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-100">
                Create Customer
            </button>
        </div>
    </form>
</div>

<?php include 'layouts/footer.php'; ?>
