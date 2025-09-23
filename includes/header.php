<?php
// ===================================================================
// HEADER TEMPLATE - Common header for all pages
// ===================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use environment to load correct DB config
require_once __DIR__ . '/../config/environment.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/functions.php';

// Establish database connection
$database = new Database();
$conn = $database->connect();

// Verify login, passing DB connection to Auth class
$auth = new Auth($conn);
$auth->requireLogin();

// Get user data (available in all pages)
$user_id = getUserId();
$user_role = getUserRole();

// Page variables (can be set before including this file)
$page_title = $page_title ?? 'EU Project Manager';
$page_styles = $page_styles ?? '';
$body_class = $body_class ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no' name='viewport' />
    
    <!-- Fonts and icons -->
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700,200" rel="stylesheet" />
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css" rel="stylesheet">
    
    <!-- CSS Files -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet" />
    <link href="../assets/css/paper-dashboard.css?v=<?php echo time(); ?>" rel="stylesheet" />
    <link href="../assets/css/custom.css" rel="stylesheet" />
    
    <!-- Page-specific styles -->
    <?php if (!empty($page_css_path) && file_exists($page_css_path)): ?>
    <link href="<?= htmlspecialchars($page_css_path) ?>" rel="stylesheet" />
    <?php endif; ?>
    <?php if (!empty($page_styles)): ?>
    <style>
        <?= $page_styles ?>
    </style>
    <?php endif; ?>
</head>

<body class="<?= $body_class ?>">
<div class="wrapper">