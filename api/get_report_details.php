<?php
/**
 * API endpoint to fetch the details of a single activity report.
 */

header('Content-Type: application/json; charset=utf-8');

// Core includes
require_once '../config/database.php';
require_once '../includes/functions.php';

// Start session to access user data. This must be done before any output.
session_start();

// --- Initial Checks ---

// Check if the user is logged in.
if (!isLoggedIn()) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

// Get the report ID from the query string and validate it.
$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$report_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid Report ID provided.']);
    exit;
}

// --- Database and User Data ---

// Establish a database connection.
$database = new Database();
$conn = $database->connect();

// Get the current user's ID from the session and role from the GET parameter.
$current_user_id = $_SESSION['user_id'] ?? 0;
$current_user_role = isset($_GET['user_role']) ? trim($_GET['user_role']) : '';

// --- Data Fetching ---

try {
    // SQL query to fetch all necessary report details.
    $sql = "SELECT 
                ar.id, ar.description, ar.report_date,  
                ar.participants_data, ar.coordinator_feedback, ar.reviewed_at,
                ar.user_id as author_user_id, -- The ID of the user who created the report
                a.name as activity_name,
                p.name as project_name,
                reporter.full_name as reporter_name,
                org.name as partner_org_name
            FROM activity_reports ar
            JOIN activities a ON ar.activity_id = a.id
            JOIN work_packages wp ON a.work_package_id = wp.id
            JOIN projects p ON wp.project_id = p.id
            LEFT JOIN users reporter ON ar.user_id = reporter.id
            LEFT JOIN partners org ON reporter.partner_id = org.id
            WHERE ar.id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$report_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no report is found, return a 404 error.
    if (!$report) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Report not found.']);
        exit;
    }

    // Fetch associated files for the report.
    $files_stmt = $conn->prepare("SELECT id, original_filename, file_path FROM uploaded_files WHERE report_id = ?");
    $files_stmt->execute([$report_id]);
    $files = $files_stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Permission Logic ---

    // A user can modify a report if they are an admin/coordinator OR they are the author.
    $can_modify = (in_array($current_user_role, ['super_admin', 'admin', 'coordinator']) || $report['author_user_id'] == $current_user_id);
    
    // A user can change the status if they are an admin/coordinator.
    

    // --- Final Response ---

    // Prepare the data to be sent as JSON.
    $response_data = [
        'success' => true,
        'report' => [
            'id' => $report['id'],
            'description' => makeTextClickable($report['description']),
            'report_date' => formatDate($report['report_date']),
            
            'participants_data' => htmlspecialchars($report['participants_data']),
            'coordinator_feedback' => htmlspecialchars($report['coordinator_feedback']),
            'activity_name' => htmlspecialchars($report['activity_name']),
            'project_name' => htmlspecialchars($report['project_name']),
            'reporter_name' => htmlspecialchars($report['reporter_name']),
            'partner_org_name' => htmlspecialchars($report['partner_org_name'])
        ],
        'files' => $files,
        'permissions' => [
            'can_modify' => $can_modify,
            
        ]
    ];

    // Send the JSON response.
    echo json_encode($response_data);

} catch (PDOException $e) {
    // Handle potential database errors.
    http_response_code(500); // Internal Server Error
    error_log("API Error in get_report_details.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
}

?>
