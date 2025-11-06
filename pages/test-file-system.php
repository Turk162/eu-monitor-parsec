<?php
/**
 * TEST SCRIPT - File Upload/Delete System
 * 
 * Script per testare il sistema completo di upload e delete file
 * con tracciamento WP e Activity
 * 
 * ATTENZIONE: Questo è uno script di test. Non lasciarlo in produzione.
 * 
 * @package EU Project Manager
 * @version 1.0
 */

// Configurazione
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/header.php';

// Verifica autenticazione
if (!$auth->isLoggedIn()) {
    die("Devi essere autenticato per eseguire questo test.");
}

$user_id = getUserId();
$user_role = getUserRole();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test File System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h2 { color: #666; margin-top: 30px; }
        .test-section { background: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #007bff; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 3px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table th, table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        table th { background: #007bff; color: white; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🧪 File Upload/Delete System - Test Suite</h1>
        <p><strong>User:</strong> {$user_id} | <strong>Role:</strong> {$user_role} | <strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
";

// ===================================================================
// TEST 1: Verifica Struttura Database
// ===================================================================
echo "<div class='test-section'>
    <h2>📋 Test 1: Verifica Struttura Database</h2>";

try {
    // Verifica tabella uploaded_files
    $stmt = $conn->query("DESCRIBE uploaded_files");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $required_columns = ['work_package_id', 'activity_id'];
    $found_columns = array_column($columns, 'Field');
    
    echo "<p class='info'>Colonne nella tabella uploaded_files:</p>";
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $col) {
        $highlight = in_array($col['Field'], $required_columns) ? "style='background:#d4edda'" : "";
        echo "<tr $highlight><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
    }
    echo "</table>";
    
    // Verifica presenza campi richiesti
    $missing = array_diff($required_columns, $found_columns);
    if (empty($missing)) {
        echo "<p class='success'>✓ Tutti i campi richiesti sono presenti</p>";
    } else {
        echo "<p class='error'>✗ Campi mancanti: " . implode(', ', $missing) . "</p>";
        echo "<p class='warning'>⚠ Esegui lo script SQL: add_wp_activity_to_uploaded_files.sql</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Errore: " . $e->getMessage() . "</p>";
}

echo "</div>";

// ===================================================================
// TEST 2: Verifica FileUploadHandler Class
// ===================================================================
echo "<div class='test-section'>
    <h2>📦 Test 2: Verifica FileUploadHandler Class</h2>";

$handler_path = __DIR__ . '/../includes/classes/FileUploadHandler.php';
if (file_exists($handler_path)) {
    echo "<p class='success'>✓ FileUploadHandler.php trovato</p>";
    
    require_once $handler_path;
    
    if (class_exists('FileUploadHandler')) {
        echo "<p class='success'>✓ Classe FileUploadHandler caricata</p>";
        
        // Verifica metodi
        $handler = new FileUploadHandler($conn);
        $methods = get_class_methods($handler);
        
        $required_methods = ['handleGenericFiles', 'deleteFile', 'checkDeletePermission'];
        
        echo "<p class='info'>Metodi disponibili:</p><ul>";
        foreach ($required_methods as $method) {
            if (in_array($method, $methods)) {
                echo "<li class='success'>✓ $method</li>";
            } else {
                echo "<li class='error'>✗ $method (mancante)</li>";
            }
        }
        echo "</ul>";
        
    } else {
        echo "<p class='error'>✗ Classe FileUploadHandler non trovata</p>";
    }
} else {
    echo "<p class='error'>✗ File FileUploadHandler.php non trovato</p>";
    echo "<p>Path cercato: $handler_path</p>";
}

echo "</div>";

// ===================================================================
// TEST 3: Verifica API Endpoint delete_file.php
// ===================================================================
echo "<div class='test-section'>
    <h2>🔌 Test 3: Verifica API Endpoint</h2>";

$api_path = __DIR__ . '/../api/delete_file.php';
if (file_exists($api_path)) {
    echo "<p class='success'>✓ API delete_file.php trovato</p>";
    
    // Leggi contenuto per verificare presenza controllo permessi
    $content = file_get_contents($api_path);
    
    if (strpos($content, 'user_role') !== false && strpos($content, 'can_delete') !== false) {
        echo "<p class='success'>✓ Controllo permessi presente nel codice</p>";
    } else {
        echo "<p class='warning'>⚠ Controllo permessi potrebbe non essere implementato</p>";
    }
    
} else {
    echo "<p class='error'>✗ API delete_file.php non trovato</p>";
    echo "<p>Path cercato: $api_path</p>";
}

echo "</div>";

// ===================================================================
// TEST 4: Verifica JavaScript file-delete.js
// ===================================================================
echo "<div class='test-section'>
    <h2>📜 Test 4: Verifica JavaScript</h2>";

$js_path = __DIR__ . '/../assets/js/file-delete.js';
if (file_exists($js_path)) {
    echo "<p class='success'>✓ file-delete.js trovato</p>";
    
    $content = file_get_contents($js_path);
    
    if (strpos($content, 'delete-file-btn') !== false) {
        echo "<p class='success'>✓ Handler per pulsante delete presente</p>";
    }
    
    if (strpos($content, 'action: \'delete_file\'') !== false) {
        echo "<p class='success'>✓ Parametro action corretto</p>";
    }
    
} else {
    echo "<p class='warning'>⚠ file-delete.js non trovato (opzionale per test backend)</p>";
}

echo "</div>";

// ===================================================================
// TEST 5: Test Recupero File da Database
// ===================================================================
echo "<div class='test-section'>
    <h2>💾 Test 5: Test Recupero File da Database</h2>";

try {
    // Cerca un progetto di test
    $stmt = $conn->query("SELECT id, name FROM projects LIMIT 1");
    $test_project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test_project) {
        echo "<p class='info'>Progetto test: {$test_project['name']} (ID: {$test_project['id']})</p>";
        
        // Query completa come in project-files.php
        $stmt = $conn->prepare("
            SELECT 
                uf.id,
                uf.filename,
                uf.original_filename,
                uf.file_category,
                uf.work_package_id,
                uf.activity_id,
                wp_direct.wp_number,
                a_direct.activity_number
            FROM uploaded_files uf
            LEFT JOIN work_packages wp_direct ON uf.work_package_id = wp_direct.id
            LEFT JOIN activities a_direct ON uf.activity_id = a_direct.id
            WHERE uf.project_id = ?
            LIMIT 5
        ");
        $stmt->execute([$test_project['id']]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($files) > 0) {
            echo "<p class='success'>✓ Trovati " . count($files) . " file</p>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Filename</th><th>Category</th><th>WP ID</th><th>WP Number</th><th>Activity ID</th><th>Activity Number</th></tr>";
            foreach ($files as $file) {
                echo "<tr>";
                echo "<td>{$file['id']}</td>";
                echo "<td>{$file['original_filename']}</td>";
                echo "<td>{$file['file_category']}</td>";
                echo "<td>" . ($file['work_package_id'] ?: '<em>null</em>') . "</td>";
                echo "<td>" . ($file['wp_number'] ?: '<em>null</em>') . "</td>";
                echo "<td>" . ($file['activity_id'] ?: '<em>null</em>') . "</td>";
                echo "<td>" . ($file['activity_number'] ?: '<em>null</em>') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠ Nessun file trovato nel progetto (normale se progetto vuoto)</p>";
        }
        
    } else {
        echo "<p class='warning'>⚠ Nessun progetto nel database</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Errore: " . $e->getMessage() . "</p>";
}

echo "</div>";

// ===================================================================
// TEST 6: Test Permessi Delete
// ===================================================================
echo "<div class='test-section'>
    <h2>🔐 Test 6: Test Permessi Delete</h2>";

echo "<p class='info'>Il tuo ruolo è: <strong>$user_role</strong></p>";

$can_delete_all = in_array($user_role, ['super_admin', 'admin', 'coordinator']);
$can_delete_own = ($user_role === 'partner');

if ($can_delete_all) {
    echo "<p class='success'>✓ Puoi eliminare TUTTI i file (admin/coordinator)</p>";
} elseif ($can_delete_own) {
    echo "<p class='success'>✓ Puoi eliminare SOLO i tuoi file (partner)</p>";
} else {
    echo "<p class='error'>✗ Non hai permessi per eliminare file</p>";
}

// Simula controllo permessi per un file
try {
    $stmt = $conn->query("SELECT id, uploaded_by FROM uploaded_files LIMIT 1");
    $test_file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test_file) {
        $file_owner = $test_file['uploaded_by'];
        $is_owner = ($file_owner == $user_id);
        
        echo "<p class='info'>File test: ID {$test_file['id']}, Owner: $file_owner</p>";
        
        if ($can_delete_all) {
            echo "<p class='success'>✓ Puoi eliminare questo file (sei admin/coordinator)</p>";
        } elseif ($is_owner) {
            echo "<p class='success'>✓ Puoi eliminare questo file (sei il proprietario)</p>";
        } else {
            echo "<p class='error'>✗ NON puoi eliminare questo file (non sei il proprietario)</p>";
        }
    }
} catch (Exception $e) {
    echo "<p class='warning'>⚠ Nessun file per test permessi</p>";
}

echo "</div>";

// ===================================================================
// TEST 7: Test Directory Upload
// ===================================================================
echo "<div class='test-section'>
    <h2>📁 Test 7: Verifica Directory Upload</h2>";

$upload_base = __DIR__ . '/../uploads/';

if (is_dir($upload_base)) {
    echo "<p class='success'>✓ Directory uploads/ esiste</p>";
    echo "<p class='info'>Path: " . realpath($upload_base) . "</p>";
    
    if (is_writable($upload_base)) {
        echo "<p class='success'>✓ Directory uploads/ è scrivibile</p>";
    } else {
        echo "<p class='error'>✗ Directory uploads/ NON è scrivibile</p>";
        echo "<p class='warning'>⚠ Esegui: chmod 755 {$upload_base}</p>";
    }
    
    // Elenca sottocartelle progetti
    $dirs = glob($upload_base . '*', GLOB_ONLYDIR);
    if (count($dirs) > 0) {
        echo "<p class='info'>Progetti con file caricati (" . count($dirs) . "):</p><ul>";
        foreach (array_slice($dirs, 0, 5) as $dir) {
            $project_name = basename($dir);
            echo "<li>$project_name</li>";
        }
        if (count($dirs) > 5) {
            echo "<li><em>... e altri " . (count($dirs) - 5) . " progetti</em></li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='warning'>⚠ Nessun progetto con file caricati</p>";
    }
    
} else {
    echo "<p class='error'>✗ Directory uploads/ NON esiste</p>";
    echo "<p class='warning'>⚠ Crea la directory: mkdir {$upload_base}</p>";
}

echo "</div>";

// ===================================================================
// RIEPILOGO FINALE
// ===================================================================
echo "<div class='test-section' style='border-left-color: #28a745;'>
    <h2>✅ Riepilogo Test</h2>";

$tests_passed = 0;
$tests_total = 7;

echo "<p><strong>Test completati:</strong> $tests_total</p>";
echo "<p>Controlla i risultati sopra per eventuali errori o warning.</p>";

echo "<h3>Prossimi Passi:</h3>
<ol>
    <li>Correggi eventuali errori evidenziati in rosso</li>
    <li>Esegui gli script SQL mancanti</li>
    <li>Copia i file mancanti nelle directory corrette</li>
    <li>Testa l'upload di un deliverable con WP e Activity</li>
    <li>Testa l'eliminazione di un file</li>
</ol>";

echo "<h3>Link Utili:</h3>
<ul>
    <li><a href='projects.php' class='btn'>Vai a Projects</a></li>
    <li><a href='project-files-upload.php?project_id=1' class='btn'>Test Upload (Project 1)</a></li>
</ul>";

echo "</div>";

echo "
    </div>
</body>
</html>";
?>