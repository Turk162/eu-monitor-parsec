<?php
// ===================================================================
// INDEX.PHP - Entry Point
// ===================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se loggato, vai alla dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header('Location: pages/dashboard.php');
    exit;
}

// Altrimenti vai al login
header('Location: login.php');
exit;
?>
