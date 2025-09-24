<?php
// Test connessione database
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test Connessione Database</h2>";

// Test connessione diretta
echo "<h3>1. Test Connessione Diretta</h3>";
try {
    $dsn = "mysql:host=localhost;dbname=eu_projectmanager;charset=utf8mb4";
    $pdo = new PDO($dsn, 'eu_user', 'eu_password123', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $stmt = $pdo->query("SELECT VERSION() as version");
    $result = $stmt->fetch();
    
    echo "<p style='color: green;'>✅ Connessione diretta riuscita!</p>";
    echo "<p>Versione MariaDB: " . $result['version'] . "</p>";
    
    // Test tabelle
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll();
    echo "<p>Tabelle trovate: " . count($tables) . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Errore connessione diretta: " . $e->getMessage() . "</p>";
}

// Test con la classe Database
echo "<h3>2. Test con Classe Database</h3>";
try {
    require_once __DIR__ . '/config/database.php';
    
    $database = new Database();
    $info = $database->getEnvironmentInfo();
    
    echo "<p>Ambiente rilevato:</p>";
    echo "<pre>" . print_r($info, true) . "</pre>";
    
    $result = $database->testConnection();
    if ($result['success']) {
        echo "<p style='color: green;'>✅ " . $result['message'] . "</p>";
    } else {
        echo "<p style='color: red;'>❌ " . $result['message'] . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Errore classe Database: " . $e->getMessage() . "</p>";
}

// Test environment detection
echo "<h3>3. Test Rilevamento Ambiente</h3>";
echo "<p>HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'non definito') . "</p>";
echo "<p>SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'non definito') . "</p>";
echo "<p>File .env.local esiste: " . (file_exists(__DIR__ . '/.env.local') ? 'Sì' : 'No') . "</p>";

// Test utenti database (se connessione funziona)
echo "<h3>4. Test Utenti nel Database</h3>";
try {
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT id, username, email, full_name, role FROM users LIMIT 3");
        $users = $stmt->fetchAll();
        
        echo "<p>Utenti trovati: " . count($users) . "</p>";
        foreach ($users as $user) {
            echo "<p>- " . $user['username'] . " (" . $user['role'] . ")</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: orange;'>⚠️ Tabella users non trovata o vuota: " . $e->getMessage() . "</p>";
}
?>