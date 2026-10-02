<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$filename = 'import_journals.csv';

if (!file_exists($filename)) {
    die("Error: 'import_journals.csv' not found in C:/xampp/htdocs/NMS/. Please ensure the file is copied to the XAMPP folder.");
}

$file = fopen($filename, 'r');
$header = fgetcsv($file); // Skip header row: Date,Reference,Description,Account ID,Debit,Credit,Account,,

$journals = [];
$row_num = 1;
while (($row = fgetcsv($file)) !== FALSE) {
    if (empty($row[0])) continue;
    $row_num++;

    $date = trim($row[0]);
    $ref = trim($row[1]);
    $entry_desc = trim($row[2]);
    $acc_code = trim($row[3]); // Column "Account ID" actually contains A001, etc.
    $line_desc = trim($row[2]); // Using entry description as line description if needed

    // Clean numeric values (remove quotes, commas, spaces and handles " - ")
    $clean_debit = str_replace(['"', ',', ' '], '', $row[4]);
    if ($clean_debit == '-') $clean_debit = 0;
    $debit = (float)$clean_debit;

    $clean_credit = str_replace(['"', ',', ' '], '', $row[5]);
    if ($clean_credit == '-') $clean_credit = 0;
    $credit = (float)$clean_credit;

    // Group lines by reference
    if (!isset($journals[$ref])) {
        $journals[$ref] = [
            'date' => $date,
            'description' => $entry_desc,
            'lines' => []
        ];
    }

    $journals[$ref]['lines'][] = [
        'code' => $acc_code,
        'description' => $line_desc,
        'debit' => $debit,
        'credit' => $credit
    ];
}
fclose($file);

echo "<html><head><style>body{font-family:sans-serif; line-height:1.5; padding:20px;} .success{color:green;} .error{color:red;} hr{border:0; border-top:1px solid #eee; margin:10px 0;}</style></head><body>";
echo "<h1>Journal Import Results</h1>";

$success_count = 0;
$error_count = 0;

foreach ($journals as $ref => $data) {
    $lines_to_post = [];
    $total_debit = 0;
    $total_credit = 0;

    foreach ($data['lines'] as $l) {
        $total_debit += $l['debit'];
        $total_credit += $l['credit'];

        // Find account ID from code
        $stmt = $conn->prepare("SELECT id, name FROM accounts WHERE code = ?");
        $stmt->bind_param("s", $l['code']);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();

        if (!$res) {
            echo "<div class='error'><strong>Skipped Reference $ref:</strong> Account code <code>" . $l['code'] . "</code> not found in Chart of Accounts.</div><hr>";
            $error_count++;
            continue 2;
        }

        $lines_to_post[] = [
            'account_id' => $res['id'],
            'description' => $l['description'],
            'debit' => $l['debit'],
            'credit' => $l['credit']
        ];
    }

    // Check balance before posting
    if (abs($total_debit - $total_credit) > 0.1) {
        echo "<div class='error'><strong>Skipped Reference $ref:</strong> Entry is Unbalanced (Debit: $total_debit, Credit: $total_credit). Difference: " . ($total_debit - $total_credit) . "</div><hr>";
        $error_count++;
        continue;
    }

    $result = postJournalEntry($conn, $data['date'], $ref, $data['description'], 'Import', $lines_to_post);

    if ($result['success']) {
        echo "<div class='success'><strong>Imported $ref:</strong> " . count($lines_to_post) . " lines posted successfully for Date " . $data['date'] . ".</div><hr>";
        $success_count++;
    } else {
        echo "<div class='error'><strong>Failed $ref:</strong> " . $result['error'] . "</div><hr>";
        $error_count++;
    }
}

echo "<h2>Import Finished Summary</h2>";
echo "<p>Successfully Imported: <strong class='success'>$success_count</strong></p>";
echo "<p>Errors/Skipped: <strong class='error'>$error_count</strong></p>";
echo "</body></html>";
?>