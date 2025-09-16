<?php
/**
 * API Endpoint: Mark Alert as Read
 * Handles AJAX requests to mark dashboard alerts as read
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include only necessary files (NOT the full header)
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

// Validate required parameters
if (!isset($_POST['action']) || $_POST['action'] !== 'mark_alert_read' || !isset($_POST['alert_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

try {
    // Get database connection
    $database = new Database();
    $conn = $database->connect();
    
    // Get parameters
    $alert_id = (int)$_POST['alert_id'];
    $user_id = (int)$_SESSION['user_id'];
    
    // Validate alert belongs to current user and update it
    $stmt = $conn->prepare("UPDATE alerts SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$alert_id, $user_id]);
    
    // Check if any row was affected
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Alert marked as read successfully'
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Alert not found or access denied'
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    error_log("Mark alert read error: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
    error_log("Mark alert read error: " . $e->getMessage());
}
?>