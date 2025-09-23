<?php
// ===================================================================
// LOGOUT - logout.php
// ===================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

session_destroy();

// Redirect al login con messaggio
header('Location: login.php?logged_out=1');
exit;
?>

