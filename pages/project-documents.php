<?php
// ===================================================================
//  PROJECT DOCUMENTS PAGE
// ===================================================================

$page_title = 'Project Documents - EU Project Manager';
$page_css_path = '../assets/css/pages/project-documents.css';
$page_js_path = '../assets/js/pages/project-documents.js';
require_once '../includes/header.php';

// ===================================================================
//  AUTHENTICATION & DATA RETRIEVAL
// ===================================================================
$auth->requireLogin();
$user_id = getUserId();

// Get project ID from URL
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$project_id) {
    Flash::set('error', 'Project ID is missing.');
    header('Location: projects.php');
    exit;
}

// Fetch project details
$project_stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$project_stmt->execute([$project_id]);
$project = $project_stmt->fetch();

if (!$project) {
    Flash::set('error', 'Project not found.');
    header('Location: projects.php');
    exit;
}

// Fetch all files for this project
$files_stmt = $conn->prepare("
    SELECT uf.*, u.full_name as uploader_name 
    FROM uploaded_files uf
    JOIN users u ON uf.uploaded_by = u.id
    WHERE uf.project_id = ? 
    ORDER BY uf.report_id, uf.uploaded_at DESC
");
$files_stmt->execute([$project_id]);
$all_files = $files_stmt->fetchAll();

// Categorize files
$files_by_category = [
    'general' => [
        'title' => 'General Documents',
        'files' => []
    ]
];

foreach ($all_files as $file) {
    if (is_null($file['report_id'])) {
        $files_by_category['general']['files'][] = $file;
    } else {
        $category_key = 'report_' . $file['report_id'];
        if (!isset($files_by_category[$category_key])) {
            $files_by_category[$category_key] = [
                'title' => 'Attachments for Report #' . $file['report_id'],
                'files' => []
            ];
        }
        $files_by_category[$category_key]['files'][] = $file;
    }
}

// ===================================================================
//  START HTML LAYOUT
// ===================================================================
?>

<div class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="nc-icon nc-folder-15"></i> Documents for: <?= htmlspecialchars($project['name']) ?>
                    </h4>
                    <a href="project-detail.php?id=<?= $project_id ?>" class="btn btn-sm btn-outline-primary"><i class="nc-icon nc-minimal-left"></i> Back to Project</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Section -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="nc-icon nc-cloud-upload-94"></i> Upload New Document</h5>
                    <p class="category">Files uploaded here will be added to the "General Documents" category.</p>
                </div>
                <div class="card-body">
                    <form id="upload-form" action="upload-project-files.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="project_id" value="<?= $project_id ?>">
                        <input type="hidden" name="action" value="upload_files">
                        
                        <div class="form-group">
                            <label>Select files to upload (Max 10MB each)</label>
                            <input type="file" name="files[]" id="file-input" class="form-control" multiple>
                        </div>

                        <div id="upload-feedback" class="mt-3"></div>

                        <div class="text-right">
                            <button type="submit" class="btn btn-primary"><i class="nc-icon nc-check-2"></i> Upload Files</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- File Display Section -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="nc-icon nc-bullet-list-67"></i> All Uploaded Files</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($all_files)): ?>
                        <div class="alert alert-info">No documents have been uploaded for this project yet.</div>
                    <?php else: ?>
                        <?php foreach ($files_by_category as $category): ?>
                            <?php if (!empty($category['files'])): ?>
                                <div class="category-section">
                                    <h6 class="category-title"><?= htmlspecialchars($category['title']) ?></h6>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="text-primary">
                                                <tr>
                                                    <th>File Name / Title</th>
                                                    <th>Uploader</th>
                                                    <th>Source</th>
                                                    <th>Size</th>
                                                    <th>Date Uploaded</th>
                                                    <th class="text-right">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($category['files'] as $file): ?>
                                                    <tr>
                                                        <td>
                                                            <i class="nc-icon nc-paper"></i>
                                                            <strong><?= htmlspecialchars($file['title'] ?? $file['original_filename']) ?></strong>
                                                            <?php if (($file['title'] ?? '') !== $file['original_filename']): ?>
                                                                <br><small class="text-muted"><?= htmlspecialchars($file['original_filename']) ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($file['uploader_name']) ?></td>
                                                        <td><?= is_null($file['report_id']) ? '<span class="badge badge-primary">General</span>' : '<span class="badge badge-info">Report</span>' ?></td>
                                                        <td><?= round($file['file_size'] / 1024, 2) ?> KB</td>
                                                        <td><?= date('Y-m-d H:i', strtotime($file['uploaded_at'])) ?></td>
                                                        <td class="text-right">
                                                            <a href="<?= htmlspecialchars($file['file_path']) ?>" class="btn btn-sm btn-info" download>
                                                                <i class="nc-icon nc-cloud-download-93"></i> Download
                                                            </a>
                                                            <button class="btn btn-sm btn-danger btn-delete-file" data-file-id="<?= $file['id'] ?>">
                                                                <i class="nc-icon nc-simple-remove"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
