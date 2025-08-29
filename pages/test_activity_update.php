<?php
// ===================================================================
//  TEST FILE per DEBUG Activity Update - VERSIONE SEMPLIFICATA
//  Salva come: pages/test_simple.php
// ===================================================================

// Disable error output in JSON responses
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ini_set('display_errors', 0);
    error_reporting(0);
    header('Content-Type: application/json');
}

$debug_log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $debug_log[] = "POST received: " . json_encode($_POST);
    
    try {
        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $debug_log[] = "Session started";
        
        // Include only essential files
        require_once '../config/database.php';
        require_once '../config/auth.php';
        
        $debug_log[] = "Files included successfully";
        
        // Get form data
        $activity_id = (int)($_POST['activity_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $progress = (float)($_POST['progress'] ?? 0);
        
        $debug_log[] = "Data: ID=$activity_id, Status=$status, Progress=$progress";
        
        if (!$activity_id) {
            echo json_encode(['success' => false, 'message' => 'No activity ID', 'debug' => $debug_log]);
            exit;
        }
        
        // Test database connection
        $database = new Database();
        $conn = $database->connect();
        
        $debug_log[] = "Database connected successfully";
        
        // Check if activity exists
        $stmt = $conn->prepare("SELECT id, status, progress, responsible_partner_id FROM activities WHERE id = ?");
        $stmt->execute([$activity_id]);
        $activity = $stmt->fetch();
        
        if (!$activity) {
            echo json_encode(['success' => false, 'message' => 'Activity not found', 'debug' => $debug_log]);
            exit;
        }
        
        $debug_log[] = "Activity found: " . json_encode($activity);
        
        // Get user info
        $user_id = $_SESSION['user_id'] ?? 0;
        $user_role = $_SESSION['user_role'] ?? 'none';
        
        $debug_log[] = "User: ID=$user_id, Role=$user_role";
        
        // Simple permission check
        $can_modify = ($user_role === 'super_admin' || $activity['responsible_partner_id'] == $user_id);
        
        $debug_log[] = "Can modify: " . ($can_modify ? 'YES' : 'NO');
        
        if (!$can_modify) {
            echo json_encode(['success' => false, 'message' => 'No permission', 'debug' => $debug_log]);
            exit;
        }
        
        // Try to update
        $update_stmt = $conn->prepare("UPDATE activities SET status = ?, progress = ?, updated_at = NOW() WHERE id = ?");
        $result = $update_stmt->execute([$status, $progress, $activity_id]);
        
        $debug_log[] = "Update result: " . ($result ? 'SUCCESS' : 'FAILED');
        
        if ($result) {
            // Verify update
            $verify_stmt = $conn->prepare("SELECT status, progress FROM activities WHERE id = ?");
            $verify_stmt->execute([$activity_id]);
            $updated = $verify_stmt->fetch();
            
            $debug_log[] = "Verified update: " . json_encode($updated);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Updated successfully!',
                'old' => ['status' => $activity['status'], 'progress' => $activity['progress']],
                'new' => ['status' => $updated['status'], 'progress' => $updated['progress']],
                'debug' => $debug_log
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed', 'debug' => $debug_log]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage(), 'debug' => $debug_log]);
    }
    
    exit;
}

// GET request - show form
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Activity Test</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        form { background: #f5f5f5; padding: 20px; border-radius: 5px; }
        label { display: block; margin: 10px 0; }
        input, select, button { padding: 8px; margin: 5px 0; }
        #result { background: #fff; border: 1px solid #ddd; padding: 15px; margin-top: 20px; white-space: pre-wrap; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h2>Simple Activity Update Test</h2>
    
    <form id="testForm">
        <label>
            Activity ID: <input type="number" name="activity_id" value="1" required>
            <small>Insert an existing activity ID</small>
        </label>
        
        <label>
            Status: 
            <select name="status" required>
                <option value="not_started">Not Started</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
            </select>
        </label>
        
        <label>
            Progress: <input type="number" name="progress" value="75" min="0" max="100" required>
        </label>
        
        <button type="submit">Test Update</button>
    </form>
    
    <div id="result"></div>
    
    <script>
    $('#testForm').submit(function(e) {
        e.preventDefault();
        
        console.log('=== TEST START ===');
        
        var formData = {
            action: 'update_status', // Even though we don't check it in this test
            activity_id: $('input[name="activity_id"]').val(),
            status: $('select[name="status"]').val(),
            progress: $('input[name="progress"]').val()
        };
        
        console.log('Sending:', formData);
        $('#result').html('Testing...').removeClass('error success');
        
        $.post('test_simple.php', formData)
            .done(function(response) {
                console.log('Response:', response);
                
                if (response.success) {
                    $('#result').addClass('success').html(
                        'SUCCESS!\n\n' +
                        'Old: Status=' + response.old.status + ', Progress=' + response.old.progress + '%\n' +
                        'New: Status=' + response.new.status + ', Progress=' + response.new.progress + '%\n\n' +
                        'Debug Log:\n' + response.debug.join('\n')
                    );
                } else {
                    $('#result').addClass('error').html(
                        'FAILED: ' + response.message + '\n\n' +
                        'Debug Log:\n' + (response.debug ? response.debug.join('\n') : 'No debug info')
                    );
                }
            })
            .fail(function(xhr, status, error) {
                console.log('AJAX Failed:', status, error);
                console.log('Response Text:', xhr.responseText);
                
                $('#result').addClass('error').html(
                    'AJAX FAILED!\n' +
                    'Status: ' + status + '\n' +
                    'Error: ' + error + '\n\n' +
                    'Response:\n' + xhr.responseText
                );
            });
        
        console.log('=== TEST END ===');
    });
    </script>
</body>
</html>