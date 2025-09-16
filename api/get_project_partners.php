<?php
// CREA questo file: api/get_project_partners.php

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

// Verifica che l'utente abbia accesso al progetto
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

// Recupera tutti i partner del progetto
$stmt = $conn->prepare("
    SELECT 
        pp.partner_id, 
        pp.role, 
        pp.budget_allocated, 
        p.name as organization,
        p.name,
        p.country, 
        p.organization_type
    FROM project_partners pp
    INNER JOIN partners p ON pp.partner_id = p.id
    WHERE pp.project_id = ?
    ORDER BY 
        CASE WHEN pp.role = 'coordinator' THEN 0 ELSE 1 END,
        p.name ASC
");

$stmt->execute([$project_id]);
$partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$partners) {
    echo json_encode(['error' => 'No partners found for this project']);
    exit;
}

echo json_encode($partners);
?>