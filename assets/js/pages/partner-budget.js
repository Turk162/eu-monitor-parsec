/**
 * ===================================================================
 * PARTNER BUDGET PAGE JAVASCRIPT
 * Handles interactions for the partner budget detail view page
 * ===================================================================
 */

$(document).ready(function() {
    // Initialize page
    initializeBudgetPage();
    
    // Bind event handlers
    bindEventHandlers();
    
    // Add animations
    addPageAnimations();
});

/**
 * Initialize budget page functionality
 */
function initializeBudgetPage() {
    console.log('Partner Budget page initialized');
    
    // Check for any missing budget data
    checkBudgetCompleteness();
    
    // Calculate and validate totals
    validateBudgetTotals();
    
    // Set up tooltips for budget items
    initializeTooltips();
    
    // Highlight any discrepancies
    highlightBudgetDiscrepancies();
}

/**
 * Bind all event handlers
 */
function bindEventHandlers() {
    // Export PDF functionality
    $('#exportPdfBtn').on('click', handleExportPdf);
    
    // Print functionality
    $('#printBudgetBtn').on('click', handlePrintBudget);
    
    // Work package card toggle (collapse/expand)
    $('.wp-budget-card .card-header').on('click', toggleWorkPackageDetails);
    
    // Hover effects for budget lines
    $('.budget-line').hover(
        function() { $(this).addClass('highlight'); },
        function() { $(this).removeClass('highlight'); }
    );
    
    // Copy budget amounts to clipboard
    $('.budget-amount').on('click', copyBudgetAmount);
    
    // Keyboard shortcuts
    $(document).on('keydown', handleKeyboardShortcuts);
}

/**
 * Add page animations
 */
function addPageAnimations() {
    // Animate cards on load
    $('.wp-budget-card').each(function(index) {
        $(this).delay(index * 100).queue(function(next) {
            $(this).addClass('fade-in');
            next();
        });
    });
    
    // Animate budget amounts
    $('.budget-amount').each(function() {
        animateNumber(this);
    });
}

/**
 * Handle PDF export functionality
 */
