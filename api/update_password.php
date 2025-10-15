<?php
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$old_password = $_POST['old_password'];
$new_password = $_POST['new_password'];

if (empty($old_password) || empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'Compila tutti i campi.']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->connect();

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($old_password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'La vecchia password non è corretta.']);
        exit;
    }

    $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$new_password_hashed, $user_id]);

    echo json_encode(['success' => true, 'message' => 'Password aggiornata con successo.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Errore del database: ' . $e->getMessage()]);
}
