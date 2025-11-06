<?php
/**
 * Upload Project Files - Caricamento File di Progetto
 *
 * Pagina per caricare nuovi file generici per un progetto.
 * 
 * @version 2.0 - Aggiunta preview file selezionati
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
require_once '../includes/classes/FileUploadHandler.php';
$fileHandler = new FileUploadHandler($conn);

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
    
    $result = $fileHandler->handleGenericFiles(
        $_FILES['project_files'],
        $category,
        $project_id,
        $user_id,
        $wp_number,
        $activity_id
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
                                    <div class="alert alert-success alert-dismissible fade show">
                                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                                        <span><?php echo htmlspecialchars($upload_success); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($upload_error): ?>
                                    <div class="alert alert-danger alert-dismissible fade show">
                                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                                        <span><?php echo htmlspecialchars($upload_error); ?></span>
                                    </div>
                                <?php endif; ?>

                                <form action="project-files-upload.php?project_id=<?php echo $project_id; ?>" method="POST" enctype="multipart/form-data" id="uploadForm">
                                    
                                    <!-- File Input -->
                                    <div class="form-group">
                                        <label><button>Select Files (Max 10MB each)</button></label>
                                        <input type="file" name="project_files[]" class="form-control-file" id="fileInput" multiple required>
                                        <small class="form-text text-muted">You can select multiple files. Allowed types: pdf, doc, docx, xlsx, xls, ppt, pptx, txt, jpg, jpeg, png, gif.</small>
                                    </div>

                                    <!-- Preview File Selezionati -->
                                    <div id="filePreview" style="display: none;" class="mb-3">
                                        <h5><i class="nc-icon nc-single-copy-04"></i> Selected Files:</h5>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead class="text-primary">
                                                    <tr>
                                                        <th width="50%">Filename</th>
                                                        <th width="15%">Size</th>
                                                        <th width="20%">Type</th>
                                                        <th width="15%" class="text-center">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="fileList">
                                                </tbody>
                                            </table>
                                        </div>
                                        <p class="text-muted"><small><strong>Total files:</strong> <span id="fileCount">0</span></small></p>
                                    </div>

                                    <!-- File Category -->
                                    <div class="form-group">
                                        <label for="file_category">File Category</label>
                                        <select class="form-control" id="file_category" name="file_category" required>
                                            <option value="deliverable">Deliverable</option>
                                            <option value="document">Document</option>
                                            <option value="presentation">Presentation</option>
                                            <option value="template">Template</option>
                                            <option value="various">Various</option>
                                        </select>
                                    </div>

                                    <!-- Campi WP e Activity -->
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

                                    <button type="submit" class="btn btn-primary" id="submitBtn">
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

<!-- Script per gestione file preview -->
<script>
$(document).ready(function() {
    let selectedFiles = [];
    const maxFileSize = 10 * 1024 * 1024; // 10MB
    const allowedExtensions = ['pdf', 'doc', 'docx', 'xlsx', 'xls', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
    
    // Gestione selezione file
    $('#fileInput').on('change', function(e) {
        const files = Array.from(e.target.files);
        selectedFiles = files;
        updateFilePreview();
    });
    
    // Aggiorna preview file
    function updateFilePreview() {
        const $fileList = $('#fileList');
        const $filePreview = $('#filePreview');
        
        $fileList.empty();
        
        if (selectedFiles.length === 0) {
            $filePreview.hide();
            return;
        }
        
        $filePreview.show();
        $('#fileCount').text(selectedFiles.length);
        
        selectedFiles.forEach((file, index) => {
            const fileName = file.name;
            const fileSize = formatFileSize(file.size);
            const fileExt = fileName.split('.').pop().toLowerCase();
            const fileType = getFileIcon(fileExt);
            
            // Verifica estensione
            const isValidExt = allowedExtensions.includes(fileExt);
            const isValidSize = file.size <= maxFileSize;
            
            let rowClass = '';
            let statusIcon = '';
            
            if (!isValidExt) {
                rowClass = 'table-danger';
                statusIcon = '<span class="text-danger" title="Invalid file type"><i class="nc-icon nc-simple-remove"></i></span>';
            } else if (!isValidSize) {
                rowClass = 'table-danger';
                statusIcon = '<span class="text-danger" title="File too large (max 10MB)"><i class="nc-icon nc-simple-remove"></i></span>';
            } else {
                rowClass = 'table-success';
                statusIcon = '<span class="text-success" title="Valid file"><i class="nc-icon nc-check-2"></i></span>';
            }
            
            const row = `
                <tr class="${rowClass}">
                    <td>
                        <i class="nc-icon ${fileType}"></i>
                        <strong>${escapeHtml(fileName)}</strong>
                        ${!isValidExt ? '<br><small class="text-danger">Invalid file type</small>' : ''}
                        ${!isValidSize ? '<br><small class="text-danger">File too large</small>' : ''}
                    </td>
                    <td>${fileSize}</td>
                    <td><span class="badge badge-secondary">${fileExt.toUpperCase()}</span></td>
                    <td class="text-center">
                        ${statusIcon}
                    </td>
                </tr>
            `;
            
            $fileList.append(row);
        });
        
        // Disabilita submit se ci sono file invalidi
        const hasInvalidFiles = selectedFiles.some(file => {
            const fileExt = file.name.split('.').pop().toLowerCase();
            return !allowedExtensions.includes(fileExt) || file.size > maxFileSize;
        });
        
        $('#submitBtn').prop('disabled', hasInvalidFiles);
        
        if (hasInvalidFiles) {
            $('#submitBtn').html('<i class="nc-icon nc-simple-remove"></i> Cannot Upload (Invalid Files)');
        } else {
            $('#submitBtn').html('<i class="nc-icon nc-check-2"></i> Upload Files');
        }
    }
    
    // Formatta dimensione file
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
    
    // Ottieni icona per tipo file
    function getFileIcon(ext) {
        const iconMap = {
            'pdf': 'nc-single-copy-04',
            'doc': 'nc-paper',
            'docx': 'nc-paper',
            'xls': 'nc-chart-bar-32',
            'xlsx': 'nc-chart-bar-32',
            'ppt': 'nc-tv-2',
            'pptx': 'nc-tv-2',
            'txt': 'nc-paper',
            'jpg': 'nc-image',
            'jpeg': 'nc-image',
            'png': 'nc-image',
            'gif': 'nc-image'
        };
        return iconMap[ext] || 'nc-single-copy-04';
    }
    
    // Escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
    
    // Gestione categoria deliverable
    $('#file_category').on('change', function() {
        if ($(this).val() === 'deliverable') {
            $('#deliverable_fields').slideDown();
        } else {
            $('#deliverable_fields').slideUp();
        }
    });
    
    // Filtro attività per WP
    $('#work_package_id').on('change', function() {
        const wpId = $(this).val();
        $('#activity_id option').each(function() {
            const actWpId = $(this).data('wp-id');
            if (!wpId || actWpId == wpId) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        $('#activity_id').val('');
    });
});
</script>

</body>
</html>