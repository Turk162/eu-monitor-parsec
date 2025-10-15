<?php
/**
 * Project Files - Gestione File Progetto
 * 
 * Pagina per visualizzare e gestire tutti i file caricati per un progetto
 * Accessibile da project details
 */

// Page configuration
$page_title = 'Project Files - EU Project Manager';
$page_css_path = '../assets/css/pages/project-files.css';
$page_js_path = '../assets/js/pages/project-files.js';

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
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : (isset($_GET['project']) ? (int)$_GET['project'] : 0);

if (!$project_id) {
    header('Location: projects.php');
    exit;
}

// Recupera informazioni progetto
$stmt = $conn->prepare("
    SELECT p.*, 
           u.full_name as coordinator_name
    FROM projects p
    LEFT JOIN users u ON p.coordinator_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header('Location: projects.php');
    exit;
}

// Verifica permessi
// Partner può vedere solo i propri progetti
if ($user_role === 'partner') {
    $stmt = $conn->prepare("
        SELECT 1 FROM project_partners 
        WHERE project_id = ? AND partner_id = (SELECT partner_id FROM users WHERE id = ?)
    ");
    $stmt->execute([$project_id, $user_id]);
    if (!$stmt->fetch()) {
        header('Location: projects.php');
        exit;
    }
}

// Recupera tutti i file del progetto
$stmt = $conn->prepare("
    SELECT 
        uf.id,
        uf.filename,
        uf.original_filename,
        uf.title,
        uf.file_path,
        uf.file_category,
        uf.file_size,
        uf.file_type,
        uf.uploaded_at,
        uf.report_id,
        p.name as partner_name,
        ar.activity_id,
        a.activity_number,
        wp.wp_number
    FROM uploaded_files uf
    LEFT JOIN users u ON uf.uploaded_by = u.id
    LEFT JOIN partners p ON u.partner_id = p.id
    LEFT JOIN activity_reports ar ON uf.report_id = ar.id
    LEFT JOIN activities a ON ar.activity_id = a.id
    LEFT JOIN work_packages wp ON a.work_package_id = wp.id
    WHERE uf.project_id = ?
    ORDER BY uf.uploaded_at DESC
");
$stmt->execute([$project_id]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiche
$total_files = count($files);
$total_size = array_sum(array_column($files, 'file_size'));

// Raggruppa per categoria
$files_by_category = [];
foreach ($files as $file) {
    $category = $file['file_category'] ?: 'other';
    if (!isset($files_by_category[$category])) {
        $files_by_category[$category] = 0;
    }
    $files_by_category[$category]++;
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
                                <li class="breadcrumb-item active">Files</li>
                            </ol>
                        </nav>
                    </div>
                </div>

                <!-- Header pagina -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h4 class="card-title mb-0">
                                            <i class="nc-icon nc-folder-17"></i>
                                            Project Files - <?php echo htmlspecialchars($project['name']); ?>
                                        </h4>
                                    </div>
                                    <div class="col-md-4 text-right">
                                        <button class="btn btn-primary btn-sm" id="addNewFileBtn">
                                            <i class="nc-icon nc-simple-add"></i> Add New File
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistiche -->
                <div class="row">
                    <div class="col-lg-3 col-md-6">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-5 col-md-4">
                                        <div class="icon-big text-center icon-warning">
                                            <i class="nc-icon nc-single-copy-04 text-primary"></i>
                                        </div>
                                    </div>
                                    <div class="col-7 col-md-8">
                                        <div class="numbers">
                                            <p class="card-category">Total Files</p>
                                            <p class="card-title"><?php echo $total_files; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-5 col-md-4">
                                        <div class="icon-big text-center icon-warning">
                                            <i class="nc-icon nc-cloud-upload-94 text-success"></i>
                                        </div>
                                    </div>
                                    <div class="col-7 col-md-8">
                                        <div class="numbers">
                                            <p class="card-category">Total Size</p>
                                            <p class="card-title"><?php echo number_format($total_size / 1048576, 2); ?> MB</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-5 col-md-4">
                                        <div class="icon-big text-center icon-warning">
                                            <i class="nc-icon nc-paper text-info"></i>
                                        </div>
                                    </div>
                                    <div class="col-7 col-md-8">
                                        <div class="numbers">
                                            <p class="card-category">Reports</p>
                                            <p class="card-title"><?php echo $files_by_category['report'] ?? 0; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-5 col-md-4">
                                        <div class="icon-big text-center icon-warning">
                                            <i class="nc-icon nc-folder-17 text-warning"></i>
                                        </div>
                                    </div>
                                    <div class="col-7 col-md-8">
                                        <div class="numbers">
                                            <p class="card-category">Documents</p>
                                            <p class="card-title"><?php echo $files_by_category['document'] ?? 0; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtri e Search -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Search Files</label>
                                            <input type="text" class="form-control" id="searchInput" placeholder="Search by filename, category, WP...">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Filter by Category</label>
                                            <select class="form-control" id="categoryFilter">
                                                <option value="">All Categories</option>
                                                <option value="report">Reports</option>
                                                <option value="document">Documents</option>
                                                <option value="deliverable">Deliverables</option>
                                                <option value="admin_report">Admin Reports</option>
                                                <option value="various">Various</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Filter by WP</label>
                                            <select class="form-control" id="wpFilter">
                                                <option value="">All Work Packages</option>
                                                <?php
                                                // Recupera WP unici
                                                $unique_wps = array_unique(array_filter(array_column($files, 'wp_number')));
                                                sort($unique_wps);
                                                foreach ($unique_wps as $wp) {
                                                    echo "<option value='" . htmlspecialchars($wp) . "'>" . htmlspecialchars($wp) . "</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabella Files -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Files List</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="filesTable">
                                        <thead class="text-primary">
                                            <tr>
                                                <th>Filename</th>
                                                <th>Category</th>
                                                <th>WP</th>
                                                <th>Activity</th>
                                                <th>Size</th>
                                                <th>Uploaded By</th>
                                                <th>Date</th>
                                                <th class="text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($files)): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center">
                                                        <p class="text-muted">No files uploaded yet</p>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($files as $file): ?>
                                                    <tr class="file-row" 
                                                        data-filename="<?php echo htmlspecialchars($file['original_filename']); ?>"
                                                        data-category="<?php echo htmlspecialchars($file['file_category']); ?>"
                                                        data-wp="<?php echo htmlspecialchars($file['wp_number'] ?? ''); ?>">
                                                        <td>
                                                            <i class="nc-icon nc-single-copy-04"></i>
                                                            <strong><?php echo htmlspecialchars($file['original_filename']); ?></strong>
                                                            <?php if ($file['title']): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($file['title']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $category_badges = [
                                                                'report' => 'badge-primary',
                                                                'document' => 'badge-info',
                                                                'deliverable' => 'badge-success',
                                                                'admin_report' => 'badge-warning',
                                                                'various' => 'badge-secondary'
                                                            ];
                                                            $badge_class = $category_badges[$file['file_category']] ?? 'badge-default';
                                                            ?>
                                                            <span class="badge <?php echo $badge_class; ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $file['file_category'])); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $file['wp_number'] ? htmlspecialchars($file['wp_number']) : '-'; ?></td>
                                                        <td><?php echo $file['activity_number'] ? htmlspecialchars($file['activity_number']) : '-'; ?></td>
                                                        <td><?php echo number_format($file['file_size'] / 1024, 2); ?> KB</td>
                                                        <td><?php echo htmlspecialchars($file['partner_name'] ?? 'N/A'); ?></td>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($file['uploaded_at'])); ?></td>
                                                        <td class="text-right">
                                                            <a href="../<?php echo htmlspecialchars($file['file_path']); ?>" 
                                                               class="btn btn-sm btn-info" 
                                                               download
                                                               title="Download">
                                                                <i class="nc-icon nc-cloud-download-93"></i>
                                                            </a>
                                                            <?php if ($file['report_id']): ?>
                                                                <a href="reports.php?id=<?php echo $file['report_id']; ?>" 
                                                                   class="btn btn-sm btn-default" 
                                                                   title="View Report">
                                                                    <i class="nc-icon nc-paper"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
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
</div>

<!-- Hidden data for JS -->
<script>
    const projectId = <?php echo $project_id; ?>;
</script>

</body>
</html>