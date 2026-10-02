<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();

session_start();
$_SESSION['flash_info'] = 'You have been successfully logged out.';
header("Location: " . BASE_URL . "login.php");
exit;
