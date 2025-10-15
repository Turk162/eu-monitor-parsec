/**
 * Project Files Upload - JavaScript
 * 
 * Gestisce la logica del form di upload, in particolare i campi condizionali.
 */

document.addEventListener('DOMContentLoaded', function() {

    const categorySelect = document.getElementById('file_category');
    const deliverableFields = document.getElementById('deliverable_fields');
    const wpSelect = document.getElementById('work_package_id');
    const activitySelect = document.getElementById('activity_id');
    const allActivities = Array.from(activitySelect.options);

    function toggleDeliverableFields() {
        if (categorySelect.value === 'deliverable') {
            deliverableFields.style.display = 'block';
            wpSelect.required = true;
        } else {
            deliverableFields.style.display = 'none';
            wpSelect.required = false;
        }
    }

    function filterActivities() {
        const selectedWpId = wpSelect.value;
        let currentActivityValue = activitySelect.value;

        // Clear activities
        activitySelect.innerHTML = '';

        // Add default option
        activitySelect.appendChild(allActivities[0]);

        // Filter and add relevant activities
        allActivities.forEach(option => {
            if (option.value && option.dataset.wpId === selectedWpId) {
                activitySelect.appendChild(option.cloneNode(true));
            }
        });

        // Restore selection if possible
        if (Array.from(activitySelect.options).some(opt => opt.value === currentActivityValue)) {
            activitySelect.value = currentActivityValue;
        } else {
            activitySelect.value = '';
        }
    }

    // Initial check
    toggleDeliverableFields();

    // Event Listeners
    categorySelect.addEventListener('change', toggleDeliverableFields);
    wpSelect.addEventListener('change', filterActivities);

    console.log('Project Files Upload page initialized');
});
