<?php
// ===================================================================
//  EDIT ACTIVITY REPORT PAGE
// ===================================================================

// ===================================================================
//  PAGE CONFIGURATION & INCLUDES
// ===================================================================

$page_title = 'Edit Report - EU Project Manager';
require_once '../includes/header.php';

// ===================================================================
//  AUTHENTICATION & AUTHORIZATION
// ===================================================================

$auth->requireLogin();
$current_user_id = getUserId();
$current_user_role = getUserRole();
$user_partner_id = $_SESSION['partner_id'] ?? 0; // Define user_partner_id

// ===================================================================
//  DATABASE CONNECTION (already handled by header.php)
// ===================================================================
// $conn is already available from header.php

// ===================================================================
//  GET REPORT DATA & PERMISSION CHECK
// ===================================================================

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$report_id) {
    Flash::set('error', 'Invalid Report ID.');
    header('Location: reports.php');
    exit;
}

// Fetch the report data to edit
$stmt = $conn->prepare("SELECT * FROM activity_reports WHERE id = ?");
$stmt->execute([$report_id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    Flash::set('error', 'Report not found.');
    header('Location: reports.php');
    exit;
}

// Permission check
$can_edit = (in_array($current_user_role, ['super_admin', 'admin', 'coordinator']) || $report['user_id'] == $current_user_id);
if (!$can_edit) {
    Flash::set('error', 'You do not have permission to edit this report.');
    header('Location: reports.php');
    exit;
}

// Fetch files associated with this report
$report_files_stmt = $conn->prepare("
    SELECT uf.*, u.full_name as uploaded_by_name
    FROM uploaded_files uf
    LEFT JOIN users u ON uf.uploaded_by = u.id
    WHERE uf.report_id = ?
    ORDER BY uf.uploaded_at DESC
");
$report_files_stmt->execute([$report_id]);
$report_files = $report_files_stmt->fetchAll();

// ===================================================================
//  FORM SUBMISSION HANDLER (UPDATE LOGIC)
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_report') {

    // --- 1. Sanitize and retrieve form data ---
    $activity_id = (int)($_POST['activity_id'] ?? 0);
    $report_date = $_POST['report_date'];
    $description = trim($_POST['description']);
    $participants_data = trim($_POST['participants_data'] ?? null);
    

    // --- 2. Server-side validation ---
    $errors = [];
    if (empty($activity_id)) $errors[] = "An activity must be selected.";
    if (empty($report_date)) $errors[] = "The report date is required.";
    if (empty($description)) $errors[] = "The description cannot be empty.";

    // --- 3. Process the data ---
    if (empty($errors)) {
        try {
            // Get project_id from the activity for data integrity
            $proj_stmt = $conn->prepare("SELECT project_id FROM activities WHERE id = ?");
            $proj_stmt->execute([$activity_id]);
            $project_id = $proj_stmt->fetchColumn();

            if (!$project_id) {
                throw new Exception("The selected activity is not linked to a valid project.");
            }

            // To satisfy the JSON CHECK constraint, encode the plain text as a JSON string.
            $participants_json = json_encode($participants_data);

            // Update the report details
            $update_stmt = $conn->prepare(
                'UPDATE activity_reports SET 
                    activity_id = ?, 
                    project_id = ?, 
                    report_date = ?, 
                    description = ?, 
                    participants_data = ?, 
                    updated_at = NOW()
                 WHERE id = ?'
            );
            $update_stmt->execute([$activity_id, $project_id, $report_date, $description, $participants_json, $report_id]);

            Flash::set('success', "Report #{$report_id} has been updated successfully.");
            header("Location: reports.php");
            exit;

        } catch (Exception $e) {
            Flash::set('error', "An error occurred: " . $e->getMessage());
        }
    } else {
        Flash::set('error', implode("<br>", $errors));
    }
}

// ===================================================================
//  DATA FOR FORM DROPDOWNS
// ===================================================================

// Get activities the current user is allowed to report on.
$activities_sql = "";
$activities_params = [];
if ($user_role === 'super_admin') {
    $activities_sql = "SELECT a.id, a.name, p.name as project_name FROM activities a JOIN work_packages wp ON a.work_package_id = wp.id JOIN projects p ON wp.project_id = p.id WHERE a.status != 'not_started' ORDER BY p.name, a.name";
} else {
    // Regular users can only report on activities belonging to projects their partner organization is part of.
    $activities_sql = "SELECT a.id, a.name, p.name as project_name FROM activities a JOIN work_packages wp ON a.work_package_id = wp.id JOIN projects p ON wp.project_id = p.id JOIN project_partners pp ON p.id = pp.project_id WHERE pp.partner_id = ? AND a.status != 'not_started' ORDER BY p.name, a.name";
    $activities_params[] = $user_partner_id;
}
$activities_stmt = $conn->prepare($activities_sql);
$activities_stmt->execute($activities_params);
$available_activities = $activities_stmt->fetchAll();

// Get all participant categories for the form.
// $categories_stmt = $conn->query("SELECT id, name FROM participant_categories ORDER BY name");
// $participant_categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// ===================================================================
//  START HTML LAYOUT
// ===================================================================
?>
  <!-- SIDEBAR & NAV -->
<body class="">
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <?php include '../includes/navbar.php'; ?>

            <!-- CONTENT -->
            <div class="content">
    <?php displayAlert(); ?>
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="nc-icon nc-ruler-pencil"></i> Edit Report #<?= $report['id'] ?></h5>
                    <p class="card-category">Update the details of the report below.</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="edit-report.php?id=<?= $report_id ?>">
                        <input type="hidden" name="action" value="update_report">

                        <div class="form-group">
                            <label>Activity <span class="text-danger">*</span></label>
                            <select class="form-control" name="activity_id" required>
                                <option value="">-- Select an Activity --</option>
                                <?php foreach ($available_activities as $activity): ?>
                                    <option value="<?= $activity['id'] ?>" <?= ($report['activity_id'] == $activity['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($activity['project_name']) ?> / <?= htmlspecialchars($activity['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Report Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="report_date" required value="<?= htmlspecialchars($report['report_date']) ?>">
                        </div>

                        <div class="form-group">
                            <label>Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="description" rows="5" required placeholder="Describe the work done, progress, and any issues encountered..."><?= htmlspecialchars($report['description']) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Participants Data</label>
                            <textarea class="form-control" name="participants_data" rows="3" placeholder="Enter any relevant participant data here..."><?= htmlspecialchars($report['participants_data']) ?></textarea>
                            <small class="form-text text-muted">Optional: Describe the participants of the activity.</small>
                        </div>

                        <div class="form-group">
                            <button><label>Attach New Files (Optional)</label></button>
                            <input type="file" name="report_files[]" class="form-control-file" multiple id="editReportFilesInput">
                            <small class="form-text text-muted">You can select multiple files to add to this report.</small>
                            <div id="editSelectedFiles" class="mt-2"></div>
                        </div>

                        <div class="form-group">
                            <label>Existing Files</label>
                            <div id="existingFilesList">
                                <?php if (!empty($report_files)): ?>
                                    <ul class="list-group">
                                        <?php foreach ($report_files as $file): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="nc-icon nc-paper"></i> <?= htmlspecialchars($file['original_filename']) ?></span>
                                                <div>
                                                    <a href="../<?= $file['file_path'] ?>" download="<?= htmlspecialchars($file['original_filename']) ?>" class="btn btn-sm btn-info" title="Download">
                                                        <i class="nc-icon nc-minimal-down"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger btn-delete-report-file" data-file-id="<?= $file['id'] ?>" data-file-name="<?= htmlspecialchars($file['original_filename']) ?>">
                                                        <i class="nc-icon nc-simple-remove"></i>
                                                    </button>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted">No files attached to this report yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <hr>

                        <div class="text-right">
                            <a href="reports.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary"><i class="nc-icon nc-check-2"></i> Update Report</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// ===================================================================
//  END HTML LAYOUT & FOOTER
// ===================================================================
include '../includes/footer.php';
?>
