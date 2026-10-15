<?php
// Load configuration from config.json if present. If missing, redirect to setup.
$configPath = __DIR__ . '/../config.json';
if (!file_exists($configPath)) {
    // Allow CLI or installer to proceed; when accessed via web redirect to setup page
    if (php_sapi_name() !== 'cli') {
        header('Location: /nms/setup.php');
        exit;
    }
}

$config = [];
if (file_exists($configPath)) {
    $raw = file_get_contents($configPath);
    $config = json_decode($raw, true) ?: [];
}

// Fallbacks for backward compatibility
define('DB_HOST', $config['db_host'] ?? 'localhost');
define('DB_USER', $config['db_user'] ?? 'root');
define('DB_PASS', $config['db_pass'] ?? '');
define('DB_NAME', $config['db_name'] ?? 'nms_accounting');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// OAuth config (optional)
define('OAUTH_GOOGLE_CLIENT_ID', $config['oauth']['google']['client_id'] ?? '');
define('OAUTH_GOOGLE_CLIENT_SECRET', $config['oauth']['google']['client_secret'] ?? '');
define('OAUTH_MICROSOFT_CLIENT_ID', $config['oauth']['microsoft']['client_id'] ?? '');
define('OAUTH_MICROSOFT_CLIENT_SECRET', $config['oauth']['microsoft']['client_secret'] ?? '');

// Global settings
$currency = $config['currency'] ?? "PKR";
$company_name = $config['company_name'] ?? "Naqeebs Multi Services";
?>
