<?php
// Page configuration
$page_title = 'Edit User - EU Project Manager';

// Include header
require_once '../includes/header.php';

// Auth and DB
$auth = new Auth();
$database = new Database();
$conn = $database->connect();

// User permissions check
$auth->requireLogin();
$user_role = getUserRole();
if ($user_role !== 'super_admin') {
    header('Location: projects.php?error=access_denied');
    exit;
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id === 0) {
    setErrorMessage("User ID not provided.");
    header('Location: users.php');
    exit;
}

// Fetch user data
$user_data = null;
try {
    $stmt = $conn->prepare("SELECT id, full_name, email, role, partner_id, is_active FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        setErrorMessage("User not found.");
        header('Location: users.php');
        exit;
    }
} catch (PDOException $e) {
    setErrorMessage("Error fetching user data: " . $e->getMessage());
    header('Location: users.php');
    exit;
}

// Handle form submission for updating user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $partner_id = !empty($_POST['partner_id']) ? (int)$_POST['partner_id'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password']; // New password, if provided

    // Basic validation
    $errors = [];
    if (empty($full_name)) $errors[] = "Full name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
    if (empty($role)) $errors[] = "Role is required.";
    if ($role !== 'super_admin' && empty($partner_id)) $errors[] = "An organization must be selected for this user role.";

    if (empty($errors)) {
        try {
            $sql = "UPDATE users SET full_name = ?, email = ?, role = ?, partner_id = ?, is_active = ?";
            $params = [$full_name, $email, $role, $partner_id, $is_active];

            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $sql .= ", password = ?";
                $params[] = $password_hash;
            }
            $sql .= " WHERE id = ?";
            $params[] = $user_id;

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            setSuccessMessage("User updated successfully!");
            header("Location: users.php"); // Redirect to user list
            exit;

        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // Duplicate entry for email/username
                setErrorMessage("A user with this email or username already exists.");
            } else {
                setErrorMessage("Error updating user: " . $e->getMessage());
            }
        }
    } else {
        setErrorMessage(implode("<br>", $errors));
    }
}

// Fetch all partner organizations for the dropdown
$partners_stmt = $conn->query("SELECT id, name FROM partners ORDER BY name");
$organizations = $partners_stmt->fetchAll(PDO::FETCH_ASSOC);

// Available roles (same as users.php)
$available_roles = ['coordinator' => 'Coordinator', 'partner' => 'Partner'];
if ($user_role === 'super_admin') {
    $available_roles['super_admin'] = 'Super Admin';
}

?>
<body class="">
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <?php include '../includes/navbar.php'; ?>

        <div class="content">
            <?php displayAlert(); ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Edit User: <?= htmlspecialchars($user_data['full_name']) ?></h5>
                            <p class="card-category">Modify user details and credentials.</p>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="edit-user.php?id=<?= $user_id ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Full Name</label>
                                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user_data['full_name']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Email Address</label>
                                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user_data['email']) ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>New Password (leave blank to keep current)</label>
                                            <input type="password" name="password" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Role</label>
                                            <select name="role" class="form-control" required>
                                                <?php foreach ($available_roles as $role_val => $role_name): ?>
                                                    <option value="<?= $role_val ?>" <?= ($user_data['role'] === $role_val) ? 'selected' : '' ?>><?= $role_name ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Organization</label>
                                            <select name="partner_id" class="form-control">
                                                <option value="">None (for Super Admins)</option>
                                                <?php foreach ($organizations as $org): ?>
                                                    <option value="<?= $org['id'] ?>" <?= ($user_data['partner_id'] === $org['id']) ? 'selected' : '' ?>><?= htmlspecialchars($org['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= $user_data['is_active'] ? 'checked' : '' ?>>
                                                <span class="form-check-sign"></span>
                                                User is Active
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 text-right">
                                        <button type="submit" class="btn btn-primary"><i class="nc-icon nc-check-2"></i> Update User</button>
                                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../includes/footer.php'; ?>
    </div>
</body>
</html>