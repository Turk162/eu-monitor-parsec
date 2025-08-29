<?php
// ===================================================================
//  DELETE PROJECT - FIXED VERSION
// ===================================================================
// This script handles the deletion of a project and all its associated data.
// Based on successful debug testing, this version properly handles the
// deletion order to avoid foreign key constraint violations.
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

// Security Check: Only super_admins and coordinators can access this script.
if (!in_array($user_role, ['super_admin', 'coordinator'])) {
    $_SESSION['error'] = 'You do not have permission to delete projects.';
    header('Location: projects.php');
    exit;
}

// ===================================================================
//  CSRF TOKEN VALIDATION
// ===================================================================

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error'] = 'Invalid request. Please try again.';
    header('Location: projects.php');
    exit;
}

// ===================================================================
//  DELETION LOGIC
// ===================================================================

$project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;

if (!$project_id) {
    $_SESSION['error'] = 'Invalid project ID provided.';
    header('Location: projects.php');
    exit;
}

try {
    $database = new Database();
    $conn = $database->connect();

    // First, retrieve project details for permission check and success message.
    $stmt = $conn->prepare("SELECT name, coordinator_id FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        $_SESSION['error'] = 'Project not found.';
        header('Location: projects.php');
        exit;
    }
    
    // Authorization Check: A user must be a super_admin or the specific project coordinator.
    if ($user_role !== 'super_admin' && $project['coordinator_id'] != $user_id) {
        $_SESSION['error'] = 'You do not have permission to delete this specific project.';
        header('Location: projects.php');
        exit;
    }
    
    // Use a transaction to ensure all related data is deleted or nothing is.
    $conn->beginTransaction();

    // ===================================================================
    // PROVEN DELETION ORDER - Based on successful debug testing
    // ===================================================================
    
    $total_deleted = 0;
    
    // STEP 1: Delete uploaded files (deepest dependency level)
    $stmt1 = $conn->prepare("
        DELETE uf FROM uploaded_files uf
        INNER JOIN activity_reports ar ON uf.report_id = ar.id
        INNER JOIN activities a ON ar.activity_id = a.id
        INNER JOIN work_packages wp ON a.work_package_id = wp.id
        WHERE wp.project_id = ?
    ");
    $stmt1->execute([$project_id]);
    $deleted_files = $stmt1->rowCount();
    $total_deleted += $deleted_files;
    
    // STEP 2: Delete activity reports
    $stmt2 = $conn->prepare("
        DELETE ar FROM activity_reports ar
        INNER JOIN activities a ON ar.activity_id = a.id
        INNER JOIN work_packages wp ON a.work_package_id = wp.id
        WHERE wp.project_id = ?
    ");
    $stmt2->execute([$project_id]);
    $deleted_reports = $stmt2->rowCount();
    $total_deleted += $deleted_reports;
    
    // STEP 3: Delete activities
    $stmt3 = $conn->prepare("
        DELETE a FROM activities a
        INNER JOIN work_packages wp ON a.work_package_id = wp.id
        WHERE wp.project_id = ?
    ");
    $stmt3->execute([$project_id]);
    $deleted_activities = $stmt3->rowCount();
    $total_deleted += $deleted_activities;
    
    // STEP 4: Delete alerts
    $stmt4 = $conn->prepare("DELETE FROM alerts WHERE project_id = ?");
    $stmt4->execute([$project_id]);
    $deleted_alerts = $stmt4->rowCount();
    $total_deleted += $deleted_alerts;
    
    // STEP 5: Delete milestones
    $stmt5 = $conn->prepare("DELETE FROM milestones WHERE project_id = ?");
    $stmt5->execute([$project_id]);
    $deleted_milestones = $stmt5->rowCount();
    $total_deleted += $deleted_milestones;
    
    // STEP 6: Delete participant categories
    $stmt6 = $conn->prepare("DELETE FROM participant_categories WHERE project_id = ?");
    $stmt6->execute([$project_id]);
    $deleted_categories = $stmt6->rowCount();
    $total_deleted += $deleted_categories;
    
    // STEP 7: Delete work packages
    $stmt7 = $conn->prepare("DELETE FROM work_packages WHERE project_id = ?");
    $stmt7->execute([$project_id]);
    $deleted_workpackages = $stmt7->rowCount();
    $total_deleted += $deleted_workpackages;
    
    // STEP 8: Delete project partners (CRITICAL - this was the blocking issue!)
    $stmt8 = $conn->prepare("DELETE FROM project_partners WHERE project_id = ?");
    $stmt8->execute([$project_id]);
    $deleted_partners = $stmt8->rowCount();
    $total_deleted += $deleted_partners;
    
    // STEP 9: Finally, delete the project itself
    $stmt9 = $conn->prepare("DELETE FROM projects WHERE id = ?");
    $stmt9->execute([$project_id]);
    $deleted_project = $stmt9->rowCount();
    
    // Verify that the project was actually deleted
    if ($deleted_project > 0) {
        $conn->commit();
        
        // Build success message with details
        $details = [];
        if ($deleted_partners > 0) $details[] = "$deleted_partners partners";
        if ($deleted_workpackages > 0) $details[] = "$deleted_workpackages work packages";
        if ($deleted_activities > 0) $details[] = "$deleted_activities activities";
        if ($deleted_reports > 0) $details[] = "$deleted_reports reports";
        if ($deleted_files > 0) $details[] = "$deleted_files files";
        if ($deleted_milestones > 0) $details[] = "$deleted_milestones milestones";
        if ($deleted_alerts > 0) $details[] = "$deleted_alerts alerts";
        if ($deleted_categories > 0) $details[] = "$deleted_categories participant categories";
        
        $success_message = "Project '" . htmlspecialchars($project['name']) . "' has been permanently deleted.";
        if (!empty($details)) {
            $success_message .= " Also removed: " . implode(', ', $details) . ".";
        }
        
        $_SESSION['success'] = $success_message;
        
    } else {
        $conn->rollBack();
        $_SESSION['error'] = 'Failed to delete the project. It may have already been deleted or does not exist.';
    }
    
} catch (PDOException $e) {
    // Log the detailed error for debugging
    error_log("Delete project PDO error: " . $e->getMessage());
    $_SESSION['error'] = 'A database error occurred while deleting the project. Please contact the administrator.';
    
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
} catch (Exception $e) {
    error_log("Delete project general error: " . $e->getMessage());
    $_SESSION['error'] = 'An unexpected error occurred while deleting the project. Please try again.';
    
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
}

// Redirect back to the projects list page
header('Location: projects.php');
exit;
?>