/* ===================================================================
 *  PAGE-SPECIFIC SCRIPTS FOR: Add Project Work Packages
 * =================================================================== */

let wpCounter = 1;

function addWorkPackage() {
    const container = document.getElementById('workPackagesContainer');
    const template = container.querySelector('.wp-container');
    const clone = template.cloneNode(true);

    clone.dataset.wpIndex = wpCounter;
    clone.querySelector('.wp-number').textContent = wpCounter + 1;

    const inputs = clone.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        if (input.name) {
            input.name = input.name.replace(/work_packages\[\d+\]/, `work_packages[${wpCounter}]`);
        }
        if (input.type !== 'button') {
            input.value = '';
        }
    });

    const removeButton = clone.querySelector('.remove-wp');
    if (removeButton) {
        removeButton.style.display = 'inline-block';
    }

    // Reset activities
    const activitiesContainer = clone.querySelector('.activities-container');
    while (activitiesContainer.children.length > 1) {
        activitiesContainer.removeChild(activitiesContainer.lastChild);
    }
    const firstActivity = activitiesContainer.querySelector('.activity-item');
    if (firstActivity) {
        firstActivity.dataset.activityIndex = 0;
        firstActivity.querySelector('.activity-number').textContent = 1;
        const activityInputs = firstActivity.querySelectorAll('input, select, textarea');
        activityInputs.forEach(input => {
            if (input.name) {
                input.name = input.name.replace(/activities\[\d+\]/, 'activities[0]');
            }
        });
        const firstActivityRemove = firstActivity.querySelector('.remove-btn');
        if (firstActivityRemove) {
            firstActivityRemove.style.display = 'none';
        }
    }

    container.appendChild(clone);
    wpCounter++;
}

function removeWorkPackage(button) {
    const item = button.closest('.wp-container');
    if (item) {
        item.remove();
    }
}

function addActivity(button) {
    const activitiesContainer = button.previousElementSibling;
    const wpContainer = button.closest('.wp-container');
    const wpIndex = wpContainer.dataset.wpIndex;
    const activityIndex = activitiesContainer.children.length;

    const template = activitiesContainer.querySelector('.activity-item');
    const clone = template.cloneNode(true);

    clone.dataset.activityIndex = activityIndex;
    clone.querySelector('.activity-number').textContent = activityIndex + 1;

    const inputs = clone.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        if (input.name) {
            input.name = input.name.replace(/work_packages\[\d+\]\[activities\]\[\d+\]/, `work_packages[${wpIndex}][activities][${activityIndex}]`);
        }
        if (input.type !== 'button') {
            input.value = '';
        }
    });

    const removeButton = clone.querySelector('.remove-btn');
    if (removeButton) {
        removeButton.style.display = 'inline-block';
    }

    activitiesContainer.appendChild(clone);
}

function removeActivity(button) {
    const item = button.closest('.activity-item');
    if (item) {
        item.remove();
    }
}

function skipStep() {
    const form = document.getElementById('workPackagesForm');
    const projectId = form.querySelector('a[href*="project_id="]').href.split('=').pop();
    window.location.href = `add-project-milestones.php?project_id=${projectId}`;
}

document.addEventListener('DOMContentLoaded', function() {
    const firstWpRemove = document.querySelector('.wp-container[data-wp-index="0"] .remove-wp');
    if (firstWpRemove) {
        firstWpRemove.style.display = 'none';
    }

    const firstActivityRemove = document.querySelector('.activity-item[data-activity-index="0"] .remove-btn');
    if (firstActivityRemove) {
        firstActivityRemove.style.display = 'none';
    }
});