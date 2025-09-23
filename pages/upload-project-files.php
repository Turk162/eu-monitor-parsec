<?php
// ===================================================================
//  UPLOAD PROJECT FILES (API ENDPOINT)
// ===================================================================
//  This script should only output JSON.

// Use output buffering to prevent header.php from sending HTML
ob_start();
// header.php now handles all inclusions, session, auth, and DB connection ($conn)
require_once '../includes/header.php'; 
ob_end_clean(); // Discard any HTML output

// Set the JSON header
header('Content-Type: application/json');

// ===================================================================
//  MAIN LOGIC
// ===================================================================
// Note: $conn, $auth, $user_id, $user_role are all available from header.php

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_files') {
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;

    if (!$project_id) {
        $response['message'] = 'Project ID is missing.';
        echo json_encode($response);
        exit;
    }

    // Authorization is already checked by header.php's requireLogin(), 
    // but we can add an extra layer if needed. The user object is in the $auth class.

    if (isset($_FILES['files'])) {
        $upload_dir = '../uploads/';
        
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'image/jpeg', 'image/png'];
        $max_size = 10 * 1024 * 1024; // 10 MB

        $success_count = 0;
        $error_count = 0;
        $error_messages = [];

        foreach ($_FILES['files']['name'] as $key => $name) {
            $file_tmp = $_FILES['files']['tmp_name'][$key];
            $file_size = $_FILES['files']['size'][$key];
            $file_type = $_FILES['files']['type'][$key];
            $file_error = $_FILES['files']['error'][$key];

            if ($file_error === UPLOAD_ERR_OK) {
                if (!in_array($file_type, $allowed_types)) {
                    $error_count++;
                    $error_messages[] = "File '$name': Invalid file type.";
                    continue;
                }
                if ($file_size > $max_size) {
                    $error_count++;
                    $error_messages[] = "File '$name': Exceeds maximum size of 10MB.";
                    continue;
                }

                $file_extension = pathinfo($name, PATHINFO_EXTENSION);
                $unique_filename = uniqid('proj_' . $project_id . '_', true) . '.' . $file_extension;
                $destination = $upload_dir . $unique_filename;

                if (move_uploaded_file($file_tmp, $destination)) {
                    $stmt = $conn->prepare("
                        INSERT INTO uploaded_files (project_id, report_id, uploaded_by, filename, file_path, original_filename, file_size, file_type)
                        VALUES (?, NULL, ?, ?, ?, ?, ?, ?)
                    ");
                    if ($stmt->execute([$project_id, $user_id, $unique_filename, 'uploads/' . $unique_filename, $name, $file_size, $file_type])) {
                        $success_count++;
                    } else {
                        $error_count++;
                        $error_messages[] = "File '$name': Failed to save to database. " . $stmt->errorInfo()[2];
                        unlink($destination); 
                    }
                } else {
                    $error_count++;
                    $error_messages[] = "File '$name': Failed to move to destination. Check permissions.";
                }
            }
            else {
                $error_count++;
                $error_messages[] = "File '$name': Upload error code: $file_error.";
            }
        }

        if ($success_count > 0 && $error_count == 0) {
            $response['success'] = true;
            $response['message'] = "$success_count file(s) uploaded successfully.";
        } else {
            $response['message'] = "Upload completed with $error_count errors. " . implode(' ', $error_messages);
        }

    } else {
        $response['message'] = 'No files were sent.';
    }
} else {
    $response['message'] = 'Invalid request.';
}

echo json_encode($response);
exit;