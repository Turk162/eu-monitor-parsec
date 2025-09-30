<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mock session data
$_SESSION['user_id'] = 1;
$_SESSION['partner_id'] = 1;

// Mock GET data
$_GET['project_id'] = 28;
$_GET['partner_id'] = 1;

// SETUP: Manually include necessary files
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h1>Debugging admin_reports.php</h1>";

// Simulate a POST request with no files to trigger the warning
echo "<h2>Simulating 'Upload Documents' with NO files selected</h2>";

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];
$_FILES = [];

$_POST['project_id'] = 28;
$_POST['partner_id'] = 1;
$_POST['action'] = 'upload_wp_files_14'; // Different WP

echo "<pre>-- MOCKED \$_POST ---";
print_r($_POST);
echo "</pre>";

echo "<pre>-- MOCKED \$_FILES ---";
print_r($_FILES);
echo "</pre>";

// Include the script to be tested
try {
    include 'pages/admin_reports.php';
} catch (Error $e) {
    echo "<p style='color:red; border: 2px solid red; padding: 10px;'><b>FATAL ERROR CAUGHT:</b><br>" . $e->getMessage() . "<br><b>File:</b> " . $e->getFile() . "<br><b>Line:</b> " . $e->getLine() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

if (isset($_SESSION['success'])) {
    echo "<p style='color:green;'><b>Session Success:</b> " . $_SESSION['success'] . "</p>";
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo "<p style='color:red;'><b>Session Error:</b> " . $_SESSION['error'] . "</p>";
    unset($_SESSION['error']);
}

echo "<p><b>Debug script finished.</b></p>";
?>