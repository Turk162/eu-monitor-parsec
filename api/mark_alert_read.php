<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alert_id'])) {
    $alert_id = (int)$_POST['alert_id'];
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if ($user_id > 0) {
        $database = new Database();
        $conn = $database->connect();
        
        $stmt = $conn->prepare("UPDATE alerts SET is_read = 1 WHERE id = ? AND user_id = ?");
        $result = $stmt->execute([$alert_id, $user_id]);
        
        echo json_encode(['success' => $result]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>