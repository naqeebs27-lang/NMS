<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
session_start();

$provider = $_GET['provider'] ?? '';
$code = $_GET['code'] ?? '';
if (!$provider || !$code) { header('Location: login.php'); exit; }

function http_post($url, $data, $headers = []) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	$res = curl_exec($ch);
	$err = curl_error($ch);
	curl_close($ch);
	if ($err) return false;
	return $res;
}

if ($provider === 'google') {
	$tokenUrl = 'https://oauth2.googleapis.com/token';
	$clientId = OAUTH_GOOGLE_CLIENT_ID;
	$clientSecret = OAUTH_GOOGLE_CLIENT_SECRET;
	$redirect = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/oauth_callback.php?provider=google';
	$post = http_build_query(['code' => $code, 'client_id' => $clientId, 'client_secret' => $clientSecret, 'redirect_uri' => $redirect, 'grant_type' => 'authorization_code']);
	$resp = http_post($tokenUrl, $post, ['Content-Type: application/x-www-form-urlencoded']);
	if (!$resp) { header('Location: login.php'); exit; }
	$token = json_decode($resp, true);
	$access = $token['access_token'] ?? null;
	$id_token = $token['id_token'] ?? null;
	// fetch userinfo
	$userinfo = json_decode(file_get_contents('https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . urlencode($access)), true);
	$email = $userinfo['email'] ?? null;
	$name = $userinfo['name'] ?? ($userinfo['given_name'] ?? '');
} elseif ($provider === 'microsoft') {
	$tokenUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
	$clientId = OAUTH_MICROSOFT_CLIENT_ID;
	$clientSecret = OAUTH_MICROSOFT_CLIENT_SECRET;
	$redirect = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/oauth_callback.php?provider=microsoft';
	$post = http_build_query(['code' => $code, 'client_id' => $clientId, 'client_secret' => $clientSecret, 'redirect_uri' => $redirect, 'grant_type' => 'authorization_code']);
	$resp = http_post($tokenUrl, $post, ['Content-Type: application/x-www-form-urlencoded']);
	if (!$resp) { header('Location: login.php'); exit; }
	$token = json_decode($resp, true);
	$access = $token['access_token'] ?? null;
	// get userinfo
	$opts = ['http'=>['header'=>'Authorization: Bearer ' . $access]];
	$context = stream_context_create($opts);
	$userinfo = json_decode(file_get_contents('https://graph.microsoft.com/v1.0/me', false, $context), true);
	$email = $userinfo['mail'] ?? $userinfo['userPrincipalName'] ?? null;
	$name = $userinfo['displayName'] ?? '';
} else {
	header('Location: login.php'); exit;
}

if (!$email) { header('Location: login.php'); exit; }

// Lookup user by email in users table; create if missing
$stmt = $conn->prepare("SELECT id, username, role, full_name FROM users WHERE email = ? AND is_active = 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
if ($user = $res->fetch_assoc()) {
	$_SESSION['user_id'] = $user['id'];
	$_SESSION['username'] = $user['username'];
	$_SESSION['user_role'] = $user['role'];
	$_SESSION['full_name'] = $user['full_name'];
	header('Location: index.php'); exit;
} else {
	// create user
	$username = preg_replace('/[^a-z0-9_\.]/i', '_', strtolower($email));
	$passwordHash = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
	$fullName = $name;
	$role = 'User';
	$stmt2 = $conn->prepare("INSERT INTO users (username, password, role, full_name, email, is_active) VALUES (?, ?, ?, ?, ?, 1)");
	$stmt2->bind_param('sssss', $username, $passwordHash, $role, $fullName, $email);
	if ($stmt2->execute()) {
		$uid = $conn->insert_id;
		$_SESSION['user_id'] = $uid;
		$_SESSION['username'] = $username;
		$_SESSION['user_role'] = $role;
		$_SESSION['full_name'] = $fullName;
		header('Location: index.php'); exit;
	} else {
		header('Location: login.php'); exit;
	}
}
