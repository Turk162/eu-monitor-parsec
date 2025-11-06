<?php
// ===================================================================
//  DELETE FILE API ENDPOINT
// ===================================================================
// Use output buffering to prevent header.php from sending HTML
ob_start();
require_once '../includes/header.php';
ob_end_clean(); // Discard any HTML output

// Set the JSON header
header('Content-Type: application/json');

// ===================================================================
//  MAIN LOGIC
// ===================================================================
$response = ['success' => false, 'message' => 'Invalid request.'];

// Require login
$auth->requireLogin();
$user_id = getUserId();
$user_role = getUserRole();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_file') {
    $file_id = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
    
    if (!$file_id) {
        $response['message'] = 'File ID is missing.';
        echo json_encode($response);
        exit;
    }
    
    try {
        // Fetch file info from DB with user info
        $stmt = $conn->prepare("
            SELECT 
                uf.*,
                u.partner_id as uploader_partner_id
            FROM uploaded_files uf
            LEFT JOIN users u ON uf.uploaded_by = u.id
            WHERE uf.id = ?
        ");
        $stmt->execute([$file_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            $response['message'] = 'File not found in the database.';
            echo json_encode($response);
            exit;
        }
        
        // --- ENHANCED AUTHORIZATION CHECK ---
        $can_delete = false;
        
        // Super admin, admin, and coordinator can delete any file
        if (in_array($user_role, ['super_admin', 'admin', 'coordinator'])) {
            $can_delete = true;
        }
        // Partner can delete only their own files
        elseif ($user_role === 'partner') {
            if ($file['uploaded_by'] == $user_id) {
                $can_delete = true;
            }
        }
        
        if (!$can_delete) {
            http_response_code(403);
            $response['message'] = 'You do not have permission to delete this file.';
            echo json_encode($response);
            exit;
        }
        
        // Construct file path
        $file_path_on_server = __DIR__ . '/../' . $file['file_path'];
        
        // Start transaction
        $conn->beginTransaction();
        
        // 1. Delete the record from the database
        $delete_stmt = $conn->prepare("DELETE FROM uploaded_files WHERE id = ?");
        $delete_stmt->execute([$file_id]);
        
        if ($delete_stmt->rowCount() > 0) {
            // 2. Delete the physical file from the server
            if (file_exists($file_path_on_server)) {
                if (unlink($file_path_on_server)) {
                    // Commit transaction
                    $conn->commit();
                    $response['success'] = true;
                    $response['message'] = 'File deleted successfully.';
                } else {
                    // If file deletion fails, roll back the DB change
                    $conn->rollBack();
                    $response['message'] = 'Failed to delete the physical file. Check server permissions.';
                }
            } else {
                // If file doesn't exist on server, we still consider the DB deletion a success
                $conn->commit();
                $response['success'] = true;
                $response['message'] = 'File record deleted from database (physical file was already missing).';
            }
        } else {
            $conn->rollBack();
            $response['message'] = 'Failed to delete the file record from the database.';
        }
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Delete File API Error: " . $e->getMessage());
        $response['message'] = 'A database error occurred: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid action or request method.';
}

echo json_encode($response);
exit;