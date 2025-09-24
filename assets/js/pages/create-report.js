/**
 * CREATE REPORT PAGE - JavaScript
 * 
 * Gestisce la funzionalità della pagina di creazione report:
 * - Upload file dinamici con titoli
 * - Validazione file (tipo e dimensione)
 * - Interfaccia utente reattiva
 * - Auto-completamento titoli
 * 
 * @version 2.0
 * @author EU Project Manager Team
 */

$(document).ready(function() {
    
    // ===================================================================
    // VARIABILI GLOBALI
    // ===================================================================
    
    let fileCounter = 1; // Contatore per ID unici dei file
    
    const FILE_CONFIG = {
        maxSize: 10485760, // 10MB in bytes
        allowedTypes: ['pdf', 'doc', 'docx', 'xlsx', 'xls', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif']
    };

    // ===================================================================
    // FUNZIONALITÀ ESISTENTE - Compatibilità con vecchio sistema
    // ===================================================================
    
    /**
     * Display selected file names per il vecchio input (se esiste)
     * Manteniamo per retrocompatibilità
     */
    $('#reportFilesInput').on('change', function() {
        const files = this.files;
        const selectedFilesDiv = $('#selectedFiles');
        
        if (selectedFilesDiv.length > 0) {
            selectedFilesDiv.empty();
            
            if (files.length > 0) {
                let fileNamesHtml = '';
                for (let i = 0; i < files.length; i++) {
                    fileNamesHtml += `<span class="badge badge-secondary mr-1">${files[i].name}</span>`;
                }
                selectedFilesDiv.html(fileNamesHtml);
            }
        }
    });

    // ===================================================================
    // GESTIONE FILE DINAMICI CON TITOLI
    // ===================================================================
    
    /**
     * Gestisce la selezione di file per i nuovi input dinamici
     */
    $(document).on('change', '.file-input', function() {
        const fileInput = $(this);
        const fileIndex = fileInput.data('file-index');
        const file = this.files[0];
        
        if (file) {
            handleFileSelection(fileInput, file, fileIndex);
        } else {
            resetFileInput(fileInput, fileIndex);
        }
    });
    
    /**
     * Gestisce la selezione di un file
     */
    function handleFileSelection(fileInput, file, fileIndex) {
        // Validazione file
        if (!validateFile(file, fileInput)) {
            return;
        }
        
        // Calcola informazioni file
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB
        const fileExtension = fileName.split('.').pop().toUpperCase();
        
        // Aggiorna interfaccia
        updateFileDisplay(fileInput, fileName, fileSize, fileExtension, fileIndex);
        
        // Auto-suggerisce titolo se vuoto
        autoSuggestTitle(fileInput, fileName);
        
        console.log(`File selezionato: ${fileName} (${fileSize} MB)`);
    }
    
    /**
     * Aggiorna il display dell'interfaccia quando un file è selezionato
     */
    function updateFileDisplay(fileInput, fileName, fileSize, fileExtension, fileIndex) {
        const fileNameDiv = $(`#fileName${fileIndex}`);
        const noFileDiv = $(`#noFile${fileIndex}`);
        const fileNameText = fileNameDiv.find('.file-name-text');
        const fileLabel = fileInput.next('label');
        
        // Aggiorna testo di feedback
        fileNameText.html(`
            <strong>${fileName}</strong><br>
            <small class="text-muted">
                ${fileExtension} • ${fileSize} MB
            </small>
        `);
        
        // Mostra feedback e nasconde messaggio "nessun file"
        fileNameDiv.show();
        noFileDiv.hide();
        
        // Cambia aspetto del pulsante
        updateButtonAppearance(fileLabel, 'selected');
        
        // Aggiungi classe per styling
        fileInput.closest('.file-upload-wrapper').addClass('file-selected');
        
        // Animazione di feedback
        fileNameDiv.hide().fadeIn(300);
    }
    
    /**
     * Aggiorna l'aspetto del pulsante
     */
    function updateButtonAppearance(fileLabel, state) {
        const btnText = fileLabel.find('.btn-text');
        const btnIcon = fileLabel.find('i');
        
        switch (state) {
            case 'selected':
                btnText.text('File Selected');
                btnIcon.removeClass('nc-cloud-upload-94').addClass('nc-check-2');
                break;
            case 'default':
                btnText.text('Choose File');
                btnIcon.removeClass('nc-check-2').addClass('nc-cloud-upload-94');
                break;
        }
    }
    
    /**
     * Auto-suggerisce titolo basato sul nome file
     */
    function autoSuggestTitle(fileInput, fileName) {
        const titleInput = fileInput.closest('.file-upload-item').find('input[name="file_titles[]"]');
        
        if (!titleInput.val().trim()) {
            // Rimuove estensione e caratteri speciali
            let suggestedTitle = fileName.replace(/\.[^/.]+$/, "");
            suggestedTitle = suggestedTitle.replace(/[_-]/g, " ");
            suggestedTitle = capitalizeWords(suggestedTitle);
            
            titleInput.val(suggestedTitle);
            
            // Animazione per evidenziare il campo compilato automaticamente
            titleInput.addClass('auto-filled');
            setTimeout(() => {
                titleInput.removeClass('auto-filled');
            }, 2000);
        }
    }
    
    /**
     * Capitalizza le parole di una stringa
     */
    function capitalizeWords(str) {
        return str.replace(/\w\S*/g, (txt) => {
            return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
        });
    }
    
    /**
     * Reset del file input allo stato iniziale
     */
    function resetFileInput(fileInput, fileIndex) {
        const fileNameDiv = $(`#fileName${fileIndex}`);
        const noFileDiv = $(`#noFile${fileIndex}`);
        const fileLabel = fileInput.next('label');
        
        // Nasconde feedback e mostra messaggio "nessun file"
        fileNameDiv.hide();
        noFileDiv.show();
        
        // Reset pulsante allo stato originale
        updateButtonAppearance(fileLabel, 'default');
        
        // Rimuove classe styling
        fileInput.closest('.file-upload-wrapper').removeClass('file-selected');
    }

    // ===================================================================
    // GESTIONE DINAMICA DEI FILE (AGGIUNGI/RIMUOVI)
    // ===================================================================
    
    /**
     * Aggiunge un nuovo campo per file upload
     */
    $('#addFileButton').on('click', function() {
        fileCounter++;
        addNewFileField(fileCounter);
        updateRemoveButtons();
        
        // Scroll verso il nuovo campo aggiunto
        setTimeout(() => {
            $(`#fileUpload${fileCounter}`)[0].scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
        }, 100);
    });
    
    /**
     * Crea HTML per un nuovo campo file
     */
    function addNewFileField(counter) {
        const container = $('#fileUploadsContainer');
        const newFileItem = $(`
            <div class="file-upload-item" id="fileUpload${counter}">
                <div class="row">
                    <div class="col-md-5">
                        <label>File Title</label>
                        <input type="text" name="file_titles[]" class="form-control" 
                               placeholder="e.g., Progress Report Q1, Meeting Minutes..." />
                    </div>
                    <div class="col-md-6">
                        <label>Select File</label>
                        <div class="file-upload-wrapper">
                            <input type="file" name="report_files[]" class="file-input" 
                                   data-file-index="${counter}" id="fileInput${counter}" />
                            <label for="fileInput${counter}" class="btn btn-outline-info btn-block file-select-btn">
                                <i class="nc-icon nc-cloud-upload-94 mr-2"></i>
                                <span class="btn-text">Choose File</span>
                            </label>
                        </div>
                        <div class="selected-file-display" id="fileName${counter}" style="display: none;">
                            <div class="alert alert-success mb-0 mt-2 py-2">
                                <i class="nc-icon nc-check-2 mr-2"></i>
                                <span class="file-name-text">No file selected</span>
                            </div>
                        </div>
                        <div class="no-file-display" id="noFile${counter}">
                            <small class="text-muted mt-1 d-block">
                                <i class="nc-icon nc-bullet-list-67 mr-1"></i>
                                No file chosen
                            </small>
                        </div>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="button" class="btn btn-danger btn-sm btn-remove-file" 
                                data-file-id="fileUpload${counter}" title="Remove this file">
                            <i class="nc-icon nc-simple-remove"></i>
                        </button>
                    </div>
                </div>
            </div>
        `);
        
        container.append(newFileItem);
        
        // Animazione per il nuovo elemento
        newFileItem.hide().slideDown(300);
    }
    
    /**
     * Rimuove un campo file
     */
    $(document).on('click', '.btn-remove-file', function() {
        const fileId = $(this).data('file-id');
        const fileItem = $('#' + fileId);
        
        // Conferma rimozione se il file è stato selezionato
        const hasFile = fileItem.find('.file-input')[0].files.length > 0;
        if (hasFile) {
            if (!confirm('Are you sure you want to remove this file?')) {
                return;
            }
        }
        
        // Animazione di rimozione
        fileItem.slideUp(300, function() {
            $(this).remove();
            updateRemoveButtons();
        });
    });
    
    /**
     * Aggiorna la visibilità dei pulsanti "rimuovi"
     */
    function updateRemoveButtons() {
        const items = $('.file-upload-item');
        
        items.each(function() {
            const removeBtn = $(this).find('.btn-remove-file');
            if (items.length > 1) {
                removeBtn.show();
            } else {
                removeBtn.hide();
            }
        });
    }

    // ===================================================================
    // VALIDAZIONE FILE
    // ===================================================================
    
    /**
     * Valida un file selezionato
     */
    function validateFile(file, fileInput) {
        // Controlla dimensione
        if (!validateFileSize(file)) {
            showValidationError(
                'File too large!', 
                `Maximum size allowed: ${(FILE_CONFIG.maxSize / 1024 / 1024).toFixed(0)}MB\nSelected file: ${(file.size / 1024 / 1024).toFixed(2)}MB`
            );
            clearFileInput(fileInput);
            return false;
        }
        
        // Controlla tipo file
        if (!validateFileType(file)) {
            showValidationError(
                'File type not supported!', 
                `Allowed formats: ${FILE_CONFIG.allowedTypes.join(', ').toUpperCase()}\nSelected file: ${file.name.split('.').pop().toUpperCase()}`
            );
            clearFileInput(fileInput);
            return false;
        }
        
        return true;
    }
    
    /**
     * Valida la dimensione del file
     */
    function validateFileSize(file) {
        return file.size <= FILE_CONFIG.maxSize;
    }
    
    /**
     * Valida il tipo del file
     */
    function validateFileType(file) {
        const fileExtension = file.name.split('.').pop().toLowerCase();
        return FILE_CONFIG.allowedTypes.includes(fileExtension);
    }
    
    /**
     * Mostra errore di validazione
     */
    function showValidationError(title, message) {
        alert(`${title}\n\n${message}`);
        console.warn('File validation error:', title, message);
    }
    
    /**
     * Pulisce il file input dopo errore di validazione
     */
    function clearFileInput(fileInput) {
        fileInput.val('');
        const fileIndex = fileInput.data('file-index');
        resetFileInput(fileInput, fileIndex);
    }

    // ===================================================================
    // UTILITÀ E HELPER
    // ===================================================================
    
    /**
     * Formatta la dimensione del file in formato leggibile
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    /**
     * Genera un ID univoco per elementi dinamici
     */
    function generateUniqueId(prefix = 'item') {
        return prefix + '_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    // ===================================================================
    // INIZIALIZZAZIONE
    // ===================================================================
    
    /**
     * Inizializza lo stato della pagina al caricamento
     */
    function initializePage() {
        // Nascondi pulsanti rimuovi se c'è solo un file
        updateRemoveButtons();
        
        // Imposta focus sul primo campo
        $('input[name="file_titles[]"]:first').focus();
        
        console.log('Create Report page initialized successfully');
        console.log('File upload configuration:', FILE_CONFIG);
    }
    
    // Esegue inizializzazione
    initializePage();
    
    // ===================================================================
    // DEBUG E DEVELOPMENT (rimuovere in produzione)
    // ===================================================================
    
    // Log per debug in development
    if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
        window.debugFileUpload = {
            fileCounter: () => fileCounter,
            config: FILE_CONFIG,
            resetCounter: () => { fileCounter = 1; },
            simulateError: (type) => {
                if (type === 'size') {
                    showValidationError('Debug: Size Error', 'Simulated file size error');
                } else if (type === 'type') {
                    showValidationError('Debug: Type Error', 'Simulated file type error');
                }
            }
        };
        console.log('Debug utilities available in window.debugFileUpload');
    }

});