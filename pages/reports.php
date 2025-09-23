<?php
// Page configuration (BEFORE including header)
$page_title = 'Reports - EU Project Manager';
$page_styles = '<link rel="stylesheet" href="../assets/css/pages/reports.css">';

// Include header (handles session, auth, database, user variables)
require_once '../includes/header.php';

// $auth, $conn, $user_id, $user_role are all available from header.php

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_report':
                $activity_id = (int)$_POST['activity_id'];
                $report_date = $_POST['report_date'];
                $description = trim($_POST['description']);
                $participants_data = $_POST['participants_data'] ?? '';
                
                // Validate activity access
                $access_check = $conn->prepare("
                    SELECT a.id, a.name, p.name as project_name 
                    FROM activities a
                    JOIN work_packages wp ON a.work_package_id = wp.id
                    JOIN projects p ON wp.project_id = p.id
                    JOIN project_partners pp ON p.id = pp.project_id
                    WHERE a.id = ? AND (pp.partner_id = ? OR ? = 'super_admin')
                ");
                $user_partner_id = $_SESSION['partner_id'] ?? 0;
                $access_check->execute([$activity_id, $user_partner_id, $user_role]);
                
                if ($access_check->fetch()) {
                    // Also get the partner_id of the current user
                    $user_partner_id = $_SESSION['partner_id'] ?? null;

                    $stmt = $conn->prepare("
                        INSERT INTO activity_reports (activity_id, partner_id, user_id, report_date, description, participants_data, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    if ($stmt->execute([$activity_id, $user_partner_id, $user_id, $report_date, $description, $participants_data])) {
                        $report_id = $conn->lastInsertId();
                        setSuccessMessage("Report created successfully! Report ID: #$report_id");
                        
                        // Handle file uploads
                        if (!empty($_FILES['report_files']['name'][0])) {
                            $upload_dir = '../uploads/reports/';
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0777, true);
                            }
                            
                            for ($i = 0; $i < count($_FILES['report_files']['name']); $i++) {
                                if ($_FILES['report_files']['error'][$i] === UPLOAD_ERR_OK) {
                                    $filename = $_FILES['report_files']['name'][$i];
                                    $tmp_name = $_FILES['report_files']['tmp_name'][$i];
                                    $file_size = $_FILES['report_files']['size'][$i];
                                    
                                    if (isAllowedFileType($filename) && $file_size <= 10485760) { // 10MB limit
                                        $safe_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
                                        $file_path = $upload_dir . $safe_filename;
                                        
                                        if (move_uploaded_file($tmp_name, $file_path)) {
                                            $file_stmt = $conn->prepare("
                                                INSERT INTO uploaded_files (report_id, filename, original_filename, file_path, file_size, file_type, uploaded_by, uploaded_at) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                                            ");
                                            $file_stmt->execute([
                                                $report_id, $safe_filename, $filename, $file_path, 
                                                $file_size, pathinfo($filename, PATHINFO_EXTENSION), $user_id
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        setErrorMessage("Error creating report. Please try again.");
                    }
                } else {
                    setErrorMessage("Access denied. You cannot report on this activity.");
                }
                break;
        }
    }
}

// Handle filters
$project_filter = isset($_GET['project']) ? (int)$_GET['project'] : 0;
$activity_filter = isset($_GET['activity']) ? (int)$_GET['activity'] : 0;
// $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$partner_filter = isset($_GET['partner']) ? (int)$_GET['partner'] : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'cards';

// Build main query based on user role
$where_conditions = [];
$params = [];

if ($user_role === 'super_admin' || $user_role === 'coordinator') {
    $base_sql = "SELECT ar.*, a.name as activity_name, a.activity_number,
                        wp.name as wp_name, wp.wp_number, p.name as project_name, p.id as project_id,
                        reporter.full_name as reporter_name, org.name as partner_org, org.country as partner_country,
                        reviewer.full_name as reviewer_name
                 FROM activity_reports ar
                 JOIN activities a ON ar.activity_id = a.id
                 JOIN work_packages wp ON a.work_package_id = wp.id
                 JOIN projects p ON wp.project_id = p.id
                 LEFT JOIN partners org ON ar.partner_id = org.id
                 LEFT JOIN users reporter ON ar.user_id = reporter.id
                 LEFT JOIN users reviewer ON ar.reviewed_by = reviewer.id";
} else {
    $user_partner_id = $_SESSION['partner_id'] ?? 0;
    $base_sql = "SELECT ar.*, a.name as activity_name, a.activity_number,
                        wp.name as wp_name, wp.wp_number, p.name as project_name, p.id as project_id,
                        reporter.full_name as reporter_name, org.name as partner_org, org.country as partner_country,
                        reviewer.full_name as reviewer_name
                 FROM activity_reports ar
                 JOIN activities a ON ar.activity_id = a.id
                 JOIN work_packages wp ON a.work_package_id = wp.id
                 JOIN projects p ON wp.project_id = p.id
                 JOIN project_partners pp ON p.id = pp.project_id
                 LEFT JOIN partners org ON ar.partner_id = org.id
                 LEFT JOIN users reporter ON ar.user_id = reporter.id
                 LEFT JOIN users reviewer ON ar.reviewed_by = reviewer.id
                 WHERE pp.partner_id = ?";
    $params[] = $user_partner_id;
}

// Add filters
if ($project_filter) {
    $where_conditions[] = "p.id = ?";
    $params[] = $project_filter;
}

if ($activity_filter) {
    $where_conditions[] = "a.id = ?";
    $params[] = $activity_filter;
}



if ($partner_filter) {
    $where_conditions[] = "u.id = ?";
    $params[] = $partner_filter;
}

if ($search_query) {
    $where_conditions[] = "(ar.description LIKE ? OR a.name LIKE ? OR p.name LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

// Combine WHERE conditions
if (!empty($where_conditions)) {
    if ($user_role === 'super_admin' || $user_role === 'coordinator') {
        $base_sql .= " WHERE " . implode(" AND ", $where_conditions);
    } else {
        $base_sql .= " AND " . implode(" AND ", $where_conditions);
    }
}

$base_sql .= " ORDER BY ar.created_at DESC";

// Execute query
$stmt = $conn->prepare($base_sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Get filter options - Projects
if ($user_role === 'super_admin' || $user_role === 'coordinator') {
    $projects_stmt = $conn->prepare("SELECT id, name FROM projects ORDER BY name");
    $projects_stmt->execute();
} else {
    $user_partner_id = $_SESSION['partner_id'] ?? 0;
    $projects_stmt = $conn->prepare("
        SELECT DISTINCT p.id, p.name 
        FROM projects p 
        JOIN project_partners pp ON p.id = pp.project_id 
        WHERE pp.partner_id = ? 
        ORDER BY p.name
    ");
    $projects_stmt->execute([$user_partner_id]);
}
$available_projects = $projects_stmt->fetchAll();

// Get activities for create form
if ($user_role === 'super_admin') {
    $activities_stmt = $conn->prepare("
        SELECT a.id, a.name, a.activity_number, wp.wp_number, p.name as project_name,
               CASE WHEN ar.id IS NOT NULL THEN 1 ELSE 0 END as has_report
        FROM activities a
        JOIN work_packages wp ON a.work_package_id = wp.id
        JOIN projects p ON wp.project_id = p.id
        LEFT JOIN activity_reports ar ON a.id = ar.activity_id AND ar.partner_id = ?
        WHERE a.status != 'not_started'
        ORDER BY p.name, wp.wp_number, a.activity_number
    ");
    $activities_stmt->execute([$user_id]);
} else {
    $user_partner_id = $_SESSION['partner_id'] ?? 0;
    $activities_stmt = $conn->prepare("
        SELECT a.id, a.name, a.activity_number, wp.wp_number, p.name as project_name,
               CASE WHEN ar.id IS NOT NULL THEN 1 ELSE 0 END as has_report
        FROM activities a
        JOIN work_packages wp ON a.work_package_id = wp.id
        JOIN projects p ON wp.project_id = p.id
        JOIN project_partners pp ON p.id = pp.project_id
        LEFT JOIN activity_reports ar ON a.id = ar.activity_id AND ar.partner_id = ?
        WHERE pp.partner_id = ? AND a.status != 'not_started'
        ORDER BY p.name, wp.wp_number, a.activity_number
    ");
    $activities_stmt->execute([$user_partner_id, $user_partner_id]);
}
$available_activities = $activities_stmt->fetchAll();

// Get partners for filter (admin/coordinator only)
$available_partners = [];
if ($user_role === 'super_admin' || $user_role === 'coordinator') {
    $partners_stmt = $conn->prepare("
        SELECT DISTINCT p.id, p.name
        FROM partners p
        JOIN activity_reports ar ON p.id = ar.partner_id
        ORDER BY p.name
    ");
    $partners_stmt->execute();
    $available_partners = $partners_stmt->fetchAll();
}

// Report statistics
$stats = [
    'total' => count($reports)
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
    
    <!-- STATISTICS -->
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="row">
                        <div class="col-5 col-md-4">
                            <div class="icon-big text-center icon-warning">
                                <i class="nc-icon nc-chart-bar-32 text-primary"></i>
                            </div>
                        </div>
                        <div class="col-7 col-md-8">
                            <div class="numbers">
                                <p class="card-category">Total Reports</p>
                                <p class="card-title"><?= $stats['total'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ACTIONS AND FILTERS -->
    <div class="row">
        <div class="col-12">
            <div class="filter-card">
                <!-- Action Buttons -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <a href="create-report.php" class="create-report-btn">
                            <i class="nc-icon nc-simple-add"></i>
                            Create New Report
                        </a>
                    </div>
                    
                    <div class="d-flex align-items-center">
                        <div class="view-toggle mr-3">
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
                        
                        <small class="text-muted">
                            Showing <?= count($reports) ?> reports
                        </small>
                    </div>
                </div>
                
                <!-- Filters Form -->
                <form method="GET" action="" id="filterForm">
                    <input type="hidden" name="view" value="<?= $view_mode ?>">
                    
                    <div class="row">
                        <div class="col-md-3">
                            <label>
                                <i class="nc-icon nc-zoom-split"></i>
                                Search Reports
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   name="search" 
                                   placeholder="Search reports..." 
                                   value="<?= htmlspecialchars($search_query) ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label>
                                <i class="nc-icon nc-briefcase-24"></i>
                                Project
                            </label>
                            <select class="form-control" name="project">
                                <option value="">All Projects</option>
                                <?php foreach($available_projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>" <?= $project_filter == $proj['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($proj['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ($user_role === 'super_admin' || $user_role === 'coordinator'): ?>
                        <div class="col-md-2">
                            <label>
                                <i class="nc-icon nc-single-02"></i>
                                Partner
                            </label>
                            <select class="form-control" name="partner">
                                <option value="">All Partners</option>
                                <?php foreach($available_partners as $partner): ?>
                                <option value="<?= $partner['id'] ?>" <?= $partner_filter == $partner['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($partner['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-2">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="nc-icon nc-zoom-split"></i>
                                Filter
                            </button>
                        </div>
                        
                        <div class="col-md-1">
                            <label>&nbsp;</label>
                            <a href="reports.php" class="btn btn-outline-secondary btn-block">
                                <i class="nc-icon nc-simple-remove"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- REPORTS DISPLAY -->
    <?php if (empty($reports)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="text-center py-5">
                        <i class="nc-icon nc-chart-bar-32" style="font-size: 64px; color: #dee2e6;"></i>
                        <h4>No Reports Found</h4>
                        <p class="text-muted">
                            <?php if ($search_query || $project_filter || $partner_filter): ?>
                                No reports match your current filters. Try adjusting your search criteria.
                            <?php else: ?>
                                No activity reports have been created yet.
                            <?php endif; ?>
                        </p>
                        
                        <?php if ($search_query || $project_filter || $partner_filter): ?>
                        <a href="reports.php" class="btn btn-primary">
                            <i class="nc-icon nc-simple-remove"></i>
                            Clear Filters
                        </a>
                        <?php else: ?>
                        <a href="create-report.php" class="btn btn-primary">
                            <i class="nc-icon nc-simple-add"></i>
                            Create Your First Report
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
        <?php foreach($reports as $report): ?>
        <?php
        $days_since = floor((time() - strtotime($report['created_at'])) / (60 * 60 * 24));
        ?>
        
        <div class="col-lg-6 col-xl-4">
            <div class="card report-card">
                <div class="card-body">
                    <!-- Report Header -->
                    <div class="report-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">
                                    <i class="nc-icon nc-chart-bar-32 text-primary"></i>
                                    Report #<?= $report['id'] ?>
                                </h6>
                                <small class="text-muted">
                                    <i class="nc-icon nc-briefcase-24"></i>
                                    <?= htmlspecialchars($report['project_name']) ?>
                                </small><br>
                                <small class="text-info">
                                    <i class="nc-icon nc-paper"></i>
                                    <?= htmlspecialchars($report['wp_number']) ?> - <?= htmlspecialchars($report['activity_name']) ?>
                                </small>
                            </div>
                            <div class="text-right">
                                
                                <div class="mt-1">
                                    <small class="text-muted">
                                        <?= $days_since == 0 ? 'Today' : $days_since . ' days ago' ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Report Body -->
                    <div class="report-body">
                        <p class="text-muted mb-3" style="font-size: 14px;">
                            <?= htmlspecialchars(substr($report['description'], 0, 120)) ?>
                            <?= strlen($report['description']) > 120 ? '...' : '' ?>
                        </p>
                        
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Report Date</small><br>
                                <strong><?= formatDate($report['report_date']) ?></strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Partner</small><br>
                                <strong><?= htmlspecialchars($report['partner_org']) ?></strong>
                            </div>
                        </div>
                        
                        <?php if ($report['coordinator_feedback']): ?>
                        <div class="mt-3">
                            <small class="text-muted">Coordinator Feedback:</small>
                            <p class="mb-0" style="font-size: 13px; font-style: italic;">
                                "<?= htmlspecialchars($report['coordinator_feedback']) ?>"
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Report Footer -->
                    <div class="report-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">
                                    <i class="nc-icon nc-world-2"></i>
                                    <?= htmlspecialchars($report['partner_org']) ?> (<?= htmlspecialchars($report['partner_country']) ?>)
                                </small>
                            </div>
                            
                            <div>
                                <button class="btn btn-primary btn-sm" onclick="viewReport(<?= $report['id'] ?>)">
                                    <i class="nc-icon nc-zoom-split"></i>
                                    View
                                </button>
                            </div>
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
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Report ID</th>
                                    <th>Activity</th>
                                    <th>Partner</th>
                                    <th>Report Date</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($reports as $report): ?>
                                <tr>
                                    <td>
                                        <strong>#<?= $report['id'] ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($report['project_name']) ?></strong><br>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($report['wp_number']) ?> - <?= htmlspecialchars($report['activity_name']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($report['partner_org']) ?></strong>
                                    </td>
                                    <td>
                                        <?= formatDate($report['report_date']) ?>
                                    </td>
                                    <td>
                                        <?= formatDateTime($report['created_at']) ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="viewReport(<?= $report['id'] ?>)">
                                            <i class="nc-icon nc-zoom-split"></i>
                                            View
                                        </button>
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


    

    <!-- Pass user role to JavaScript -->
<script>
    const currentUserRole = "<?= htmlspecialchars($user_role, ENT_QUOTES, 'UTF-8') ?>";
</script>

<!-- VIEW REPORT MODAL -->
    <div class="modal fade" id="viewReportModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="nc-icon nc-zoom-split"></i>
                        Report Details
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                
                <div class="modal-body" id="reportDetailsContent">
                    <!-- Content loaded via JavaScript -->
                </div>
                
                <div class="modal-footer">
                    <a href="#" id="modal-edit-button" class="btn btn-warning" style="display: none;"><i class="nc-icon nc-ruler-pencil"></i> Edit</a>
                    <a href="#" id="modal-delete-button" class="btn btn-danger" style="display: none;"><i class="nc-icon nc-simple-delete"></i> Delete</a>
                    <button type="button" class="btn btn-secondary ml-auto" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    

    <!-- Core JS Files -->
    <script src="../assets/js/core/jquery.min.js"></script>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>
    <script src="../assets/js/plugins/perfect-scrollbar.jquery.min.js"></script>
    <script src="../assets/js/paper-dashboard.min.js?v=2.0.1" type="text/javascript"></script>
    
    <!-- Custom JS for Reports page -->
    <script src="../assets/js/pages/reports.js"></script>
</body>
</html>