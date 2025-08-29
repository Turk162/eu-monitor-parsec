<?php
// ===================================================================
//  PAGE CONFIGURATION
// ===================================================================

// Set the title of the page
$page_title = 'Create New Project - EU Project Manager';

// TEMPORARY FIX: Override navbar title detection
$navbar_page_title = 'Create New Project';

// Specify the path to the page-specific CSS file
$page_css_path = '../assets/css/pages/create-project.css';

// Specify the path to the page-specific JS file  
$page_js_path = '../assets/js/pages/create-project.js';

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
//  INCLUDE HEADER
// ===================================================================
// The header includes session management, authentication checks,
// database connection, and defines common user variables.
// It will also use $page_title and $page_css_path to build the <head>.
// ===================================================================

require_once '../includes/header.php';

// ===================================================================
//  AUTHORIZATION CHECK
// ===================================================================

// Only super_admins and coordinators are allowed to create projects.
if (!in_array($user_role, ['super_admin', 'coordinator'])) {
    Flash::set('error', 'You do not have permission to create new projects.');
    header('Location: projects.php');
    exit;
}

// ===================================================================
//  DATABASE CONNECTION & INITIAL DATA
// ===================================================================

// Database connection (user_id and user_role are already available from header)
$database = new Database();
$conn = $database->connect();

// ===================================================================
//  FORM SUBMISSION HANDLER
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_project') {
    
    // --- 1. Sanitize and retrieve form data ---
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $program_type = $_POST['program_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $budget = (float)($_POST['budget'] ?? 0);
    $lead_partner_id = (int)($_POST['lead_partner_id'] ?? 0);
    $lead_partner_budget = (float)($_POST['lead_partner_budget'] ?? 0);
    // If the user is a super_admin, get coordinator from form. Otherwise, it's the current user.
    $coordinator_id = ($user_role === 'super_admin') ? (int)($_POST['coordinator_id'] ?? 0) : $user_id;
    
    // --- 2. Server-side validation ---
    $errors = [];
    if (empty($name)) $errors[] = "Project name is required.";
    if (empty($description)) $errors[] = "Project description is required.";
    if (empty($program_type)) $errors[] = "Programme type is required.";
    if (empty($start_date)) $errors[] = "Start date is required.";
    if (empty($end_date)) $errors[] = "End date is required.";
    if ($budget <= 0) $errors[] = "Total budget must be greater than 0.";
    if (!$lead_partner_id) $errors[] = "Lead partner organization is required.";
    if ($lead_partner_budget <= 0) $errors[] = "Lead partner budget must be greater than 0.";
    if ($lead_partner_budget > $budget) $errors[] = "Lead partner budget cannot exceed total project budget.";
    if (!$coordinator_id) $errors[] = "Project coordinator is required.";
    
    // Date validation
    if (!empty($start_date) && !empty($end_date)) {
        if (strtotime($end_date) <= strtotime($start_date)) {
            $errors[] = "End date must be after start date.";
        }
    }
    
    // --- 3. If no errors, save the project ---
    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Insert the main project record - CORRECTED COLUMN NAMES
            $project_sql = "INSERT INTO projects (name, description, program_type, start_date, end_date, 
                          budget, coordinator_id, status, created_at, updated_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'planning', NOW(), NOW())";
            
            $project_stmt = $conn->prepare($project_sql);
            $project_stmt->execute([
                sanitizeInput($name),
                sanitizeInput($description),
                $program_type,
                $start_date,
                $end_date,
                $budget,
                $coordinator_id
            ]);
            
            $project_id = $conn->lastInsertId();
            
            // Associate the lead partner with the project as 'coordinator' - CORRECTED COLUMN NAMES
            $partner_sql = "INSERT INTO project_partners (project_id, partner_id, role, budget_allocated, joined_at) 
                           VALUES (?, ?, 'coordinator', ?, NOW())";
            $partner_stmt = $conn->prepare($partner_sql);
            $partner_stmt->execute([$project_id, $lead_partner_id, $lead_partner_budget]);
            
            $conn->commit();
            
            // Success message and redirect to next step
            Flash::set('success', 'Project created successfully! You can now add the other partner organizations.');
            header('Location: add-project-partners.php?project_id=' . $project_id);
            exit;
            
        } catch (PDOException $e) {
            $conn->rollback();
            error_log("Create project error: " . $e->getMessage());
            Flash::set('error', 'An error occurred while creating the project. Please try again.');
        }
    } else {
        // Show validation errors
        Flash::set('error', implode('<br>', $errors));
    }
}

// ===================================================================
//  DATA FOR THE FORM
// ===================================================================

