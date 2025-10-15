<?php
// ===================================================================
// UTILITY FUNCTIONS - ENGLISH VERSION
// ===================================================================

function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
    }
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function getUserRole() {
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
    }
    return $_SESSION['role'] ?? null;
}

function getUserId() {
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
    }
    return $_SESSION['user_id'] ?? null;
}

function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (!$datetime) return '-';
    return date($format, strtotime($datetime));
}


function getStatusBadge($status) {
    $badges = [
        'not_started' => '<span class="badge badge-secondary">Not Started</span>',
        'in_progress' => '<span class="badge badge-primary">In Progress</span>',
        'completed' => '<span class="badge badge-success">Completed</span>',
        'overdue' => '<span class="badge badge-danger">Overdue</span>',
        'delayed' => '<span class="badge badge-warning">Delayed</span>',
        'active' => '<span class="badge badge-success">Active</span>',
        'planning' => '<span class="badge badge-info">Planning</span>',
        'suspended' => '<span class="badge badge-warning">Suspended</span>',
        'pending' => '<span class="badge badge-warning">Pending</span>',
    ];
    
    return $badges[$status] ?? '<span class="badge badge-secondary">' . ucfirst($status) . '</span>';
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function redirectTo($url) {
    header("Location: $url");
    exit;
}

function showAlert($message, $type = 'info') {
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
    }
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

function displayAlert() {
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
    }
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        echo '<div class="alert alert-' . $alert['type'] . ' alert-dismissible fade show" role="alert">';
        echo $alert['message'];
        echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
        echo '<span aria-hidden="true">&times;</span>';
        echo '</button>';
        echo '</div>';
        unset($_SESSION['alert']);
    }
}

// Database functions for projects
function getMyProjects($conn, $user_id, $role) {
    if ($role === 'super_admin') {
        $sql = "SELECT p.*, COUNT(DISTINCT pp.partner_id) as partner_count,
                       AVG(wp.progress) as progress
                FROM projects p 
                LEFT JOIN project_partners pp ON p.id = pp.project_id
                LEFT JOIN work_packages wp ON p.id = wp.project_id
                WHERE p.status IN ('active', 'planning')
                GROUP BY p.id 
                ORDER BY p.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } else {
        $user_partner_id = $_SESSION['partner_id'] ?? 0;
        $sql = "SELECT p.*, COUNT(DISTINCT pp2.partner_id) as partner_count,
                       AVG(wp.progress) as progress
                FROM projects p 
                JOIN project_partners pp ON p.id = pp.project_id
                LEFT JOIN project_partners pp2 ON p.id = pp2.project_id
                LEFT JOIN work_packages wp ON p.id = wp.project_id
                WHERE pp.partner_id = ? AND p.status IN ('active', 'planning')
                GROUP BY p.id 
                ORDER BY p.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_partner_id]);
    }
    
    return $stmt->fetchAll();
}

function getDashboardStats($conn, $user_id, $role) {
    $stats = [];
    $user_partner_id = $_SESSION['partner_id'] ?? 0;

    // Active projects
    if ($role === 'super_admin') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM projects WHERE status = 'active'");
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM projects p 
                               JOIN project_partners pp ON p.id = pp.project_id 
                               WHERE pp.partner_id = ? AND p.status = 'active'");
        $stmt->execute([$user_partner_id]);
    }
    $stats['active_projects'] = $stmt->fetch()['count'];
    
    // Completed activities this month
    if ($role === 'super_admin') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM activities 
                               WHERE status = 'completed' 
                               AND MONTH(updated_at) = MONTH(CURRENT_DATE())
                               AND YEAR(updated_at) = YEAR(CURRENT_DATE())");
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM activities a
                               JOIN work_packages wp ON a.work_package_id = wp.id
                               JOIN project_partners pp ON wp.project_id = pp.project_id
                               WHERE pp.partner_id = ? AND a.status = 'completed'
                               AND MONTH(a.updated_at) = MONTH(CURRENT_DATE())
                               AND YEAR(a.updated_at) = YEAR(CURRENT_DATE())");
        $stmt->execute([$user_partner_id]);
    }
    $stats['completed_activities'] = $stmt->fetch()['count'];
    
    // Upcoming deadlines (7 days)
    if ($role === 'super_admin') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM activities 
                               WHERE end_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
                               AND status != 'completed'");
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM activities a
                               JOIN work_packages wp ON a.work_package_id = wp.id
                               JOIN project_partners pp ON wp.project_id = pp.project_id
                               WHERE pp.partner_id = ? 
                               AND a.end_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
                               AND a.status != 'completed'");
        $stmt->execute([$user_partner_id]);
    }
    $stats['upcoming_deadlines'] = $stmt->fetch()['count'];
    
    return $stats;
}

