<?php
$page_title = 'Change Password';
$page_css_path = '../assets/css/pages/change-password.css';
$page_js_path = '../assets/js/pages/change-password.js';

require_once '../includes/header.php';
?>

<body class="">
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-panel">
            <?php include '../includes/navbar.php'; ?>
            <div class="content">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="title">Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form id="changePasswordForm">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>Old Password</label>
                                                <input type="password" class="form-control" id="old_password" name="old_password" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>New Password</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>Confirm New Password</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary btn-round">Change Password</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
</body>

</html>
