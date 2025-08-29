<?php

// ===================================================================
//  CREATE NEW ACTIVITY REPORT PAGE
// ===================================================================

$page_title = 'Create New Report - EU Project Manager';
require_once '../includes/header.php';

// ===================================================================
//  AUTHENTICATION & AUTHORIZATION
// ===================================================================
$auth->requireLogin();
$user_id = getUserId();
$user_role = getUserRole();
// We get the partner_id from session for regular users.
// For super_admin, it will be determined from a form field.
$session_partner_id = $_SESSION['partner_id'] ?? 0;

// ===================================================================
//  DATABASE CONNECTION
// ===================================================================
$database = new Database();
$conn = $database->connect();

// ===================================================================
//  FORM SUBMISSION HANDLER
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_report') {

    // Determine the partner_id for the report
    if ($user_role === 'super_admin') {
        $report_partner_id = (int)($_POST['partner_id'] ?? 0);
    } else {
        $report_partner_id = $session_partner_id;
    }

    // --- 1. Sanitize and retrieve form data ---
    $activity_id = (int)($_POST['activity_id'] ?? 0);
    $report_date = $_POST['report_date'];
    $description = trim($_POST['description']);
    $participants_data = trim($_POST['participants_data'] ?? null);
    // Ensure participants_data is valid JSON for the database constraint
    if ($participants_data !== null && $participants_data !== '') {
        $participants_data = json_encode($participants_data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Handle error if encoding fails, though it should not for a simple string
            $participants_data = json_encode("Invalid data: " . $participants_data);
        }
    }

    // Risk data
    $risk_collaboration_rating = (int)($_POST['risk_collaboration_rating'] ?? 0);
    $risk_collaboration_rating = $risk_collaboration_rating === 0 ? null : $risk_collaboration_rating;

    $risk_recruitment_difficulty = (int)($_POST['risk_recruitment_difficulty'] ?? 0);
    $risk_recruitment_difficulty = $risk_recruitment_difficulty === 0 ? null : $risk_recruitment_difficulty;

    $risk_quality_check = (int)($_POST['risk_quality_check'] ?? 0);
    $risk_quality_check = $risk_quality_check === 0 ? null : $risk_quality_check;

    $risk_budget_status = trim($_POST['risk_budget_status'] ?? 'na');
    $risk_budget_status = $risk_budget_status === 'na' ? null : $risk_budget_status;

    // --- 2. Server-side validation ---
    $errors = [];
    if (empty($activity_id)) $errors[] = "An activity must be selected.";
    if (empty($report_date)) $errors[] = "The report date is required.";
    if (empty($description)) $errors[] = "The description cannot be empty.";
    if (empty($report_partner_id)) $errors[] = "A partner must be associated with the report."; // New validation

    // --- 3. Process the data ---
    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            $proj_stmt = $conn->prepare("SELECT project_id FROM activities WHERE id = ?");
            $proj_stmt->execute([$activity_id]);
            $project_id = $proj_stmt->fetchColumn();

            if (!$project_id) {
                throw new Exception("The selected activity is not linked to a valid project.");
            }

            $stmt = $conn->prepare(
                'INSERT INTO activity_reports (activity_id, project_id, partner_id, user_id, report_date, description, participants_data, created_at, risk_collaboration_rating, risk_recruitment_difficulty, risk_quality_check, risk_budget_status) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)'
            );
            $stmt->execute([
                $activity_id, $project_id, $report_partner_id, $user_id, $report_date, $description, $participants_data,
                $risk_collaboration_rating, $risk_recruitment_difficulty, $risk_quality_check, $risk_budget_status
            ]);
            $report_id = $conn->lastInsertId();

            if (!empty($_FILES['report_files']['name'][0])) {
                handleFileUploads($conn, $_FILES['report_files'], $report_id, $user_id);
            }

            $conn->commit();

            if ($report_id) {
                require_once __DIR__ . '/../includes/classes/RiskCalculator.php';
                $riskCalculator = new RiskCalculator($conn);
                $riskCalculator->processReport($report_id);
            }

            Flash::set('success', "Report #{$report_id} has been created successfully.");
            header("Location: reports.php");
            exit;

        } catch (Exception $e) {
            $conn->rollBack();
            Flash::set('error', "An error occurred: " . $e->getMessage());
        }
    } else {
        Flash::set('error', implode("<br>", $errors));
    }
}

// ===================================================================
//  DATA FOR FORM DROPDOWNS
// ===================================================================

// Pre-select activity from URL if provided
$selected_activity_id = (int)($_GET['activity_id'] ?? 0);

// Get activities
$activities_sql = "SELECT a.id, a.name, a.activity_number, p.name as project_name FROM activities a JOIN work_packages wp ON a.work_package_id = wp.id JOIN projects p ON wp.project_id = p.id";
$params = [];

if ($user_role !== 'super_admin') {
    // For non-admins, only show activities from projects they are part of.
    $activities_sql .= " WHERE p.id IN (SELECT project_id FROM project_partners WHERE partner_id = :partner_id)";
    $params['partner_id'] = $session_partner_id;
}