function getProjectDetails($conn, $project_id) {
    $sql = "SELECT p.*, u.full_name as coordinator_name, u.email as coordinator_email
            FROM projects p
            LEFT JOIN users u ON p.coordinator_id = u.id
            WHERE p.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$project_id]);
    return $stmt->fetch();
}

function getAllPartners($conn) {
    $sql = "SELECT id, name FROM partners ORDER BY name";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getProjectPartners($conn, $project_id) {
    $sql = "SELECT pp.partner_id, p.name, p.country
            FROM project_partners pp
            JOIN partners p ON pp.partner_id = p.id
            WHERE pp.project_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$project_id]);
    return $stmt->fetchAll();
}

function getWorkPackagesWithActivities($conn, $project_id) {
    $sql = "SELECT wp.*, 
                   (SELECT COUNT(*) FROM activities WHERE work_package_id = wp.id) as total_activities,
                   (SELECT COUNT(*) FROM activities WHERE work_package_id = wp.id AND status = 'completed') as completed_activities
            FROM work_packages wp
            WHERE wp.project_id = ?
            ORDER BY wp.wp_number ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$project_id]);
    return $stmt->fetchAll();
}

function getMilestones($conn, $project_id) {
    $sql = "SELECT * FROM milestones WHERE project_id = ? ORDER BY end_date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$project_id]);
    return $stmt->fetchAll();
}

function getProjectFiles($conn, $project_id) {
    $sql = "SELECT uf.* 
            FROM uploaded_files uf
            JOIN activity_reports ar ON uf.report_id = ar.id
            JOIN activities a ON ar.activity_id = a.id
            JOIN work_packages wp ON a.work_package_id = wp.id
            WHERE wp.project_id = ? ORDER BY uf.uploaded_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$project_id]);
    return $stmt->fetchAll();
}

function getProgramTypes() {
    return [
        'Horizon 2020' => 'Horizon 2020',
        'Horizon Europe' => 'Horizon Europe',
        'Erasmus+' => 'Erasmus+',
        'COSME' => 'COSME',
        'LIFE' => 'LIFE',
        'Creative Europe' => 'Creative Europe',
        'Other' => 'Other'
    ];
}

