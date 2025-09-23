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

try {
    // Work Package details con lead partner
    $stmt = $conn->prepare("
        SELECT wp.*, p.name as lead_partner_name, proj.name as project_name
        FROM work_packages wp
        LEFT JOIN partners p ON wp.lead_partner_id = p.id  
        LEFT JOIN projects proj ON wp.project_id = proj.id
        WHERE wp.id = ?
    ");
    $stmt->execute([$wp_id]);
    $wp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$wp) {
        echo json_encode(['error' => 'Work Package not found']);
        exit;
    }

    // Attività associate
    $activities_stmt = $conn->prepare("
        SELECT a.*, p.name as responsible_partner_name
        FROM activities a
        LEFT JOIN partners p ON a.responsible_partner_id = p.id
        WHERE a.work_package_id = ?
        ORDER BY a.activity_number ASC, a.name ASC
    ");
    $activities_stmt->execute([$wp_id]);
    $activities = $activities_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Budget specifici del Work Package per ogni partner
    $wp_budgets_stmt = $conn->prepare("
        SELECT p.name as partner_name, p.country, pp.role
        FROM work_package_partner_budgets wpb
        JOIN partners p ON wpb.partner_id = p.id
        JOIN project_partners pp ON wpb.partner_id = pp.partner_id AND wpb.project_id = pp.project_id
        WHERE wpb.work_package_id = ?
        ORDER BY pp.role DESC, p.name ASC
    ");
    $wp_budgets_stmt->execute([$wp_id]);
    $wp_partner_budgets = $wp_budgets_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate WP Total Budget
    $total_budget_stmt = $conn->prepare("
        SELECT 
            SUM(
                CASE 
                    WHEN wpb.wp_type = 'project_management' THEN COALESCE(wpb.project_management_cost, 0)
                    ELSE COALESCE(wpb.working_days_total, 0)
                END
            ) + 
            SUM(COALESCE(wpb.other_costs, 0)) +
            COALESCE((SELECT SUM(COALESCE(bts.total, 0)) 
             FROM budget_travel_subsistence bts
             JOIN work_package_partner_budgets wpb_inner ON bts.wp_partner_budget_id = wpb_inner.id
             WHERE wpb_inner.work_package_id = ?), 0)
        AS total_budget
        FROM work_package_partner_budgets wpb
        WHERE wpb.work_package_id = ?
    ");
    $total_budget_stmt->execute([$wp_id, $wp_id]);
    $wp_total_budget = $total_budget_stmt->fetchColumn();

    // Risposta con WP, attività e budget specifici del WP
    echo json_encode([
        'work_package' => $wp,
        'activities' => $activities,
        'partner_budgets' => $wp_partner_budgets,
        'wp_total_budget' => $wp_total_budget ?? 0
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>