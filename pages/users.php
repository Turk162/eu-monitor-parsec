<?php
// Page configuration
$page_title = 'User Management - EU Project Manager';

// Include header
require_once '../includes/header.php';

// Auth and DB
$database = new Database();
$conn = $database->connect();
$auth = new Auth($conn);

// User permissions check
$auth->requireLogin();
$user_role = getUserRole();
if (!in_array($user_role, ['super_admin', 'coordinator'])) {
    header('Location: projects.php?error=access_denied');
    exit;
}

// Handle form submission for creating a new user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $partner_id = !empty($_POST['partner_id']) ? (int)$_POST['partner_id'] : null;

    // Basic validation
    $errors = [];
    if (empty($full_name)) $errors[] = "Full name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
    if (empty($password)) $errors[] = "Password is required.";
    if (empty($role)) $errors[] = "Role is required.";
    if ($role !== 'super_admin' && empty($partner_id)) $errors[] = "An organization must be selected for this user role.";

    if (empty($errors)) {
        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (full_name, email, username, password, role, partner_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$full_name, $email, $email, $password_hash, $role, $partner_id]);

            setSuccessMessage("User created successfully!");
            header("Location: users.php"); // Redirect to refresh
            exit;

        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // Duplicate entry for email/username
                setErrorMessage("A user with this email or username already exists.");
            } else {
                setErrorMessage("Error creating user: " . $e->getMessage());
            }
        }
    } else {
        setErrorMessage(implode("<br>", $errors));
    }
}

// Fetch existing users and their organizations
$users_stmt = $conn->prepare("
    SELECT u.id, u.full_name, u.email, u.role, u.is_active, p.name as organization_name
    FROM users u
    LEFT JOIN partners p ON u.partner_id = p.id
    ORDER BY u.full_name
");
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all partner organizations for the dropdown
$partners_stmt = $conn->query("SELECT id, name FROM partners ORDER BY name");
$organizations = $partners_stmt->fetchAll(PDO::FETCH_ASSOC);

// Available roles
$available_roles = ['coordinator' => 'Coordinator', 'partner' => 'Partner'];
if ($user_role === 'super_admin') {
    $available_roles['super_admin'] = 'Super Admin';
}

?>
   <!-- SIDEBAR & NAV -->
<body class="">
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <?php include '../includes/navbar.php'; ?>

            <!-- CONTENT -->
      <div class="content">
        <?php displayAlert(); ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Create New User</h5>
                        <p class="card-category">Add a new user to the system and assign them to an organization.</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="users.php">
                            <input type="hidden" name="action" value="create_user">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Full Name</label>
                                        <input type="text" name="full_name" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Email Address</label>
                                        <input type="email" name="email" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Password</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Role</label>
                                        <select name="role" class="form-control" required>
                                            <option value="">Select role...</option>
                                            <?php foreach ($available_roles as $role_val => $role_name): ?>
                                                <option value="<?= $role_val ?>"><?= $role_name ?></option>
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
                                                <option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 text-right">
                                    <button type="submit" class="btn btn-primary"><i class="nc-icon nc-simple-add"></i> Create User</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Registered Users</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="text-primary">
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Organization</th>
                                    <th>Status</th>
                                    <?php if ($user_role === 'super_admin'): ?>
                                    <th>Actions</th>
                                    <?php endif; ?>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td><?= getRoleDisplayName($user['role']) ?></td>
                                            <td><?= htmlspecialchars($user['organization_name'] ?? 'N/A') ?></td>
                                            <td><?= $user['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
                                            <?php if ($user_role === 'super_admin'): ?>
                                            <td>
                                                <a href="edit-user.php?id=<?= $user['id'] ?>" class="btn btn-info btn-sm">Edit</a>
                                                <a href="delete-user.php?id=<?= $user['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</div>