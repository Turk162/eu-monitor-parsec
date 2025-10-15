<?php
/**
 * FileUploadHandler Class - Local Filesystem Storage
 * 
 * Gestisce l'upload dei file per i report delle attività su filesystem locale.
 * Struttura organizzata:
 * - /uploads/progetto/reports/WPX/num_attivita-filename
 * - /uploads/progetto/admin_reports/organizzazione/filename
 * - /uploads/progetto/documents/filename
 * - /uploads/progetto/deliverables/WPX/num_attivita-filename
 * - /uploads/progetto/various/filename
 * 
 * @package EU Project Manager
 * @version 3.0 - Local Filesystem
 */

class FileUploadHandler {
    
    private $conn;
    private $base_upload_dir;
    private $max_file_size;
    private $allowed_extensions;
    
    /**
     * Costruttore
     * 
     * @param PDO $conn Connessione database
     */
    public function __construct($conn) {
        $this->conn = $conn;
        $this->base_upload_dir = __DIR__ . '/../../uploads/';
        $this->max_file_size = 10485760; // 10MB in bytes
        $this->allowed_extensions = ['pdf', 'doc', 'docx', 'xlsx', 'xls', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
    }
    
    /**
     * Metodo principale per gestire upload file per report
     * 
     * @param array $files Array $_FILES dei file caricati
     * @param array $titles Array dei titoli dei file (opzionale)
     * @param int $report_id ID del report
     * @param int $user_id ID dell'utente che carica
     * @return array Array con success status e message
     */
    public function handleReportFiles($files, $titles, $report_id, $user_id) {
        try {
            error_log("FileUploadHandler: Inizio processo upload per report #$report_id");
            
            // Recupera informazioni progetto, WP e activity dal report
            $reportInfo = $this->getReportInformation($report_id);
            if (!$reportInfo) {
                error_log("FileUploadHandler: Informazioni report non trovate per #$report_id");
                return $this->createResponse(false, 'Informazioni report non trovate.');
            }
            
            error_log("FileUploadHandler: Report info - Project: {$reportInfo['project_name']}, WP: {$reportInfo['wp_number']}, Activity: {$reportInfo['activity_id']}");
            
            // Crea struttura cartelle per report: /uploads/progetto/reports/WPX/
            $uploadDir = $this->createReportDirectory(
                $reportInfo['project_name'],
                $reportInfo['wp_number']
            );
            
            if (!$uploadDir) {
                return $this->createResponse(false, 'Impossibile creare directory upload.');
            }
            
            error_log("FileUploadHandler: Directory upload: $uploadDir");
            
            // Processa tutti i file caricati
            return $this->processFilesToLocal(
                $files, 
                $titles, 
                $uploadDir, 
                $report_id, 
                $user_id,
                $reportInfo,
                'report'  // Categoria file
            );
            
        } catch (Exception $e) {
            error_log("FileUploadHandler Error: " . $e->getMessage());
            return $this->createResponse(false, 'Errore durante upload file: ' . $e->getMessage());
        }
    }
    
    /**
     * Upload file per admin reports
     * 
     * @param array $files Array $_FILES
     * @param string $projectName Nome progetto
     * @param string $organizationName Nome organizzazione
     * @param int $user_id ID utente
     * @param int $project_id ID progetto
     * @return array Response
     */
    public function handleAdminReportFiles($files, $projectName, $organizationName, $user_id, $project_id) {
        try {
            // Crea struttura: /uploads/progetto/admin_reports/organizzazione/
            $uploadDir = $this->createAdminReportDirectory($projectName, $organizationName);
            
            if (!$uploadDir) {
                return $this->createResponse(false, 'Impossibile creare directory admin reports.');
            }
            
            // Processa file
            return $this->processFilesToLocal(
                $files,
                [],  // Nessun titolo per admin reports
                $uploadDir,
                null,  // Nessun report_id
                $user_id,
                ['project_id' => $project_id, 'project_name' => $projectName],
                'admin_report'
            );
            
        } catch (Exception $e) {
            error_log("FileUploadHandler Error: " . $e->getMessage());
            return $this->createResponse(false, 'Errore durante upload file: ' . $e->getMessage());
        }
    }
    
    /**
     * Upload file generici (documents, deliverables, various)
     * 
     * @param array $files Array $_FILES
     * @param string $category Categoria: document, deliverable, various
     * @param int $project_id ID progetto
     * @param int $wp_number Numero WP (solo per deliverables)
     * @param int $activity_id ID attività (solo per deliverables)
     * @param int $user_id ID utente
     * @return array Response
     */
    public function handleGenericFiles($files, $category, $project_id, $wp_number = null, $activity_id = null, $user_id) {
        try {
            // Recupera nome progetto
            $stmt = $this->conn->prepare("SELECT name FROM projects WHERE id = ?");
            $stmt->execute([$project_id]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$project) {
                return $this->createResponse(false, 'Progetto non trovato.');
            }
            
            // Crea directory in base alla categoria
            switch ($category) {
                case 'document':
                    $uploadDir = $this->createDocumentDirectory($project['name']);
                    break;
                case 'deliverable':
                    if (!$wp_number) {
                        return $this->createResponse(false, 'Work Package richiesto per deliverable.');
                    }
                    $uploadDir = $this->createDeliverableDirectory($project['name'], $wp_number);
                    break;
                case 'various':
                    $uploadDir = $this->createVariousDirectory($project['name']);
                    break;
                default:
                    return $this->createResponse(false, 'Categoria file non valida.');
            }
            
            if (!$uploadDir) {
                return $this->createResponse(false, 'Impossibile creare directory upload.');
            }
            
            // Processa file
            return $this->processFilesToLocal(
                $files,
                [],
                $uploadDir,
                null,
                $user_id,
                ['project_id' => $project_id, 'project_name' => $project['name'], 'activity_id' => $activity_id],
                $category
            );
            
        } catch (Exception $e) {
            error_log("FileUploadHandler Error: " . $e->getMessage());
            return $this->createResponse(false, 'Errore durante upload file: ' . $e->getMessage());
        }
    }
    
    /**
     * Recupera informazioni complete dal report
     * 
     * @param int $report_id ID del report
     * @return array|false Array con info o false
     */
    private function getReportInformation($report_id) {
        $stmt = $this->conn->prepare("
            SELECT 
                ar.id as report_id,
                ar.activity_id,
                a.activity_number,
                a.name as activity_name,
                wp.id as work_package_id,
                wp.wp_number,
                wp.name as wp_name,
                p.id as project_id,
                p.name as project_name
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
     * Crea directory per report: /uploads/progetto/reports/WPX/
     */
    private function createReportDirectory($projectName, $wpNumber) {
        $projectFolder = $this->sanitizeName($projectName);
        
        // Se wp_number contiene già "WP", non aggiungerlo
        $wpFolder = (stripos($wpNumber, 'WP') === 0) ? $wpNumber : 'WP' . $wpNumber;
        
        $path = $this->base_upload_dir . $projectFolder . '/reports/' . $wpFolder . '/';
        
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                error_log("FileUploadHandler: Impossibile creare directory: $path");
                return false;
            }
        }
        
        return $path;
    }
    
    /**
     * Crea directory per admin reports: /uploads/progetto/admin_reports/organizzazione/
     */
    private function createAdminReportDirectory($projectName, $organizationName) {
        $projectFolder = $this->sanitizeName($projectName);
        $orgFolder = $this->sanitizeName($organizationName);
        $path = $this->base_upload_dir . $projectFolder . '/admin_reports/' . $orgFolder . '/';
        
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                error_log("FileUploadHandler: Impossibile creare directory: $path");
                return false;
            }
        }
        
