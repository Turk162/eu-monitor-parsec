/* ===================================================================
 *  JavaScript per Add Project Work Packages & Activities (Updated)
 *  Gestisce budget per partner, WP dinamici e activities
 * =================================================================== */

let workPackageIndex = 0;

// ===================================================================
// FUNZIONI PER WORK PACKAGES
// ===================================================================

function addWorkPackage() {
    workPackageIndex++;
    
    const container = document.getElementById('work-packages-container');
    const template = container.querySelector('.work-package-item').cloneNode(true);
    
    // Aggiorna gli indici nel nuovo template
    updateWorkPackageIndices(template, workPackageIndex);
    
    // Pulisci i valori del form
    clearFormValues(template);
    
    // Mostra il pulsante remove
    const removeBtn = template.querySelector('.remove-wp-btn');
    if (removeBtn) {
        removeBtn.style.display = 'inline-block';
    }
    
    // Resetta le activities al template base
    resetActivitiesContainer(template);
    
    container.appendChild(template);
    updateWorkPackageNumbers();
    
    // Aggiungi event listeners per i budget
    addBudgetCalculationListeners(template);
}

function removeWorkPackage(button) {
    const workPackageItem = button.closest('.work-package-item');
    if (workPackageItem) {
        workPackageItem.remove();
        updateWorkPackageNumbers();
    }
}

function updateWorkPackageIndices(template, newIndex) {
    // Aggiorna tutti gli attributi name che contengono work_packages[X]
    const inputs = template.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        if (input.name) {
            input.name = input.name.replace(/work_packages\[\d+\]/, `work_packages[${newIndex}]`);
        }
    });
    
    // Aggiorna data-wp-index
    template.setAttribute('data-wp-index', newIndex);
}

function updateWorkPackageNumbers() {
    const workPackages = document.querySelectorAll('.work-package-item');
    workPackages.forEach((wp, index) => {
        const numberSpan = wp.querySelector('.wp-number');
        if (numberSpan) {
            numberSpan.textContent = index + 1;
        }
    });
}

// ===================================================================
// FUNZIONI PER ACTIVITIES
// ===================================================================

function addActivity(button) {
    const activitiesContainer = button.closest('.activities-section').querySelector('.activities-container');
    const workPackageItem = button.closest('.work-package-item');
    const wpIndex = workPackageItem.getAttribute('data-wp-index');
    
    const template = activitiesContainer.querySelector('.activity-item').cloneNode(true);
    const activityIndex = activitiesContainer.children.length;
    
    // Aggiorna gli indici dell'activity
    updateActivityIndices(template, wpIndex, activityIndex);
    
    // Pulisci i valori
    clearFormValues(template);
    
    // Mostra il pulsante remove
    const removeBtn = template.querySelector('.remove-activity-btn');
    if (removeBtn) {
        removeBtn.style.display = 'inline-block';
    }
    
    activitiesContainer.appendChild(template);
    updateActivityNumbers(activitiesContainer);
}

function removeActivity(button) {
    const activityItem = button.closest('.activity-item');
    const activitiesContainer = activityItem.closest('.activities-container');
    
    if (activityItem) {
        activityItem.remove();
        updateActivityNumbers(activitiesContainer);
    }
}

function updateActivityIndices(template, wpIndex, activityIndex) {
    const inputs = template.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        if (input.name) {
            input.name = input.name.replace(
                /work_packages\[\d+\]\[activities\]\[\d+\]/,
                `work_packages[${wpIndex}][activities][${activityIndex}]`
            );
        }
    });
    
    template.setAttribute('data-activity-index', activityIndex);
}

function updateActivityNumbers(activitiesContainer) {
    const activities = activitiesContainer.querySelectorAll('.activity-item');
    activities.forEach((activity, index) => {
        const numberSpan = activity.querySelector('.activity-number');
        if (numberSpan) {
            numberSpan.textContent = index + 1;
        }
    });
}

function resetActivitiesContainer(workPackageTemplate) {
    const activitiesContainer = workPackageTemplate.querySelector('.activities-container');
    
    // Rimuovi tutte le activities tranne la prima
    const activities = activitiesContainer.querySelectorAll('.activity-item');
    for (let i = 1; i < activities.length; i++) {
        activities[i].remove();
    }
    
    // Resetta la prima activity
    const firstActivity = activitiesContainer.querySelector('.activity-item');
    if (firstActivity) {
        firstActivity.setAttribute('data-activity-index', '0');
        const numberSpan = firstActivity.querySelector('.activity-number');
        if (numberSpan) {
            numberSpan.textContent = '1';
        }
        
        // Nascondi il pulsante remove della prima activity
        const removeBtn = firstActivity.querySelector('.remove-activity-btn');
        if (removeBtn) {
            removeBtn.style.display = 'none';
        }
    }
}

// ===================================================================
// FUNZIONI PER BUDGET CALCULATION
// ===================================================================

