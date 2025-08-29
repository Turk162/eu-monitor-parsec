<?php
// ===================================================================
//  ADD PROJECT MILESTONES PAGE
// ===================================================================
// This page is part of the project creation wizard (Step 4).
// It allows a project coordinator to define key milestones.
// ===================================================================

// ===================================================================
//  PAGE CONFIGURATION
// ===================================================================

// Set the title of the page
$page_title = 'Add Project Milestones';

// TEMPORARY FIX: Override navbar title detection
$navbar_page_title = 'Add Milestones';

// Specify the path to the page-specific CSS file
$page_css_path = '../assets/css/pages/add-project-milestones.css';

// Specify the path to the page-specific JS file  
$page_js_path = '../assets/js/pages/add-project-milestones.js';

session_start();
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_once '../includes/header.php';

// ===================================================================
//  AUTHORIZATION CHECK
// ===================================================================

// Only super_admins and coordinators are allowed to create projects.
if (!in_array($user_role, ['super_admin', 'coordinator'])) {
    setErrorMessage('You do not have permission to add milestones.');
    header('Location: projects.php');
    exit;
}

// ===================================================================
//  DATABASE CONNECTION & INITIAL DATA
// ===================================================================

// Database connection (user_id and user_role are already available from header)
$database = new Database();
$conn = $database->connect();

// Get Project ID from URL and validate it
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if (!$project_id) {
    setErrorMessage('Project ID is required to add milestones.');
    header('Location: projects.php');
    exit;
}

// Fetch project details to display and for permission checks
$project_stmt = $conn->prepare("SELECT id, name, coordinator_id FROM projects WHERE id = ?");
$project_stmt->execute([$project_id]);
$project = $project_stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    setErrorMessage('The specified project could not be found.');
    header('Location: projects.php');
    exit;
}

// Authorization Check: Only super admins or the project coordinator can add milestones
if ($user_role !== 'super_admin' && $project['coordinator_id'] !== $user_id) {
    setErrorMessage('You do not have permission to add milestones to this project.');
    header('Location: project-detail.php?id=' . $project_id);
    exit;
}

// Fetch Work Packages for this project to link milestones to them
$wp_stmt = $conn->prepare("SELECT id, wp_number, name FROM work_packages WHERE project_id = ? ORDER BY wp_number");
$wp_stmt->execute([$project_id]);
$work_packages = $wp_stmt->fetchAll(PDO::FETCH_ASSOC);

// ===================================================================
//  FORM SUBMISSION HANDLER
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $milestones_data = $_POST['milestones'] ?? [];
    
    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare(
            "INSERT INTO milestones (project_id, work_package_id, name, description, end_date, status) 
             VALUES (:project_id, :work_package_id, :name, :description, :end_date, 'pending')"
        );

        foreach ($milestones_data as $ms_data) {
            // Skip any empty milestone forms that might have been submitted
            if (empty($ms_data['name']) || empty($ms_data['end_date'])) {
                continue;
            }
            
            $stmt->execute([
                ':project_id' => $project_id,
                ':work_package_id' => !empty($ms_data['work_package_id']) ? (int)$ms_data['work_package_id'] : null,
                ':name' => sanitizeInput($ms_data['name']),
                ':description' => sanitizeInput($ms_data['description'] ?? ''),
                ':end_date' => $ms_data['end_date']
            ]);
        }

        $conn->commit();
        setSuccessMessage('Project milestones have been saved successfully!');
        header('Location: project-detail.php?id=' . $project_id);
        exit;

    } catch (PDOException $e) {
        $conn->rollback();
        setErrorMessage('An error occurred while saving the milestones. Please try again.');
    }
}

// ===================================================================
//  START HTML LAYOUT
// ===================================================================

?>

<?php include '../includes/sidebar.php'; ?>

<div class="main-panel">
    <?php 
    // OVERRIDE navbar title for this page
    $navbar_page_title = 'Add Milestones';
    include '../includes/navbar.php'; 
    ?>
    
    <!-- CONTENT -->
    <div class="content">

        <!-- Success/Error Messages -->
        <?php displayAlert(); ?>
        
        <div class="row">
            <div class="col-md-12 offset-md-0">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title"><i class="nc-icon nc-trophy"></i> Add Project Milestones</h5>
                        <p class="card-category">Step 4: Define key milestones for "<?= htmlspecialchars($project['name']) ?>"</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div id="milestonesContainer">
                                <!-- Milestone Template -->
                                <div class="milestone-item" data-ms-index="0">
                                    <div class="row">
                                        <div class="col-10">
                                            <strong>Milestone #<span class="ms-number">1</span></strong>
                                        </div>
                                        <div class="col-2 text-right">
                                            <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeMilestone(this)" style="display: none;">Remove</button>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Milestone Name <span class="text-danger">*</span></label>
                                                <input type="text" name="milestones[0][name]" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Due Date <span class="text-danger">*</span></label>
                                                <input type="date" name="milestones[0][end_date]" class="form-control" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Description</label>
                                        <textarea name="milestones[0][description]" class="form-control" rows="2"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Link to Work Package (Optional)</label>
                                        <select name="milestones[0][work_package_id]" class="form-control">
                                            <option value="">-- No specific WP --</option>
                                            <?php foreach ($work_packages as $wp): ?>
                                                <option value="<?= $wp['id'] ?>"><?= htmlspecialchars($wp['wp_number'] . ': ' . $wp['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-outline-primary" onclick="addMilestone()">
                                    <i class="nc-icon nc-simple-add"></i> Add Another Milestone
                                </button>
                            </div>

                            <hr>

                            <div class="text-right">
                                <a href="add-project-workpackages.php?project_id=<?= $project_id ?>" class="btn btn-secondary"><i class="nc-icon nc-minimal-left"></i> Back</a>
                                <button type="submit" class="btn btn-primary"><i class="nc-icon nc-check-2"></i> Save Milestones & Finish</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</div>

</body>
</html>
