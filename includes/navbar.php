<?php
/**
 * Navbar Include File
 * EU Project Manager - Paper Dashboard Template
 * 
 * This file contains the top navigation bar component
 * To be included in all dashboard pages
 */

// Get current page title for navbar brand
$page_titles = [
    'dashboard.php' => 'Dashboard',
    'projects.php' => 'My Projects',
    'create-project.php' => 'Create New Project',
    'activities.php' => 'Activities',
    'reports.php' => 'Reports',
    'calendar.php' => 'Calendar',
    'users.php' => 'User Management',
    'profile.php' => 'Profile',
    'settings.php' => 'Settings',
    'add-project-partners.php' => 'Add Partner',
    'add-project-workpackages.php' => 'Add Work Package',
    'add-project-milestones.php' => 'Add Milestone',
    'manage-project-risks.php' => 'Manage Risks',
    'project-detail.php' => 'Project Details',
    'edit-project.php' => 'Edit Project',
    'add-activity.php' => 'Add Activity',
    'edit-activity.php' => 'Edit Activity',
    'project-edit.php' => 'Edit Project',
    'activity-view.php' => 'Activity Details',
    'project-gantt.php' => 'Project Gantt',
    'add-report.php' => 'Add a Report',
    'edit-report.php' => 'Edit a Report',
    'create-report.php' => 'Create a Report',
    'partner-budget.php' => 'Partner Budget',
    'manage-partners-budget.php' => 'Manage Partners Budget',
    'project-files.php' => 'Project Files',
    'edit-project-files.php' => 'Edit Project Files',
    'admin_reports.php' => 'Admin Reports',
    'partners.php' => 'Partners',
    'add-partner.php' => 'Add Partner',
    'edit-partner.php' => 'Edit Partner',
    'add-work-package.php' => 'Add Work Package',
    'edit-work-package.php' => 'Edit Work Package',
    'add-milestone.php' => 'Add Milestone',
    'edit-milestone.php' => 'Edit Milestone',
    'add-risk.php' => 'Add Risk',
    'edit-risk.php' => 'Edit Risk',
    'change-password.php' => 'Change Password'
    
    
];

$current_page = basename($_SERVER['PHP_SELF']);
$page_title = isset($page_titles[$current_page]) ? $page_titles[$current_page] : 'Pagina non dichiarata';
?>

<div class="main-panel">
    <!-- NAVBAR WITH FIXED LOGOUT -->
    <nav class="navbar navbar-expand-lg navbar-absolute fixed-top navbar-transparent">
        <div class="container-fluid">
            <div class="navbar-wrapper">
                <div class="navbar-toggle">
                    <button type="button" class="navbar-toggler">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="navbar-toggler-icon icon-bar"></span>
                        <span class="navbar-toggler-icon icon-bar"></span>
                        <span class="navbar-toggler-icon icon-bar"></span>
                    </button>
                </div>
                <a class="navbar-brand" href="javascript:;"><?php echo $page_title; ?></a>
            </div>
            <!-- RIGHT SIDE WITH USER INFO AND LOGOUT -->
            <div class="navbar-nav ml-auto d-flex flex-row align-items-center">
                <!-- User Info -->
                <div class="user-info">
                    <i class="nc-icon nc-circle-10"></i>
                    <span><strong><?= isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User' ?></strong></span>
                    <span class="text-muted">|</span>
                    <span><?= isset($_SESSION['role']) && function_exists('getRoleDisplayName') ? getRoleDisplayName($_SESSION['role']) : $_SESSION['role'] ?></span>
                </div>
                <!-- Direct Logout Button -->
                <a href="../logout.php" class="logout-btn ml-3" onclick="return confirm('Are you sure you want to logout?');">
                    <i class="nc-icon nc-button-power"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>