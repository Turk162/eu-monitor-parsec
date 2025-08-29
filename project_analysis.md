# Analisi del Progetto EU Project Manager

## Diagramma ad Albero delle Cartelle

```
/var/www/eu-project-manager/
├───eu_projectmanager_database.sql
├───index.php
├───login.php
├───logout.php
├───newpasw.php
├───run_risk_calculation.php
├───.vscode/
│   └───sftp.json
├───api/
│   ├───activities.php
│   ├───get_activity_details.php
│   ├───get_milestone_details.php
│   ├───get_report_details.php
│   ├───get_work_package_details.php
│   └───projects.php
├───assets/
│   ├───css/
│   │   ├───bootstrap.min.css
│   │   ├───bootstrap.min.css.map
│   │   ├───custom.css
│   │   ├───paper-dashboard.css
│   │   ├───paper-dashboard.css.map
│   │   ├───paper-dashboard.min.css
│   │   └───pages/
│   │       ├───activities.css
│   │       ├───add-project-milestones.css
│   │       ├───add-project-partners.css
│   │       ├───add-project-workpackages.css
│   │       ├───calendar.css
│   │       ├───create-project.css
│   │       ├───dashboard.css
│   │       ├───gantt-backup.css
│   │       ├───gantt.back2.css
│   │       ├───gantt.css
│   │       ├───project-detail.css
│   │       ├───project-edit.css
│   │       ├───projects.css
│   │       └───reports.css
│   ├───demo/
│   │   ├───demo.css
│   │   └───demo.js
│   ├───fonts/
│   │   ├───nucleo-icons.eot
│   │   ├───nucleo-icons.ttf
│   │   ├───nucleo-icons.woff
│   │   └───nucleo-icons.woff2
│   ├───img/
│   │   ├───apple-icon.png
│   │   ├───bg5.jpg
│   │   ├───damir-bosnjak.jpg
│   │   ├───default-avatar.png
│   │   ├───favicon.png
│   │   ├───header.jpg
│   │   ├───jan-sendereks.jpg
│   │   ├───logo-small.png
│   │   ├───mike.jpg
│   │   └───faces/
│   │       ├───ayo-ogunseinde-1.jpg
│   │       ├───ayo-ogunseinde-2.jpg
│   │       ├───clem-onojeghuo-1.jpg
│   │       ├───clem-onojeghuo-2.jpg
│   │       ├───clem-onojeghuo-3.jpg
│   │       ├───clem-onojeghuo-4.jpg
│   │       ├───erik-lucatero-1.jpg
│   │       ├───erik-lucatero-2.jpg
│   │       ├───joe-gardner-1.jpg
│   │       ├───joe-gardner-2.jpg
│   │       ├───kaci-baum-1.jpg
│   │       └───kaci-baum-2.jpg
│   ├───js/
│   │   ├───paper-dashboard.js
│   │   ├───paper-dashboard.js.map
│   │   ├───paper-dashboard.min.js
│   │   ├───core/
│   │   │   ├───bootstrap.min.js
│   │   │   ├───jquery.min.js
│   │   │   └───popper.min.js
│   │   ├───pages/
│   │   │   ├───activities.js
│   │   │   ├───add-project-milestones.js
│   │   │   ├───add-project-partners.js
│   │   │   ├───add-project-workpackages.js
│   │   │   ├───calendar.js
│   │   │   ├───create-project.js
│   │   │   ├───dashboard.js
│   │   │   ├───gantt-backup.js
│   │   │   ├───gantt.back2.js
│   │   │   ├───gantt.js
│   │   │   ├───project-detail.js
│   │   │   ├───project-edit.js
│   │   │   ├───projects.js
│   │   │   ├───reports.js
│   │   │   └───reports.js.bak
│   │   └───plugins/
│   │       ├───bootstrap-notify.js
│   │       ├───chartjs.min.js
│   │       └───perfect-scrollbar.jquery.min.js
│   └───scss/
│       ├───paper-dashboard.scss
│       └───paper-dashboard/
│           ├───_alerts.scss
│           ├───_animated-buttons.scss
│           ├───_buttons.scss
│           ├───_cards.scss
│           ├───_checkboxes-radio.scss
│           ├───_dropdown.scss
│           ├───_fixed-plugin.scss
│           ├───_footers.scss
│           ├───_images.scss
│           ├───_inputs.scss
│           ├───_misc.scss
│           ├───_mixins.scss
│           ├───_navbar.scss
│           ├───_nucleo-outline.scss
│           ├───_page-header.scss
│           ├───_responsive.scss
│           ├───_sidebar-and-main-panel.scss
│           ├───_tables.scss
│           ├───_typography.scss
│           ├───_variables.scss
│           ├───cards/
│           │   ├───_card-chart.scss
│           │   ├───_card-map.scss
│           │   ├───_card-plain.scss
│           │   ├───_card-stats.scss
│           │   └───_card-user.scss
│           ├───mixins/
│           │   ├───_buttons.scss
│           │   ├───_cards.scss
│           │   ├───_dropdown.scss
│           │   ├───_inputs.scss
│           │   ├───_page-header.scss
│           │   └───_transparency.scss
│           │   └───...
│           └───plugins/
├───config/
│   ├───auth.php
│   ├───database_local.php
│   ├───database.php
│   ├───environment.php
│   └───settings.php
├───includes/
│   ├───footer.php
│   ├───functions.php
│   ├───header.php
│   ├───navbar.php
│   ├───sidebar.php
│   └───classes/
│       ├───AlertSystem.php
│       └───RiskCalculator.php
├───pages/
│   ├───activities.php
│   ├───add-project-milestones.php
│   ├───add-project-partners.php
│   ├───add-project-workpackages.php
│   ├───calendar.php
│   ├───create-project.php
│   ├───create-report.php
│   ├───dashboard.php
│   ├───debug_simple_report.php
│   ├───debug-delete-project.php
│   ├───delete-project.php
│   ├───delete-report.php
│   ├───edit-report.php
│   ├───manage-project-risks.php
│   ├───partners.php
│   ├───project-detail.php
│   ├───project-edit.php
│   ├───project-gantt.php
│   ├───projects.php
│   ├───reports.php
│   ├───risk-dashboard.php
│   ├───test_activity_update.php
│   └───users.php
├───paper-dashboard-master/
│   ├───CHANGELOG.md
│   ├───genezio.yaml
│   ├───gulpfile.js
│   ├───index.html
│   ├───ISSUE_TEMPLATE.md
│   ├───LICENSE
│   ├───nucleo-icons.html
│   ├───package.json
│   ├───README.md
│   ├───template.html
│   ├───.github/
│   │   └───workflows/
│   │       └───main.yml
│   ├───docs/
│   │   └───documentation.html
│   └───examples/
│       ├───dashboard.html
│       ├───icons.html
│       ├───map.html
│       ├───notifications.html
│       ├───tables.html
│       ├───typography.html
│       ├───upgrade.html
│       └───user.html
└───uploads/
    └───.gitkeep
```

