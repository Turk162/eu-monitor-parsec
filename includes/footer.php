<?php
/**
 * Footer Include File
 * EU Project Manager - Paper Dashboard Template
 * 
 * This file contains the footer component and closing HTML structure
 * To be included at the end of all dashboard pages
 */

// Dynamic year for copyright
$current_year = date('Y');
?>

            <!-- FOOTER -->
            <footer class="footer footer-black footer-white">
                <div class="container-fluid">
                    <div class="row">
                        <nav class="footer-nav">
                            <ul>
                                <li><a href="https://europa.eu/programmes/erasmus-plus/" target="_blank">Erasmus+</a></li>
                                <li><a href="https://ec.europa.eu/info/research-and-innovation/funding/funding-opportunities/funding-programmes-and-open-calls/horizon-europe_en" target="_blank">Horizon Europe</a></li>
                                <li><a href="about.php">About Us</a></li>
                                <li><a href="help.php">Help</a></li>
                            </ul>
                        </nav>
                        <div class="credits ml-auto">
                            <span class="copyright">
                                © <?php echo $current_year; ?>, EU Project Manager
                            </span>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Core JS Files -->
    <script src="../assets/js/core/jquery.min.js"></script>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>
    <script src="../assets/js/plugins/perfect-scrollbar.jquery.min.js"></script>
    
    <!-- Paper Dashboard CORE plugins -->
    <script src="../assets/js/plugins/chartjs.min.js"></script>
    <script src="../assets/js/plugins/bootstrap-notify.js"></script>
    
    <!-- Paper Dashboard for Bootstrap 4 -->
    <script src="../assets/js/paper-dashboard.min.js?v=2.0.1"></script>
    
    <!-- Custom Scripts -->
    <script>
        $(document).ready(function() {
            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
            
            // Confirm delete actions
            $('.btn-delete').on('click', function(e) {
                if (!confirm('Are you sure you want to delete this item?')) {
                    e.preventDefault();
                }
            });
        });

        // Funzione per marcare alert come letti
function markAsRead(alertId, alertElement) {
    // Submit del form per marcare come letto
    const form = alertElement.querySelector('form');
    if (form) {
        form.submit();
    }
}
        
    </script>

    <!-- Page-specific script -->
    <?php if (!empty($page_js_path) && file_exists($page_js_path)): ?>
    <script src="<?= htmlspecialchars($page_js_path) ?>"></script>
    <?php endif; ?>
</body>
</html>