// Get all partner organizations for the lead partner dropdown
$partners_stmt = $conn->prepare("SELECT id, name, country FROM partners ORDER BY name");
$partners_stmt->execute();
$partners = $partners_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all coordinators (users with coordinator role) for super_admin selection
$coordinators = [];
if ($user_role === 'super_admin') {
    $coord_stmt = $conn->prepare("
        SELECT u.id, u.full_name, u.email, p.name as organization 
        FROM users u 
        LEFT JOIN partners p ON u.partner_id = p.id 
        WHERE u.role = 'coordinator' 
        ORDER BY u.full_name
    ");
    $coord_stmt->execute();
    $coordinators = $coord_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Programme types for European projects
$program_types = [
    'erasmus_plus' => 'Erasmus+ Programme',
    'horizon_europe' => 'Horizon Europe',
    'interreg' => 'Interreg',
    'life' => 'LIFE Programme',
    'creative_europe' => 'Creative Europe',
    'digital_europe' => 'Digital Europe Programme',
    'other' => 'Other EU Programme'
];

?>

<?php include '../includes/sidebar.php'; ?>

<div class="main-panel">
    <?php 
    // OVERRIDE navbar title for this page
    $navbar_page_title = 'Create New Project';
    include '../includes/navbar.php'; 
    ?>
    
    <!-- CONTENT -->
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
            <div class="col-md-12 offset-md-0">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="nc-icon nc-simple-add"></i> Create New Project
                        </h5>
                        <p class="card-category">Step 1 of 4: Basic Project Information</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="createProjectForm">
                            <input type="hidden" name="action" value="create_project">
                            
                            <!-- Project Details Section -->
                            <div class="form-section">
                                <h6><i class="nc-icon nc-paper"></i> Project Details</h6>
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="name">Project Name <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                                            <small class="form-text text-muted">Enter the full project title</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="program_type">Programme Type <span class="required">*</span></label>
                                            <select class="form-control" id="program_type" name="program_type" required>
                                                <option value="">Select Programme...</option>
                                                <?php foreach ($program_types as $key => $label): ?>
                                                    <option value="<?= $key ?>" <?= (($_POST['program_type'] ?? '') === $key) ? 'selected' : '' ?>>
                                                        <?= $label ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description">Project Description <span class="required">*</span></label>
                                    <textarea class="form-control" id="description" name="description" rows="4" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                    <small class="form-text text-muted">Provide a comprehensive description of the project objectives and scope</small>
                                </div>
                            </div>
                            
                            <!-- Timeline & Budget Section -->
                            <div class="form-section">
                                <h6><i class="nc-icon nc-calendar-60"></i> Timeline & Budget</h6>
                                
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="start_date">Start Date <span class="required">*</span></label>
                                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                                   value="<?= $_POST['start_date'] ?? '' ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="end_date">End Date <span class="required">*</span></label>
                                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                                   value="<?= $_POST['end_date'] ?? '' ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="budget">Total Project Budget (€) <span class="required">*</span></label>
                                            <input type="number" class="form-control" id="budget" name="budget" 
                                                   min="1" step="0.01" value="<?= $_POST['budget'] ?? '' ?>" required>
                                            <small class="form-text text-muted">Total budget for the entire project</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Lead Partner Section -->
                            <div class="form-section">
                                <h6><i class="nc-icon nc-single-02"></i> Lead Partner Organization</h6>
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="lead_partner_id">Lead Partner <span class="required">*</span></label>
                                            <select class="form-control" id="lead_partner_id" name="lead_partner_id" required>
                                                <option value="">Select Lead Partner...</option>
                                                <?php foreach ($partners as $partner): ?>
                                                    <option value="<?= $partner['id'] ?>" 
                                                            <?= (($_POST['lead_partner_id'] ?? '') == $partner['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($partner['name']) ?> (<?= htmlspecialchars($partner['country']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="form-text text-muted">The organization that will coordinate the project</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="lead_partner_budget">Lead Partner Budget (€) <span class="required">*</span></label>
                                            <input type="number" class="form-control" id="lead_partner_budget" name="lead_partner_budget" 
                                                   min="1" step="0.01" value="<?= $_POST['lead_partner_budget'] ?? '' ?>" required>
                                            <small class="form-text text-muted">Budget allocated to lead partner</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Coordinator Section (Only for Super Admin) -->
                            <?php if ($user_role === 'super_admin'): ?>
                            <div class="form-section">
                                <h6><i class="nc-icon nc-badge"></i> Project Coordinator</h6>
                                
                                <div class="form-group">
                                    <label for="coordinator_id">Project Coordinator <span class="required">*</span></label>
                                    <select class="form-control" id="coordinator_id" name="coordinator_id" required>
                                        <option value="">Select Coordinator...</option>
                                        <?php foreach ($coordinators as $coordinator): ?>
                                            <option value="<?= $coordinator['id'] ?>" 
                                                    <?= (($_POST['coordinator_id'] ?? '') == $coordinator['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($coordinator['full_name']) ?> 
                                                (<?= htmlspecialchars($coordinator['organization'] ?? 'No Organization') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Person responsible for overall project coordination</small>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Form Actions -->
                            <div class="form-group text-right">
                                <a href="projects.php" class="btn btn-secondary">
                                    <i class="nc-icon nc-simple-remove"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="nc-icon nc-check-2"></i> Create Project & Continue
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</div>