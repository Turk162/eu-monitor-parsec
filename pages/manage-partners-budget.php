<?php
// ===================================================================
//  MANAGE PARTNER BUDGETS PAGE - Complete Rewrite
//  Allows coordinators to set detailed budget breakdown for partners
// ===================================================================

require_once '../includes/header.php';

// ===================================================================
//  AUTHORIZATION & VALIDATION
// ===================================================================

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if (!$project_id) {
    $_SESSION['error'] = 'No project ID specified.';
    header('Location: projects.php');
    exit;
}

// Authorization: Only coordinators and super_admin can manage budgets
if ($user_role !== 'super_admin' && $user_role !== 'coordinator') {
    $_SESSION['error'] = 'You do not have permission to manage partner budgets.';
    header('Location: projects.php');
    exit;
}

// Get project details and verify coordinator access
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

// Additional check: non-super_admin coordinators can only manage their own projects
if ($user_role === 'coordinator' && $project['coordinator_id'] !== $user_id) {
    $_SESSION['error'] = 'You can only manage budgets for your own projects.';
    header('Location: projects.php');
    exit;
}

// ===================================================================
//  HELPER FUNCTIONS
// ===================================================================

function formatProgramName($program_type) {
    $programs = array(
        'erasmus_plus' => 'Erasmus+',
        'horizon_europe' => 'Horizon Europe',
        'interreg' => 'Interreg',
        'life' => 'LIFE Programme',
        'creative_europe' => 'Creative Europe',
        'eu_citizenship' => 'Europe for Citizens',
        'digital_europe' => 'Digital Europe',
        'cerv' => 'CERV Programme',
        'other' => 'Other EU Programme'
    );
    return isset($programs[$program_type]) ? $programs[$program_type] : ucfirst(str_replace('_', ' ', $program_type));
}

function calculateBudgetTotals($budget, $travel_data_for_budget) {
    $totals = [
        'personnel' => 0,
        'travel' => 0,
        'other' => 0,
        'grand_total' => 0
    ];
    
    // Calculate personnel total
    if ($budget['wp_type'] === 'project_management') {
        $totals['personnel'] = (float)$budget['project_management_cost'];
    } else {
        if ($budget['working_days'] && $budget['daily_rate']) {
            $totals['personnel'] = $budget['working_days'] * $budget['daily_rate'];
        }
    }
    
    // Calculate travel total (sum of manual totals for this budget)
if (!empty($travel_data_for_budget)) {
    foreach ($travel_data_for_budget as $travel) {
        $totals['travel'] += (float)$travel['total']; // Usa il campo total invece di travel_cost
    }
}
    
    // Other costs
    $totals['other'] = (float)$budget['other_costs'];
    
    // Grand total
    $totals['grand_total'] = $totals['personnel'] + $totals['travel'] + $totals['other'];
    
    return $totals;
}

function formatCurrency($amount) {
    if ($amount === null || $amount === '') return '';
    return number_format((float)$amount, 2, '.', '');
}

