<?php
/**
 * Test Script - Upload File su Filesystem Locale
 * 
 * Testa il FileUploadHandler con salvataggio su filesystem locale
 * Verifica struttura cartelle e salvataggio database
 * 
 * ELIMINA questo file dopo il test
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test Upload File Report su Filesystem Locale</h1>";
echo "<pre>";

// Include header (contiene autoload e classi necessarie)
require_once __DIR__ . '/includes/header.php';

try {
    echo "🔍 Step 1: Verifica connessione database...\n";
    
    // Connessione database
    $database = new Database();
    $db = $database->connect();
    
    if (!$db) {
        die("❌ Database non connesso\n");
    }
    
    echo "✅ Database connesso\n\n";
    
    // Include FileUploadHandler
    require_once __DIR__ . '/includes/classes/FileUploadHandler.php';
    
    // Recupera un report di test dal database
    echo "🔍 Step 2: Ricerca report disponibili...\n";
    $stmt = $db->prepare("
        SELECT 
            ar.id as report_id,
            ar.activity_id,
            a.name as activity_name,
            wp.wp_number,
            p.id as project_id,
            p.name as project_name
        FROM activity_reports ar
        JOIN activities a ON ar.activity_id = a.id
        JOIN work_packages wp ON a.work_package_id = wp.id
        JOIN projects p ON wp.project_id = p.id
        ORDER BY ar.id DESC
        LIMIT 5
    ");
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($reports)) {
        die("❌ Nessun report trovato nel database. Crea un report prima di testare.\n");
    }
    
    echo "✅ Trovati " . count($reports) . " report recenti:\n";
    foreach ($reports as $report) {
        echo "   - Report #{$report['report_id']} - {$report['project_name']} / WP{$report['wp_number']} / {$report['activity_name']}\n";
    }
    
    // Usa il primo report per il test
    $test_report = $reports[0];
    $report_id = $test_report['report_id'];
    
    echo "\n📋 Userò il report #{$report_id} per il test\n";
    echo "   Progetto: {$test_report['project_name']}\n";
    echo "   WP: WP{$test_report['wp_number']}\n";
    echo "   Activity: {$test_report['activity_name']}\n";
    echo "   Activity ID: {$test_report['activity_id']}\n\n";
    
    // Crea un file di test
    echo "🔍 Step 3: Creazione file di test...\n";
    $test_content = "Questo è un file di test creato il " . date('Y-m-d H:i:s') . "\n";
    $test_content .= "Report ID: {$report_id}\n";
    $test_content .= "Test upload filesystem locale\n";
    $test_content .= "Progetto: {$test_report['project_name']}\n";
    $test_content .= "WP: {$test_report['wp_number']}\n";
    
    $temp_file = sys_get_temp_dir() . '/test_upload_' . time() . '.txt';
    file_put_contents($temp_file, $test_content);
    
    if (!file_exists($temp_file)) {
        die("❌ Impossibile creare file di test\n");
    }
    
    echo "✅ File di test creato: $temp_file\n";
    echo "   Dimensione: " . filesize($temp_file) . " bytes\n\n";
    
    // Verifica directory uploads
    echo "🔍 Step 4: Verifica directory uploads...\n";
    $uploads_dir = __DIR__ . '/uploads/';
    if (!is_dir($uploads_dir)) {
        echo "⚠️  Directory uploads non esiste, verrà creata automaticamente\n";
    } else {
        echo "✅ Directory uploads esistente: $uploads_dir\n";
    }
    echo "\n";
    
    // Simula array $_FILES
    $files = [
        'name' => ['test_report_file.txt'],
        'type' => ['text/plain'],
        'tmp_name' => [$temp_file],
        'error' => [UPLOAD_ERR_OK],
        'size' => [filesize($temp_file)]
    ];
    
    // Titolo file opzionale
    $titles = ['File di test per report - Filesystem Locale'];
    
    // User ID di test (usa 1 o un ID valido)
    $user_id = 1;
    
    // Inizializza FileUploadHandler
    echo "🔍 Step 5: Inizializzazione FileUploadHandler...\n";
    $fileHandler = new FileUploadHandler($db);
    echo "✅ FileUploadHandler inizializzato\n\n";
    
    // Esegui upload
    echo "🔍 Step 6: Upload file su filesystem locale...\n";
    echo "   Struttura attesa: uploads/{$test_report['project_name']}/reports/WP{$test_report['wp_number']}/{$test_report['activity_id']}-test_report_file.txt\n\n";
    
    $result = $fileHandler->handleReportFiles($files, $titles, $report_id, $user_id);
    
    // Mostra risultato
    echo "============================================\n";
    if ($result['success']) {
        echo "✅ UPLOAD COMPLETATO CON SUCCESSO!\n";
    } else {
        echo "❌ UPLOAD FALLITO\n";
    }
    echo "============================================\n\n";
    
    echo "Messaggio: {$result['message']}\n\n";
    
    if ($result['success']) {
        // Verifica nel database
        echo "🔍 Step 7: Verifica salvataggio database...\n";
        $verify_stmt = $db->prepare("
            SELECT 
                uf.id,
                uf.filename,
                uf.original_filename,
                uf.title,
                uf.file_path,
                uf.file_category,
                uf.file_size,
                uf.file_type,
                uf.uploaded_at
            FROM uploaded_files uf
            WHERE uf.report_id = ?
            ORDER BY uf.id DESC
            LIMIT 1
        ");
        $verify_stmt->execute([$report_id]);
        $saved_file = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($saved_file) {
            echo "✅ File trovato nel database:\n";
            echo "   ID: {$saved_file['id']}\n";
            echo "   Nome file: {$saved_file['filename']}\n";
            echo "   Nome originale: {$saved_file['original_filename']}\n";
            echo "   Titolo: {$saved_file['title']}\n";
            echo "   Path: {$saved_file['file_path']}\n";
            echo "   Categoria: {$saved_file['file_category']}\n";
            echo "   Dimensione: {$saved_file['file_size']} bytes\n";
            echo "   Tipo: {$saved_file['file_type']}\n";
            echo "   Data upload: {$saved_file['uploaded_at']}\n\n";
            
            // Verifica esistenza file fisico
            echo "🔍 Step 8: Verifica esistenza file fisico...\n";
            $full_path = __DIR__ . '/' . $saved_file['file_path'];
            
            if (file_exists($full_path)) {
                echo "✅ File trovato sul filesystem: $full_path\n";
                echo "   Dimensione effettiva: " . filesize($full_path) . " bytes\n";
                echo "   Leggibile: " . (is_readable($full_path) ? 'Sì' : 'No') . "\n\n";
                
                // Mostra contenuto
                echo "📄 Contenuto file:\n";
                echo "---\n";
                echo file_get_contents($full_path);
                echo "\n---\n\n";
            } else {
                echo "❌ File NON trovato sul filesystem: $full_path\n\n";
            }
            
            // Verifica struttura cartelle
            echo "📂 Verifica struttura cartelle:\n";
            $project_folder = preg_replace('/[^a-zA-Z0-9-_\s.]/', '', $test_report['project_name']);
            $project_folder = preg_replace('/\s+/', '_', $project_folder);
            $expected_structure = "uploads/{$project_folder}/reports/WP{$test_report['wp_number']}/";
            
            echo "   Struttura attesa: $expected_structure\n";
            $full_dir = __DIR__ . '/' . $expected_structure;
            
            if (is_dir($full_dir)) {
                echo "   ✅ Directory esiste: $full_dir\n";
                
                // Lista file nella directory
                $files_in_dir = scandir($full_dir);
                $files_in_dir = array_diff($files_in_dir, ['.', '..']);
                
                if (count($files_in_dir) > 0) {
                    echo "   File nella directory:\n";
                    foreach ($files_in_dir as $file) {
                        echo "      - $file\n";
                    }
                }
            } else {
                echo "   ❌ Directory NON esiste: $full_dir\n";
            }
            
        } else {
            echo "⚠️  File non trovato nel database (ma upload potrebbe essere riuscito)\n";
        }
    }
    
    // Cleanup file temporaneo
    if (file_exists($temp_file)) {
        unlink($temp_file);
        echo "\n🧹 File temporaneo eliminato\n";
    }
    
    echo "\n============================================\n";
    echo "TEST COMPLETATO\n";
    echo "============================================\n\n";
    
    if ($result['success']) {
        echo "✅ Il sistema di upload su filesystem locale funziona correttamente!\n\n";
        echo "Prossimi passi:\n";
        echo "1. Verifica manualmente la cartella uploads/ sul server\n";
        echo "2. Testa creando un vero report con allegati dal portale\n";
        echo "3. Elimina questo file test_upload_local.php\n";
    } else {
        echo "⚠️  Controlla gli errori sopra e risolvi i problemi\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERRORE: " . $e->getMessage() . "\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
?>