<?php
// ===================================================================
//  MANAGE PROJECT RISKS PAGE
// ===================================================================

// ===================================================================
//  INCLUDES & SESSION
// ===================================================================
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// ===================================================================
//  AUTHENTICATION & AUTHORIZATION
// ===================================================================

$auth = new Auth();
$auth->requireLogin();

$user_id = getUserId();
$user_role = getUserRole();

// Only super_admin and coordinator can manage project risks
if (!in_array($user_role, ['super_admin', 'coordinator'])) {
    Flash::set('error', 'You do not have permission to manage project risks.');
    header('Location: projects.php');
    exit;
}

// ===================================================================
//  DATABASE & DATA FETCHING
// ===================================================================

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if (!$project_id) {
    Flash::set('error', 'No project ID specified.');
    header('Location: projects.php');
    exit;
}

$database = new Database();
$conn = $database->connect();

// Fetch project details
$project_stmt = $conn->prepare("SELECT id, name FROM projects WHERE id = ?");
$project_stmt->execute([$project_id]);
$project = $project_stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    Flash::set('error', 'Project not found.');
    header('Location: projects.php');
    exit;
}

// Handle form submission for adding a new risk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_project_risk') {
    $risk_description = trim($_POST['risk_description'] ?? '');
    $default_probability = 1; // Default for new custom risks
    $default_impact = 1;      // Default for new custom risks

    if (!empty($risk_description)) {
        try {
            $conn->beginTransaction();

            // 1. Generate unique risk_code
            $project_initials = strtoupper(substr($project['name'], 0, 2));
            $next_seq_num = 1;
            $max_code_stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(risk_code, 3) AS UNSIGNED)) FROM risks WHERE risk_code LIKE ?");
            $max_code_stmt->execute([$project_initials . '%']);
            $max_num = $max_code_stmt->fetchColumn();
            if ($max_num !== null) {
                $next_seq_num = $max_num + 1;
            }
            $new_risk_code = $project_initials . str_pad($next_seq_num, 2, '0', STR_PAD_LEFT);

            // 2. Insert into risks table
            $insert_risk_stmt = $conn->prepare("
                INSERT INTO risks (risk_code, category, description, critical_threshold)
                VALUES (?, ?, ?, ?)
            ");
            // For custom risks, category can be 'Custom' and critical_threshold a default value
            $insert_risk_stmt->execute([$new_risk_code, 'Custom', $risk_description, 12]); // Default critical_threshold
            $new_risk_id = $conn->lastInsertId();

            // 3. Associate with project in project_risks table
            $initial_score = $default_probability * $default_impact;
            $status = '';
            if ($initial_score > 12) $status = 'Critical';
            elseif ($initial_score > 6) $status = 'High';
            elseif ($initial_score > 2) $status = 'Medium';
            else $status = 'Low';

            $insert_project_risk_stmt = $conn->prepare("
                INSERT INTO project_risks (project_id, risk_id, current_probability, current_impact, current_score, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert_project_risk_stmt->execute([$project_id, $new_risk_id, $default_probability, $default_impact, $initial_score, $status]);

            $conn->commit();
            Flash::set('success', 'Custom risk "' . htmlspecialchars($risk_description) . '" added and associated with project successfully!');

        } catch (PDOException $e) {
            $conn->rollBack();
            // Check for duplicate entry error (e.g., if risk_code generation somehow clashes)
            if ($e->getCode() == 23000) { // SQLSTATE for Integrity Constraint Violation
                Flash::set('error', 'A risk with a similar code might already exist. Please try again or contact support.');
            } else {
                Flash::set('error', 'Database error: ' . $e->getMessage());
            }
        }
    } else {
        Flash::set('error', 'Risk description cannot be empty.');
    }
    header('Location: manage-project-risks.php?project_id=' . $project_id);
    exit;
}