function initializeWorkPackageBudgets($conn, $project_id) {
    try {
        $conn->beginTransaction();
        
        // Get work packages
        $wp_stmt = $conn->prepare("SELECT id, wp_number, name FROM work_packages WHERE project_id = ?");
        $wp_stmt->execute([$project_id]);
        $work_packages = $wp_stmt->fetchAll();
        
        // Get project partners
        $partners_stmt = $conn->prepare("SELECT partner_id FROM project_partners WHERE project_id = ?");
        $partners_stmt->execute([$project_id]);
        $partners = $partners_stmt->fetchAll();
        
        if (empty($work_packages) || empty($partners)) {
            $conn->rollback();
            return false;
        }
        
        $insert_stmt = $conn->prepare("
            INSERT IGNORE INTO work_package_partner_budgets 
            (project_id, work_package_id, partner_id, wp_type, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        
        foreach ($work_packages as $wp) {
            foreach ($partners as $partner) {
                // Determine if WP1 (project management) or standard
                $wp_type = (strtolower($wp['wp_number']) === 'wp1' || 
                           stripos($wp['name'], 'project management') !== false) 
                           ? 'project_management' : 'standard';
                
                $insert_stmt->execute([
                    $project_id,
                    $wp['id'],
                    $partner['partner_id'],
                    $wp_type
                ]);
            }
        }
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

// ===================================================================
//  FORM SUBMISSION HANDLER
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        $budget_data = isset($_POST['budget']) ? $_POST['budget'] : array();
        $travel_data = isset($_POST['travel']) ? $_POST['travel'] : array();
        $action = isset($_POST['action']) ? $_POST['action'] : 'save';
        
        // Update budget records
        $update_budget_stmt = $conn->prepare("
            UPDATE work_package_partner_budgets 
            SET project_management_cost = ?, working_days = ?, daily_rate = ?, 
                working_days_total = ?, other_costs = ?, other_description = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        // Clear and re-insert travel data
        $delete_travel_stmt = $conn->prepare("DELETE FROM budget_travel_subsistence WHERE wp_partner_budget_id = ?");
$insert_travel_stmt = $conn->prepare("
    INSERT INTO budget_travel_subsistence 
    (wp_partner_budget_id, activity_destination, persons, days, travel_cost, daily_subsistence, total)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
        
        foreach ($budget_data as $budget_id => $budget_info) {
            $budget_id = (int)$budget_id;
            
            $project_management_cost = isset($budget_info['project_management_cost']) ? (float)$budget_info['project_management_cost'] : null;
            $working_days = isset($budget_info['working_days']) ? (int)$budget_info['working_days'] : null;
            $daily_rate = isset($budget_info['daily_rate']) ? (float)$budget_info['daily_rate'] : null;
            $other_costs = isset($budget_info['other_costs']) ? (float)$budget_info['other_costs'] : 0;
            $other_description = isset($budget_info['other_description']) ? trim($budget_info['other_description']) : null;
            
            // Calculate working_days_total
            $working_days_total = null;
            if ($working_days && $daily_rate) {
                $working_days_total = $working_days * $daily_rate;
            }
            
            $update_budget_stmt->execute([
                $project_management_cost,
                $working_days,
                $daily_rate,
                $working_days_total,
                $other_costs,
                $other_description,
                $budget_id
            ]);
            
            // Handle travel data - save exactly what user entered
            $delete_travel_stmt->execute([$budget_id]);
            
           if (isset($travel_data[$budget_id])) {
    foreach ($travel_data[$budget_id] as $travel_info) {
        if (!empty($travel_info['activity_destination'])) {
            
            // Gestisci travel_cost e total separatamente
            $travel_cost = isset($travel_info['travel_cost']) ? (float)$travel_info['travel_cost'] : 0;
            
            // Il totale manuale viene dal campo total_amount
            $manual_total = 0;
            if (isset($travel_info['total_amount']) && $travel_info['total_amount'] > 0) {
                $manual_total = (float)$travel_info['total_amount'];
            }
            
            $insert_travel_stmt->execute([
                $budget_id,
                trim($travel_info['activity_destination']),
                isset($travel_info['persons']) ? (int)$travel_info['persons'] : 0,
                isset($travel_info['days']) ? (int)$travel_info['days'] : 0,
                $travel_cost,
                isset($travel_info['daily_subsistence']) ? (float)$travel_info['daily_subsistence'] : 0,
                $manual_total  // Salva il totale manuale nel campo total
            ]);
        }
    }
}
}
        
        $conn->commit();

        // Recalculate budgets for all affected work packages
        if (!empty($budget_data)) {
            $budget_ids = array_keys($budget_data);
            if (!empty($budget_ids)) {
                $placeholders = str_repeat('?,', count($budget_ids) - 1) . '?';
                
                $wp_ids_stmt = $conn->prepare("
                    SELECT DISTINCT work_package_id 
                    FROM work_package_partner_budgets 
                    WHERE id IN ($placeholders)
                ");
                $wp_ids_stmt->execute($budget_ids);
                $work_package_ids = $wp_ids_stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($work_package_ids as $wp_id) {
                    recalculateWorkPackageBudget($conn, $wp_id);
                }
            }
        }
        
        if ($action === 'refresh') {
            $_SESSION['success'] = 'Budget data updated and totals refreshed!';
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $_SESSION['success'] = 'Budget details updated successfully!';
            header('Location: project-detail.php?id=' . $project_id);
            exit;
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = 'Error updating budget details: ' . $e->getMessage();
    }
}

// ===================================================================
//  DATA FETCHING
// ===================================================================

// Initialize budget records if needed
initializeWorkPackageBudgets($conn, $project_id);

// Get work packages with budget data
$work_packages_stmt = $conn->prepare("
    SELECT wp.id, wp.wp_number, wp.name as wp_name, wp.description
    FROM work_packages wp 
    WHERE wp.project_id = ?
    ORDER BY wp.wp_number
");
$work_packages_stmt->execute([$project_id]);
$work_packages = $work_packages_stmt->fetchAll();

// Get all budget data
$budgets_stmt = $conn->prepare("
    SELECT 
        wpb.*,
        p.name as partner_name,
        p.country as partner_country,
        p.organization_type
    FROM work_package_partner_budgets wpb
    INNER JOIN partners p ON wpb.partner_id = p.id
    WHERE wpb.project_id = ?
    ORDER BY wpb.work_package_id, p.name
");
$budgets_stmt->execute([$project_id]);
$all_budgets = $budgets_stmt->fetchAll();

// Group budgets by work package and partner
$budgets_by_wp = array();
foreach ($all_budgets as $budget) {
    $wp_id = $budget['work_package_id'];
    if (!isset($budgets_by_wp[$wp_id])) {
        $budgets_by_wp[$wp_id] = array();
    }
    $budgets_by_wp[$wp_id][] = $budget;
}

// Get travel data
$travel_data = array();
if (!empty($all_budgets)) {
    $budget_ids = array_column($all_budgets, 'id');
    $placeholders = str_repeat('?,', count($budget_ids) - 1) . '?';
    
    $travel_stmt = $conn->prepare("
        SELECT * FROM budget_travel_subsistence 
        WHERE wp_partner_budget_id IN ($placeholders)
        ORDER BY wp_partner_budget_id, id
    ");
    $travel_stmt->execute($budget_ids);
    $travels = $travel_stmt->fetchAll();
    
    foreach ($travels as $travel) {
        $travel_data[$travel['wp_partner_budget_id']][] = $travel;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Manage Partner Budgets - EU Project Manager</title>
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
                <div class="row">
                    <div class="col-md-12">
                        <!-- Page Header -->
                        <div class="card page-header-card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h4 class="card-title">
                                            <i class="nc-icon nc-settings-gear-65"></i>
                                            Manage Partner Budgets
                                        </h4>
                                        <p class="card-category">
                                            <?php echo htmlspecialchars($project['name']); ?> - 
                                            <?php echo formatProgramName($project['program_type']); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-right">
                                        <a href="project-detail.php?id=<?php echo $project_id; ?>" class="btn btn-secondary btn-sm">
                                            <i class="nc-icon nc-minimal-left"></i> Back to Project
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Success/Error Messages -->
                        <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="nc-icon nc-check-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="nc-icon nc-simple-remove"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                        <?php endif; ?>

                        <!-- Instructions -->
                        <div class="card instructions-card">
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <h6><strong>Instructions:</strong></h6>
                                    <ul class="mb-0">
                                        <li><strong>Personnel:</strong> Set working days and daily rate for standard work packages, or flat-rate cost for project management</li>
                                        <li><strong>Travel & Subsistence:</strong> Add up to 3 travel entries per work package</li>
                                        <li><strong>Other Costs:</strong> Include any additional expenses with description</li>
                                        <li><strong>Calculations:</strong> Totals are calculated automatically as you type</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Main Form -->
                        <form method="POST" action="" id="budgetManagementForm">
                            
                            <!-- Work Packages -->
                            <?php foreach ($work_packages as $wp): ?>
                            <div class="card work-package-card" data-wp-id="<?php echo $wp['id']; ?>">
                                <div class="card-header work-package-header">
                                    <h5 class="mb-0">
                                        <?php echo htmlspecialchars($wp['wp_number']); ?>: 
                                        <?php echo htmlspecialchars($wp['wp_name']); ?>
                                    </h5>
                                    <?php if ($wp['description']): ?>
                                    <p class="text-muted mb-0"><?php echo htmlspecialchars($wp['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-body">
                                    <?php if (isset($budgets_by_wp[$wp['id']])): ?>
                                        <?php foreach ($budgets_by_wp[$wp['id']] as $budget): ?>
                                        <div class="partner-budget-section" data-budget-id="<?php echo $budget['id']; ?>">
                                            <?php 
                                            // Calculate totals for this budget
                                            $budget_travels = isset($travel_data[$budget['id']]) ? $travel_data[$budget['id']] : array();
                                            $totals = calculateBudgetTotals($budget, $budget_travels);
                                            ?>
                                            <div class="partner-header">
                                                <h6>
                                                    <i class="nc-icon nc-single-02"></i>
                                                    <?php echo htmlspecialchars($budget['partner_name']); ?>
                                                    <small class="text-muted">
                                                        (<?php echo htmlspecialchars($budget['partner_country']); ?> - 
                                                        <?php echo htmlspecialchars($budget['organization_type']); ?>)
                                                    </small>
                                                </h6>
                                            </div>
                                            
                                            <div class="budget-sections">
                                                <!-- Personnel Section -->
                                                <div class="budget-section personnel-section">
                                                    <h6 class="section-title">
                                                        <i class="nc-icon nc-badge"></i> 
                                                        <?php echo $budget['wp_type'] === 'project_management' ? 'Project Management Activities' : 'Personnel'; ?>
                                                    </h6>
                                                    
                                                    <?php if ($budget['wp_type'] === 'project_management'): ?>
                                                    <!-- Project Management Flat Rate -->
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label>Project Management Cost (€)</label>
                                                                <input type="number" 
                                                                       name="budget[<?php echo $budget['id']; ?>][project_management_cost]" 
                                                                       class="form-control project-management-cost" 
                                                                       step="0.01" 
                                                                       min="0"
                                                                       value="<?php echo formatCurrency($budget['project_management_cost']); ?>"
                                                                       placeholder="0.00"
                                                                       data-budget-id="<?php echo $budget['id']; ?>">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label>PMA Total</label>
                                                                <div class="personnel-total">€<?php echo formatCurrency($totals['personnel']); ?></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php else: ?>
                                                    <!-- Standard Work Package -->
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <div class="form-group">
                                                                <label>Working Days</label>
                                                                <input type="number" 
                                                                       name="budget[<?php echo $budget['id']; ?>][working_days]" 
                                                                       class="form-control working-days" 
                                                                       min="0"
                                                                       value="<?php echo $budget['working_days'] ? $budget['working_days'] : ''; ?>"
                                                                       placeholder="0"
                                                                       data-budget-id="<?php echo $budget['id']; ?>">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="form-group">
                                                                <label>Daily Rate (€)</label>
                                                                <input type="number" 
                                                                       name="budget[<?php echo $budget['id']; ?>][daily_rate]" 
                                                                       class="form-control daily-rate" 
                                                                       step="0.01" 
                                                                       min="0"
                                                                       value="<?php echo formatCurrency($budget['daily_rate']); ?>"
                                                                       placeholder="0.00"
                                                                       data-budget-id="<?php echo $budget['id']; ?>">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label>Personnel Total</label>
                                                                <div class="personnel-total">€<?php echo formatCurrency($totals['personnel']); ?></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Travel Section -->
                                                <div class="budget-section travel-section">
                                                    <h6 class="section-title">
                                                        <i class="nc-icon nc-world-2"></i> Travel & Subsistence
                                                    </h6>
                                                    
                                                    <?php 
                                                    $existing_travels = isset($travel_data[$budget['id']]) ? $travel_data[$budget['id']] : array();
                                                    for ($i = 0; $i < 3; $i++): 
                                                        $travel = isset($existing_travels[$i]) ? $existing_travels[$i] : null;
                                                    ?>
                                                    <div class="travel-entry" data-travel-index="<?php echo $i; ?>">
                                                        <div class="travel-entry-header">
                                                            <strong>Travel Entry <?php echo ($i + 1); ?></strong>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-3">
                                                                <div class="form-group">
                                                                    <label>Action/Destination</label>
                                                                    <input type="text" 
                                                                           name="travel[<?php echo $budget['id']; ?>][<?php echo $i; ?>][activity_destination]" 
                                                                           class="form-control activity-destination" 
                                                                           value="<?php echo $travel ? htmlspecialchars($travel['activity_destination']) : ''; ?>"
                                                                           placeholder="e.g., Kick-off Meeting/Brussels">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <div class="form-group">
                                                                    <label>Persons</label>
                                                                    <input type="number" 
                                                                           name="travel[<?php echo $budget['id']; ?>][<?php echo $i; ?>][persons]" 
                                                                           class="form-control persons" 
                                                                           min="0"
                                                                           value="<?php echo $travel ? $travel['persons'] : ''; ?>"
                                                                           placeholder="0">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-1">
                                                                <div class="form-group">
                                                                    <label>Days</label>
                                                                    <input type="number" 
                                                                           name="travel[<?php echo $budget['id']; ?>][<?php echo $i; ?>][days]" 
                                                                           class="form-control days" 
                                                                           min="0"
                                                                           value="<?php echo $travel ? $travel['days'] : ''; ?>"
                                                                           placeholder="0">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <div class="form-group">
                                                                    <label>Travel Cost (€)</label>
                                                                    <input type="number" 
                                                                           name="travel[<?php echo $budget['id']; ?>][<?php echo $i; ?>][travel_cost]" 
                                                                           class="form-control travel-cost" 
                                                                           step="0.01" 
                                                                           min="0"
                                                                           value="<?php echo $travel ? formatCurrency($travel['travel_cost']) : ''; ?>"
                                                                           placeholder="0.00">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <div class="form-group">
                                                                    <label>Daily Subsistence (€)</label>
                                                                    <input type="number" 
                                                                           name="travel[<?php echo $budget['id']; ?>][<?php echo $i; ?>][daily_subsistence]" 
                                                                           class="form-control daily-subsistence" 
                                                                           step="0.01" 
                                                                           min="0"
                                                                           value="<?php echo $travel ? formatCurrency($travel['daily_subsistence']) : ''; ?>"
                                                                           placeholder="0.00">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <div class="form-group">
                                                                    <label>Total Amount (€)</label>
                                                                    <input type="number" 
       name="travel[<?php echo $budget['id']; ?>][<?php echo $i; ?>][total_amount]" 
       class="form-control travel-total-amount" 
       step="0.01" 
       min="0"
       value="<?php echo $travel ? formatCurrency($travel['total']) : ''; ?>"
       placeholder="0.00"
       data-budget-id="<?php echo $budget['id']; ?>">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endfor; ?>
                                                    
                                                    <div class="travel-section-total">
                                                        <strong>Travel Total: €<?php echo formatCurrency($totals['travel']); ?></strong>
                                                    </div>
                                                </div>
                                                
                                                <!-- Other Costs Section -->
                                                <div class="budget-section other-section">
                                                    <h6 class="section-title">
                                                        <i class="nc-icon nc-money-coins"></i> Other Costs
                                                    </h6>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="form-group">
                                                                <label>Other Expenses (€)</label>
                                                                <input type="number" 
                                                                       name="budget[<?php echo $budget['id']; ?>][other_costs]" 
                                                                       class="form-control other-costs" 
                                                                       step="0.01" 
                                                                       min="0"
                                                                       value="<?php echo formatCurrency($budget['other_costs']); ?>"
                                                                       placeholder="0.00"
                                                                       data-budget-id="<?php echo $budget['id']; ?>">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-8">
                                                            <div class="form-group">
                                                                <label>Description</label>
                                                                <textarea name="budget[<?php echo $budget['id']; ?>][other_description]" 
                                                                          class="form-control" 
                                                                          rows="2"
                                                                          placeholder="Describe other expenses..."><?php echo htmlspecialchars($budget['other_description'] ? $budget['other_description'] : ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Partner Total -->
                                                <div class="partner-total-section">
                                                    <div class="partner-total">
                                                        <h6>Partner Total: €<?php echo formatCurrency($totals['grand_total']); ?></h6>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            No partners assigned to this work package.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <!-- Submit Section -->
                            <div class="card submit-card">
                                <div class="card-body text-center">
                                    <button type="submit" name="action" value="refresh" class="btn btn-info btn-lg mr-3">
                                        <i class="nc-icon nc-refresh-02"></i> Aggiorna Totali
                                    </button>
                                    <button type="submit" name="action" value="save" class="btn btn-primary btn-lg">
                                        <i class="nc-icon nc-check-2"></i> Save Budget Details
                                    </button>
                                    <a href="project-detail.php?id=<?php echo $project_id; ?>" class="btn btn-secondary btn-lg ml-3">
                                        <i class="nc-icon nc-minimal-left"></i> Cancel
                                    </a>
                                </div>
                            </div>
                        </form>
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
<script src="../assets/js/pages/manage-partners-budget.js"></script> 

</body>
</html>