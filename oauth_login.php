<?php
require_once 'includes/config.php';
session_start();

$provider = $_GET['provider'] ?? '';
if (!$provider) {
	header('Location: login.php'); exit;
}

if ($provider === 'google') {
	$clientId = OAUTH_GOOGLE_CLIENT_ID;
	$redirect = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/oauth_callback.php?provider=google';
	$scope = urlencode('openid email profile');
	$url = 'https://accounts.google.com/o/oauth2/v2/auth?response_type=code&client_id=' . urlencode($clientId) . '&redirect_uri=' . urlencode($redirect) . '&scope=' . $scope . '&access_type=offline&prompt=select_account';
	header('Location: ' . $url);
	exit;
} elseif ($provider === 'microsoft') {
	$clientId = OAUTH_MICROSOFT_CLIENT_ID;
	$redirect = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/oauth_callback.php?provider=microsoft';
	$scope = urlencode('openid email profile offline_access');
	$url = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?client_id=' . urlencode($clientId) . '&response_type=code&redirect_uri=' . urlencode($redirect) . '&response_mode=query&scope=' . $scope;
	header('Location: ' . $url);
	exit;
} else {
	header('Location: login.php'); exit;
}
