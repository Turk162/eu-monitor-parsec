/**
 * ===================================================================
 * MANAGE PARTNER BUDGETS - JAVASCRIPT
 * Handles real-time calculations and form interactions
 * ===================================================================
 */

$(document).ready(function() {
    // Initialize the page
    initializeBudgetPage();
    
    // Bind event handlers
    bindEventHandlers();
    
    // Calculate initial totals
    calculateAllTotals();
    
    // Add animations
    addPageAnimations();
});

/**
 * Initialize budget management page
 */
function initializeBudgetPage() {
    console.log('Budget Management page initialized');
    
    // Setup tooltips
    initializeTooltips();
    
    // Setup form validation
    initializeFormValidation();
    
    // Setup auto-save
    setupAutoSave();
    
    // Show any session messages
    showSessionMessages();
}

/**
 * Bind all event handlers
 */
function bindEventHandlers() {
    // Personnel cost calculations
    $(document).on('input', '.working-days, .daily-rate, .project-management-cost', handlePersonnelCalculation);
    
    // Travel cost input
    $(document).on('input', '.travel-total-amount', handleTravelCalculation);
    
    // Other costs
    $(document).on('input', '.other-costs', handleOtherCostsCalculation);
    
    // Form submission
    $('#budgetManagementForm').on('submit', handleFormSubmission);
    
    // Keyboard shortcuts
    $(document).on('keydown', handleKeyboardShortcuts);
    
    // Real-time validation
    $('.form-control').on('blur', validateField);
    
    // Clear travel entry functionality
    $(document).on('click', '.clear-travel-btn', clearTravelEntry);
}

/**
 * Handle personnel cost calculation
 */
function handlePersonnelCalculation(event) {
    const input = $(event.target);
    const budgetId = input.data('budget-id');
    
    console.log('Personnel calculation triggered for budget ID:', budgetId);
    
    if (!budgetId) {
        console.log('No budget ID found, skipping calculation');
        return;
    }
    
    const budgetSection = input.closest('.partner-budget-section');
    const personnelSection = budgetSection.find('.personnel-section');
    
    let total = 0;
    
    // Check if it's project management or standard
    const pmCostInput = personnelSection.find('.project-management-cost');
    const workingDaysInput = personnelSection.find('.working-days');
    const dailyRateInput = personnelSection.find('.daily-rate');
    
    if (pmCostInput.length > 0) {
        // Project management - flat rate
        total = parseFloat(pmCostInput.val()) || 0;
        console.log('Project management cost:', total);
    } else {
        // Standard - working days × daily rate
        const workingDays = parseFloat(workingDaysInput.val()) || 0;
        const dailyRate = parseFloat(dailyRateInput.val()) || 0;
        total = workingDays * dailyRate;
        console.log('Working days calculation:', workingDays, 'x', dailyRate, '=', total);
    }
    
    // Update personnel total display
    const personnelTotalSpan = budgetSection.find(`#personnel-total-${budgetId}`);
    personnelTotalSpan.text(formatCurrency(total));
    console.log('Updated personnel total to:', formatCurrency(total));
    
    // Add visual feedback
    if (total > 0) {
        personnelSection.addClass('has-value');
        input.addClass('field-success').removeClass('field-error');
    } else {
        personnelSection.removeClass('has-value');
        input.removeClass('field-success field-error');
    }
    
    // Update partner total
    updatePartnerTotal(budgetId);
    
    // Trigger auto-save
    triggerAutoSave();
}

/**
 * Handle travel cost calculation (manual total only, no automatic calculation)
 */
function handleTravelCalculation(event) {
    const input = $(event.target);
    const budgetSection = input.closest('.partner-budget-section');
    const budgetId = budgetSection.data('budget-id');
    
    console.log('Travel calculation triggered for budget ID:', budgetId);
    
    // Calculate total travel costs for this partner (sum of manual total amounts only)
    let totalTravel = 0;
    budgetSection.find('.travel-total-amount').each(function() {
        const amount = parseFloat($(this).val()) || 0;
        totalTravel += amount;
        console.log('Travel entry amount:', amount);
    });
    
    console.log('Total travel for partner:', totalTravel);
    
    // Update travel section total
    budgetSection.find(`#travel-total-${budgetId}`).text(formatCurrency(totalTravel));
    
    // Add visual feedback to the entry
    const travelEntry = input.closest('.travel-entry');
    const destination = travelEntry.find('.activity-destination').val().trim();
    const amount = parseFloat(input.val()) || 0;
    
    if (amount > 0 || destination) {
        travelEntry.addClass('has-content');
        input.addClass('field-success').removeClass('field-error');
    } else {
        travelEntry.removeClass('has-content');
        input.removeClass('field-success field-error');
    }
    
    // Update partner total
    updatePartnerTotal(budgetId);
    
    // Trigger auto-save
    triggerAutoSave();
}

