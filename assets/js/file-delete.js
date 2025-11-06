/**
 * File Delete Handler
 * 
 * Gestisce l'eliminazione dei file via AJAX
 * 
 * @package EU Project Manager
 * @version 1.0
 */

$(document).ready(function() {
    
    /**
     * Handler per pulsante elimina file
     */
    $(document).on('click', '.delete-file-btn', function(e) {
        e.preventDefault();
        
        const fileId = $(this).data('file-id');
        const fileName = $(this).data('file-name');
        const $button = $(this);
        
        // Conferma eliminazione
        if (!confirm(`Sei sicuro di voler eliminare il file "${fileName}"?\n\nQuesta azione è irreversibile.`)) {
            return;
        }
        
        // Disabilita pulsante durante elaborazione
        $button.prop('disabled', true);
        $button.html('<i class="nc-icon nc-refresh-69 spin"></i> Eliminazione...');
        
        // Chiamata AJAX
        $.ajax({
            url: '../api/delete_file.php',
            type: 'POST',
            data: {
                action: 'delete_file',
                file_id: fileId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Mostra notifica successo
                    showNotification('success', 'File eliminato', response.message);
                    
                    // Rimuovi riga dalla tabella
                    $button.closest('tr').fadeOut(400, function() {
                        $(this).remove();
                        
                        // Verifica se la tabella è vuota
                        checkEmptyTable();
                    });
                } else {
                    // Mostra errore
                    showNotification('danger', 'Errore', response.message);
                    
                    // Riabilita pulsante
                    $button.prop('disabled', false);
                    $button.html('<i class="nc-icon nc-simple-remove"></i> Delete');
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete error:', error);
                showNotification('danger', 'Errore', 'Errore durante eliminazione file.');
                
                // Riabilita pulsante
                $button.prop('disabled', false);
                $button.html('<i class="nc-icon nc-simple-remove"></i> Delete');
            }
        });
    });
    
    /**
     * Verifica se la tabella file è vuota
     */
    function checkEmptyTable() {
        const $filesTable = $('#files-table tbody');
        
        if ($filesTable.find('tr').length === 0) {
            $filesTable.html(`
                <tr>
                    <td colspan="7" class="text-center text-muted">
                        <i class="nc-icon nc-folder-16"></i> 
                        Nessun file caricato
                    </td>
                </tr>
            `);
        }
    }
    
    /**
     * Mostra notifica usando Paper Dashboard
     */
    function showNotification(type, title, message) {
        $.notify({
            icon: type === 'success' ? 'nc-icon nc-check-2' : 'nc-icon nc-simple-remove',
            title: title,
            message: message
        }, {
            type: type,
            timer: 3000,
            placement: {
                from: 'top',
                align: 'right'
            }
        });
    }
});