<?php
// ===================================================================
//  ACTIVITIES MANAGEMENT PAGE
// ===================================================================

// ===================================================================
//  PAGE CONFIGURATION
// ===================================================================

$page_title = 'Activities Management - EU Project Manager';
$page_css_path = '../assets/css/pages/activities.css';
$page_js_path = '../assets/js/pages/activities.js';

// ===================================================================
//  INCLUDES
// ===================================================================

require_once '../includes/header.php'; // Handles session, auth, and initial page setup

// ===================================================================
//  DATABASE & USER DATA
// ===================================================================

// $auth->requireLogin(); // Temporarily commented out for debugging session issue

$database = new Database();
$conn = $database->connect();

$user_id = getUserId();
$user_role = getUserRole();
$user_partner_id = $_SESSION['partner_id'] ?? 0;



// ===================================================================
//  HANDLE ACTIVITY STATUS UPDATE
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_activity'])) {
    header('Content-Type: application/json');
    
    $activity_id = (int)($_POST['activity_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    
    // Validazione input
    $valid_statuses = ['not_started', 'in_progress', 'completed'];
    if (!$activity_id || !in_array($new_status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    // Controllo permessi
    $can_modify = false;
    if ($user_role === 'super_admin') {
        $can_modify = true;
    } else {
        $stmt = $conn->prepare("SELECT responsible_partner_id FROM activities WHERE id = ?");
        $stmt->execute([$activity_id]);
        $responsible_partner = $stmt->fetchColumn();
        $can_modify = ($responsible_partner == $user_partner_id);
    }
    
    if ($can_modify) {
        $update_stmt = $conn->prepare("UPDATE activities SET status = ?, updated_at = NOW() WHERE id = ?");
        $success = $update_stmt->execute([$new_status, $activity_id]);
        
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Activity updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database update failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to modify this activity']);
    }
    exit;
}


// ===================================================================
//  FILTERING LOGIC
// ===================================================================

// Retrieve filter values from the URL parameters
$project_filter = (int)($_GET['project'] ?? 0);
$wp_filter = (int)($_GET['wp'] ?? 0);
$status_filter = (string)($_GET['status'] ?? '');
$search_query = trim((string)($_GET['search'] ?? ''));
$view_mode = (string)($_GET['view'] ?? 'cards'); // 'cards' or 'table'
$my_activities_only = !empty($_GET['my_activities']);

// ===================================================================
//  MAIN QUERY CONSTRUCTION
// ===================================================================

// Base SQL query selects all necessary fields for display
$sql_select = "
    SELECT 
        a.*, 
        wp.name as wp_name, 
        wp.wp_number, 
        p.name as project_name, 
        p.id as project_id,
        resp_partner.name as responsible_org,
        CASE WHEN a.responsible_partner_id = :logged_in_partner_id THEN 1 ELSE 0 END as is_my_responsibility
    FROM activities a
    JOIN work_packages wp ON a.work_package_id = wp.id
    JOIN projects p ON wp.project_id = p.id
    LEFT JOIN partners resp_partner ON a.responsible_partner_id = resp_partner.id
";

// WHERE clauses and parameters will be built dynamically
$where_conditions = [];
$params = ['logged_in_partner_id' => $user_partner_id];

// Role-based access control: Super admins see all projects, others see only their own.
if ($user_role !== 'super_admin') {
    $where_conditions[] = "p.id IN (SELECT project_id FROM project_partners WHERE partner_id = :partner_id)";
    $params['partner_id'] = $user_partner_id;
}

// Apply filters to the query
if ($my_activities_only) {
    $where_conditions[] = "a.responsible_partner_id = :user_id_filter";
$params['user_id_filter'] = $user_partner_id;
}
if ($project_filter) {
    $where_conditions[] = "p.id = :project_id";
    $params['project_id'] = $project_filter;
}
if ($wp_filter) {
    $where_conditions[] = "wp.id = :wp_id";
    $params['wp_id'] = $wp_filter;
}
if ($status_filter) {
    $where_conditions[] = "a.status = :status";
    $params['status'] = $status_filter;
}
if ($search_query) {
    $where_conditions[] = "(a.name LIKE :search OR a.description LIKE :search)";
    $params['search'] = "%$search_query%";
}

// Combine all conditions into the final SQL query
$sql = $sql_select;
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

// Define sorting order: Overdue first, then due soon, then by due date.
$sql .= " ORDER BY 
            CASE 
                WHEN a.end_date IS NOT NULL AND a.end_date < CURDATE() AND a.status != 'completed' THEN 1
                WHEN a.end_date IS NOT NULL AND a.end_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 2
                ELSE 3
            END,
            a.end_date ASC, a.created_at DESC";

// Execute the main query to get activities
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll();

// ===================================================================
//  DATA FOR FILTERS & STATISTICS
// ===================================================================

// Fetch available projects for the filter dropdown
if ($user_role === 'super_admin') {
    $projects_stmt = $conn->query("SELECT id, name FROM projects ORDER BY name");
} else {
    $projects_stmt = $conn->prepare("
        SELECT DISTINCT p.id, p.name FROM projects p 
        JOIN project_partners pp ON p.id = pp.project_id 
        WHERE pp.partner_id = ? ORDER BY p.name
    ");
    $projects_stmt->execute([$user_partner_id]);
}
$available_projects = $projects_stmt->fetchAll();

// Fetch work packages for the selected project
$available_wps = [];
if ($project_filter) {
    $wp_stmt = $conn->prepare("SELECT id, wp_number, name FROM work_packages WHERE project_id = ? ORDER BY wp_number");
    $wp_stmt->execute([$project_filter]);
    $available_wps = $wp_stmt->fetchAll();
}

// Calculate statistics based on the fetched activities
$stats = [
    'total' => count($activities),
    'not_started' => count(array_filter($activities, fn($a) => $a['status'] === 'not_started')),
    'in_progress' => count(array_filter($activities, fn($a) => $a['status'] === 'in_progress')),
    'completed' => count(array_filter($activities, fn($a) => $a['status'] === 'completed')),
    'overdue' => count(array_filter($activities, fn($a) => $a['status'] !== 'completed' && !empty($a['end_date']) && strtotime($a['end_date']) < time())),
    'my_total' => count(array_filter($activities, fn($a) => $a['is_my_responsibility'] == 1)),
    'my_completed' => count(array_filter($activities, fn($a) => $a['is_my_responsibility'] == 1 && $a['status'] === 'completed')),
];

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

            <!-- STATISTICS CARDS -->
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="card card-stats">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-5 col-md-4">
                                    <div class="icon-big text-center icon-warning">
                                        <i class="nc-icon nc-paper text-primary"></i>
                                    </div>
                                </div>
                                <div class="col-7 col-md-8">
                                    <div class="numbers">
                                        <p class="card-category">Total Activities</p>
                                        <p class="card-title"><?= $stats['total'] ?>
                                            <?php if ($user_role !== 'super_admin' && $stats['my_total'] > 0): ?>
                                            <small style="font-size: 14px; color: #51CACF;"> (<?= $stats['my_total'] ?> mine)</small>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card card-stats">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-5 col-md-4">
                                    <div class="icon-big text-center icon-warning">
                                        <i class="nc-icon nc-settings-gear-65 text-warning"></i>
                                    </div>
                                </div>
                                <div class="col-7 col-md-8">
                                    <div class="numbers">
                                        <p class="card-category">In Progress</p>
                                        <p class="card-title"><?= $stats['in_progress'] ?>
    <?php if ($user_role !== 'super_admin'): ?>
        <?php 
        $my_in_progress = count(array_filter($activities, fn($a) => $a['is_my_responsibility'] == 1 && $a['status'] === 'in_progress'));
        if ($my_in_progress > 0): ?>
        <small style="font-size: 14px; color: #51CACF;"> (<?= $my_in_progress ?> mine)</small>
        <?php endif; ?>
    <?php endif; ?>
</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card card-stats">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-5 col-md-4">
                                    <div class="icon-big text-center icon-warning">
                                        <i class="nc-icon nc-check-2 text-success"></i>
                                    </div>
                                </div>
                                <div class="col-7 col-md-8">
                                    <div class="numbers">
                                        <p class="card-category">Completed</p>
                                        <p class="card-title"><?= $stats['completed'] ?>
                                            <?php if ($user_role !== 'super_admin' && $stats['my_completed'] > 0): ?>
                                            <small style="font-size: 14px; color: #51CACF;"> (<?= $stats['my_completed'] ?> mine)</small>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card card-stats">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-5 col-md-4">
                                    <div class="icon-big text-center icon-warning">
                                        <i class="nc-icon nc-bell-55 text-danger"></i>
                                    </div>
                                </div>
                                <div class="col-7 col-md-8">
                                    <div class="numbers">
                                        <p class="card-category">Overdue</p>
                                        <p class="card-title">
<p class="card-title"><?= $stats['overdue'] ?>
    <?php if ($user_role !== 'super_admin'): ?>
    <small style="font-size: 14px; color: #dc3545;"></small>
    <?php endif; ?>
</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FILTERS AND CONTROLS -->
            <div class="row mb-4">
                <div class="col-12">
                    <!-- View Toggle and My Activities Filter -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="view-toggle">
                                    <a href="?<?= http_build_query(array_merge($_GET, ['view' => 'cards'])) ?>" 
                                       class="btn <?= $view_mode === 'cards' ? 'active' : '' ?>">
                                        <i class="nc-icon nc-layout-11"></i>
                                        Cards
                                    </a>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['view' => 'table'])) ?>" 
                                       class="btn <?= $view_mode === 'table' ? 'active' : '' ?>">
                                        <i class="nc-icon nc-bullet-list-67"></i>
                                        Table
                                    </a>
                                </div>
                                
                                <div class="d-flex align-items-center">
                                    <!-- My Activities Toggle -->
                                    <?php if ($user_role !== 'super_admin' && $stats['my_total'] > 0): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['my_activities' => $my_activities_only ? '0' : '1'])) ?>" 
                                       class="my-activities-toggle <?= $my_activities_only ? 'active' : '' ?> mr-3">
                                        <i class="nc-icon nc-single-02"></i>
                                        <?= $my_activities_only ? 'Show All Activities' : 'My Activities Only' ?>
                                        (<?= $stats['my_total'] ?>)
                                    </a>
                                    <?php endif; ?>
                                    
                                    <small class="text-muted">
                                        Showing <?= count($activities) ?> activities
                                        <?php if ($project_filter || $wp_filter || $status_filter || $search_query || $my_activities_only): ?>
                                        with current filters
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                            
                            <!-- Filters Form -->
                            <form method="GET" action="" id="filterForm">
                                <input type="hidden" name="view" value="<?= $view_mode ?>">
                                <input type="hidden" name="my_activities" value="<?= $my_activities_only ? '1' : '0' ?>">
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <label>
                                            <i class="nc-icon nc-zoom-split"></i>
                                            Search Activities
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               name="search" 
                                               placeholder="Search activities..." 
                                               value="<?= htmlspecialchars($search_query) ?>">
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label>
                                            <i class="nc-icon nc-briefcase-24"></i>
                                            Project
                                        </label>
                                        <select class="form-control" name="project" id="projectSelect">
                                            <option value="">All Projects</option>
                                            <?php foreach($available_projects as $proj): ?>
                                            <option value="<?= $proj['id'] ?>" <?= $project_filter == $proj['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($proj['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label>
                                            <i class="nc-icon nc-layers-3"></i>
                                            Work Package
                                        </label>
                                        <select class="form-control" name="wp">
                                            <option value="">All WPs</option>
                                            <?php foreach($available_wps as $wp): ?>
                                            <option value="<?= $wp['id'] ?>" <?= $wp_filter == $wp['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($wp['wp_number']) ?> - <?= htmlspecialchars($wp['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label>
                                            <i class="nc-icon nc-settings-gear-65"></i>
                                            Status
                                        </label>
                                        <select class="form-control" name="status">
                                            <option value="">All Status</option>
                                            <option value="not_started" <?= $status_filter === 'not_started' ? 'selected' : '' ?>>Not Started</option>
                                            <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                                            <option value="overdue" <?= $status_filter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="nc-icon nc-zoom-split"></i>
                                            Apply Filters
                                        </button>
                                        <a href="activities.php" class="btn btn-outline-secondary ml-2">
                                            <i class="nc-icon nc-simple-remove"></i>
                                            Clear All
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACTIVITIES DISPLAY -->
            <?php if (empty($activities)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="empty-state">
                                <i class="nc-icon nc-paper"></i>
                                <h4>No Activities Found</h4>
                                <p class="text-muted">
                                    <?php if ($search_query || $project_filter || $wp_filter || $status_filter || $my_activities_only): ?>
                                        No activities match your current filters. Try adjusting your search criteria.
                                    <?php else: ?>
                                        You don't have any activities assigned yet.
                                    <?php endif; ?>
                                </p>
                                
                                <?php if ($search_query || $project_filter || $wp_filter || $status_filter || $my_activities_only): ?>
                                <a href="activities.php" class="btn btn-primary">
                                    <i class="nc-icon nc-simple-remove"></i>
                                    Clear Filters
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php elseif ($view_mode === 'cards'): ?>
            
            <!-- CARDS VIEW -->
            <div class="row">
                <?php foreach($activities as $activity): ?>
                <?php
                $is_overdue = $activity['status'] !== 'completed' && $activity['end_date'] && strtotime($activity['end_date']) < time();
                $is_due_soon = $activity['end_date'] && strtotime($activity['end_date']) <= strtotime('+7 days') && !$is_overdue;
                $is_completed = $activity['status'] === 'completed';
                $is_my_responsibility = $activity['is_my_responsibility'] == 1;
                $can_modify = ($user_role === 'super_admin' || $is_my_responsibility);
                
                $days_left = null;
                $days_class = 'safe';
                if ($activity['end_date'] && !$is_completed) {
                    $days_left = ceil((strtotime($activity['end_date']) - time()) / (60 * 60 * 24));
                    if ($days_left < 0) {
                        $days_class = 'urgent';
                    } elseif ($days_left <= 3) {
                        $days_class = 'urgent';
                    } elseif ($days_left <= 7) {
                        $days_class = 'warning';
                    }
                }
                ?>
                
                <div class="col-lg-6 col-xl-4">
                    <div class="activity-card card <?= $is_overdue ? 'overdue' : ($is_due_soon ? 'due-soon' : ($is_completed ? 'completed' : '')) ?> <?= $is_my_responsibility ? 'my-responsibility' : '' ?>">
                        
                        <!-- Activity Header -->
                        <div class="activity-header">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-1">
                                       <h6 class="mb-0 mr-2">
      
                                   <!--TITOLO ATTIVITÀ -->
      
      <?php
        $activityName = $activity['name'];
        $limit = 50; // Imposta qui il limite di caratteri
        $truncatedName = (strlen($activityName) > $limit) ? htmlspecialchars(substr($activityName, 0, $limit)) . '...' : htmlspecialchars($activityName);
       ?>
       <a href="activity-view.php?id=<?= $activity['id'] ?>" class="text-dark font-weight-bold"><?= $truncatedName ?></a>
   </h6>
                                        <?php if ($is_my_responsibility): ?>
                                        <span class="responsibility-badge">
                                            <i class="nc-icon nc-single-02"></i>
                                            Your Responsibility
                                        </span>
                                        <?php else: ?>
                                        <span class="other-activity-badge">
                                            <?= htmlspecialchars($activity['responsible_org'] ?? 'Not assigned') ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <i class="nc-icon nc-briefcase-24"></i>
                                        <?= htmlspecialchars($activity['project_name']) ?>
                                    </small><br>
                                    <small class="text-info">
                                        <i class="nc-icon nc-layers-3"></i>
                                        <?= htmlspecialchars($activity['wp_number']) ?> - <?= htmlspecialchars(substr($activity['wp_name'], 0, 22)) ?>
                                    </small>
                                </div>
                                <div class="text-right">
                                    <?= getStatusBadge($activity['status']) ?>
                                    <?php if ($days_left !== null): ?>
                                    <div class="days-left <?= $days_class ?> mt-1">
                                        <?php if ($days_left < 0): ?>
                                            <?= abs($days_left) ?> days overdue
                                        <?php elseif ($days_left == 0): ?>
                                            Due today!
                                        <?php else: ?>
                                            <?= $days_left ?> days left
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Activity Body -->
                        <div class="activity-body">
                            <p class="text-muted mb-3" style="font-size: 14px;">
                                <?= htmlspecialchars(substr($activity['description'], 0, 100)) ?>
                                <?= strlen($activity['description']) > 120 ? '...' : '' ?>
                            </p>
                            
                                                        
                            <!-- Quick Update Form -->
                            <div class="quick-update">
                                <form class="activity-update-form" method="POST" action="">
                                    <input type="hidden" name="update_activity" value="1">
                                    <input type="hidden" name="activity_id" value="<?= $activity['id'] ?>">
                                    
                                    <div class="row align-items-center">
    <div class="col-12">
        <?php
        $status_class = 'status-' . str_replace('_', '-', $activity['status']);
        ?>
        <select class="status-selector <?= $status_class ?>" name="status" <?= !$can_modify ? 'disabled' : '' ?>>
            <option value="not_started" <?= $activity['status'] === 'not_started' ? 'selected' : '' ?>>Not Started</option>
            <option value="in_progress" <?= $activity['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
            <option value="completed" <?= $activity['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
        </select>
    </div>
</div>
                                </form>
                                
                                <?php if (!$can_modify): ?>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="nc-icon nc-simple-remove"></i>
Only <?= htmlspecialchars($activity['responsible_org'] ?? 'the responsible partner') ?> can modify this activity                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Activity Footer -->
                        <div class="activity-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <?php if ($activity['end_date']): ?>
                                    <small class="deadline-badge <?= $is_overdue ? 'bg-danger text-white' : ($is_due_soon ? 'bg-warning text-dark' : 'bg-light text-dark') ?>">
                                        <i class="nc-icon nc-calendar-60"></i>
                                        Due: <?= formatDate($activity['end_date']) ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <a href="create-report.php?activity_id=<?= $activity['id'] ?>" class="btn btn-sm btn-info">
                                        <i class="nc-icon nc-send"></i> Send Report
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php else: ?>
            
            <!-- TABLE VIEW -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table activity-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Activity</th>
                                            <th>Project / WP</th>
                                            <th>Responsibility</th>
                                            <th>Status</th>
                                            <th>Due Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($activities as $activity): ?>
                                        <?php
                                        $is_overdue = $activity['status'] !== 'completed' && $activity['end_date'] && strtotime($activity['end_date']) < time();
                                        $is_due_soon = $activity['end_date'] && strtotime($activity['end_date']) <= strtotime('+7 days') && !$is_overdue;
                                        $is_my_responsibility = $activity['is_my_responsibility'] == 1;
                                        $can_modify = ($user_role === 'super_admin' || $is_my_responsibility);
                                        ?>
                                        
                                        <tr class="<?= $is_overdue ? 'table-danger' : ($is_due_soon ? 'table-warning' : '') ?> <?= $is_my_responsibility ? 'my-responsibility' : '' ?>">
                                            <td>
                                                <h6 class="mb-1"><?= htmlspecialchars($activity['name']) ?></h6>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars(substr($activity['description'], 0, 60)) ?>
                                                    <?= strlen($activity['description']) > 60 ? '...' : '' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($activity['project_name']) ?></strong><br>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($activity['wp_number']) ?> - <?= htmlspecialchars($activity['wp_name']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($is_my_responsibility): ?>
                                                <span class="responsibility-badge">
                                                    <i class="nc-icon nc-single-02"></i>
                                                    Your Responsibility
                                                </span>
                                                <?php else: ?>
                                                <small class="text-muted">
                                                    <i class="nc-icon nc-bank"></i>
                                                    <?= htmlspecialchars($activity['responsible_org'] ?? 'Not assigned') ?>
                                                </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form class="activity-update-form" method="POST" action="">
                                                    <input type="hidden" name="update_activity" value="1">
                                                    <input type="hidden" name="activity_id" value="<?= $activity['id'] ?>">
                                                    
                                                    <div class="d-flex align-items-center">
                                                        <?php
                                                        $status_class = 'status-' . str_replace('_', '-', $activity['status']);
                                                        ?>
                                                        <select class="form-control form-control-sm status-selector <?= $status_class ?>" name="status" <?= !$can_modify ? 'disabled' : '' ?>>
                                                            <option value="not_started" <?= $activity['status'] === 'not_started' ? 'selected' : '' ?>>Not Started</option>
                                                            <option value="in_progress" <?= $activity['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                            <option value="completed" <?= $activity['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                        </select>
                                                    </div>
                                            </td>
                                            
                                            <td>
                                                <?php if ($activity['end_date']): ?>
                                                <span class="<?= $is_overdue ? 'text-danger font-weight-bold' : ($is_due_soon ? 'text-warning font-weight-bold' : '') ?>">
                                                    <?= formatDate($activity['end_date']) ?>
                                                </span>
                                                <?php else: ?>
                                                <span class="text-muted">No due date</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="create-report.php?activity_id=<?= $activity['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="nc-icon nc-send"></i> Report
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </div>

        <!-- FOOTER-->
        <?php include '../includes/footer.php'; ?>