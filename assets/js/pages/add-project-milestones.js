/* ===================================================================
 *  PAGE-SPECIFIC SCRIPTS FOR: Add Project Milestones
 * =================================================================== */

// Counter for unique milestone fields
let msCounter = 1;

/**
 * Clones the first milestone form item, updates its indices, and appends it to the container.
 */
function addMilestone() {
    const container = document.getElementById('milestonesContainer');
    if (!container) return;

    const template = container.querySelector('.milestone-item');
    if (!template) return;

    const clone = template.cloneNode(true);

    // Update the visual milestone number
    clone.querySelector('.ms-number').textContent = msCounter + 1;

    // Update the name attributes for all form elements to ensure they are submitted as a unique array item
    const inputs = clone.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        if (input.name) {
            input.name = input.name.replace(/milestones\[\d+\]/, `milestones[${msCounter}]`);
        }
        // Clear the value of the cloned input
        if (input.type !== 'button') {
            input.value = '';
        }
    });

    // Show the remove button on the new item
    const removeButton = clone.querySelector('.remove-btn');
    if(removeButton) {
        removeButton.style.display = 'inline-block';
    }

    container.appendChild(clone);
    msCounter++;
}

/**
 * Removes the parent .milestone-item of the clicked button.
 * @param {HTMLElement} button - The remove button that was clicked.
 */
function removeMilestone(button) {
    const item = button.closest('.milestone-item');
    if (item) {
        item.remove();
    }
}

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    // The first item should not have a remove button visible
    const firstRemoveBtn = document.querySelector('.milestone-item[data-ms-index="0"] .remove-btn');
    if (firstRemoveBtn) {
        firstRemoveBtn.style.display = 'none';
    }
});