/**
 * Handle other costs calculation
 */
function handleOtherCostsCalculation(event) {
    const input = $(event.target);
    const budgetId = input.data('budget-id');
    
    if (!budgetId) return;
    
    // Update partner total
    updatePartnerTotal(budgetId);
    
    // Trigger auto-save
    triggerAutoSave();
}

/**
 * Update partner total (personnel + travel + other)
 */
function updatePartnerTotal(budgetId) {
    const budgetSection = $(`.partner-budget-section[data-budget-id="${budgetId}"]`);
    
    console.log('Updating partner total for budget ID:', budgetId);
    
    // Get personnel total
    const personnelTotalText = budgetSection.find(`#personnel-total-${budgetId}`).text();
    const personnelTotal = parseFloat(personnelTotalText.replace(/[^0-9.-]/g, '')) || 0;
    console.log('Personnel total:', personnelTotal);
    
    // Get travel total
    const travelTotalText = budgetSection.find(`#travel-total-${budgetId}`).text();
    const travelTotal = parseFloat(travelTotalText.replace(/[^0-9.-]/g, '')) || 0;
    console.log('Travel total:', travelTotal);
    
    // Get other costs
    const otherCosts = parseFloat(budgetSection.find('.other-costs').val()) || 0;
    console.log('Other costs:', otherCosts);
    
    // Calculate total
    const grandTotal = personnelTotal + travelTotal + otherCosts;
    console.log('Grand total:', grandTotal);
    
    // Update display
    budgetSection.find(`#partner-total-${budgetId}`).text(formatCurrency(grandTotal));
    
    // Add visual feedback
    if (grandTotal > 0) {
        budgetSection.find('.partner-total').addClass('has-value');
    } else {
        budgetSection.find('.partner-total').removeClass('has-value');
    }
}

/**
 * Calculate all totals on page load
 */
function calculateAllTotals() {
    console.log('Calculating all totals...');
    
    // Trigger calculation for all personnel inputs
    $('.working-days, .daily-rate, .project-management-cost').each(function() {
        if ($(this).val()) {
            $(this).trigger('input');
        }
    });
    
    // Trigger calculation for all travel total amounts
    $('.travel-total-amount').each(function() {
        if ($(this).val()) {
            $(this).trigger('input');
        }
    });
    
    // Trigger calculation for all other costs
    $('.other-costs').each(function() {
        if ($(this).val()) {
            $(this).trigger('input');
        }
    });
    
    console.log('All totals calculated');
}

/**
 * Handle form submission
 */
