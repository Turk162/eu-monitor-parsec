$(document).ready(function() {

    // Handle the file upload form submission
    $('#upload-form').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        var feedbackDiv = $('#upload-feedback');

        feedbackDiv.html('<div class="alert alert-info">Uploading, please wait...</div>');

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                if (response.success) {
                    feedbackDiv.html('<div class="alert alert-success">' + response.message + '</div>');
                    // Reload the page to show the new file
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    feedbackDiv.html('<div class="alert alert-danger">Error: ' + response.message + '</div>');
                }
            },
            error: function() {
                feedbackDiv.html('<div class="alert alert-danger">An unexpected error occurred during upload.</div>');
            }
        });
    });

    // Handle file deletion
    $('.btn-delete-file').on('click', function() {
        var fileId = $(this).data('file-id');
        var row = $(this).closest('tr');

        if (!confirm('Are you sure you want to delete this file? This action cannot be undone.')) {
            return;
        }

        $.ajax({
            url: '../api/delete_file.php',
            type: 'POST',
            data: { 
                action: 'delete_file',
                file_id: fileId 
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Fade out and remove the row from the table
                    row.fadeOut(500, function() {
                        $(this).remove();
                    });
                    // You might want to add a more persistent success message
                    alert('File deleted successfully.'); 
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An unexpected error occurred while trying to delete the file.');
            }
        });
    });

});
