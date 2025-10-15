<?php
/**
 * Handles the deletion of a single activity report.
 */

// Start session to access user data. This must be done before any output.
session_start();

// Temporary debug line to check the user's role
die("Your current role is: " . ($_SESSION['user_role'] ?? 'not set'));

// Core includes
require_once '../config/database.php';
require_once '../includes/functions.php';

// --- Security and Authorization ---

// 1. Check if user is logged in
if (!isLoggedIn()) {
    Flash::set('error', 'You must be logged in to perform this action.');
    header('Location: ../login.php');
    exit;
}

// 2. Get Report ID from URL
$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$report_id) {
    Flash::set('error', 'Invalid Report ID.');
    header('Location: reports.php');
    exit;
}

// 3. Get user data from session
$current_user_id = $_SESSION['user_id'] ?? 0;
$current_user_role = $_SESSION['user_role'] ?? '';

// --- Database Connection ---
$database = new Database();
$conn = $database->connect();

// 4. Verify Permissions
try {
    // Fetch the report's author ID to check permissions
    $stmt = $conn->prepare("SELECT user_id FROM activity_reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch();

    if (!$report) {
        Flash::set('error', 'Report not found.');
        header('Location: reports.php');
        exit;
    }

    // Check if the user has permission to delete
    $can_delete = (in_array($current_user_role, ['super_admin', 'admin', 'coordinator']) || $report['user_id'] == $current_user_id);

    if (!$can_delete) {
        Flash::set('error', 'You do not have permission to delete this report.');
        header('Location: reports.php');
        exit;
    }

    // --- Deletion Process ---

    $conn->beginTransaction();

    // a. Delete associated files from server and database
    $files_stmt = $conn->prepare("SELECT file_path FROM uploaded_files WHERE report_id = ?");
    $files_stmt->execute([$report_id]);
    $files_to_delete = $files_stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($files_to_delete as $file_path) {
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    $delete_files_stmt = $conn->prepare("DELETE FROM uploaded_files WHERE report_id = ?");
    $delete_files_stmt->execute([$report_id]);

    // b. Delete the report itself
    $delete_report_stmt = $conn->prepare("DELETE FROM activity_reports WHERE id = ?");
    $delete_report_stmt->execute([$report_id]);

    // c. Commit the transaction
    $conn->commit();

    Flash::set('success', "Report #{$report_id} has been deleted successfully.");

} catch (PDOException $e) {
    // If something goes wrong, roll back the transaction
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Deletion Error: " . $e->getMessage());
    Flash::set('error', 'A database error occurred while trying to delete the report.');
}

// --- Redirect back to the reports page ---
header('Location: reports.php');
exit;

?>