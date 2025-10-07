<?php
/**
 * FileUploadHandler Class
 * 
 * Handles file uploads for activity reports with project-based organization.
 * Files are organized in: uploads/reports/[project-name]/[files]
 * 
 * @package EU Project Manager
 * @author EU Project Manager Team
 * @version 1.0
 */

class FileUploadHandler {
    
    private $conn;
    private $base_upload_dir;
    private $max_file_size;
    private $allowed_extensions;
    
    /**
     * Constructor
     * 
     * @param PDO $conn Database connection
     */
    public function __construct($conn) {
        $this->conn = $conn;
        $this->base_upload_dir = '../uploads/reports/';
        $this->max_file_size = 10485760; // 10MB in bytes
        $this->allowed_extensions = ['pdf', 'doc', 'docx', 'xlsx', 'xls', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
    }
    
    /**
     * Main method to handle file uploads for reports
     * 
     * @param array $files $_FILES array for the uploaded files
     * @param array $titles Array of titles for the files (optional)
     * @param int $report_id The ID of the report these files belong to
     * @param int $user_id The ID of the user uploading the files
     * @return array Array with success status and message
     */
    public function handleReportFiles($files, $titles, $report_id, $user_id) {
        try {
            // Log per debug
            error_log("FileUploadHandler: Starting upload process for report #$report_id");
            
            // Get project information from report_id
            $project = $this->getProjectFromReport($report_id);
            if (!$project) {
                error_log("FileUploadHandler: Project not found for report #$report_id");
                return $this->createResponse(false, 'Project not found for this report.');
            }
            
            error_log("FileUploadHandler: Found project: " . $project['name']);
            
            // Create project-specific upload directory
            $project_upload_dir = $this->createProjectDirectory($project['name']);
            if (!$project_upload_dir) {
                error_log("FileUploadHandler: Failed to create directory for project: " . $project['name']);
                return $this->createResponse(false, 'Failed to create upload directory.');
            }
            
            error_log("FileUploadHandler: Upload directory created: $project_upload_dir");
            
            // Process all uploaded files
            return $this->processFiles($files, $titles, $project_upload_dir, $report_id, $user_id, $project['id']);
            
        } catch (Exception $e) {
            error_log("FileUploadHandler Error: " . $e->getMessage());
            return $this->createResponse(false, 'An error occurred during file upload: ' . $e->getMessage());
        }
    }
    
    /**
     * Get project information from report ID
     * 
     * @param int $report_id Report ID
     * @return array|false Project data or false if not found
     */
    private function getProjectFromReport($report_id) {
        $stmt = $this->conn->prepare("
            SELECT p.id, p.name 
            FROM activity_reports ar 
            JOIN activities a ON ar.activity_id = a.id 
            JOIN work_packages wp ON a.work_package_id = wp.id 
            JOIN projects p ON wp.project_id = p.id 
            WHERE ar.id = ?
        ");
        $stmt->execute([$report_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create project-specific directory
     * 
     * @param string $project_name Original project name
     * @return string|false Full path to project directory or false on failure
     */
    private function createProjectDirectory($project_name) {
        $project_folder = $this->sanitizeProjectName($project_name);
        $full_project_dir = $this->base_upload_dir . $project_folder . '/';
        
        error_log("FileUploadHandler: Creating directory: $full_project_dir");
        
        if (!is_dir($full_project_dir)) {
            if (!mkdir($full_project_dir, 0755, true)) {
                error_log("FileUploadHandler: mkdir failed for: $full_project_dir");
                return false;
            }
            error_log("FileUploadHandler: Directory created successfully");
        } else {
            error_log("FileUploadHandler: Directory already exists");
        }
        
        return $full_project_dir;
    }
    
    /**
     * Process all uploaded files
     * 
     * @param array $files Files array from $_FILES
     * @param array $titles Array of titles for the files
     * @param string $upload_dir Target upload directory
     * @param int $report_id Report ID
     * @param int $user_id User ID
     * @return array Response array
     */
    private function processFiles($files, $titles, $upload_dir, $report_id, $user_id, $project_id) {
        $uploaded_files = [];
        $errors = [];
        
        error_log("FileUploadHandler: Processing " . count($files['name']) . " files");
        
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                // Get title for this file (if provided)
                $file_title = isset($titles[$i]) ? trim($titles[$i]) : '';
                if (empty($file_title)) {
                    $file_title = $files['name'][$i]; // Fallback to filename if no title
                }
                
                error_log("FileUploadHandler: Processing file #$i: " . $files['name'][$i] . " with title: $file_title");
                
                $result = $this->processSingleFile(
                    $files['name'][$i],
                    $files['tmp_name'][$i],
                    $files['size'][$i],
                    $file_title,
                    $upload_dir,
                    $report_id,
                    $user_id,
                    $project_id // Pass project_id down
                );
                
                if ($result['success']) {
                    $uploaded_files[] = $files['name'][$i];
                    error_log("FileUploadHandler: File uploaded successfully: " . $files['name'][$i]);
                } else {
                    $errors[] = $result['error'];
                    error_log("FileUploadHandler: File upload failed: " . $result['error']);
                }
            } else {
                $error_msg = "Upload error for: {$files['name'][$i]} (Error code: {$files['error'][$i]})";
                $errors[] = $error_msg;
                error_log("FileUploadHandler: " . $error_msg);
            }
        }
        
        return $this->createFinalResponse($uploaded_files, $errors);
    }
    
/**
     * Process a single uploaded file
     * 
     * @param string $filename Original filename
     * @param string $tmp_name Temporary file path
     * @param int $file_size File size in bytes
     * @param string $title User-provided title for the file
     * @param string $upload_dir Target upload directory
     * @param int $report_id Report ID
     * @param int $user_id User ID
     * @return array Result array
     */
    private function processSingleFile($filename, $tmp_name, $file_size, $title, $upload_dir, $report_id, $user_id, $project_id) {
        // Security validations
        if (!$this->isAllowedFileType($filename)) {
            return ['success' => false, 'error' => "File type not allowed: {$filename}"];
        }
        
        if ($file_size > $this->max_file_size) {
            return ['success' => false, 'error' => "File too large: {$filename}"];
        }
        
        // Generate safe filename USANDO IL TITOLO
        $safe_filename = $this->generateSafeFilename($filename, $title);
        $full_file_path = $upload_dir . $safe_filename;
        
        error_log("FileUploadHandler: Moving file from $tmp_name to $full_file_path");
        
        // Move file
        if (!move_uploaded_file($tmp_name, $full_file_path)) {
            return ['success' => false, 'error' => "Failed to move file: {$filename}"];
        }
        
        error_log("FileUploadHandler: File moved successfully, saving to database");
        
        // Save to database
        if (!$this->saveFileToDatabase($report_id, $project_id, $safe_filename, $filename, $title, $full_file_path, $file_size, $user_id)) {
            // Clean up file if database insert failed
            if (file_exists($full_file_path)) {
                unlink($full_file_path);
            }
            return ['success' => false, 'error' => "Database error for: {$filename}"];
        }
        
        return ['success' => true];
    }
    
    /**
     * Generate safe filename with timestamp, usando il titolo se disponibile
     * 
     * @param string $original_filename Original filename
     * @param string $title User-provided title (optional)
     * @return string Safe filename
     */
    private function generateSafeFilename($original_filename, $title = '') {
        $file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
        
        // Usa il titolo se disponibile, altrimenti usa il nome del file originale
        if (!empty($title)) {
            // Sanitizza il titolo per renderlo sicuro come nome file
            $safe_basename = $this->sanitizeForFilename($title);
        } else {
            // Fallback al nome originale del file
            $safe_basename = preg_replace('/[^a-zA-Z0-9._-]/', '', pathinfo($original_filename, PATHINFO_FILENAME));
        }
        
        // Se dopo la sanitizzazione il nome è vuoto, usa il nome originale
        if (empty($safe_basename)) {
            $safe_basename = preg_replace('/[^a-zA-Z0-9._-]/', '', pathinfo($original_filename, PATHINFO_FILENAME));
        }
        
        // Se ancora vuoto, usa un nome di default
        if (empty($safe_basename)) {
            $safe_basename = 'file';
        }
        
        return time() . '_' . $safe_basename . '.' . $file_extension;
    }
    
    /**
     * Sanitizza una stringa per renderla sicura come nome file
     * 
     * @param string $input Input string
     * @return string Sanitized string
     */
    private function sanitizeForFilename($input) {
        // Rimuove caratteri speciali e li sostituisce con underscore
        $sanitized = trim($input);
        
        // Sostituisce spazi con underscore
        $sanitized = str_replace(' ', '_', $sanitized);
        
        // Rimuove caratteri non sicuri per i file system
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '', $sanitized);
        
        // Rimuove underscore multipli consecutivi
        $sanitized = preg_replace('/_+/', '_', $sanitized);
        
        // Rimuove underscore all'inizio e alla fine
        $sanitized = trim($sanitized, '_');
        
        // Limita la lunghezza per evitare nomi file troppo lunghi
        if (strlen($sanitized) > 50) {
            $sanitized = substr($sanitized, 0, 50);
            $sanitized = rtrim($sanitized, '_');
        }
        
        return $sanitized;
    }
    
    /**
     * Save file information to database
     * 
     * @param int $report_id Report ID
     * @param string $safe_filename Safe filename
     * @param string $original_filename Original filename
     * @param string $title User-provided title
     * @param string $file_path Full file path
     * @param int $file_size File size
     * @param int $user_id User ID
     * @return bool Success status
     */
    private function saveFileToDatabase($report_id, $project_id, $safe_filename, $original_filename, $title, $file_path, $file_size, $user_id) {
        $file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
        
        // Prima verifichiamo se esiste il campo title nella tabella
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO uploaded_files 
                (report_id, project_id, filename, original_filename, title, file_path, file_size, file_type, uploaded_by, uploaded_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $report_id,
                $project_id,
                $safe_filename,
                $original_filename,
                $title,
                $file_path,
                $file_size,
                $file_extension,
                $user_id
            ]);
            
            if ($result) {
                error_log("FileUploadHandler: File saved to database successfully");
            } else {
                error_log("FileUploadHandler: Database insert failed: " . implode(', ', $stmt->errorInfo()));
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("FileUploadHandler: Database error: " . $e->getMessage());
            
            // Se il campo title non esiste, prova senza
            if (strpos($e->getMessage(), 'title') !== false) {
                error_log("FileUploadHandler: Trying without title field");
                $stmt = $this->conn->prepare("
                    INSERT INTO uploaded_files 
                    (report_id, project_id, filename, original_filename, file_path, file_size, file_type, uploaded_by, uploaded_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                return $stmt->execute([
                    $report_id,
                    $project_id,
                    $safe_filename,
                    $original_filename,
                    $file_path,
                    $file_size,
                    $file_extension,
                    $user_id
                ]);
            }
            
            return false;
        }
    }
    
    /**
     * Check if file type is allowed
     * 
     * @param string $filename Filename to check
     * @return bool True if allowed
     */
    private function isAllowedFileType($filename) {
        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($file_extension, $this->allowed_extensions);
    }
    
  
    /**
     * Sanitize project name for use as folder name
     * 
     * @param string $project_name Original project name
     * @return string Sanitized folder name
     */
    private function sanitizeProjectName($project_name) {
        // Convert to lowercase and replace spaces/special chars with underscores
        $sanitized = strtolower(trim($project_name));
        $sanitized = preg_replace('/[^a-z0-9._-]/', '_', $sanitized);
        $sanitized = preg_replace('/_+/', '_', $sanitized); // Remove multiple underscores
        $sanitized = trim($sanitized, '_'); // Remove leading/trailing underscores
        
        // Ensure it's not empty and has reasonable length
        if (empty($sanitized)) {
            $sanitized = 'unknown_project';
        }
        
        if (strlen($sanitized) > 50) {
            $sanitized = substr($sanitized, 0, 50);
        }
        
        return $sanitized;
    }
    
    /**
     * Create standardized response array
     * 
     * @param bool $success Success status
     * @param string $message Response message
     * @return array Response array
     */
    private function createResponse($success, $message) {
        return ['success' => $success, 'message' => $message];
    }
    
    /**
     * Create final response based on upload results
     * 
     * @param array $uploaded_files Successfully uploaded files
     * @param array $errors Error messages
     * @return array Final response
     */
    private function createFinalResponse($uploaded_files, $errors) {
        $success_count = count($uploaded_files);
        $error_count = count($errors);
        
        if ($success_count > 0 && $error_count === 0) {
            return $this->createResponse(true, "{$success_count} file(s) uploaded successfully.");
        } elseif ($success_count > 0 && $error_count > 0) {
            return $this->createResponse(true, "{$success_count} file(s) uploaded, {$error_count} error(s): " . implode(', ', $errors));
        } else {
            return $this->createResponse(false, "Upload failed: " . implode(', ', $errors));
        }
    }
}
?>