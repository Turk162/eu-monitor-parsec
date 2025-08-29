<?php
// ===================================================================
//  ADD PROJECT PARTNERS PAGE
// ===================================================================
// This page is part of the project creation wizard (Step 2).
// It allows a project coordinator to add partners and set their budgets.
// ===================================================================

// ===================================================================
//  PAGE CONFIGURATION
// ===================================================================

// Set the title of the page
$page_title = 'Add Project Partners';

// TEMPORARY FIX: Override navbar title detection
$navbar_page_title = 'Add Partners';

// Specify the path to the page-specific CSS file
$page_css_path = '../assets/css/pages/add-project-partners.css';

// Specify the path to the page-specific JS file  
$page_js_path = '../assets/js/pages/add-project-partners.js';
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
//  DATABASE & INITIAL DATA FETCH
// ===================================================================

$database = new Database();
$conn = $database->connect();

// Get Project ID from URL and validate it
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if (!$project_id) {
    setErrorMessage('Project ID is required to add partners.');
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

// Authorization Check: Only super admins or the project coordinator can add partners
if ($user_role !== 'super_admin' && $project['coordinator_id'] !== $user_id) {
    setErrorMessage('You do not have permission to add partners to this project.');
    header('Location: project-detail.php?id=' . $project_id);
    exit;
}

// Fetch all available partners for the dropdown
$all_partners_stmt = $conn->prepare("SELECT id, name, country FROM partners ORDER BY name");
$all_partners_stmt->execute();
$all_partners = $all_partners_stmt->fetchAll(PDO::FETCH_ASSOC);

// ===================================================================
//  FORM SUBMISSION HANDLER
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $partners_data = $_POST['partners'] ?? [];
    
    try {
        $conn->beginTransaction();

        // First, clear existing partners (except the coordinator) to resync
        $lead_partner_stmt = $conn->prepare("SELECT partner_id FROM project_partners WHERE project_id = ? AND role = 'coordinator'");
        $lead_partner_stmt->execute([$project_id]);
        $lead_partner_id = $lead_partner_stmt->fetchColumn();

        $delete_stmt = $conn->prepare("DELETE FROM project_partners WHERE project_id = ? AND role = 'partner'");
        $delete_stmt->execute([$project_id]);

        // Insert the new set of partners
        $stmt = $conn->prepare(
            "INSERT INTO project_partners (project_id, partner_id, role, budget_allocated) 
             VALUES (:project_id, :partner_id, 'partner', :budget)"
        );

        foreach ($partners_data as $p_data) {
            // Skip if no partner is selected
            if (empty($p_data['id'])) {
                continue;
            }
            
            // Prevent re-adding the coordinator as a regular partner
            if ($p_data['id'] == $lead_partner_id) {
                continue;
            }

            $stmt->execute([
                ':project_id' => $project_id,
                ':partner_id' => (int)$p_data['id'],
                ':budget' => !empty($p_data['budget']) ? (float)$p_data['budget'] : 0
            ]);
        }

        $conn->commit();
        setSuccessMessage('Project partners have been saved successfully!');
        header('Location: add-project-workpackages.php?project_id=' . $project_id);
        exit;

    } catch (PDOException $e) {
        $conn->rollback();
        setErrorMessage('An error occurred while saving the partners. Error: ' . $e->getMessage());
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
                        <h5 class="card-title"><i class="nc-icon nc-world-2"></i> Add Project Partners</h5>
                        <p class="card-category">Step 2: Add partner organizations to "<?= htmlspecialchars($project['name']) ?>"</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div id="partnersContainer">
                                <!-- Partner Template -->
                                <div class="partner-item" data-partner-index="0">
                                    <div class="row">
                                        <div class="col-10">
                                            <strong>Partner #<span class="partner-number">1</span></strong>
                                        </div>
                                        <div class="col-2 text-right">
                                            <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removePartner(this)" style="display: none;">Remove</button>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="form-group">
                                                <label>Partner Organization <span class="text-danger">*</span></label>
                                                <select name="partners[0][id]" class="form-control" required>
                                                    <option value="">-- Select a Partner --</option>
                                                    <?php foreach ($all_partners as $partner): ?>
                                                        <option value="<?= $partner['id'] ?>"><?= htmlspecialchars($partner['name'] . ' (' . $partner['country'] . ')') ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Allocated Budget (€)</label>
                                                <input type="number" name="partners[0][budget]" class="form-control" placeholder="e.g., 50000" step="0.01">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-outline-primary" onclick="addPartner()">
                                    <i class="nc-icon nc-simple-add"></i> Add Another Partner
                                </button>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between">
                                <a href="create-project.php?id=<?= $project_id ?>" class="btn btn-secondary"><i class="nc-icon nc-minimal-left"></i> Back</a>
                                <div>
                                    <a href="add-project-workpackages.php?project_id=<?= $project_id ?>" class="btn btn-outline-primary">Skip & Continue <i class="nc-icon nc-minimal-right"></i></a>
                                    <button type="submit" class="btn btn-primary"><i class="nc-icon nc-check-2"></i> Save Partners & Continue</button>
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

</body>
</html>