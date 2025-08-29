<?php
// La password in chiaro
$password = 'partner123';

// Crea un hash bcrypt (di default usa cost = 10)
$hash = password_hash($password, PASSWORD_BCRYPT);

// Stampa il risultato
echo "Hash della password: " . $hash . "\n";
?>
