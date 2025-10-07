<?php
// ===================================================================
//  ACTIVITY DETAIL VIEW PAGE
// ===================================================================

// ===================================================================
//  PAGE CONFIGURATION
// ===================================================================
$page_title = 'Activity Details - EU Project Manager';
// Optional: Add specific CSS or JS if needed later
// $page_css_path = '../assets/css/pages/activity-view.css';
// $page_js_path = '../assets/js/pages/activity-view.js';

// ===================================================================
//  INCLUDES
// ===================================================================
require_once '../includes/header.php';

// ===================================================================
//  DATABASE & USER DATA
// ===================================================================
$auth->requireLogin();
$database = new Database();
$conn = $database->connect();
$user_id = getUserId();
$user_role = getUserRole();

// ===================================================================
//  FETCH ACTIVITY DATA
// ===================================================================
$activity_id = (int)($_GET['id'] ?? 0);
$activity = null;
$reports = [];

if ($activity_id) {
    // Main query to get all details for the activity
    $sql = "
        SELECT 
            a.*, 
            wp.name as wp_name, 
            wp.wp_number, 
            p.name as project_name, 
            p.id as project_id,
            resp_partner.name as responsible_org
        FROM activities a
        JOIN work_packages wp ON a.work_package_id = wp.id
        JOIN projects p ON wp.project_id = p.id
        LEFT JOIN partners resp_partner ON a.responsible_partner_id = resp_partner.id
        WHERE a.id = :activity_id
    ";
    
    // Role-based access control: Non-admins can only see activities in their projects
    if ($user_role !== 'super_admin') {
        $user_partner_id = $_SESSION['partner_id'] ?? 0;
        $sql .= " AND p.id IN (SELECT project_id FROM project_partners WHERE partner_id = :partner_id)";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':activity_id', $activity_id, PDO::PARAM_INT);
    if ($user_role !== 'super_admin') {
        $stmt->bindValue(':partner_id', $user_partner_id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);

    // If activity is found, fetch its reports
    if ($activity) {
        $page_title = htmlspecialchars($activity['activity_number'] . ' - ' . $activity['name']);
        
        $reports_sql = "
            SELECT r.id, r.report_date, p.name as partner_name
            FROM activity_reports r
            JOIN partners p ON r.partner_id = p.id
            WHERE r.activity_id = :activity_id
            ORDER BY r.report_date DESC
        ";
        $reports_stmt = $conn->prepare($reports_sql);
        $reports_stmt->execute(['activity_id' => $activity_id]);
        $reports = $reports_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}

// Handle case where activity is not found or user has no permission
if (!$activity) {
    Flash::set('error', 'Activity not found or you do not have permission to view it.');
    header('Location: activities.php');
    exit;
}

?>

<body class="">
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <?php include '../includes/navbar.php'; ?>

        <div class="content">
            <?php displayAlert(); ?>

            <!-- Activity Header -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">
                                <i class="nc-icon nc-tag-content"></i> 
                                <?= htmlspecialchars($activity['activity_number']) ?> - <?= htmlspecialchars($activity['name']) ?>
                            </h4>
                            <p class="category">
                                Part of project: 
                                <a href="project-detail.php?id=<?= $activity['project_id'] ?>">
                                    <?= htmlspecialchars($activity['project_name']) ?>
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Details & Description -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header"><h5 class="card-title">Activity Description</h5></div>
                        <div class="card-body">
                            <p><?= nl2br(htmlspecialchars($activity['description'])) ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header"><h5 class="card-title">Details</h5></div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <strong>Status:</strong>
                                    <span class="ml-2"><?= getStatusBadge($activity['status']) ?></span>
                                </li>
                                <li class="mb-2">
                                    <strong>Work Package:</strong>
                                    <span class="ml-2"><?= htmlspecialchars($activity['wp_number']) ?> - <?= htmlspecialchars($activity['wp_name']) ?></span>
                                </li>
                                <li class="mb-2">
                                    <strong>Responsible Partner:</strong>
                                    <span class="ml-2"><?= htmlspecialchars($activity['responsible_org'] ?? 'N/A') ?></span>
                                </li>
                                <li class="mb-2">
                                    <strong>Start Date:</strong>
                                    <span class="ml-2"><?= $activity['start_date'] ? formatDate($activity['start_date']) : 'N/A' ?></span>
                                </li>
                                <li class="mb-2">
                                    <strong>End Date:</strong>
                                    <span class="ml-2"><?= $activity['end_date'] ? formatDate($activity['end_date']) : 'N/A' ?></span>
                                </li>
                                
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Associated Reports -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header"><h5 class="card-title">Associated Reports</h5></div>
                        <div class="card-body">
                            <?php if (empty($reports)): ?>
                                <p>No reports have been submitted for this activity yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Report Date</th>
                                                <th>Submitted by Partner</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reports as $report): ?>
                                                <tr>
                                                    <td><?= formatDate($report['report_date']) ?></td>
                                                    <td><?= htmlspecialchars($report['partner_name']) ?></td>
                                                    <td>
                                                        <a href="edit-report.php?id=<?= $report['id'] ?>" class="btn btn-sm btn-warning">
                                                            <i class="nc-icon nc-zoom-split"></i> View / Edit
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                             <hr>
                            <a href="create-report.php?activity_id=<?= $activity_id ?>" class="btn btn-primary">
                                <i class="nc-icon nc-simple-add"></i> Submit New Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <?php include '../includes/footer.php'; ?>
    </div>
</body>
</html>
