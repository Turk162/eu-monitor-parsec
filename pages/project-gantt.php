<?php
// ===================================================================
// PROJECT GANTT - Page-specific configuration
// ===================================================================

// Set the title of the page
$page_title = 'Project Gantt - EU Project Manager';

// Specify the path to the page-specific CSS file
$page_css_path = '../assets/css/pages/gantt.css';

// Specify the path to the page-specific JS file
$page_js_path = '../assets/js/pages/gantt.js';

// Set fullwidth body class for Gantt page
$body_class = 'gantt-fullwidth';

// Include header (handles session, auth, database, user variables)
require_once '../includes/header.php';

// Database connection (user_id and user_role are already available from header)
$database = new Database();
$conn = $database->connect();

// ===================================================================
//  PROJECT GANTT DATA RETRIEVAL
// ===================================================================

// Get project ID from URL
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$project_id) {
    $_SESSION['error'] = "Project ID is required.";
    header('Location: projects.php');
    exit;
}

// Check if user has access to this project
if ($user_role !== 'super_admin') {
    $user_partner_id = $_SESSION['partner_id'] ?? 0;
    $access_check = $conn->prepare("
        SELECT COUNT(*) 
        FROM project_partners pp 
        WHERE pp.project_id = ? AND pp.partner_id = ?
    ");
    $access_check->execute([$project_id, $user_partner_id]);
    
    if ($access_check->fetchColumn() == 0) {
        $_SESSION['error'] = "You don't have access to this project.";
        header('Location: projects.php');
        exit;
    }
}

// Get project basic information
$project_stmt = $conn->prepare("
    SELECT p.*, 
           u.full_name as coordinator_name,
           part.name as coordinator_organization
    FROM projects p
    LEFT JOIN users u ON p.coordinator_id = u.id
    LEFT JOIN partners part ON u.partner_id = part.id
    WHERE p.id = ?
");
$project_stmt->execute([$project_id]);
$project = $project_stmt->fetch();

if (!$project) {
    $_SESSION['error'] = "Project not found.";
    header('Location: projects.php');
    exit;
}

// Get work packages with their information
$wp_stmt = $conn->prepare("
    SELECT wp.*, 
           u.full_name as lead_partner_name,
           part.name as lead_organization
    FROM work_packages wp
    LEFT JOIN users u ON wp.lead_partner_id = u.id
    LEFT JOIN partners part ON u.partner_id = part.id
    WHERE wp.project_id = ?
    ORDER BY wp.wp_number
");
$wp_stmt->execute([$project_id]);
$work_packages = $wp_stmt->fetchAll();

// Get activities for each work package
$activities_by_wp = [];
foreach ($work_packages as $wp) {
    $activities_stmt = $conn->prepare("
        SELECT a.*, 
               part.name as responsible_partner_name
        FROM activities a
        LEFT JOIN partners part ON a.responsible_partner_id = part.id
        WHERE a.work_package_id = ?
        ORDER BY a.activity_number
    ");
    $activities_stmt->execute([$wp['id']]);
    $activities_by_wp[$wp['id']] = $activities_stmt->fetchAll();
}

// Get project milestones
$milestones_stmt = $conn->prepare("
    SELECT m.*, 
           wp.wp_number,
           wp.name as wp_name
    FROM milestones m
    LEFT JOIN work_packages wp ON m.work_package_id = wp.id
    WHERE m.project_id = ?
    ORDER BY m.due_date ASC
");
$milestones_stmt->execute([$project_id]);
$milestones = $milestones_stmt->fetchAll();

// Calculate timeline months
$project_start = new DateTime($project['start_date']);
$project_end = new DateTime($project['end_date']);

// Generate monthly timeline
$timeline_months = [];
$current_date = clone $project_start;
$current_date->modify('first day of this month');

while ($current_date <= $project_end) {
    $timeline_months[] = [
        'year' => $current_date->format('Y'),
        'month' => $current_date->format('n'),
        'month_name' => $current_date->format('M'),
        'full_name' => $current_date->format('F Y'),
        'date_key' => $current_date->format('Y-m')
    ];
    $current_date->modify('+1 month');
}

// Work package colors (fixed)
$wp_colors = [
    'WP1' => '#51CACF', // Primary theme
    'WP2' => '#28a745', // Green  
    'WP3' => '#ffc107', // Yellow
    'WP4' => '#dc3545', // Red
    'WP5' => '#6f42c1', // Purple
    'WP6' => '#fd7e14', // Orange
    'WP7' => '#17a2b8', // Teal
    'WP8' => '#6c757d'  // Gray
];

// Helper functions
function calculateDuration($start_date, $end_date) {
    if (!$start_date || !$end_date) return 0;
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    return $interval->days;
}



function isInMonth($start_date, $end_date, $year, $month) {
    if (!$start_date || !$end_date) return false;
    
    $month_start = new DateTime("$year-$month-01");
    $month_end = clone $month_start;
    $month_end->modify('last day of this month');
    
    $task_start = new DateTime($start_date);
    $task_end = new DateTime($end_date);
    
    return ($task_start <= $month_end && $task_end >= $month_start);
}
?>

<!-- FULLWIDTH LAYOUT FOR GANTT -->
<div class="wrapper">
    <!-- CUSTOM NAVBAR FOR GANTT PAGE -->
    <nav class="navbar navbar-expand-lg navbar-absolute fixed-top navbar-transparent">
        <div class="container-fluid">
            <div class="navbar-wrapper">
                <a class="navbar-brand" href="dashboard.php">
                    <i class="nc-icon nc-chart-bar-32"></i>
                    EU Project Manager - Gantt View
                </a>
            </div>
            
            <!-- GANTT PAGE NAVIGATION -->
            <div class="navbar-nav-scroll">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="nc-icon nc-bank"></i>
                            <span class="d-lg-none">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="projects.php">
                            <i class="nc-icon nc-briefcase-24"></i>
                            <span class="d-lg-none">Projects</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="activities.php">
                            <i class="nc-icon nc-paper"></i>
                            <span class="d-lg-none">Activities</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="calendar.php">
                            <i class="nc-icon nc-calendar-60"></i>
                            <span class="d-lg-none">Calendar</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="nc-icon nc-chart-bar-32"></i>
                            <span class="d-lg-none">Reports</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="navbar-nav ml-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                        <i class="nc-icon nc-single-02"></i>
                        <span class="d-lg-none"><?= $_SESSION['full_name'] ?></span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="profile.php">
                            <i class="nc-icon nc-single-02"></i>
                            Profile
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item logout-btn" href="../logout.php">
                            <i class="nc-icon nc-button-power"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- MAIN PANEL WITH FULLWIDTH -->
    <div class="main-panel">
        <div class="content">
            <!-- ALERT MESSAGE -->
            <?php displayAlert(); ?>
            
            <!-- PROJECT HEADER -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card project-header-card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <div class="d-flex align-items-center mb-2">
                                        <a href="project-detail.php?id=<?= $project_id ?>" class="btn btn-outline-light btn-sm mr-3">
                                            <i class="nc-icon nc-minimal-left"></i> Back to Project
                                        </a>
                                        <h4 class="mb-0 text-white">
                                            <i class="nc-icon nc-chart-bar-32"></i>
                                            <?= htmlspecialchars($project['name']) ?> - Gantt Chart
                                        </h4>
                                    </div>
                                    <p class="text-white-50 mb-0">
                                        <i class="nc-icon nc-calendar-60"></i>
                                        <?= formatDate($project['start_date']) ?> - <?= formatDate($project['end_date']) ?>
                                        <span class="mx-2">|</span>
                                        <i class="nc-icon nc-badge"></i>
                                        <?= htmlspecialchars($project['program_type']) ?>
                                    </p>
                                </div>
                                <div class="col-md-4 text-right">
                                    <div class="project-stats">
                                        <div class="stat-item">
                                            <strong><?= count($work_packages) ?></strong>
                                            <small class="d-block text-white-50">Work Packages</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GANTT CHART TABLE -->
            <div class="row">
                <div class="col-12">
                    <div class="card gantt-table-card">
                        <div class="card-body p-0">
                            <div class="gantt-wrapper">
                                <div id="gantt-scroll-left" class="gantt-scroll-arrow left">&lt;</div>
                                <div class="gantt-table-container">
                                    <table class="table gantt-table mb-0">
                                    <thead>
                                        <tr>
                                            <th class="gantt-task-column">
                                                <div class="task-header">
                                                    <strong>Work Packages & Activities</strong>
                                                </div>
                                            </th>
                                            <th class="gantt-dates-column">
                                                <div class="dates-header">
                                                    <strong>Start - End</strong>
                                                </div>
                                            </th>
                                            <?php foreach ($timeline_months as $month): ?>
                                            <th class="gantt-month-column" data-month="<?= $month['date_key'] ?>">
                                                <div class="month-header">
                                                    <div class="month-name"><?= $month['month_name'] ?></div>
                                                    <div class="month-year"><?= $month['year'] ?></div>
                                                </div>
                                            </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($work_packages as $wp_index => $wp): ?>
                                        <?php if ($wp_index > 0): ?>
                                        <tr class="gantt-repeated-header">
                                            <th class="gantt-task-column">
                                                <div class="task-header">
                                                    <strong>Work Packages & Activities</strong>
                                                </div>
                                            </th>
                                            <th class="gantt-dates-column">
                                                <div class="dates-header">
                                                    <strong>Start - End</strong>
                                                </div>
                                            </th>
                                            <?php foreach ($timeline_months as $month): ?>
                                            <th class="gantt-month-column" data-month="<?= $month['date_key'] ?>">
                                                <div class="month-header">
                                                    <div class="month-name"><?= $month['month_name'] ?></div>
                                                    <div class="month-year"><?= $month['year'] ?></div>
                                                </div>
                                            </th>
                                            <?php endforeach; ?>
                                        </tr>
                                        <?php endif; ?>
                                        <!-- WORK PACKAGE ROW -->
                                        <tr class="gantt-wp-row" data-wp-id="<?= $wp['id'] ?>">
                                            <td class="gantt-task-cell wp-task">
                                                <div class="task-info">
                                                    <div class="wp-color-indicator" style="background-color: <?= $wp_colors[$wp['wp_number']] ?? '#6c757d' ?>"></div>
                                                    <div class="task-content">
                                                        <div class="task-name">WP<?= $wp['wp_number'] ?>: <?= htmlspecialchars($wp['name']) ?></div>
                                                        <div class="task-details"><?= htmlspecialchars($wp['lead_partner_name'] ?? 'No lead assigned') ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="gantt-dates-cell">
                                                <div class="dates-info">
                                                    <div class="start-date"><?= $wp['start_date'] ? date('M d, Y', strtotime($wp['start_date'])) : 'TBD' ?></div>
                                                    <div class="end-date"><?= $wp['end_date'] ? date('M d, Y', strtotime($wp['end_date'])) : 'TBD' ?></div>
                                                </div>
                                            </td>
                                            <?php foreach ($timeline_months as $month): ?>
                                            <td class="gantt-month-cell">
                                                <?php if (isInMonth($wp['start_date'], $wp['end_date'], $month['year'], $month['month'])): ?>
                                                <div class="gantt-bar wp-bar" 
                                                     style="background-color: <?= $wp_colors[$wp['wp_number']] ?? '#6c757d' ?>; opacity: 0.8;"
                                                     data-toggle="tooltip"
                                                     data-wp-id="<?= $wp['id'] ?>"
                                                     title="<?= htmlspecialchars($wp['name']) ?>">
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <?php endforeach; ?>
                                        </tr>

                                        <!-- ACTIVITIES ROWS -->
                                        <?php if (!empty($activities_by_wp[$wp['id']])): ?>
                                        <?php foreach ($activities_by_wp[$wp['id']] as $activity): ?>
                                        <tr class="gantt-activity-row" data-activity-id="<?= $activity['id'] ?>" data-wp-id="<?= $wp['id'] ?>">
                                            <td class="gantt-task-cell activity-task">
                                                <div class="task-info activity-info">
                                                    <div class=""><i class="nc-icon nc-circle-10"></i></div>
                                                    <div class="activity-indent"></div>
                                                    <div class="task-content">
                                                        <div class="task-name"><?= htmlspecialchars($activity['name']) ?></div>
                                                        <div class="task-details"><?= htmlspecialchars($activity['responsible_partner_name'] ?? 'Unassigned') ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="gantt-dates-cell">
                                                <div class="dates-info">
                                                    <div class="start-date"><?= $activity['start_date'] ? date('M d, Y', strtotime($activity['start_date'])) : 'TBD' ?></div>
                                                    <div class="end-date"><?= $activity['end_date'] ? date('M d, Y', strtotime($activity['end_date'])) : 'TBD' ?></div>
                                                </div>
                                            </td>
                                            <?php foreach ($timeline_months as $month): ?>
                                            <td class="gantt-month-cell">
                                                <?php if (isInMonth($activity['start_date'], $activity['end_date'], $month['year'], $month['month'])): ?>
                                                <div class="gantt-bar activity-bar" 
                                                     style="background-color: <?= $wp_colors[$wp['wp_number']] ?? '#6c757d' ?>; opacity: 0.6;"
                                                     data-toggle="tooltip"
                                                     data-activity-id="<?= $activity['id'] ?>"
                                                     title="<?= htmlspecialchars($activity['name']) ?>">
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>

                                        <!-- MILESTONES ROW FOR THIS WP -->
                                        <?php 
                                        $wp_milestones = array_filter($milestones, function($m) use ($wp) { 
                                            return $m['wp_number'] == $wp['wp_number']; 
                                        });
                                        if (!empty($wp_milestones)): 
                                        ?>
                                        <tr class="gantt-milestone-row">
                                            <td class="gantt-task-cell milestone-task">
                                                <div class="task-info milestone-info">
                                                    <div class="milestone-indent"></div>
                                                    <div class="task-content">
                                                        <div class="task-name">
                                                            <i class="nc-icon nc-diamond"></i>
                                                            Milestones
                                                        </div>
                                                        <div class="task-details"><?= count($wp_milestones) ?> milestone(s)</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="gantt-dates-cell">
                                                <div class="dates-info">
                                                    <div class="milestone-count"><?= count($wp_milestones) ?> items</div>
                                                </div>
                                            </td>
                                            <?php foreach ($timeline_months as $month): ?>
                                            <td class="gantt-month-cell milestone-cell">
                                                <?php foreach ($wp_milestones as $milestone): ?>
                                                    <?php if ($milestone['due_date']): ?>
                                                        <?php $milestone_date = new DateTime($milestone['due_date']); ?>
                                                        <?php if ($milestone_date->format('Y-m') === $month['date_key']): ?>
                                                        <div class="milestone-marker" 
                                                             data-toggle="tooltip"
                                                             data-milestone-id="<?= $milestone['id'] ?>"
                                                             title="<?= htmlspecialchars($milestone['name']) ?> - <?= formatDate($milestone['due_date']) ?>">
                                                            ♦
                                                        </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div id="gantt-scroll-right" class="gantt-scroll-arrow right">&gt;</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LEGEND -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title mb-3">
                                <i class="nc-icon nc-palette"></i>
                                Color Legend
                            </h6>
                            <div class="legend-container">
                                <?php foreach ($work_packages as $wp): ?>
                                <div class="legend-item">
                                    <div class="legend-color" style="background-color: <?= $wp_colors[$wp['wp_number']] ?? '#1387edff' ?>"></div>
                                    <span class="legend-text"><?= $wp['wp_number'] ?>: <?= htmlspecialchars($wp['name']) ?></span>
                                </div>
                                <?php endforeach; ?>
                                <div class="legend-item">
                                    <div class="legend-symbol">♦</div>
                                    <span class="legend-text">Milestones</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <?php include '../includes/footer.php'; ?>
    </div>
</div>

<!-- Pass data to JavaScript -->
<script>
window.projectGanttData = {
    project: <?= json_encode($project) ?>,
    workPackages: <?= json_encode($work_packages) ?>,
    activities: <?= json_encode($activities_by_wp) ?>,
    milestones: <?= json_encode($milestones) ?>,
    wpColors: <?= json_encode($wp_colors) ?>
};
</script>