<?php
// ===================================================================
// DASHBOARD - Page-specific configuration
// ===================================================================
// DEBUG - aggiungi all'inizio del file dashboard.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ===================================================================
//  PAGE CONFIGURATION
// ===================================================================

// Set the title of the page
$page_title = 'Dashboard - EU Project Manager';

// Specify the path to the page-specific CSS file
$page_css_path = '../assets/css/pages/dashboard.css';

// Specify the path to the page-specific JS file
$page_js_path = '../assets/js/pages/dashboard.js';

// ===================================================================
//  INCLUDE HEADER
// ===================================================================
// The header includes session management, authentication checks,
// database connection, and defines common user variables.
// It will also use $page_title and $page_css_path to build the <head>.
// ===================================================================

// Include header (handles session, auth, database, user variables)
require_once '../includes/header.php';

// Database connection (user_id and user_role are already available from header)
$database = new Database();
$conn = $database->connect();

// Get dashboard-specific data
$projects = getMyProjects($conn, $user_id, $user_role);
$stats = getDashboardStats($conn, $user_id, $user_role);

// Upcoming deadlines
if ($user_role === 'super_admin') {
    $stmt = $conn->prepare("SELECT a.name as activity_name, a.end_date, p.name as project_name, p.id as project_id
                           FROM activities a 
                           JOIN work_packages wp ON a.work_package_id = wp.id
                           JOIN projects p ON wp.project_id = p.id
                           WHERE a.end_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 14 DAY)
                           AND a.status != 'completed'
                           ORDER BY a.end_date ASC LIMIT 5");
    $stmt->execute();
} else {
    $user_partner_id = $_SESSION['partner_id'] ?? 0;
    $stmt = $conn->prepare("SELECT a.name as activity_name, a.end_date, p.name as project_name, p.id as project_id
                           FROM activities a 
                           JOIN work_packages wp ON a.work_package_id = wp.id
                           JOIN projects p ON wp.project_id = p.id
                           JOIN project_partners pp ON p.id = pp.project_id
                           WHERE pp.partner_id = ? 
                           AND a.end_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 14 DAY)
                           AND a.status != 'completed'
                           ORDER BY a.end_date ASC LIMIT 5");
    $stmt->execute([$user_partner_id]);
}
$upcoming_deadlines = $stmt->fetchAll();

// Recent reports
if ($user_role === 'super_admin') {
    $stmt = $conn->prepare("SELECT ar.*, a.name as activity_name, p.name as project_name, u.full_name as reporter_name
                           FROM activity_reports ar
                           JOIN activities a ON ar.activity_id = a.id
                           JOIN work_packages wp ON a.work_package_id = wp.id
                           JOIN projects p ON wp.project_id = p.id
                           JOIN users u ON ar.user_id = u.id
                           ORDER BY ar.created_at DESC LIMIT 5");
    $stmt->execute();
} else {
    $user_partner_id = $_SESSION['partner_id'] ?? 0;
    $stmt = $conn->prepare("SELECT ar.*, a.name as activity_name, p.name as project_name, u.full_name as reporter_name
                           FROM activity_reports ar
                           JOIN activities a ON ar.activity_id = a.id
                           JOIN work_packages wp ON a.work_package_id = wp.id
                           JOIN projects p ON wp.project_id = p.id
                           JOIN users u ON ar.user_id = u.id
                           WHERE ar.partner_id = ?
                           ORDER BY ar.created_at DESC LIMIT 5");
    $stmt->execute([$user_partner_id]);
}
$recent_reports = $stmt->fetchAll();
?>

   <!-- SIDEBAR & NAV -->
<body class="">
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <?php include '../includes/navbar.php'; ?>

            <!-- CONTENT -->
            <div class="content">
                <!-- ALERT MESSAGE -->
                <?php displayAlert(); ?>
                
                <script>
function markAsRead(alertId, alertElement) {
    fetch('../api/mark_alert_read.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `alert_id=${alertId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertElement.remove();
        } else {
            console.error('Error:', data.error);
        }
    })
    .catch(error => console.error('Fetch error:', error));
}
</script>
                <?php

