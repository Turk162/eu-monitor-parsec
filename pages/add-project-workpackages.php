<?php
// ===================================================================
//  ADD PROJECT WORK PACKAGES & ACTIVITIES PAGE (UPDATED)
// ===================================================================
// This page is part of the project creation wizard (Step 3).
// It allows a project coordinator to define Work Packages (WPs) and their
// associated Activities with partner-specific budgets.
// ===================================================================

// ===================================================================
//  INCLUDES & SESSION
// ===================================================================

session_start();
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// ===================================================================
//  AUTHENTICATION & AUTHORIZATION
// ===================================================================

$auth = new Auth();
$auth->requireLogin();

$user_id = getUserId();
$user_role = getUserRole();

// ===================================================================
//  DATABASE & INITIAL DATA FETCH
// ===================================================================

$database = new Database();
$conn = $database->connect();

// Get Project ID from URL and validate it
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if (!$project_id) {
    Flash::set('error', 'Project ID is required to add work packages.');
    header('Location: projects.php');
    exit;
}

// Fetch project details for display and permission checks
$project_stmt = $conn->prepare("SELECT id, name, coordinator_id FROM projects WHERE id = ?");
$project_stmt->execute([$project_id]);
$project = $project_stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    Flash::set('error', 'The specified project could not be found.');
    header('Location: projects.php');
    exit;
}

// Authorization Check: Only super admins or the project coordinator can add WPs
if ($user_role !== 'super_admin' && $project['coordinator_id'] !== $user_id) {
    Flash::set('error', 'You do not have permission to add work packages to this project.');
    header('Location: project-detail.php?id=' . $project_id);
    exit;
}