## Spiegazione della Logica del Progetto e Relazioni tra i Componenti

Il progetto "EU Project Manager" è un'applicazione web basata su PHP e MySQL, progettata per la gestione di progetti europei. Utilizza un'architettura MVC (Model-View-Controller) semplificata, dove i file PHP nelle cartelle `pages/` fungono da "View" e in parte da "Controller", mentre la logica di business e l'interazione con il database sono gestite da classi e script nelle cartelle `includes/classes/`, `config/` e `api/`. La parte frontend si basa sul template Paper Dashboard, utilizzando Bootstrap, jQuery e altri plugin JavaScript.

### Componenti Principali e Loro Relazioni:

1.  **Autenticazione e Sessione (`index.php`, `login.php`, `logout.php`, `config/auth.php`, `includes/header.php`, `includes/functions.php`):**
    *   `index.php`: Probabilmente il punto di ingresso principale che reindirizza alla dashboard o alla pagina di login se l'utente non è autenticato.
    *   `login.php`: Gestisce il processo di autenticazione degli utenti.
    *   `logout.php`: Termina la sessione utente.
    *   `config/auth.php`: Contiene la logica per la gestione dell'autenticazione e delle sessioni utente, inclusi i controlli di accesso.
    *   `includes/header.php`: Viene incluso in quasi tutte le pagine e si occupa di avviare la sessione, includere le classi di autenticazione e reindirizzare gli utenti non autenticati.
    *   `includes/functions.php`: Contiene funzioni di utilità, inclusa `getUserId()` e `getUserRole()`, che recuperano i dati dell'utente dalla sessione.

