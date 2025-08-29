<?php
// ===================================================================
// DATABASE CONFIGURATION - AUTO-DETECT ENVIRONMENT
// ===================================================================

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset = 'utf8mb4';
    private $conn;

    public function __construct() {
        // AUTO-DETECT ENVIRONMENT
        $isLocal = $this->isLocalEnvironment();
        
        if ($isLocal) {
            // CONFIGURAZIONE LOCALE
            $this->host = 'localhost';
            $this->db_name = 'YOUR_DB_NAME';
            $this->username = 'YOUR_DB_USERNAME';
            $this->password = 'YOUR_DB_PASSWORD';
        } else {
            // CONFIGURAZIONE PRODUZIONE - 
            $this->host = 'localhost';
            $this->db_name = 'YOUR_DB_NAME';
            $this->username = 'YOUR_DB_USERNAME';
            $this->password = 'YOUR_DB_PASSWORD';
        }
    }

    private function isLocalEnvironment() {
        // Controlla diversi indicatori di ambiente locale
        return (
            // Controlla hostname
            (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'YOUR_HOST') ||
            // Controlla se siamo su localhost
            (isset($_SERVER['SERVER_NAME']) && in_array($_SERVER['SERVER_NAME'], ['localhost', ''])) ||
            // Controlla variabile d'ambiente personalizzata
            (getenv('APP_ENV') === 'local') ||
            // Controlla se esiste file marker di sviluppo
            file_exists(__DIR__ . '/.env.local')
        );
    }

    public function connect() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
            
        } catch(PDOException $e) {
            $env = $this->isLocalEnvironment() ? 'LOCAL' : 'PRODUCTION';
            error_log("Database connection error [$env]: " . $e->getMessage());
            die("Errore di connessione al database. Controllare le credenziali.");
        }
        return $this->conn;
    }

    public function testConnection() {
        try {
            $conn = $this->connect();
            if ($conn) {
                $stmt = $conn->query("SELECT 1");
                $env = $this->isLocalEnvironment() ? 'locale' : 'produzione';
                return ['success' => true, 'message' => "Connessione riuscita ($env)"];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Errore: ' . $e->getMessage()];
        }
    }
    
    // Funzione per importare lo schema del database
    public function importSchema($sqlFile) {
        try {
            $conn = $this->connect();
            $sql = file_get_contents($sqlFile);
            
            // Esegui le query una per una
            $queries = explode(';', $sql);
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    $conn->exec($query);
                }
            }
            
            return ['success' => true, 'message' => 'Schema importato con successo'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Errore importazione: ' . $e->getMessage()];
        }
    }

    // Debug info
    public function getEnvironmentInfo() {
        return [
            'is_local' => $this->isLocalEnvironment(),
            'host' => $this->host,
            'database' => $this->db_name,
            'username' => $this->username
        ];
    }
}

// Test rapido della connessione (per debug)
if (isset($_GET['test_db'])) {
    header('Content-Type: application/json');
    $db = new Database();
    $result = $db->testConnection();
    echo json_encode($result);
    exit;
}

// Debug environment (per sviluppo)
if (isset($_GET['debug_env'])) {
    header('Content-Type: application/json');
    $db = new Database();
    echo json_encode($db->getEnvironmentInfo());
    exit;
}
?>
