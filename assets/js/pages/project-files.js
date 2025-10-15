/**
 * Project Files - JavaScript
 * 
 * Gestisce search, filtri e interazioni della pagina
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Elementi DOM
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const wpFilter = document.getElementById('wpFilter');
    const fileRows = document.querySelectorAll('.file-row');
    const addNewFileBtn = document.getElementById('addNewFileBtn');
    
    /**
     * Funzione di filtro principale
     * Filtra le righe della tabella in base a search, categoria e WP
     */
    function filterFiles() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedCategory = categoryFilter.value.toLowerCase();
        const selectedWP = wpFilter.value.toLowerCase();
        
        let visibleCount = 0;
        
        fileRows.forEach(function(row) {
            const filename = row.getAttribute('data-filename').toLowerCase();
            const category = row.getAttribute('data-category').toLowerCase();
            const wp = row.getAttribute('data-wp').toLowerCase();
            
            // Verifica search term (cerca in filename)
            const matchesSearch = filename.includes(searchTerm);
            
            // Verifica categoria
            const matchesCategory = !selectedCategory || category === selectedCategory;
            
            // Verifica WP
            const matchesWP = !selectedWP || wp === selectedWP;
            
            // Mostra/nascondi row
            if (matchesSearch && matchesCategory && matchesWP) {
                row.classList.remove('hidden');
                row.style.display = '';
                visibleCount++;
            } else {
                row.classList.add('hidden');
                row.style.display = 'none';
            }
        });
        
        // Mostra messaggio se nessun risultato
        updateNoResultsMessage(visibleCount);
    }
    
    /**
     * Mostra/nasconde messaggio "nessun risultato"
     */
    function updateNoResultsMessage(visibleCount) {
        const tbody = document.querySelector('#filesTable tbody');
        let noResultsRow = document.getElementById('noResultsRow');
        
        if (visibleCount === 0 && fileRows.length > 0) {
            if (!noResultsRow) {
                noResultsRow = document.createElement('tr');
                noResultsRow.id = 'noResultsRow';
                noResultsRow.innerHTML = '<td colspan="8" class="text-center"><p class="text-muted">No files match your search criteria</p></td>';
                tbody.appendChild(noResultsRow);
            }
        } else {
            if (noResultsRow) {
                noResultsRow.remove();
            }
        }
    }
    
    /**
     * Event listener per search input
     * Debounce per evitare troppe chiamate
     */
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(filterFiles, 300);
    });
    
    /**
     * Event listener per filtro categoria
     */
    categoryFilter.addEventListener('change', filterFiles);
    
    /**
     * Event listener per filtro WP
     */
    wpFilter.addEventListener('change', filterFiles);
    
    /**
     * Reset filtri
     */
    function resetFilters() {
        searchInput.value = '';
        categoryFilter.value = '';
        wpFilter.value = '';
        filterFiles();
    }
    
    // Aggiungi pulsante reset (opzionale)
    const resetBtn = document.createElement('button');
    resetBtn.className = 'btn btn-sm btn-default ml-2';
    resetBtn.innerHTML = '<i class="nc-icon nc-refresh-69"></i> Reset Filters';
    resetBtn.onclick = resetFilters;
    
    // Inserisci pulsante reset dopo il filtro WP
    if (wpFilter && wpFilter.parentElement) {
        const parentDiv = wpFilter.closest('.col-md-3');
        if (parentDiv) {
            const btnContainer = document.createElement('div');
            btnContainer.className = 'mt-3';
            btnContainer.appendChild(resetBtn);
            parentDiv.appendChild(btnContainer);
        }
    }
    
    /**
     * Add New File button
     * Reindirizza a pagina upload (da implementare)
     */
    if (addNewFileBtn) {
        addNewFileBtn.addEventListener('click', function() {
            window.location.href = 'project-files-upload.php?project_id=' + projectId;
        });
    }
    
    /**
     * Download tracking (opzionale)
     * Traccia i download dei file
     */
    const downloadLinks = document.querySelectorAll('a[download]');
    downloadLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            const filename = this.getAttribute('href').split('/').pop();
            console.log('Downloading file:', filename);
            
            // Opzionale: invia log al server
            // fetch('api/log_download.php', {
            //     method: 'POST',
            //     body: JSON.stringify({filename: filename}),
            //     headers: {'Content-Type': 'application/json'}
            // });
        });
    });
    
    /**
     * Tooltip per pulsanti (se Bootstrap tooltip è disponibile)
     */
    if (typeof $ !== 'undefined' && $.fn.tooltip) {
        $('[title]').tooltip({
            placement: 'top',
            trigger: 'hover'
        });
    }
    
    /**
     * Animazione card statistiche
     */
    const statsCards = document.querySelectorAll('.card-stats');
    statsCards.forEach(function(card, index) {
        setTimeout(function() {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(function() {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 50);
        }, index * 100);
    });
    
    /**
     * Highlight row al caricamento (se c'è un hash nell'URL)
     */
    if (window.location.hash) {
        const fileId = window.location.hash.substring(1);
        const targetRow = document.querySelector(`tr[data-file-id="${fileId}"]`);
        if (targetRow) {
            targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            targetRow.style.backgroundColor = '#fffacd';
            setTimeout(function() {
                targetRow.style.transition = 'background-color 1s ease';
                targetRow.style.backgroundColor = '';
            }, 2000);
        }
    }
    
    console.log('Project Files page initialized');
    console.log('Total files:', fileRows.length);
});