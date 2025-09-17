/* ===================================================================
 *  PAGE-SPECIFIC SCRIPTS FOR: Project Detail Page
 * =================================================================== */

// Gestione hover bordeaux per i titoli WP - Intercetta hover della CARD
document.addEventListener('DOMContentLoaded', function() {
    console.log('JavaScript WP hover caricato'); // Debug
    
    // Seleziona tutte le WP cards
    const wpCards = document.querySelectorAll('.wp-card');
    console.log('Trovate WP cards:', wpCards.length); // Debug
    
    wpCards.forEach(function(card, index) {
        const title = card.querySelector('.wp-header h5');
        if (title) {
            console.log('Configurando hover per card', index + 1);
            
            const originalColor = '#333';
            
            // Hover sulla CARD - titolo bordeaux
            card.addEventListener('mouseenter', function() {
                console.log('Hover IN su card', index + 1);
                title.style.color = '#8B0000 !important';
                title.style.cursor = 'pointer';
            });
            
            // Mouse leave dalla CARD - ripristina colore
            card.addEventListener('mouseleave', function() {
                console.log('Hover OUT da card', index + 1);
                title.style.color = originalColor + ' !important';
                title.style.cursor = 'default';
            });
        }
    });
});

$(document).ready(function() {
    // Initialize Bootstrap tooltips for progress circles
    $('.progress-circle').tooltip({
        title: function() {
            return 'Overall project completion: ' + $(this).text();
        },
        placement: 'top'
    });

    // --- Hover Animations for UI elements ---
    $('.wp-card').hover(
        function() {
            $(this).find('.wp-header').css('background', 'linear-gradient(135deg, #51CACF 0%, #667eea 100%)');
            $(this).find('.wp-header h5, .wp-header p, .wp-header small').css('color', 'white');
        },
        function() {
            $(this).find('.wp-header').css('background', '#f8f9fa');
            $(this).find('.wp-header h5, .wp-header p, .wp-header small').css('color', '');
        }
    );

    $('.partner-card:not(.coordinator-card)').hover(
        function() {
            $(this).css('background', 'linear-gradient(135deg, rgba(81, 202, 207, 0.1) 0%, rgba(255, 255, 255, 1) 100%)');
        },
        function() {
            $(this).css('background', '');
        }
    );

    // --- Tab functionality ---
    // Logic to remember the last active tab using localStorage
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        localStorage.setItem('lastProjectDetailTab', $(e.target).attr('href'));
    });

    const lastTab = localStorage.getItem('lastProjectDetailTab');
    if (lastTab) {
        $('#projectTabs a[href="' + lastTab + '"]').tab('show');
    }
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