function calculateWorkPackageBudget(workPackageElement) {
    const budgetInputs = workPackageElement.querySelectorAll('.partner-budget-input');
    let totalBudget = 0;
    
    budgetInputs.forEach(input => {
        const value = parseFloat(input.value) || 0;
        totalBudget += value;
    });
    
    const totalBudgetSpan = workPackageElement.querySelector('.wp-total-budget');
    if (totalBudgetSpan) {
        totalBudgetSpan.textContent = totalBudget.toLocaleString('it-IT', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    return totalBudget;
}

function addBudgetCalculationListeners(workPackageElement) {
    const budgetInputs = workPackageElement.querySelectorAll('.partner-budget-input');
    
    budgetInputs.forEach(input => {
        input.addEventListener('input', function() {
            calculateWorkPackageBudget(workPackageElement);
        });
        
        input.addEventListener('blur', function() {
            // Formatta il valore quando l'utente esce dal campo
            const value = parseFloat(this.value) || 0;
            this.value = value.toFixed(2);
        });
    });
}

// ===================================================================
// FUNZIONI UTILITY
// ===================================================================

function clearFormValues(element) {
    const inputs = element.querySelectorAll('input, textarea');
    inputs.forEach(input => {
        if (input.type !== 'button' && input.type !== 'submit') {
            input.value = '';
        }
    });
    
    const selects = element.querySelectorAll('select');
    selects.forEach(select => {
        select.selectedIndex = 0;
    });
}

// ===================================================================
// VALIDAZIONE FORM
// ===================================================================

function validateForm() {
    let isValid = true;
    const errors = [];
    
    // Controlla che ci sia almeno un work package
    const workPackages = document.querySelectorAll('.work-package-item');
    if (workPackages.length === 0) {
        errors.push('È necessario aggiungere almeno un Work Package');
        isValid = false;
    }
    
    // Controlla ogni work package
    workPackages.forEach((wp, index) => {
        const wpNumber = wp.querySelector('input[name*="wp_number"]').value.trim();
        const wpName = wp.querySelector('input[name*="name"]:not([name*="activities"])').value.trim();
        
        if (!wpNumber) {
            errors.push(`Work Package ${index + 1}: il numero del WP è obbligatorio`);
            isValid = false;
        }
        
        if (!wpName) {
            errors.push(`Work Package ${index + 1}: il nome del WP è obbligatorio`);
            isValid = false;
        }
        
        // Controlla se ci sono duplicati nel numero WP
        const otherWPs = Array.from(workPackages).filter((otherWP, otherIndex) => otherIndex !== index);
        const duplicateWP = otherWPs.find(otherWP => {
            const otherWpNumber = otherWP.querySelector('input[name*="wp_number"]').value.trim();
            return otherWpNumber && otherWpNumber === wpNumber;
        });
        
        if (duplicateWP) {
            errors.push(`Work Package ${index + 1}: il numero "${wpNumber}" è già utilizzato`);
            isValid = false;
        }
        
        // Controlla le activities
        const activities = wp.querySelectorAll('.activity-item');
        const activitiesWithNames = Array.from(activities).filter(activity => {
            const activityName = activity.querySelector('input[name*="[name]"]').value.trim();
            return activityName.length > 0;
        });
        
        if (activitiesWithNames.length === 0) {
            errors.push(`Work Package ${index + 1}: è necessario aggiungere almeno un'attività`);
            isValid = false;
        }
    });
    
    // Mostra errori se presenti
    if (errors.length > 0) {
        alert('Errori di validazione:\n\n' + errors.join('\n'));
    }
    
    return isValid;
}

// ===================================================================
// INIZIALIZZAZIONE QUANDO LA PAGINA È CARICATA
// ===================================================================

document.addEventListener('DOMContentLoaded', function() {
    // Nasconde i pulsanti remove per i primi elementi
    const firstWpRemoveBtn = document.querySelector('.work-package-item:first-child .remove-wp-btn');
    if (firstWpRemoveBtn) {
        firstWpRemoveBtn.style.display = 'none';
    }
    
    const firstActivityRemoveBtn = document.querySelector('.activity-item:first-child .remove-activity-btn');
    if (firstActivityRemoveBtn) {
        firstActivityRemoveBtn.style.display = 'none';
    }
    
    // Aggiungi listeners per il calcolo dei budget su tutti i WP esistenti
    const allWorkPackages = document.querySelectorAll('.work-package-item');
    allWorkPackages.forEach(wp => {
        addBudgetCalculationListeners(wp);
        calculateWorkPackageBudget(wp); // Calcola il budget iniziale
    });
    
    // Aggiungi validazione al form
    const form = document.getElementById('workPackagesForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
            }
        });
    }
    
    // Formattazione automatica dei campi budget
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('partner-budget-input')) {
            // Rimuovi caratteri non numerici eccetto punto e virgola
            let value = e.target.value.replace(/[^0-9.,]/g, '');
            // Sostituisci virgola con punto per la gestione decimale
            value = value.replace(',', '.');
            e.target.value = value;
        }
    });
});

// ===================================================================
// FUNZIONI GLOBALI ACCESSIBILI DAL HTML
// ===================================================================

// Rendi le funzioni disponibili globalmente per gli onclick nel HTML
window.addWorkPackage = addWorkPackage;
window.removeWorkPackage = removeWorkPackage;
window.addActivity = addActivity;
window.removeActivity = removeActivity;