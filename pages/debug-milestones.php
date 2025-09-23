<?php
// ===================================================================
// DEBUG MILESTONE FORM - Intercetta POST dalla pagina reale
// ===================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 32;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Milestone Form Real</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .debug { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 4px; }
        .test-form { background: #ecf0f1; padding: 15px; border-radius: 4px; margin: 15px 0; }
    </style>
</head>
<body>

<h1>Debug Milestone Form - Project <?= $project_id ?></h1>

<?php

// ===================================================================
// COPIA ESATTA DELLA LOGICA add-project-milestones.php
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<div class='debug'>";
    echo "<h2>POST Data Received</h2>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    
    echo "<h2>Session Data</h2>";
    echo "<pre>" . print_r($_SESSION, true) . "</pre>";
    
    echo "<h2>Server Data</h2>";
    echo "<pre>";
    echo "HTTP_HOST: " . $_SERVER['HTTP_HOST'] . "\n";
    echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
    echo "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
    echo "CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set') . "\n";
    echo "</pre>";
    
    try {
        $database = new Database();
        $conn = $database->connect();
        echo "<p class='success'>✓ Database connected</p>";
        
        // Test project exists
        $project_stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
        $project_stmt->execute([$project_id]);
        $project = $project_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            echo "<p class='error'>✗ Project $project_id not found</p>";
            echo "</div>";
            exit;
        }
        
        echo "<p class='success'>✓ Project found: " . htmlspecialchars($project['name']) . "</p>";
        
        // Process milestone data exactly like the real form
        $milestones_data = $_POST['milestones'] ?? [];
        echo "<h3>Milestones Data Processing</h3>";
        echo "<p>Milestones array count: " . count($milestones_data) . "</p>";
        
        if (empty($milestones_data)) {
            echo "<p class='warning'>⚠ No milestones data received</p>";
            echo "</div>";
            exit;
        }
        
        // TEST 1: Check sanitizeInput function
        echo "<h3>Testing sanitizeInput function</h3>";
        if (function_exists('sanitizeInput')) {
            $test_input = "Test <script>alert('test')</script>";
            $sanitized = sanitizeInput($test_input);
            echo "<p class='success'>✓ sanitizeInput exists</p>";
            echo "<p>Test: '$test_input' → '$sanitized'</p>";
        } else {
            echo "<p class='error'>✗ sanitizeInput function not found</p>";
            echo "<p>Creating fallback function...</p>";
            function sanitizeInput($input) {
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
            }
        }
        
        // TEST 2: Begin transaction and prepare statement
        echo "<h3>Database Transaction Test</h3>";
        $conn->beginTransaction();
        echo "<p class='success'>✓ Transaction started</p>";
        
        // EXACT query from add-project-milestones.php
        $stmt = $conn->prepare(
            "INSERT INTO milestones (project_id, work_package_id, name, description, due_date, status) 
             VALUES (:project_id, :work_package_id, :name, :description, :due_date, 'pending')"
        );
        echo "<p class='success'>✓ Statement prepared</p>";
        
        $insert_count = 0;
        foreach ($milestones_data as $index => $ms_data) {
            echo "<h4>Processing Milestone #$index</h4>";
            echo "<pre>" . print_r($ms_data, true) . "</pre>";
            
            // Exact validation from real form
            if (empty($ms_data['name']) || empty($ms_data['due_date'])) {
                echo "<p class='warning'>⚠ Skipping empty milestone (name or due_date missing)</p>";
                continue;
            }
            
            echo "<p class='success'>✓ Milestone validation passed</p>";
            
            // Prepare parameters exactly like real form
            $params = [
                ':project_id' => $project_id,
                ':work_package_id' => !empty($ms_data['work_package_id']) ? (int)$ms_data['work_package_id'] : null,
                ':name' => sanitizeInput($ms_data['name']),
                ':description' => sanitizeInput($ms_data['description'] ?? ''),
                ':due_date' => $ms_data['due_date']
            ];
            
            echo "<h5>Parameters for database:</h5>";
            echo "<pre>" . print_r($params, true) . "</pre>";
            
            // Execute statement
            echo "<h5>Executing statement...</h5>";
            try {
                $result = $stmt->execute($params);
                if ($result) {
                    $milestone_id = $conn->lastInsertId();
                    echo "<p class='success'>✓ Milestone inserted successfully! ID: $milestone_id</p>";
                    $insert_count++;
                } else {
                    echo "<p class='error'>✗ Statement execute returned false</p>";
                    echo "<pre>" . print_r($stmt->errorInfo(), true) . "</pre>";
                }
            } catch (PDOException $e) {
                echo "<p class='error'>✗ PDO Exception during execute:</p>";
                echo "<pre>" . $e->getMessage() . "</pre>";
                echo "<p>Error Code: " . $e->getCode() . "</p>";
                echo "<p>Error Info:</p>";
                echo "<pre>" . print_r($stmt->errorInfo(), true) . "</pre>";
            }
        }
        
        // TEST 3: Commit transaction
        echo "<h3>Transaction Commit</h3>";
        try {
            $conn->commit();
            echo "<p class='success'>✓ Transaction committed successfully</p>";
            echo "<p class='success'>Total milestones inserted: $insert_count</p>";
        } catch (PDOException $e) {
            echo "<p class='error'>✗ Commit failed:</p>";
            echo "<pre>" . $e->getMessage() . "</pre>";
        }
        
        // TEST 4: Check message functions
        echo "<h3>Testing Message Functions</h3>";
        if (function_exists('setSuccessMessage')) {
            echo "<p class='success'>✓ setSuccessMessage exists</p>";
            setSuccessMessage('Test success message');
        } else {
            echo "<p class='error'>✗ setSuccessMessage not found</p>";
        }
        
        if (function_exists('setErrorMessage')) {
            echo "<p class='success'>✓ setErrorMessage exists</p>";
        } else {
            echo "<p class='error'>✗ setErrorMessage not found</p>";
        }
        
        if (function_exists('displayAlert')) {
            echo "<p class='success'>✓ displayAlert exists</p>";
        } else {
            echo "<p class='error'>✗ displayAlert not found</p>";
        }
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        echo "<p class='error'>✗ Fatal exception:</p>";
        echo "<pre>" . $e->getMessage() . "</pre>";
        echo "<p>File: " . $e->getFile() . "</p>";
        echo "<p>Line: " . $e->getLine() . "</p>";
        echo "<p>Trace:</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
    
    echo "</div>";
}

