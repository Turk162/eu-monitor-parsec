<?php
// ===================================================================
//  PARTNER BUDGET VIEW PAGE
//  Shows detailed budget breakdown for a specific partner in a project
//  MODIFIED: Reads totals from database instead of calculating
// ===================================================================

require_once '../includes/header.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// ===================================================================
//  AUTHORIZATION & VALIDATION
// ===================================================================

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if (!$project_id) {
    $_SESSION['error'] = 'No project ID specified.';
    header('Location: projects.php');
    exit;
}

// Get user info safely
$user_id = function_exists('getUserId') ? getUserId() : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
$user_role = function_exists('getUserRole') ? getUserRole() : (isset($_SESSION['role']) ? $_SESSION['role'] : 'guest');
$user_partner_id = isset($_SESSION['partner_id']) ? (int)$_SESSION['partner_id'] : 0;

$database = new Database();
$conn = $database->connect();

// Authorization: User must be a partner in this project or super_admin
if ($user_role !== 'super_admin') {
    if (!$user_partner_id) {
        $_SESSION['error'] = 'No partner organization associated with your account.';
        header('Location: projects.php');
        exit;
    }
    
    $access_stmt = $conn->prepare("
        SELECT COUNT(*) FROM project_partners 
        WHERE project_id = ? AND partner_id = ?
    ");
    $access_stmt->execute([$project_id, $user_partner_id]);
    
    if ($access_stmt->fetchColumn() == 0) {
        $_SESSION['error'] = 'You do not have permission to view this project budget.';
        header('Location: projects.php');
        exit;
    }
}

// For super_admin and coordinators, allow partner selection via GET parameter
$target_partner_id = $user_partner_id;
if (($user_role === 'super_admin' || $user_role === 'coordinator') && isset($_GET['partner_id'])) {
    $target_partner_id = (int)$_GET['partner_id'];
}

// ===================================================================
//  DATA FETCHING
// ===================================================================

// Get project details
$project_stmt = $conn->prepare("
    SELECT p.*, u.full_name as coordinator_name 
    FROM projects p 
    LEFT JOIN users u ON p.coordinator_id = u.id 
    WHERE p.id = ?
");
$project_stmt->execute([$project_id]);
$project = $project_stmt->fetch();

if (!$project) {
    $_SESSION['error'] = 'Project not found.';
    header('Location: projects.php');
    exit;
}

// Get partner details
$partner_stmt = $conn->prepare("
    SELECT p.*, pp.budget_allocated as total_project_budget, pp.role
    FROM partners p 
    INNER JOIN project_partners pp ON p.id = pp.partner_id
    WHERE p.id = ? AND pp.project_id = ?
");
$partner_stmt->execute([$target_partner_id, $project_id]);
$partner = $partner_stmt->fetch();

if (!$partner) {
    $_SESSION['error'] = 'Partner not found in this project.';
    header('Location: projects.php');
    exit;
}

// Get detailed budget breakdown by work packages WITH PRE-CALCULATED TOTALS
$budget_stmt = $conn->prepare("
    SELECT 
        wpb.*,
        wp.wp_number,
        wp.name as wp_name,
        wp.description as wp_description,
        
        -- Personnel total (pre-calculated in database)
        CASE 
            WHEN wpb.wp_type = 'project_management' THEN COALESCE(wpb.project_management_cost, 0)
            ELSE COALESCE(wpb.working_days_total, 0)
        END as personnel_total,
        
        -- Travel total (sum from travel table using manual total field)
        COALESCE(travel_totals.total_travel, 0) as travel_total,
        
        -- Other costs
        COALESCE(wpb.other_costs, 0) as other_total,
        
        -- Work package total
        (CASE 
            WHEN wpb.wp_type = 'project_management' THEN COALESCE(wpb.project_management_cost, 0)
            ELSE COALESCE(wpb.working_days_total, 0)
        END + COALESCE(travel_totals.total_travel, 0) + COALESCE(wpb.other_costs, 0)) as wp_total
        
    FROM work_package_partner_budgets wpb
    INNER JOIN work_packages wp ON wpb.work_package_id = wp.id
    LEFT JOIN (
        SELECT 
            wp_partner_budget_id,
            SUM(total) as total_travel
        FROM budget_travel_subsistence 
        GROUP BY wp_partner_budget_id
    ) travel_totals ON wpb.id = travel_totals.wp_partner_budget_id
    
    WHERE wpb.project_id = ? AND wpb.partner_id = ?
    ORDER BY wp.wp_number ASC
");
$budget_stmt->execute([$project_id, $target_partner_id]);
$wp_budgets = $budget_stmt->fetchAll();

// Get travel & subsistence data for each work package (for display details)
$travel_data = [];
if (!empty($wp_budgets)) {
    $wp_budget_ids = array_column($wp_budgets, 'id');
    $placeholders = str_repeat('?,', count($wp_budget_ids) - 1) . '?';
    
    $travel_stmt = $conn->prepare("
        SELECT * FROM budget_travel_subsistence 
        WHERE wp_partner_budget_id IN ($placeholders)
        ORDER BY wp_partner_budget_id, id
    ");
    $travel_stmt->execute($wp_budget_ids);
    $travels = $travel_stmt->fetchAll();
    
    // Group by work package budget ID
    foreach ($travels as $travel) {
        $travel_data[$travel['wp_partner_budget_id']][] = $travel;
    }
}

// Calculate ONLY the grand total (sum of wp_total from database)
$calculated_total = 0;
foreach ($wp_budgets as $wp_budget) {
    $calculated_total += (float)$wp_budget['wp_total'];
}

// Helper functions
function formatCurrency($amount) {
    return '€' . number_format((float)$amount, 2, ',', '.');
}

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Partner Budget - EU Project Manager</title>
    <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no' name='viewport' />
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet" />
    <link href="../assets/css/paper-dashboard.css?v=2.0.1" rel="stylesheet" />
    <link href="../assets/css/pages/partner-budget.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700,200" rel="stylesheet" />
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css" rel="stylesheet">
</head>

<body>
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-panel">
            <?php include '../includes/navbar.php'; ?>
            
            <div class="content">
                <!-- Header con breadcrumb -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="projects.php">Projects</a></li>
                                <li class="breadcrumb-item"><a href="project-detail.php?id=<?php echo $project_id; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
                                <li class="breadcrumb-item active" aria-current="page">Partner Budget</li>
                            </ol>
                        </nav>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <!-- Page Header -->
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h4 class="card-title">
                                            <i class="nc-icon nc-money-coins"></i>
                                            Organization Budget Details
                                        </h4>
                                        <p class="card-category">
                                            <?php echo htmlspecialchars($project['name']); ?> - 
                                            <?php echo formatProgramName($project['program_type']); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-right">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Organization Info -->
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h5><?php echo htmlspecialchars($partner['name']); ?></h5>
                                        <p class="text-muted">
                                            <?php echo htmlspecialchars($partner['organization_type']); ?> - 
                                            <?php echo htmlspecialchars($partner['country']); ?>
                                            <?php if ($partner['role'] === 'coordinator'): ?>
                                                <span class="badge badge-primary ml-2">Project Coordinator</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-right">
                                        <div class="budget-summary">
                                            <h6 class="text-muted">Total Allocated Budget</h6>
                                            <h4 class="text-primary"><?php echo formatCurrency($partner['total_project_budget']); ?></h4>
                                            <?php if (abs($calculated_total - $partner['total_project_budget']) > 0.01): ?>
                                                <small class="text-warning">
                                                    Detailed: <?php echo formatCurrency($calculated_total); ?>
                                                    <?php if ($calculated_total < $partner['total_project_budget']): ?>
                                                        <br>Remaining: <?php echo formatCurrency($partner['total_project_budget'] - $calculated_total); ?>
                                                    <?php endif; ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Work Packages Budget Breakdown -->
                        <?php foreach ($wp_budgets as $wp_budget): ?>
                        <div class="card wp-budget-card" data-wp-type="<?php echo $wp_budget['wp_type']; ?>">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <?php echo htmlspecialchars($wp_budget['wp_number']); ?>: 
                                    <?php echo htmlspecialchars($wp_budget['wp_name']); ?>
                                    <span class="wp-total float-right">
                                        <?php echo formatCurrency($wp_budget['wp_total']); ?>
                                    </span>
                                </h5>
                                <?php if ($wp_budget['wp_description']): ?>
                                <p class="card-category"><?php echo htmlspecialchars(substr($wp_budget['wp_description'], 0, 150)); ?>...</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body">
                                <!-- Personnel Costs -->
                                <div class="budget-section">
                                    <?php if ($wp_budget['wp_type'] === 'project_management'): ?>
                                        <div class="budget-line">
                                            <div class="budget-item">
                                                <strong>Project Management:</strong>
                                                <span class="budget-amount">
                                                    <?php echo formatCurrency($wp_budget['personnel_total']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="budget-line">
                                            <div class="budget-item">
                                                <strong>Personnel costs:</strong>
                                                <?php echo (int)($wp_budget['working_days'] ?? 0); ?> days × 
                                                <?php echo formatCurrency($wp_budget['daily_rate'] ?? 0); ?>/day = 
                                                <span class="budget-amount">
                                                    <?php echo formatCurrency($wp_budget['personnel_total']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Travel & Subsistence -->
                                <?php if (isset($travel_data[$wp_budget['id']]) && !empty($travel_data[$wp_budget['id']])): ?>
                                <div class="budget-section">
                                    <h6 class="section-title">Travel & Subsistence:</h6>
                                    <?php foreach ($travel_data[$wp_budget['id']] as $index => $travel): ?>
                                    <div class="budget-line travel-line">
                                        <div class="travel-header">
                                            <strong><?php echo ($index + 1); ?>. <?php echo htmlspecialchars($travel['activity_destination']); ?></strong>
                                        </div>
                                        <div class="travel-details">
                                            <?php echo $travel['persons']; ?> persons × <?php echo $travel['days']; ?> days<br>
                                            Travel: <?php echo formatCurrency($travel['travel_cost']); ?> | 
                                            Subsistence: <?php echo formatCurrency($travel['daily_subsistence']); ?>/day<br>
                                            <span class="budget-amount">Total: <?php echo formatCurrency($travel['total']); ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <!-- Travel Section Total -->
                                    <div class="budget-line">
                                        <div class="budget-item">
                                            <strong>Travel & Subsistence Total:</strong>
                                            <span class="budget-amount">
                                                <?php echo formatCurrency($wp_budget['travel_total']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Other Costs -->
                                <?php if ($wp_budget['other_total'] > 0): ?>
                                <div class="budget-section">
                                    <div class="budget-line">
                                        <div class="budget-item">
                                            <strong>Other:</strong>
                                            <span class="budget-amount"><?php echo formatCurrency($wp_budget['other_total']); ?></span>
                                            <?php if ($wp_budget['other_description']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($wp_budget['other_description']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- WP Subtotal -->
                                <div class="budget-section wp-subtotal">
                                    <div class="budget-line">
                                        <div class="budget-item">
                                            <strong><?php echo $wp_budget['wp_number']; ?> Subtotal:</strong>
                                            <span class="budget-amount">
                                                <?php echo formatCurrency($wp_budget['wp_total']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Total Summary -->
                        <div class="card budget-total-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h5>Total Partner Budget:</h5>
                                        <p class="text-muted">Sum of all work package allocations</p>
                                    </div>
                                    <div class="col-md-4 text-right">
                                        <h4 class="text-success"><?php echo formatCurrency($calculated_total); ?></h4>
                                        <?php if ($partner['total_project_budget'] > $calculated_total): ?>
                                        <p class="text-warning">
                                            Remaining: <?php echo formatCurrency($partner['total_project_budget'] - $calculated_total); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="card">
                            <div class="card-body text-center">
                                <button class="btn btn-primary" id="exportPdfBtn">
                                    <i class="nc-icon nc-single-copy-04"></i> Export PDF
                                </button>
                                <button class="btn btn-secondary" id="printBudgetBtn">
                                    <i class="nc-icon nc-tap-01"></i> Print Budget
                                </button>
                                <?php if ($user_role === 'super_admin' || $user_role === 'coordinator'): ?>
                                <a href="manage-partners-budget.php?project_id=<?php echo $project_id; ?>&partner_id=<?php echo $target_partner_id; ?>" 
                                   class="btn btn-warning">
                                    <i class="nc-icon nc-settings-gear-65"></i> Edit Budget Details
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <!-- Core JS Files -->
    <script src="../assets/js/core/jquery.min.js"></script>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>
    <script src="../assets/js/plugins/perfect-scrollbar.jquery.min.js"></script>
    <script src="../assets/js/plugins/bootstrap-notify.js"></script>
    <script src="../assets/js/paper-dashboard.min.js?v=2.0.1"></script>
    <script src="../assets/js/pages/partner-budget.js"></script>
</body>
</html>