/* ===================================================================
 *  PAGE-SPECIFIC SCRIPTS FOR: Activities Page
 * =================================================================== */

function showNotification(message, type) {
    var alertClass = 'alert-info';
    if (type === 'success') alertClass = 'alert-success';
    if (type === 'danger') alertClass = 'alert-danger';
    if (type === 'warning') alertClass = 'alert-warning';
    
    var alertHtml = '<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
        message +
        '<button type="button" class="close" data-dismiss="alert">' +
        '<span>&times;</span>' +
        '</button>' +
        '</div>';
    
    $('.content').prepend(alertHtml);
    
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 3000);
}

// Function to set the color of a status selector based on its value
function updateSelectorColor($selector) {
    $selector.removeClass('status-not-started status-in-progress status-completed');
    var status = $selector.val();
    if (status === 'not_started') {
        $selector.addClass('status-not-started');
    } else if (status === 'in_progress') {
        $selector.addClass('status-in-progress');
    } else if (status === 'completed') {
        $selector.addClass('status-completed');
    }
}

$(document).ready(function() {
    // Set initial colors for all status selectors on page load
    $('.status-selector, select[name="status"]').each(function() {
        updateSelectorColor($(this));
    });

    // Auto-submit filter form on select change
    $('#projectSelect').change(function() {
        $('#filterForm').submit();
    });
    
    $('select[name="wp"], select[name="status"]').change(function() {
        // Also update color for the main filter dropdown
        if ($(this).attr('name') === 'status') {
            updateSelectorColor($(this));
        }
        $('#filterForm').submit();
    });
    
    // Search on Enter key
    $('input[name="search"]').keypress(function(e) {
        if (e.which == 13) {
            $('#filterForm').submit();
        }
    });
    
    // Activity update functionality on status change
    $(document).on('change', '.activity-update-form select[name="status"]', function() {
        var $select = $(this);
        var $form = $select.closest('.activity-update-form');

        var activityId = $form.find('input[name="activity_id"]').val();
        var newStatus = $select.val();

        // Update selector color immediately on change
        updateSelectorColor($select);

        $select.prop('disabled', true);

        $.post('activities.php', {
            action: 'update_status',
            activity_id: activityId,
            status: newStatus,
            update_activity: 1
        }, function(response) {
            if (response.success) {
                var $container = $form.closest('.activity-card, tr');
                
                // Update status badge text and class
                var $statusBadge = $container.find('.status-badge');
                if ($statusBadge.length) {
                    $statusBadge.removeClass('badge-secondary badge-primary badge-success badge-danger');
                    switch(newStatus) {
                        case 'not_started':
                            $statusBadge.addClass('badge-secondary').text('Not Started');
                            break;
                        case 'in_progress':
                            $statusBadge.addClass('badge-primary').text('In Progress');
                            break;
                        case 'completed':
                            $statusBadge.addClass('badge-success').text('Completed');
                            break;
                        default:
                            $statusBadge.addClass('badge-secondary').text('Unknown');
                    }
                }
                
                $container.removeClass('overdue due-soon completed');
                if (newStatus === 'completed') {
                    $container.addClass('completed');
                }

                showNotification('Activity updated successfully!', 'success');
            } else {
                showNotification('Error: ' + response.message, 'danger');
                // Revert color if update failed
                // Note: This requires storing the old value, for now, we just log it.
            }

            $select.prop('disabled', false);
        }, 'json').fail(function(xhr, status, error) {
            showNotification('Connection error. Please try again.', 'danger');
            $select.prop('disabled', false);
            console.error('AJAX Error:', status, error);
            console.error('Response:', xhr.responseText);
        });
    });
});