?>

<div class='debug'>
    <h2>Real Form Test</h2>
    <p>Questo form simula esattamente quello della pagina milestone:</p>
    
    <div class='test-form'>
        <form method='POST'>
            <p><strong>Milestone Name:</strong> 
               <input type='text' name='milestones[0][name]' value='Debug Test Milestone' required style='width: 300px; padding: 5px;'>
            </p>
            
            <p><strong>Due Date:</strong> 
               <input type='date' name='milestones[0][due_date]' value='<?= date('Y-m-d', strtotime('+30 days')) ?>' required style='padding: 5px;'>
            </p>
            
            <p><strong>Description:</strong> 
               <textarea name='milestones[0][description]' style='width: 300px; height: 60px; padding: 5px;'>Test milestone description from debug form</textarea>
            </p>
            
            <p><strong>Work Package:</strong> 
               <select name='milestones[0][work_package_id]' style='padding: 5px;'>
                   <option value=''>-- No specific WP --</option>
                   <option value='17'>WP1: Project Management</option>
               </select>
            </p>
            
            <p>
               <input type='submit' value='Test Submit Milestone' 
                      style='background: #e74c3c; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;'>
            </p>
        </form>
    </div>
</div>

<div class='debug'>
    <h2>Quick Actions</h2>
    <p><a href="add-project-milestones.php?project_id=<?= $project_id ?>" style="background: #27ae60; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">← Back to Real Milestone Page</a></p>
    <p><a href="?project_id=<?= $project_id ?>" style="background: #95a5a6; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">Refresh Debug</a></p>
</div>

</body>
</html>