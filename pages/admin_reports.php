<?php
$page_title = 'Administrative Reports - EU Project Manager';
$page_css_path = '../assets/css/pages/admin-reports.css';
$page_js_path = '../assets/js/pages/admin-reports.js';
require_once __DIR__ . '/../includes/header.php';

// ===================================================================
// GET PROJECT AND PARTNER IDS
// ===================================================================

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$partner_id = isset($_GET['partner_id']) ? (int)$_GET['partner_id'] : 0;

if (!$project_id || !$partner_id) {
    $_SESSION['error'] = 'Project or partner not specified.';
    header('Location: projects.php');
    exit;
}

// ===================================================================
// DATABASE CONNECTION
// ===================================================================

$database = new Database();
$conn = $database->connect();

// ===================================================================
// FETCH PROJECT, PARTNER, AND WORK PACKAGES
// ===================================================================

$project_stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$project_stmt->execute([$project_id]);
$project = $project_stmt->fetch();

$partner_stmt = $conn->prepare("SELECT * FROM partners WHERE id = ?");
$partner_stmt->execute([$partner_id]);
$partner = $partner_stmt->fetch();

// Query corretta con JOIN per ottenere il nome del lead partner
$wp_stmt = $conn->prepare("
    SELECT wp.*, p.name as lead_partner_name 
    FROM work_packages wp
    LEFT JOIN partners p ON wp.lead_partner_id = p.id
    WHERE wp.project_id = ? 
    ORDER BY wp.wp_number ASC
");
$wp_stmt->execute([$project_id]);
$work_packages = $wp_stmt->fetchAll();

if (!$project || !$partner) {
    $_SESSION['error'] = 'Project or partner not found.';
    header('Location: projects.php');
    exit;
}
// Recupera tutte le mobilità per questo progetto, organizzate per work package
$mobilities_stmt = $conn->prepare("
    SELECT * FROM mobilities 
    WHERE project_id = ? 
    ORDER BY work_package_id, name
");
$mobilities_stmt->execute([$project_id]);
$all_mobilities = $mobilities_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizza le mobilità per work package ID
$mobilities_by_wp = [];
foreach ($all_mobilities as $mobility) {
    $mobilities_by_wp[$mobility['work_package_id']][] = $mobility;
}
// ===================================================================
// FORM SUBMISSION HANDLING
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ===================================================================
// GESTIONE ELIMINAZIONE FILE
// ===================================================================

if (isset($_POST['delete_file_id'])) {
    $file_id = (int)$_POST['delete_file_id'];
    
    // Recupera info sul file
    $file_stmt = $conn->prepare("SELECT * FROM uploaded_files WHERE id = ? AND project_id = ?");
    $file_stmt->execute([$file_id, $project_id]);
    $file_data = $file_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($file_data) {
        // Cancella il file fisico
        $full_file_path = '../' . $file_data['file_path'];
        if (file_exists($full_file_path)) {
            unlink($full_file_path);
        }
        
        // Cancella dal database
        $delete_stmt = $conn->prepare("DELETE FROM uploaded_files WHERE id = ?");
        if ($delete_stmt->execute([$file_id])) {
            $_SESSION['success'] = 'File deleted successfully.';
        } else {
            $_SESSION['error'] = 'Error deleting file from database.';
        }
    } else {
        $_SESSION['error'] = 'File not found.';
    }
    
    // Redirect per evitare re-submit
    header("Location: admin_reports.php?project_id=$project_id&partner_id=$partner_id");
    exit;
}
    try {
        $conn->beginTransaction();
        
        // Ottieni l'ID dell'utente corrente
        $current_user_id = $_SESSION['user_id'] ?? 1;
        
        // Cicla attraverso tutti i work packages
        foreach ($work_packages as $wp) {
            $wp_id = $wp['id'];
            
            // ===================================================================
            // GESTIONE PERSONNEL DATA
            // ===================================================================
            
            // Gestisci i personnel esistenti (con ID numerico)
            if (isset($_POST['personnel_name'][$wp_id]) && is_array($_POST['personnel_name'][$wp_id])) {
                foreach ($_POST['personnel_name'][$wp_id] as $key => $personnel_name) {
                    $personnel_name = trim($personnel_name);
                    if (empty($personnel_name)) continue;
                    
                    $working_days = isset($_POST['working_days'][$wp_id][$key]) ? (float)$_POST['working_days'][$wp_id][$key] : 0;
                    
                    // Se la chiave è numerica E maggiore di 0, è un record esistente da aggiornare
                    if (is_numeric($key) && $key > 0) {
                        $update_stmt = $conn->prepare("
                            UPDATE admin_reports 
                            SET personnel_name = ?, working_days = ? 
                            WHERE id = ? AND project_id = ? AND partner_id = ? AND work_package_id = ?
                        ");
                        $update_stmt->execute([
                            $personnel_name, 
                            $working_days, 
                            $key, 
                            $project_id, 
                            $partner_id, 
                            $wp_id
                        ]);
                        $report_id = $key;
                    } else {
                        // Altrimenti è un nuovo record (key=0,1,2... o stringa)
                        $insert_stmt = $conn->prepare("
                            INSERT INTO admin_reports (project_id, partner_id, work_package_id, personnel_name, working_days) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $insert_stmt->execute([$project_id, $partner_id, $wp_id, $personnel_name, $working_days]);
                        $report_id = $conn->lastInsertId();
                    }
                    
                    // ===================================================================
                    // GESTIONE FILE UPLOADS PER QUESTO PERSONNEL
                    // ===================================================================
                    
                    $file_types = ['letter_of_assignment', 'timesheet', 'invoices'];
                    
                    foreach ($file_types as $file_type) {
                        if (isset($_FILES[$file_type]['name'][$wp_id][$key]) && 
                            !empty($_FILES[$file_type]['name'][$wp_id][$key])) {
                            
                            $file = [
                                'name' => $_FILES[$file_type]['name'][$wp_id][$key],
                                'tmp_name' => $_FILES[$file_type]['tmp_name'][$wp_id][$key],
                                'size' => $_FILES[$file_type]['size'][$wp_id][$key],
                                'type' => $_FILES[$file_type]['type'][$wp_id][$key],
                                'error' => $_FILES[$file_type]['error'][$wp_id][$key]
                            ];
                            
                            if ($file['error'] === UPLOAD_ERR_OK) {
                                // Validazione file
                                $allowed_types = [
                                    'application/pdf', 
                                    'image/jpeg', 
                                    'image/png', 
                                    'image/jpg',
                                    'application/msword', 
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'text/plain',
                                    'text/csv',
                                    'application/vnd.ms-excel',
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                                ];
                                $max_size = 10 * 1024 * 1024; // 10MB
                                
                                if (!in_array($file['type'], $allowed_types)) {
                                    throw new Exception("Tipo di file non consentito per {$file['name']}");
                                }
                                
                                if ($file['size'] > $max_size) {
                                    throw new Exception("File troppo grande: {$file['name']}");
                                }
                                
                                // Crea la directory uploads se non esiste
                                $upload_dir = __DIR__ . '/../uploads/' . $project_id . '/' . $partner_id . '/';
                                if (!is_dir($upload_dir)) {
                                    mkdir($upload_dir, 0755, true);
                                }
                                
                                // Genera nome file unico
                                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                                $unique_filename = uniqid() . '_' . $file_type . '_' . $wp_id . '_' . $key . '.' . $file_extension;
                                $file_path = $upload_dir . $unique_filename;
                                $relative_path = 'uploads/' . $project_id . '/' . $partner_id . '/' . $unique_filename;
                                
                                // Sposta il file
                                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                                    // Salva nel database - per admin_reports usiamo un identificatore nel file_field
                                    $admin_file_field = "admin_report_{$report_id}_{$file_type}";
                                    $file_stmt = $conn->prepare("
                                        INSERT INTO uploaded_files 
                                        (project_id, file_field, filename, original_filename, file_path, file_size, file_type, uploaded_by) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                    ");
                                    $file_stmt->execute([
                                        $project_id,
                                        $admin_file_field,
                                        $unique_filename,
                                        $file['name'],
                                        $relative_path,
                                        $file['size'],
                                        $file['type'],
                                        $current_user_id
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
            
            // ===================================================================
            // GESTIONE MOBILITY DATA (SEMPLIFICATA)
            // ===================================================================
            
            if (isset($_POST['mobility_name'][$wp_id]) && is_array($_POST['mobility_name'][$wp_id])) {
                foreach ($_POST['mobility_name'][$wp_id] as $key => $mobility_name) {
                    $mobility_name = trim($mobility_name);
                    if (empty($mobility_name)) continue;
                    
                    // Salva mobility come admin_report con personnel_name = mobility name e working_days = 0
                    $mobility_stmt = $conn->prepare("
                        INSERT INTO admin_reports (project_id, partner_id, work_package_id, personnel_name, working_days) 
                        VALUES (?, ?, ?, ?, 0)
                    ");
                    $mobility_stmt->execute([$project_id, $partner_id, $wp_id, "MOBILITY: " . $mobility_name]);
                    $mobility_report_id = $conn->lastInsertId();
                    
                    // Gestisci file mobility
                    $mobility_file_types = ['boarding_cards', 'mobility_invoices'];
                    
                    foreach ($mobility_file_types as $file_type) {
                        if (isset($_FILES[$file_type]['name'][$wp_id][$key]) && 
                            !empty($_FILES[$file_type]['name'][$wp_id][$key])) {
                            
                            $file = [
                                'name' => $_FILES[$file_type]['name'][$wp_id][$key],
                                'tmp_name' => $_FILES[$file_type]['tmp_name'][$wp_id][$key],
                                'size' => $_FILES[$file_type]['size'][$wp_id][$key],
                                'type' => $_FILES[$file_type]['type'][$wp_id][$key],
                                'error' => $_FILES[$file_type]['error'][$wp_id][$key]
                            ];
                            
                            if ($file['error'] === UPLOAD_ERR_OK) {
                                // Validazione file
                                $allowed_types = [
                                    'application/pdf', 
                                    'image/jpeg', 
                                    'image/png', 
                                    'image/jpg',
                                    'application/msword', 
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'text/plain',
                                    'text/csv',
                                    'application/vnd.ms-excel',
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                                ];
                                $max_size = 10 * 1024 * 1024; // 10MB
                                
                                if (!in_array($file['type'], $allowed_types)) {
                                    throw new Exception("Tipo di file non consentito per {$file['name']}. Tipi supportati: PDF, DOC, DOCX, TXT, CSV, XLS, XLSX, JPG, PNG");
                                }
                                
                                if ($file['size'] > $max_size) {
                                    throw new Exception("File troppo grande: {$file['name']} (max 10MB)");
                                }
                                
                                // Stessa logica di upload dei file personnel
                                $upload_dir = __DIR__ . '/../uploads/' . $project_id . '/' . $partner_id . '/';
                                if (!is_dir($upload_dir)) {
                                    mkdir($upload_dir, 0755, true);
                                }
                                
                                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                                $unique_filename = uniqid() . '_' . $file_type . '_' . $wp_id . '_' . $key . '.' . $file_extension;
                                $file_path = $upload_dir . $unique_filename;
                                $relative_path = 'uploads/' . $project_id . '/' . $partner_id . '/' . $unique_filename;
                                
                                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                                    // Salva file mobility con identificatore specifico
                                    $mobility_file_field = "admin_mobility_{$mobility_report_id}_{$file_type}";
                                    $file_stmt = $conn->prepare("
                                        INSERT INTO uploaded_files 
                                        (project_id, file_field, filename, original_filename, file_path, file_size, file_type, uploaded_by) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                    ");
                                    $file_stmt->execute([
                                        $project_id,
                                        $mobility_file_field,
                                        $unique_filename,
                                        $file['name'],
                                        $relative_path,
                                        $file['size'],
                                        $file['type'],
                                        $current_user_id
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }
        
        $conn->commit();
        $_SESSION['success'] = 'Administrative report saved successfully.';
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = 'Error saving report: ' . $e->getMessage();
    }
    
    // Redirect per evitare re-submit
    header("Location: admin_reports.php?project_id=$project_id&partner_id=$partner_id");
    exit;
}

// ===================================================================
// FETCH EXISTING REPORT DATA
// ===================================================================

$report_entries_stmt = $conn->prepare("SELECT * FROM admin_reports WHERE project_id = ? AND partner_id = ?");
$report_entries_stmt->execute([$project_id, $partner_id]);
$report_entries_raw = $report_entries_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizza gli entries per work package
$report_entries = [];
$mobility_entries = [];
foreach ($report_entries_raw as $entry) {
    if (strpos($entry['personnel_name'], 'MOBILITY:') === 0) {
        $mobility_entries[$entry['work_package_id']][] = $entry;
    } else {
        $report_entries[$entry['work_package_id']][] = $entry;
    }
}

// Fetch uploaded files per admin reports
$uploaded_files = [];
if (!empty($report_entries_raw)) {
    // Recupera file per admin reports usando il pattern file_field
    $files_stmt = $conn->prepare("
        SELECT * FROM uploaded_files 
        WHERE project_id = ? AND file_field LIKE 'admin_report_%'
    ");
    $files_stmt->execute([$project_id]);
    $uploaded_files_raw = $files_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($uploaded_files_raw as $file) {
        // Estrai l'ID del report dal file_field: admin_report_{ID}_{type}
        if (preg_match('/admin_report_(\d+)_(.+)/', $file['file_field'], $matches)) {
            $report_id = $matches[1];
            $file_type = $matches[2]; // letter_of_assignment, timesheet, invoices
            $file['original_file_type'] = $file_type; // Aggiungi il tipo originale per il controllo
            $uploaded_files[$report_id][] = $file;
        }
        // Per mobility: admin_mobility_{ID}_{type}
        if (preg_match('/admin_mobility_(\d+)_(.+)/', $file['file_field'], $matches)) {
            $mobility_id = $matches[1];
            $file_type = $matches[2]; // boarding_cards, mobility_invoices
            $file['original_file_type'] = $file_type;
            $uploaded_files[$mobility_id][] = $file;
        }
    }
}

?>

<body class="">
    <div class="wrapper ">
        <?php include '../includes/sidebar.php'; ?>
        <?php include '../includes/navbar.php'; ?>
        <div class="content">
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-plain">
                        <div class="card-header">
                            <h4 class="card-title">Administrative Report for <?= htmlspecialchars($project['name']) ?></h4>
                            <p class="card-category">Partner: <?= htmlspecialchars($partner['name']) ?></p>
                        </div>
                        <div class="card-body">
                            <form action="" method="post" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-12 text-right">
                                        <button type="submit" class="btn btn-primary btn-round">Save Report</button>
                                    </div>
                                </div>
                                <input type="hidden" name="project_id" value="<?= $project_id ?>">
                                <input type="hidden" name="partner_id" value="<?= $partner_id ?>">

                                <?php foreach ($work_packages as $wp): ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="card-title"><?= htmlspecialchars($wp['wp_number']) ?>: <?= htmlspecialchars($wp['name']) ?></h5>
                                        <p class="card-category">Lead Partner: <?= htmlspecialchars($wp['lead_partner_name'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="card-body">
                                        <h6>Personnel Data</h6>
                                        
                                        <!-- Existing Personnel Entries -->
                                        <?php if (isset($report_entries[$wp['id']])): ?>
                                            <?php foreach ($report_entries[$wp['id']] as $entry): ?>
                                                <div class="personnel-entry mb-3 border p-3 rounded">
                                                    <div class="form-row">
                                                    <div class="form-row">
                                                        <div class="form-group col-md-6">
                                                            <label>Personnel Name</label>
                                                            <input type="text" name="personnel_name[<?= $wp['id'] ?>][<?= $entry['id'] ?>]" class="form-control" placeholder="Ex. John Doe" value="<?= htmlspecialchars($entry['personnel_name']) ?>">
                                                        </div>
                                                        <div class="form-group col-md-4">
                                                            <label>Working Days</label>
                                                            <input type="number" name="working_days[<?= $wp['id'] ?>][<?= $entry['id'] ?>]" class="form-control" placeholder="Ex. 20" value="<?= htmlspecialchars($entry['working_days']) ?>">
                                                        </div>
                                                        <div class="form-group col-md-2 d-flex align-items-end">
                                                            <button type="button" class="btn btn-danger btn-round btn-sm remove-personnel">Remove</button>
                                                        </div>
                                                    </div>
                                                    <div class="form-group col-md-12 mt-3">
    <label>Personnel Attachments:</label>
    
    <!-- Letter of Assignment -->
    <div class="custom-file mb-2">
        <input type="file" name="letter_of_assignment[<?= $wp['id'] ?>][<?= $entry['id'] ?>]" class="custom-file-input" lang="en">
        <label class="custom-file-label">Letter of Assignment</label>
    </div>
    <div class="file-list">
        <?php if (isset($uploaded_files[$entry['id']])): ?>
            <?php foreach ($uploaded_files[$entry['id']] as $file): ?>
                <?php if ($file['original_file_type'] === 'letter_of_assignment'): ?>
                    <p>
                        <i class="nc-icon nc-paper"></i> 
                        <a href="../<?= htmlspecialchars($file['file_path']) ?>" target="_blank">
                            <?= htmlspecialchars($file['original_filename']) ?>
                        </a> 
                        <small>(<?= number_format($file['file_size']/1024, 1) ?> KB)</small>
                        <button type="submit" name="delete_file_id" value="<?= $file['id'] ?>" 
                                class="btn btn-sm btn-danger btn-link" 
                                onclick="return confirm('Are you sure you want to delete this file?')">
                            <i class="nc-icon nc-simple-remove"></i>
                        </button>
                    </p>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Timesheet -->
    <div class="custom-file mb-2">
        <input type="file" name="timesheet[<?= $wp['id'] ?>][<?= $entry['id'] ?>]" class="custom-file-input" lang="en">
        <label class="custom-file-label">Timesheet</label>
    </div>
    <div class="file-list">
        <?php if (isset($uploaded_files[$entry['id']])): ?>
            <?php foreach ($uploaded_files[$entry['id']] as $file): ?>
                <?php if ($file['original_file_type'] === 'timesheet'): ?>
                    <p>
                        <i class="nc-icon nc-paper"></i> 
                        <a href="../<?= htmlspecialchars($file['file_path']) ?>" target="_blank">
                            <?= htmlspecialchars($file['original_filename']) ?>
                        </a> 
                        <small>(<?= number_format($file['file_size']/1024, 1) ?> KB)</small>
                        <button type="submit" name="delete_file_id" value="<?= $file['id'] ?>" 
                                class="btn btn-sm btn-danger btn-link" 
                                onclick="return confirm('Are you sure you want to delete this file?')">
                            <i class="nc-icon nc-simple-remove"></i>
                        </button>
                    </p>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Invoices -->
    <div class="custom-file">
        <input type="file" name="invoices[<?= $wp['id'] ?>][<?= $entry['id'] ?>]" class="custom-file-input" lang="en">
        <label class="custom-file-label">Invoices</label>
    </div>
    <div class="file-list">
        <?php if (isset($uploaded_files[$entry['id']])): ?>
            <?php foreach ($uploaded_files[$entry['id']] as $file): ?>
                <?php if ($file['original_file_type'] === 'invoices'): ?>
                    <p>
                        <i class="nc-icon nc-paper"></i> 
                        <a href="../<?= htmlspecialchars($file['file_path']) ?>" target="_blank">
                            <?= htmlspecialchars($file['original_filename']) ?>
                        </a> 
                        <small>(<?= number_format($file['file_size']/1024, 1) ?> KB)</small>
                        <button type="submit" name="delete_file_id" value="<?= $file['id'] ?>" 
                                class="btn btn-sm btn-danger btn-link" 
                                onclick="return confirm('Are you sure you want to delete this file?')">
                            <i class="nc-icon nc-simple-remove"></i>
                        </button>
                    </p>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <!-- New Personnel Entry Template -->
                                        <div class="personnel-entry mb-3 border p-3 rounded">
                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label>Personnel Name</label>
                                                    <input type="text" name="personnel_name[<?= $wp['id'] ?>][]" class="form-control" placeholder="Ex. John Doe">
                                                </div>
                                                <div class="form-group col-md-4">
                                                    <label>Working Days</label>
                                                    <input type="number" name="working_days[<?= $wp['id'] ?>][]" class="form-control" placeholder="Ex. 20">
                                                </div>
                                                <div class="form-group col-md-2 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger btn-round btn-sm remove-personnel">Remove</button>
                                                </div>
                                            </div>
                                            <div class="form-group col-md-12 mt-3">
                                                <label>Personnel Attachments:</label>
                                                <div class="custom-file mb-2">
                                                    <input type="file" name="letter_of_assignment[<?= $wp['id'] ?>][]" class="custom-file-input" lang="en">
                                                    <label class="custom-file-label">Letter of Assignment</label>
                                                </div><div class="file-list"></div>
                                                <div class="custom-file mb-2">
                                                    <input type="file" name="timesheet[<?= $wp['id'] ?>][]" class="custom-file-input" lang="en">
                                                    <label class="custom-file-label">Timesheet</label>
                                                </div><div class="file-list"></div>
                                                <div class="custom-file">
                                                    <input type="file" name="invoices[<?= $wp['id'] ?>][]" class="custom-file-input" lang="en">
                                                    <label class="custom-file-label">Invoices</label>
                                                </div><div class="file-list"></div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-info btn-round btn-sm add-personnel" data-wp-id="<?= $wp['id'] ?>">Add Personnel</button>

                                        <h6 class="mt-4">Mobility</h6>
                                        
                                        <!-- Existing Mobility Entries -->
                                        <?php if (isset($mobility_entries[$wp['id']])): ?>
                                            <?php foreach ($mobility_entries[$wp['id']] as $mobility): ?>
                                                <div class="mobility-entry mb-3 border p-3 rounded">
                                                    <div class="form-row">
                                                        <div class="form-group col-md-6">
                                                            <label>Mobility Name</label>
                                                            <input type="text" name="mobility_name[<?= $wp['id'] ?>][<?= $mobility['id'] ?>]" class="form-control" value="<?= htmlspecialchars(str_replace('MOBILITY: ', '', $mobility['personnel_name'])) ?>">
                                                        </div>
                                                        <div class="form-group col-md-6">
                                                            <label>Mobility Attachments:</label>
                                                            <div class="custom-file mb-2">
                                                                <input type="file" name="boarding_cards[<?= $wp['id'] ?>][<?= $mobility['id'] ?>]" class="custom-file-input" lang="en">
                                                                <label class="custom-file-label">Boarding Cards</label>
                                                            </div>
                                                            <div class="file-list">
                                                                <?php if (isset($uploaded_files[$mobility['id']])): ?>
                                                                    <?php foreach ($uploaded_files[$mobility['id']] as $file): ?>
                                                                        <?php if ($file['file_field'] === 'boarding_cards'): ?>
                                                                            <p>
                                                                                <i class="nc-icon nc-paper"></i> 
                                                                                <a href="../<?= htmlspecialchars($file['file_path']) ?>" target="_blank">
                                                                                    <?= htmlspecialchars($file['original_filename']) ?>
                                                                                </a>
                                                                                <button type="submit" name="delete_file_id" value="<?= $file['id'] ?>" 
                                                                                        class="btn btn-sm btn-danger btn-link" 
                                                                                        onclick="return confirm('Are you sure you want to delete this file?')">
                                                                                    <i class="nc-icon nc-simple-remove"></i>
                                                                                </button>
                                                                            </p>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="custom-file">
                                                                <input type="file" name="mobility_invoices[<?= $wp['id'] ?>][<?= $mobility['id'] ?>]" class="custom-file-input" lang="en">
                                                                <label class="custom-file-label">Invoices</label>
                                                            </div>
                                                            <div class="file-list">
                                                                <?php if (isset($uploaded_files[$mobility['id']])): ?>
                                                                    <?php foreach ($uploaded_files[$mobility['id']] as $file): ?>
                                                                        <?php if ($file['file_field'] === 'mobility_invoices'): ?>
                                                                            <p>
                                                                                <i class="nc-icon nc-paper"></i> 
                                                                                <a href="../<?= htmlspecialchars($file['file_path']) ?>" target="_blank">
                                                                                    <?= htmlspecialchars($file['original_filename']) ?>
                                                                                </a>
                                                                                <button type="submit" name="delete_file_id" value="<?= $file['id'] ?>" 
                                                                                        class="btn btn-sm btn-danger btn-link" 
                                                                                        onclick="return confirm('Are you sure you want to delete this file?')">
                                                                                    <i class="nc-icon nc-simple-remove"></i>
                                                                                </button>
                                                                            </p>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        
                                        <!-- New Mobility Entry Template -->
                                        <div class="mobility-entry mb-3 border p-3 rounded">
                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label>Mobility Name</label>
                                                    <select name="mobility_name[<?= $wp['id'] ?>][]" class="form-control">
                                                        <option value="">Select Mobility</option>
                                                        <?php if (isset($mobilities_by_wp[$wp['id']])): ?>
                                                            <?php foreach ($mobilities_by_wp[$wp['id']] as $mobility): ?>
                                                                <option value="<?= htmlspecialchars($mobility['name']) ?>">
                                                                    <?= htmlspecialchars($mobility['name']) ?>
                                                                    <?php if (!empty($mobility['location'])): ?>
                                                                        - <?= htmlspecialchars($mobility['location']) ?>
                                                                    <?php endif; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <option value="" disabled>No mobilities available for this WP</option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                                <div class="form-group col-md-6">
                                                    <label>Mobility Attachments:</label>
                                                    <div class="custom-file mb-2">
                                                        <input type="file" name="boarding_cards[<?= $wp['id'] ?>][]" class="custom-file-input" lang="en">
                                                        <label class="custom-file-label">Boarding Cards</label>
                                                    </div><div class="file-list"></div>
                                                    <div class="custom-file">
                                                        <input type="file" name="mobility_invoices[<?= $wp['id'] ?>][]" class="custom-file-input" lang="en">
                                                        <label class="custom-file-label">Invoices</label>
                                                    </div><div class="file-list"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-info btn-round btn-sm add-mobility" data-wp-id="<?= $wp['id'] ?>">Add Mobility</button>
                                    </div>
                                </div>
                                <?php endforeach; ?>

                                <button type="submit" class="btn btn-primary btn-round">Save Report</button>
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