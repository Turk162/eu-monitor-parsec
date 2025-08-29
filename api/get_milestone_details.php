<?php
header('Content-Type: application/json');
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$conn = $database->connect();

$milestone_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$milestone_id) {
    echo json_encode(['error' => 'Milestone ID is required']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM milestones WHERE id = ?");
$stmt->execute([$milestone_id]);
$milestone = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$milestone) {
    echo json_encode(['error' => 'Milestone not found']);
    exit;
}

echo json_encode($milestone);
?>