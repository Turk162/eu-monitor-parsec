<?php
// ===================================================================
//  PROJECTS LISTING PAGE
// ===================================================================
// This page displays a list of projects accessible to the current user.
// It supports filtering by status, program type, and a search query.
// ===================================================================

// ===================================================================
//  PAGE CONFIGURATION & INCLUDES
// ===================================================================

$page_title = 'My Projects - EU Project Manager';
$page_css_path = '../assets/css/pages/projects.css';
$page_js_path = '../assets/js/pages/projects.js';

require_once '../includes/header.php';

// ===================================================================
//  FUNZIONI HELPER PER LE PROJECT CARD OTTIMIZZATE
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
 * Restituisce la classe CSS per il gradient del header basato sullo status
 */
function getHeaderStatusClass($status) {
    switch (strtolower($status)) {
        case 'active':
            return 'status-active';
        case 'planning':
            return 'status-planning';
        case 'completed':
            return 'status-completed';
        case 'suspended':
            return 'status-suspended';
        default:
            return '';
    }
}

/**
 * Formatta il budget in versione compatta
 */
function formatBudgetCompact($budget) {
    if ($budget >= 1000000) {
        return '€' . number_format($budget / 1000000, 1) . 'M';
    } elseif ($budget >= 1000) {
        return '€' . number_format($budget / 1000, 0) . 'K';
    } else {
        return '€' . number_format($budget, 0);
    }
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

$database = new Database();
$conn = $database->connect();

// Get current user's info
$user_id = getUserId();
$user_role = getUserRole();

// --- Filtering Logic ---
$status_filter = $_GET['status'] ?? '';
$program_filter = $_GET['program'] ?? '';
$search_query = trim($_GET['search'] ?? '');

// --- Main Query Construction ---
$params = [];
$where_conditions = [];

// The base query joins projects with partners and work packages to get aggregate data.
$base_sql = "
    SELECT 
        p.*, 
        COUNT(DISTINCT pp.partner_id) as partner_count,
        COUNT(DISTINCT wp.id) as work_package_count,
        COUNT(DISTINCT a.id) as activity_count,
        AVG(wp.progress) as avg_progress,
        u.full_name as coordinator_name
    FROM projects p 
    LEFT JOIN project_partners pp ON p.id = pp.project_id
    LEFT JOIN work_packages wp ON p.id = wp.project_id
    LEFT JOIN activities a ON wp.id = a.work_package_id
    LEFT JOIN users u ON p.coordinator_id = u.id
";

// Role-based access: Super admins see all projects, others see only their own.
if ($user_role !== 'super_admin') {
    $user_partner_id = $_SESSION['partner_id'] ?? 0;
    // A sub-query is used to get projects where the user's partner is involved.
    $base_sql .= " WHERE p.id IN (SELECT project_id FROM project_partners WHERE partner_id = ?)";
    $params[] = $user_partner_id;
}

// Apply filters to the query
if ($status_filter) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}
if ($program_filter) {
    $where_conditions[] = "p.program_type = ?";
    $params[] = $program_filter;
}
if ($search_query) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
}

// Append WHERE clauses to the main query
if (!empty($where_conditions)) {
    $base_sql .= ($user_role === 'super_admin' ? ' WHERE ' : ' AND ') . implode(' AND ', $where_conditions);
}

$base_sql .= " GROUP BY p.id ORDER BY p.created_at DESC";