2.  **Configurazione (`config/`):**
    *   `config/database.php`: Contiene le credenziali e la logica per la connessione al database MySQL. È il "Model" per l'interazione con il DB.
    *   `config/auth.php`: (già menzionato) Gestisce le impostazioni di autenticazione.
    *   `config/settings.php`: Probabilmente contiene impostazioni globali dell'applicazione.
    *   `config/environment.php`: Potrebbe definire variabili d'ambiente (es. sviluppo/produzione).

3.  **Interfaccia Utente (Frontend - `pages/`, `assets/`):**
    *   `pages/`: Contiene i file PHP che generano le diverse pagine dell'applicazione (es. `dashboard.php`, `projects.php`, `activities.php`, `reports.php`). Questi file includono `header.php`, `sidebar.php` e `navbar.php` per la struttura comune.
    *   `assets/css/`: Fogli di stile CSS, inclusi Bootstrap, Paper Dashboard e stili personalizzati per pagine specifiche.
    *   `assets/js/`: Script JavaScript, inclusi le librerie core (jQuery, Bootstrap, Popper) e script specifici per le pagine (`pages/`). Questi script gestiscono l'interattività lato client e spesso effettuano chiamate AJAX alle API.
    *   `assets/img/`: Immagini e icone utilizzate nell'interfaccia.
    *   `assets/fonts/`: Font personalizzati.
    *   `assets/scss/`: File SCSS per la pre-compilazione dei CSS, indicando l'uso di un pre-processore CSS.

4.  **Logica di Business e API (Backend - `api/`, `includes/classes/`):**
    *   `api/`: Contiene script PHP che fungono da endpoint API. Questi script ricevono richieste (spesso AJAX dal frontend), elaborano i dati, interagiscono con il database e restituiscono risposte (solitamente in formato JSON). Esempi includono `api/activities.php` (per aggiornare lo stato delle attività) e `api/projects.php`.
    *   `includes/classes/`: Contiene classi PHP riutilizzabili che incapsulano la logica di business.
        *   `AlertSystem.php`: Probabilmente gestisce la creazione e la visualizzazione di messaggi di allerta.
        *   `RiskCalculator.php`: Potrebbe contenere la logica per il calcolo e la gestione dei rischi di progetto.
    *   `includes/functions.php`: Contiene funzioni PHP di utilità che possono essere chiamate da qualsiasi parte del codice.

5.  **Database (`eu_projectmanager_database.sql`):**
    *   Questo file SQL definisce lo schema del database, incluse tabelle come `users`, `projects`, `partners`, `work_packages`, `activities`, `milestones`, `activity_reports`, `project_risks`, `risks`, ecc.
    *   È il cuore del sistema, dove tutti i dati del progetto sono memorizzati e gestiti. Le relazioni tra le tabelle (tramite chiavi esterne) definiscono la struttura dei dati del progetto.

6.  **Gestione dei Progetti, Attività e Report:**
    *   **Progetti:** Le pagine `pages/projects.php`, `pages/create-project.php`, `pages/project-detail.php`, `pages/project-edit.php` gestiscono la creazione, visualizzazione e modifica dei progetti. `api/projects.php` gestisce le operazioni CRUD lato backend.
    *   **Work Packages:** Le pagine `pages/add-project-workpackages.php` e le tabelle `work_packages` gestiscono la suddivisione dei progetti in pacchetti di lavoro.
    *   **Attività:** Le pagine `pages/activities.php` e `api/activities.php` sono centrali per la gestione delle attività all'interno dei work package. Le attività sono assegnate a partner responsabili.
    *   **Milestone:** `pages/add-project-milestones.php` e la tabella `milestones` gestiscono i traguardi del progetto.
    *   **Report:** `pages/reports.php`, `pages/create-report.php`, `pages/edit-report.php` e la tabella `activity_reports` gestiscono la creazione e la visualizzazione dei report di attività.

7.  **Altre Funzionalità:**
    *   `run_risk_calculation.php`: Probabilmente uno script per eseguire calcoli sui rischi in background o su richiesta.
    *   `pages/risk-dashboard.php`, `pages/manage-project-risks.php`: Interfacce per la gestione e visualizzazione dei rischi.
    *   `uploads/`: Cartella per i file caricati dagli utenti.
    *   `.vscode/sftp.json`: File di configurazione per SFTP, utile per lo sviluppo.
    *   `paper-dashboard-master/`: Contiene i file originali del template Paper Dashboard, probabilmente usati come riferimento o per la compilazione.

