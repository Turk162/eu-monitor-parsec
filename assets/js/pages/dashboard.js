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

    // ===================================================================
    // NOTIFICATIONS - Mark as Read Functionality
    // ===================================================================
    
    console.log('Dashboard.js loaded successfully'); // DEBUG
    
    // Handle "Mark as Read" button clicks using event delegation
    $(document).on('click', '.mark-as-read-btn', function(e) {
        e.preventDefault();
        console.log('Mark as Read button clicked'); // DEBUG
        
        const button = $(this);
        const alertId = button.data('alert-id');
        const alertContainer = button.closest('.alert');
        
        console.log('Alert ID:', alertId); // DEBUG
        
        if (!alertId) {
            console.error('No alert ID found');
            return;
        }
        
        // Disable button and show loading state
        button.prop('disabled', true).html('<strong>Processing...</strong>');
        
        console.log('Sending AJAX request...'); // DEBUG
        
        // Send AJAX request to mark alert as read
        $.ajax({
            url: '../api/mark_alert_read.php',
            type: 'POST',
            data: {
                action: 'mark_alert_read',
                alert_id: alertId
            },
            dataType: 'json',
            success: function(response) {
                console.log('AJAX Success:', response); // DEBUG
                
                // If successful, fade out and remove the alert
                alertContainer.fadeOut(400, function() {
                    $(this).remove();
                    
                    // Check if there are no more alerts left
                    const remainingAlerts = $('.notifications-container .alert').length;
                    if (remainingAlerts === 0) {
                        // Replace notifications container with "no notifications" message
                        $('.notifications-section').html(`
                            <h6 class="mb-3">
                                <i class="nc-icon nc-bell-55 text-muted"></i>
                                Your Notifications
                            </h6>
                            <div class="text-center text-muted">
                                <i class="nc-icon nc-check-2" style="font-size: 1.5rem;"></i>
                                <p class="mt-2 mb-0">No notifications</p>
                            </div>
                        `);
                    }
                });
                
                // Show success notification
                showNotification('Notification marked as read', 'success');
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error - Status:', status); // DEBUG
                console.error('AJAX Error - Error:', error); // DEBUG
                console.error('AJAX Error - Response:', xhr.responseText); // DEBUG
                
                // If error, re-enable button and show error message
                button.prop('disabled', false).html('<strong>Mark as Read</strong>');
                showNotification('Error marking notification as read. Please try again.', 'danger');
            }
        });
    });
    
    // ===================================================================
    // UTILITY FUNCTIONS
    // ===================================================================
    
    // Show notification function (reusable for dashboard alerts)
    function showNotification(message, type) {
        const alertClass = 'alert-' + (type || 'info');
        const notification = `
            <div class="alert ${alertClass} alert-dismissible fade show notification-temp" 
                 style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                ${message}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `;
        
        $('body').append(notification);
        
        // Auto-remove after 4 seconds
        setTimeout(function() {
            $('.notification-temp').fadeOut(function() {
                $(this).remove();
            });
        }, 4000);
    }
});