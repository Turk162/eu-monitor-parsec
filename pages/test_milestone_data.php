<?php
// ===================================================================
// TEST MILESTONE DATA - Debug file
// ===================================================================

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once '../config/database.php';

// Get project ID from URL (default to 1 for testing)
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 1;

echo "<h1>Test Milestone Data - Project ID: $project_id</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .section { margin: 30px 0; padding: 20px; border: 1px solid #ccc; }
    .error { color: red; font-weight: bold; }
    .success { color: green; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
</style>";

try {
    // Database connection
    $database = new Database();
    $conn = $database->connect();
    echo "<div class='section'><h2 class='success'>✓ Database connection successful</h2></div>";
    
    // ===================================================================
    // 1. CHECK PROJECT EXISTS
    // ===================================================================
    
    echo "<div class='section'>";
    echo "<h2>1. Project Check</h2>";
    
    $project_stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
    $project_stmt->execute([$project_id]);
    $project = $project_stmt->fetch();
    
    if ($project) {
        echo "<p class='success'>✓ Project found: " . htmlspecialchars($project['name']) . "</p>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        foreach ($project as $key => $value) {
            echo "<tr><td>$key</td><td>" . htmlspecialchars($value) . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>✗ Project not found with ID: $project_id</p>";
        exit;
    }
    echo "</div>";
    
    // ===================================================================
    // 2. CHECK WORK PACKAGES
    // ===================================================================
    
    echo "<div class='section'>";
    echo "<h2>2. Work Packages Check</h2>";
    
    $wp_stmt = $conn->prepare("
        SELECT wp.*, 
               u.full_name as lead_partner_name,
               part.name as lead_organization
        FROM work_packages wp
        LEFT JOIN users u ON wp.lead_partner_id = u.id
        LEFT JOIN partners part ON u.partner_id = part.id
        WHERE wp.project_id = ?
        ORDER BY wp.wp_number
    ");
    $wp_stmt->execute([$project_id]);
    $work_packages = $wp_stmt->fetchAll();
    
    echo "<p>Found " . count($work_packages) . " work packages</p>";
    
    if (count($work_packages) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>WP Number</th><th>Name</th><th>Start Date</th><th>End Date</th><th>Lead Partner</th></tr>";
        foreach ($work_packages as $wp) {
            echo "<tr>";
            echo "<td>" . $wp['id'] . "</td>";
            echo "<td>" . $wp['wp_number'] . "</td>";
            echo "<td>" . htmlspecialchars($wp['name']) . "</td>";
            echo "<td>" . $wp['start_date'] . "</td>";
            echo "<td>" . $wp['end_date'] . "</td>";
            echo "<td>" . htmlspecialchars($wp['lead_partner_name'] ?? 'None') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>⚠ No work packages found</p>";
    }
    echo "</div>";
    
    // ===================================================================
    // 3. CHECK MILESTONES
    // ===================================================================
    
    echo "<div class='section'>";
    echo "<h2>3. Milestones Check</h2>";
    
    // First, let's see the actual structure of milestones table
    echo "<h3>3.1 Milestones Table Structure</h3>";
    $structure_stmt = $conn->prepare("DESCRIBE milestones");
    $structure_stmt->execute();
    $columns = $structure_stmt->fetchAll();
    
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . $col['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Now check the milestones data
    echo "<h3>3.2 Milestones Data</h3>";
    
    $milestones_stmt = $conn->prepare("
        SELECT m.*, 
               wp.wp_number,
               wp.name as wp_name
        FROM milestones m
        LEFT JOIN work_packages wp ON m.work_package_id = wp.id
        WHERE m.project_id = ?
        ORDER BY m.due_date ASC
    ");
    $milestones_stmt->execute([$project_id]);
    $milestones = $milestones_stmt->fetchAll();
    
    echo "<p>Found " . count($milestones) . " milestones</p>";
    
    if (count($milestones) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>WP ID</th><th>WP Number</th><th>Due Date</th><th>Status</th><th>Description</th></tr>";
        foreach ($milestones as $milestone) {
            echo "<tr>";
            echo "<td>" . $milestone['id'] . "</td>";
            echo "<td>" . htmlspecialchars($milestone['name']) . "</td>";
            echo "<td>" . $milestone['work_package_id'] . "</td>";
            echo "<td>" . ($milestone['wp_number'] ?? 'NULL') . "</td>";
            echo "<td>" . ($milestone['due_date'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($milestone['status'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($milestone['description'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>⚠ No milestones found</p>";
    }
    echo "</div>";
    
    // ===================================================================
    // 4. CHECK MILESTONE-WP RELATIONSHIP
    // ===================================================================
    
    echo "<div class='section'>";
    echo "<h2>4. Milestone-Work Package Relationship Check</h2>";
    
    if (count($milestones) > 0 && count($work_packages) > 0) {
        echo "<h3>Milestones grouped by Work Package:</h3>";
        
        foreach ($work_packages as $wp) {
            echo "<h4>WP" . $wp['wp_number'] . ": " . htmlspecialchars($wp['name']) . " (ID: " . $wp['id'] . ")</h4>";
            
            $wp_milestones = array_filter($milestones, function($m) use ($wp) {
                return $m['work_package_id'] == $wp['id'];
            });
            
            if (count($wp_milestones) > 0) {
                echo "<ul>";
                foreach ($wp_milestones as $milestone) {
                    echo "<li>";
                    echo "<strong>" . htmlspecialchars($milestone['name']) . "</strong>";
                    echo " - Due: " . ($milestone['due_date'] ?? 'NULL');
                    echo " - Status: " . htmlspecialchars($milestone['status'] ?? 'NULL');
                    echo "</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>No milestones found for this work package</p>";
            }
        }
    }
    echo "</div>";
    
    // ===================================================================
    // 5. TIMELINE MONTHS TEST
    // ===================================================================
    
    echo "<div class='section'>";
    echo "<h2>5. Timeline Months Test</h2>";
    
    if ($project) {
        $project_start = new DateTime($project['start_date']);
        $project_end = new DateTime($project['end_date']);
        
        echo "<p>Project duration: " . $project['start_date'] . " to " . $project['end_date'] . "</p>";
        
        // Generate monthly timeline
        $timeline_months = [];
        $current_date = clone $project_start;
        $current_date->modify('first day of this month');
        
        while ($current_date <= $project_end) {
            $timeline_months[] = [
                'year' => $current_date->format('Y'),
                'month' => $current_date->format('n'),
                'month_name' => $current_date->format('M'),
                'full_name' => $current_date->format('F Y'),
                'date_key' => $current_date->format('Y-m')
            ];
            $current_date->modify('+1 month');
        }
        
        echo "<p>Timeline months (" . count($timeline_months) . "):</p>";
        echo "<table>";
        echo "<tr><th>Date Key</th><th>Month Name</th><th>Full Name</th></tr>";
        foreach ($timeline_months as $month) {
            echo "<tr>";
            echo "<td>" . $month['date_key'] . "</td>";
            echo "<td>" . $month['month_name'] . "</td>";
            echo "<td>" . $month['full_name'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // ===================================================================
    // 6. MILESTONE MONTH MATCHING TEST
    // ===================================================================
    
    echo "<div class='section'>";
    echo "<h2>6. Milestone Month Matching Test</h2>";
    
    if (count($milestones) > 0 && isset($timeline_months)) {
        echo "<h3>Testing milestone date matching:</h3>";
        
        foreach ($milestones as $milestone) {
            if ($milestone['due_date']) {
                $milestone_month_key = date('Y-m', strtotime($milestone['due_date']));
                $found_in_timeline = false;
                
                foreach ($timeline_months as $month) {
                    if ($month['date_key'] === $milestone_month_key) {
                        $found_in_timeline = true;
                        break;
                    }
                }
                
                echo "<p>";
                echo "<strong>" . htmlspecialchars($milestone['name']) . "</strong><br>";
                echo "Due date: " . $milestone['due_date'] . "<br>";
                echo "Month key: " . $milestone_month_key . "<br>";
                echo "Found in timeline: " . ($found_in_timeline ? "<span class='success'>YES</span>" : "<span class='error'>NO</span>");
                echo "</p>";
            }
        }
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='section'>";
    echo "<h2 class='error'>✗ Error occurred:</h2>";
    echo "<p class='error'>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>

<div class="section">
    <h2>Test Complete</h2>
    <p>Check the output above to identify any issues with the milestone data.</p>
    <p><a href="?id=<?= $project_id ?>">Refresh Test</a> | <a href="project-gantt.php?id=<?= $project_id ?>">Go to Gantt</a></p>
</div>