### Flusso di Lavoro Tipico:

1.  **Accesso:** L'utente accede tramite `login.php`. `config/auth.php` e `includes/header.php` gestiscono l'autenticazione e la creazione della sessione.
2.  **Navigazione:** Una volta autenticato, l'utente viene reindirizzato a `dashboard.php` o `projects.php`. La `sidebar.php` e `navbar.php` forniscono la navigazione.
3.  **Visualizzazione Dati:** Le pagine in `pages/` recuperano i dati dal database tramite query PHP dirette (utilizzando la connessione da `config/database.php`) e li visualizzano.
4.  **Interazione (Modifica/Creazione):** Quando l'utente interagisce con un form (es. per aggiornare lo stato di un'attività), il JavaScript (`assets/js/pages/`) invia una richiesta AJAX a un endpoint API in `api/`.
5.  **Elaborazione Backend:** Lo script API riceve la richiesta, esegue controlli di autorizzazione (come quelli implementati per le attività), interagisce con il database (tramite `config/database.php`) per aggiornare o inserire dati, e restituisce una risposta JSON al frontend.
6.  **Aggiornamento Frontend:** Il JavaScript riceve la risposta JSON e aggiorna dinamicamente l'interfaccia utente.

In sintesi, il progetto è una robusta applicazione web per la gestione di progetti, con una chiara separazione tra frontend e backend, e un'enfasi sulla gestione dei dati tramite un database relazionale.

### Struttura del Database

Il database `eu_projectmanager` è il cuore del sistema e memorizza tutti i dati relativi alla gestione dei progetti. È composto da diverse tabelle relazionate tra loro tramite chiavi primarie (PK) e chiavi esterne (FK). Di seguito una descrizione delle tabelle principali e delle loro relazioni:

#### Tabelle Principali:

*   **`users`**
    *   **Scopo:** Memorizza le informazioni sugli utenti del sistema.
    *   **Campi Chiave:** `id` (PK), `username`, `email`, `password`, `full_name`, `role` (super_admin, coordinator, partner, admin), `partner_id` (FK a `partners.id`).
    *   **Relazioni:**
        *   `1:N` con `partners` (un partner può avere molti utenti).
        *   `1:N` con `projects` (un utente può essere `coordinator_id` di molti progetti).
        *   `1:N` con `alerts` (un utente riceve molti alert).
        *   `1:N` con `uploaded_files` (un utente carica molti file).

*   **`partners`**
    *   **Scopo:** Memorizza le informazioni sulle organizzazioni partner coinvolte nei progetti.
    *   **Campi Chiave:** `id` (PK), `name`, `organization_type`, `country`.
    *   **Relazioni:**
        *   `N:1` con `users` (molti utenti appartengono a un partner).
        *   `N:M` con `projects` tramite `project_partners` (molti partner possono partecipare a molti progetti).
        *   `N:1` con `activities` (un partner è responsabile di molte attività - `responsible_partner_id`).
        *   `N:1` con `work_packages` (un partner può essere `lead_partner_id` di molti WP).
        *   `N:1` con `activity_reports` (un partner invia molti report).

*   **`projects`**
    *   **Scopo:** Memorizza i dettagli dei progetti europei.
    *   **Campi Chiave:** `id` (PK), `name`, `description`, `program_type`, `start_date`, `end_date`, `coordinator_id` (FK a `users.id`), `status`, `budget`.
    *   **Relazioni:**
        *   `N:1` con `users` (un progetto ha un coordinatore).
        *   `N:M` con `partners` tramite `project_partners`.
        *   `1:N` con `work_packages` (un progetto ha molti WP).
        *   `1:N` con `activities` (un progetto ha molte attività).
        *   `1:N` con `milestones` (un progetto ha molte milestone).
        *   `1:N` con `project_risks` (un progetto ha molti rischi associati).
        *   `1:N` con `activity_reports` (un progetto ha molti report).

*   **`work_packages`**
    *   **Scopo:** Rappresenta i pacchetti di lavoro all'interno di un progetto.
    *   **Campi Chiave:** `id` (PK), `project_id` (FK a `projects.id`), `wp_number`, `name`, `description`, `lead_partner_id` (FK a `partners.id`), `start_date`, `end_date`, `budget`, `status`, `progress`.
    *   **Relazioni:**
        *   `N:1` con `projects` (un WP appartiene a un progetto).
        *   `N:1` con `partners` (un WP ha un partner responsabile).
        *   `1:N` con `activities` (un WP ha molte attività).
        *   `1:N` con `milestones` (un WP può avere molte milestone).