function handleExportPdf() {
    const button = $(this);
    const originalText = button.html();
    
    // Show loading state
    button.html('<i class="nc-icon nc-refresh-69 fa-spin"></i> Generating PDF...');
    button.prop('disabled', true);
    
    // Get current page data
    const projectId = getUrlParameter('project_id');
    const partnerId = getUrlParameter('partner_id') || getCurrentPartnerId();
    
    // Prepare export data
    const exportData = {
        action: 'export_partner_budget_pdf',
        project_id: projectId,
        partner_id: partnerId,
        include_details: true,
        format: 'pdf'
    };
    
    // Send export request
    $.ajax({
        url: '../api/budget-operations.php',
        method: 'POST',
        data: exportData,
        xhrFields: {
            responseType: 'blob'
        },
        success: function(response, status, xhr) {
            // Create download link
            const blob = new Blob([response], { type: 'application/pdf' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            
            // Get filename from response header or generate one
            const disposition = xhr.getResponseHeader('content-disposition');
            let filename = 'partner-budget.pdf';
            if (disposition && disposition.indexOf('attachment') !== -1) {
                const filenameMatch = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
                if (filenameMatch && filenameMatch[1]) {
                    filename = filenameMatch[1].replace(/['"]/g, '');
                }
            }
            
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            
            // Cleanup
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            showNotification('PDF exported successfully!', 'success');
        },
        error: function(xhr, status, error) {
            console.error('PDF export failed:', error);
            showNotification('Failed to export PDF. Please try again.', 'error');
        },
        complete: function() {
            // Restore button state
            button.html(originalText);
            button.prop('disabled', false);
        }
    });
}

/**
 * Handle print functionality
 */
function handlePrintBudget() {
    // Prepare page for printing
    $('body').addClass('printing');
    
    // Add print-specific styles
    const printStyles = `
        <style type="text/css" media="print">
            @page { margin: 2cm; size: A4; }
            body { font-size: 12pt; line-height: 1.4; }
            .wp-budget-card { page-break-inside: avoid; margin-bottom: 1cm; }
            .budget-total-card { page-break-before: avoid; }
        </style>
    `;
    $('head').append(printStyles);
    
    // Print the page
    setTimeout(function() {
        window.print();
        
        // Cleanup after print
        setTimeout(function() {
            $('body').removeClass('printing');
            $('head').find('style[media="print"]:last').remove();
        }, 1000);
    }, 500);
}

/**
 * Toggle work package details (expand/collapse)
 */
function toggleWorkPackageDetails(event) {
    event.preventDefault();
    
    const card = $(this).closest('.wp-budget-card');
    const cardBody = card.find('.card-body');
    const isCollapsed = cardBody.hasClass('collapsed');
    
    if (isCollapsed) {
        cardBody.removeClass('collapsed').slideDown(300);
        card.find('.toggle-icon').removeClass('nc-minimal-down').addClass('nc-minimal-up');
    } else {
        cardBody.addClass('collapsed').slideUp(300);
        card.find('.toggle-icon').removeClass('nc-minimal-up').addClass('nc-minimal-down');
    }
}

/**
 * Copy budget amount to clipboard
 */
function copyBudgetAmount(event) {
    event.stopPropagation();
    
    const amount = $(this).text().trim();
    
    // Create temporary input for copying
    const tempInput = $('<input>');
    $('body').append(tempInput);
    tempInput.val(amount).select();
    
    try {
        document.execCommand('copy');
        showNotification(`Copied: ${amount}`, 'info', 2000);
        
        // Visual feedback
        $(this).addClass('copied');
        setTimeout(() => $(this).removeClass('copied'), 1000);
    } catch (err) {
        console.error('Copy failed:', err);
        showNotification('Copy failed', 'error', 2000);
    }
    
    tempInput.remove();
}

/**
 * Handle keyboard shortcuts
 */
function handleKeyboardShortcuts(event) {
    // Ctrl/Cmd + P = Print
    if ((event.ctrlKey || event.metaKey) && event.keyCode === 80) {
        event.preventDefault();
        handlePrintBudget();
    }
    
    // Ctrl/Cmd + S = Export PDF
    if ((event.ctrlKey || event.metaKey) && event.keyCode === 83) {
        event.preventDefault();
        handleExportPdf();
    }
    
    // Escape = Close any open dialogs
    if (event.keyCode === 27) {
        $('.modal').modal('hide');
    }
}

/**
 * Check budget completeness and show warnings
 */
function checkBudgetCompleteness() {
    const wpCards = $('.wp-budget-card');
    let incompleteCount = 0;
    
    wpCards.each(function() {
        const wpType = $(this).data('wp-type');
        const hasPersonnelCost = $(this).find('.budget-amount').length > 0;
        
        if (!hasPersonnelCost) {
            $(this).addClass('incomplete-budget');
            incompleteCount++;
        }
    });
    
    if (incompleteCount > 0) {
        showNotification(
            `${incompleteCount} work package(s) have incomplete budget details. Contact the coordinator for updates.`,
            'warning',
            8000
        );
    }
}

/**
 * Validate budget totals and show discrepancies
 */
function validateBudgetTotals() {
    const allocatedBudget = parseFloat($('.budget-summary h4').text().replace(/[€,]/g, '')) || 0;
    const calculatedBudget = parseFloat($('.budget-total-card h4').text().replace(/[€,]/g, '')) || 0;
    
    const difference = Math.abs(allocatedBudget - calculatedBudget);
    
    if (difference > 0.01) {
        const message = calculatedBudget < allocatedBudget 
            ? `There's a remaining budget of €${difference.toFixed(2)} not yet allocated to specific activities.`
            : `The detailed budget exceeds the allocated amount by €${difference.toFixed(2)}.`;
            
        showNotification(message, 'info', 10000);
    }
}

/**
 * Initialize tooltips for budget items
 */
function initializeTooltips() {
    // Add tooltips for budget explanations
    $('.budget-amount').each(function() {
        const parent = $(this).closest('.budget-line');
        const description = parent.find('.text-muted').text();
        
        if (description) {
            $(this).attr('title', description).tooltip();
        }
    });
    
    // Add tooltips for work package types
    $('.wp-budget-card[data-wp-type="project_management"]').attr(
        'title', 
        'Project Management work packages use flat-rate costs instead of daily rates'
    ).tooltip();
    
    $('.wp-budget-card[data-wp-type="standard"]').attr(
        'title', 
        'Standard work packages calculate costs based on working days and daily rates'
    ).tooltip();
}

/**
 * Highlight budget discrepancies
 */
function highlightBudgetDiscrepancies() {
    // Check for zero amounts
    $('.budget-amount').each(function() {
        const amount = parseFloat($(this).text().replace(/[€,]/g, ''));
        if (amount === 0) {
            $(this).addClass('zero-amount').attr('title', 'No budget allocated');
        }
    });
    
    // Check for missing travel data in standard WPs
    $('.wp-budget-card[data-wp-type="standard"]').each(function() {
        const hasTravel = $(this).find('.travel-line').length > 0;
        if (!hasTravel) {
            $(this).find('.card-header').append(
                '<small class="text-muted ml-2">(No travel costs)</small>'
            );
        }
    });
}

/**
 * Animate number counting effect
 */
function animateNumber(element) {
    const $element = $(element);
    const text = $element.text();
    const match = text.match(/€?([\d,]+\.?\d*)/);
    
    if (match) {
        const number = parseFloat(match[1].replace(/,/g, ''));
        const prefix = text.substring(0, match.index);
        const suffix = text.substring(match.index + match[0].length);
        
        $({ counter: 0 }).animate({
            counter: number
        }, {
            duration: 1000,
            easing: 'swing',
            step: function() {
                $element.text(prefix + '€' + numberWithCommas(Math.floor(this.counter)) + suffix);
            },
            complete: function() {
                $element.text(prefix + '€' + numberWithCommas(number) + suffix);
            }
        });
    }
}

/**
 * Utility Functions
 */

function getUrlParameter(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

function getCurrentPartnerId() {
    // Extract partner ID from page context or session
    return $('body').data('partner-id') || null;
}

function numberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function showNotification(message, type = 'info', duration = 5000) {
    const typeMap = {
        'success': 'success',
        'error': 'danger',
        'warning': 'warning',
        'info': 'info'
    };
    
    const notificationType = typeMap[type] || 'info';
    
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
}

/**
 * Advanced Features
 */

// Auto-refresh budget data every 5 minutes (for coordinators)
if (window.userRole === 'coordinator' || window.userRole === 'super_admin') {
    setInterval(function() {
        checkForBudgetUpdates();
    }, 300000); // 5 minutes
}

function checkForBudgetUpdates() {
    const projectId = getUrlParameter('project_id');
    const partnerId = getUrlParameter('partner_id') || getCurrentPartnerId();
    
    if (projectId && partnerId) {
        $.ajax({
            url: '../api/budget-operations.php',
            method: 'GET',
            data: {
                action: 'check_budget_updates',
                project_id: projectId,
                partner_id: partnerId,
                last_check: Date.now()
            },
            success: function(response) {
                if (response.has_updates) {
                    showNotification(
                        'Budget data has been updated. <a href="#" onclick="location.reload()">Refresh page</a> to see changes.',
                        'info',
                        10000
                    );
                }
            },
            error: function() {
                // Silently fail for auto-refresh
                console.log('Auto-refresh check failed');
            }
        });
    }
}

// Export budget summary as JSON (for advanced users)
window.exportBudgetJson = function() {
    const budgetData = {
        project_id: getUrlParameter('project_id'),
        partner_id: getUrlParameter('partner_id'),
        work_packages: [],
        generated_at: new Date().toISOString()
    };
    
    $('.wp-budget-card').each(function() {
        const wpData = {
            wp_number: $(this).find('.card-title').text().split(':')[0],
            wp_name: $(this).find('.card-title').text().split(':')[1],
            wp_type: $(this).data('wp-type'),
            budget_breakdown: [],
            total: $(this).find('.wp-total').text()
        };
        
        $(this).find('.budget-line').each(function() {
            const lineData = {
                description: $(this).find('strong').text(),
                amount: $(this).find('.budget-amount').text()
            };
            wpData.budget_breakdown.push(lineData);
        });
        
        budgetData.work_packages.push(wpData);
    });
    
    const dataStr = JSON.stringify(budgetData, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `budget-${budgetData.project_id}-${budgetData.partner_id}.json`;
    link.click();
    URL.revokeObjectURL(url);
};