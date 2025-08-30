<?php
// Include header
require_once '../includes/header.php';

// Auth and DB
$auth = new Auth();
$database = new Database();
$conn = $database->connect();

// User permissions check
$auth->requireLogin();
$user_role = getUserRole();
$current_user_id = getUserId(); // Assuming getUserId() returns the ID of the currently logged-in user

if ($user_role !== 'super_admin') {
    setErrorMessage("Access denied. Only Super Admins can delete users.");
    header('Location: projects.php');
    exit;
}

$user_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id_to_delete === 0) {
    setErrorMessage("User ID not provided for deletion.");
    header('Location: users.php');
    exit;
}

// Prevent a super admin from deleting themselves
if ($user_id_to_delete === $current_user_id) {
    setErrorMessage("You cannot delete your own super admin account.");
    header('Location: users.php');
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id_to_delete]);

    if ($stmt->rowCount() > 0) {
        setSuccessMessage("User deleted successfully!");
    } else {
        setErrorMessage("User not found or could not be deleted.");
    }
} catch (PDOException $e) {
    setErrorMessage("Error deleting user: " . $e->getMessage());
}

header('Location: users.php');
exit;
?>