*   **`activities`**
    *   **Scopo:** Dettagli delle singole attività all'interno dei work package.
    *   **Campi Chiave:** `id` (PK), `work_package_id` (FK a `work_packages.id`), `project_id` (FK a `projects.id`), `responsible_partner_id` (FK a `partners.id`), `name`, `description`, `start_date`, `end_date`, `status`, `progress`, `budget`.
    *   **Relazioni:**
        *   `N:1` con `work_packages` (un'attività appartiene a un WP).
        *   `N:1` con `projects` (un'attività appartiene a un progetto).
        *   `N:1` con `partners` (un'attività ha un partner responsabile).
        *   `1:N` con `activity_reports` (un'attività può avere molti report).
        *   `1:N` con `alerts` (un'attività può generare alert).

*   **`activity_reports`**
    *   **Scopo:** Memorizza i report di avanzamento delle attività.
    *   **Campi Chiave:** `id` (PK), `activity_id` (FK a `activities.id`), `partner_id` (FK a `partners.id`), `user_id` (FK a `users.id`), `report_date`, `description`, `project_id` (FK a `projects.id`).
    *   **Relazioni:**
        *   `N:1` con `activities` (un report si riferisce a un'attività).
        *   `N:1` con `partners` (un report è inviato da un partner).
        *   `N:1` con `users` (un report è inviato da un utente).
        *   `N:1` con `projects` (un report si riferisce a un progetto).
        *   `1:N` con `uploaded_files` (un report può avere molti file allegati).

*   **`project_partners`**
    *   **Scopo:** Tabella di giunzione per la relazione N:M tra `projects` e `partners`.
    *   **Campi Chiave:** `id` (PK), `project_id` (FK a `projects.id`), `partner_id` (FK a `partners.id`), `role` (coordinator/partner), `budget_allocated`.

*   **`project_risks`**
    *   **Scopo:** Associa i rischi generici (`risks`) a specifici progetti e ne traccia lo stato attuale.
    *   **Campi Chiave:** `id` (PK), `project_id` (FK a `projects.id`), `risk_id` (FK a `risks.id`), `current_probability`, `current_impact`, `current_score`, `status`.
    *   **Relazioni:**
        *   `N:1` con `projects` (un rischio di progetto si riferisce a un progetto).
        *   `N:1` con `risks` (un rischio di progetto si basa su un rischio generico).
        *   `1:N` con `risk_history` (un rischio di progetto ha una cronologia di modifiche).
        *   `1:N` con `risk_alerts` (un rischio di progetto può generare alert).

*   **`risks`**
    *   **Scopo:** Catalogo dei rischi generici predefiniti.
    *   **Campi Chiave:** `id` (PK), `risk_code`, `category`, `description`, `critical_threshold`.
    *   **Relazioni:**
        *   `1:N` con `project_risks` (un rischio generico può essere associato a molti progetti).

*   **`milestones`**
    *   **Scopo:** Traccia i traguardi importanti all'interno dei progetti o work package.
    *   **Campi Chiave:** `id` (PK), `project_id` (FK a `projects.id`), `work_package_id` (FK a `work_packages.id`), `name`, `due_date`, `status`.

*   **`alerts`**
    *   **Scopo:** Memorizza le notifiche per gli utenti.
    *   **Campi Chiave:** `id` (PK), `user_id` (FK a `users.id`), `project_id` (FK a `projects.id`), `activity_id` (FK a `activities.id`), `type`, `title`, `message`.

#### Relazioni Chiave e Flusso Dati:

Il database è progettato per supportare la gestione completa del ciclo di vita di un progetto. I `projects` sono il fulcro, suddivisi in `work_packages`, che a loro volta contengono `activities`. Ogni `activity` è assegnata a un `responsible_partner_id` (che è un `partner`). Gli `users` sono associati a `partners` e possono avere diversi `roles`. I `reports` sono collegati alle `activities` e ai `partners` che li hanno inviati. Il sistema di `risks` permette di associare e tracciare i rischi a livello di progetto.

Questa struttura permette di navigare facilmente tra progetti, partner, utenti, attività e report, fornendo una visione integrata dello stato di avanzamento e delle responsabilità.