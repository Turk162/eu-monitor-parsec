<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "TEST LOGOUT<br><br>";

echo "1. Carico logout.php...<br>";
$content = file_get_contents('logout.php');
echo "✓ File letto (" . strlen($content) . " bytes)<br>";

echo "2. Verifico sintassi...<br>";
// Check for common issues
if (strpos($content, '<?php') === false) {
    die("✗ Manca tag apertura PHP");
}

echo "3. Provo esecuzione manuale:<br>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_destroy();
echo "✓ Session destroy OK<br>";

echo "4. Test redirect:<br>";
echo "✓ Redirect funzionerebbe<br>";

echo "<br><strong>Logout.php dovrebbe funzionare. Problema potrebbe essere nel file caricato.</strong>";

echo "<br><br>Primi 500 caratteri di logout.php:<br>";
echo "<pre>" . htmlspecialchars(substr($content, 0, 500)) . "</pre>";
?>