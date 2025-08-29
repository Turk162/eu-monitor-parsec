<?php
header('Content-Type: application/json');
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$conn = $database->connect();

$wp_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$wp_id) {
    echo json_encode(['error' => 'Work Package ID is required']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM work_packages WHERE id = ?");
$stmt->execute([$wp_id]);
$wp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wp) {
    echo json_encode(['error' => 'Work Package not found']);
    exit;
}

echo json_encode($wp);
?>