// Handle form submission for updating an existing risk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_project_risk') {
    $project_risk_id = (int)($_POST['project_risk_id'] ?? 0);
    $new_probability = (int)($_POST['new_probability'] ?? 1);
    $new_impact = (int)($_POST['new_impact'] ?? 1);

    if ($project_risk_id > 0 && $new_probability >= 1 && $new_probability <= 5 && $new_impact >= 1 && $new_impact <= 5) {
        try {
            // Fetch current risk details to pass to UpdateRiskScore procedure
            $current_risk_stmt = $conn->prepare("SELECT risk_id FROM project_risks WHERE id = ?");
            $current_risk_stmt->execute([$project_risk_id]);
            $current_risk_data = $current_risk_stmt->fetch(PDO::FETCH_ASSOC);

            if ($current_risk_data) {
                $risk_id_for_procedure = $current_risk_data['risk_id'];
                // Call the stored procedure to update the risk score and log history
                $sp_stmt = $conn->prepare("CALL UpdateRiskScore(:project_risk_id, :new_probability, :new_impact, :change_reason)");
                $sp_stmt->execute([
                    'project_risk_id' => $project_risk_id,
                    'new_probability' => $new_probability,
                    'new_impact' => $new_impact,
                    'change_reason' => 'Manual update from admin panel.'
                ]);
                Flash::set('success', 'Risk updated successfully!');

                // --- NEW: Alert Triggering Logic ---
                // Re-fetch updated risk details including critical_threshold
                $updated_risk_stmt = $conn->prepare("
                    SELECT pr.current_score, pr.status, r.critical_threshold, r.description as risk_description
                    FROM project_risks pr
                    JOIN risks r ON pr.risk_id = r.id
                    WHERE pr.id = ?
                ");
                $updated_risk_stmt->execute([$project_risk_id]);
                $updated_risk_data = $updated_risk_stmt->fetch(PDO::FETCH_ASSOC);

                if ($updated_risk_data) {
                    $current_score = $updated_risk_data['current_score'];
                    $critical_threshold = $updated_risk_data['critical_threshold'];
                    $risk_description = $updated_risk_data['risk_description'];
                    $risk_status = $updated_risk_data['status'];

                    // Instantiate AlertSystem
                    require_once '../includes/classes/AlertSystem.php';
                    $alertSystem = new AlertSystem($conn);

                    $alertMessage = "Risk '" . htmlspecialchars($risk_description) . "' (Score: {$current_score}, Status: {$risk_status}) has been updated.";
                    $alertLevel = 0; // Default to no alert

                    if ($current_score >= $critical_threshold) {
                        // If score meets or exceeds critical threshold, escalate
                        if ($current_score > 12) { // Example: Very high score, might be Level 3
                            $alertLevel = 3;
                            $alertMessage = "CRITICAL RISK ALERT: '" . htmlspecialchars($risk_description) . "' has reached a score of {$current_score} (Status: {$risk_status}). Immediate attention required!";
                        } elseif ($current_score >= $critical_threshold) { // Meets critical threshold, Level 2
                            $alertLevel = 2;
                            $alertMessage = "HIGH RISK ALERT: '" . htmlspecialchars($risk_description) . "' has reached a score of {$current_score} (Status: {$risk_status}). Please review.";
                        }
                    } else if ($current_score > 6 && $current_score < $critical_threshold) { // High but not critical, might be Level 1 for monitoring
                        $alertLevel = 1;
                        $alertMessage = "Risk '" . htmlspecialchars($risk_description) . "' is now HIGH (Score: {$current_score}). Monitoring advised.";
                    }

                    if ($alertLevel > 0) {
                        $alertSystem->triggerRiskAlert($project_risk_id, $alertLevel, $alertMessage, $project_id);
                    }
                }
                // --- END NEW: Alert Triggering Logic ---

            } else {
                Flash::set('error', 'Risk not found for update.');
            }
        } catch (PDOException $e) {
            Flash::set('error', 'Database error: ' . $e->getMessage());
        }
    } else {
        Flash::set('error', 'Invalid input for updating risk.');
    }
    header('Location: manage-project-risks.php?project_id=' . $project_id);
    exit;
}

// Handle form submission for removing an existing risk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_project_risk') {
    $project_risk_id = (int)($_POST['project_risk_id'] ?? 0);

    if ($project_risk_id > 0) {
        try {
            $delete_stmt = $conn->prepare("DELETE FROM project_risks WHERE id = ? AND project_id = ?");
            $delete_stmt->execute([$project_risk_id, $project_id]);
            if ($delete_stmt->rowCount() > 0) {
                Flash::set('success', 'Risk removed from project successfully!');
            } else {
                Flash::set('error', 'Risk not found or not associated with this project.');
            }
        } catch (PDOException $e) {
            Flash::set('error', 'Database error: ' . $e->getMessage());
        }
    } else {
        Flash::set('error', 'Invalid risk ID for removal.');
    }
    header('Location: manage-project-risks.php?project_id=' . $project_id);
    exit;
}

