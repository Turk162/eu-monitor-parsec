<?php
// ===================================================================
//  DEBUG DELETE PROJECT - DIAGNOSTIC TOOL
// ===================================================================
// This file helps debug why project deletion is not working.
// It provides detailed information about foreign key constraints,
// related data, and step-by-step deletion process.
// ===================================================================

session_start();
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Authentication
$auth = new Auth();
$auth->requireLogin();
$user_id = getUserId();
$user_role = getUserRole();

// Get project ID
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$debug_mode = isset($_GET['debug']) ? $_GET['debug'] : 'info';

if (!$project_id) {
    die("❌ Project ID required. Use: debug-delete-project.php?project_id=X");
}

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "<h1>🔍 DEBUG DELETE PROJECT - ID: $project_id</h1>";
    echo "<style>
        body { font-family: monospace; margin: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; font-weight: bold; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; background: #f9f9f9; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .sql { background: #000; color: #0f0; padding: 10px; margin: 10px 0; font-family: courier; }
    </style>";

    // ===================================================================
    // 1. PROJECT INFORMATION
    // ===================================================================
    echo "<div class='section'>";
    echo "<h2>📋 1. PROJECT INFORMATION</h2>";
    
    $project_stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
    $project_stmt->execute([$project_id]);
    $project = $project_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        echo "<span class='error'>❌ PROJECT NOT FOUND!</span>";
        exit;
    }
    
    echo "<span class='success'>✅ Project found: " . htmlspecialchars($project['name']) . "</span><br>";
    echo "Coordinator ID: " . $project['coordinator_id'] . "<br>";
    echo "Status: " . $project['status'] . "<br>";
    echo "Created: " . $project['created_at'] . "<br>";
    echo "</div>";

    // ===================================================================
    // 2. FOREIGN KEY CONSTRAINTS CHECK
    // ===================================================================
    echo "<div class='section'>";
    echo "<h2>🔗 2. FOREIGN KEY CONSTRAINTS</h2>";
    
    $constraints_query = "
        SELECT 
            kcu.TABLE_NAME,
            kcu.COLUMN_NAME,
            rc.CONSTRAINT_NAME,
            kcu.REFERENCED_TABLE_NAME,
            kcu.REFERENCED_COLUMN_NAME,
            rc.DELETE_RULE
        FROM 
            information_schema.REFERENTIAL_CONSTRAINTS rc
        JOIN 
            information_schema.KEY_COLUMN_USAGE kcu 
            ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME 
            AND rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
        WHERE 
            rc.CONSTRAINT_SCHEMA = DATABASE()
            AND (kcu.REFERENCED_TABLE_NAME = 'projects' OR kcu.TABLE_NAME = 'projects')
        ORDER BY kcu.TABLE_NAME
    ";
    
    $constraints_stmt = $conn->prepare($constraints_query);
    $constraints_stmt->execute();
    $constraints = $constraints_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($constraints) {
        echo "<table>";
        echo "<tr><th>Table</th><th>Column</th><th>References</th><th>Delete Rule</th></tr>";
        foreach ($constraints as $constraint) {
            $delete_rule_color = ($constraint['DELETE_RULE'] == 'CASCADE') ? 'success' : 'warning';
            echo "<tr>";
            echo "<td>" . $constraint['TABLE_NAME'] . "</td>";
            echo "<td>" . $constraint['COLUMN_NAME'] . "</td>";
            echo "<td>" . $constraint['REFERENCED_TABLE_NAME'] . "." . $constraint['REFERENCED_COLUMN_NAME'] . "</td>";
            echo "<td class='$delete_rule_color'>" . $constraint['DELETE_RULE'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<span class='warning'>⚠️ No foreign key constraints found!</span>";
    }
    echo "</div>";

    // ===================================================================
    // 3. RELATED DATA COUNT
    // ===================================================================
    echo "<div class='section'>";
    echo "<h2>📊 3. RELATED DATA COUNT</h2>";
    
    $related_data = [
        'project_partners' => "SELECT COUNT(*) FROM project_partners WHERE project_id = ?",
        'work_packages' => "SELECT COUNT(*) FROM work_packages WHERE project_id = ?",
        'milestones' => "SELECT COUNT(*) FROM milestones WHERE project_id = ?",
        'alerts' => "SELECT COUNT(*) FROM alerts WHERE project_id = ?",
        'participant_categories' => "SELECT COUNT(*) FROM participant_categories WHERE project_id = ?",
        'activities' => "SELECT COUNT(*) FROM activities a JOIN work_packages wp ON a.work_package_id = wp.id WHERE wp.project_id = ?",
        'activity_reports' => "SELECT COUNT(*) FROM activity_reports ar JOIN activities a ON ar.activity_id = a.id JOIN work_packages wp ON a.work_package_id = wp.id WHERE wp.project_id = ?",
        'uploaded_files' => "SELECT COUNT(*) FROM uploaded_files uf JOIN activity_reports ar ON uf.report_id = ar.id JOIN activities a ON ar.activity_id = a.id JOIN work_packages wp ON a.work_package_id = wp.id WHERE wp.project_id = ?"
    ];
    
    echo "<table>";
    echo "<tr><th>Table</th><th>Count</th><th>Status</th></tr>";
    foreach ($related_data as $table => $query) {
        try {
            $stmt = $conn->prepare($query);
            $stmt->execute([$project_id]);
            $count = $stmt->fetchColumn();
            $status_class = ($count > 0) ? 'warning' : 'success';
            $status_text = ($count > 0) ? "⚠️ Has data" : "✅ Empty";
            echo "<tr><td>$table</td><td>$count</td><td class='$status_class'>$status_text</td></tr>";
        } catch (Exception $e) {
            echo "<tr><td>$table</td><td class='error'>ERROR</td><td class='error'>" . $e->getMessage() . "</td></tr>";
        }
    }
    echo "</table>";
    echo "</div>";

    // ===================================================================
    // 4. STEP-BY-STEP DELETION TEST (DRY RUN)
    // ===================================================================
    if ($debug_mode == 'dryrun') {
        echo "<div class='section'>";
        echo "<h2>🧪 4. DRY RUN - STEP-BY-STEP DELETION TEST</h2>";
        echo "<p class='info'>ℹ️ This is a DRY RUN - no data will be deleted!</p>";
        
        $deletion_steps = [
            "uploaded_files" => "
                DELETE uf FROM uploaded_files uf
                INNER JOIN activity_reports ar ON uf.report_id = ar.id
                INNER JOIN activities a ON ar.activity_id = a.id
                INNER JOIN work_packages wp ON a.work_package_id = wp.id
                WHERE wp.project_id = ?",
            "activity_reports" => "
                DELETE ar FROM activity_reports ar
                INNER JOIN activities a ON ar.activity_id = a.id
                INNER JOIN work_packages wp ON a.work_package_id = wp.id
                WHERE wp.project_id = ?",
            "activities" => "
                DELETE a FROM activities a
                INNER JOIN work_packages wp ON a.work_package_id = wp.id
                WHERE wp.project_id = ?",
            "milestones" => "DELETE FROM milestones WHERE project_id = ?",
            "alerts" => "DELETE FROM alerts WHERE project_id = ?",
            "participant_categories" => "DELETE FROM participant_categories WHERE project_id = ?",
            "work_packages" => "DELETE FROM work_packages WHERE project_id = ?",
            "project_partners" => "DELETE FROM project_partners WHERE project_id = ?",
            "projects" => "DELETE FROM projects WHERE id = ?"
        ];
        
        $conn->beginTransaction();
        
        echo "<ol>";
        foreach ($deletion_steps as $step => $query) {
            echo "<li><strong>$step</strong><br>";
            echo "<div class='sql'>" . htmlspecialchars(trim($query)) . "</div>";
            
            try {
                $stmt = $conn->prepare($query);
                $stmt->execute([$project_id]);
                $affected = $stmt->rowCount();
                echo "<span class='success'>✅ Would delete $affected rows</span><br><br>";
            } catch (Exception $e) {
                echo "<span class='error'>❌ ERROR: " . $e->getMessage() . "</span><br><br>";
            }
        }
        echo "</ol>";
        
        $conn->rollBack();
        echo "<p class='info'>🔄 Transaction rolled back - no data was actually deleted!</p>";
        echo "</div>";
    }

    // ===================================================================
    // 5. ACTUAL DELETION (DANGEROUS!)
    // ===================================================================
    if ($debug_mode == 'delete' && $user_role == 'super_admin') {
        echo "<div class='section'>";
        echo "<h2>💀 5. ACTUAL DELETION - DANGER ZONE!</h2>";
        echo "<p class='error'>⚠️ THIS WILL ACTUALLY DELETE THE PROJECT!</p>";
        
        $deletion_steps = [
            "uploaded_files" => "
                DELETE uf FROM uploaded_files uf
                INNER JOIN activity_reports ar ON uf.report_id = ar.id
                INNER JOIN activities a ON ar.activity_id = a.id
                INNER JOIN work_packages wp ON a.work_package_id = wp.id
                WHERE wp.project_id = ?",
            "activity_reports" => "
                DELETE ar FROM activity_reports ar
                INNER JOIN activities a ON ar.activity_id = a.id
                INNER JOIN work_packages wp ON a.work_package_id = wp.id
                WHERE wp.project_id = ?",
            "activities" => "
                DELETE a FROM activities a
                INNER JOIN work_packages wp ON a.work_package_id = wp.id
                WHERE wp.project_id = ?",
            "milestones" => "DELETE FROM milestones WHERE project_id = ?",
            "alerts" => "DELETE FROM alerts WHERE project_id = ?",
            "participant_categories" => "DELETE FROM participant_categories WHERE project_id = ?",
            "work_packages" => "DELETE FROM work_packages WHERE project_id = ?",
            "project_partners" => "DELETE FROM project_partners WHERE project_id = ?",
            "projects" => "DELETE FROM projects WHERE id = ?"
        ];
        
        $conn->beginTransaction();
        $all_success = true;
        
        echo "<ol>";
        foreach ($deletion_steps as $step => $query) {
            echo "<li><strong>$step</strong><br>";
            echo "<div class='sql'>" . htmlspecialchars(trim($query)) . "</div>";
            
            try {
                $stmt = $conn->prepare($query);
                $stmt->execute([$project_id]);
                $affected = $stmt->rowCount();
                echo "<span class='success'>✅ Deleted $affected rows</span><br><br>";
            } catch (Exception $e) {
                echo "<span class='error'>❌ ERROR: " . $e->getMessage() . "</span><br><br>";
                $all_success = false;
                break;
            }
        }
        echo "</ol>";
        
        if ($all_success) {
            $conn->commit();
            echo "<p class='success'>🎉 PROJECT SUCCESSFULLY DELETED!</p>";
            echo "<p><a href='projects.php'>← Back to Projects</a></p>";
        } else {
            $conn->rollBack();
            echo "<p class='error'>💥 DELETION FAILED - Transaction rolled back!</p>";
        }
        echo "</div>";
    }

    // ===================================================================
    // 6. NAVIGATION
    // ===================================================================
    echo "<div class='section'>";
    echo "<h2>🎛️ DEBUG OPTIONS</h2>";
    echo "<p>";
    echo "<a href='?project_id=$project_id&debug=info'>📋 Basic Info</a> | ";
    echo "<a href='?project_id=$project_id&debug=dryrun'>🧪 Dry Run Test</a>";
    if ($user_role == 'super_admin') {
        echo " | <a href='?project_id=$project_id&debug=delete' onclick='return confirm(\"ARE YOU SURE? This will PERMANENTLY DELETE the project!\")'>💀 ACTUAL DELETE</a>";
    }
    echo "</p>";
    echo "<p><a href='projects.php'>← Back to Projects</a></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>💥 FATAL ERROR</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}
?>