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

    $(document).on('click', '.btn-edit-wp', function() {
    const wpId = $(this).data('wp-id');
    fetchAndPopulateModal(wpId, 'get_work_package_details', '#editWorkPackageModal', '#editWorkPackageModalBody');
});

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
// SOSTITUISCI la funzione .btn-edit-activity nel tuo project-edit.js con questa:

$(document).on('click', '.btn-edit-activity', function() {
    const activityId = $(this).data('activity-id');
    
    $.ajax({
        url: `../api/get_activity_details.php?id=${activityId}`,
        method: 'GET',
        dataType: 'json',
        success: function(activity) {
            if (activity.error) {
                alert(activity.error);
                return;
            }
            
            let formHtml = `
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Activity Number</label>
                            <input type="text" name="activity_number" class="form-control" value="${activity.activity_number || ''}">
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Activity Name</label>
                            <input type="text" name="name" class="form-control" value="${activity.name || ''}" required>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3">${activity.description || ''}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Responsible Partner</label>
                            <select name="responsible_partner_id" class="form-control" required>
                                <option value="">Loading partners...</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Budget (€)</label>
                            <input type="number" name="budget" class="form-control" value="${activity.budget || ''}" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="${activity.start_date || ''}">
                    </div>
                    <div class="col-md-6">
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" value="${activity.end_date || ''}">
                    </div>
                </div>
            `;
            
            // Popola il contenuto del modale
            $('#editActivityModalBody').html(formHtml);
            
            // CHIAVE: Imposta l'ID dell'attività nel campo hidden
            $('#edit_activity_id').val(activityId);
            
            // Carica i partner del progetto tramite AJAX
            loadProjectPartners(activity.responsible_partner_id);
            
            // Mostra il modale
            $('#editActivityModal').modal('show');
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            alert('Errore nel recuperare i dettagli dell\'attività: ' + error);
        }
    });
});

// AGGIUNGI questa nuova funzione nel tuo project-edit.js:

function loadProjectPartners(selectedPartnerId = null) {
    const partnerSelect = $('#editActivityModalBody select[name="responsible_partner_id"]');
    
    // Ottieni l'ID del progetto dalla URL
    const urlParams = new URLSearchParams(window.location.search);
    const projectId = urlParams.get('id');
    
    if (!projectId) {
        console.error('Project ID not found in URL');
        partnerSelect.html('<option value="">Error: Project ID not found</option>');
        return;
    }
    
    // Carica i partner tramite AJAX
    $.ajax({
        url: `../api/get_project_partners.php?project_id=${projectId}`,
        method: 'GET',
        dataType: 'json',
        success: function(partners) {
            partnerSelect.empty();
            partnerSelect.append('<option value="">Select Partner...</option>');
            
            if (partners && partners.length > 0) {
                partners.forEach(partner => {
                    const selected = (partner.partner_id == selectedPartnerId) ? 'selected' : '';
                    const orgName = partner.organization || partner.name || 'Unknown';
                    const country = partner.country || 'N/A';
                    partnerSelect.append(`<option value="${partner.partner_id}" ${selected}>${orgName} (${country})</option>`);
                });
                
                console.log('Partners loaded successfully:', partners.length);
            } else {
                partnerSelect.append('<option value="">No partners found</option>');
                console.warn('No partners found for project');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading partners:', status, error);
            partnerSelect.html('<option value="">Error loading partners</option>');
            
            // Fallback: prova a usare i partner già presenti nella pagina
            loadPartnersFromPage(selectedPartnerId);
        }
    });
}

// AGGIUNGI questa funzione di fallback nel tuo project-edit.js:

function loadPartnersFromPage(selectedPartnerId = null) {
    const partnerSelect = $('#editActivityModalBody select[name="responsible_partner_id"]');
    
    console.log('Trying fallback: loading partners from page');
    
    // Cerca i partner nelle dropdown esistenti nella pagina
    const existingOptions = $('#lead_partner_id option, select[name="responsible_partner_id"] option').not('[value=""]');
    
    if (existingOptions.length > 0) {
        partnerSelect.empty();
        partnerSelect.append('<option value="">Select Partner...</option>');
        
        const addedPartners = new Set();
        
        existingOptions.each(function() {
            const value = $(this).val();
            const text = $(this).text();
            
            if (value && !addedPartners.has(value)) {
                addedPartners.add(value);
                const selected = (value == selectedPartnerId) ? 'selected' : '';
                partnerSelect.append(`<option value="${value}" ${selected}>${text}</option>`);
            }
        });
        
        console.log('Fallback loaded partners:', addedPartners.size);
    } else {
        partnerSelect.html('<option value="">No partners available</option>');
        console.error('No partners found anywhere on the page');
    }
}
    $(document).on('click', '.btn-delete-activity', function() {
        const activityId = $(this).data('activity-id');
        if (confirm('Are you sure you want to delete this activity?')) {
            submitPostAction('delete_activity', { activity_id: activityId });
        }
    });

    // --- MILESTONE MANAGEMENT ---
// SOSTITUISCI la funzione .btn-edit-milestone nel tuo project-edit.js con questa:

$(document).on('click', '.btn-edit-milestone', function() {
    const milestoneId = $(this).data('milestone-id');
    
    $.ajax({
        url: `../api/get_milestone_details.php?id=${milestoneId}`,
        method: 'GET',
        dataType: 'json',
        success: function(milestone) {
            if (milestone.error) {
                alert(milestone.error);
                return;
            }
            
            let formHtml = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Milestone Name</label>
                            <input type="text" name="name" class="form-control" value="${milestone.name || ''}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Due Date</label>
                            <input type="date" name="due_date" class="form-control" value="${milestone.due_date || ''}" required>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="2">${milestone.description || ''}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Work Package</label>
                            <select name="work_package_id" class="form-control">
                                <option value="">Loading work packages...</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="overdue">Overdue</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Completed Date</label>
                    <input type="date" name="completed_date" class="form-control" value="${milestone.completed_date || ''}">
                </div>
            `;
            
            // Popola il contenuto del modale
            $('#editMilestoneModalBody').html(formHtml);
            
            // CHIAVE: Imposta l'ID della milestone nel campo hidden
            $('#edit_milestone_id').val(milestoneId);
            
            // Imposta lo status selezionato
            $('#editMilestoneModalBody select[name="status"]').val(milestone.status);
            
            // Carica i work packages del progetto
            loadProjectWorkPackages(milestone.work_package_id);
            
            // Mostra il modale
            $('#editMilestoneModal').modal('show');
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error loading milestone:', status, error);
            alert('Errore nel recuperare i dettagli della milestone: ' + error);
        }
    });
});

// AGGIUNGI questa nuova funzione nel tuo project-edit.js:

function loadProjectWorkPackages(selectedWpId = null) {
    const wpSelect = $('#editMilestoneModalBody select[name="work_package_id"]');
    
    // Ottieni l'ID del progetto dalla URL
    const urlParams = new URLSearchParams(window.location.search);
    const projectId = urlParams.get('id');
    
    if (!projectId) {
        console.error('Project ID not found in URL');
        wpSelect.html('<option value="">Error: Project ID not found</option>');
        return;
    }
    
    // Carica i work packages tramite AJAX
    $.ajax({
        url: `../api/get_project_work_packages.php?project_id=${projectId}`,
        method: 'GET',
        dataType: 'json',
        success: function(workPackages) {
            wpSelect.empty();
            wpSelect.append('<option value="">-- No specific WP --</option>');
            
            if (workPackages && workPackages.length > 0) {
                workPackages.forEach(wp => {
                    const selected = (wp.id == selectedWpId) ? 'selected' : '';
                    wpSelect.append(`<option value="${wp.id}" ${selected}>${wp.wp_number}: ${wp.name}</option>`);
                });
                
                console.log('Work packages loaded successfully:', workPackages.length);
            } else {
                console.warn('No work packages found for project');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading work packages:', status, error);
            
            // Fallback: carica dai work packages già presenti nella pagina
            loadWorkPackagesFromPage(selectedWpId);
        }
    });
}

// AGGIUNGI questa funzione di fallback nel tuo project-edit.js:

function loadWorkPackagesFromPage(selectedWpId = null) {
    const wpSelect = $('#editMilestoneModalBody select[name="work_package_id"]');
    
    console.log('Trying fallback: loading work packages from page');
    
    // Cerca i work packages nelle dropdown esistenti nella pagina
    const existingWpOptions = $('#lead_partner_id option, select[name="work_package_id"] option').not('[value=""]');
    
    wpSelect.empty();
    wpSelect.append('<option value="">-- No specific WP --</option>');
    
    // Prova a estrarre work packages dalle cards visibili nella pagina
    const wpCards = $('.work-package-card, .wp-card');
    if (wpCards.length > 0) {
        wpCards.each(function() {
            const wpId = $(this).data('wp-id');
            const wpTitle = $(this).find('h6, .wp-title, .card-title').first().text().trim();
            
            if (wpId && wpTitle) {
                const selected = (wpId == selectedWpId) ? 'selected' : '';
                wpSelect.append(`<option value="${wpId}" ${selected}>${wpTitle}</option>`);
            }
        });
        
        console.log('Fallback loaded work packages from cards:', wpCards.length);
    } else {
        console.error('No work packages found anywhere on the page');
    }
}

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
    
    // NOMI DEI CAMPI CORRETTI per allinearsi con il PHP
    formHtml = `
        <div class="row">
            <div class="col-md-3"><div class="form-group"><label>WP Number</label><input type="text" name="wp_number" class="form-control" value="${wp.wp_number || ''}" required></div></div>
            <div class="col-md-9"><div class="form-group"><label>Work Package Name</label><input type="text" name="wp_name" class="form-control" value="${wp.name || ''}" required></div></div>
        </div>
        <div class="form-group"><label>Description</label><textarea name="wp_description" class="form-control" rows="3">${wp.description || ''}</textarea></div>
        <div class="row">
            <div class="col-md-4"><div class="form-group"><label>Lead Partner</label><select name="lead_partner_id" class="form-control"></select></div></div>
            <div class="col-md-4"><div class="form-group"><label>Budget (€)</label><input type="number" name="wp_budget" class="form-control" value="${wp.budget || ''}" step="0.01" min="0"></div></div>
            <div class="col-md-4"><div class="form-group"><label>Status</label><select name="status" class="form-control"></select></div></div>
        </div>
        <div class="row">
            <div class="col-md-4"><div class="form-group"><label>Start Date</label><input type="date" name="wp_start_date" class="form-control" value="${wp.start_date || ''}" required></div></div>
            <div class="col-md-4"><div class="form-group"><label>End Date</label><input type="date" name="wp_end_date" class="form-control" value="${wp.end_date || ''}" required></div></div>
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
    
    // IMPORTANTE: Imposta l'ID del work package nel campo hidden
    $('#edit_wp_id').val(id);
}

                $(modalSelector).modal('show');
            },
            error: function() {
                alert('Failed to fetch details.');
            }
        });
    }

    // AGGIUNGERE ALLA FINE del file assets/js/pages/project-edit.js
// Dopo la chiusura di $(document).ready(function() { ... });

// --- GOOGLE GROUPS FUNCTIONALITY ---
$(document).ready(function() {
    
    // Google Groups URL validation
    $('#google_groups_url').on('blur', function() {
        const url = $(this).val().trim();
        const statusIndicator = $('.groups-status');
        const testButton = $('.btn-groups-test');
        
        if (url) {
            if (isValidGoogleGroupsUrl(url)) {
                $(this).removeClass('is-invalid').addClass('is-valid');
                statusIndicator.removeClass('not-configured').addClass('configured')
                    .html('<i class="nc-icon nc-check-2"></i> URL Valido');
                testButton.prop('disabled', false);
            } else {
                $(this).removeClass('is-valid').addClass('is-invalid');
                statusIndicator.removeClass('configured').addClass('not-configured')
                    .html('<i class="nc-icon nc-simple-remove"></i> URL Non Valido');
                testButton.prop('disabled', true);
            }
        } else {
            $(this).removeClass('is-valid is-invalid');
            statusIndicator.removeClass('configured').addClass('not-configured')
                .html('<i class="nc-icon nc-simple-remove"></i> Non configurato');
            testButton.prop('disabled', true);
        }
    });

    // Google Groups form validation
    $('#googleGroupsForm').on('submit', function(e) {
        const url = $('#google_groups_url').val().trim();
        
        if (url && !isValidGoogleGroupsUrl(url)) {
            e.preventDefault();
            alert('Inserire un URL Google Groups valido.\nFormato corretto: https://groups.google.com/g/nome-gruppo');
            $('#google_groups_url').focus();
            return false;
        }
        
        // Disable submit button to prevent double submission
        $(this).find('button[type="submit"]').prop('disabled', true).html(
            '<i class="nc-icon nc-refresh-69 spin"></i> Salvando...'
        );
    });

    // Test Google Groups link functionality
    $(document).on('click', '.btn-groups-test', function(e) {
        e.preventDefault();
        const url = $('#google_groups_url').val().trim();
        
        if (url && isValidGoogleGroupsUrl(url)) {
            // Open in new window/tab
            window.open(url, '_blank', 'noopener,noreferrer');
            
            // Optional: track the test action
            console.log('Google Groups link tested:', url);
        } else {
            alert('URL Google Groups non valido o vuoto');
        }
    });

    // Auto-save for Google Groups URL (integrates with existing auto-save)
    $('#google_groups_url').addClass('auto-save');

    // Initialize Google Groups status on page load
    const initialUrl = $('#google_groups_url').val().trim();
    if (initialUrl) {
        $('#google_groups_url').trigger('blur');
    }

});

// Utility function to validate Google Groups URL
function isValidGoogleGroupsUrl(url) {
    if (!url) return false;
    
    // Check if it's a valid URL
    try {
        const urlObj = new URL(url);
        
        // Check if it's a Google Groups URL
        if (!urlObj.hostname.includes('groups.google.com')) {
            return false;
        }
        
        // Check for common Google Groups URL patterns
        const validPatterns = [
            /^https:\/\/groups\.google\.com\/g\/[a-zA-Z0-9_-]+/,  // /g/group-name
            /^https:\/\/groups\.google\.com\/forum\/#!forum\/[a-zA-Z0-9_-]+/, // legacy format
            /^https:\/\/groups\.google\.com\/d\/forum\/[a-zA-Z0-9_-]+/ // another format
        ];
        
        return validPatterns.some(pattern => pattern.test(url));
        
    } catch (e) {
        return false;
    }
}

// Function to show Google Groups status message
function showGroupsMessage(message, type = 'info') {
    // Create or update status message
    let messageDiv = $('#google-groups-message');
    if (messageDiv.length === 0) {
        messageDiv = $('<div id="google-groups-message" class="alert mt-2" style="display:none;"></div>');
        $('#google_groups_url').closest('.form-group').append(messageDiv);
    }
    
    // Set message type and content
    messageDiv.removeClass('alert-success alert-danger alert-warning alert-info')
        .addClass('alert-' + type)
        .html(message)
        .fadeIn();
    
    // Auto-hide after 3 seconds
    setTimeout(function() {
        messageDiv.fadeOut();
    }, 3000);
}

// Add to existing form validation (extend the existing form validation)
const originalFormValidation = $('#basicDetailsForm').data('events');
$('#googleGroupsForm').on('submit', function(e) {
    // Leverage existing form validation patterns
    const form = $(this);
    if (!form[0].checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
        form.addClass('was-validated');
    }
});
});

