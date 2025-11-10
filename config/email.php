<?php
// ===================================================================
// EMAIL CONFIGURATION
// ===================================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    private $test_mode;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        
        // Detect environment (local vs production)
        $this->test_mode = ($_SERVER['HTTP_HOST'] === 'eu-projectmanager.local' || 
                           strpos($_SERVER['HTTP_HOST'], 'localhost') !== false);
        
        if ($this->test_mode) {
            // LOCAL: Test mode - log emails to file
            $this->setupTestMode();
        } else {
            // PRODUCTION: Real SMTP (da configurare dopo)
            $this->setupProduction();
        }
    }
    
    private function setupTestMode() {
        // In test mode, non inviamo email reali
        // Le scriviamo in un file di log
        $this->mailer->SMTPDebug = 0;
    }
    
private function setupProduction() {
    // SMTP settings for production - Aruba
    $this->mailer->isSMTP();
    $this->mailer->Host = 'out.postassl.it';
    $this->mailer->SMTPAuth = true;
    $this->mailer->Username = '';
    $this->mailer->Password = '';
    $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $this->mailer->Port = 465;
    $this->mailer->SMTPDebug = 0; // Disable debug in production
}
    
    public function sendPasswordReset($to_email, $to_name, $reset_link, $token_expiry) {
        try {
            if ($this->test_mode) {
                // Test mode: log to file
                return $this->logEmailToFile($to_email, $to_name, $reset_link, $token_expiry);
            }
            
            // Production: send real email
            $this->mailer->setFrom('noreply@cooperativaparsec.it', 'EU-Monitor Parsec');
            $this->mailer->addAddress($to_email, $to_name);
            $this->mailer->Subject = 'Password Reset Request - EU-Monitor Parsec';
            $this->mailer->isHTML(true);
            
            $html_body = $this->getPasswordResetTemplate($to_name, $reset_link, $token_expiry);
            $this->mailer->Body = $html_body;
            $this->mailer->AltBody = strip_tags($html_body);
            
            $this->mailer->send();
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error sending email: ' . $e->getMessage()];
        }
    }
    
    private function logEmailToFile($to_email, $to_name, $reset_link, $token_expiry) {
        // Create logs directory if not exists
        $log_dir = __DIR__ . '/../logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $log_file = $log_dir . '/email_test.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $log_content = "\n" . str_repeat('=', 70) . "\n";
        $log_content .= "EMAIL TEST LOG - $timestamp\n";
        $log_content .= str_repeat('=', 70) . "\n";
        $log_content .= "TO: $to_name <$to_email>\n";
        $log_content .= "SUBJECT: Password Reset Request - EU Project Manager\n";
        $log_content .= "TOKEN EXPIRY: $token_expiry\n";
        $log_content .= str_repeat('-', 70) . "\n";
        $log_content .= "RESET LINK:\n$reset_link\n";
        $log_content .= str_repeat('=', 70) . "\n\n";
        
        file_put_contents($log_file, $log_content, FILE_APPEND);
        
        return [
            'success' => true, 
            'message' => 'TEST MODE: Email logged to logs/email_test.log',
            'reset_link' => $reset_link
        ];
    }
    
    private function getPasswordResetTemplate($name, $reset_link, $expiry) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #667eea;'>Password Reset Request</h2>
                <p>Hello <strong>$name</strong>,</p>
                <p>You have requested to reset your password for EU Project Manager.</p>
                <p>Click the button below to reset your password:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='$reset_link' 
                       style='background: linear-gradient(135deg, #51CACF 0%, #667eea 100%);
                              color: white;
                              padding: 12px 30px;
                              text-decoration: none;
                              border-radius: 25px;
                              display: inline-block;'>
                        Reset Password
                    </a>
                </p>
                <p>Or copy this link into your browser:</p>
                <p style='word-break: break-all; color: #666; font-size: 12px;'>$reset_link</p>
                <p style='color: #999; font-size: 12px;'>This link will expire at: <strong>$expiry</strong></p>
                <p style='color: #999; font-size: 12px;'>If you did not request this password reset, please ignore this email.</p>
                <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
                <p style='color: #999; font-size: 11px;'>
                    EU Project Manager - Cooperativa Parsec<br>
                    European Projects Management System
                </p>
            </div>
        </body>
        </html>
        ";
    }
}
?>
