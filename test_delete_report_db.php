<?php
/**
 * Test script for debugging report deletion database logic.
 *
 * How to use:
 * 1. Find a report ID that you want to test deleting.
 * 2. Access this script in your browser, e.g., /test_delete_report_db.php?id=123
 * 3. The script will simulate the deletion and print the steps.
 * 4. IMPORTANT: This script will NOT actually delete the data as the transaction is rolled back.
 */

echo "<pre>"; // Use preformatted text for cleaner output

// --- Setup ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';
require_once 'includes/functions.php';

// --- Get Report ID ---
$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$report_id) {
    die("ERROR: No report ID provided. Please use a URL like test_delete_report_db.php?id=123");
}

echo "--- STARTING DELETE TEST FOR REPORT ID: $report_id ---\\n\\n";

// --- Database Connection ---
try {
    $database = new Database();
    $conn = $database->connect();
    echo "[SUCCESS] Connected to the database.\\n";
} catch (PDOException $e) {
    die("[FATAL] Database Connection Failed: " . $e->getMessage() . "\\n");
}

// --- Test Logic ---
try {
    echo "\\n--- STEP 1: Starting Database Transaction ---\\n";
    $conn->beginTransaction();
    echo "[OK] Transaction started.\\n";

    // --- Check if report exists ---
    $stmt = $conn->prepare("SELECT * FROM activity_reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch();

    if (!$report) {
        die("[ERROR] Report with ID $report_id not found.\\n");
    }
    echo "[OK] Report #$report_id found in 'activity_reports' table.\\n";

    // --- Find associated files ---
    echo "\\n--- STEP 2: Finding associated files in 'uploaded_files' table ---\\n";
    $files_stmt = $conn->prepare("SELECT id, file_path, original_filename FROM uploaded_files WHERE report_id = ?");
    $files_stmt->execute([$report_id]);
    $files_to_delete = $files_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($files_to_delete) > 0) {
        echo "[INFO] Found " . count($files_to_delete) . " associated file(s):\\n";
        foreach ($files_to_delete as $file) {
            echo "  - File ID: {$file['id']}, Path: {$file['file_path']}\\n";
        }
    } else {
        echo "[INFO] No associated files found for this report.\\n";
    }

    // --- Simulate deleting file records from DB ---
    echo "\\n--- STEP 3: Simulating deletion from 'uploaded_files' table ---\\n";
    $delete_files_stmt = $conn->prepare("DELETE FROM uploaded_files WHERE report_id = ?");
    $delete_files_stmt->execute([$report_id]);
    $deleted_files_count = $delete_files_stmt->rowCount();
    echo "[OK] Simulated deletion of $deleted_files_count file record(s) from the database.\\n";

    // --- Simulate deleting the report record ---
    echo "\\n--- STEP 4: Simulating deletion from 'activity_reports' table ---\\n";
    $delete_report_stmt = $conn->prepare("DELETE FROM activity_reports WHERE id = ?");
    $delete_report_stmt->execute([$report_id]);
    $deleted_reports_count = $delete_report_stmt->rowCount();
    echo "[OK] Simulated deletion of $deleted_reports_count report record(s).\\n";

    // --- Final Check ---
    if ($deleted_reports_count > 0) {
        echo "\\n[SUCCESS] The database commands to delete the report were executed without errors.\\n";
    } else {
        echo "\\n[WARNING] The report deletion command ran, but no rows were affected. The report might have been deleted by another process already.\\n";
    }

} catch (PDOException $e) {
    echo "\\n[FATAL DATABASE ERROR] An error occurred during the deletion simulation:\\n";
    echo $e->getMessage() . "\\n";
    echo "\\n--- TRANSACTION WILL BE ROLLED BACK ---\\n";
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    die();
}

// --- Rollback Transaction ---
echo "\\n--- FINAL STEP: Rolling back transaction ---\\n";
$conn->rollBack();
echo "[OK] Transaction has been rolled back. No actual data was deleted from the database.\\n";

echo "\\n--- TEST COMPLETE ---\\n";
echo "</pre>";

?>
