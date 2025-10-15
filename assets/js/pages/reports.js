/**
 * Handles the functionality for the Reports page, including
 * filtering, viewing report details in a modal, and handling UI events.
 */

// Function to view a report's details in a modal.
function viewReport(reportId) {

    // Get references to modal elements.
    const modal = $('#viewReportModal');
    const modalBody = $('#reportDetailsContent');
    const editButton = $('#modal-edit-button');
    const deleteButton = $('#modal-delete-button');

    // --- 1. Reset Modal State ---
    
    // Show a loading spinner while fetching data.
    modalBody.html('<div class="text-center p-5"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading details...</div>');
    
    // Hide action buttons by default.
    editButton.hide();
    deleteButton.hide();

    // Show the modal window.
    modal.modal('show');

    // --- 2. Fetch Report Data via AJAX ---

    $.ajax({
        url: `../api/get_report_details.php?id=${reportId}&user_role=${currentUserRole}`,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            // Check if the API call was successful.
            if (response.success) {
                const report = response.report;
                const files = response.files;
                const permissions = response.permissions;

                // --- 3. Build and Populate Modal Content ---

                // Construct the HTML for the modal body.
                let filesHtml = '';
                if (files.length > 0) {
                    filesHtml = '<h5><i class="nc-icon nc-attach-87 text-primary"></i> Attached Files</h5><ul class="list-group">';
                    files.forEach(file => {
                        filesHtml += `<li class="list-group-item"><a href="../${file.file_path}" target="_blank"><i class="nc-icon nc-paper"></i> ${file.original_filename}</a></li>`;
                    });
                    filesHtml += '</ul><hr>';
                }

                let participantsHtml = '';
                if(report.participants_data){
                    participantsHtml = `<h5><i class="nc-icon nc-single-02 text-primary"></i> Participants Data</h5>
                                        <div class="card bg-light p-3"><p class="mb-0" style="white-space: pre-wrap;">${report.participants_data}</p></div><hr>`
                }

                const modalHtml = `
                    <h4>Report #${report.id}</h4>
                    <p class="text-muted">Activity: <strong>${report.activity_name}</strong></p>
                    <hr>
                    <h5><i class="nc-icon nc-paper text-primary"></i> Summary</h5>
                    <p>${report.description}</p>
                    <div class="row">
                        <div class="col-md-6"><strong>Project:</strong> ${report.project_name}</div>
                        <div class="col-md-6"><strong>Partner:</strong> ${report.partner_org_name}</div>
                        <div class="col-md-6"><strong>Report Date:</strong> ${report.report_date}</div>
                        <div class="col-md-6"><strong>Status:</strong> ${report.status_badge}</div>
                    </div>
                    <hr>
                    ${participantsHtml}
                    ${filesHtml}
                `;
                
                // Set the generated HTML as the modal body content.
                modalBody.html(modalHtml);

                // --- 4. Handle Permissions ---

                // If the user has permission to modify, show the action buttons.
                if (permissions.can_modify) {
                    // Set the correct links for the buttons.
                    editButton.attr('href', `edit-report.php?id=${report.id}`);
                    deleteButton.attr('href', `delete-report.php?id=${report.id}`);
                    
                    // Show the buttons.
                    editButton.show();
                    deleteButton.show();
                }

                // If the user can change the status, show the status update form.
                if (permissions.can_change_status) {
                    const statusFormHtml = `
                        <hr>
                        <h5><i class="nc-icon nc-settings-gear-65 text-primary"></i> Update Status</h5>
                        <form id="statusUpdateForm" onsubmit="updateReportStatus(event, ${report.id})">
                            <div class="form-group">
                                <select name="status" class="form-control">
                                    <option value="draft" ${report.status === 'draft' ? 'selected' : ''}>Draft</option>
                                    <option value="submitted" ${report.status === 'submitted' ? 'selected' : ''}>Submitted</option>
                                    <option value="approved" ${report.status === 'approved' ? 'selected' : ''}>Approved</option>
                                    <option value="rejected" ${report.status === 'rejected' ? 'selected' : ''}>Rejected</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <textarea name="feedback" class="form-control" rows="2" placeholder="Add optional feedback for the partner...">${report.coordinator_feedback}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Status</button>
                            <small id="statusUpdateResult" class="ml-2"></small>
                        </form>
                    `;
                    modalBody.append(statusFormHtml);
                }

            } else {
                // If the API returns an error, show the error message.
                modalBody.html(`<div class="alert alert-danger"><strong>Error:</strong> ${response.message}</div>`);
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            // Handle AJAX-level errors (e.g., network issues, 500 server error).
            modalBody.html(`<div class="alert alert-danger"><strong>AJAX Error:</strong> Could not load report details. ${errorThrown}</div>`);
        }
    });
}

// Function to handle the submission of the status update form.
function updateReportStatus(event, reportId) {
    event.preventDefault(); // Prevent default form submission

    const form = $('#statusUpdateForm');
    const resultMessage = $('#statusUpdateResult');

    // Get data from the form.
    const formData = {
        report_id: reportId,
        status: form.find('select[name="status"]').val(),
        feedback: form.find('textarea[name="feedback"]').val(),
        action: 'update_status' // Action for the server to identify the request
    };

    resultMessage.text('Updating...').removeClass('text-success text-danger');

    // Send the data via AJAX POST request.
    $.ajax({
        url: '../api/update_report_status.php', // A new, dedicated API endpoint
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                resultMessage.text(response.message).addClass('text-success');
                // Optionally, refresh the page or update the UI to reflect the change
                setTimeout(() => { window.location.reload(); }, 1000);
            } else {
                resultMessage.text(response.message).addClass('text-danger');
            }
        },
        error: function() {
            resultMessage.text('An error occurred.').addClass('text-danger');
        }
    });
}

// --- Event Handlers ---

$(document).ready(function() {
    // Auto-submit filter form on select change.
    $('#filterForm select').change(function() {
        $('#filterForm').submit();
    });

    // Add click handler for the delete button to add a confirmation dialog.
    $('#modal-delete-button').on('click', function(e) {
        e.preventDefault(); // Stop the link from navigating immediately
        const deleteUrl = $(this).attr('href');
        if (confirm('Are you sure you want to permanently delete this report?')) {
            window.location.href = deleteUrl;
        }
    });
});

/**
 * Handles the delete confirmation process with detailed logging.
 * @param {Event} event The click event.
 * @param {string} deleteUrl The URL to redirect to for deletion.
 */
function confirmDelete(event, deleteUrl) {
    event.preventDefault(); // Stop the link from navigating immediately

    console.log('confirmDelete function called.');
    console.log('Delete URL:', deleteUrl);

    if (confirm('Are you sure you want to permanently delete this report? This action cannot be undone.')) {
        console.log('User confirmed deletion. Redirecting...');
        window.location.href = deleteUrl;
    } else {
        console.log('User cancelled deletion.');
    }
}
