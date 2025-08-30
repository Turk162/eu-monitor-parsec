<?php
// Page configuration
$page_title = 'Notification Debug Test - EU Project Manager';

// Include header
require_once '../includes/header.php';

// Auth and DB
$auth = new Auth();
$database = new Database();
$conn = $database->connect();

// User permissions check
$auth->requireLogin();
$user_role = getUserRole();
$user_id = getUserId();

// Only super_admin and coordinator can access this debug page
if (!in_array($user_role, ['super_admin', 'coordinator'])) {
    header('Location: projects.php?error=access_denied');
    exit;
}

require_once '../includes/classes/AlertSystem.php';
$alertSystem = new AlertSystem($conn);

$debug_output = [];

$debug_output[] = "<h2>Current User Info:</h2>";
$debug_output[] = "<p>User ID: " . htmlspecialchars($user_id) . "</p>";
$debug_output[] = "<p>User Role: " . htmlspecialchars($user_role) . "</p>";

// --- Section 1: Inspect Project Risks ---
$debug_output[] = "<h2>Project Risks Status:</h2>";
$risks_query = "
    SELECT pr.id as project_risk_id, pr.project_id, p.name as project_name, pr.current_score, pr.status as project_risk_status, 
           r.description as risk_name, r.description as risk_description, r.critical_threshold
    FROM project_risks pr
    JOIN risks r ON pr.risk_id = r.id
    JOIN projects p ON pr.project_id = p.id
";
$risks_params = [];

if ($user_role === 'coordinator') {
    $risks_query .= " WHERE p.coordinator_id = ?";
    $risks_params[] = $user_id;
}

$stmt_risks = $conn->prepare($risks_query . " ORDER BY p.name");
$stmt_risks->execute($risks_params);
$project_risks = $stmt_risks->fetchAll(PDO::FETCH_ASSOC);

if (empty($project_risks)) {
    $debug_output[] = "<p>No project risks found for your account.</p>";
} else {
    $debug_output[] = "<table class=\"table table-bordered table-striped\">";
    $debug_output[] = "<thead><tr><th>Project</th><th>Risk Name</th><th>Description</th><th>Current Score</th><th>Critical Threshold</th><th>Status</th><th>Is Critical?</th></tr></thead>";
    $debug_output[] = "<tbody>";
    foreach ($project_risks as $risk) {
        $is_critical = ($risk['current_score'] >= $risk['critical_threshold']) ? 'YES' : 'NO';
        $debug_output[] = "<tr>";
        $debug_output[] = "<td>" . htmlspecialchars($risk['project_name']) . "</td>";
        $debug_output[] = "<td>" . htmlspecialchars($risk['risk_name']) . "</td>";
        $debug_output[] = "<td>" . htmlspecialchars($risk['risk_description']) . "</td>";
        $debug_output[] = "<td>" . htmlspecialchars($risk['current_score']) . "</td>";
        $debug_output[] = "<td>" . htmlspecialchars($risk['critical_threshold']) . "</td>";
        $debug_output[] = "<td>" . htmlspecialchars($risk['project_risk_status']) . "</td>";
        $debug_output[] = "<td>" . $is_critical . "</td>";
        $debug_output[] = "</tr>";
    }
    $debug_output[] = "</tbody></table>";
}

// --- Section 2: Inspect Unread Persistent Risk Alerts ---
$debug_output[] = "<h2>Unread Persistent Risk Alerts:</h2>";
$unread_alerts_query = "
    SELECT a.id, a.project_id, p.name as project_name, a.title, a.message, a.created_at
    FROM alerts a
    LEFT JOIN projects p ON a.project_id = p.id
    WHERE a.user_id = ? AND a.is_read = 0 AND a.type = 'risk_persistent'
    ORDER BY a.created_at DESC
";
$stmt_unread_alerts = $conn->prepare($unread_alerts_query);
$stmt_unread_alerts->execute([$user_id]);
$unread_alerts = $stmt_unread_alerts->fetchAll(PDO::FETCH_ASSOC);

if (empty($unread_alerts)) {
    $debug_output[] = "<p>No unread persistent risk alerts found for your account.</p>";
} else {
    $debug_output[] = "<table class=\"table table-bordered table-striped\">";
    $debug_output[] = "<thead><tr><th>Alert ID</th><th>Project</th><th>Title</th><th>Message</th><th>Created At</th><th>Action</th></tr></thead>";
    $debug_output[] = "<tbody>";
    foreach ($unread_alerts as $alert) {
        $debug_output[] = "<tr>";
        $debug_output[] = "<td>" . htmlspecialchars($alert['id']) . "</td>";
        $debug_output[] = "<td>" . htmlspecialchars($alert['project_name'] ?? 'N/A') . "</td>";
        $debug_output[] = "<td>" . htmlspecialchars($alert['title']) . "</td>";
        $debug_output[] = "<td>" . htmlspecialchars($alert['message']) . "</td>";
        $debug_output[] = "<td>" . htmlspecialchars($alert['created_at']) . "</td>";
        $debug_output[] = "<td>
            <form method=\"POST\" action=\"\">
                <input type=\"hidden\" name=\"action\" value=\"mark_alert_read\">
                <input type=\"hidden\" name=\"alert_id\" value=\"" . htmlspecialchars($alert['id']) . "\">
                <button type=\"submit\" class=\"btn btn-sm btn-success\">Mark as Read</button>
            </form>
        </td>";
        $debug_output[] = "</tr>";
    }
    $debug_output[] = "</tbody></table>";
}

// Handle alert mark as read (for simplicity in same file)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'mark_alert_read') {
    $alert_id = intval($_POST['alert_id'] ?? 0);
    if ($alert_id > 0) {
        $mark_stmt = $conn->prepare("UPDATE alerts SET is_read = 1 WHERE id = ? AND user_id = ?");
        $mark_stmt->execute([$alert_id, $user_id]);
        // Redirect to avoid resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Output
echo "<div class=\"container mt-5\">";
echo "<h1>{$page_title}</h1>";
foreach ($debug_output as $line) {
    echo $line;
}
echo "</div>";

// Include footer
require_once '../includes/footer.php';
?>
