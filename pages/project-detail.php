<?php
// ===================================================================
//  PROJECT DETAIL PAGE
// ===================================================================
// This page provides a comprehensive overview of a single project,
// including its work packages, partners, milestones, and activities.
// ===================================================================

// ===================================================================
//  INCLUDES & SESSION
// ===================================================================
require_once '../includes/header.php';

// ===================================================================
//  CSRF TOKEN GENERATION
// ===================================================================
// Generate a CSRF token to protect against cross-site request forgery attacks.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ===================================================================
//  AUTHENTICATION & AUTHORIZATION
// ===================================================================

$auth = new Auth();
$auth->requireLogin();

$user_id = getUserId();
$user_role = getUserRole();

// ===================================================================
//  FUNZIONI HELPER PER L'HEADER OTTIMIZZATO
// ===================================================================

/**
 * Calcola i giorni rimanenti alla fine del progetto
 */
function getDaysRemaining($end_date) {
    if (empty($end_date)) return 0;
    
    $today = new DateTime();
    $end = new DateTime($end_date);
    
    if ($end < $today) {
        return 0; // Progetto terminato
    }
    
    return $today->diff($end)->days;
}

/**
 * Restituisce la classe CSS per i giorni rimanenti
 */
function getDaysRemainingClass($days) {
    if ($days <= 30) {
        return 'danger'; // Rosso - meno di 30 giorni
    } elseif ($days <= 180) {
        return 'warning'; // Giallo - meno di 6 mesi
    } else {
        return 'safe'; // Verde - più di 6 mesi
    }
}

/**
 * Calcola la percentuale di tempo trascorso del progetto
 */
function getTimeProgressPercentage($start_date, $end_date) {
    if (empty($start_date) || empty($end_date)) return 0;
    
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $today = new DateTime();
    
    if ($today < $start) return 0; // Non ancora iniziato
    if ($today > $end) return 100; // Terminato
    
    $total_days = $start->diff($end)->days;
    $elapsed_days = $start->diff($today)->days;
    
    return $total_days > 0 ? round(($elapsed_days / $total_days) * 100) : 0;
}

/**
 * Formatta il nome del programma per la visualizzazione
 */
function formatProgramName($program_type) {
    $programs = [
        'erasmus_plus' => 'Erasmus+',
        'horizon_europe' => 'Horizon Europe',
        'interreg' => 'Interreg',
        'life' => 'LIFE Programme',
        'creative_europe' => 'Creative Europe',
        'eu_citizenship' => 'Europe for Citizens',
        'digital_europe' => 'Digital Europe',
        'cerv' => 'CERV Programme',
        'other' => 'Other EU Programme'
    ];
    
    return $programs[$program_type] ?? ucfirst(str_replace('_', ' ', $program_type));
}


// ===================================================================
//  DATABASE & DATA FETCHING
// ===================================================================

$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$project_id) {
$_SESSION['error'] = 'No project ID specified.';
    header('Location: projects.php');
    exit;
}

$database = new Database();
$conn = $database->connect();

// Authorization Check: User must be a super_admin or a partner in the project.
if ($user_role !== 'super_admin') {
    $user_partner_id = $_SESSION['partner_id'] ?? 0;
    $access_stmt = $conn->prepare("SELECT COUNT(*) FROM project_partners WHERE project_id = ? AND partner_id = ?");
    $access_stmt->execute([$project_id, $user_partner_id]);
    if ($access_stmt->fetchColumn() == 0) {
$_SESSION['error'] = 'You do not have permission to view this project.';
        header('Location: projects.php');
        exit;
    }
}

