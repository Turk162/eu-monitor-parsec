/* ===================================================================
 *  PAGE-SPECIFIC SCRIPTS FOR: Dashboard
 * =================================================================== */

$(document).ready(function() {
    // Auto-refresh stats every 5 minutes
    // This is useful for a dashboard that is often left open.
    setInterval(function() {
        location.reload();
    }, 300000); // 300000 milliseconds = 5 minutes

    // Show a helpful tooltip on the project progress circles
    // Initialize Bootstrap tooltips
    $('.progress-circle').tooltip({
        title: 'Project completion percentage',
        placement: 'top'
    });

    // Add a confirmation dialog before logging out
    // This prevents accidental logouts.
    $('.logout-btn').click(function(e) {
        if (!confirm('Are you sure you want to logout?')) {
            e.preventDefault(); // Cancel the logout action if user clicks 'Cancel'
        }
    });
});