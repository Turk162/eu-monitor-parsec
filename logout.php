<?php
// ===================================================================
// LOGOUT - logout.php
// ===================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/auth.php';

$auth = new Auth();
$auth->logout();

// Redirect al login con messaggio
header('Location: login.php?logged_out=1');
exit;
?>

