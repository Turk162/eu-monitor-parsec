<?php
// Standalone debug script for a simple report insertion.

header('Content-Type: text/plain; charset=utf-8');

echo "=========================================\n";
echo "  SIMPLE REPORT INSERTION TEST         \n";
echo "=========================================\n\n";

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- STEP 1: LOAD FULL APPLICATION ENVIRONMENT ---
echo "[1] Loading application environment from header.php...\n";
require_once '../includes/header.php';
echo "    -> Environment loaded.\n\n";

// --- STEP 2: CHECK SESSION & USER DATA ---
echo "[2] Checking user and session data...\n";
$user_id = getUserId();
$user_role = getUserRole();
$user_partner_id = $_SESSION['partner_id'] ?? 0;
echo "    -> User ID: $user_id\n";
echo "    -> User Role: $user_role\n";
echo "    -> Partner ID: $user_partner_id\n\n";

if (empty($user_id) || empty($user_partner_id)) {
    echo "    -> FATAL: User ID or Partner ID is missing from the session. Cannot proceed.\n";
    exit;
}

// --- STEP 3: DATABASE CONNECTION ---
echo "[3] Connecting to database...\n";
$database = new Database();
$conn = $database->connect();
echo "    -> DB Connection successful.\n\n";

// --- STEP 4: GET VALID ACTIVITY/PROJECT IDs ---
echo "[4] Fetching valid IDs from an existing activity...\n";
try {
    $id_stmt = $conn->query("SELECT id, project_id FROM activities LIMIT 1");
    $valid_ids = $id_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$valid_ids) {
        echo "    -> FATAL: No activities found in the database. Cannot perform test.\n";
        exit;
    }
    $valid_activity_id = $valid_ids['id'];
    $valid_project_id = $valid_ids['project_id'];
    echo "    -> Using Activity ID: $valid_activity_id and Project ID: $valid_project_id\n\n";

    // --- STEP 5: ATTEMPT SIMPLE INSERT ---
    echo "[5] Attempting to insert a simple report (no risks, no files)...\n";
    $conn->beginTransaction();
    $sql = "INSERT INTO activity_reports (activity_id, project_id, partner_id, user_id, report_date, description, status) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$valid_activity_id, $valid_project_id, $user_partner_id, $user_id, date('Y-m-d'), 'Simple report insertion test']);
    $new_report_id = $conn->lastInsertId();
    $conn->commit();

    echo "    -> SUCCESS! Report created with ID: $new_report_id\n\n";
    echo "=========================================\n";
    echo "  TEST SUCCEEDED. The basic insertion works.\n";
    echo "  The problem is likely in how the form data is processed or in the RiskCalculator integration.\n";
    echo "=========================================\n";

} catch (Throwable $e) {
    echo "\n!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
    echo "   AN ERROR OCCURRED DURING INSERTION!   \n";
    echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
    echo "Error Type:    " . get_class($e) . "\n";
    echo "Error Message: " . $e->getMessage() . "\n";
    echo "File:          " . $e->getFile() . "\n";
    echo "Line:          " . $e->getLine() . "\n";
    if ($conn->inTransaction()) {
        $conn->rollBack();
        echo "\nDatabase transaction was rolled back.\n";
    }
}
