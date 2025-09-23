<?php
// ===================================================================
//  LAYOUT DEBUG TEST FILE
//  File per diagnosticare il problema di posizionamento della sidebar
// ===================================================================

// Parametri di test via URL
$test_mode = isset($_GET['test']) ? $_GET['test'] : '1';
$include_sidebar = isset($_GET['sidebar']) ? $_GET['sidebar'] : 'include';
$include_css = isset($_GET['css']) ? $_GET['css'] : 'all';
$include_js = isset($_GET['js']) ? $_GET['js'] : 'all';

echo "<!-- DEBUG: Test mode = $test_mode, Sidebar = $include_sidebar, CSS = $include_css, JS = $include_js -->";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Layout Debug Test - EU Project Manager</title>
    <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no' name='viewport' />
    
    <!-- Bootstrap CSS -->
    <?php if ($include_css === 'all' || $include_css === 'bootstrap'): ?>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet" />
    <?php endif; ?>
    
    <!-- Paper Dashboard CSS -->
    <?php if ($include_css === 'all' || $include_css === 'paper'): ?>
    <link href="../assets/css/paper-dashboard.css?v=2.0.1" rel="stylesheet" />
    <?php endif; ?>
    
    <!-- Custom CSS -->
    <?php if ($include_css === 'all' || $include_css === 'custom'): ?>
    <link href="../assets/css/pages/manage-partners-budget.css" rel="stylesheet" />
    <?php endif; ?>
    
    <!-- Debug CSS -->
    <style>
        /* CSS di debug per visualizzare il layout */
        .debug-info {
            position: fixed;
            top: 10px;
            right: 10px;
            background: #000;
            color: #fff;
            padding: 10px;
            z-index: 9999;
            font-size: 12px;
            border-radius: 5px;
        }
        
        <?php if ($test_mode === 'debug-colors'): ?>
        /* Test mode: Colori di debug */
        .sidebar {
            background: red !important;
            border: 3px solid yellow !important;
        }
        .main-panel {
            background: blue !important;
            border: 3px solid green !important;
        }
        .content {
            background: pink !important;
            border: 2px solid orange !important;
        }
        <?php endif; ?>
        
        <?php if ($test_mode === 'force-layout'): ?>
        /* Test mode: Layout forzato */
        .sidebar {
            position: fixed !important;
            left: 0 !important;
            width: 260px !important;
            height: 100vh !important;
            z-index: 1000 !important;
        }
        .main-panel {
            margin-left: 260px !important;
            width: calc(100% - 260px) !important;
            transform: none !important;
            transition: none !important;
        }
        <?php endif; ?>
        
        <?php if ($test_mode === 'minimal'): ?>
        /* Test mode: CSS minimale */
        .wrapper {
            display: flex;
        }
        .sidebar {
            width: 260px;
            background: #f0f0f0;
            min-height: 100vh;
        }
        .main-panel {
            flex: 1;
            background: #fff;
        }
        <?php endif; ?>
    </style>
    
    <!-- Font Awesome -->
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css" rel="stylesheet">
</head>

