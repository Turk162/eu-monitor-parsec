/* ===================================================================
 *  PAGE-SPECIFIC SCRIPTS FOR: Project Detail Page
 * =================================================================== */

$(document).ready(function() {
    // Initialize Bootstrap tooltips for progress circles
    $('.progress-circle').tooltip({
        title: function() {
            return 'Overall project completion: ' + $(this).text();
        },
        placement: 'top'
    });

    
    // --- Tab functionality ---
    // The logic to remember the last tab has been removed to always default to Overview.
});

/**
 * Confirms and handles the deletion of a project.
 * This function now creates a dynamic form to submit the request via POST,
 * including a CSRF token for security.
 * 
 * @param {number} projectId - The ID of the project to delete.
 * @param {string} projectName - The name of the project for the confirmation dialog.
 * @param {string} csrfToken - The CSRF token to validate the request.
 */


function confirmDeleteProject(projectId, projectName, csrfToken) {
    const confirmationMessage = `Are you sure you want to DELETE the project "${projectName}"?\n\nThis action is irreversible and will remove all associated data, including work packages, activities, and reports.`;

    if (confirm(confirmationMessage)) {
        const promptConfirmation = prompt('To confirm, please type the word "DELETE" in all capital letters.');
        
        if (promptConfirmation === 'DELETE') {
            // Create a new form element dynamically
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete-project.php';

            // Create an input for the project ID
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'project_id';
            idInput.value = projectId;
            form.appendChild(idInput);

            // Create an input for the CSRF token
            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = 'csrf_token';
            tokenInput.value = csrfToken;
            form.appendChild(tokenInput);

            // Append the form to the body, submit it, and then remove it
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

        } else if (promptConfirmation !== null) {
            alert('Deletion cancelled. The confirmation text did not match.');
        }
    }
}

function openWPDetailsModal(wpId, wpNumber) {
    console.log('Apertura modale per WP ID:', wpId);
    
    $('#wpDetailsModal').modal('show');
    $('#wpDetailsModalLabel').html('<i class="nc-icon nc-layers-3 text-info"></i> ' + wpNumber + ' Details');
    
    // Mostra loading nel body della modale
    const modalBody = document.getElementById('wpDetailsModalBody');
    modalBody.innerHTML = `
        <div class="text-center py-4">
            <i class="nc-icon nc-refresh-02"></i>
            <p class="mt-2">Loading work package details...</p>
        </div>
    `;
    
    // Chiamata API
    $.ajax({
        url: '../api/get_work_package_details.php?id=' + wpId,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log('Dati ricevuti:', response);
            
            if (response.error) {
                modalBody.innerHTML = '<div class="alert alert-danger">' + response.error + '</div>';
            } else {
                populateWPDetailsModal(response, modalBody);
            }
        },
        error: function() {
            modalBody.innerHTML = '<div class="alert alert-danger">Errore di connessione</div>';
        }
    });
}

function populateWPDetailsModal(data, modalBody) {
    const wp = data.work_package;
    const activities = data.activities || [];
    const partnerBudgets = data.partner_budgets || [];
    const wp_total_budget = data.wp_total_budget || 0;
    
    modalBody.innerHTML = `
        <!-- WP Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="mb-2">${wp.wp_number} - ${wp.name}</h4>
                <p class="text-muted">${wp.description || 'Nessuna descrizione disponibile'}</p>
            </div>
        </div>
        
        <!-- WP Basic Info -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="info-card p-3 border rounded">
                    <h6><i class="nc-icon nc-single-02"></i> Lead Partner</h6>
                    <p class="mb-0">${wp.lead_partner_name || 'Non assegnato'}</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-card p-3 border rounded">
                    <h6><i class="nc-icon nc-badge"></i> Status</h6>
                    <p class="mb-0">${getStatusDisplay(wp.status)}</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-card p-3 border rounded">
                    <h6><i class="nc-icon nc-calendar-60"></i> Timeline</h6>
                    <p class="mb-0">${formatDateRange(wp.start_date, wp.end_date)}</p>
                </div>
            </div>
        </div>
        
        <!-- Budget Section -->
        <div class="row mb-4">
            <div class="col-12">
                <h6 style="color: #51CACF; margin-bottom: 15px;">
                    <i class="nc-icon nc-group-students"></i> Involved Partners
                </h6>
                ${generateBudgetBreakdown(partnerBudgets)}
                <div class="mt-3">
                    <strong>WP TOTAL Budget: €${formatNumber(wp_total_budget)}</strong>
                </div>
            </div>
        </div>
        
        <!-- Activities Section -->
        <div class="row">
            <div class="col-12">
                <h6 style="color: #51CACF; margin-bottom: 15px;">
                    <i class="nc-icon nc-paper"></i> Activities (${activities.length})
                </h6>
                <div class="activities-list">
                    ${generateActivitiesList(activities)}
                </div>
            </div>
        </div>
    `;
    
    // Aggiorna link "View All Activities"
    $('#wpDetailViewActivities').attr('href', 'activities.php?wp=' + wp.id);
}

// FUNZIONI HELPER
function getStatusDisplay(status) {
    const statusMap = {
        'not_started': '<span class="badge badge-secondary">Non Iniziato</span>',
        'in_progress': '<span class="badge badge-primary">In Corso</span>',
        'completed': '<span class="badge badge-success">Completato</span>',
        'delayed': '<span class="badge badge-warning">In Ritardo</span>'
    };
    return statusMap[status] || status;
}

function formatDateRange(startDate, endDate) {
    const start = startDate ? new Date(startDate).toLocaleDateString('it-IT') : 'N/A';
    const end = endDate ? new Date(endDate).toLocaleDateString('it-IT') : 'N/A';
    return start + ' - ' + end;
}

function formatNumber(number) {
    return new Intl.NumberFormat('it-IT').format(number || 0);
}

function generateBudgetBreakdown(partnerBudgets) {
    if (partnerBudgets.length === 0) {
        return '<p class="text-muted">No partners are assigned to this Work Package.</p>';
    }
    
    const budgetRows = partnerBudgets.map(partner => {
        const roleTag = partner.role === 'coordinator' ? 
            '<span class="badge badge-primary ml-2">Coordinator</span>' : '';
        
        return `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="partner-budget-item p-3 border rounded">
                    <div>
                        <strong>${partner.partner_name}</strong>${roleTag}
                        <br><small class="text-muted">${partner.country}</small>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    return `
        <div class="row">
            ${budgetRows}
        </div>
    `;
}

function generateActivitiesList(activities) {
    if (activities.length === 0) {
        return '<p class="text-muted">Nessuna attività associata</p>';
    }
    
    return activities.map(activity => `
        <div class="activity-item border-bottom py-2">
            <div class="d-flex justify-content-between">
                <div>
                    <strong>${activity.activity_number || ''} - ${activity.name}</strong><br>
                    <small class="text-muted">Responsabile: ${activity.responsible_partner_name || 'Non assegnato'}</small>
                </div>
                <div>
                    ${getStatusDisplay(activity.status)}
                </div>
            </div>
        </div>
    `).join('');
}