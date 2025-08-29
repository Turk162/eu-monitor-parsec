<?php
/**
 * Sidebar Include File
 * EU Project Manager - Paper Dashboard Template
 * 
 * This file contains the sidebar navigation component
 * To be included in all dashboard pages
 */

// Get current page name for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" data-color="white" data-active-color="danger">
    <div class="logo">
        <a href="#" class="simple-text logo-mini">
            <div class="logo-image-small">
                <img src="../assets/img/logo-small.png" alt="logo">
            </div>
        </a>
        <a href="#" class="simple-text logo-normal">
            Project Manager
        </a>
    </div>
    <div class="sidebar-wrapper">
        <ul class="nav">
            <li <?php echo ($current_page == 'dashboard.php') ? 'class="active"' : ''; ?>>
                <a href="dashboard.php">
                    <i class="nc-icon nc-bank"></i>
                    <p>Dashboard</p>
                </a>
            </li>
            <li <?php echo ($current_page == 'projects.php') ? 'class="active"' : ''; ?>>
                <a href="projects.php">
                    <i class="nc-icon nc-briefcase-24"></i>
                    <p>My Projects</p>
                </a>
            </li>
          <!--   <li <?php echo ($current_page == 'activities.php') ? 'class="active"' : ''; ?>>
                <a href="activities.php">
                    <i class="nc-icon nc-paper"></i>
                    <p>Activities</p>
                </a>
            </li>
            <li <?php echo ($current_page == 'reports.php') ? 'class="active"' : ''; ?>>
                <a href="reports.php">
                    <i class="nc-icon nc-chart-bar-32"></i>
                    <p>Reports</p>
                </a>
            </li>-->
            <li <?php echo ($current_page == 'calendar.php') ? 'class="active"' : ''; ?>>
                <a href="calendar.php">
                    <i class="nc-icon nc-calendar-60"></i>
                    <p>Calendar</p>
                </a>
            </li>
            <?php if($user_role === 'super_admin' || $user_role === 'coordinator'): ?>
            <li <?php echo ($current_page == 'users.php') ? 'class="active"' : ''; ?>>
                <a href="users.php">
                            <i class="nc-icon nc-circle-10"></i>
                            <p>User Management</p>
                        </a>
                    </li>
                    <li>
                        <a href="partners.php">
                            <i class="nc-icon nc-world-2"></i>
                            <p>Partners</p>
                        </a>
                    </li>
            <?php endif; ?>
        </ul>
    </div>
</div>