<body>
    <!-- Debug Info Panel -->
    <div class="debug-info">
        <strong>DEBUG TEST</strong><br>
        Mode: <?php echo $test_mode; ?><br>
        Sidebar: <?php echo $include_sidebar; ?><br>
        CSS: <?php echo $include_css; ?><br>
        JS: <?php echo $include_js; ?><br>
        <hr style="margin: 5px 0;">
        <a href="?test=1" style="color: #fff;">Test 1: Normal</a><br>
        <a href="?test=debug-colors" style="color: #fff;">Test 2: Colors</a><br>
        <a href="?test=force-layout" style="color: #fff;">Test 3: Force</a><br>
        <a href="?test=minimal" style="color: #fff;">Test 4: Minimal</a><br>
        <a href="?sidebar=hardcode" style="color: #fff;">Hardcode Sidebar</a><br>
        <a href="?css=none" style="color: #fff;">No CSS</a><br>
        <a href="?js=none" style="color: #fff;">No JS</a>
    </div>

    <div class="wrapper">
        <!-- SIDEBAR SECTION -->
        <?php if ($include_sidebar === 'include'): ?>
            <!-- Test con include PHP -->
            <?php 
            echo "<!-- SIDEBAR INCLUDE START -->";
            if (file_exists('../includes/sidebar.php')) {
                include '../includes/sidebar.php';
                echo "<!-- SIDEBAR INCLUDE SUCCESS -->";
            } else {
                echo "<!-- SIDEBAR INCLUDE FAILED: File not found -->";
                echo '<div style="background: red; color: white; padding: 20px;">SIDEBAR INCLUDE FAILED</div>';
            }
            echo "<!-- SIDEBAR INCLUDE END -->";
            ?>
        <?php elseif ($include_sidebar === 'hardcode'): ?>
            <!-- Test con sidebar hardcoded -->
            <div class="sidebar" data-color="white" data-active-color="danger">
                <div class="logo">
                    <a href="#" class="simple-text logo-mini">
                        <div class="logo-image-small">
                            <img src="../assets/img/logo-small.png">
                        </div>
                    </a>
                    <a href="#" class="simple-text logo-normal">
                        PROJECT MANAGER
                    </a>
                </div>
                <div class="sidebar-wrapper">
                    <ul class="nav">
                        <li>
                            <a href="../pages/dashboard.php">
                                <i class="nc-icon nc-bank"></i>
                                <p>DASHBOARD</p>
                            </a>
                        </li>
                        <li>
                            <a href="../pages/projects.php">
                                <i class="nc-icon nc-tile-56"></i>
                                <p>MY PROJECTS</p>
                            </a>
                        </li>
                        <li class="active">
                            <a href="#">
                                <i class="nc-icon nc-settings-gear-65"></i>
                                <p>TEST PAGE</p>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        <?php else: ?>
            <!-- Nessuna sidebar -->
            <div style="background: yellow; padding: 20px; width: 260px;">
                NO SIDEBAR LOADED
            </div>
        <?php endif; ?>
        
        <!-- MAIN PANEL -->
        <div class="main-panel">
            <!-- NAVBAR SECTION -->
            <?php if ($include_sidebar === 'include'): ?>
                <?php 
                echo "<!-- NAVBAR INCLUDE START -->";
                if (file_exists('../includes/navbar.php')) {
                    include '../includes/navbar.php';
                    echo "<!-- NAVBAR INCLUDE SUCCESS -->";
                } else {
                    echo "<!-- NAVBAR INCLUDE FAILED -->";
                    echo '<nav class="navbar navbar-expand-lg navbar-absolute fixed-top navbar-transparent">
                            <div class="container-fluid">
                                <span class="navbar-brand">DEBUG NAVBAR</span>
                            </div>
                          </nav>';
                }
                echo "<!-- NAVBAR INCLUDE END -->";
                ?>
            <?php else: ?>
                <!-- Navbar hardcoded -->
                <nav class="navbar navbar-expand-lg navbar-absolute fixed-top navbar-transparent">
                    <div class="container-fluid">
                        <div class="navbar-wrapper">
                            <div class="navbar-toggle">
                                <button type="button" class="navbar-toggler">
                                    <span class="navbar-toggler-bar bar1"></span>
                                    <span class="navbar-toggler-bar bar2"></span>
                                    <span class="navbar-toggler-bar bar3"></span>
                                </button>
                            </div>
                            <a class="navbar-brand" href="#">Layout Debug Test</a>
                        </div>
                        <button class="navbar-toggler" type="button">
                            <span class="navbar-toggler-bar navbar-kebab"></span>
                            <span class="navbar-toggler-bar navbar-kebab"></span>
                            <span class="navbar-toggler-bar navbar-kebab"></span>
                        </button>
                        <div class="collapse navbar-collapse justify-content-end">
                            <ul class="navbar-nav">
                                <li class="nav-item">
                                    <a class="nav-link" href="#">
                                        <span class="no-icon">Debug User</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </nav>
            <?php endif; ?>
            
            <!-- CONTENT -->
            <div class="content">
                <div class="row">
                    <div class="col-md-12">
                        <!-- Test Content -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Layout Debug Test</h4>
                                <p class="card-category">Testing sidebar positioning issue</p>
                            </div>
                            <div class="card-body">
                                <h5>Current Test Configuration:</h5>
                                <ul>
                                    <li><strong>Test Mode:</strong> <?php echo $test_mode; ?></li>
                                    <li><strong>Sidebar Method:</strong> <?php echo $include_sidebar; ?></li>
                                    <li><strong>CSS Loading:</strong> <?php echo $include_css; ?></li>
                                    <li><strong>JS Loading:</strong> <?php echo $include_js; ?></li>
                                </ul>
                                
                                <h5>File Checks:</h5>
                                <ul>
                                    <li><strong>sidebar.php:</strong> <?php echo file_exists('../includes/sidebar.php') ? 'EXISTS' : 'NOT FOUND'; ?></li>
                                    <li><strong>navbar.php:</strong> <?php echo file_exists('../includes/navbar.php') ? 'EXISTS' : 'NOT FOUND'; ?></li>
                                    <li><strong>paper-dashboard.css:</strong> <?php echo file_exists('../assets/css/paper-dashboard.css') ? 'EXISTS' : 'NOT FOUND'; ?></li>
                                    <li><strong>manage-partners-budget.css:</strong> <?php echo file_exists('../assets/css/pages/manage-partners-budget.css') ? 'EXISTS' : 'NOT FOUND'; ?></li>
                                </ul>
                                
                                <h5>Layout Test Content:</h5>
                                <p>This content should appear to the right of the sidebar without excessive spacing.</p>
                                <p>If you see this content jumping or shifting after page load, that indicates a JavaScript timing issue.</p>
                                <p>If there's too much space between sidebar and content, that indicates a CSS layout issue.</p>
                                
                                <!-- Dummy content for testing -->
                                <div class="card">
                                    <div class="card-body">
                                        <h6>Sample Work Package</h6>
                                        <p>This simulates the work package cards from the original page.</p>
                                        <div class="form-group">
                                            <label>Test Input</label>
                                            <input type="text" class="form-control" placeholder="Test form input">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT SECTION -->
    <?php if ($include_js === 'all' || $include_js === 'jquery'): ?>
    <script src="../assets/js/core/jquery.min.js"></script>
    <?php endif; ?>
    
    <?php if ($include_js === 'all' || $include_js === 'bootstrap'): ?>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>
    <?php endif; ?>
    
    <?php if ($include_js === 'all' || $include_js === 'paper'): ?>
    <script src="../assets/js/paper-dashboard.min.js?v=2.0.1"></script>
    <?php endif; ?>
    
    <?php if ($include_js === 'all' || $include_js === 'custom'): ?>
    <script src="../assets/js/pages/manage-partners-budget.js"></script>
    <?php endif; ?>
    
    <!-- Debug JavaScript -->
    <script>
        console.log('=== LAYOUT DEBUG TEST ===');
        console.log('Test mode:', '<?php echo $test_mode; ?>');
        console.log('Sidebar method:', '<?php echo $include_sidebar; ?>');
        
        $(document).ready(function() {
            console.log('Document ready');
            console.log('Sidebar width:', $('.sidebar').width());
            console.log('Main panel margin-left:', $('.main-panel').css('margin-left'));
            console.log('Main panel width:', $('.main-panel').width());
            
            // Monitor layout changes
            var initialPosition = $('.main-panel').offset();
            console.log('Initial main-panel position:', initialPosition);
            
            setTimeout(function() {
                var finalPosition = $('.main-panel').offset();
                console.log('Final main-panel position (after 1s):', finalPosition);
                
                if (initialPosition && finalPosition) {
                    if (initialPosition.left !== finalPosition.left) {
                        console.warn('LAYOUT SHIFT DETECTED!');
                        console.warn('Initial left:', initialPosition.left, 'Final left:', finalPosition.left);
                    }
                }
            }, 1000);
        });
        
        // Monitor window resize
        $(window).on('resize', function() {
            console.log('Window resized - Main panel margin:', $('.main-panel').css('margin-left'));
        });
    </script>
</body>
</html>
