<?php
// ===================================================================
// ENVIRONMENT CONFIGURATION
// ===================================================================

// Rileva se siamo in locale o in produzione
define('IS_LOCAL', $_SERVER['HTTP_HOST'] === 'eu-projectmanager.local');

// Include il file database appropriato
if (IS_LOCAL) {
    require_once __DIR__ . '/database_local.php';
} else {
    require_once __DIR__ . '/database.php';
}

// Configurazioni specifiche per ambiente
if (IS_LOCAL) {
    // Sviluppo locale
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    // Produzione
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
}
?>
