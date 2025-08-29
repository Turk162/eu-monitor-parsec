<?php
header('Content-Type: application/json');
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$conn = $database->connect();

$activity_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$activity_id) {
    echo json_encode(['error' => 'Activity ID is required']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM activities WHERE id = ?");
$stmt->execute([$activity_id]);
$activity = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activity) {
    echo json_encode(['error' => 'Activity not found']);
    exit;
}

echo json_encode($activity);
?>