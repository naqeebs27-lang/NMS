<?php
require_once 'includes/config.php';

$filename = 'import_coa.csv';

if (!file_exists($filename)) {
    die("Error: 'import_coa.csv' not found. Please place it in the NMS folder.");
}

$file = fopen($filename, 'r');
$header = fgetcsv($file); // code, name, type_id, description

echo "<html><head><style>body{font-family:sans-serif; padding:20px;} .success{color:green;} .error{color:red;}</style></head><body>";
echo "<h1>Chart of Accounts Import Results</h1>";

$success = 0;
$error = 0;

while (($row = fgetcsv($file)) !== FALSE) {
    if (empty($row[0])) continue;

    $code = trim($row[0]);
    $name = trim($row[1]);
    $type_id = (int)$row[2];
    $desc = trim($row[3]);

    // Check if code exists
    $check = $conn->prepare("SELECT id FROM accounts WHERE code = ?");
    $check->bind_param("s", $code);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo "<div class='error'>Skipped $code: Already exists.</div>";
        $error++;
        continue;
    }

    $stmt = $conn->prepare("INSERT INTO accounts (code, name, type_id, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $code, $name, $type_id, $desc);

    if ($stmt->execute()) {
        echo "<div class='success'>Imported $code - $name</div>";
        $success++;
    } else {
        echo "<div class='error'>Failed $code: " . $conn->error . "</div>";
        $error++;
    }
}

fclose($file);
echo "<h3>Summary: $success successful, $error skipped/failed.</h3>";
echo "</body></html>";
?>