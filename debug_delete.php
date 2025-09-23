<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set headers for JSON response (if used for AJAX testing)
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// --- Database Configuration (replace with actual credentials for testing) ---
// IMPORTANT: REMOVE THIS FILE AND THESE CREDENTIALS AFTER DEBUGGING!
define('DB_HOST', 'localhost');
define('DB_NAME', 'eu_projectmanager');
define('DB_USER', 'root');
define('DB_PASS', 'your_db_password'); // !!! REPLACE WITH ACTUAL PASSWORD !!!
// --- End Database Configuration ---

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $response['message'] = "Database connection failed: " . $e->getMessage();
    echo json_encode($response);
    exit;
}

if (isset($_REQUEST['file_id'])) {
    $file_id = (int)$_REQUEST['file_id'];

    // 1. Fetch file details
    $stmt = $pdo->prepare("SELECT file_path, original_filename FROM uploaded_files WHERE id = ?");
    $stmt->execute([$file_id]);
    $file_data = $stmt->fetch();

    if (!$file_data) {
        $response['message'] = "File with ID {$file_id} not found in database.";
    } else {
        $file_path_db = $file_data['file_path'];
        $original_filename = $file_data['original_filename'];
        $full_file_path = '../' . $file_path_db; // Adjust path relative to debug_delete.php

        $response['file_info'] = [
            'id' => $file_id,
            'original_filename' => $original_filename,
            'db_path' => $file_path_db,
            'full_server_path' => realpath($full_file_path) // Get absolute path for clarity
        ];

        // 2. Check if file exists on disk and attempt to delete
        if (file_exists($full_file_path)) {
            $response['file_exists_on_disk'] = true;
            if (is_writable($full_file_path)) {
                $response['file_is_writable'] = true;
                if (unlink($full_file_path)) {
                    $response['physical_delete_status'] = "SUCCESS: Physical file deleted.";
                } else {
                    $response['physical_delete_status'] = "FAILURE: Could not delete physical file. PHP error: " . (error_get_last()['message'] ?? 'None');
                    $response['php_error'] = error_get_last();
                }
            } else {
                $response['file_is_writable'] = false;
                $response['physical_delete_status'] = "FAILURE: Physical file is not writable. Check permissions.";
            }
        } else {
            $response['file_exists_on_disk'] = false;
            $response['physical_delete_status'] = "WARNING: Physical file does not exist on disk, but will attempt to delete DB record.";
        }

        // 3. Attempt to delete record from database
        $stmt = $pdo->prepare("DELETE FROM uploaded_files WHERE id = ?");
        if ($stmt->execute([$file_id])) {
            $response['db_delete_status'] = "SUCCESS: Database record deleted.";
            $response['success'] = true; // Overall success if DB record is deleted
        } else {
            $response['db_delete_status'] = "FAILURE: Could not delete database record. PDO error: " . ($stmt->errorInfo()[2] ?? 'None');
            $response['success'] = false;
        }
    }
} else {
    $response['message'] = "Please provide a 'file_id' parameter (e.g., debug_delete.php?file_id=1).";
}

echo json_encode($response);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Debug Delete File</title>
    <style>
        body { font-family: monospace; white-space: pre; }
    </style>
</head>
<body>
    <h1>Debug Delete File</h1>
    <p>This script attempts to delete a file and its database record.</p>
    <p><strong>WARNING: This script performs actual deletions. Use with caution!</strong></p>
    <form method="GET">
        File ID to delete: <input type="number" name="file_id" required>
        <input type="submit" value="Delete File">
    </form>
    <hr>
    <h2>JSON Response:</h2>
    <pre><?php echo json_encode($response, JSON_PRETTY_PRINT); ?></pre>
</body>
</html>