// Execute the final query
$stmt = $conn->prepare($base_sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();

// --- Data for Filters and Stats ---

// Get all unique program types for the filter dropdown
$program_types = $conn->query("SELECT DISTINCT program_type FROM projects WHERE program_type IS NOT NULL ORDER BY program_type")->fetchAll(PDO::FETCH_COLUMN);

// Get dashboard stats for the header cards
$stats = getDashboardStats($conn, $user_id, $user_role);

// ===================================================================
//  START HTML LAYOUT
// ===================================================================
?>

  <body class="">
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <?php include '../includes/navbar.php'; ?>

        <!-- CONTENT -->
        <div class="content">
                <!-- ALERT MESSAGE -->
                <?php displayAlert(); ?>
                
                <!-- PAGE HEADER WITH STATS -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-5 col-md-4">
                                        <div class="icon-big text-center icon-warning">
                                            <i class="nc-icon nc-briefcase-24 text-warning"></i>
                                        </div>
                                    </div>
                                    <div class="col-7 col-md-8">
                                        <div class="numbers">
                                            <p class="card-category">Total Projects</p>
                                            <p class="card-title"><?= count($projects) ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-5 col-md-4">
                                        <div class="icon-big text-center icon-warning">
                                            <i class="nc-icon nc-settings-gear-65 text-success"></i>
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
                        </div>
                    </div>
                    
                    <div class="col-md-3">
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
                                            <p class="card-category">Deadlines</p>
                                            <p class="card-title"><?= $stats['upcoming_deadlines'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-5 col-md-4">
                                        <div class="icon-big text-center icon-warning">
                                            <i class="nc-icon nc-chart-bar-32 text-info"></i>
                                        </div>
                                    </div>
                                    <div class="col-7 col-md-8">
                                        <div class="numbers">
                                            <p class="card-category">Completed</p>
                                            <p class="card-title"><?= $stats['completed_activities'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
<?php if (in_array($user_role, ['super_admin', 'coordinator'])): ?>
    <a href="create-project.php" class="btn btn-primary">
        <i class="nc-icon nc-simple-add"></i> New Project
    </a>
<?php endif; ?>
                <!-- FILTERS AND SEARCH -->
                <div class="row">
                    <div class="col-12">
                        <div class="filter-card">
                            <form method="GET" action="" id="filterForm">
                                <div class="row align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label">
                                            <i class="nc-icon nc-zoom-split"></i>
                                            Search Projects
                                        </label>
                                        <input type="text" 
                                               class="form-control search-box" 
                                               name="search" 
                                               placeholder="Search by name or description..." 
                                               value="<?= htmlspecialchars($search_query) ?>">
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">
                                            <i class="nc-icon nc-settings-gear-65"></i>
                                            Status
                                        </label>
                                        <select class="form-control" name="status">
                                            <option value="">All Status</option>
                                            <option value="planning" <?= $status_filter === 'planning' ? 'selected' : '' ?>>Planning</option>
                                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">
                                            <i class="nc-icon nc-badge"></i>
                                            Program Type
                                        </label>
                                        <select class="form-control" name="program">
                                            <option value="">All Programs</option>
                                            <?php foreach($program_types as $program): ?>
                                            <option value="<?= htmlspecialchars($program) ?>" 
                                                    <?= $program_filter === $program ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($program) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="nc-icon nc-zoom-split"></i>
                                            Filter
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Quick Filter Badges -->
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <small class="text-muted">Quick filters:</small><br>
                                        <a href="?status=active" class="btn btn-outline-success btn-filter btn-sm">
                                            <i class="nc-icon nc-settings-gear-65"></i> Active Only
                                        </a>
                                        <a href="?program=Erasmus%2B" class="btn btn-outline-info btn-filter btn-sm">
                                            <i class="nc-icon nc-badge"></i> Erasmus+
                                        </a>
                                        <a href="?" class="btn btn-outline-secondary btn-filter btn-sm">
                                            <i class="nc-icon nc-simple-remove"></i> Clear All
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

              <!-- PROJECTS LIST -->
<?php foreach($projects as $project): 
    $days_remaining = getDaysRemaining($project['end_date']);
    $days_class = getDaysRemainingClass($days_remaining);
    $time_progress = getTimeProgressPercentage($project['start_date'], $project['end_date']);
    $header_class = getHeaderStatusClass($project['status']);
    $budget_compact = formatBudgetCompact($project['budget'] ?? 0);
    $program_name = formatProgramName($project['program_type']);
?>
<div class="col-lg-6 col-xl-4">
    <div class="project-card card">
        <!-- Progress indicator basato sul tempo trascorso -->
        <div class="progress-indicator">
            <div class="progress-bar-custom" style="width: <?= $time_progress ?>%"></div>
        </div>
        
        <!-- ========================================
             PROJECT HEADER SECTION
             Contains: Title, Program Type, Coordinator Info, Partners
             ======================================== -->
        <div class="project-header <?= $header_class ?>">
            <div class="project-title">
                <i class="nc-icon nc-badge"></i>
                <?= htmlspecialchars($project['name']) ?>
            </div>
            <div class="project-meta">
                <div class="program-badge">
                    <?= $program_name ?>
                </div>
                <div class="partners-count">
                    <i class="nc-icon nc-world-2"></i>
                    <?= $project['partner_count'] ?? 0 ?> Partners
                </div>
                <div class="coordinator-info">
                    <i class="nc-icon nc-single-02"></i>
                    <?= htmlspecialchars($project['coordinator_name'] ?? 'TBD') ?>
                </div>
            </div>
        </div>
        
        <!-- ========================================
             PROJECT BODY SECTION
             Contains: Description, Stats, Timeline
             ======================================== -->
        <div class="project-body">
            <!-- Project Description -->
            <p class="project-description">
                <?= htmlspecialchars(substr($project['description'] ?? '', 0, 150)) ?>
                <?= strlen($project['description'] ?? '') > 150 ? '...' : '' ?>
            </p>
            
            <!-- Stats compatte -->
            <div class="project-stats">
                <div class="stat-item">
                    <span class="stat-value days-remaining <?= $days_class ?>">
                        <?= $days_remaining ?>
                    </span>
                    <div class="stat-label">Days Left</div>
                </div>
                <div class="stat-item">
    <span class="stat-value">
        <?= $project['activity_count'] ?? 0 ?>
    </span>
    <div class="stat-label">Activities</div>
</div>
<div class="stat-item">
    <span class="stat-value">
        <?= $project['work_package_count'] ?? 0 ?>
    </span>
    <div class="stat-label">WPs</div>
</div>
                <div class="stat-item">
                    <span class="stat-value budget-display">
                        <?= $budget_compact ?>
                    </span>
                    <div class="stat-label">Budget</div>
                </div>
            </div>
            
            <!-- Timeline compatta -->
            <div class="project-timeline">
                <div>
                    <i class="nc-icon nc-calendar-60 text-success"></i>
                    <strong>Start:</strong> <?= date('d/m/Y', strtotime($project['start_date'])) ?>
                </div>
                <div>
                    <i class="nc-icon nc-check-2 text-<?= $days_remaining <= 30 ? 'danger' : 'primary' ?>"></i>
                    <strong>End:</strong> <?= date('d/m/Y', strtotime($project['end_date'])) ?>
                </div>
            </div>
        </div>
        
        <!-- ========================================
             PROJECT FOOTER SECTION
             Contains: Action Buttons
             ======================================== -->
        <div class="project-footer">
            <div class="action-buttons">
                <a href="reports.php?project_id=<?= $project['id'] ?>" class="btn-action btn-secondary-action">
                    <i class="nc-icon nc-chart-bar-32"></i> Reports
                </a>
                <a href="activities.php?project_id=<?= $project['id'] ?>" class="btn-action btn-secondary-action">
                    <i class="nc-icon nc-bullet-list-67"></i> Activities
                </a>
                <a href="project-detail.php?id=<?= $project['id'] ?>" class="btn-action btn-primary-action">
                    <i class="nc-icon nc-zoom-split"></i> Details
                </a>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- ===================================================================
     JAVASCRIPT PER AGGIORNAMENTO DINAMICO DEI COUNTDOWN
     =================================================================== -->
<script>
// Aggiorna i countdown ogni ora (3600000 ms)
setInterval(function() {
    // Ricarica la pagina per aggiornare i countdown
    // In una versione più avanzata, si potrebbe fare via AJAX
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 3600000);

// Funzione per formattare i giorni rimanenti con colori dinamici
document.addEventListener('DOMContentLoaded', function() {
    const daysElements = document.querySelectorAll('.days-remaining');
    
    daysElements.forEach(function(element) {
        const days = parseInt(element.textContent);
        
        // Rimuovi classi esistenti
        element.classList.remove('danger', 'warning', 'safe');
        
        // Aggiungi classe basata sui giorni
        if (days <= 30) {
            element.classList.add('danger');
        } else if (days <= 180) {
            element.classList.add('warning');
        } else {
            element.classList.add('safe');
        }
    });
});
</script>

        <!-- FOOTER -->

       <?php include '../includes/footer.php'; ?>
</body>
</html>