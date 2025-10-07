<?php
// api/delete_file.php

// Use a simplified header that starts session and connects to DB
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = new Database();
$conn = $db->connect();
$auth = new Auth($conn);

// 1. Check Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.0 405 Method Not Allowed');
    die('This endpoint only accepts POST requests.');
}

// 2. Security Checks (CSRF & Role)
$auth->requireLogin();
$auth->requireRole(['super_admin', 'coordinator']); // Only admins and coordinators can delete

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    setErrorMessage('Invalid CSRF token.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../pages/projects.php'));
    exit;
}

// 3. Get Input
$file_id = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
$project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;

$redirect_url = '../pages/project-detail.php?id=' . $project_id . '#documents';

if (!$file_id || !$project_id) {
    setErrorMessage('Invalid file or project ID.');
    header('Location: ../pages/projects.php');
    exit;
}

try {
    // 4. Fetch file details from DB
    $stmt = $conn->prepare("SELECT file_path, project_id FROM uploaded_files WHERE id = ?");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch();

    if (!$file) {
        setErrorMessage('File not found.');
        header('Location: ' . $redirect_url);
        exit;
    }

    // Authorization check: ensure the file belongs to the project we are redirecting to
    if ($file['project_id'] != $project_id) {
        setErrorMessage('File does not belong to this project.');
        header('Location: ' . $redirect_url);
        exit;
    }

    $file_path_on_disk = __DIR__ . '/../' . $file['file_path'];
    
    // 5. Delete from DB and Filesystem
    $conn->beginTransaction();

    $delete_stmt = $conn->prepare("DELETE FROM uploaded_files WHERE id = ?");
    $delete_stmt->execute([$file_id]);

    if (file_exists($file_path_on_disk)) {
        unlink($file_path_on_disk);
    }

    $conn->commit();

    setSuccessMessage('File deleted successfully.');

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("File deletion error: " . $e->getMessage());
    setErrorMessage('An error occurred while deleting the file.');
}

// 6. Redirect back
header('Location: ' . $redirect_url);
exit;