// Role display functions
function getRoleDisplayName($role) {
    $roles = [
        'super_admin' => 'Super Administrator',
        'coordinator' => 'Project Coordinator',
        'partner' => 'Partner Organization',
        'admin' => 'Administrator'
    ];
    
    return $roles[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

// Activity status functions
function getActivityStatusOptions() {
    return [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'completed' => 'Completed', 
        'overdue' => 'Overdue'
    ];
}

// Project status functions
function getProjectStatusOptions() {
    return [
        'planning' => 'Planning',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'completed' => 'Completed'
    ];
}

// Work package status functions
function getWorkPackageStatusOptions() {
    return [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress', 
        'completed' => 'Completed',
        'delayed' => 'Delayed'
    ];
}

// File upload functions
function isAllowedFileType($filename) {
    $allowed_extensions = ['pdf', 'doc', 'docx', 'xlsx', 'xls', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
    $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($file_extension, $allowed_extensions);
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Validation functions
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Notification functions
function createAlert($conn, $user_id, $type, $title, $message, $project_id = null, $activity_id = null) {
    $sql = "INSERT INTO alerts (user_id, project_id, activity_id, type, title, message) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$user_id, $project_id, $activity_id, $type, $title, $message]);
}

function getUnreadAlertsCount($conn, $user_id) {
    $sql = "SELECT COUNT(*) as count FROM alerts WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetch()['count'];
}

// Date helper functions  
function isDeadlineApproaching($end_date, $days = 7) {
    if (!$end_date) return false;
    $due_timestamp = strtotime($end_date);
    $threshold_timestamp = strtotime("+{$days} days");
    return $due_timestamp <= $threshold_timestamp && $due_timestamp >= time();
}

function getDaysUntilDeadline($end_date) {
    if (!$end_date) return null;
    $due_timestamp = strtotime($end_date);
    $now_timestamp = time();
    $diff_days = floor(($due_timestamp - $now_timestamp) / (60 * 60 * 24));
    return $diff_days;
}

// Success/Error message helpers
function setSuccessMessage($message) {
    showAlert($message, 'success');
}

function setErrorMessage($message) {
    showAlert($message, 'danger');
}

function setWarningMessage($message) {
    showAlert($message, 'warning');
}

function setInfoMessage($message) {
    showAlert($message, 'info');
}

// Report Status display function


function getAvailableCoordinators($conn) {
    $sql = "SELECT id, full_name FROM users WHERE role = 'coordinator' OR role = 'super_admin' ORDER BY full_name";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}
// Aggiungi questa classe alla fine di functions.php
class Flash {
    public static function set($type, $message) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[$type] = $message;
    }
    
    public static function get($type) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $message = $_SESSION[$type] ?? null;
        unset($_SESSION[$type]);
        return $message;
    }
}

/**
 * Recalculates the total budget for a given Work Package and updates the database.
 * The total is the sum of all detailed costs (personnel, travel, other).
 *
 * @param PDO $conn The database connection object.
 * @param int $work_package_id The ID of the Work Package to recalculate.
 * @return bool True on success, false on failure.
 */
function recalculateWorkPackageBudget($conn, $work_package_id) {
    if (empty($work_package_id)) {
        return false;
    }

    try {
        // Query to calculate the sum of all detailed costs for the WP
        $calc_stmt = $conn->prepare("
            SELECT 
                -- Sum of Personnel Costs
                SUM(
                    CASE 
                        WHEN wpb.wp_type = 'project_management' THEN COALESCE(wpb.project_management_cost, 0)
                        ELSE COALESCE(wpb.working_days_total, 0)
                    END
                ) as total_personnel,
                
                -- Sum of Other Costs
                SUM(COALESCE(wpb.other_costs, 0)) as total_other,

                -- Sum of Travel Costs from the related table
                (SELECT SUM(COALESCE(bts.total, 0)) 
                 FROM budget_travel_subsistence bts
                 JOIN work_package_partner_budgets wpb_inner ON bts.wp_partner_budget_id = wpb_inner.id
                 WHERE wpb_inner.work_package_id = :wp_id_travel) as total_travel

            FROM work_package_partner_budgets wpb
            WHERE wpb.work_package_id = :wp_id_main
        ");

        $calc_stmt->execute([
            ':wp_id_travel' => $work_package_id,
            ':wp_id_main' => $work_package_id
        ]);
        
        $totals = $calc_stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate the grand total
        $grand_total = ($totals['total_personnel'] ?? 0) + 
                       ($totals['total_other'] ?? 0) + 
                       ($totals['total_travel'] ?? 0);

        // Update the budget field in the work_packages table
        $update_stmt = $conn->prepare(
            "UPDATE work_packages SET budget = :grand_total WHERE id = :wp_id"
        );
        
        return $update_stmt->execute([
            ':grand_total' => $grand_total,
            ':wp_id' => $work_package_id
        ]);

    } catch (Exception $e) {
        // In case of an error, log it or handle it as needed
        // For now, we just return false
        return false;
    }
}

/**
 * Handles the upload of files associated with a report.
 *
 * @param PDO $conn The database connection object.
 * @param array $files The $_FILES array for the uploaded files.
 * @param int $report_id The ID of the report the files belong to.
 * @param int $user_id The ID of the user uploading the files.
 */
function handleFileUploads($conn, $files, $report_id, $user_id) {
    $upload_dir = '../uploads/reports/'; // Specific directory for report files
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'image/jpeg', 'image/png'];
    $max_size = 10 * 1024 * 1024; // 10 MB

    foreach ($files['name'] as $key => $name) {
        $file_tmp = $files['tmp_name'][$key];
        $file_size = $files['size'][$key];
        $file_type = $files['type'][$key];
        $file_error = $files['error'][$key];

        if ($file_error === UPLOAD_ERR_OK) {
            if (!in_array($file_type, $allowed_types)) {
                error_log("File '$name': Invalid file type.");
                continue;
            }
            if ($file_size > $max_size) {
                error_log("File '$name': Exceeds maximum size of 10MB.");
                continue;
            }

            $file_extension = pathinfo($name, PATHINFO_EXTENSION);
            $unique_filename = uniqid('report_' . $report_id . '_', true) . '.' . $file_extension;
            $destination = $upload_dir . $unique_filename;

            if (move_uploaded_file($file_tmp, $destination)) {
                $stmt = $conn->prepare("
                    INSERT INTO uploaded_files (report_id, uploaded_by, filename, original_filename, file_path, file_size, file_type)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                if (!$stmt->execute([$report_id, $user_id, $unique_filename, $name, 'uploads/reports/' . $unique_filename, $file_size, $file_type])) {
                    error_log("File '$name': Failed to save to database. " . $stmt->errorInfo()[2]);
                    unlink($destination); // Clean up uploaded file
                }
            } else {
                error_log("File '$name': Failed to move to destination. Check permissions.");
            }
        } else {
            error_log("File '$name': Upload error code: $file_error.");
            }
        }
        }
        
        /**
         * Converts plain text into HTML, making URLs clickable and preserving line breaks.
         *
         * @param string $text The input text.
         * @return string The processed HTML.
         */
        function makeTextClickable($text) {
    // 1. First, escape all HTML to prevent XSS
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // 2. Find all URLs (http, https, ftp) and wrap them in <a> tags
    $url_pattern = '/(https?:\\/\\/[a-zA-Z0-9\\-._~:?#\\[\\]@!$&\'()*+,;=\\/]+)/';
    $text = preg_replace($url_pattern, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', $text);

    // 3. Convert newline characters to <br> tags
    $text = nl2br($text);

    return $text;
}