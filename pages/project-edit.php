<?php

// Page configuration (BEFORE including header)
$page_title = 'Edit Project - EU Project Manager';

// Include header (handles session, auth, database, user variables)
require_once '../includes/header.php';

// Get project ID from URL
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$project_id) {
    $_SESSION['error'] = "Project ID is required.";
    header('Location: projects.php');
    exit;
}

// Database connection
$database = new Database();
$conn = $database->connect();

// Get user info
$user_id = getUserId();
$user_role = getUserRole();

// Check if user can edit this project
$access_check = $conn->prepare("
    SELECT p.*, u.full_name as coordinator_name 
    FROM projects p 
    LEFT JOIN users u ON p.coordinator_id = u.id 
    WHERE p.id = ?
");
$access_check->execute([$project_id]);
$project = $access_check->fetch();

if (!$project) {
    $_SESSION['error'] = "Project not found.";
    header('Location: projects.php');
    exit;
}

// Check permissions - only super_admin or project coordinator can edit
if ($user_role !== 'super_admin' && $project['coordinator_id'] !== $user_id) {
    $_SESSION['error'] = "Access denied. Only project coordinators can edit projects.";
    header('Location: projects.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_basic_details':
                $name = sanitizeInput($_POST['name']);
                $description = sanitizeInput($_POST['description']);
                $program_type = sanitizeInput($_POST['program_type']);
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                $budget = (float)$_POST['budget'];
                $status = $_POST['status'];
                $coordinator_id = ($user_role === 'super_admin') ? (int)$_POST['coordinator_id'] : $project['coordinator_id'];
                
                // Validation
                $errors = [];
                if (empty($name)) $errors[] = "Project name is required";
                if (empty($description)) $errors[] = "Description is required";
                if (empty($program_type)) $errors[] = "Program type is required";
                if (empty($start_date)) $errors[] = "Start date is required";
                if (empty($end_date)) $errors[] = "End date is required";
                if ($budget <= 0) $errors[] = "Budget must be greater than 0";
                if ($start_date >= $end_date) $errors[] = "End date must be after start date";
                if (empty($coordinator_id)) $errors[] = "Coordinator is required";
                
                if (empty($errors)) {
                    $sql = "
                        UPDATE projects 
                        SET name = ?, description = ?, program_type = ?, start_date = ?, 
                            end_date = ?, budget = ?, status = ?, updated_at = NOW()";
                    
                    $params = [$name, $description, $program_type, $start_date, $end_date, $budget, $status];

                    if ($user_role === 'super_admin') {
                        $sql .= ", coordinator_id = ?";
                        $params[] = $coordinator_id;
                    }

                    $sql .= " WHERE id = ?";
                    $params[] = $project_id;

                    $stmt = $conn->prepare($sql);
                    
                    if ($stmt->execute($params)) {
                        $_SESSION['success'] = "Project details updated successfully!";
                        // Refresh project data
                        $access_check->execute([$project_id]);
                        $project = $access_check->fetch();
                    } else {
                        throw new Exception("Failed to update project details");
                    }
                } else {
                    $_SESSION['error'] = implode("<br>", $errors);
                }
                break;
                
case 'add_partner':
    $partner_id = (int)$_POST['partner_id'];
    $budget = (float)$_POST['budget'];

    // Security check: Prevent adding the coordinator as a regular partner
    $coordinator_info_stmt = $conn->prepare("SELECT u.partner_id FROM projects p JOIN users u ON p.coordinator_id = u.id WHERE p.id = ?");
    $coordinator_info_stmt->execute([$project_id]);
    $coordinator_partner_id = $coordinator_info_stmt->fetchColumn();

    if ($partner_id === $coordinator_partner_id) {
        $_SESSION['error'] = "The Lead Partner cannot be added as a regular partner.";
        break;
    }
    
    // Check if partner already exists
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM project_partners WHERE project_id = ? AND partner_id = ?");
    $check_stmt->execute([$project_id, $partner_id]);
    
    if ($check_stmt->fetch()['count'] > 0) {
        $_SESSION['error'] = "Partner is already assigned to this project.";
    } else {
        $insert_stmt = $conn->prepare("
            INSERT INTO project_partners (project_id, partner_id, role, budget_allocated) 
            VALUES (?, ?, 'partner', ?)
        ");
        
        if ($insert_stmt->execute([$project_id, $partner_id, $budget])) {
            $_SESSION['success'] = "Partner added successfully!";
        } else {
            throw new Exception("Failed to add partner");
        }
    }
    break;

case 'delete_partner':
    $partner_id = (int)$_POST['partner_id'];
    
    // Additional permission check
    if ($user_role === 'coordinator') {
        // Coordinators cannot delete other coordinators
        $role_check = $conn->prepare("SELECT role FROM project_partners WHERE project_id = ? AND partner_id = ?");
        $role_check->execute([$project_id, $partner_id]);
        $partner_role = $role_check->fetch()['role'] ?? '';
        
        if ($partner_role === 'coordinator') {
            $_SESSION['error'] = "You cannot remove other coordinators from the project.";
            break;
        }
    }
    
    $delete_stmt = $conn->prepare("DELETE FROM project_partners WHERE project_id = ? AND partner_id = ?");
    if ($delete_stmt->execute([$project_id, $partner_id])) {
        $_SESSION['success'] = "Partner removed successfully!";
    } else {
        throw new Exception("Failed to remove partner");
    }
    break;
case 'add_work_package':
                $wp_number = sanitizeInput($_POST['wp_number']);
                $wp_name = sanitizeInput($_POST['wp_name']);
                $wp_description = sanitizeInput($_POST['wp_description']);
                $lead_partner_id = (int)$_POST['lead_partner_id'];
                $wp_start_date = $_POST['wp_start_date'];
                $wp_end_date = $_POST['wp_end_date'];
                $wp_budget = (float)$_POST['wp_budget'];
                
                $wp_stmt = $conn->prepare("
                    INSERT INTO work_packages 
                    (project_id, wp_number, name, description, lead_partner_id, start_date, end_date, budget) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($wp_stmt->execute([$project_id, $wp_number, $wp_name, $wp_description, $lead_partner_id, $wp_start_date, $wp_end_date, $wp_budget])) {
                    $_SESSION['success'] = "Work package added successfully!";
                } else {
                    throw new Exception("Failed to add work package");
                }
                break;

            case 'add_activity':
                $wp_id = (int)$_POST['work_package_id'];
                $activity_number = sanitizeInput($_POST['activity_number']);
                $activity_name = sanitizeInput($_POST['name']);
                $activity_description = sanitizeInput($_POST['description']);
                $responsible_partner_id = (int)$_POST['responsible_partner_id'];
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                $due_date = $_POST['due_date'];
                $budget = (float)$_POST['budget'];

                $stmt = $conn->prepare("
                    INSERT INTO activities (work_package_id, project_id, activity_number, name, description, responsible_partner_id, start_date, end_date, due_date, budget)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                if ($stmt->execute([$wp_id, $project_id, $activity_number, $activity_name, $activity_description, $responsible_partner_id, $start_date, $end_date, $due_date, $budget])) {
                    $_SESSION['success'] = "Activity added successfully!";
                } else {
                    throw new Exception("Failed to add activity");
                }
                break;

            case 'update_activity':
                $activity_id = (int)$_POST['activity_id'];
                $activity_number = sanitizeInput($_POST['activity_number']);
                $activity_name = sanitizeInput($_POST['name']);
                $activity_description = sanitizeInput($_POST['description']);
                $responsible_partner_id = (int)$_POST['responsible_partner_id'];
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                $due_date = $_POST['due_date'];
                $budget = (float)$_POST['budget'];

                $stmt = $conn->prepare("
                    UPDATE activities
                    SET activity_number = ?, name = ?, description = ?, responsible_partner_id = ?, start_date = ?, end_date = ?, due_date = ?, budget = ?
                    WHERE id = ?
                ");

                if ($stmt->execute([$activity_number, $activity_name, $activity_description, $responsible_partner_id, $start_date, $end_date, $due_date, $budget, $activity_id])) {
                    $_SESSION['success'] = "Activity updated successfully!";
                } else {
                    throw new Exception("Failed to update activity");
                }
                break;

            case 'delete_activity':
                $activity_id = (int)$_POST['activity_id'];
                $stmt = $conn->prepare("DELETE FROM activities WHERE id = ?");
                if ($stmt->execute([$activity_id])) {
                    $_SESSION['success'] = "Activity deleted successfully!";
                } else {
                    throw new Exception("Failed to delete activity");
                }
                break;

            

            case 'add_milestone':
                $name = sanitizeInput($_POST['name']);
                $due_date = $_POST['due_date'];
                $description = sanitizeInput($_POST['description']);
                $work_package_id = !empty($_POST['work_package_id']) ? (int)$_POST['work_package_id'] : null;

                $stmt = $conn->prepare("
                    INSERT INTO milestones (project_id, work_package_id, name, description, due_date, status)
                    VALUES (?, ?, ?, ?, ?, 'pending')
                ");

                if ($stmt->execute([$project_id, $work_package_id, $name, $description, $due_date])) {
                    $_SESSION['success'] = "Milestone added successfully!";
                } else {
                    throw new Exception("Failed to add milestone");
                }
                break;

            case 'update_milestone':
                $milestone_id = (int)$_POST['milestone_id'];
                $name = sanitizeInput($_POST['name']);
                $due_date = $_POST['due_date'];
                $description = sanitizeInput($_POST['description']);
                $work_package_id = !empty($_POST['work_package_id']) ? (int)$_POST['work_package_id'] : null;
                $status = sanitizeInput($_POST['status']);
                $completed_date = !empty($_POST['completed_date']) ? $_POST['completed_date'] : null;

                $stmt = $conn->prepare("
                    UPDATE milestones
                    SET name = ?, description = ?, due_date = ?, work_package_id = ?, status = ?, completed_date = ?
                    WHERE id = ? AND project_id = ?
                ");

                if ($stmt->execute([$name, $description, $due_date, $work_package_id, $status, $completed_date, $milestone_id, $project_id])) {
                    $_SESSION['success'] = "Milestone updated successfully!";
                } else {
                    throw new Exception("Failed to update milestone");
                }
                break;

            case 'delete_milestone':
                $milestone_id = (int)$_POST['milestone_id'];
                $stmt = $conn->prepare("DELETE FROM milestones WHERE id = ? AND project_id = ?");
                if ($stmt->execute([$milestone_id, $project_id])) {
                    $_SESSION['success'] = "Milestone deleted successfully!";
                } else {
                    throw new Exception("Failed to delete milestone");
                }
                break;

            case 'update_work_package':
                $wp_id = (int)$_POST['wp_id'];
                $wp_number = sanitizeInput($_POST['wp_number']);
                $wp_name = sanitizeInput($_POST['wp_name']);
                $wp_description = sanitizeInput($_POST['wp_description']);
                $lead_partner_id = (int)$_POST['lead_partner_id'];
                $wp_start_date = $_POST['wp_start_date'];
                $wp_end_date = $_POST['wp_end_date'];
                $wp_budget = (float)$_POST['wp_budget'];
                $wp_status = sanitizeInput($_POST['status']);
                $wp_progress = (float)$_POST['progress'];

                $stmt = $conn->prepare("
                    UPDATE work_packages
                    SET wp_number = ?, name = ?, description = ?, lead_partner_id = ?, start_date = ?, end_date = ?, budget = ?, status = ?, progress = ?
                    WHERE id = ? AND project_id = ?
                ");

                if ($stmt->execute([$wp_number, $wp_name, $wp_description, $lead_partner_id, $wp_start_date, $wp_end_date, $wp_budget, $wp_status, $wp_progress, $wp_id, $project_id])) {
                    $_SESSION['success'] = "Work Package updated successfully!";
                } else {
                    throw new Exception("Failed to update Work Package");
                }
                break;
                
            case 'auto_save':
                // Handle auto-save via AJAX
                $field = $_POST['field'];
                $value = sanitizeInput($_POST['value']);
                
                $allowed_fields = ['name', 'description', 'program_type', 'budget'];
                if (in_array($field, $allowed_fields)) {
                    $stmt = $conn->prepare("UPDATE projects SET $field = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$value, $project_id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Auto-saved']);
                    exit;
                }
                break;
        }
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollback();
        }
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    // 1. Nel blocco di gestione del POST (intorno alla riga dove vengono gestiti gli altri campi)
if (isset($_POST['google_groups_url'])) {
    $google_groups_url = trim($_POST['google_groups_url']);
    
    // Validazione URL Google Groups (opzionale)
    if (!empty($google_groups_url) && !filter_var($google_groups_url, FILTER_VALIDATE_URL)) {
        $_SESSION['error'] = "URL Google Groups non valido";
    } elseif (!empty($google_groups_url) && !str_contains($google_groups_url, 'groups.google.com')) {
        $_SESSION['error'] = "Inserire un URL Google Groups valido (groups.google.com)";
    } else {
        // Aggiorna il database
        $stmt = $conn->prepare("UPDATE projects SET google_groups_url = ? WHERE id = ?");
        if ($stmt->execute([$google_groups_url, $project_id])) {
            $_SESSION['success'] = "URL Google Groups aggiornato con successo";
        } else {
            $_SESSION['error'] = "Errore nell'aggiornamento URL Google Groups";
        }
    }
}

$project_query = "SELECT *, google_groups_url FROM projects WHERE id = ?";


}

// Get project partners
$partners_stmt = $conn->prepare("
    SELECT pp.*, p.name as organization, p.country, p.organization_type,
           u.full_name, u.email
    FROM project_partners pp
    JOIN partners p ON pp.partner_id = p.id
    LEFT JOIN users u ON u.partner_id = p.id
    WHERE pp.project_id = ?
    ORDER BY pp.role DESC, p.name
");
$partners_stmt->execute([$project_id]);
$all_project_partners = $partners_stmt->fetchAll();

// Separate Lead Partner from other partners
$lead_partner = null;
$project_partners = [];
foreach ($all_project_partners as $partner) {
    if ($partner['role'] === 'coordinator') {
        $lead_partner = $partner;
    } else {
        $project_partners[] = $partner;
    }
}

// Get the ID of the coordinator's partner organization
$coordinator_partner_id = null;
if ($lead_partner) {
    $coordinator_partner_id = $lead_partner['partner_id'];
}


// Get available partners for selection, excluding the coordinator
$available_partners_query = "
    SELECT p.id, p.name as organization, p.country, p.organization_type
    FROM partners p
    WHERE p.id NOT IN (
        SELECT DISTINCT partner_id FROM project_partners WHERE project_id = ? AND partner_id IS NOT NULL
    )
";


// Query per ottenere TUTTI i partner del progetto (incluso coordinator)
$project_partners_stmt = $conn->prepare("
    SELECT 
        pp.partner_id, 
        pp.role, 
        pp.budget_allocated, 
        p.name, 
        p.country, 
        p.organization_type
    FROM project_partners pp
    INNER JOIN partners p ON pp.partner_id = p.id
    WHERE pp.project_id = ?
    ORDER BY 
        CASE WHEN pp.role = 'coordinator' THEN 0 ELSE 1 END,
        p.name ASC
");
$project_partners_stmt->execute([$project_id]);
$project_partners = $project_partners_stmt->fetchAll(PDO::FETCH_ASSOC);

// Trova il coordinator attuale
$current_coordinator = null;
foreach ($project_partners as $partner) {
    if ($partner['role'] === 'coordinator') {
        $current_coordinator = $partner['partner_id'];
        break;
    }
}

// Also exclude the coordinator ID if it exists
if ($coordinator_partner_id) {
    $available_partners_query .= " AND p.id != ?";
}

$available_partners_query .= " ORDER BY p.name";

$available_partners_stmt = $conn->prepare($available_partners_query);

$params = [$project_id];
if ($coordinator_partner_id) {
    $params[] = $coordinator_partner_id;
}

$available_partners_stmt->execute($params);
$available_partners = $available_partners_stmt->fetchAll();

// Get available coordinators for the dropdown
$coordinators = [];
if ($user_role === 'super_admin') {
    $coord_stmt = $conn->prepare("
        SELECT u.id, u.full_name, p.name as organization
        FROM users u
        LEFT JOIN partners p ON u.partner_id = p.id
        WHERE u.role IN ('coordinator', 'super_admin') AND u.is_active = 1
        ORDER BY u.full_name
    ");
    $coord_stmt->execute();
    $coordinators = $coord_stmt->fetchAll();
}

// Get work packages and their activities
$wp_stmt = $conn->prepare("
    SELECT wp.*, u.full_name as lead_partner_name, p.name as lead_organization
    FROM work_packages wp
    LEFT JOIN users u ON wp.lead_partner_id = u.id
    LEFT JOIN partners p ON u.partner_id = p.id
    WHERE wp.project_id = ?
    ORDER BY wp.wp_number
");
$wp_stmt->execute([$project_id]);
$work_packages = $wp_stmt->fetchAll();

// For each work package, get its activities
$activities_stmt = $conn->prepare("
    SELECT a.*, p.name as responsible_partner_name
    FROM activities a
    LEFT JOIN partners p ON a.responsible_partner_id = p.id
    WHERE a.work_package_id = ?
    ORDER BY a.activity_number
");

foreach ($work_packages as $key => $wp) {
    $activities_stmt->execute([$wp['id']]);
    $work_packages[$key]['activities'] = $activities_stmt->fetchAll();
}

// Get project milestones
$milestones_stmt = $conn->prepare("
    SELECT m.*, wp.wp_number, wp.name as wp_name
    FROM milestones m
    LEFT JOIN work_packages wp ON m.work_package_id = wp.id
    WHERE m.project_id = ?
    ORDER BY m.due_date ASC
");
$milestones_stmt->execute([$project_id]);
$milestones = $milestones_stmt->fetchAll();

// Get project files
$files_stmt = $conn->prepare("
    SELECT uf.*, u.full_name as uploaded_by_name
    FROM uploaded_files uf
    LEFT JOIN activity_reports ar ON uf.report_id = ar.id
    LEFT JOIN activities a ON ar.activity_id = a.id
    LEFT JOIN users u ON uf.uploaded_by = u.id
    WHERE a.work_package_id IN (
        SELECT id FROM work_packages WHERE project_id = ?
    )
    ORDER BY uf.uploaded_at DESC
");
$files_stmt->execute([$project_id]);
$project_files = $files_stmt->fetchAll();

// European program types
$program_types = [
    'erasmus_plus' => 'Erasmus+',
    'horizon_europe' => 'Horizon Europe',
    'interreg' => 'Interreg',
    'life' => 'LIFE Programme',
    'creative_europe' => 'Creative Europe',
    'eu_citizenship' => 'Europe for Citizens',
    'digital_europe' => 'Digital Europe Programme',
    'cerv' => 'Citizens, Equality, Rights and Values (CERV)',
    'other' => 'Other EU Programme'
];

// Project status options
$status_options = [
    'planning' => 'Planning',
    'active' => 'Active',
    'suspended' => 'Suspended',
    'completed' => 'Completed'
];
?>

<!-- MAIN CONTENT after header -->
<?php include '../includes/sidebar.php'; ?>
<?php include '../includes/navbar.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="../assets/js/pages/project-edit.js"></script>
<link rel="stylesheet" href="../assets/css/pages/project-edit.css">

<div class="content">
    <div class="row">
        <div class="col-md-12">
            
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
            
            <!-- Project Header -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title mb-1">
                            <i class="nc-icon nc-settings-gear-65"></i> 
                            Edit Project: <?= htmlspecialchars($project['name']) ?>
                        </h4>
                        <p class="card-category mb-0">
                            <span class="badge badge-status status-<?= $project['status'] ?>">
                                <?= ucfirst($project['status']) ?>
                            </span>
                            <span class="ml-2">
                                <i class="nc-icon nc-calendar-60"></i>
                                <?= date('M j, Y', strtotime($project['start_date'])) ?> - 
                                <?= date('M j, Y', strtotime($project['end_date'])) ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <a href="project-detail.php?id=<?= $project_id ?>" class="btn btn-info btn-sm">
                            <i class="nc-icon nc-zoom-split"></i> View Details
                        </a>
                        <a href="projects.php" class="btn btn-secondary btn-sm">
                            <i class="nc-icon nc-minimal-left"></i> Back to Projects
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Auto-save indicator -->
            <div class="auto-save-indicator" id="autoSaveIndicator">
                <i class="nc-icon nc-check-2"></i> Auto-saved
            </div>
            
            <!-- Tabbed Interface -->
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs" id="projectTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="basic-tab" data-toggle="tab" href="#basic" role="tab">
                                <i class="nc-icon nc-paper"></i> Basic Details
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="partners-tab" data-toggle="tab" href="#partners" role="tab">
                                <i class="nc-icon nc-single-02"></i> Partners
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="workpackages-tab" data-toggle="tab" href="#workpackages" role="tab">
                                <i class="nc-icon nc-bullet-list-67"></i> Work Packages
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="files-tab" data-toggle="tab" href="#files" role="tab">
                                <i class="nc-icon nc-cloud-upload-94"></i> Documents
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="milestones-tab" data-toggle="tab" href="#milestones" role="tab">
                                <i class="nc-icon nc-trophy"></i> Milestones
                            </a>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="projectTabsContent">
                        
                        <!-- BASIC DETAILS TAB -->
                        <div class="tab-pane fade show active" id="basic" role="tabpanel">
                            <form method="POST" action="" id="basicDetailsForm">
                                <input type="hidden" name="action" value="update_basic_details">
                                
                                <div class="form-section">
                                    <h6>
                                        <span class="section-icon">
                                            <i class="nc-icon nc-paper"></i>
                                        </span>
                                        Project Information
                                    </h6>
                                    
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="form-group">
                                                <label for="name" class="required-field">Project Name</label>
                                                <input type="text" class="form-control auto-save" id="name" name="name" 
                                                       value="<?= htmlspecialchars($project['name']) ?>" required>
                                                <small class="form-text text-muted">Enter a clear, descriptive project name</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="status" class="required-field">Project Status</label>
                                                <select class="form-control" id="status" name="status" required>
                                                    <?php foreach ($status_options as $value => $label): ?>
                                                        <option value="<?= $value ?>" <?= $project['status'] === $value ? 'selected' : '' ?>>
                                                            <?= $label ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="description" class="required-field">Project Description</label>
                                        <textarea class="form-control auto-save" id="description" name="description" rows="4" required><?= htmlspecialchars($project['description']) ?></textarea>
                                        <small class="form-text text-muted">Provide a comprehensive overview of project objectives and activities</small>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="program_type" class="required-field">EU Programme</label>
                                                <select class="form-control auto-save" id="program_type" name="program_type" required>
                                                    <option value="">Select programme...</option>
                                                    <?php foreach ($program_types as $value => $label): ?>
                                                        <option value="<?= $value ?>" <?= $project['program_type'] === $value ? 'selected' : '' ?>>
                                                            <?= $label ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="budget" class="required-field">Total Budget (€)</label>
                                                <input type="number" class="form-control auto-save" id="budget" name="budget" 
                                                       value="<?= $project['budget'] ?>" step="0.01" min="0" required>
                                                <small class="form-text text-muted">Total project budget in Euro</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                <!-- 3. SEZIONE HTML DA AGGIUNGERE NEL FORM (dopo la sezione budget o dove preferisci) -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="nc-icon nc-chat-33"></i>
                    Google Groups Integration
                </h5>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="google_groups_url">URL Google Groups</label>
                    <input type="url" 
                           class="form-control" 
                           id="google_groups_url" 
                           name="google_groups_url"
                           value="<?= htmlspecialchars($project['google_groups_url'] ?? '') ?>"
                           placeholder="https://groups.google.com/g/your-group-name">
                    <small class="form-text text-muted">
                        Inserisci l'URL del Google Group associato a questo progetto. 
                        <br>Esempio: https://groups.google.com/g/bridge-project-team
                    </small>
                </div>
                
                <?php if (!empty($project['google_groups_url'])): ?>
                    <div class="form-group">
                        <a href="<?= htmlspecialchars($project['google_groups_url']) ?>" 
                           target="_blank" 
                           class="btn btn-outline-primary btn-sm">
                            <i class="nc-icon nc-zoom-split"></i>
                            Visualizza Google Group
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
                                    <?php if ($user_role === 'super_admin'): ?>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="coordinator_id" class="required-field">Project Coordinator</label>
                                                <select class="form-control" id="coordinator_id" name="coordinator_id" required>
                                                    <?php foreach ($coordinators as $coordinator): ?>
                                                        <option value="<?= $coordinator['id'] ?>" <?= $project['coordinator_id'] == $coordinator['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($coordinator['full_name'] . ' (' . $coordinator['organization'] . ')') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="form-text text-muted">Assign a new coordinator for the project.</small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                </div>
                                
                                <div class="form-section">
                                    <h6>
                                        <span class="section-icon">
                                            <i class="nc-icon nc-calendar-60"></i>
                                        </span>
                                        Project Timeline
                                    </h6>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="start_date" class="required-field">Start Date</label>
                                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                                       value="<?= $project['start_date'] ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="end_date" class="required-field">End Date</label>
                                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                                       value="<?= $project['end_date'] ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="nc-icon nc-bulb-63"></i>
                                        <strong>Project Duration:</strong>
                                        <span id="projectDuration">
                                            <?php
                                            $start = new DateTime($project['start_date']);
                                            $end = new DateTime($project['end_date']);
                                            $interval = $start->diff($end);
                                            echo $interval->days . ' days (' . round($interval->days / 30) . ' months)';
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="form-group text-right">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="nc-icon nc-check-2"></i> Update Project Details
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- PARTNERS TAB -->
                        <div class="tab-pane fade" id="partners" role="tabpanel">
                            <div class="form-section">
                                <h6>
                                    <span class="section-icon">
                                        <i class="nc-icon nc-single-02"></i>
                                    </span>
                                    Lead Partner
                                </h6>
                                <?php if ($lead_partner): ?>
                                <div class="row">
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="partner-card selected">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-1"><?= htmlspecialchars($lead_partner['organization']) ?></h6>
                                                <span class="badge badge-primary">Lead Partner</span>
                                            </div>
                                            <p class="text-muted mb-1">
                                                <i class="nc-icon nc-world-2"></i>
                                                <?= htmlspecialchars($lead_partner['country'] ?? 'No country') ?>
                                            </p>
                                            <p class="text-muted mb-0">
                                                <i class="nc-icon nc-money-coins"></i>
                                                Budget: €<?= number_format($lead_partner['budget_allocated'] ?? 0, 2) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">Lead partner not assigned.</div>
                                <?php endif; ?>
                            </div>

                            <div class="form-section">
                                <h6>
                                    <span class="section-icon">
                                        <i class="nc-icon nc-badge"></i>
                                    </span>
                                    Project Partners
                                </h6>
                                
                                <div class="row">
                                    <?php if (empty($project_partners)): ?>
                                        <div class="col-12">
                                            <div class="alert alert-info">
                                                <i class="nc-icon nc-alert-circle-i"></i>
                                                No other partners assigned to this project yet.
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($project_partners as $partner): ?>
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div class="partner-card">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="mb-1"><?= htmlspecialchars($partner['name']) ?></h6>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span class="badge badge-secondary">Partner</span>
                                                            <button class="btn btn-sm btn-danger btn-delete-partner" 
                                                                    data-partner-id="<?= $partner['partner_id'] ?>"
                                                                    data-partner-name="<?= htmlspecialchars($partner['name']) ?>"
                                                                    title="Remove Partner">
                                                                <i class="nc-icon nc-simple-remove"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <p class="text-muted mb-1">
                                                        <i class="nc-icon nc-world-2"></i>
                                                        <?= htmlspecialchars($partner['country'] ?? 'No country') ?>
                                                    </p>
                                                    <p class="text-muted mb-0">
                                                        <i class="nc-icon nc-money-coins"></i>
                                                        Budget: €<?= number_format($partner['budget_allocated'] ?? 0, 2) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-section">
    <h6>
        <span class="section-icon">
            <i class="nc-icon nc-simple-add"></i>
        </span>
        Add New Partner
    </h6>
    
    <?php if (empty($available_partners)): ?>
        <div class="alert alert-info">
            <i class="nc-icon nc-alert-circle-i"></i>
            All available partners are already assigned to this project.
        </div>
    <?php else: ?>
        <form method="POST" action="" id="addPartnerForm">
            <input type="hidden" name="action" value="add_partner">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="partner_id" class="required-field">Select Partner</label>
                        <select class="form-control" id="partner_id" name="partner_id" required>
                            <option value="">Choose partner organization...</option>
                            <?php foreach ($available_partners as $partner): ?>
                                <option value="<?= $partner['id'] ?>">
                                    <?= htmlspecialchars($partner['organization']) ?> 
                                    (<?= htmlspecialchars($partner['country']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="budget">Budget Allocation (€)</label>
                        <input type="number" class="form-control" id="budget" name="budget" 
                               step="0.01" min="0" placeholder="0.00">
                        <small class="form-text text-muted">Leave empty for no budget allocation</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="form-control btn btn-primary">
                            <i class="nc-icon nc-simple-add"></i> Add
                        </button>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>
                        </div>
                        
                        <!-- WORK PACKAGES TAB -->
                        <div class="tab-pane fade" id="workpackages" role="tabpanel">
                            <div class="form-section">
                                <h6>
                                    <span class="section-icon">
                                        <i class="nc-icon nc-bullet-list-67"></i>
                                    </span>
                                    Current Work Packages
                                </h6>
                                
                                <?php if (empty($work_packages)): ?>
                                    <div class="alert alert-info">
                                        <i class="nc-icon nc-alert-circle-i"></i>
                                        No work packages created yet. Add your first work package below.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($work_packages as $wp): ?>
                                        <div class="wp-card">
                                            <div class="wp-header">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span><?= htmlspecialchars($wp['wp_number']) ?>: <?= htmlspecialchars($wp['name']) ?></span>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="badge badge-light">€<?= number_format($wp['budget'], 2) ?></span>
                                                        <button class="btn btn-sm btn-outline-light btn-edit-wp" data-wp-id="<?= $wp['id'] ?>">Edit</button>
                                                        <button class="btn btn-sm btn-danger btn-delete-wp" data-wp-id="<?= $wp['id'] ?>" data-wp-name="<?= htmlspecialchars($wp['name']) ?>">Delete</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="wp-body">
                                                <p class="mb-2"><?= htmlspecialchars($wp['description']) ?></p>
                                                <!-- Activities Section -->
                                                <div class="activities-section mt-3">
                                                    <h6>Activities</h6>
                                                    <?php if (empty($wp['activities'])) : ?>
                                                        <p class="text-muted">No activities for this work package yet.</p>
                                                    <?php else: ?>
                                                        <table class="table table-sm">
                                                            <tbody>
                                                            <?php foreach ($wp['activities'] as $activity): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($activity['activity_number']) ?></td>
                                                                    <td><?= htmlspecialchars($activity['name']) ?></td>
                                                                    <td><?= htmlspecialchars($activity['responsible_partner_name']) ?></td>
                                                                    <td>
                                                                        <button class="btn btn-sm btn-info btn-edit-activity" data-activity-id="<?= $activity['id'] ?>">Edit</button>
                                                                        <button class="btn btn-sm btn-danger btn-delete-activity" data-activity-id="<?= $activity['id'] ?>">Delete</button>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-primary btn-add-activity" data-wp-id="<?= $wp['id'] ?>">+ Add Activity</button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-section">
                                <h6>
                                    <span class="section-icon">
                                        <i class="nc-icon nc-simple-add"></i>
                                    </span>
                                    Add New Work Package
                                </h6>
                                
                                <form method="POST" action="" id="workPackageForm">
                                    <input type="hidden" name="action" value="add_work_package">
                                    
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="wp_number" class="required-field">WP Number</label>
                                                <input type="text" class="form-control" id="wp_number" name="wp_number" 
                                                       placeholder="WP1" required>
                                                <small class="form-text text-muted">e.g., WP1, WP2, etc.</small>
                                            </div>
                                        </div>
                                        <div class="col-md-9">
                                            <div class="form-group">
                                                <label for="wp_name" class="required-field">Work Package Name</label>
                                                <input type="text" class="form-control" id="wp_name" name="wp_name" 
                                                       placeholder="Project Management" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="wp_description" class="required-field">Description</label>
                                        <textarea class="form-control" id="wp_description" name="wp_description" 
                                                  rows="3" placeholder="Detailed description of work package objectives and activities" required></textarea>
                                    </div>
                                    
                                  <div class="row">
   <div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label for="lead_partner_id" class="required-field">Lead Partner</label>
            <select class="form-control" id="lead_partner_id" name="lead_partner_id" required>
                <option value="">Select lead partner...</option>
                <?php if (empty($project_partners)): ?>
                    <option value="" disabled>No partners found for this project</option>
                <?php else: ?>
                    <?php foreach ($project_partners as $partner): ?>
                        <option value="<?= $partner['partner_id'] ?>" 
                                <?= ($partner['partner_id'] == $current_coordinator) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($partner['name']) ?>
                            (<?= htmlspecialchars($partner['country']) ?>)
                            <?php if ($partner['role'] === 'coordinator'): ?>
                                - <span style="color: #51CACF; font-weight: bold;">COORDINATOR</span>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <!-- DEBUG INFO -->
            <small class="text-muted">
                Found <?= count($project_partners) ?> partners. 
                Current coordinator ID: <?= $current_coordinator ?: 'None' ?>
            </small>
        </div>
    </div>
</div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="wp_budget" class="required-field">Budget (€)</label>
                                                <input type="number" class="form-control" id="wp_budget" name="wp_budget" 
                                                       step="0.01" min="0" placeholder="0.00" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>&nbsp;</label>
                                                <div class="form-control-static">
                                                    <small class="text-muted">
                                                        <i class="nc-icon nc-money-coins"></i>
                                                        Total Budget: €<?= number_format($project['budget'], 2) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="wp_start_date" class="required-field">Start Date</label>
                                                <input type="date" class="form-control" id="wp_start_date" name="wp_start_date" 
                                                       min="<?= $project['start_date'] ?>" max="<?= $project['end_date'] ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="wp_end_date" class="required-field">End Date</label>
                                                <input type="date" class="form-control" id="wp_end_date" name="wp_end_date" 
                                                       min="<?= $project['start_date'] ?>" max="<?= $project['end_date'] ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group text-right">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="nc-icon nc-simple-add"></i> Add Work Package
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- DOCUMENTS TAB -->
                        <div class="tab-pane fade" id="files" role="tabpanel">
                            <div class="form-section">
                                <h6>
                                    <span class="section-icon">
                                        <i class="nc-icon nc-cloud-upload-94"></i>
                                    </span>
                                    Project Documents
                                </h6>
                                
                                <div class="file-upload-area" onclick="document.getElementById('fileInput').click()">
                                    <i class="nc-icon nc-cloud-upload-94" style="font-size: 48px; color: #51CACF; margin-bottom: 15px;"></i>
                                    <h5>Drop files here or click to upload</h5>
                                    <p class="text-muted">Supported formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (Max 10MB)</p>
                                    <input type="file" id="fileInput" multiple style="display: none;" 
                                           accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                </div>
                                
                                <div id="uploadProgress" style="display: none;" class="mt-3">
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <small class="text-muted">Uploading files...</small>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h6>
                                    <span class="section-icon">
                                        <i class="nc-icon nc-folder-17"></i>
                                    </span>
                                    Uploaded Documents
                                </h6>
                                
                                <?php if (empty($project_files)): ?>
                                    <div class="alert alert-info">
                                        <i class="nc-icon nc-alert-circle-i"></i>
                                        No documents uploaded yet. Use the upload area above to add project files.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead class="text-primary">
                                                <tr>
                                                    <th><i class="nc-icon nc-paper"></i> File Name</th>
                                                    <th><i class="nc-icon nc-ruler-pencil"></i> Size</th>
                                                    <th><i class="nc-icon nc-single-02"></i> Uploaded By</th>
                                                    <th><i class="nc-icon nc-calendar-60"></i> Date</th>
                                                    <th class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($project_files as $file): ?>
                                                    <tr>
                                                        <td>
                                                            <i class="nc-icon nc-paper"></i>
                                                            <?= htmlspecialchars($file['original_filename']) ?>
                                                        </td>
                                                        <td><?= formatFileSize($file['file_size'] ?? 0) ?></td>
                                                        <td><?= htmlspecialchars($file['uploaded_by_name'] ?? 'Unknown') ?></td>
                                                        <td><?= date('M j, Y', strtotime($file['uploaded_at'])) ?></td>
                                                        <td class="text-center">
                                                            <a href="<?= $file['file_path'] ?>" target="_blank" 
                                                               class="btn btn-sm btn-info" title="View/Download">
                                                                <i class="nc-icon nc-zoom-split"></i>
                                                            </a>
                                                            <button class="btn btn-sm btn-danger btn-delete-file" 
                                                                    data-file-id="<?= $file['id'] ?>" title="Delete">
                                                                <i class="nc-icon nc-simple-remove"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- MILESTONES TAB -->
                        <div class="tab-pane fade" id="milestones" role="tabpanel">
                            <div class="form-section">
                                <h6>
                                    <span class="section-icon">
                                        <i class="nc-icon nc-trophy"></i>
                                    </span>
                                    Current Milestones
                                </h6>
                                
                                <?php if (empty($milestones)): ?>
                                    <div class="alert alert-info">
                                        <i class="nc-icon nc-alert-circle-i"></i>
                                        No milestones defined for this project yet.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead class="text-primary">
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Due Date</th>
                                                    <th>Work Package</th>
                                                    <th>Status</th>
                                                    <th class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($milestones as $milestone): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($milestone['name']) ?></td>
                                                        <td><?= formatDate($milestone['due_date']) ?></td>
                                                        <td><?= htmlspecialchars($milestone['wp_number'] ? $milestone['wp_number'] . ': ' . $milestone['wp_name'] : 'N/A') ?></td>
                                                        <td><?= ucfirst($milestone['status']) ?></td>
                                                        <td class="text-center">
                                                            <button class="btn btn-sm btn-info btn-edit-milestone" data-milestone-id="<?= $milestone['id'] ?>">Edit</button>
                                                            <button class="btn btn-sm btn-danger btn-delete-milestone" data-milestone-id="<?= $milestone['id'] ?>">Delete</button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-section">
                                <h6>
                                    <span class="section-icon">
                                        <i class="nc-icon nc-simple-add"></i>
                                    </span>
                                    Add New Milestone
                                </h6>
                                <form method="POST" action="" id="addMilestoneForm">
                                    <input type="hidden" name="action" value="add_milestone">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="milestone_name" class="required-field">Milestone Name</label>
                                                <input type="text" class="form-control" id="milestone_name" name="name" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="milestone_due_date" class="required-field">Due Date</label>
                                                <input type="date" class="form-control" id="milestone_due_date" name="due_date" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="milestone_description">Description</label>
                                        <textarea class="form-control" id="milestone_description" name="description" rows="2"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="milestone_wp">Link to Work Package (Optional)</label>
                                        <select class="form-control" id="milestone_wp" name="work_package_id">
                                            <option value="">-- No specific WP --</option>
                                            <?php foreach ($work_packages as $wp): ?>
                                                <option value="<?= $wp['id'] ?>"><?= htmlspecialchars($wp['wp_number'] . ': ' . $wp['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group text-right">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="nc-icon nc-simple-add"></i> Add Milestone
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                    </div> <!-- End tab-content -->
                </div> <!-- End card-body -->
            </div> <!-- End card -->
        </div> <!-- End col-md-12 -->
    </div> <!-- End row -->
</div> <!-- End content -->

<!-- Add Activity Modal -->
<div class="modal fade" id="addActivityModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_activity">
                <input type="hidden" id="add_work_package_id" name="work_package_id">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Activity</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Activity Number</label>
                                <input type="text" name="activity_number" class="form-control" placeholder="e.g., 1.1">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Activity Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Responsible Partner</label>
                                <select name="responsible_partner_id" class="form-control">
                                    <option value="">Select Partner...</option>
                                    <?php foreach ($all_project_partners as $p): ?>
                                        <option value="<?= $p['partner_id'] ?>"><?= htmlspecialchars($p['organization']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Budget (€)</label>
                                <input type="number" name="budget" class="form-control" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4"><label>Start Date</label><input type="date" name="start_date" class="form-control"></div>
                        <div class="col-md-4"><label>End Date</label><input type="date" name="end_date" class="form-control"></div>
                        <div class="col-md-4"><label>Due Date</label><input type="date" name="due_date" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Activity</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Activity Modal -->
<div class="modal fade" id="editActivityModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_activity">
                <input type="hidden" id="edit_activity_id" name="activity_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Activity</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="editActivityModalBody">
                    <!-- Form fields will be loaded here via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Activity</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Milestone Modal -->
<div class="modal fade" id="editMilestoneModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_milestone">
                <input type="hidden" id="edit_milestone_id" name="milestone_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Milestone</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="editMilestoneModalBody">
                    <!-- Form fields will be loaded here via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Milestone</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Work Package Modal -->
<div class="modal fade" id="editWorkPackageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_work_package">
                <input type="hidden" id="edit_wp_id" name="wp_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Work Package</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="editWorkPackageModalBody">
                    <!-- Form fields will be loaded here via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Work Package</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Project Modal -->
<div class="modal fade" id="editProjectModal" tabindex
<?php include '../includes/footer.php'; ?>