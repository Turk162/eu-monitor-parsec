/* ===================================================================
 *  PROJECT EDIT - JAVASCRIPT COMPLETO
 *  EU Project Manager - Paper Dashboard Template
 *  
 *  Gestisce:
 *  - Auto-save dei campi
 *  - Calcolo durata progetto
 *  - Gestione partners
 *  - Work packages con budget per partner
 *  - Activities (senza budget)
 *  - Milestones
 *  - Upload file
 *  - Validazione form
 *  - Google Groups integration
 * =================================================================== */

$(document).ready(function() {

    // ===================================================================
    // AUTO-SAVE FUNCTIONALITY
    // Salva automaticamente i campi dopo 2 secondi di inattività
    // ===================================================================
    
    let autoSaveTimeout;
    
    $('.auto-save').on('input', function() {
        clearTimeout(autoSaveTimeout);
        const field = $(this).attr('name');
        const value = $(this).val();
        
        autoSaveTimeout = setTimeout(function() {
            $.ajax({
                url: '', // Post alla stessa pagina
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
                },
                error: function() {
                    console.log('Auto-save failed for field:', field);
                }
            });
        }, 2000);
    });

    /**
     * Mostra l'indicatore di auto-save per 2.5 secondi
     */
    function showAutoSaveIndicator() {
        const indicator = $('#autoSaveIndicator');
        indicator.addClass('show');
        setTimeout(function() {
            indicator.removeClass('show');
        }, 2500);
    }

    // ===================================================================
    // CALCOLO DINAMICO DURATA PROGETTO
    // Aggiorna automaticamente la durata quando cambiano le date
    // ===================================================================
    
    function updateProjectDuration() {
        try {
            const startDate = new Date($('#start_date').val());
            const endDate = new Date($('#end_date').val());
            
            if (!isNaN(startDate) && !isNaN(endDate) && endDate > startDate) {
                const timeDiff = endDate.getTime() - startDate.getTime();
                const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));
                const monthsDiff = (endDate.getFullYear() - startDate.getFullYear()) * 12 + 
                                 (endDate.getMonth() - startDate.getMonth());
                $('#projectDuration').text(`${daysDiff} days (~${monthsDiff} months)`);
            } else {
                $('#projectDuration').text('Invalid date range');
            }
        } catch (e) {
            $('#projectDuration').text('-');
        }
    }
    
    // Aggiorna durata quando cambiano le date
    $('#start_date, #end_date').on('change', updateProjectDuration);

    // ===================================================================
    // VALIDAZIONE FORM GENERALE
    // Applica validazione HTML5 e disabilita pulsanti durante submit
    // ===================================================================
    
    $('#basicDetailsForm, #addPartnerForm, #workPackageForm, #addMilestoneForm').on('submit', function(e) {
        const form = $(this);
        if (!form[0].checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            form.addClass('was-validated');
        } else {
            // Disabilita pulsante per prevenire doppi submit
            form.find('button[type="submit"]').prop('disabled', true)
                .html('<i class="nc-icon nc-refresh-69 spin"></i> Processing...');
        }
    });

    // ===================================================================
    // GESTIONE PARTNERS
    // Aggiunta e rimozione partner dal progetto
    // ===================================================================
    
    $(document).on('click', '.btn-delete-partner', function() {
        const partnerId = $(this).data('partner-id');
        const partnerName = $(this).data('partner-name');
        
        if (confirm(`Are you sure you want to remove "${partnerName}" from this project?`)) {
            submitPostAction('delete_partner', { partner_id: partnerId });
        }
    });

    // ===================================================================
    // GESTIONE WORK PACKAGES
    // Work packages con budget per partner
    // ===================================================================
    
    /**
     * Apre modal per modifica work package con gestione budget partner
     */
    $(document).on('click', '.btn-edit-wp', function() {
        const wpId = $(this).data('wp-id');
        loadEditWorkPackageModal(wpId);
        $('#editWorkPackageModal').modal('show');
    });

    /**
     * Elimina work package con conferma
     */
    $(document).on('click', '.btn-delete-wp', function() {
        const wpId = $(this).data('wp-id');
        const wpName = $(this).data('wp-name');
        if (confirm(`Delete WP "${wpName}"? This will also delete all its activities.`)) {
            submitPostAction('delete_work_package', { wp_id: wpId });
        }
    });

    // ===================================================================
    // GESTIONE ACTIVITIES (SENZA BUDGET)
    // Activities collegate ai work packages
    // ===================================================================
    
    /**
     * Apre modal per aggiungere activity
     */
    $(document).on('click', '.btn-add-activity', function() {
        const wpId = $(this).data('wp-id');
        loadAddActivityModal(wpId);
        $('#addActivityModal').modal('show');
    });

    /**
     * Apre modal per modificare activity (SENZA BUDGET)
     */
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
                
                // Form HTML senza campo budget
                let formHtml = `
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Activity Number</label>
                                <input type="text" name="activity_number" class="form-control" 
                                       value="${activity.activity_number || ''}">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Activity Name</label>
                                <input type="text" name="name" class="form-control" 
                                       value="${activity.name || ''}" required>
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
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="not_started" ${activity.status === 'not_started' ? 'selected' : ''}>Not Started</option>
                                    <option value="in_progress" ${activity.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                                    <option value="completed" ${activity.status === 'completed' ? 'selected' : ''}>Completed</option>
                                    <option value="delayed" ${activity.status === 'delayed' ? 'selected' : ''}>Delayed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <label>Start Date</label>
                            <input type="date" name="start_date" class="form-control" 
                                   value="${activity.start_date || ''}">
                        </div>
                        <div class="col-md-4">
                            <label>End Date</label>
                            <input type="date" name="end_date" class="form-control" 
                                   value="${activity.end_date || ''}">
                        </div>
                        <div class="col-md-4">
                            <label>Progress (%)</label>
                            <input type="number" name="progress" class="form-control" 
                                   min="0" max="100" step="0.1" value="${activity.progress || 0}">
                        </div>
                    </div>
                `;
                
                $('#editActivityModalBody').html(formHtml);
                $('#edit_activity_id').val(activityId);
                loadProjectPartners(activity.responsible_partner_id);
                $('#editActivityModal').modal('show');
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('Errore nel recuperare i dettagli dell\'attività: ' + error);
            }
        });
    });

    /**
     * Elimina activity con conferma
     */
    $(document).on('click', '.btn-delete-activity', function() {
        const activityId = $(this).data('activity-id');
        if (confirm('Are you sure you want to delete this activity?')) {
            submitPostAction('delete_activity', { activity_id: activityId });
        }
    });

    // ===================================================================
    // GESTIONE MILESTONES
    // Milestone collegate a progetti e work packages
    // ===================================================================
    
    /**
     * Apre modal per modificare milestone
     */
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
                                <input type="text" name="name" class="form-control" 
                                       value="${milestone.name || ''}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Due Date</label>
                                <input type="date" name="due_date" class="form-control" 
                                       value="${milestone.due_date || ''}" required>
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
                        <input type="date" name="completed_date" class="form-control" 
                               value="${milestone.completed_date || ''}">
                    </div>
                `;
                
                $('#editMilestoneModalBody').html(formHtml);
                $('#edit_milestone_id').val(milestoneId);
                $('#editMilestoneModalBody select[name="status"]').val(milestone.status);
                loadProjectWorkPackages(milestone.work_package_id);
                $('#editMilestoneModal').modal('show');
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error loading milestone:', status, error);
                alert('Errore nel recuperare i dettagli della milestone: ' + error);
            }
        });
    });

    /**
     * Elimina milestone con conferma
     */
    $(document).on('click', '.btn-delete-milestone', function() {
        const milestoneId = $(this).data('milestone-id');
        if (confirm('Are you sure you want to delete this milestone?')) {
            submitPostAction('delete_milestone', { milestone_id: milestoneId });
        }
    });

    // ===================================================================
    // GESTIONE UPLOAD FILE (DRAG & DROP)
    // Upload con drag&drop e progress bar
    // ===================================================================
    
    const uploadArea = $('.file-upload-area');
    
    // Eventi drag & drop
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
    
    // Upload tramite input file
    $('#fileInput').on('change', function() { 
        if (this.files.length > 0) uploadFiles(this.files); 
    });

    /**
     * Gestisce upload di file multipli con progress bar
     */
    function uploadFiles(files) {
        const formData = new FormData();
        formData.append('action', 'upload_files');
        
        for (let i = 0; i < files.length; i++) { 
            formData.append('files[]', files[i]); 
        }

        $('#uploadProgress').show();
        
        $.ajax({
            url: 'upload-project-files.php',
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
            error: () => { 
                $('#uploadProgress').hide(); 
                alert('Upload failed.'); 
            }
        });
    }

    // ===================================================================
    // GESTIONE TAB
    // Gestione tab con hash URL
    // ===================================================================
    
    // Mostra tab basato su hash URL
    if (window.location.hash) {
        $('#projectTabs a[href="' + window.location.hash + '"]').tab('show');
    }
    
    // Aggiorna hash quando cambia tab
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        window.location.hash = e.target.hash;
    });

    // ===================================================================
    // GOOGLE GROUPS INTEGRATION
    // Validazione e gestione URL Google Groups
    // ===================================================================
    
    // Validazione URL Google Groups
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

    // Test link Google Groups
    $(document).on('click', '.btn-groups-test', function(e) {
        e.preventDefault();
        const url = $('#google_groups_url').val().trim();
        
        if (url && isValidGoogleGroupsUrl(url)) {
            window.open(url, '_blank', 'noopener,noreferrer');
            console.log('Google Groups link tested:', url);
        } else {
            alert('URL Google Groups non valido o vuoto');
        }
    });

    // Auto-save per Google Groups URL
    $('#google_groups_url').addClass('auto-save');

    // Inizializza status Google Groups al caricamento
    const initialUrl = $('#google_groups_url').val().trim();
    if (initialUrl) {
        $('#google_groups_url').trigger('blur');
    }

    // ===================================================================
    // GESTIONE BUDGET WORK PACKAGES
    // Listeners per calcolo automatico budget partner
    // ===================================================================
    
    // Aggiungi listeners per il calcolo dei budget nei form WP esistenti
    const workPackageForm = document.getElementById('workPackageForm');
    if (workPackageForm) {
        addBudgetCalculationListeners(workPackageForm);
        calculateWorkPackageBudget(workPackageForm);
    }
    
    // Formattazione automatica dei campi budget
    $(document).on('input', '.partner-budget-input', function() {
        let value = this.value.replace(/[^0-9.,]/g, '');
        value = value.replace(',', '.');
        this.value = value;
        
        // Calcola il budget totale del WP
        const container = this.closest('.partner-budgets-section') || this.closest('form');
        if (container) {
            calculateWorkPackageBudget(container);
        }
    });

    // ===================================================================
    // UTILITY FUNCTIONS
    // Funzioni di utilità per operazioni comuni
    // ===================================================================
    
    /**
     * Crea e sottomette form POST per azioni semplici come eliminazioni
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
     * Prepara modal per aggiungere activity (verifica assenza campi budget)
     */
    function loadAddActivityModal(wpId) {
        document.getElementById('add_work_package_id').value = wpId;
        
        // Rimuovi eventuali campi budget rimasti
        const modalBody = document.querySelector('#addActivityModal .modal-body');
        const budgetFields = modalBody.querySelectorAll('input[name="budget"]');
        budgetFields.forEach(field => {
            const group = field.closest('.form-group') || field.closest('.col-md-6');
            if (group) group.remove();
        });
    }

    /**
     * Gestione modal generica con AJAX (per milestone e activity legacy)
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
                let selectElement = null;

                if (apiAction === 'get_activity_details') {
                    const activity = data;
                    // Form senza budget
                    formHtml = `
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Activity Number</label>
                                    <input type="text" name="activity_number" class="form-control" 
                                           value="${activity.activity_number || ''}">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>Activity Name</label>
                                    <input type="text" name="name" class="form-control" 
                                           value="${activity.name || ''}" required>
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
                                    <select name="responsible_partner_id" class="form-control"></select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status" class="form-control">
                                        <option value="not_started">Not Started</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                        <option value="delayed">Delayed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <label>Start Date</label>
                                <input type="date" name="start_date" class="form-control" 
                                       value="${activity.start_date || ''}">
                            </div>
                            <div class="col-md-4">
                                <label>End Date</label>
                                <input type="date" name="end_date" class="form-control" 
                                       value="${activity.end_date || ''}">
                            </div>
                            <div class="col-md-4">
                                <label>Progress (%)</label>
                                <input type="number" name="progress" class="form-control" 
                                       min="0" max="100" step="0.1" value="${activity.progress || 0}">
                            </div>
                        </div>
                    `;
                    
                    $(modalBodySelector).html(formHtml);

                    // Popola dropdown partner
                    selectElement = $(modalBodySelector + ' select[name="responsible_partner_id"]');
                    selectElement.append('<option value="">Select Partner...</option>');
                    window.projectData.allProjectPartners.forEach(p => {
                        selectElement.append(`<option value="${p.partner_id}">${p.organization} (${p.country})</option>`);
                    });
                    selectElement.val(activity.responsible_partner_id);
                    
                    // Imposta status
                    $(modalBodySelector + ' select[name="status"]').val(activity.status);
                    $('#edit_activity_id').val(id);
                    
                } else if (apiAction === 'get_milestone_details') {
                    const milestone = data;
                    formHtml = `
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Milestone Name</label>
                                    <input type="text" name="name" class="form-control" 
                                           value="${milestone.name || ''}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Due Date</label>
                                    <input type="date" name="due_date" class="form-control" 
                                           value="${milestone.due_date || ''}" required>
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
                                    <select name="work_package_id" class="form-control"></select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status" class="form-control"></select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Completed Date</label>
                            <input type="date" name="completed_date" class="form-control" 
                                   value="${milestone.completed_date || ''}">
                        </div>
                    `;
                    
                    $(modalBodySelector).html(formHtml);

                    // Popola dropdown WP
                    selectElement = $(modalBodySelector + ' select[name="work_package_id"]');
                    selectElement.append('<option value="">-- No specific WP --</option>');
                    window.projectData.workPackages.forEach(wp => {
                        selectElement.append(`<option value="${wp.id}">${wp.wp_number}: ${wp.name}</option>`);
                    });
                    selectElement.val(milestone.work_package_id);

                    // Popola dropdown Status
                    selectElement = $(modalBodySelector + ' select[name="status"]');
                    const statusOptions = ['pending', 'completed', 'overdue'];
                    statusOptions.forEach(s => {
                        selectElement.append(`<option value="${s}">${s.charAt(0).toUpperCase() + s.slice(1)}</option>`);
                    });
                    selectElement.val(milestone.status);
                    $('#edit_milestone_id').val(id);
                    
                } else if (apiAction === 'get_work_package_details') {
                    // Per work packages, usa la nuova funzione specializzata
                    $(modalSelector).modal('hide');
                    loadEditWorkPackageModal(id);
                    return;
                }

                $(modalSelector).modal('show');
            },
            error: function() {
                alert('Failed to fetch details.');
            }
        });
    }

}); // Fine $(document).ready

// ===================================================================
// FUNZIONI SPECIALIZZATE WORK PACKAGES
// Gestione work packages con budget per partner
// ===================================================================

/**
 * Carica e visualizza modal per modifica work package con budget partner
 */
function loadEditWorkPackageModal(wpId) {
    const wp = window.projectData.workPackages.find(w => w.id == wpId);
    if (!wp) {
        alert('Work package not found');
        return;
    }
    
    const modalBody = document.getElementById('editWorkPackageModalBody');
    
    // Genera HTML per budget partner
    let partnerBudgetsHtml = '';
    const allPartners = window.projectData.allProjectPartners;
    
    if (allPartners && allPartners.length > 0) {
        partnerBudgetsHtml = `
            <div class="partner-budgets-section">
                <h6 style="color: #51CACF; margin: 20px 0 15px 0;">
                    💰 Budget Allocation by Partner
                </h6>
                <div class="row partner-budget-grid">
        `;
        
        allPartners.forEach(partner => {
            // Trova budget esistente per questo partner
            const existingBudget = wp.partner_budgets ? 
                wp.partner_budgets.find(pb => pb.partner_id == partner.partner_id) : null;
            const budgetValue = existingBudget ? existingBudget.budget_allocated : '0.00';
            
            partnerBudgetsHtml += `
                <div class="col-md-6 col-lg-4">
                    <div class="form-group">
                        <label>
                            <strong>${escapeHtml(partner.organization)}</strong>
                            <small class="text-muted">(${escapeHtml(partner.country)})</small>
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">€</span>
                            </div>
                            <input type="number" 
                                   name="partner_budgets[${partner.partner_id}]" 
                                   class="form-control partner-budget-input" 
                                   step="0.01" 
                                   min="0" 
                                   value="${budgetValue}"
                                   data-partner-id="${partner.partner_id}">
                        </div>
                    </div>
                </div>
            `;
        });
        
                                partnerBudgetsHtml += `
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="alert alert-info" style="background-color: #e3f2fd; border: 1px solid #2196f3;">
                            <strong>Total WP Budget: €<span class="wp-total-budget">0.00</span></strong>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Genera HTML completo del modal
    modalBody.innerHTML = `
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label for="edit_wp_number" class="required-field">WP Number</label>
                    <input type="text" class="form-control" id="edit_wp_number" name="wp_number" 
                           value="${escapeHtml(wp.wp_number)}" required>
                </div>
            </div>
            <div class="col-md-9">
                <div class="form-group">
                    <label for="edit_wp_name" class="required-field">Work Package Name</label>
                    <input type="text" class="form-control" id="edit_wp_name" name="wp_name" 
                           value="${escapeHtml(wp.name)}" required>
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label for="edit_wp_description">Description</label>
            <textarea class="form-control" id="edit_wp_description" name="wp_description" 
                      rows="3">${escapeHtml(wp.description || '')}</textarea>
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="edit_lead_partner_id">Lead Partner</label>
                    <select class="form-control" id="edit_lead_partner_id" name="lead_partner_id">
                        <option value="">Select lead partner...</option>
                        ${allPartners.map(partner => `
                            <option value="${partner.partner_id}" 
                                    ${wp.lead_partner_id == partner.partner_id ? 'selected' : ''}>
                                ${escapeHtml(partner.organization)} (${escapeHtml(partner.country)})
                                ${partner.role === 'coordinator' ? ' - COORDINATOR' : ''}
                            </option>
                        `).join('')}
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="edit_wp_status">Status</label>
                    <select class="form-control" id="edit_wp_status" name="status">
                        <option value="not_started" ${wp.status === 'not_started' ? 'selected' : ''}>Not Started</option>
                        <option value="in_progress" ${wp.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                        <option value="completed" ${wp.status === 'completed' ? 'selected' : ''}>Completed</option>
                        <option value="delayed" ${wp.status === 'delayed' ? 'selected' : ''}>Delayed</option>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="edit_wp_progress">Progress (%)</label>
                    <input type="number" class="form-control" id="edit_wp_progress" name="progress" 
                           min="0" max="100" step="0.1" value="${wp.progress || 0}">
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="edit_wp_start_date">Start Date</label>
                    <input type="date" class="form-control" id="edit_wp_start_date" name="wp_start_date" 
                           value="${wp.start_date || ''}">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="edit_wp_end_date">End Date</label>
                    <input type="date" class="form-control" id="edit_wp_end_date" name="wp_end_date" 
                           value="${wp.end_date || ''}">
                </div>
            </div>
        </div>
        
        ${partnerBudgetsHtml}
    `;
    
    // Imposta ID work package nel form
    document.getElementById('edit_wp_id').value = wpId;
    
    // Cambia action per usare nuova gestione con budget
    const form = modalBody.closest('form');
    const actionInput = form.querySelector('input[name="action"]');
    if (actionInput) {
        actionInput.value = 'update_work_package_with_budgets';
    }
    
    // Aggiungi listeners per calcolo budget con delay
    setTimeout(() => {
        addBudgetCalculationListeners(modalBody);
        calculateWorkPackageBudget(modalBody);
    }, 100);
}

