/* ===================================================================
 *  PAGE-SPECIFIC SCRIPTS FOR: Projects List Page
 * =================================================================== */

$(document).ready(function() {
    // Initialize Bootstrap tooltips for the progress circles
    $('.progress-circle').tooltip({
        title: function() {
            return 'Project completion: ' + $(this).text();
        },
        placement: 'top'
    });

    // Auto-submit the filter form when a dropdown value changes
    $('select[name="status"], select[name="program"]').change(function() {
        $('#filterForm').submit();
    });

    // Allow search on pressing Enter in the search box
    $('input[name="search"]').keypress(function(e) {
        if (e.which == 13) { // 13 is the Enter key code
            $('#filterForm').submit();
        }
    });

    // Add an interactive hover effect to the project cards
    $('.project-card').hover(
        function() {
            // On mouse enter, change header to a gradient and text to white
            $(this).find('.project-header').css('background', 'linear-gradient(135deg, #51CACF 0%, #667eea 100%)');
            $(this).find('.project-header h5, .project-header small').css('color', 'white');
        },
        function() {
            // On mouse leave, revert to the original styles
            $(this).find('.project-header').css('background', 'linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%)');
            $(this).find('.project-header h5, .project-header small').css('color', '');
        }
    );
});
