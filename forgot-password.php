<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ===================================================================
// FORGOT PASSWORD PAGE
// ===================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/environment.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/email.php';
require_once 'vendor/autoload.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header('Location: pages/dashboard.php');
    exit;
}

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address';
        $message_type = 'danger';
    } else {
        $database = new Database();
        $conn = $database->connect();
        $auth = new Auth($conn);
        
        // Create reset token
        $result = $auth->createPasswordResetToken($email);
        
        if ($result['success'] && isset($result['token'])) {
            // Send email
            $emailService = new EmailService();
            
            // Build reset link
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $reset_link = $protocol . '://' . $host . '/reset-password.php?token=' . $result['token'];
            
            $email_result = $emailService->sendPasswordReset(
                $result['user']['email'],
                $result['user']['full_name'],
                $reset_link,
                date('d/m/Y H:i', strtotime($result['expires_at']))
            );
            
            if ($email_result['success']) {
                $message = 'If this email exists in our system, you will receive a password reset link shortly.';
                $message_type = 'success';
                
                // In test mode, show the link
                if (isset($email_result['reset_link'])) {
                    $message .= '<br><br><strong>TEST MODE:</strong> Check logs/email_test.log or use this link:<br>';
                    $message .= '<a href="' . $email_result['reset_link'] . '" target="_blank">' . $email_result['reset_link'] . '</a>';
                }
            } else {
                $message = 'An error occurred. Please try again later.';
                $message_type = 'danger';
            }
        } else {
            // Always show success message (security best practice)
            $message = 'If this email exists in our system, you will receive a password reset link shortly.';
            $message_type = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="apple-touch-icon" sizes="76x76" href="assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Forgot Password - EU Project Manager</title>
    <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no' name='viewport' />
    
    <!-- Fonts and icons -->
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700,200" rel="stylesheet" />
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css" rel="stylesheet">
    
    <!-- CSS Files -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" />
    <link href="assets/css/paper-dashboard.css?v=2.0.1" rel="stylesheet" />
    
    <style>
        .login-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 40px;
            width: 400px;
            max-width: 90vw;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h3 {
            color: #333;
            font-weight: 300;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-control {
            height: 45px;
            border-radius: 25px;
            border: 1px solid #ddd;
            padding: 0 20px;
            font-size: 14px;
        }
        
        .form-control:focus {
            border-color: #51CACF;
            box-shadow: 0 0 0 0.2rem rgba(81, 202, 207, 0.25);
        }
        
        .btn-primary {
            width: 100%;
            height: 45px;
            border-radius: 25px;
            background: linear-gradient(135deg, #51CACF 0%, #667eea 100%);
            border: none;
            color: white;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(81, 202, 207, 0.4);
            color: white;
        }
        
        .btn-link {
            color: #667eea;
            font-size: 14px;
        }
        
        .btn-link:hover {
            color: #51CACF;
            text-decoration: none;
        }
        
        .alert {
            border-radius: 15px;
            font-size: 14px;
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>

<body class="login-page">
    <div class="login-card">
        <div class="login-header">
            <h3><i class="nc-icon nc-key-25 text-info"></i> Forgot Password</h3>
            <p>Enter your email to reset your password</p>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>" role="alert">
            <?= $message ?>
        </div>
        <?php endif; ?>
        
        <?php if ($message_type !== 'success'): ?>
        <form method="POST" action="">
            <div class="form-group">
                <input type="email" 
                       class="form-control" 
                       name="email" 
                       placeholder="Your email address" 
                       required 
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="nc-icon nc-email-85"></i>
                Send Reset Link
            </button>
        </form>
        <?php endif; ?>
        
        <div class="back-to-login">
            <a href="login.php" class="btn-link">
                <i class="nc-icon nc-minimal-left"></i>
                Back to Login
            </a>
        </div>
    </div>

    <!-- Core JS Files -->
    <script src="assets/js/core/jquery.min.js"></script>
    <script src="assets/js/core/popper.min.js"></script>
    <script src="assets/js/core/bootstrap.min.js"></script>
</body>
</html>