$activities_sql .= " ORDER BY p.name, a.name";
$activities_stmt = $conn->prepare($activities_sql);
$activities_stmt->execute($params);
$available_activities = $activities_stmt->fetchAll();

// Get partners (for super_admin dropdown)
$partners = [];
if ($user_role === 'super_admin') {
    $partners_stmt = $conn->query("SELECT id, name FROM partners ORDER BY name");
    $partners = $partners_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===================================================================
//  START HTML LAYOUT
// ===================================================================
?>
<body class="">
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <?php include '../includes/navbar.php'; ?>

        <div class="content">
            <?php displayAlert(); ?>
            <div class="row">
                <div class="col-md-8 offset-md-2">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title"><i class="nc-icon nc-simple-add"></i> Create New Activity Report</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="create-report.php" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="create_report">

                                <?php if ($user_role === 'super_admin'): ?>
                                    <div class="form-group">
                                        <label>Reporting Partner <span class="text-danger">*</span></label>
                                        <select class="form-control" name="partner_id" required>
                                            <option value="">-- Select a Partner --</option>
                                            <?php foreach ($partners as $partner): ?>
                                                <option value="<?= $partner['id'] ?>"><?= htmlspecialchars($partner['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="form-text text-muted">As Super Admin, please select the partner you are reporting for.</small>
                                    </div>
                                <?php endif; ?>

                                <div class="form-group">
                                    <label>Activity <span class="text-danger">*</span></label>
                                    <select class="form-control" name="activity_id" required>
                                        <option value="">-- Select an Activity --</option>
                                        <?php foreach ($available_activities as $activity):
                                            $display_text = !empty($activity['activity_number']) ? $activity['activity_number'] . ' - ' . $activity['name'] : $activity['name'];
                                        ?>
                                            <option value="<?= $activity['id'] ?>" <?= ($activity['id'] == $selected_activity_id) ? 'selected' : '' ?>><?= htmlspecialchars($display_text) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Report Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="report_date" required value="<?= date('Y-m-d') ?>">
                                </div>

                                <div class="form-group">
                                    <label>Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="description" rows="5" required placeholder="Describe the work done, progress, and any issues encountered..."></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Participants Data</label>
                                    <textarea class="form-control" name="participants_data" rows="3" placeholder="Enter any relevant participant data here (e.g., number of attendees, organizations involved, etc.)."></textarea>
                                    <small class="form-text text-muted">Optional: Describe the participants of the activity.</small>
                                </div>

                                <div class="form-group">
                                    <label>Attach Files (Optional)</label>
                                    <input type="file" name="report_files[]" class="form-control-file" multiple>
                                    <small class="form-text text-muted">You can select multiple files.</small>
                                </div>

                                <hr>

                                <div class="internal-review-section mt-4">
                                    <h6 class="text-primary">Internal Review & Coordination</h6>
                                    <p class="category">Please provide a quick evaluation of the activity's context. For internal use only.</p>
                                    <div class="row">
                                        <div class="col-md-6"><div class="form-group"><label>Collaboration Rating (1-5)</label><select name="risk_collaboration_rating" class="form-control"><option value="0" selected>N/A (Not Applicable)</option><option value="5">5 - Excellent</option><option value="4">4 - Good</option><option value="3">3 - Average</option><option value="2">2 - Difficult</option><option value="1">1 - Very Problematic</option></select><small class="form-text text-muted">How was the collaboration with other partners during this activity?</small></div></div>
                                        <div class="col-md-6"><div class="form-group"><label>Recruitment Difficulty (1-5)</label><select name="risk_recruitment_difficulty" class="form-control"><option value="0" selected>N/A (Not Applicable)</option><option value="5">5 - Very Easy</option><option value="4">4 - Easy</option><option value="3">3 - As Expected</option><option value="2">2 - Difficult</option><option value="1">1 - Very Difficult</option></select><small class="form-text text-muted">How difficult was it to involve the target group?</small></div></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6"><div class="form-group"><label>Deliverable Quality (1-5)</label><select name="risk_quality_check" class="form-control"><option value="0" selected>N/A (Not Applicable)</option><option value="5">5 - Exceeds Expectations</option><option value="4">4 - Meets Expectations</option><option value="3">3 - Needs Minor Revisions</option><option value="2">2 - Needs Major Revisions</option><option value="1">1 - Not Acceptable</option></select><small class="form-text text-muted">Internal check on the quality of the output produced.</small></div></div>
                                        <div class="col-md-6"><div class="form-group"><label>Budget Status</label><select name="risk_budget_status" class="form-control"><option value="na" selected>N/A (Not Applicable)</option><option value="green">Green - On Track</option><option value="yellow">Yellow - Minor Deviation</option><option value="red">Red - Significant Deviation</option></select><small class="form-text text-muted">Is the activity aligned with the allocated budget?</small></div></div>
                                    </div>
                                </div>

                                <hr>

                                <div class="text-right">
                                    <a href="reports.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary"><i class="nc-icon nc-check-2"></i> Submit Report</button>
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
