<?php
$to = "guidoricci.lavoro@gmail.com"; // Sostituisci con la tua email
$subject = "Test Email from EU Project Manager CLI";
$message = "This is a test email sent from the EU Project Manager application via PHP's mail() function.";
$headers = "From: guidoricci.lavoro@gmail.com\r\n";
$headers .= "Reply-To: guidoricci.lavoro@gmail.com\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

if (mail($to, $subject, $message, $headers)) {
    echo "Email sent successfully to $to\n";
} else {
    echo "Email sending failed.\n";
    // Mostra l'ultimo errore PHP, se disponibile
    $error = error_get_last();
    if ($error) {
        echo "Error details: " . $error['message'] . "\n";
    }
}
?>