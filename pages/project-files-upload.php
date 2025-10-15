<?php
/**
 * Upload Project Files - Caricamento File di Progetto
 *
 * Pagina per caricare nuovi file generici per un progetto.
 */

// Page configuration
$page_title = 'Upload Project Files - EU Project Manager';
$page_css_path = '../assets/css/pages/project-files-upload.css';
$page_js_path = '../assets/js/pages/project-files-upload.js';

// Include header
require_once '../includes/header.php';

// Auth and DB
$database = new Database();
$conn = $database->connect();
$auth = new Auth($conn);

// Verifica autenticazione
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$user_id = getUserId();
$user_role = getUserRole();

// Recupera project_id dalla query string
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

if (!$project_id) {
    header('Location: projects.php');
    exit;
}

// Recupera informazioni progetto
$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header('Location: projects.php');
    exit;
}

// Verifica permessi (simile a project-files.php)
if ($user_role === 'partner') {
    $stmt = $conn->prepare("SELECT 1 FROM project_partners WHERE project_id = ? AND partner_id = (SELECT partner_id FROM users WHERE id = ?)");
    $stmt->execute([$project_id, $user_id]);
    if (!$stmt->fetch()) {
        header('Location: projects.php');
        exit;
    }
}

// Recupera Work Packages e Attività per i menu a tendina
$stmt_wp = $conn->prepare("SELECT id, wp_number, name FROM work_packages WHERE project_id = ? ORDER BY wp_number");
$stmt_wp->execute([$project_id]);
$work_packages = $stmt_wp->fetchAll(PDO::FETCH_ASSOC);

$stmt_act = $conn->prepare("SELECT id, activity_number, name, work_package_id FROM activities WHERE work_package_id IN (SELECT id FROM work_packages WHERE project_id = ?) ORDER BY activity_number");
$stmt_act->execute([$project_id]);
$activities = $stmt_act->fetchAll(PDO::FETCH_ASSOC);


// Gestione del form di upload
$upload_success = null;
$upload_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['project_files'])) {
    require_once '../includes/classes/FileUploadHandler.php';
    
    $file_uploader = new FileUploadHandler($conn);
    
    $category = $_POST['file_category'] ?? 'various';
    $wp_id = $_POST['work_package_id'] ?? null;
    $activity_id = $_POST['activity_id'] ?? null;
    
    $wp_number = null;
    if ($wp_id) {
        $stmt = $conn->prepare("SELECT wp_number FROM work_packages WHERE id = ?");
        $stmt->execute([$wp_id]);
        $wp_number = $stmt->fetchColumn();
    }

    $result = $file_uploader->handleGenericFiles(
        $_FILES['project_files'],
        $category,
        $project_id,
        $wp_number,
        $activity_id,
        $user_id
    );
    
    if ($result['success']) {
        $upload_success = $result['message'];
    } else {
        $upload_error = $result['message'];
    }
}

// Include header
include '../includes/header.php';
?>

  <!-- SIDEBAR & NAV -->
<body class="">
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <?php include '../includes/navbar.php'; ?>

            <!-- CONTENT -->
            <div class="content">
                <!-- Header con breadcrumb -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="projects.php">Projects</a></li>
                                <li class="breadcrumb-item"><a href="project-detail.php?id=<?php echo $project_id; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
                                <li class="breadcrumb-item"><a href="project-files.php?project_id=<?php echo $project_id; ?>">Files</a></li>
                                <li class="breadcrumb-item active">Upload Files</li>
                            </ol>
                        </nav>
                    </div>
                </div>

                <!-- Card di Upload -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><i class="nc-icon nc-cloud-upload-94"></i> Upload New Project Files</h4>
                                <p class="card-category">Upload documents, deliverables, or other files for "<?php echo htmlspecialchars($project['name']); ?>"</p>
                            </div>
                            <div class="card-body">
                                <?php if ($upload_success): ?>
                                    <div class="alert alert-success">
                                        <span><?php echo htmlspecialchars($upload_success); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($upload_error): ?>
                                    <div class="alert alert-danger">
                                        <span><?php echo htmlspecialchars($upload_error); ?></span>
                                    </div>
                                <?php endif; ?>

                                <form action="project-files-upload.php?project_id=<?php echo $project_id; ?>" method="POST" enctype="multipart/form-data" id="uploadForm">
                                    
                                    <!-- File Input -->
                                    <div class="form-group">
                                        <label>Select Files (Max 10MB each)</label>
                                        <input type="file" name="project_files[]" class="form-control-file" multiple required>
                                        <small class="form-text text-muted">You can select multiple files. Allowed types: pdf, doc, docx, xlsx, xls, ppt, pptx, txt, jpg, jpeg, png, gif.</small>
                                    </div>

                                    <!-- File Category -->
                                    <div class="form-group">
                                        <label for="file_category">File Category</label>
                                        <select class="form-control" id="file_category" name="file_category" required>
                                            <option value="document">Document</option>
                                            <option value="deliverable">Deliverable</option>
                                            <option value="various">Various</option>
                                        </select>
                                    </div>

                                    <!-- Campi condizionali per Deliverable -->
                                    <div id="deliverable_fields" style="display: none;">
                                        <div class="form-group">
                                            <label for="work_package_id">Work Package</label>
                                            <select class="form-control" id="work_package_id" name="work_package_id">
                                                <option value="">Select a Work Package</option>
                                                <?php foreach ($work_packages as $wp): ?>
                                                    <option value="<?php echo $wp['id']; ?>"><?php echo htmlspecialchars($wp['wp_number'] . ' - ' . $wp['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="activity_id">Activity</label>
                                            <select class="form-control" id="activity_id" name="activity_id">
                                                <option value="">Select an Activity</option>
                                                <?php foreach ($activities as $act): ?>
                                                    <option value="<?php echo $act['id']; ?>" data-wp-id="<?php echo $act['work_package_id']; ?>"><?php echo htmlspecialchars($act['activity_number'] . ' - ' . $act['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <hr>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="nc-icon nc-check-2"></i> Upload Files
                                    </button>
                                    <a href="project-files.php?project_id=<?php echo $project_id; ?>" class="btn btn-secondary">
                                        <i class="nc-icon nc-simple-remove"></i> Cancel
                                    </a>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
</body>
</html>
