/* ===================================================================
 *  PAGE-SPECIFIC SCRIPTS FOR: Edit Project Page
 * =================================================================== */

$(document).ready(function() {

    // --- AUTO-SAVE FUNCTIONALITY ---
    let autoSaveTimeout;
    $('.auto-save').on('input', function() {
        clearTimeout(autoSaveTimeout);
        const field = $(this).attr('name');
        const value = $(this).val();
        
        autoSaveTimeout = setTimeout(function() {
            $.ajax({
                url: '', // Post to the same page
                method: 'POST',
                data: {
                    action: 'auto_save',
                    field: field,
                    value: value
                },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        showAutoSaveIndicator();
                    }
                }
            });
        }, 2000); // Trigger auto-save after 2 seconds of inactivity
    });

    function showAutoSaveIndicator() {
        const indicator = $('#autoSaveIndicator');
        indicator.addClass('show');
        setTimeout(function() {
            indicator.removeClass('show');
        }, 2500);
    }

    // --- DYNAMIC PROJECT DURATION CALCULATION ---
    function updateProjectDuration() {
        try {
            const startDate = new Date($('#start_date').val());
            const endDate = new Date($('#end_date').val());
            
            if (!isNaN(startDate) && !isNaN(endDate) && endDate > startDate) {
                const timeDiff = endDate.getTime() - startDate.getTime();
                const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));
                const monthsDiff = (endDate.getFullYear() - startDate.getFullYear()) * 12 + (endDate.getMonth() - startDate.getMonth());
                $('#projectDuration').text(`${daysDiff} days (~${monthsDiff} months)`);
            } else {
                $('#projectDuration').text('Invalid date range');
            }
        } catch (e) {
            $('#projectDuration').text('-');
        }
    }
    $('#start_date, #end_date').on('change', updateProjectDuration);

    // --- FORM VALIDATION ---
    $('#basicDetailsForm, #addPartnerForm, #workPackageForm, #addMilestoneForm').on('submit', function(e) {
        const form = $(this);
        if (!form[0].checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            form.addClass('was-validated');
            // Optionally, add a generic alert
            // alert('Please fill out all required fields correctly.');
        } else {
            form.find('button[type="submit"]').prop('disabled', true).html('<i class="nc-icon nc-refresh-69 spin"></i> Processing...');
        }
    });

    // --- PARTNER MANAGEMENT ---
    $(document).on('click', '.btn-delete-partner', function() {
        const partnerId = $(this).data('partner-id');
        const partnerName = $(this).data('partner-name');
        
        if (confirm(`Are you sure you want to remove "${partnerName}" from this project?`)) {
            submitPostAction('delete_partner', { partner_id: partnerId });
        }
    });

    // --- WORK PACKAGE & ACTIVITY MANAGEMENT ---
    $(document).on('click', '.btn-delete-wp', function() {
        const wpId = $(this).data('wp-id');
        const wpName = $(this).data('wp-name');
        if (confirm(`Delete WP "${wpName}"? This will also delete all its activities.`)) {
            submitPostAction('delete_work_package', { wp_id: wpId });
        }
    });

    $(document).on('click', '.btn-add-activity', function() {
        const wpId = $(this).data('wp-id');
        $('#add_work_package_id').val(wpId);
        $('#addActivityModal').modal('show');
    });

    $(document).on('click', '.btn-edit-activity', function() {
        const activityId = $(this).data('activity-id');
        fetchAndPopulateModal(activityId, 'get_activity_details', '#editActivityModal', '#editActivityModalBody');
    });

    $(document).on('click', '.btn-delete-activity', function() {
        const activityId = $(this).data('activity-id');
        if (confirm('Are you sure you want to delete this activity?')) {
            submitPostAction('delete_activity', { activity_id: activityId });
        }
    });

    // --- MILESTONE MANAGEMENT ---
    $(document).on('click', '.btn-edit-milestone', function() {
        const milestoneId = $(this).data('milestone-id');
        fetchAndPopulateModal(milestoneId, 'get_milestone_details', '#editMilestoneModal', '#editMilestoneModalBody');
    });

    $(document).on('click', '.btn-delete-milestone', function() {
        const milestoneId = $(this).data('milestone-id');
        if (confirm('Are you sure you want to delete this milestone?')) {
            submitPostAction('delete_milestone', { milestone_id: milestoneId });
        }
    });

    // --- FILE UPLOAD (DRAG & DROP) ---
    const uploadArea = $('.file-upload-area');
    uploadArea.on('dragover dragenter', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    }).on('dragleave drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    }).on('drop', function(e) {
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            uploadFiles(files);
        }
    });
    $('#fileInput').on('change', function() { if (this.files.length > 0) uploadFiles(this.files); });

    function uploadFiles(files) {
        const formData = new FormData();
        formData.append('action', 'upload_files');
        // Note: You need a project_id to be available in the global scope or passed to this function.
        // formData.append('project_id', projectId);
        for (let i = 0; i < files.length; i++) { formData.append('files[]', files[i]); }

        $('#uploadProgress').show();
        $.ajax({
            url: 'upload-project-files.php', // This should be a dedicated upload handler script
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', e => {
                    if (e.lengthComputable) {
                        const percent = (e.loaded / e.total) * 100;
                        $('#uploadProgress .progress-bar').css('width', percent + '%');
                    }
                });
                return xhr;
            },
            success: () => location.reload(),
            error: () => { $('#uploadProgress').hide(); alert('Upload failed.'); }
        });
    }

    // --- TAB MANAGEMENT ---
    // Logic to show tab based on URL hash and update hash on change
    if (window.location.hash) {
        $('#projectTabs a[href="' + window.location.hash + '"]').tab('show');
    }
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        window.location.hash = e.target.hash;
    });

    // --- UTILITY FUNCTIONS ---
    /**
     * Creates and submits a POST form for simple actions like deletion.
     * @param {string} action - The 'action' value for the form.
     * @param {object} data - An object of key-value pairs for hidden inputs.
     */
    function submitPostAction(action, data) {
        const form = $('<form>', { method: 'POST', action: '' });
        form.append($('<input>', { type: 'hidden', name: 'action', value: action }));
        for (const key in data) {
            form.append($('<input>', { type: 'hidden', name: key, value: data[key] }));
        }
        form.appendTo('body').submit();
    }

    /**
     * Fetches details via AJAX and populates a modal.
     * This is a generic function that needs a corresponding API endpoint.
     * @param {number} id - The ID of the item to fetch.
     * @param {string} apiAction - The name of the API endpoint/action.
     * @param {string} modalSelector - The selector for the modal container.
     * @param {string} modalBodySelector - The selector for the modal body to populate.
     */
    function fetchAndPopulateModal(id, apiAction, modalSelector, modalBodySelector) {
        $.ajax({
            url: `../api/${apiAction}.php?id=${id}`,
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    alert(data.error);
                    return;
                }

                let formHtml = '';
                let selectElement = null; // To hold the select element for dropdowns

                if (apiAction === 'get_activity_details') {
                    const activity = data;
                    formHtml = `
                        <div class="row">
                            <div class="col-md-4"><div class="form-group"><label>Activity Number</label><input type="text" name="activity_number" class="form-control" value="${activity.activity_number || ''}"></div></div>
                            <div class="col-md-8"><div class="form-group"><label>Activity Name</label><input type="text" name="name" class="form-control" value="${activity.name || ''}" required></div></div>
                        </div>
                        <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3">${activity.description || ''}</textarea></div>
                        <div class="row">
                            <div class="col-md-6"><div class="form-group"><label>Responsible Partner</label><select name="responsible_partner_id" class="form-control"></select></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Budget (€)</label><input type="number" name="budget" class="form-control" value="${activity.budget || ''}" step="0.01" min="0"></div></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6"><label>Start Date</label><input type="date" name="start_date" class="form-control" value="${activity.start_date || ''}"></div>
                            <div class="col-md-6"><label>End Date</label><input type="date" name="end_date" class="form-control" value="${activity.end_date || ''}"></div>
                        </div>
                    `;
                    $(modalBodySelector).html(formHtml);

                    // Populate Responsible Partner dropdown
                    selectElement = $(modalBodySelector + ' select[name="responsible_partner_id"]');
                    selectElement.append('<option value="">Select Partner...</option>');
                    window.projectData.allProjectPartners.forEach(p => {
                        selectElement.append(`<option value="${p.partner_id}">${p.organization} (${p.country})</option>`);
                    });
                    selectElement.val(activity.responsible_partner_id);
                    $('#edit_activity_id').val(id); // Set the hidden activity ID
                } else if (apiAction === 'get_milestone_details') {
                    const milestone = data;
                    formHtml = `
                        <div class="row">
                            <div class="col-md-6"><div class="form-group"><label>Milestone Name</label><input type="text" name="name" class="form-control" value="${milestone.name || ''}" required></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Due Date</label><input type="date" name="end_date" class="form-control" value="${milestone.end_date || ''}" required></div></div>
                        </div>
                        <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="2">${milestone.description || ''}</textarea></div>
                        <div class="row">
                            <div class="col-md-6"><div class="form-group"><label>Work Package</label><select name="work_package_id" class="form-control"></select></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Status</label><select name="status" class="form-control"></select></div></div>
                        </div>
                        <div class="form-group"><label>Completed Date</label><input type="date" name="completed_date" class="form-control" value="${milestone.completed_date || ''}"></div>
                    `;
                    $(modalBodySelector).html(formHtml);

                    // Populate WP dropdown
                    selectElement = $(modalBodySelector + ' select[name="work_package_id"]');
                    selectElement.append('<option value="">-- No specific WP --</option>');
                    window.projectData.workPackages.forEach(wp => {
                        selectElement.append(`<option value="${wp.id}">${wp.wp_number}: ${wp.name}</option>`);
                    });
                    selectElement.val(milestone.work_package_id);

                    // Populate Status dropdown
                    selectElement = $(modalBodySelector + ' select[name="status"]');
                    const statusOptions = ['pending', 'completed', 'overdue'];
                    statusOptions.forEach(s => {
                        selectElement.append(`<option value="${s}">${s.charAt(0).toUpperCase() + s.slice(1)}</option>`);
                    });
                    selectElement.val(milestone.status);
                    $('#edit_milestone_id').val(id); // Set the hidden milestone ID
                } else if (apiAction === 'get_work_package_details') {
                    const wp = data;
                    formHtml = `
                        <div class="row">
                            <div class="col-md-3"><div class="form-group"><label>WP Number</label><input type="text" name="wp_number" class="form-control" value="${wp.wp_number || ''}" required></div></div>
                            <div class="col-md-9"><div class="form-group"><label>Work Package Name</label><input type="text" name="name" class="form-control" value="${wp.name || ''}" required></div></div>
                        </div>
                        <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3">${wp.description || ''}</textarea></div>
                        <div class="row">
                            <div class="col-md-4"><div class="form-group"><label>Lead Partner</label><select name="lead_partner_id" class="form-control"></select></div></div>
                            <div class="col-md-4"><div class="form-group"><label>Budget (€)</label><input type="number" name="budget" class="form-control" value="${wp.budget || ''}" step="0.01" min="0" required></div></div>
                            <div class="col-md-4"><div class="form-group"><label>Status</label><select name="status" class="form-control"></select></div></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4"><label>Start Date</label><input type="date" name="start_date" class="form-control" value="${wp.start_date || ''}" required></div>
                            <div class="col-md-4"><label>End Date</label><input type="date" name="end_date" class="form-control" value="${wp.end_date || ''}" required></div>
                            <div class="col-md-4"><div class="form-group"><label>Progress (%)</label><input type="number" name="progress" class="form-control" value="${wp.progress || '0'}" min="0" max="100" step="1"></div></div>
                        </div>
                    `;
                    $(modalBodySelector).html(formHtml);

                    // Populate Lead Partner dropdown
                    selectElement = $(modalBodySelector + ' select[name="lead_partner_id"]');
                    selectElement.append('<option value="">Select Lead Partner...</option>');
                    window.projectData.allProjectPartners.forEach(p => {
                        selectElement.append(`<option value="${p.partner_id}">${p.organization} (${p.country})</option>`);
                    });
                    selectElement.val(wp.lead_partner_id);

                    // Populate Status dropdown
                    selectElement = $(modalBodySelector + ' select[name="status"]');
                    const statusOptions = ['not_started', 'in_progress', 'completed', 'delayed'];
                    statusOptions.forEach(s => {
                        selectElement.append(`<option value="${s}">${s.replace(/_/g, ' ').charAt(0).toUpperCase() + s.replace(/_/g, ' ').slice(1)}</option>`);
                    });
                    selectElement.val(wp.status);
                    $('#edit_wp_id').val(id); // Set the hidden WP ID
                }

                $(modalSelector).modal('show');
            },
            error: function() {
                alert('Failed to fetch details.');
            }
        });
    }
});