        return $path;
    }
    
    /**
     * Crea directory per documenti: /uploads/progetto/documents/
     */
    private function createDocumentDirectory($projectName) {
        $projectFolder = $this->sanitizeName($projectName);
        $path = $this->base_upload_dir . $projectFolder . '/documents/';
        
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                return false;
            }
        }
        
        return $path;
    }
    
    /**
     * Crea directory per deliverables: /uploads/progetto/deliverables/WPX/
     */
    private function createDeliverableDirectory($projectName, $wpNumber) {
        $projectFolder = $this->sanitizeName($projectName);
        
        // Se wp_number contiene già "WP", non aggiungerlo
        $wpFolder = (stripos($wpNumber, 'WP') === 0) ? $wpNumber : 'WP' . $wpNumber;
        
        $path = $this->base_upload_dir . $projectFolder . '/deliverables/' . $wpFolder . '/';
        
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                return false;
            }
        }
        
        return $path;
    }
    
    /**
     * Crea directory per vari: /uploads/progetto/various/
     */
    private function createVariousDirectory($projectName) {
        $projectFolder = $this->sanitizeName($projectName);
        $path = $this->base_upload_dir . $projectFolder . '/various/';
        
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                return false;
            }
        }
        
        return $path;
    }
    
    /**
     * Processa tutti i file e li salva localmente
     */
    private function processFilesToLocal($files, $titles, $uploadDir, $report_id, $user_id, $info, $category) {
        $uploaded_files = [];
        $errors = [];
        
        error_log("FileUploadHandler: Processamento " . count($files['name']) . " file");
        
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                
                // Recupera titolo file se fornito
                $file_title = isset($titles[$i]) ? trim($titles[$i]) : '';
                
                // Validazione file
                $validation = $this->validateFile(
                    $files['name'][$i],
                    $files['size'][$i],
                    $files['tmp_name'][$i]
                );
                
                if (!$validation['valid']) {
                    $errors[] = "File '{$files['name'][$i]}': {$validation['message']}";
                    error_log("FileUploadHandler: Validazione fallita per {$files['name'][$i]}: {$validation['message']}");
                    continue;
                }
                
                // Genera nome file
                $originalName = $files['name'][$i];
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                
                // Nome file dipende dalla categoria
                if ($category === 'report' || $category === 'deliverable') {
                    // Per report e deliverable: num_attivita-filename
                    $activityId = $info['activity_id'];
                    $safeFilename = $activityId . '-' . $this->sanitizeName($originalName);
                } else {
                    // Per altri: timestamp-filename
                    $safeFilename = time() . '-' . $this->sanitizeName($originalName);
                }
                
                $filePath = $uploadDir . $safeFilename;
                
                error_log("FileUploadHandler: Upload file locale: $filePath");
                
                try {
                    // Sposta file nella directory
                    // Usa move_uploaded_file per file HTTP, rename per test
                    $moved = false;
                    if (is_uploaded_file($files['tmp_name'][$i])) {
                        $moved = move_uploaded_file($files['tmp_name'][$i], $filePath);
                    } else {
                        // Fallback per test: usa rename/copy
                        $moved = rename($files['tmp_name'][$i], $filePath);
                    }
                    
                    if ($moved) {
                        
                        // Path relativo per il database
                        $relativePath = str_replace($this->base_upload_dir, 'uploads/', $filePath);
                        
                        // Salva riferimento nel database
                        $dbInsert = $this->saveFileToDatabase(
                            $report_id,
                            $user_id,
                            $info['project_id'],
                            $safeFilename,
                            $originalName,
                            $file_title,
                            $relativePath,
                            $category,
                            $files['size'][$i],
                            $files['type'][$i]
                        );
                        
                        if ($dbInsert) {
                            $uploaded_files[] = $safeFilename;
                            error_log("FileUploadHandler: File salvato con successo");
                        } else {
                            $errors[] = "File '{$originalName}': salvato ma errore DB";
                            // Elimina file se DB fallisce
                            @unlink($filePath);
                        }
                        
                    } else {
                        $errors[] = "File '{$originalName}': impossibile spostare file";
                        error_log("FileUploadHandler: Impossibile spostare file {$originalName}");
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "File '{$originalName}': {$e->getMessage()}";
                    error_log("FileUploadHandler: Errore upload per {$originalName}: " . $e->getMessage());
                }
                
            } else if ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                $errors[] = "File '{$files['name'][$i]}': Upload error code {$files['error'][$i]}";
                error_log("FileUploadHandler: Upload error {$files['error'][$i]} per {$files['name'][$i]}");
            }
        }
        
        // Genera risposta finale
        if (count($uploaded_files) > 0 && count($errors) === 0) {
            return $this->createResponse(true, count($uploaded_files) . " file caricati con successo.");
        } else if (count($uploaded_files) > 0) {
            return $this->createResponse(true, count($uploaded_files) . " file caricati, ma con alcuni errori: " . implode("; ", $errors));
        } else {
            return $this->createResponse(false, "Nessun file caricato. Errori: " . implode("; ", $errors));
        }
    }
    
    /**
     * Valida un file prima dell'upload
     */
    private function validateFile($filename, $filesize, $tmpPath) {
        // Verifica estensione
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowed_extensions)) {
            return ['valid' => false, 'message' => "Estensione file non consentita (.{$extension})"];
        }
        
        // Verifica dimensione
        if ($filesize > $this->max_file_size) {
            return ['valid' => false, 'message' => "File troppo grande (max 10MB)"];
        }
        
        // Verifica file esistente
        if (!file_exists($tmpPath)) {
            return ['valid' => false, 'message' => "File temporaneo non trovato"];
        }
        
        return ['valid' => true, 'message' => 'OK'];
    }
    
    /**
     * Salva riferimento file nel database
     */
    private function saveFileToDatabase($report_id, $user_id, $project_id, $filename, $originalName, $title, $filePath, $category, $fileSize, $mimeType) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO uploaded_files (
                    report_id,
                    project_id,
                    uploaded_by,
                    filename,
                    original_filename,
                    title,
                    file_path,
                    file_category,
                    file_size,
                    file_type,
                    uploaded_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            return $stmt->execute([
                $report_id,
                $project_id,
                $user_id,
                $filename,
                $originalName,
                $title ?: null,
                $filePath,
                $category,
                $fileSize,
                $mimeType
            ]);
            
        } catch (Exception $e) {
            error_log("FileUploadHandler: Errore salvataggio DB: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sanitizza nome per filesystem
     */
    private function sanitizeName($name) {
        // Rimuovi caratteri speciali
        $name = preg_replace('/[^a-zA-Z0-9-_\s.]/', '', $name);
        // Sostituisci spazi con underscore
        $name = preg_replace('/\s+/', '_', $name);
        // Limita lunghezza
        $name = substr($name, 0, 100);
        return $name;
    }
    
    /**
     * Crea response standardizzata
     */
    private function createResponse($success, $message) {
        return [
            'success' => $success,
            'message' => $message
        ];
    }
}