function handleFormSubmission(event) {
    event.preventDefault();
    
    const form = $(event.target);
    const submitBtn = form.find('button[type="submit"]');
    const originalText = submitBtn.html();
    
    // Show loading state
    submitBtn.html('<i class="fa fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
    form.addClass('form-loading');
    
    // Validate form
    if (!validateForm()) {
        submitBtn.html(originalText).prop('disabled', false);
        form.removeClass('form-loading');
        showNotification('Please fix the validation errors before saving.', 'error');
        return;
    }
    
    // Submit form after a short delay for UX
    setTimeout(function() {
        form.off('submit').submit();
    }, 500);
}

/**
 * Validate entire form
 */
function validateForm() {
    let isValid = true;
    
    // Validate numeric fields
    $('.form-control[type="number"]').each(function() {
        if (!validateField.call(this)) {
            isValid = false;
        }
    });
    
    // Validate travel entries (if destination is filled, total amount must be filled)
    $('.travel-entry').each(function() {
        const container = $(this);
        const destination = container.find('.activity-destination').val().trim();
        
        if (destination) {
            const totalAmount = container.find('.travel-total-amount').val();
            
            if (!totalAmount || totalAmount <= 0) {
                container.find('.travel-total-amount').addClass('field-error');
                isValid = false;
            }
        }
    });
    
    return isValid;
}

/**
 * Validate individual field
 */
function validateField(event) {
    const field = $(this);
    const value = field.val();
    
    // Remove existing validation classes
    field.removeClass('field-success field-error');
    
    // Skip validation for empty optional fields
    if (!value && !field.prop('required')) {
        return true;
    }
    
    let isValid = true;
    
    // Validate numeric fields
    if (field.attr('type') === 'number') {
        const numValue = parseFloat(value);
        if (isNaN(numValue) || numValue < 0) {
            isValid = false;
        }
    }
    
    // Add validation classes
    if (isValid) {
        field.addClass('field-success');
    } else {
        field.addClass('field-error');
    }
    
    return isValid;
}

/**
 * Clear travel entry
 */
function clearTravelEntry(event) {
    event.preventDefault();
    
    const travelEntry = $(event.target).closest('.travel-entry');
    travelEntry.find('input').val('');
    travelEntry.find('.travel-total-amount').text('0.00');
    travelEntry.removeClass('has-content');
    
    // Recalculate totals
    const firstInput = travelEntry.find('input').first();
    if (firstInput.length) {
        firstInput.trigger('input');
    }
    
    showNotification('Travel entry cleared', 'info', 2000);
}

/**
 * Setup tooltips
 */
function initializeTooltips() {
    // Add tooltips for form fields
    $('input[name*="[working_days]"]').attr('title', 'Number of working days allocated to this work package');
    $('input[name*="[daily_rate]"]').attr('title', 'Daily rate in EUR for this partner');
    $('input[name*="[project_management_cost]"]').attr('title', 'Flat-rate cost for project management activities');
    $('input[name*="[persons]"]').attr('title', 'Number of persons traveling');
    $('input[name*="[days]"]').attr('title', 'Number of days for travel');
    $('input[name*="[travel_cost]"]').attr('title', 'Transportation cost in EUR');
    $('input[name*="[daily_subsistence]"]').attr('title', 'Daily subsistence allowance in EUR');
    
    // Initialize Bootstrap tooltips
    $('[title]').tooltip({
        placement: 'top',
        delay: { show: 500, hide: 100 }
    });
}

/**
 * Setup form validation
 */
function initializeFormValidation() {
    // Real-time validation
    $('.form-control').on('input blur', function() {
        validateField.call(this);
    });
    
    // Form submission validation
    $('#budgetManagementForm').on('submit', function(event) {
        if (!validateForm()) {
            event.preventDefault();
            showNotification('Please correct the validation errors', 'error');
        }
    });
}

/**
 * Setup auto-save functionality
 */
function setupAutoSave() {
    let autoSaveTimeout;
    let hasUnsavedChanges = false;
    
    // Track changes
    $('#budgetManagementForm input, #budgetManagementForm textarea').on('input', function() {
        hasUnsavedChanges = true;
        
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(function() {
            if (hasUnsavedChanges) {
                // Auto-save could be implemented here via AJAX
                console.log('Auto-save triggered');
            }
        }, 30000); // 30 seconds
    });
    
    // Warn about unsaved changes
    $(window).on('beforeunload', function() {
        if (hasUnsavedChanges) {
            return 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
    
    // Clear flag on successful submit
    $('#budgetManagementForm').on('submit', function() {
        hasUnsavedChanges = false;
    });
}

/**
 * Add page animations
 */
function addPageAnimations() {
    // Animate work package cards on load
    $('.work-package-card').each(function(index) {
        $(this).delay(index * 150).queue(function(next) {
            $(this).addClass('fade-in');
            next();
        });
    });
    
    // Animate partner sections
    $('.partner-budget-section').addClass('slide-down');
    
    // Show success message if redirected after save
    if (window.location.hash === '#success') {
        showNotification('Budget details saved successfully!', 'success', 5000);
    }
}

/**
 * Handle keyboard shortcuts
 */
function handleKeyboardShortcuts(event) {
    // Ctrl/Cmd + S = Save form
    if ((event.ctrlKey || event.metaKey) && event.keyCode === 83) {
        event.preventDefault();
        $('#budgetManagementForm').submit();
    }
    
    // Ctrl/Cmd + R = Refresh calculations
    if ((event.ctrlKey || event.metaKey) && event.keyCode === 82) {
        event.preventDefault();
        calculateAllTotals();
        showNotification('Calculations refreshed', 'info', 2000);
    }
}

/**
 * Show session messages
 */
function showSessionMessages() {
    if (typeof sessionMessages !== 'undefined') {
        if (sessionMessages.success) {
            showNotification(sessionMessages.success, 'success', 4000);
        }
        if (sessionMessages.error) {
            showNotification(sessionMessages.error, 'error', 6000);
        }
        if (sessionMessages.info) {
            showNotification(sessionMessages.info, 'info', 5000);
        }
    }
}

/**
 * Trigger auto-save (placeholder for future implementation)
 */
function triggerAutoSave() {
    // This could be implemented to save draft changes via AJAX
    console.log('Auto-save triggered');
}

/**
 * Utility Functions
 */

/**
 * Format currency for display
 */
function formatCurrency(amount) {
    if (isNaN(amount) || amount === null) return '0.00';
    return parseFloat(amount).toFixed(2);
}

/**
 * Show notification message
 */
function showNotification(message, type = 'info', duration = 5000) {
    const typeMap = {
        'success': 'success',
        'error': 'danger',
        'warning': 'warning',
        'info': 'info'
    };
    
    const notificationType = typeMap[type] || 'info';
    
    if (typeof $.notify !== 'undefined') {
        $.notify({
            icon: 'nc-icon nc-bell-55',
            message: message
        }, {
            type: notificationType,
            timer: duration,
            placement: {
                from: 'top',
                align: 'right'
            },
            animate: {
                enter: 'animated fadeInDown',
                exit: 'animated fadeOutUp'
            }
        });
    } else {
        // Fallback to console if notify plugin not available
        console.log(`${type.toUpperCase()}: ${message}`);
    }
}

/**
 * Advanced Features
 */

/**
 * Export budget data as CSV
 */
window.exportBudgetCSV = function() {
    const csvData = [];
    const headers = ['Work Package', 'Partner', 'Personnel Cost', 'Travel Cost', 'Other Cost', 'Total'];
    csvData.push(headers.join(','));
    
    $('.work-package-card').each(function() {
        const wpName = $(this).find('.work-package-header h5').text().trim();
        
        $(this).find('.partner-budget-section').each(function() {
            const partnerName = $(this).find('.partner-header h6').text().split('(')[0].trim();
            const budgetId = $(this).data('budget-id');
            
            // Extract costs
            const personnelCost = $(this).find(`#personnel-total-${budgetId}`).text().replace(/[^0-9.-]/g, '') || '0';
            const travelCost = $(this).find(`#travel-total-${budgetId}`).text().replace(/[^0-9.-]/g, '') || '0';
            const otherCost = $(this).find('.other-costs').val() || '0';
            const total = $(this).find(`#partner-total-${budgetId}`).text().replace(/[^0-9.-]/g, '') || '0';
            
            const row = [
                `"${wpName}"`,
                `"${partnerName}"`,
                personnelCost,
                travelCost,
                otherCost,
                total
            ];
            
            csvData.push(row.join(','));
        });
    });
    
    // Download CSV
    const csvContent = csvData.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'partner-budgets.csv';
    link.click();
    URL.revokeObjectURL(url);
    
    showNotification('Budget data exported successfully', 'success', 3000);
};

/**
 * Print budget summary
 */
window.printBudgetSummary = function() {
    window.print();
};

/**
 * Copy travel entry to clipboard (for reuse)
 */
window.copyTravelEntry = function(budgetId, travelIndex) {
    const travelEntry = $(`.partner-budget-section[data-budget-id="${budgetId}"] .travel-entry[data-travel-index="${travelIndex}"]`);
    
    const travelData = {
        destination: travelEntry.find('.activity-destination').val(),
        persons: travelEntry.find('.persons').val(),
        days: travelEntry.find('.days').val(),
        travel_cost: travelEntry.find('.travel-cost').val(),
        daily_subsistence: travelEntry.find('.daily-subsistence').val()
    };
    
    // Store in localStorage for potential reuse
    localStorage.setItem('copied_travel_entry', JSON.stringify(travelData));
    
    showNotification('Travel entry copied to clipboard', 'success', 2000);
};

/**
 * Paste travel entry from clipboard
 */
window.pasteTravelEntry = function(budgetId, travelIndex) {
    const copiedData = localStorage.getItem('copied_travel_entry');
    
    if (!copiedData) {
        showNotification('No travel entry in clipboard', 'warning', 2000);
        return;
    }
    
    try {
        const travelData = JSON.parse(copiedData);
        const travelEntry = $(`.partner-budget-section[data-budget-id="${budgetId}"] .travel-entry[data-travel-index="${travelIndex}"]`);
        
        travelEntry.find('.activity-destination').val(travelData.destination);
        travelEntry.find('.persons').val(travelData.persons);
        travelEntry.find('.days').val(travelData.days);
        travelEntry.find('.travel-cost').val(travelData.travel_cost);
        travelEntry.find('.daily-subsistence').val(travelData.daily_subsistence);
        
        // Trigger calculations
        travelEntry.find('.persons').trigger('input');
        
        showNotification('Travel entry pasted successfully', 'success', 2000);
        
    } catch (e) {
        showNotification('Error pasting travel entry', 'error', 3000);
    }
};