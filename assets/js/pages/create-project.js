/* ===================================================================
 *  PAGE-SPECIFIC SCRIPTS FOR: Create Project Page
 * =================================================================== */

document.addEventListener('DOMContentLoaded', function() {
    const createProjectForm = document.getElementById('createProjectForm');
    if (createProjectForm) {
        createProjectForm.addEventListener('submit', function(e) {
            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = new Date(document.getElementById('end_date').value);
            const budget = parseFloat(document.getElementById('budget').value);
            const leadBudget = parseFloat(document.getElementById('lead_partner_budget').value);
            
            if (endDate <= startDate) {
                e.preventDefault();
                alert('End date must be after start date.');
                return;
            }
            
            if (leadBudget > budget) {
                e.preventDefault();
                alert('Lead partner budget cannot exceed total project budget.');
                return;
            }
        });
    }
});
