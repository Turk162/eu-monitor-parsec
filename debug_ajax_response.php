<?php
// debug_ajax_response.php

session_start();

// Configurazione (sostituisci con dati reali dal tuo DB)
$activity_id = 1; 
$new_status  = 'completed'; 

$post_data = [
    'action'          => 'update_status',
    'activity_id'     => $activity_id,
    'status'          => $new_status,
    'update_activity' => 1
];

// URL del tuo sito
$url = 'http://eu-projectmanager.local/pages/activities.php';

// File per salvare i cookie
$cookieFile = __DIR__ . '/cookies.txt';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_HEADER, true);

// Gestione cookie (simula browser loggato)
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

// Timeout
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

// Segui i redirect (es. verso login.php)
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "Errore CURL: " . curl_error($ch);
} else {
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "HTTP Status: " . $http_code . "\n\n";
    echo "Risposta:\n" . $response;
}

curl_close($ch);