// --- MODIFIED: Persistent Risk Alert Generation ---
if (isset($user_id) && in_array($user_role, ['super_admin', 'coordinator'])) {
    require_once '../includes/classes/AlertSystem.php';
    $alertSystem = new AlertSystem($conn);

    // Get all critical risks for projects the user has access to
    $critical_risks_query = "
        SELECT pr.id as project_risk_id, pr.project_id, pr.current_score, pr.status, 
               r.critical_threshold, r.description as risk_description
        FROM project_risks pr
        JOIN risks r ON pr.risk_id = r.id
        JOIN projects p ON pr.project_id = p.id
    ";
    $critical_risks_params = [];

    if ($user_role === 'coordinator') {
        // Only show risks for projects where user is the coordinator
        $critical_risks_query .= " WHERE p.coordinator_id = ?";
        $critical_risks_params[] = $user_id;
    }
    // Super admin sees all projects, so no additional WHERE clause
    
    $stmt_critical_risks = $conn->prepare($critical_risks_query . " HAVING pr.current_score >= r.critical_threshold");
    $stmt_critical_risks->execute($critical_risks_params);
    $critical_risks = $stmt_critical_risks->fetchAll(PDO::FETCH_ASSOC);

    // Generate alerts only for currently critical risks
    foreach ($critical_risks as $risk) {
        // Check if ANY alert exists for this risk in the last 24 hours
        $existing_alert_stmt = $conn->prepare("
            SELECT id FROM alerts 
            WHERE user_id = ? 
            AND project_id = ? 
            AND type = 'risk_persistent' 
            AND message LIKE ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $search_message = "%Risk '" . htmlspecialchars($risk['risk_description']) . "' (Score: {$risk['current_score']}%"; 
        $existing_alert_stmt->execute([$user_id, $risk['project_id'], $search_message]);
        
        if (!$existing_alert_stmt->fetch()) {
            // Create alert only if none exists in the last 24 hours
            $alertTitle = "Critical Risk Alert: " . htmlspecialchars($alertSystem->getProjectName($risk['project_id']));
            $alertMessage = "Risk '" . htmlspecialchars($risk['risk_description']) . "' (Score: {$risk['current_score']}, Status: {$risk['status']}) is currently critical. Please review.";
            
            $alertSystem->createDashboardAlert(
                $user_id, 
                $risk['project_id'], 
                null,
                'risk_persistent', 
                $alertTitle, 
                $alertMessage
            );
        }
    }
}
// --- END MODIFIED ---

                // Fetch unread dashboard alerts for the current user (including newly generated persistent ones)
                $dashboard_alerts = [];
                if (isset($user_id)) {
                    $stmt = $conn->prepare("SELECT a.*, p.name as project_name 
                                   FROM alerts a 
                                   LEFT JOIN projects p ON a.project_id = p.id
                                   WHERE a.user_id = ? AND a.is_read = 0 
                                   ORDER BY a.created_at DESC");
                    $stmt->execute([$user_id]);
                    $dashboard_alerts = $stmt->fetchAll();
                }
                ?>


     <!-- WELCOME HEADER & DASHBOARD ALERTS -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <!-- WELCOME SECTION - Più largo (8 colonne) -->
                    <div class="col-md-8">
                        <h4 class="card-title">
                            <i class="nc-icon nc-satisfied text-success"></i>
                            Welcome, <?= $_SESSION['full_name'] ?>!
                        </h4>
                        <p class="card-text">
                            <?php if (!empty($projects)): ?>
                                <strong>Your Projects:</strong>
                                <ul class="list-unstyled mb-0">
                                    <?php foreach ($projects as $project): ?>
                                        <li><i class="nc-icon nc-briefcase-24 text-info"></i> <a href="project-detail.php?id=<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <strong>No projects currently linked to your account.</strong>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <!-- NOTIFICATIONS SECTION - Più piccolo (4 colonne) -->
                    <div class="col-md-4">
                        <?php if (!empty($dashboard_alerts)): ?>
                        <div class="notifications-section">
                            <h6 class="mb-3">
                                <i class="nc-icon nc-bell-55 text-warning"></i>
                                Your Notifications
                            </h6>
                            <div class="notifications-container" style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($dashboard_alerts as $alert): ?>
                                <div class="alert alert-<?php 
                                    switch ($alert['type']) {
                                        case 'risk': echo 'danger'; break;
                                        case 'deadline': echo 'warning'; break;
                                        case 'milestone': echo 'info'; break;
                                        default: echo 'primary'; break;
                                    }
                                ?> alert-dismissible fade show mb-2" role="alert" style="padding: 8px 12px; font-size: 0.9em;">
                                    <strong><?= htmlspecialchars($alert['title']) ?></strong>
                                    <p class="mb-2"><small><?= htmlspecialchars($alert['message']) ?></small></p>
                                    <?php if ($alert['project_id']): ?>
                                        <small class="text-muted">Project: <?= htmlspecialchars($alert['project_name']) ?></small>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline-block; margin-left: 10px;">
                                        <input type="hidden" name="action" value="mark_alert_read">
                                        <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>">
<button onclick="markAsRead(<?= $alert['id'] ?>, this.closest('.alert'))" class="btn btn-link btn-sm p-0">
    Mark as Read
</button>                                   </form>
                                   
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="notifications-section">
                            <h6 class="mb-3">
                                <i class="nc-icon nc-bell-55 text-muted"></i>
                                Your Notifications
                            </h6>
                            <div class="text-center text-muted">
                                <i class="nc-icon nc-check-2" style="font-size: 1.5rem;"></i>
                                <p class="mt-2 mb-0">No notifications</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

                <!-- STATISTICS -->
                <div class="row">
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats stat-card">
                            <div class="card-body ">
                                <div class="row">
                                    <div class="col-5 col-md-4">
                                        <div class="icon-big text-center icon-warning">
                                            <i class="nc-icon nc-briefcase-24 text-warning"></i>
                                        </div>
                                    </div>
                                    <div class="col-7 col-md-8">
                                        <div class="numbers">
                                            <p class="card-category">Active Projects</p>
                                            <p class="card-title"><?= $stats['active_projects'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer ">
                                <hr>
                                <div class="stats">
                                    <i class="fa fa-refresh"></i>
                                    Updated now
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats stat-card">
                            <div class="card-body ">
                                <div class="row">
                                    <div class="col-5 col-md-4">
                                        <div class="icon-big text-center icon-warning">
                                            <i class="nc-icon nc-paper text-success"></i>
                                        </div>
                                    </div>
                                    <div class="col-7 col-md-8">
                                        <div class="numbers">
                                            <p class="card-category">Completed Activities</p>
                                            <p class="card-title"><?= $stats['completed_activities'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer ">
                                <hr>
                                <div class="stats">
                                    <i class="fa fa-calendar-o"></i>
                                    This month
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats stat-card">
                            <div class="card-body ">
                                <div class="row">
                                    <div class="col-5 col-md-4">
                                        <div class="icon-big text-center icon-warning">
                                            <i class="nc-icon nc-bell-55 text-danger"></i>
                                        </div>
                                    </div>
                                    <div class="col-7 col-md-8">
                                        <div class="numbers">
                                            <p class="card-category">Upcoming Deadlines</p>
                                            <p class="card-title"><?= $stats['upcoming_deadlines'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer ">
                                <hr>
                                <div class="stats">
                                    <i class="fa fa-clock-o"></i>
                                    Next 7 days
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats stat-card">
                            <div class="card-body ">
                                <div class="row">
                                    <div class="col-5 col-md-4">
                                        <div class="icon-big text-center icon-warning">
                                            <i class="nc-icon nc-chart-bar-32 text-info"></i>
                                        </div>
                                    </div>
                                    <div class="col-7 col-md-8">
                                        <div class="numbers">
                                            <p class="card-category">Recent Reports</p>
                                            <p class="card-title"><?= count($recent_reports) ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer ">
                                <hr>
                                <div class="stats">
                                    <i class="fa fa-check"></i>
                                    Latest reports
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PROJECTS AND DEADLINES -->
                <div class="row">
                    <!-- MY PROJECTS -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="nc-icon nc-briefcase-24"></i>
                                    My European Projects
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($projects)): ?>
                                <div class="text-center py-4">
                                    <i class="nc-icon nc-simple-add" style="font-size: 48px; color: #ccc;"></i>
                                    <p class="text-muted">No projects assigned at the moment.</p>
                                </div>
                                <?php else: ?>
                                <?php foreach($projects as $project): ?>
                                <div class="project-card">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h6 class="mb-1">
                                                <i class="nc-icon nc-badge text-info"></i>
                                                <?= htmlspecialchars($project['name']) ?>
                                            </h6>
                                            <p class="text-muted mb-1" style="font-size: 13px;">
                                                <?= htmlspecialchars(substr($project['description'], 0, 100)) ?>...
                                            </p>
                                            <small class="text-muted">
                                                <i class="nc-icon nc-world-2"></i>
                                                <?= $project['partner_count'] ?> partners | 
                                                <?= $project['program_type'] ?>
                                            </small>
                                        </div>

                                        <div class="col-md-3 text-right">
                                            <div class="mb-2">
                                                <?= getStatusBadge($project['status']) ?>
                                            </div>
                                            <a href="project-detail.php?id=<?= $project['id'] ?>" 
                                               class="btn btn-primary btn-sm">
                                                <i class="nc-icon nc-zoom-split"></i>
                                                Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- UPCOMING DEADLINES -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="nc-icon nc-bell-55"></i>
                                    Upcoming Deadlines
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcoming_deadlines)): ?>
                                <div class="text-center py-3">
                                    <i class="nc-icon nc-check-2 text-success" style="font-size: 32px;"></i>
                                    <p class="text-muted">No upcoming deadlines!</p>
                                </div>
                                <?php else: ?>
                                <?php foreach($upcoming_deadlines as $deadline): ?>
                                <div class="deadline-item">
                                    <h6 class="mb-1"><?= htmlspecialchars($deadline['activity_name']) ?></h6>
                                    <p class="mb-1 text-muted" style="font-size: 13px;">
                                        <?= htmlspecialchars($deadline['project_name']) ?>
                                    </p>
                                    <small class="text-danger">
                                        <i class="nc-icon nc-time-alarm"></i>
                                        <?= formatDate($deadline['end_date']) ?>
                                    </small>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <div class="text-center mt-3">
                                    <a href="calendar.php" class="btn btn-outline-primary btn-sm">
                                        <i class="nc-icon nc-calendar-60"></i>
                                        View Calendar
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- RECENT REPORTS (if any) -->
                        <?php if (!empty($recent_reports)): ?>
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="nc-icon nc-chart-bar-32"></i>
                                    Recent Reports
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php foreach(array_slice($recent_reports, 0, 3) as $report): ?>
                                <div class="border-bottom py-2">
                                    <small class="text-muted"><?= htmlspecialchars($report['project_name']) ?></small>
                                    <h6 class="mb-1" style="font-size: 14px;">
                                        <?= htmlspecialchars($report['activity_name']) ?>
                                    </h6>
                                    <small class="text-info">
                                        by <?= htmlspecialchars($report['reporter_name']) ?> • 
                                        <?= formatDateTime($report['created_at']) ?>
                                    </small>
                                </div>
                                <?php endforeach; ?>
                                
                                <div class="text-center mt-2">
                                    <a href="reports.php" class="btn btn-outline-info btn-sm">
                                        <i class="nc-icon nc-chart-bar-32"></i>
                                        All Reports
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <script>


               <!-- FOOTER-->
           <?php include '../includes/footer.php'; ?>