<?php
// CREA questo nuovo file: api/get_project_work_packages.php

header('Content-Type: application/json');
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$conn = $database->connect();

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

if (!$project_id) {
    echo json_encode(['error' => 'Project ID is required']);
    exit;
}

// Verifica accesso al progetto
$user_role = getUserRole();
$user_partner_id = $_SESSION['partner_id'] ?? 0;

if ($user_role !== 'super_admin') {
    // Verifica che l'utente faccia parte di questo progetto
    $access_stmt = $conn->prepare("
        SELECT 1 FROM project_partners 
        WHERE project_id = ? AND partner_id = ?
    ");
    $access_stmt->execute([$project_id, $user_partner_id]);
    
    if (!$access_stmt->fetch()) {
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
}

// Recupera tutti i work packages del progetto
$stmt = $conn->prepare("
    SELECT 
        id, 
        wp_number, 
        name, 
        description, 
        start_date, 
        end_date, 
        status, 
        progress,
        lead_partner_id
    FROM work_packages 
    WHERE project_id = ? 
    ORDER BY wp_number ASC
");

$stmt->execute([$project_id]);
$work_packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$work_packages) {
    echo json_encode([]);
    exit;
}

echo json_encode($work_packages);
?>