// ===================================================================
// FUNZIONI CALCOLO BUDGET PARTNER
// ===================================================================

/**
 * Calcola il budget totale del work package sommando i budget dei partner
 */
function calculateWorkPackageBudget(container) {
    const budgetInputs = container.querySelectorAll('.partner-budget-input');
    let totalBudget = 0;
    
    budgetInputs.forEach(input => {
        const value = parseFloat(input.value) || 0;
        totalBudget += value;
    });
    
    const totalBudgetSpan = container.querySelector('.wp-total-budget');
    if (totalBudgetSpan) {
        totalBudgetSpan.textContent = totalBudget.toLocaleString('it-IT', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    return totalBudget;
}

/**
 * Aggiunge event listeners per calcolo automatico budget
 */
function addBudgetCalculationListeners(container) {
    const budgetInputs = container.querySelectorAll('.partner-budget-input');
    
    budgetInputs.forEach(input => {
        input.addEventListener('input', function() {
            calculateWorkPackageBudget(container);
        });
        
        input.addEventListener('blur', function() {
            const value = parseFloat(this.value) || 0;
            this.value = value.toFixed(2);
        });
    });
}

// ===================================================================
// FUNZIONI CARICAMENTO DATI AJAX
// ===================================================================

/**
 * Carica partner del progetto per dropdown activities
 */
function loadProjectPartners(selectedPartnerId = null) {
    const partnerSelect = $('#editActivityModalBody select[name="responsible_partner_id"]');
    
    // Ottieni ID progetto da URL
    const urlParams = new URLSearchParams(window.location.search);
    const projectId = urlParams.get('id');
    
    if (!projectId) {
        console.error('Project ID not found in URL');
        partnerSelect.html('<option value="">Error: Project ID not found</option>');
        return;
    }
    
    // Carica partner via AJAX
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
            
            // Fallback: usa partner dalla pagina
            loadPartnersFromPage(selectedPartnerId);
        }
    });
}