// Fetch associated risks for the project
$associated_risks_stmt = $conn->prepare("
    SELECT pr.id as project_risk_id, r.risk_code, r.description, pr.current_probability, pr.current_impact, pr.current_score, pr.status
    FROM project_risks pr
    JOIN risks r ON pr.risk_id = r.id
    WHERE pr.project_id = ?
    ORDER BY r.risk_code ASC
");
$associated_risks_stmt->execute([$project_id]);
$associated_risks = $associated_risks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all available risks (for the dropdown)
$all_risks_stmt = $conn->prepare("SELECT id, risk_code, description FROM risks ORDER BY risk_code ASC");
$all_risks_stmt->execute();
$all_risks = $all_risks_stmt->fetchAll(PDO::FETCH_ASSOC);



// ===================================================================
//  PAGE-SPECIFIC VARIABLES
// ===================================================================

$page_title = 'Manage Risks for ' . htmlspecialchars($project['name']);
$page_css_path = '../assets/css/pages/project-detail.css'; // Reusing existing styles
$page_js_path = '';

// ===================================================================
//  START HTML LAYOUT
// ===================================================================

include '../includes/header.php';
?>

<body class="">
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <?php include '../includes/navbar.php'; ?>

        <div class="content">
            <?php displayAlert(); // Display success/error messages ?>

            <div class="row">
                <div class="col-12 mb-3">
                    <a href="project-detail.php?id=<?= $project_id ?>" class="back-button">
                        <i class="nc-icon nc-minimal-left"></i>
                        Back to Project Details
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">
                                <i class="nc-icon nc-alert-circle-i text-danger"></i>
                                Manage Risks for Project: <?= htmlspecialchars($project['name']) ?>
                            </h4>
                            <p class="category">Associate and manage specific risks for this project.</p>
                        </div>
                        <div class="card-body">
                            <h5>Associated Risks</h5>
                            <?php if (empty($associated_risks)): ?>
                                <p class="text-muted">No risks are currently associated with this project.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead class="text-primary">
                                            <th>Code</th>
                                            <th>Description</th>
                                            <th class="text-center">Prob.</th>
                                            <th class="text-center">Impact</th>
                                            <th class="text-center">Score</th>
                                            <th class="text-center">Status</th>
                                            <th>Actions</th>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($associated_risks as $risk): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($risk['risk_code']) ?></td>
                                                    <td><?= htmlspecialchars($risk['description']) ?></td>
                                                    <td class="text-center"><?= htmlspecialchars($risk['current_probability']) ?></td>
                                                    <td class="text-center"><?= htmlspecialchars($risk['current_impact']) ?></td>
                                                    <td class="text-center font-weight-bold"><?= htmlspecialchars($risk['current_score']) ?></td>
                                                    <td class="text-center">
                                                        <span class="badge badge-<?= strtolower($risk['status']) === 'critical' ? 'danger' : (strtolower($risk['status']) === 'high' ? 'warning' : (strtolower($risk['status']) === 'medium' ? 'info' : 'success')) ?>">
                                                            <?= htmlspecialchars($risk['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <!-- Actions for editing/removing will go here -->
                                                        <button class="btn btn-sm btn-info edit-risk-btn" 
                                                                data-toggle="modal" 
                                                                data-target="#editRiskModal"
                                                                data-id="<?= $risk['project_risk_id'] ?>"
                                                                data-probability="<?= $risk['current_probability'] ?>"
                                                                data-impact="<?= $risk['current_impact'] ?>">Edit</button>
                                                        <form method="POST" action="manage-project-risks.php?project_id=<?= $project_id ?>" style="display:inline-block;">
                                                            <input type="hidden" name="action" value="remove_project_risk">
                                                            <input type="hidden" name="project_risk_id" value="<?= $risk['project_risk_id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this risk from the project?');">Remove</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <h5 class="mt-5">Add New Custom Risk to Project</h5>
                            <form method="POST" action="manage-project-risks.php?project_id=<?= $project_id ?>">
                                <input type="hidden" name="action" value="add_project_risk">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="risk_description">Risk Description</label>
                                            <input type="text" class="form-control" id="risk_description" name="risk_description" required placeholder="e.g., Delays in partner reporting">
                                        </div>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary btn-block">Add Custom Risk</button>
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

<!-- Edit Risk Modal -->
<div class="modal fade" id="editRiskModal" tabindex="-1" role="dialog" aria-labelledby="editRiskModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editRiskModalLabel">Edit Risk Score</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST" action="manage-project-risks.php?project_id=<?= $project_id ?>">
        <input type="hidden" name="action" value="update_project_risk">
        <input type="hidden" name="project_risk_id" id="edit_project_risk_id">
        <div class="modal-body">
          <div class="form-group">
            <label for="edit_probability">Probability (1-5)</label>
            <input type="number" class="form-control" id="edit_probability" name="new_probability" min="1" max="5" required>
          </div>
          <div class="form-group">
            <label for="edit_impact">Impact (1-5)</label>
            <input type="number" class="form-control" id="edit_impact" name="new_impact" min="1" max="5" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    $('#editRiskModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); // Button that triggered the modal
        var projectRiskId = button.data('id');
        var probability = button.data('probability');
        var impact = button.data('impact');

        var modal = $(this);
        modal.find('#edit_project_risk_id').val(projectRiskId);
        modal.find('#edit_probability').val(probability);
        modal.find('#edit_impact').val(impact);
    });
});
</script>

</body>
</html>