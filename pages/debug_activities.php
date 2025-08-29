<?php
// ===================================================================
//  DEBUG FILE per Activities Update
//  Salva come: pages/debug_activities.php
// ===================================================================

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

echo "<h2>DEBUG Activities Update System</h2>";

// 1. Test sessione utente
echo "<h3>1. SESSION DATA:</h3>";
echo "<pre>";
var_dump($_SESSION);
echo "</pre>";

$user_id = getUserId();
$user_role = getUserRole();
$user_partner_id = $_SESSION['partner_id'] ?? 0;

echo "<p><strong>User ID:</strong> $user_id</p>";
echo "<p><strong>User Role:</strong> $user_role</p>";
echo "<p><strong>User Partner ID:</strong> $user_partner_id</p>";

// 2. Test connessione database
echo "<h3>2. DATABASE CONNECTION:</h3>";
try {
    $database = new Database();
    $conn = $database->connect();
    echo "<p style='color: green;'>✓ Database connection OK</p>";
    
    // Test query attività
    $test_stmt = $conn->query("SELECT id, name, status, responsible_partner_id FROM activities LIMIT 3");
    $test_activities = $test_stmt->fetchAll();
    echo "<p><strong>Sample Activities:</strong></p>";
    echo "<pre>";
    var_dump($test_activities);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database error: " . $e->getMessage() . "</p>";
}

// 3. Test richieste POST
echo "<h3>3. POST REQUEST TEST:</h3>";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<p style='color: blue;'>POST REQUEST RECEIVED</p>";
    echo "<p><strong>POST Data:</strong></p>";
    echo "<pre>";
    var_dump($_POST);
    echo "</pre>";
    
    if (isset($_POST['update_activity'])) {
        header('Content-Type: application/json');
        
        $activity_id = (int)($_POST['activity_id'] ?? 0);
        $new_status = $_POST['status'] ?? '';
        
        echo "<p><strong>Processing:</strong> Activity ID = $activity_id, Status = $new_status</p>";
        
        // Test permessi
        if ($user_role === 'super_admin') {
            $can_modify = true;
            echo "<p style='color: green;'>✓ Super admin - can modify</p>";
        } else {
            $stmt = $conn->prepare("SELECT responsible_partner_id FROM activities WHERE id = ?");
            $stmt->execute([$activity_id]);
            $responsible_partner = $stmt->fetchColumn();
            $can_modify = ($responsible_partner == $user_partner_id);
            echo "<p><strong>Activity Partner:</strong> $responsible_partner</p>";
            echo "<p><strong>User Partner:</strong> $user_partner_id</p>";
            echo "<p style='color: " . ($can_modify ? 'green' : 'red') . ";'>" . ($can_modify ? '✓' : '✗') . " Permission check</p>";
        }
        
        if ($can_modify) {
            $update_stmt = $conn->prepare("UPDATE activities SET status = ?, updated_at = NOW() WHERE id = ?");
            $success = $update_stmt->execute([$new_status, $activity_id]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'DEBUG: Update successful!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'DEBUG: Update failed']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'DEBUG: No permission']);
        }
        exit;
    }
} else {
    echo "<p>No POST request - showing debug info only</p>";
}

// 4. Test form
echo "<h3>4. TEST FORM:</h3>";
if (!empty($test_activities)) {
    $test_activity = $test_activities[0];
    echo "<p>Testing with Activity ID: {$test_activity['id']} - {$test_activity['name']}</p>";
    
    echo '<form method="POST" action="">
        <input type="hidden" name="update_activity" value="1">
        <input type="hidden" name="activity_id" value="' . $test_activity['id'] . '">
        <select name="status">
            <option value="not_started">Not Started</option>
            <option value="in_progress" selected>In Progress</option>
            <option value="completed">Completed</option>
        </select>
        <button type="submit">Test Update</button>
    </form>';
}

// 5. JavaScript Test
echo "<h3>5. JAVASCRIPT TEST:</h3>";
echo '<script src="../assets/js/core/jquery.min.js"></script>';
echo '<button onclick="testAjax()">Test AJAX Call</button>';
echo '<div id="ajax-result"></div>';

echo '<script>
function testAjax() {
    console.log("Testing AJAX...");
    $.post("debug_activities.php", {
        action: "update_status",
        activity_id: ' . ($test_activities[0]['id'] ?? 1) . ',
        status: "completed",
        update_activity: 1
    }, function(response) {
        console.log("SUCCESS:", response);
        $("#ajax-result").html("<p style=\"color: green;\">AJAX Success: " + JSON.stringify(response) + "</p>");
    }, "json").fail(function(xhr, status, error) {
        console.log("ERROR:", status, error);
        console.log("Response:", xhr.responseText);
        $("#ajax-result").html("<p style=\"color: red;\">AJAX Error: " + status + " - " + error + "<br>Response: " + xhr.responseText + "</p>");
    });
}
</script>';

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
h3 { color: #333; border-bottom: 2px solid #ccc; padding-bottom: 5px; }
</style>