<?php
// ===================================================================
// CALENDAR - Page-specific configuration
// ===================================================================

// ===================================================================
//  PAGE CONFIGURATION
// ===================================================================

// Set the title of the page
$page_title = 'Calendar - EU Project Manager';

// Specify the path to the page-specific CSS file
$page_css_path = '../assets/css/pages/calendar.css';

// Specify the path to the page-specific JS file
$page_js_path = '../assets/js/pages/calendar.js';

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

// ===================================================================
//  CALENDAR DATA RETRIEVAL
// ===================================================================

// Get current month/year from URL parameters or default to current
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Get project filter from URL
$selected_projects = isset($_GET['projects']) ? $_GET['projects'] : [];
if (!is_array($selected_projects)) {
    $selected_projects = [$selected_projects];
}

// Ensure valid month/year
if ($current_month < 1 || $current_month > 12) {
    $current_month = date('n');
}
if ($current_year < 2020 || $current_year > 2030) {
    $current_year = date('Y');
}

// Get available projects based on user role
if ($user_role === 'super_admin') {
    $projects_stmt = $conn->prepare("
        SELECT p.id, p.name, p.status, 
               COUNT(DISTINCT pp.partner_id) as partner_count
        FROM projects p 
        LEFT JOIN project_partners pp ON p.id = pp.project_id
        GROUP BY p.id, p.name, p.status
        ORDER BY p.name
    ");
    $projects_stmt->execute();
} else {
    $user_partner_id = $_SESSION['partner_id'] ?? 0;
    $projects_stmt = $conn->prepare("
        SELECT p.id, p.name, p.status,
               COUNT(DISTINCT pp2.partner_id) as partner_count
        FROM projects p 
        JOIN project_partners pp ON p.id = pp.project_id
        LEFT JOIN project_partners pp2 ON p.id = pp2.project_id
        WHERE pp.partner_id = ?
        GROUP BY p.id, p.name, p.status
        ORDER BY p.name
    ");
    $projects_stmt->execute([$user_partner_id]);
}
$available_projects = $projects_stmt->fetchAll();

// If no projects selected, select all by default
if (empty($selected_projects) && !empty($available_projects)) {
    $selected_projects = array_column($available_projects, 'id');
}

// Calculate previous and next month for navigation
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $current_month + 1;
$next_year = $current_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Get calendar events and upcoming events based on user role and selected projects
$events = [];
$upcoming_events = [];

if (!empty($selected_projects)) {
    $project_placeholders = str_repeat('?,', count($selected_projects) - 1) . '?';

    // Base query parts
    $activity_select = "
        SELECT 
            'activity' as event_type, a.id, a.name as title, a.end_date as event_date, a.status,
            p.name as project_name, p.id as project_id, wp.name as wp_name, 'activity' as type_label
        FROM activities a 
        JOIN work_packages wp ON a.work_package_id = wp.id
        JOIN projects p ON wp.project_id = p.id";
    
    $milestone_select = "
        SELECT 
            'milestone' as event_type, m.id, m.name as title, m.due_date as event_date, m.status,
            p.name as project_name, p.id as project_id, wp.name as wp_name, 'milestone' as type_label
        FROM milestones m
        JOIN projects p ON m.project_id = p.id
        LEFT JOIN work_packages wp ON m.work_package_id = wp.id";

    $project_end_select = "
        SELECT 
            'project' as event_type, p.id, CONCAT('Project End: ', p.name) as title, p.end_date as event_date, p.status,
            p.name as project_name, p.id as project_id, NULL as wp_name, 'project_end' as type_label
        FROM projects p";

    // User-specific conditions
    $user_condition = '';
    $user_params = [];
    if ($user_role !== 'super_admin') {
        $user_partner_id = $_SESSION['partner_id'] ?? 0;
        $user_condition = " JOIN project_partners pp ON p.id = pp.project_id WHERE pp.partner_id = ? AND p.id IN ($project_placeholders)";
        $user_params = [$user_partner_id, ...$selected_projects];
    } else {
        $user_condition = " WHERE p.id IN ($project_placeholders)";
        $user_params = $selected_projects;
    }

    // Fetch calendar events for the selected month
    $events_sql = "
        ($activity_select $user_condition AND MONTH(a.end_date) = ? AND YEAR(a.end_date) = ?)
        UNION ALL
        ($milestone_select $user_condition AND MONTH(m.due_date) = ? AND YEAR(m.due_date) = ?)
        UNION ALL
        ($project_end_select $user_condition AND MONTH(p.end_date) = ? AND YEAR(p.end_date) = ?)
        ORDER BY event_date ASC";
    
    $stmt = $conn->prepare($events_sql);
    $params = array_merge($user_params, [$current_month, $current_year], 
                         $user_params, [$current_month, $current_year],
                         $user_params, [$current_month, $current_year]);
    $stmt->execute($params);
    $events = $stmt->fetchAll();

    // Fetch upcoming events for the next 30 days
    $upcoming_activity_select = str_replace("a.end_date as event_date", "a.end_date as event_date, DATEDIFF(a.end_date, CURDATE()) as days_until", $activity_select);
    $upcoming_milestone_select = str_replace("m.due_date as event_date", "m.due_date as event_date, DATEDIFF(m.due_date, CURDATE()) as days_until", $milestone_select);

    $upcoming_sql = "
        ($upcoming_activity_select $user_condition AND a.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND a.status != 'completed')
        UNION ALL
        ($upcoming_milestone_select $user_condition AND m.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND m.status != 'completed')
        ORDER BY event_date ASC LIMIT 10";

    $upcoming_stmt = $conn->prepare($upcoming_sql);
    $upcoming_params = array_merge($user_params, $user_params);
    $upcoming_stmt->execute($upcoming_params);
    $upcoming_events = $upcoming_stmt->fetchAll();
}

// Calculate calendar grid
$first_day = mktime(0, 0, 0, $current_month, 1, $current_year);
$days_in_month = date('t', $first_day);
$first_day_of_week = date('w', $first_day); // 0 = Sunday
$month_name = date('F', $first_day);

// Adjust for Monday start (European standard)
$first_day_of_week = ($first_day_of_week == 0) ? 6 : $first_day_of_week - 1;
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
            
            <!-- PAGE HEADER -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row align-items-center">
<div class="col-md-6">
    <h4 class="card-title mb-0">
        <i class="nc-icon nc-calendar-60 text-info"></i>
        Project Calendar
    </h4>
    <p class="card-text text-muted">
        View project deadlines, milestones and activities
    </p>
</div>
<div class="col-md-6 text-right">
                                    <div class="btn-group mb-2">
                                        <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>&<?= http_build_query(['projects' => $selected_projects]) ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="nc-icon nc-minimal-left"></i>
                                            Previous
                                        </a>
                                        <button class="btn btn-primary btn-sm" disabled>
                                            <?= $month_name ?> <?= $current_year ?>
                                        </button>
                                        <a href="?month=<?= $next_month ?>&year=<?= $next_year ?>&<?= http_build_query(['projects' => $selected_projects]) ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            Next
                                            <i class="nc-icon nc-minimal-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CALENDAR LEGEND -->
<div class="row">
    <div class="col-12">
        <div class="card legend-card">
            <div class="card-body py-3">
                <div class="d-flex justify-content-center align-items-center">
                    <span class="mr-3"><strong>Legend:</strong></span>
                    <div class="calendar-legend">
                        <span class="legend-item">
                            <span class="legend-color activity"></span>
                            Activities
                        </span>
                        <span class="legend-item">
                            <span class="legend-color milestone"></span>
                            Milestones
                        </span>
                        <span class="legend-item">
                            <span class="legend-color project"></span>
                            Project Ends
                        </span>
                        <span class="legend-separator">•</span>
                        <span class="legend-item">
                            <span class="legend-color completed"></span>
                            Completed
                        </span>
                        <span class="legend-item">
                            <span class="legend-color in_progress"></span>
                            In Progress
                        </span>
                        <span class="legend-item">
                            <span class="legend-color overdue"></span>
                            Overdue
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

            <!-- CALENDAR AND EVENTS -->
            <div class="row">
                <!-- CALENDAR GRID -->
                <div class="col-md-8">
                    <div class="card calendar-card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="nc-icon nc-calendar-60"></i>
                                <?= $month_name ?> <?= $current_year ?>
                            </h5>
                          <div class="selected-projects-info text-right">
    <small class="text-muted">
        <i class="nc-icon nc-briefcase-24"></i>
        Showing <?= count($selected_projects) ?> of <?= count($available_projects) ?> projects
    </small>
</div>
                        </div>
                        <div class="card-body p-0">
                            <div class="calendar-grid">
                                <!-- Calendar header with days -->
                                <div class="calendar-header">
                                    <div class="calendar-day-header">Mon</div>
                                    <div class="calendar-day-header">Tue</div>
                                    <div class="calendar-day-header">Wed</div>
                                    <div class="calendar-day-header">Thu</div>
                                    <div class="calendar-day-header">Fri</div>
                                    <div class="calendar-day-header">Sat</div>
                                    <div class="calendar-day-header">Sun</div>
                                </div>
                                
                                <!-- Calendar body -->
                                <div class="calendar-body">
                                    <?php
                                    $current_day = 1;
                                    $weeks = ceil(($days_in_month + $first_day_of_week) / 7);
                                    
                                    for ($week = 0; $week < $weeks; $week++):
                                    ?>
                                    <div class="calendar-week">
                                        <?php for ($day_of_week = 0; $day_of_week < 7; $day_of_week++): ?>
                                        <div class="calendar-day <?php 
                                            if ($week == 0 && $day_of_week < $first_day_of_week) {
                                                echo 'other-month';
                                            } elseif ($current_day > $days_in_month) {
                                                echo 'other-month';
                                            } else {
                                                echo 'current-month';
                                                if ($current_day == date('j') && $current_month == date('n') && $current_year == date('Y')) {
                                                    echo ' today';
                                                }
                                                if (isset($events_by_date[$current_day])) {
                                                    echo ' has-events';
                                                }
                                            }
                                        ?>">
                                            <?php if ($week == 0 && $day_of_week < $first_day_of_week): ?>
                                                <!-- Previous month days -->
                                                <span class="day-number">
                                                    <?= date('j', mktime(0, 0, 0, $current_month, $day_of_week - $first_day_of_week + 1, $current_year)) ?>
                                                </span>
                                            <?php elseif ($current_day <= $days_in_month): ?>
                                                <!-- Current month days -->
                                                <span class="day-number"><?= $current_day ?></span>
                                                
                                                <!-- Events for this day -->
                                                <?php if (isset($events_by_date[$current_day])): ?>
                                                <div class="day-events">
                                                    <?php foreach ($events_by_date[$current_day] as $event): ?>
                                                    <div class="event-dot <?= $event['event_type'] ?> <?= $event['status'] ?>" 
                                                         data-toggle="tooltip" 
                                                         title="<?= htmlspecialchars($event['title']) ?> - <?= htmlspecialchars($event['project_name']) ?>">
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php $current_day++; ?>
                                            <?php else: ?>
                                                <!-- Next month days -->
                                                <span class="day-number">
                                                    <?= $current_day - $days_in_month ?>
                                                </span>
                                                <?php $current_day++; ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php endfor; ?>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>



                <!-- UPCOMING EVENTS SIDEBAR -->
                <div class="col-md-4">

                <!-- PROJECT FILTER -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">
            <i class="nc-icon nc-settings-gear-65"></i>
            Project Filter
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" id="projectFilterForm">
            <input type="hidden" name="month" value="<?= $current_month ?>">
            <input type="hidden" name="year" value="<?= $current_year ?>">
            
            <div class="form-group">
                <label for="project_select" class="form-label">Select Projects:</label>
                <select class="form-control" id="project_select" name="projects[]" multiple>
                    <?php foreach ($available_projects as $project): ?>
                    <option value="<?= $project['id'] ?>" 
                            <?= in_array($project['id'], $selected_projects) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($project['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">
                    <?= count($selected_projects) ?> of <?= count($available_projects) ?> projects selected
                </small>
            </div>
            
            <div class="d-flex justify-content-between">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" onclick="selectAllProjects()">
                        All
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="clearAllProjects()">
                        None
                    </button>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="nc-icon nc-refresh-69"></i>
                    Update
                </button>
            </div>
        </form>
    </div>
</div>
                    <!-- UPCOMING EVENTS -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="nc-icon nc-bell-55"></i>
                                Upcoming Events
                            </h5>
                            <p class="card-category">
                                Next 30 days 
                                <?php if (count($selected_projects) < count($available_projects)): ?>
                                <small class="text-info">(filtered)</small>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="card-body">
                            <?php if (empty($upcoming_events)): ?>
                            <div class="text-center py-3">
                                <i class="nc-icon nc-check-2 text-success" style="font-size: 32px;"></i>
                                <p class="text-muted">No upcoming events!</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($upcoming_events as $event): ?>
                            <div class="upcoming-event">
                                <div class="event-date">
                                    <span class="event-day"><?= date('j', strtotime($event['event_date'])) ?></span>
                                    <span class="event-month"><?= date('M', strtotime($event['event_date'])) ?></span>
                                </div>
                                <div class="event-details">
                                    <h6 class="event-title"><?= htmlspecialchars($event['title']) ?></h6>
                                    <p class="event-project"><?= htmlspecialchars($event['project_name']) ?></p>
                                    <?php if ($event['wp_name']): ?>
                                    <small class="text-muted"><?= htmlspecialchars($event['wp_name']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="event-type">
                                    <span class="badge badge-<?= getEventBadgeColor($event['event_type']) ?>">
                                        <?= ucfirst($event['type_label']) ?>
                                    </span>
                                    <?php if ($event['days_until'] <= 3): ?>
                                    <small class="text-danger d-block">
                                        <i class="nc-icon nc-time-alarm"></i>
                                        <?php if ($event['days_until'] == 0): ?>
                                            Today!
                                        <?php elseif ($event['days_until'] == 1): ?>
                                            Tomorrow
                                        <?php else: ?>
                                            <?= $event['days_until'] ?> days
                                        <?php endif; ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    

                    
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <?php include '../includes/footer.php'; ?>

<?php
// Helper functions for calendar display
function getEventBadgeColor($event_type) {
    switch ($event_type) {
        case 'activity':
            return 'primary';
        case 'milestone':
            return 'warning';
        case 'project':
            return 'danger';
        default:
            return 'secondary';
    }
}
?>