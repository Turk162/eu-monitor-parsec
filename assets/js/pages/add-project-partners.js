/* ===================================================================
 *  PAGE-SPECIFIC SCRIPTS FOR: Add Project Partners
 * =================================================================== */

// Counter for unique partner fields
let partnerCounter = 1;

/**
 * Clones the first partner form item, updates its indices, and appends it to the container.
 */
function addPartner() {
    const container = document.getElementById('partnersContainer');
    if (!container) return;

    const template = container.querySelector('.partner-item');
    if (!template) return;

    const clone = template.cloneNode(true);

    // Update the visual partner number
    clone.querySelector('.partner-number').textContent = partnerCounter + 1;

    // Update the name attributes for all form elements to ensure they are submitted as a unique array item
    const inputs = clone.querySelectorAll('select, input');
    inputs.forEach(input => {
        if (input.name) {
            input.name = input.name.replace(/partners\[\d+\]/, `partners[${partnerCounter}]`);
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
    partnerCounter++;
}

/**
 * Removes the parent .partner-item of the clicked button.
 * @param {HTMLElement} button - The remove button that was clicked.
 */
function removePartner(button) {
    const item = button.closest('.partner-item');
    if (item) {
        item.remove();
    }
}

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    // The first item should not have a remove button visible
    const firstRemoveBtn = document.querySelector('.partner-item[data-partner-index="0"] .remove-btn');
    if (firstRemoveBtn) {
        firstRemoveBtn.style.display = 'none';
    }
});
