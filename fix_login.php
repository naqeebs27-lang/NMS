<?php
require_once 'includes/config.php';

$new_password = password_hash('admin123', PASSWORD_DEFAULT);
$username = 'admin';

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
$stmt->bind_param("ss", $new_password, $username);

if ($stmt->execute()) {
    echo "<h1>Success!</h1>";
    echo "<p>The password for user 'admin' has been reset to <strong>admin123</strong>.</p>";
    echo "<p><a href='login.php'>Click here to login</a></p>";
} else {
    echo "<h1>Error</h1>";
    echo "<p>Could not update password: " . $conn->error . "</p>";
}
?>