// Fetch partners associated with this project for dropdowns
$partners_stmt = $conn->prepare("
    SELECT p.id, p.name, p.country 
    FROM partners p
    JOIN project_partners pp ON p.id = pp.partner_id
    WHERE pp.project_id = ?
    ORDER BY p.name
");
$partners_stmt->execute([$project_id]);
$available_partners = $partners_stmt->fetchAll(PDO::FETCH_ASSOC);

// ===================================================================
//  FORM SUBMISSION HANDLER
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $work_packages_data = $_POST['work_packages'] ?? [];
    
    try {
        $conn->beginTransaction();

        // Prepare statements for insertion
        $wp_stmt = $conn->prepare("
            INSERT INTO work_packages (project_id, wp_number, name, description, lead_partner_id, start_date, end_date, status) 
            VALUES (:project_id, :wp_number, :name, :description, :lead_partner_id, :start_date, :end_date, 'not_started')
        ");

        $wp_budget_stmt = $conn->prepare("
            INSERT INTO work_package_partner_budgets (work_package_id, partner_id, project_id, budget_allocated) 
            VALUES (:work_package_id, :partner_id, :project_id, :budget_allocated)
        ");

        $activity_stmt = $conn->prepare("
            INSERT INTO activities (work_package_id, project_id, activity_number, name, description, responsible_partner_id, start_date, end_date, status) 
            VALUES (:work_package_id, :project_id, :activity_number, :name, :description, :responsible_partner_id, :start_date, :end_date, 'not_started')
        ");

        foreach ($work_packages_data as $wp_data) {
            // Skip empty/incomplete work packages
            if (empty($wp_data['name']) || empty($wp_data['wp_number'])) {
                continue;
            }
            
            // Insert the Work Package (without budget - will be calculated by trigger)
            $wp_stmt->execute([
                ':project_id' => $project_id,
                ':wp_number' => sanitizeInput($wp_data['wp_number']),
                ':name' => sanitizeInput($wp_data['name']),
                ':description' => sanitizeInput($wp_data['description'] ?? ''),
                ':lead_partner_id' => !empty($wp_data['lead_partner_id']) ? (int)$wp_data['lead_partner_id'] : null,
                ':start_date' => !empty($wp_data['start_date']) ? $wp_data['start_date'] : null,
                ':end_date' => !empty($wp_data['end_date']) ? $wp_data['end_date'] : null
            ]);
            
            $wp_id = $conn->lastInsertId();
            
            // Insert partner budgets for this Work Package
            if (!empty($wp_data['partner_budgets'])) {
                foreach ($wp_data['partner_budgets'] as $partner_id => $budget) {
                    if ($budget > 0) {
                        $wp_budget_stmt->execute([
                            ':work_package_id' => $wp_id,
                            ':partner_id' => (int)$partner_id,
                            ':project_id' => $project_id,
                            ':budget_allocated' => (float)$budget
                        ]);
                    }
                }
            }
            
            // Insert associated activities for this Work Package (NO BUDGET)
            if (!empty($wp_data['activities'])) {
                foreach ($wp_data['activities'] as $activity_data) {
                    // Skip empty/incomplete activities
                    if (empty($activity_data['name'])) {
                        continue;
                    }
                    
                    $activity_stmt->execute([
                        ':work_package_id' => $wp_id,
                        ':project_id' => $project_id,
                        ':activity_number' => sanitizeInput($activity_data['activity_number'] ?? ''),
                        ':name' => sanitizeInput($activity_data['name']),
                        ':description' => sanitizeInput($activity_data['description'] ?? ''),
                        ':responsible_partner_id' => !empty($activity_data['responsible_partner_id']) ? (int)$activity_data['responsible_partner_id'] : null,
                        ':start_date' => !empty($activity_data['start_date']) ? $activity_data['start_date'] : null,
                        ':end_date' => !empty($activity_data['end_date']) ? $activity_data['end_date'] : null
                    ]);
                }
            }
        }

        $conn->commit();
        Flash::set('success', 'Work packages and activities have been created successfully!');
        header('Location: add-project-milestones.php?project_id=' . $project_id);
        exit;

    } catch (PDOException $e) {
        $conn->rollback();
        Flash::set('error', 'A database error occurred: ' . $e->getMessage());
    }
}

// ===================================================================
//  PAGE-SPECIFIC VARIABLES
// ===================================================================

$page_title = 'Add Work Packages - ' . htmlspecialchars($project['name']);
$page_description = 'Define work packages and activities for your project';

?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<script src="../assets/js/pages/add-project-workpackages.js"></script>


<div class="main-panel">
    <?php 
    $navbar_page_title = 'Add Work Packages & Activities';
    include '../includes/navbar.php'; 
    ?>
    
    <div class="content">
        <!-- Progress Indicator -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="wizard-progress">
                            <div class="wizard-step completed">
                                <span class="step-number">1</span>
                                <span class="step-title">Project Details</span>
                            </div>
                            <div class="wizard-step completed">
                                <span class="step-number">2</span>
                                <span class="step-title">Partners</span>
                            </div>
                            <div class="wizard-step active">
                                <span class="step-number">3</span>
                                <span class="step-title">Work Packages</span>
                            </div>
                            <div class="wizard-step">
                                <span class="step-number">4</span>
                                <span class="step-title">Milestones</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Form -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="title">
                            <i class="nc-icon nc-bullet-list-67"></i> 
                            Work Packages & Activities for: <strong><?= htmlspecialchars($project['name']) ?></strong>
                        </h5>
                        <p class="category">Define the work packages and their activities. Each work package will have budget allocations per partner.</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="workPackagesForm">
                            <div id="work-packages-container">
                                <!-- Work Package Template -->
                                <div class="work-package-item" data-wp-index="0">
                                    <div class="wp-header">
                                        <div class="row">
                                            <div class="col-md-10">
                                                <h6 style="color: #333; margin-bottom: 0;">
                                                    📦 Work Package #<span class="wp-number">1</span>
                                                </h6>
                                            </div>
                                            <div class="col-md-2 text-right">
                                                <button type="button" class="btn btn-sm btn-danger remove-wp-btn" onclick="removeWorkPackage(this)" style="display: none;">
                                                    <i class="nc-icon nc-simple-remove"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="wp-basic-info">
                                        <div class="row">
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label><strong>WP Number *</strong></label>
                                                    <input type="text" 
                                                           name="work_packages[0][wp_number]" 
                                                           class="form-control" 
                                                           placeholder="WP1" 
                                                           required>
                                                </div>
                                            </div>
                                            <div class="col-md-10">
                                                <div class="form-group">
                                                    <label><strong>Work Package Name *</strong></label>
                                                    <input type="text" 
                                                           name="work_packages[0][name]" 
                                                           class="form-control" 
                                                           placeholder="Enter work package name" 
                                                           required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label><strong>Description</strong></label>
                                                    <textarea name="work_packages[0][description]" 
                                                              class="form-control" 
                                                              rows="3" 
                                                              placeholder="Describe the work package objectives and main activities"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label><strong>Lead Partner</strong></label>
                                                    <select name="work_packages[0][lead_partner_id]" class="form-control">
                                                        <option value="">Select Lead Partner</option>
                                                        <?php foreach ($available_partners as $partner): ?>
                                                            <option value="<?= $partner['id'] ?>">
                                                                <?= htmlspecialchars($partner['name']) ?> 
                                                                (<?= htmlspecialchars($partner['country']) ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label><strong>Start Date</strong></label>
                                                    <input type="date" 
                                                           name="work_packages[0][start_date]" 
                                                           class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label><strong>End Date</strong></label>
                                                    <input type="date" 
                                                           name="work_packages[0][end_date]" 
                                                           class="form-control">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Partner Budgets Section -->
                                    <div class="partner-budgets-section">
                                        <h6 style="color: #51CACF; margin: 20px 0 15px 0;">
                                            💰 Budget Allocation by Partner
                                        </h6>
                                        <div class="row partner-budget-grid">
                                            <?php foreach ($available_partners as $partner): ?>
                                                <div class="col-md-6 col-lg-4">
                                                    <div class="form-group">
                                                        <label>
                                                            <strong><?= htmlspecialchars($partner['name']) ?></strong>
                                                            <small class="text-muted">(<?= htmlspecialchars($partner['country']) ?>)</small>
                                                        </label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text">€</span>
                                                            </div>
                                                            <input type="number" 
                                                                   name="work_packages[0][partner_budgets][<?= $partner['id'] ?>]" 
                                                                   class="form-control partner-budget-input" 
                                                                   step="0.01" 
                                                                   min="0" 
                                                                   placeholder="0.00"
                                                                   data-partner-id="<?= $partner['id'] ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="alert alert-info" style="background-color: #e3f2fd; border: 1px solid #2196f3;">
                                                    <strong>Total WP Budget: €<span class="wp-total-budget">0.00</span></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr style="border-color: #51CACF;">
                                    
                                    <!-- Activities Section -->
                                    <div class="activities-section">
                                        <h6 style="color: #333; margin-bottom: 15px;">
                                            🎯 Activities for this Work Package
                                        </h6>
                                        
                                        <div class="activities-container">
                                            <!-- Activity Template -->
                                            <div class="activity-item" data-activity-index="0">
                                                <div class="row mb-2">
                                                    <div class="col-md-10">
                                                        <strong>Activity #<span class="activity-number">1</span></strong>
                                                    </div>
                                                    <div class="col-md-2 text-right">
                                                        <button type="button" class="btn btn-sm btn-danger remove-activity-btn" onclick="removeActivity(this)" style="display: none;">
                                                            <i class="nc-icon nc-simple-remove"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <div class="form-group">
                                                            <label>Activity Number</label>
                                                            <input type="text" 
                                                                   name="work_packages[0][activities][0][activity_number]" 
                                                                   class="form-control" 
                                                                   placeholder="A1.1">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-9">
                                                        <div class="form-group">
                                                            <label>Activity Name *</label>
                                                            <input type="text" 
                                                                   name="work_packages[0][activities][0][name]" 
                                                                   class="form-control" 
                                                                   placeholder="Enter activity name" 
                                                                   required>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="form-group">
                                                            <label>Description</label>
                                                            <textarea name="work_packages[0][activities][0][description]" 
                                                                      class="form-control" 
                                                                      rows="2" 
                                                                      placeholder="Describe the activity"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label>Responsible Partner</label>
                                                            <select name="work_packages[0][activities][0][responsible_partner_id]" class="form-control">
                                                                <option value="">Select Partner</option>
                                                                <?php foreach ($available_partners as $partner): ?>
                                                                    <option value="<?= $partner['id'] ?>">
                                                                        <?= htmlspecialchars($partner['name']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label>Start Date</label>
                                                            <input type="date" 
                                                                   name="work_packages[0][activities][0][start_date]" 
                                                                   class="form-control">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label>End Date</label>
                                                            <input type="date" 
                                                                   name="work_packages[0][activities][0][end_date]" 
                                                                   class="form-control">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="text-center mt-3">
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addActivity(this)">
                                                <i class="nc-icon nc-simple-add"></i> Add Activity
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="button" class="btn btn-outline-primary" onclick="addWorkPackage()">
                                    <i class="nc-icon nc-simple-add"></i> Add Work Package
                                </button>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between">
                                <a href="add-project-partners.php?project_id=<?= $project_id ?>" class="btn btn-secondary">
                                    <i class="nc-icon nc-minimal-left"></i> Back
                                </a>
                                <div>
                                    <a href="add-project-milestones.php?project_id=<?= $project_id ?>" class="btn btn-outline-primary">
                                        Skip & Continue <i class="nc-icon nc-minimal-right"></i>
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="nc-icon nc-check-2"></i> Save Work Packages & Continue
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</div>

<script>
// JavaScript per gestire l'aggiunta/rimozione di WP e attività
// E per calcolare automaticamente i budget totali

let workPackageIndex = 0;

function addWorkPackage() {
    workPackageIndex++;
    
    const container = document.getElementById('work-packages-container');
    const template = container.querySelector('.work-package-item').cloneNode(true);
    
    // Aggiorna gli indici nel nuovo template
    updateWorkPackageIndices(template, workPackageIndex);
    
    // Pulisci i valori
    clearFormValues(template);
    
    // Mostra il pulsante remove
    template.querySelector('.remove-wp-btn').style.display = 'inline-block';
    
    container.appendChild(template);
    updateWorkPackageNumbers();
}