<?php
// Simple installer / setup script
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$db_host = $_POST['db_host'] ?? 'localhost';
	$db_admin_user = 'root';
	$db_admin_pass = '';
	$db_name = $_POST['db_name'] ?? 'nms_accounting';
	$app_db_user = trim($_POST['app_db_user'] ?? '') ?: $db_admin_user;
	$app_db_pass = $_POST['app_db_pass'] ?? $db_admin_pass;
	$application_username = trim($_POST['application_username'] ?? '');
	$application_password = $_POST['application_password'] ?? '';
	$create_app_user = isset($_POST['create_app_user']);

	// Business details
	$business_name = trim($_POST['business_name'] ?? 'Naqeebs Multi Services');
	$fiscal_year_start = $_POST['fiscal_year_start'] ?? null; // expected YYYY-MM-DD or MM-DD
	$business_address = trim($_POST['business_address'] ?? '');
	$logo_path = '';

	// Handle logo upload
	if (!empty($_FILES['business_logo']['name'])) {
		$uploadsDir = __DIR__ . '/uploads';
		if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0755, true);
		$tmp = $_FILES['business_logo']['tmp_name'];
		$orig = basename($_FILES['business_logo']['name']);
		$ext = pathinfo($orig, PATHINFO_EXTENSION);
		$allowed = ['png','jpg','jpeg','gif','webp'];
		if (in_array(strtolower($ext), $allowed) && is_uploaded_file($tmp)) {
			$filename = 'logo_' . time() . '.' . $ext;
			$dest = $uploadsDir . '/' . $filename;
			if (@move_uploaded_file($tmp, $dest)) {
				$logo_path = 'uploads/' . $filename;
			}
		}
	}

	// Try connect with admin creds
	$adminConn = new mysqli($db_host, $db_admin_user, $db_admin_pass);
	if ($adminConn->connect_error) {
		$error = "Unable to connect to MySQL with provided admin credentials: " . $adminConn->connect_error;
	} else {
		// Create database
		if (!$adminConn->query("CREATE DATABASE IF NOT EXISTS `" . $adminConn->real_escape_string($db_name) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
			$error = "Failed to create database: " . $adminConn->error;
		} else {
			// Optionally create application DB user
			if ($create_app_user && ($app_db_user !== $db_admin_user)) {
				$escapedUser = $adminConn->real_escape_string($app_db_user);
				$escapedPass = $adminConn->real_escape_string($app_db_pass);
				$q1 = "CREATE USER IF NOT EXISTS '" . $escapedUser . "'@'localhost' IDENTIFIED BY '" . $escapedPass . "'";
				if (!$adminConn->query($q1)) {
					$error = "Failed to create application database user: " . $adminConn->error;
				}
				$q2 = "GRANT ALL PRIVILEGES ON `" . $adminConn->real_escape_string($db_name) . "`.* TO '" . $escapedUser . "'@'localhost'";
				if (!isset($error) && !$adminConn->query($q2)) {
					$error = "Failed to grant application database permissions: " . $adminConn->error;
				}
				if (!isset($error) && !$adminConn->query("FLUSH PRIVILEGES")) {
					$error = "Failed to flush database privileges: " . $adminConn->error;
				}
			}

			// Run schema and authentication SQL using the admin connection.
			$adminConn->select_db($db_name);
			$importSql = function ($path, $label) use ($adminConn, &$error) {
				if (!file_exists($path)) {
					$error = "Missing SQL file: " . $path;
					return false;
				}

				if (!$adminConn->multi_query(file_get_contents($path))) {
					$error = "Failed to import " . $label . ": " . $adminConn->error;
					return false;
				}

				$adminConn->store_result();
				while ($adminConn->more_results()) {
					if (!$adminConn->next_result()) {
						$error = "Failed to import " . $label . ": " . $adminConn->error;
						return false;
					}
					$adminConn->store_result();
				}
				return true;
			};

			if (!isset($error)) {
				$importSql(__DIR__ . '/sql/schema.sql', 'schema');
			}
			if (!isset($error)) {
				$importSql(__DIR__ . '/sql/auth.sql', 'authentication schema');
			}
			if (!isset($error)) {
				$importSql(__DIR__ . '/sql/reconciliation.sql', 'reconciliation schema');
			}
			if (!isset($error)) {
				if ($application_username === '' || $application_password === '') {
					$error = 'Application username and password are required.';
				} else {
					$applicationPasswordHash = password_hash($application_password, PASSWORD_DEFAULT);
					$applicationEmail = $application_username . '@local.nms';
					$stmtU = $adminConn->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, 'Accountant')");
					if (!$stmtU) {
						$error = 'Failed to prepare application user creation: ' . $adminConn->error;
					} else {
						$stmtU->bind_param('ssss', $application_username, $applicationPasswordHash, $application_username, $applicationEmail);
						if (!$stmtU->execute()) {
							$error = 'Failed to create application user: ' . $stmtU->error;
						}
						$stmtU->close();
					}
				}
			}

			if (!isset($error)) {
				// Insert company info into companies table (if exists)
				$adminConn->select_db($db_name);
				$stmtC = $adminConn->prepare("INSERT INTO companies (name, logo, address, fiscal_year_start, currency) VALUES (?, ?, ?, ?, ?)");
				if ($stmtC) {
					$fy = $fiscal_year_start ?: null;
					$curr = 'PKR';
					$stmtC->bind_param('sssss', $business_name, $logo_path, $business_address, $fy, $curr);
					@$stmtC->execute();
				}

				// Write config.json
				$config = [
					'db_host' => $db_host,
					'db_user' => $app_db_user,
					'db_pass' => $app_db_pass,
					'db_name' => $db_name,
					'currency' => 'PKR',
					'company_name' => $business_name,
					'fiscal_year_start' => $fiscal_year_start,
					'address' => $business_address,
					'logo' => $logo_path,
					'oauth' => [
						'google' => ['client_id' => '', 'client_secret' => ''],
						'microsoft' => ['client_id' => '', 'client_secret' => '']
					]
				];
				$written = file_put_contents(__DIR__ . '/config.json', json_encode($config, JSON_PRETTY_PRINT));
				if ($written === false) {
					$error = "Unable to write config.json. Check file permissions.";
				} else {
					header('Location: index.php');
					exit;
				}
			}
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Setup - NMS Accounting</title>
	<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center">
	<div class="max-w-2xl w-full p-8">
		<div class="bg-white p-6 rounded-xl shadow">
			<h2 class="text-lg font-bold mb-4">Application Setup</h2>
			<?php if (isset($error)): ?>
				<div class="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded"><?php echo htmlspecialchars($error); ?></div>
			<?php endif; ?>
			<form method="post" enctype="multipart/form-data" class="space-y-4">
				<div>
					<label class="block text-sm font-medium">DB Host</label>
					<input name="db_host" value="localhost" class="mt-1 w-full p-2 border rounded" />
				</div>
				<div>
					<label class="block text-sm font-medium">Application Database Name</label>
					<input name="db_name" value="nms_accounting" class="mt-1 w-full p-2 border rounded" />
				</div>
				<div>
					<label class="block text-sm font-medium">Application DB Username (optional)</label>
					<input name="app_db_user" placeholder="leave empty to use admin user" class="mt-1 w-full p-2 border rounded" />
				</div>
				<div>
					<label class="block text-sm font-medium">Application DB Password (optional)</label>
					<input name="app_db_pass" type="password" class="mt-1 w-full p-2 border rounded" />
				</div>
				<div>
					<label class="block text-sm font-medium">Application Login Username</label>
					<input name="application_username" required class="mt-1 w-full p-2 border rounded" />
				</div>
				<div>
					<label class="block text-sm font-medium">Application Login Password</label>
					<input name="application_password" type="password" required class="mt-1 w-full p-2 border rounded" />
				</div>
			<div>
				<label class="block text-sm font-medium">Business / Company Name</label>
				<input name="business_name" value="Naqeebs Multi Services" class="mt-1 w-full p-2 border rounded" />
			</div>
			<div>
				<label class="block text-sm font-medium">Fiscal Year Start (YYYY-MM-DD)</label>
				<input name="fiscal_year_start" type="date" class="mt-1 w-full p-2 border rounded" />
			</div>
			<div>
				<label class="block text-sm font-medium">Business Address</label>
				<textarea name="business_address" class="mt-1 w-full p-2 border rounded"></textarea>
			</div>
			<div>
				<label class="block text-sm font-medium">Business Logo (png/jpg)</label>
				<input name="business_logo" type="file" accept="image/*" class="mt-1 w-full" />
			</div>
				<div class="flex items-center">
					<input type="checkbox" id="create_app_user" name="create_app_user" class="mr-2" />
					<label for="create_app_user">Create application DB user (if provided)</label>
				</div>
				<div>
					<button class="px-4 py-2 bg-emerald-600 text-white rounded">Run Setup</button>
				</div>
			</form>
		</div>
	</div>
</body>
</html>
