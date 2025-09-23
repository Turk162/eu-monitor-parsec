<?php
// ===================================================================
// LOGIN PAGE - ENGLISH VERSION
// ===================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include environment to get DB config and error reporting
require_once 'config/environment.php'; 
// Auth class definition
require_once 'config/auth.php'; 

// If already logged in, redirect to dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header('Location: pages/dashboard.php');
    exit;
}

$error_message = '';

// Establish database connection
$database = new Database();
$conn = $database->connect();

// Handle login form
if ($_POST) {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        // Instantiate Auth with the database connection
        $auth = new Auth($conn); 
        $result = $auth->login($_POST['username'], $_POST['password']);
        
        if ($result['success']) {
            header('Location: pages/dashboard.php');
            exit;
        } else {
            $error_message = $result['message'];
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
    <title>Login - EU Project Manager</title>
    <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no' name='viewport' />
    
    <!-- Fonts and icons -->
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700,200" rel="stylesheet" />
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css" rel="stylesheet">
    
    <!-- CSS Files -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" />
    <link href="assets/css/paper-dashboard.css?v=2.0.1" rel="stylesheet" />
    <link href="../assets/css/custom.css" rel="stylesheet" />
    
    <!-- Custom Login Styles -->
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
        
        .login-header h2 {
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
        
        .btn-login {
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
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(81, 202, 207, 0.4);
            color: white;
        }
        
        .alert {
            border-radius: 15px;
            font-size: 14px;
        }
        
        
    </style>
</head>

<body class="login-page">
    <div class="login-card">
        <div class="login-header">
            <h3><i class="nc-icon nc-badge text-info"></i> EU Project Manager <br> Parsec</h3>
            <p>European Projects Management</p>
        </div>
        
        <?php if ($error_message): ?>
        <div class="alert alert-danger" role="alert">
            <i class="nc-icon nc-simple-remove"></i>
            <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <input type="text" 
                       class="form-control" 
                       name="username" 
                       placeholder="Username or Email" 
                       required 
                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
            </div>
            
            <div class="form-group">
                <input type="password" 
                       class="form-control" 
                       name="password" 
                       placeholder="Password" 
                       required>
            </div>
            
            <button type="submit" class="btn btn-login">
                <i class="nc-icon nc-key-25"></i>
                Sign In
            </button>
        </form>
        
        
    </div>

    <!-- Core JS Files -->
    <script src="assets/js/core/jquery.min.js"></script>
    <script src="assets/js/core/popper.min.js"></script>
    <script src="assets/js/core/bootstrap.min.js"></script>
    
    <!-- Quick Login JS -->
    <script>
        // Auto-focus on first field
        document.querySelector('input[name="username"]').focus();
    </script>
</body>
</html>