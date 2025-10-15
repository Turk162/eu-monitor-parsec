<?php
// api/update_milestone_status.php

require_once '../includes/header.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$milestone_id = isset($_POST['milestone_id']) ? (int)$_POST['milestone_id'] : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';
$project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;

// Validate input
$valid_statuses = ['pending', 'in_progress', 'completed', 'at_risk'];
if (!$milestone_id || !$project_id || !in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input provided.']);
    exit;
}

// Authorization: User must be a partner in this project or super_admin
$user_role = getUserRole();
if ($user_role !== 'super_admin') {
    $user_partner_id = $_SESSION['partner_id'] ?? 0;
    if (!$user_partner_id) {
        echo json_encode(['success' => false, 'message' => 'No partner organization associated with your account.']);
        exit;
    }
    
    $access_stmt = $conn->prepare("SELECT COUNT(*) FROM project_partners WHERE project_id = ? AND partner_id = ?");
    $access_stmt->execute([$project_id, $user_partner_id]);
    
    if ($access_stmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to modify this milestone.']);
        exit;
    }
}

// Update milestone status
try {
    if ($status === 'completed') {
        $stmt = $conn->prepare("UPDATE milestones SET status = ?, completed_date = NOW() WHERE id = ?");
        $params = [$status, $milestone_id];
    } else {
        $stmt = $conn->prepare("UPDATE milestones SET status = ?, completed_date = NULL WHERE id = ?");
        $params = [$status, $milestone_id];
    }
    
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Milestone status updated successfully.']);
    } else {
        // This can happen if the status was already the one selected
        echo json_encode(['success' => true, 'message' => 'Milestone status was already set to the selected value.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>