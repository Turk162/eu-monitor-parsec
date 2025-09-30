<?php
// Page configuration
$page_title = 'Partner Organizations - EU Project Manager';

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

// Handle form submission for creating a new partner organization
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_organization') {
    $name = trim($_POST['name']);
    $organization_type = trim($_POST['organization_type']);
    $country = trim($_POST['country']);

    // Basic validation
    $errors = [];
    if (empty($name)) $errors[] = "Organization name is required.";
    if (empty($organization_type)) $errors[] = "Organization type is required.";
    if (empty($country)) $errors[] = "Country is required.";

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO partners (name, organization_type, country) VALUES (?, ?, ?)");
            $stmt->execute([$name, $organization_type, $country]);
            setSuccessMessage("Partner organization created successfully!");
            header("Location: partners.php"); // Redirect to refresh
            exit;
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // Duplicate entry
                setErrorMessage("An organization with this name already exists.");
            } else {
                setErrorMessage("Error creating organization: " . $e->getMessage());
            }
        }
    } else {
        setErrorMessage(implode("<br>", $errors));
    }
}

// Fetch existing partner organizations from the database
$partners_stmt = $conn->prepare("SELECT id, name, organization_type, country, created_at FROM partners ORDER BY name");
$partners_stmt->execute();
$partners = $partners_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organization types for the dropdown
$org_types = ['University', 'Research Center', 'SME', 'Large Enterprise', 'NGO', 'Public Body', 'Other'];

?>

   <!-- SIDEBAR & NAV -->
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <?php include '../includes/navbar.php'; ?>

            <!-- CONTENT -->
            <div class="content">
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Create New Partner Organization</h5>
                <p class="card-category">Add a new partner organization to the system.</p>
            </div>
            <div class="card-body">
                <form method="POST" action="partners.php">
                    <input type="hidden" name="action" value="create_organization">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>Organization Name</label>
                                <input type="text" name="name" class="form-control" placeholder="e.g., Innovate Europe" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Organization Type</label>
                                <select name="organization_type" class="form-control" required>
                                    <option value="">Select type...</option>
                                    <?php foreach ($org_types as $type): ?>
                                        <option value="<?= $type ?>"><?= $type ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Country</label>
                                <input type="text" name="country" class="form-control" placeholder="e.g., Italy" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 text-right">
                            <button type="submit" class="btn btn-primary"><i class="nc-icon nc-simple-add"></i> Create Organization</button>
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
                <h5 class="card-title">Registered Partner Organizations</h5>
                <p class="card-category">List of all partner organizations.</p>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead class="text-primary">
                            <th>Name</th>
                            <th>Type</th>
                            <th>Country</th>
                            <th class="text-right">Registered On</th>
                        </thead>
                        <tbody>
                            <?php if (empty($partners)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No organizations found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($partners as $partner): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($partner['name']) ?></td>
                                        <td><?= htmlspecialchars($partner['organization_type']) ?></td>
                                        <td><?= htmlspecialchars($partner['country']) ?></td>
                                        <td class="text-right"><?= formatDate($partner['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div></div></div>

<?php
// Include footer
include '../includes/footer.php';
?>
