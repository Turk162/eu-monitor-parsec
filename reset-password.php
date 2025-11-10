<?php
// ===================================================================
// RESET PASSWORD PAGE
// ===================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/environment.php';
require_once 'config/database.php';
require_once 'config/auth.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header('Location: pages/dashboard.php');
    exit;
}

$database = new Database();
$conn = $database->connect();
$auth = new Auth($conn);

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$message = '';
$message_type = '';
$token_valid = false;
$user_info = null;

// Verify token on page load
if (!empty($token)) {
    $verification = $auth->verifyPasswordResetToken($token);
    if ($verification['success']) {
        $token_valid = true;
        $user_info = $verification;
    } else {
        $message = 'This password reset link is invalid or has expired.';
        $message_type = 'danger';
    }
} else {
    $message = 'No reset token provided.';
    $message_type = 'danger';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($new_password) || empty($confirm_password)) {
        $message = 'Please fill in all fields';
        $message_type = 'danger';
    } elseif (strlen($new_password) < 8) {
        $message = 'Password must be at least 8 characters long';
        $message_type = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Passwords do not match';
        $message_type = 'danger';
    } else {
        // Reset password
        $result = $auth->resetPasswordWithToken($token, $new_password);
        
        if ($result['success']) {
            $message = 'Password reset successfully! You can now login with your new password.';
            $message_type = 'success';
            $token_valid = false; // Hide form after successful reset
        } else {
            $message = $result['message'];
            $message_type = 'danger';
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
    <title>Reset Password - EU Project Manager</title>
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
        
        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .user-info strong {
            color: #667eea;
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
        
        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 10px;
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
            <h3><i class="nc-icon nc-refresh-69 text-info"></i> Reset Password</h3>
            <p>Enter your new password</p>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>" role="alert">
            <?= $message ?>
        </div>
        <?php endif; ?>
        
        <?php if ($token_valid && $user_info): ?>
        <div class="user-info">
            <small>Resetting password for:</small><br>
            <strong><?= htmlspecialchars($user_info['full_name']) ?></strong><br>
            <small><?= htmlspecialchars($user_info['email']) ?></small>
        </div>
        
        <form method="POST" action="" id="resetForm">
            <div class="form-group">
                <input type="password" 
                       class="form-control" 
                       name="new_password" 
                       id="new_password"
                       placeholder="New password" 
                       required
                       minlength="8">
            </div>
            
            <div class="form-group">
                <input type="password" 
                       class="form-control" 
                       name="confirm_password" 
                       id="confirm_password"
                       placeholder="Confirm new password" 
                       required
                       minlength="8">
            </div>
            
            <div class="password-requirements">
                <i class="nc-icon nc-alert-circle-i"></i>
                Password must be at least 8 characters long
            </div>
            
            <button type="submit" class="btn btn-primary" style="margin-top: 20px;">
                <i class="nc-icon nc-check-2"></i>
                Reset Password
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
    
    <script>
        // Client-side validation
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
        });
    </script>
</body>
</html>