// Fetch all project-related data in a series of queries.
// Project Details
$project_stmt = $conn->prepare("
    SELECT p.*, u.full_name as coordinator_name, u.email as coordinator_email, partner.name as coordinator_org, google_groups_url
    FROM projects p 
    LEFT JOIN users u ON p.coordinator_id = u.id
    LEFT JOIN partners partner ON u.partner_id = partner.id
    WHERE p.id = ?
");
$project_stmt->execute([$project_id]);
$project = $project_stmt->fetch();

if (!$project) {
$_SESSION['error'] = 'The requested project could not be found.';
    header('Location: projects.php');
    exit;
}

// Work Packages
$wp_stmt = $conn->prepare("
    SELECT wp.*, u.full_name as lead_partner_name, partner.name as lead_partner_org,
           COUNT(a.id) as activity_count
    FROM work_packages wp
    LEFT JOIN users u ON wp.lead_partner_id = u.id
    LEFT JOIN partners partner ON u.partner_id = partner.id
    LEFT JOIN activities a ON wp.id = a.work_package_id
    WHERE wp.project_id = ? GROUP BY wp.id ORDER BY wp.wp_number ASC
");
$wp_stmt->execute([$project_id]);
$work_packages = $wp_stmt->fetchAll();

// Partners
$partners_stmt = $conn->prepare("
    SELECT p.name, p.organization_type, p.country, pp.role, pp.budget_allocated
    FROM project_partners pp JOIN partners p ON pp.partner_id = p.id
    WHERE pp.project_id = ? ORDER BY pp.role DESC, p.name ASC
");
$partners_stmt->execute([$project_id]);
$partners = $partners_stmt->fetchAll();

// Milestones
$milestones_stmt = $conn->prepare("
    SELECT m.*, wp.name as wp_name, wp.wp_number
    FROM milestones m LEFT JOIN work_packages wp ON m.work_package_id = wp.id
    WHERE m.project_id = ? ORDER BY m.due_date ASC
");
$milestones_stmt->execute([$project_id]);
$milestones = $milestones_stmt->fetchAll();

// Recent Activities
$activities_stmt = $conn->prepare("
    SELECT a.*, wp.name as wp_name, wp.wp_number, u.full_name as responsible_name
    FROM activities a JOIN work_packages wp ON a.work_package_id = wp.id
    LEFT JOIN users u ON a.responsible_partner_id = u.id
    WHERE wp.project_id = ? ORDER BY a.end_date ASC, a.created_at DESC LIMIT 10
");
$activities_stmt->execute([$project_id]);
$recent_activities = $activities_stmt->fetchAll();

// Calculate Overall Project Progress
// Set Overall Project Progress to 0 since progress field was removed
$overall_progress = 0;

// ===================================================================
//  PAGE-SPECIFIC VARIABLES
// ===================================================================

$page_title = htmlspecialchars($project['name']) . ' - Project Details';
$page_css_path = '../assets/css/pages/project-detail.css';
$page_js_path = '../assets/js/pages/project-detail.js';

// ===================================================================
//  START HTML LAYOUT
// ===================================================================

include '../includes/header.php';
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
                
                <!-- BACK BUTTON -->
                <div class="row">
                    <div class="col-12 mb-3">
               <a href="projects.php" class="back-button-optimized">
                            <i class="nc-icon nc-minimal-left"></i>
                            Back to Projects
                        </a>
                    </div>
                </div>
                
               <!-- PROJECT HEADER OTTIMIZZATO -->
                <?php 
                    $days_remaining = getDaysRemaining($project['end_date']);
                    $days_class = getDaysRemainingClass($days_remaining);
                    $time_progress = getTimeProgressPercentage($project['start_date'], $project['end_date']);
                    $program_name = formatProgramName($project['program_type']);
                ?>
                <div class="row">
                    <div class="col-12">
                        <div class="optimized-header">
                            <!-- Top accent bar -->
                            <div class="header-top-bar status-<?= strtolower($project['status']) ?>"></div>
                            
                            <div class="header-content">
                                <!-- Info principale progetto -->
                                <div class="project-info">
                                    <h1 class="project-title-optimized">
                                        <i class="nc-icon nc-badge"></i>
                                        <?= htmlspecialchars($project['name']) ?>
                                    </h1>
                                    
                                    <p class="project-subtitle">
                                        <?= htmlspecialchars($project['description']) ?>
                                    </p>
                                    
                                    <!-- Meta informazioni in riga -->
                                    <div class="project-meta-row">
                                        <div class="meta-item">
                                            <i class="nc-icon nc-single-02"></i>
                                            <span>Coordinator: <strong><?= htmlspecialchars($project['coordinator_name'] ?? 'Not assigned') ?></strong></span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="nc-icon nc-world-2"></i>
                                            <span><strong><?= count($partners) ?></strong> Partners</span>
                                        </div>
                                        <div class="program-badge-optimized">
                                            <i class="nc-icon nc-album-2"></i>
                                            <?= $program_name ?>
                                        </div>
                                        <div class="status-badge-optimized status-<?= strtolower($project['status']) ?>">
                                            <?= ucfirst($project['status']) ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Progress bar compatta -->
                                    <div class="progress-indicator-detail">
                                        <div class="progress-bar-detail" style="width: <?= $time_progress ?>%"></div>
                                    </div>
                                    <div class="progress-text">Project Timeline Progress (<?= $time_progress ?>% completed)</div>
                                </div>
                                
                                <!-- Stats e budget -->
                                <div class="header-stats">
                                    <?php if ($project['budget']): ?>
                                    <div class="budget-compact">€<?= number_format($project['budget'], 0, ',', '.') ?></div>
                                    <?php endif; ?>
                                    <div class="timeline-compact">
                                        <div><strong>Start:</strong> <?= formatDate($project['start_date']) ?></div>
                                        <div><strong>End:</strong> <?= formatDate($project['end_date']) ?></div>
                                    </div>
                                    <?php if ($days_remaining > 0): ?>
                                    <div class="days-remaining-compact <?= $days_class ?>">
                                        <div><?= $days_remaining ?></div>
                                        <div style="font-size: 10px;">Days Remaining</div>
                                    </div>
                                    <?php else: ?>
                                    <div class="days-remaining-compact completed">
                                        <div>Completed</div>
                                        <div style="font-size: 10px;">Project Ended</div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MAIN CONTENT TABS -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <ul class="nav nav-tabs card-header-tabs" id="projectTabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="overview-tab" data-toggle="tab" href="#overview" role="tab">
                                            <i class="nc-icon nc-zoom-split"></i>
                                            Overview
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="workpackages-tab" data-toggle="tab" href="#workpackages" role="tab">
                                            <i class="nc-icon nc-layers-3"></i>
                                            Work Packages (<?= count($work_packages) ?>)
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="partners-tab" data-toggle="tab" href="#partners" role="tab">
                                            <i class="nc-icon nc-world-2"></i>
                                            Partners (<?= count($partners) ?>)
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="milestones-tab" data-toggle="tab" href="#milestones" role="tab">
                                            <i class="nc-icon nc-trophy"></i>
                                            Milestones (<?= count($milestones) ?>)
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="card-body">
                                <div class="tab-content" id="projectTabContent">
                                    
                                    <!-- OVERVIEW TAB -->
                                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                                        <div class="row">
                                            <!-- Project Info -->
                                            <div class="col-md-8">
                                                <h5 class="section-title">
                                                    <i class="nc-icon nc-paper"></i>
                                                    Project Information
                                                </h5>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p><strong>Coordinator:</strong><br>
                                                        <?= htmlspecialchars($project['coordinator_name'] ?? 'Not assigned') ?><br>
                                                        <small class="text-muted"><?= htmlspecialchars($project['coordinator_org'] ?? '') ?></small></p>
                                                        
                                                        <p><strong>Program Type:</strong><br>
                                                        <?= htmlspecialchars($project['program_type']) ?></p>
                                                        
                                                        <p><strong>Status:</strong><br>
                                                        <?= getStatusBadge($project['status']) ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p><strong>Duration:</strong><br>
                                                        <?= formatDate($project['start_date']) ?> - <?= formatDate($project['end_date']) ?></p>
                                                        
                                                        <?php if ($project['budget']): ?>
                                                        <p><strong>Budget:</strong><br>
                                                        <span class="budget-display" style="font-size: 18px;">€<?= number_format($project['budget'], 0, ',', '.') ?></span></p>
                                                        <?php endif; ?>
                                                        
                                                        <p><strong>Partners:</strong><br>
                                                        <?= count($partners) ?> organizations from <?= count(array_unique(array_column($partners, 'country'))) ?> countries</p>
                                                    </div>
                                                </div>                                              
                                                
                                                <!-- Recent Activities -->
                                                <h5 class="section-title mt-4">
                                                    <i class="nc-icon nc-time-alarm"></i>
                                                    Recent Activities
                                                </h5>
                                                
                                                <div class="card">
                                                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                                        <?php if (empty($recent_activities)): ?>
                                                        <p class="text-muted text-center py-3">No activities found for this project.</p>
                                                        <?php else: ?>
                                                        <?php foreach($recent_activities as $activity): ?>
                                                        <div class="activity-item">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <h6 class="mb-1"><?= htmlspecialchars($activity['name']) ?></h6>
                                                                    <small class="text-muted">
                                                                        <?= htmlspecialchars($activity['wp_number']) ?> - <?= htmlspecialchars($activity['wp_name']) ?>
                                                                    </small><br>
                                                                    <small class="text-info">
                                                                        Responsible: <?= htmlspecialchars($activity['responsible_name'] ?? 'Not assigned') ?>
                                                                    </small>
                                                                </div>
                                                                <div class="text-right">
                                                                    <?= getStatusBadge($activity['status']) ?>
                                                                    <?php if ($activity['end_date']): ?>
                                                                    <div class="timeline-date">
                                                                        Due: <?= formatDate($activity['end_date']) ?>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Quick Actions -->
                                            <div class="col-md-4">
                                                <h5 class="section-title">
                                                    <i class="nc-icon nc-settings-gear-65"></i>
                                                    Quick Actions
                                                </h5>
                                                
                                                <div class="list-group">
                                                    <a href="activities.php?project=<?= $project['id'] ?>" 
                                                       class="list-group-item list-group-item-action">
                                                        <i class="nc-icon nc-paper text-primary"></i>
                                                        View All Activities
                                                    </a>
                                                    
                                                    <a href="reports.php?project=<?= $project['id'] ?>" 
                                                       class="list-group-item list-group-item-action">
                                                        <i class="nc-icon nc-chart-bar-32 text-success"></i>
                                                        Project Reports
                                                    </a>
                                                    
                                                    <a href="project-gantt.php?id=<?= $project['id'] ?>" 
                                                       class="list-group-item list-group-item-action">
                                                        <i class="nc-icon nc-calendar-60 text-warning"></i>
                                                        Project Timeline
                                                    </a>
<!-- Google groups link -->

<?php if (!empty($project['google_groups_url'])): ?>
    <a href="<?= htmlspecialchars($project['google_groups_url']) ?>" 
       target="_blank"
       class="list-group-item list-group-item-action">
        <i class="nc-icon nc-chat-33 text-info"></i>
        Project Discussions
        <i class="nc-icon nc-minimal-right float-right mt-1" style="font-size: 12px;"></i>
    </a>
<?php endif; ?>

<?php 
// Se sei admin e non c'è Google Groups configurato, mostra link per configurare
if (empty($project['google_groups_url']) && in_array($_SESSION['role'], ['super_admin', 'coordinator', 'admin'])): 
?>
    <a href="project-edit.php?id=<?= $project_id ?>#google_groups_url" 
       class="list-group-item list-group-item-action list-group-item-light">
        <i class="nc-icon nc-simple-add text-muted"></i>
        <span class="text-muted">Add Project Discussions</span>
    </a>
<?php endif; ?>                                 
                                                       <?php if ($user_role === 'super_admin' || $user_role === 'coordinator'): ?>
                                                       <a href="manage-project-risks.php?project_id=<?= $project['id'] ?>" 
                                                       class="list-group-item list-group-item-action">
                                                        <i class="nc-icon nc-alert-circle-i text-danger"></i>
                                                        Manage Project Risks
                                                    </a>
                                                    <?php endif; ?>
                                                    <?php if ($user_role === 'super_admin' || $user_role === 'coordinator'): ?>
                                                    <a href="project-edit.php?id=<?= $project['id'] ?>" 
                                                       class="list-group-item list-group-item-action">
                                                        <i class="nc-icon nc-settings text-info"></i>
                                                        Edit Project
                                                    </a>
                                                    <a href="javascript:void(0)" 
   class="list-group-item list-group-item-action text-danger" 
   onclick="confirmDeleteProject(<?= $project['id'] ?>, '<?= addslashes(htmlspecialchars($project['name'])) ?>', '<?= $csrf_token ?>')">
    <i class="nc-icon nc-simple-remove text-danger"></i>
    Delete Project
</a>
                                                    <?php endif; ?>                                               </div>

                                                
                                           
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- WORK PACKAGES TAB -->
                                    <div class="tab-pane fade" id="workpackages" role="tabpanel">
                                        <h5 class="section-title">
                                            <i class="nc-icon nc-layers-3"></i>
                                            Work Packages Overview
                                        </h5>
                                        
                                        <?php if (empty($work_packages)): ?>
                                        <div class="text-center py-5">
                                            <i class="nc-icon nc-folder" style="font-size: 48px; color: #ccc;"></i>
                                            <p class="text-muted">No work packages defined for this project yet.</p>
                                        </div>
                                        <?php else: ?>
                                        
                                        <div class="row">
                                            <?php foreach($work_packages as $wp): ?>
                                            <div class="col-md-6 col-lg-4">
                                                <div class="wp-card card">
                                                    <div class="wp-header">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div>
                                                                <h5 class="mb-1">
                                                                    <i class="nc-icon nc-layers-3 text-info"></i>
                                                                    <?= htmlspecialchars($wp['wp_number']) ?>
                                                                </h5>
                                                                <p class="mb-2" style="font-size: 14px; font-weight: 600;">
                                                                    <?= htmlspecialchars($wp['name']) ?>
                                                                </p>
                                                                <small class="text-muted">
                                                                    Lead: <?= htmlspecialchars($wp['lead_partner_name'] ?? 'Not assigned') ?>
                                                                </small>
                                                            </div>
                                                            
                                                        </div>
                                                    </div>
                                                    <div class="card-body">
                                                        <p class="text-muted mb-3" style="font-size: 13px;">
                                                            <?= htmlspecialchars(substr($wp['description'], 0, 100)) ?>
                                                            <?= strlen($wp['description']) > 100 ? '...' : '' ?>
                                                        </p>
                                                        
                                                        <div class="row text-center">
                                                            <div class="col-6">
                                                                <small class="text-muted">Activities</small><br>
                                                                <strong><?= $wp['activity_count'] ?></strong>
                                                            </div>
                                                            <div class="col-6">
                                                                <small class="text-muted">Status</small><br>
                                                                <?= getStatusBadge($wp['status']) ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if ($wp['budget']): ?>
                                                        <div class="text-center mt-2">
                                                            <small class="text-success">
                                                                <i class="nc-icon nc-money-coins"></i>
                                                                €<?= number_format($wp['budget'], 0, ',', '.') ?>
                                                            </small>
                                                        </div>
                                                        <?php endif; ?>
                                                        
                                                        <div class="text-center mt-3">
                                                            <a href="activities.php?wp=<?= $wp['id'] ?>" 
                                                               class="btn btn-primary btn-sm">
                                                                <i class="nc-icon nc-paper"></i>
                                                                View Activities
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- PARTNERS TAB -->
                                    <div class="tab-pane fade" id="partners" role="tabpanel">
                                        <h5 class="section-title">
                                            <i class="nc-icon nc-world-2"></i>
                                            Project Consortium
                                        </h5>
                                        
                                        <div class="row">
                                            <?php foreach($partners as $partner): ?>
                                            <div class="col-md-6 col-lg-4">
                                                <div class="partner-card <?= $partner['role'] === 'coordinator' ? 'border-primary bg-light' : '' ?>">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="mb-1">
                                                            <i class="nc-icon nc-bank text-primary"></i>
                                                            <?= htmlspecialchars($partner['name']) ?>
                                                        </h6>
                                                        <?php if ($partner['role'] === 'coordinator'): ?>
                                                        <span class="badge badge-primary">Lead Partner</span>
                                                        <?php else: ?>
                                                        <span class="badge badge-secondary">Partner</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <p class="mb-2" style="font-size: 14px; font-weight: 500;">
                                                        <i class="nc-icon nc-money-coins text-success"></i>
                                                        Budget: €<?= number_format($partner['budget_allocated'], 2) ?>
                                                    </p>
                                                    
                                                    <p class="mb-0">
                                                        <small class="text-muted">
                                                            <i class="nc-icon nc-world-2"></i>
                                                            <?= htmlspecialchars($partner['country']) ?>
                                                        </small>
                                                    </p>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- MILESTONES TAB -->
                                    <div class="tab-pane fade" id="milestones" role="tabpanel">
                                        <h5 class="section-title">
                                            <i class="nc-icon nc-trophy"></i>
                                            Project Milestones
                                        </h5>
                                        
                                        <?php if (empty($milestones)): ?>
                                        <div class="text-center py-5">
                                            <i class="nc-icon nc-trophy" style="font-size: 48px; color: #ccc;"></i>
                                            <p class="text-muted">No milestones defined for this project yet.</p>
                                        </div>
                                        <?php else: ?>
                                        
                                        <div class="row">
                                            <div class="col-12">
                                                <?php foreach($milestones as $milestone): ?>
                                                <div class="milestone-item <?= $milestone['status'] ?>">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="mb-1">
                                                                <i class="nc-icon nc-trophy"></i>
                                                                <?= htmlspecialchars($milestone['name']) ?>
                                                            </h6>
                                                            <p class="mb-2 text-muted" style="font-size: 14px;">
                                                                <?= htmlspecialchars($milestone['description']) ?>
                                                            </p>
                                                            <?php if ($milestone['wp_name']): ?>
                                                            <small class="text-info">
                                                                <i class="nc-icon nc-layers-3"></i>
                                                                Related to: <?= htmlspecialchars($milestone['wp_number']) ?> - <?= htmlspecialchars($milestone['wp_name']) ?>
                                                            </small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-right">
                                                            <?= getStatusBadge($milestone['status']) ?>
                                                            <div class="timeline-date mt-1">
                                                                <i class="nc-icon nc-calendar-60"></i>
                                                                Due: <?= formatDate($milestone['due_date']) ?>
                                                            </div>
                                                            <?php if ($milestone['completed_date']): ?>
                                                            <div class="timeline-date text-success">
                                                                <i class="nc-icon nc-check-2"></i>
                                                                Completed: <?= formatDate($milestone['completed_date']) ?>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

       <?php include '../includes/footer.php'; ?>