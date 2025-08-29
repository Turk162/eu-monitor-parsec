<?php
// ===================================================================
//  ADD PROJECT WORK PACKAGES & ACTIVITIES PAGE
// ===================================================================
// This page is part of the project creation wizard (Step 3).
// It allows a project coordinator to define Work Packages (WPs) and their
// associated Activities.
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

        // Prepare statements for insertion to be used in the loop
        $wp_stmt = $conn->prepare("
            INSERT INTO work_packages (project_id, wp_number, name, description, lead_partner_id, start_date, end_date, budget, status) 
            VALUES (:project_id, :wp_number, :name, :description, :lead_partner_id, :start_date, :end_date, :budget, 'not_started')
        ");

        $activity_stmt = $conn->prepare("
            INSERT INTO activities (work_package_id, project_id, activity_number, name, description, responsible_partner_id, start_date, end_date, end_date, budget, status) 
            VALUES (:work_package_id, :project_id, :activity_number, :name, :description, :responsible_partner_id, :start_date, :end_date, :end_date, :budget, 'not_started')
        ");

        foreach ($work_packages_data as $wp_data) {
            // Skip empty/incomplete work packages
            if (empty($wp_data['name']) || empty($wp_data['wp_number'])) {
                continue;
            }
            
            // Insert the Work Package
            $wp_stmt->execute([
                ':project_id' => $project_id,
                ':wp_number' => sanitizeInput($wp_data['wp_number']),
                ':name' => sanitizeInput($wp_data['name']),
                ':description' => sanitizeInput($wp_data['description'] ?? ''),
                ':lead_partner_id' => !empty($wp_data['lead_partner_id']) ? (int)$wp_data['lead_partner_id'] : null,
                ':start_date' => !empty($wp_data['start_date']) ? $wp_data['start_date'] : null,
                ':end_date' => !empty($wp_data['end_date']) ? $wp_data['end_date'] : null,
                ':budget' => !empty($wp_data['budget']) ? (float)$wp_data['budget'] : null
            ]);
            
            $wp_id = $conn->lastInsertId();
            
            // Insert associated activities for this Work Package
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
                        ':end_date' => !empty($activity_data['end_date']) ? $activity_data['end_date'] : null,
                        ':end_date' => !empty($activity_data['end_date']) ? $activity_data['end_date'] : null,
                        ':budget' => !empty($activity_data['budget']) ? (float)$activity_data['budget'] : null
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
$page_css_path = '../assets/css/pages/add-project-workpackages.css';
$page_js_path = '../assets/js/pages/add-project-workpackages.js';
// Page-specific styles
$page_styles = '
    .required { 
        color: #e74c3c; 
    }
    .form-section {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-left: 4px solid #51CACF;
    }
    .form-section h6 {
        color: #51CACF;
        font-weight: 600;
        margin-bottom: 15px;
    }
    .content {
        min-height: 100vh !important;
        padding: 30px 15px;
        background: white !important;
        visibility: visible !important;
        display: block !important;
    }
    .main-panel {
        width: 90% !important;
    }
    .card {
        background: white !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
        visibility: visible !important;
        display: block !important;
        
    }
';


// ===================================================================
//  START HTML LAYOUT
// ===================================================================

include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Add Work Packages - EU Project Manager</title>
    <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' name='viewport' />
    <meta name="viewport" content="width=device-width" />
    <!-- CSS Files -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet" />
    <link href="../assets/css/paper-dashboard.css?v=2.0.1" rel="stylesheet" />
    <link href="../assets/css/demo.css" rel="stylesheet" />
    <link href="../assets/css/custom.css" rel="stylesheet" />
</head>
<?php include '../includes/sidebar.php'; ?>
        <div class="main-panel">
            <?php include '../includes/navbar.php'; ?>
        
        <div class="content">
            <!-- Success/Error Messages -->
            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="nc-icon nc-check-2"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="nc-icon nc-simple-remove"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h5 class="card-title mb-0">
                                        <i class="nc-icon nc-settings-gear-65 text-primary"></i> 
                                        Add Work Packages & Activities
                                    </h5>
                                    <p class="card-category mb-0">
                                        Step 3: Define work packages and activities for 
                                        "<strong><?= htmlspecialchars($project['name']) ?></strong>"
                                    </p>
                                </div>
                                <div class="col-auto">
                                    <span class="badge badge-info">Step 3 of 3</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <form method="POST" action="" id="workPackagesForm">
                                
                                <div id="workPackagesContainer">
                                    <!-- Work Package Template (will be cloned by JavaScript) -->
                                    <div class="wp-container" data-wp-index="0">
                                        <div class="row mb-3">
                                            <div class="col-md-8">
                                                <h6 style="color: #51CACF; margin-bottom: 15px;">
                                                    📋 Work Package #<span class="wp-number">1</span>
                                                </h6>
                                            </div>
                                            <div class="col-md-4 text-right">
                                                <button type="button" class="remove-btn remove-wp" onclick="removeWorkPackage(this)" style="display: none;">
                                                    ❌ Remove WP
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label><strong>WP Number</strong> <span class="text-danger">*</span></label>
                                                    <input type="text" 
                                                           name="work_packages[0][wp_number]" 
                                                           class="form-control" 
                                                           placeholder="WP1" 
                                                           required>
                                                </div>
                                            </div>
                                            <div class="col-md-9">
                                                <div class="form-group">
                                                    <label><strong>Work Package Name</strong> <span class="text-danger">*</span></label>
                                                    <input type="text" 
                                                           name="work_packages[0][name]" 
                                                           class="form-control" 
                                                           placeholder="e.g., Project Management" 
                                                           required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label><strong>Description</strong></label>
                                            <textarea name="work_packages[0][description]" 
                                                      class="form-control" 
                                                      rows="3" 
                                                      placeholder="Describe the work package objectives and scope..."></textarea>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label><strong>Lead Partner Organization</strong></label>
                                                    <select name="work_packages[0][lead_partner_id]" class="form-control">
                                                        <option value="">Select Lead Partner Organization</option>
                                                        <?php foreach ($available_partners as $partner): ?>
                                                            <option value="<?= $partner['id'] ?>">
                                                                <?= htmlspecialchars($partner['name']) ?> 
                                                                (<?= htmlspecialchars($partner['country']) ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label><strong>Start Date</strong></label>
                                                    <input type="date" 
                                                           name="work_packages[0][start_date]" 
                                                           class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label><strong>End Date</strong></label>
                                                    <input type="date" 
                                                           name="work_packages[0][end_date]" 
                                                           class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label><strong>Budget (€)</strong></label>
                                                    <input type="number" 
                                                           name="work_packages[0][budget]" 
                                                           class="form-control" 
                                                           step="0.01" 
                                                           min="0" 
                                                           placeholder="0.00">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <hr style="border-color: #51CACF;">
                                        
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
                                                        <button type="button" class="remove-btn" onclick="removeActivity(this)" style="display: none;">
                                                            ❌ Remove
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
                                                                   placeholder="1.1">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-9">
                                                        <div class="form-group">
                                                            <label>Activity Name <span class="text-danger">*</span></label>
                                                            <input type="text" 
                                                                   name="work_packages[0][activities][0][name]" 
                                                                   class="form-control" 
                                                                   placeholder="e.g., Kick-off Meeting">
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label>Description</label>
                                                    <textarea name="work_packages[0][activities][0][description]" 
                                                              class="form-control" 
                                                              rows="2" 
                                                              placeholder="Describe the activity..."></textarea>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <div class="form-group">
                                                            <label>Responsible Partner Organization</label>
                                                            <select name="work_packages[0][activities][0][responsible_partner_id]" class="form-control">
                                                                <option value="">Select Responsible Organization</option>
                                                                <?php foreach ($available_partners as $partner): ?>
                                                                    <option value="<?= $partner['id'] ?>">
                                                                        <?= htmlspecialchars($partner['name']) ?>
                                                                        (<?= htmlspecialchars($partner['country']) ?>)
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <div class="form-group">
                                                            <label>Start Date</label>
                                                            <input type="date" 
                                                                   name="work_packages[0][activities][0][start_date]" 
                                                                   class="form-control">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <div class="form-group">
                                                            <label>End Date</label>
                                                            <input type="date" 
                                                                   name="work_packages[0][activities][0][end_date]" 
                                                                   class="form-control">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="form-group">
                                                            <label>Due Date</label>
                                                            <input type="date" 
                                                                   name="work_packages[0][activities][0][end_date]" 
                                                                   class="form-control">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <div class="form-group">
                                                            <label>Budget (€)</label>
                                                            <input type="number" 
                                                                   name="work_packages[0][activities][0][budget]" 
                                                                   class="form-control" 
                                                                   step="0.01" 
                                                                   min="0" 
                                                                   placeholder="0.00">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="add-activity-btn" onclick="addActivity(this)">
                                            ➕ Add Another Activity
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-center mb-3">
                                    <button type="button" class="btn btn-outline-primary" onclick="addWorkPackage()">
                                        ➕ Add Another Work Package
                                    </button>
                                </div>

                                <hr>

                                <div class="text-right">
                                    <a href="add-project-partners.php?project_id=<?= $project_id ?>" class="btn btn-secondary">
                                        <i class="nc-icon nc-minimal-left"></i> Back to Partners
                                    </a>
                                    <button type="button" class="btn btn-outline-primary" onclick="skipStep()">
                                        Skip for Now
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="nc-icon nc-check-2"></i> Save Wps & Go to Milestones
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php include '../includes/footer.php'; ?>