/**
 * Fallback: carica partner dai dati già presenti nella pagina
 */
function loadPartnersFromPage(selectedPartnerId = null) {
    const partnerSelect = $('#editActivityModalBody select[name="responsible_partner_id"]');
    
    console.log('Trying fallback: loading partners from page');
    
    // Cerca partner nelle dropdown esistenti
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

/**
 * Carica work packages del progetto per dropdown milestones
 */
function loadProjectWorkPackages(selectedWpId = null) {
    const wpSelect = $('#editMilestoneModalBody select[name="work_package_id"]');
    
    // Ottieni ID progetto da URL
    const urlParams = new URLSearchParams(window.location.search);
    const projectId = urlParams.get('id');
    
    if (!projectId) {
        console.error('Project ID not found in URL');
        wpSelect.html('<option value="">Error: Project ID not found</option>');
        return;
    }
    
    // Carica work packages via AJAX
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
            
            // Fallback: carica da pagina
            loadWorkPackagesFromPage(selectedWpId);
        }
    });
}

/**
 * Fallback: carica work packages dai dati della pagina
 */
function loadWorkPackagesFromPage(selectedWpId = null) {
    const wpSelect = $('#editMilestoneModalBody select[name="work_package_id"]');
    
    console.log('Trying fallback: loading work packages from page');
    
    wpSelect.empty();
    wpSelect.append('<option value="">-- No specific WP --</option>');
    
    // Estrai work packages dalle cards visibili
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

// ===================================================================
// UTILITY FUNCTIONS GLOBALI
// ===================================================================

/**
 * Escape HTML per prevenire XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

/**
 * Valida URL Google Groups
 */
function isValidGoogleGroupsUrl(url) {
    if (!url) return false;
    
    try {
        const urlObj = new URL(url);
        
        if (!urlObj.hostname.includes('groups.google.com')) {
            return false;
        }
        
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

/**
 * Mostra messaggio di stato Google Groups
 */
function showGroupsMessage(message, type = 'info') {
    let messageDiv = $('#google-groups-message');
    if (messageDiv.length === 0) {
        messageDiv = $('<div id="google-groups-message" class="alert mt-2" style="display:none;"></div>');
        $('#google_groups_url').closest('.form-group').append(messageDiv);
    }
    
    messageDiv.removeClass('alert-success alert-danger alert-warning alert-info')
        .addClass('alert-' + type)
        .html(message)
        .fadeIn();
    
    setTimeout(function() {
        messageDiv.fadeOut();
    }, 3000);
}