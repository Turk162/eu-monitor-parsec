# DOCUMENTAZIONE TECNICA PORTALE EU-PROJECT-MANAGER

**Versione:** 1.0  
**Data:** Ottobre 2025  
**Scopo:** Guida completa per sviluppo e manutenzione del sistema

---

## 1. ARCHITETTURA GENERALE

### 1.1 Stack Tecnologico

```
Frontend:
- HTML5 + Bootstrap 4.4.1
- jQuery 3.x
- Paper Dashboard Template
- Font Awesome icons

Backend:
- PHP 8.4+ 
- MySQL/MariaDB 10.11+
- PDO per database access
- Session-based authentication

Server:
- Apache/Nginx
- PHP-FPM
```

### 1.2 Struttura Directory

```
/var/www/eu-project-manager/
├── index.php                    # Entry point
├── login.php                    # Autenticazione
├── logout.php                   # Logout
├── config/
│   ├── environment.php          # Rileva locale/produzione
│   ├── database.php             # Connessione DB produzione
│   ├── database_local.php       # Connessione DB locale
│   ├── auth.php                 # Classe Auth
│   └── settings.php             # Configurazioni globali
├── includes/
│   ├── header.php               # Header comune (include auth)
│   ├── sidebar.php              # Menu laterale
│   ├── navbar.php               # Top navbar
│   ├── footer.php               # Footer
│   ├── functions.php            # Utility functions
│   └── classes/
│       ├── AlertSystem.php      # Sistema notifiche
│       └── RiskCalculator.php   # Calcolo rischi
├── pages/
│   ├── dashboard.php            # Dashboard principale
│   ├── projects.php             # Lista progetti
│   ├── activities.php           # Gestione attività
│   ├── reports.php              # Report
│   └── ...
├── api/
│   ├── get_*.php                # Endpoint GET
│   ├── update_*.php             # Endpoint UPDATE
│   └── mark_alert_read.php      # Gestione alert
├── assets/
│   ├── css/
│   │   ├── bootstrap.min.css
│   │   ├── paper-dashboard.css
│   │   ├── custom.css
│   │   └── pages/               # CSS specifici per pagina
│   ├── js/
│   │   ├── core/                # jQuery, Bootstrap, Popper
│   │   └── pages/               # JS specifici per pagina
│   └── img/
└── uploads/                      # File caricati dagli utenti
```

---

## 2. SISTEMA DI AUTENTICAZIONE

### 2.1 Classe Auth (`config/auth.php`)

**Costruttore:**
```php
$auth = new Auth($db_connection);
```

**Metodi principali:**

| Metodo | Parametri | Return | Descrizione |
|--------|-----------|--------|-------------|
| `login($username, $password)` | string, string | array | Autentica utente (username O email) |
| `logout()` | - | bool | Distrugge sessione |
| `isLoggedIn()` | - | bool | Verifica se loggato |
| `requireLogin()` | - | void | Redirect a login se non auth |
| `hasRole($roles)` | string\|array | bool | Verifica ruolo utente |
| `requireRole($roles)` | string\|array | void | HTTP 403 se ruolo insufficiente |
| `createUser($data)` | array | array | Crea nuovo utente |
| `changePassword($user_id, $old, $new)` | int, string, string | array | Modifica password |

**Dati sessione popolati:**
```php
$_SESSION['user_id']       // int
$_SESSION['username']      // string
$_SESSION['email']         // string
$_SESSION['full_name']     // string
$_SESSION['role']          // 'super_admin'|'coordinator'|'partner'|'admin'
$_SESSION['partner_id']    // int|null
$_SESSION['logged_in']     // bool
```

### 2.2 Sistema Ruoli

| Ruolo | Permessi | Caso d'uso |
|-------|----------|------------|
| `super_admin` | Accesso completo a tutto | Amministratore sistema |
| `coordinator` | Gestione progetti assegnati | Coordinatore progetto |
| `partner` | Accesso ai propri progetti/attività | Organizzazione partner |
| `admin` | (Non utilizzato attualmente) | Riservato futuro |

**Gerarchia permessi:**
```
super_admin > coordinator > partner > admin
```

### 2.3 Controlli Autorizzazione Standard

**Livello 1 - Global (in header.php):**
```php
$auth->requireLogin(); // Blocca accesso non autenticati
```

**Livello 2 - Ruolo:**
```php
if (!in_array($user_role, ['super_admin', 'coordinator'])) {
    die('Access denied');
}
```

**Livello 3 - Ownership:**
```php
// Solo coordinator del progetto
if ($user_role !== 'super_admin' && $project['coordinator_id'] !== $user_id) {
    die('Permission denied');
}

// Solo responsible partner
if ($activity['responsible_partner_id'] !== $user_partner_id) {
    die('Not authorized');
}
```

---

## 3. DATABASE SCHEMA

### 3.1 Tabelle Principali

**users**
```sql
id, username, email, password (hash), full_name, role, partner_id, is_active
```

**partners**
```sql
id, name, organization_type, country
```

**projects**
```sql
id, name, description, program_type, start_date, end_date, 
coordinator_id (FK users), status, budget
```

**work_packages**
```sql
id, project_id (FK), wp_number, name, description, 
lead_partner_id (FK partners), start_date, end_date, budget, status, progress
```

**activities**
```sql
id, work_package_id (FK), project_id (FK), activity_number, name, description,
responsible_partner_id (FK partners), start_date, end_date, due_date,
status, progress
```

**activity_reports**
```sql
id, activity_id (FK), partner_id (FK), user_id (FK), project_id (FK),
report_date, description, participants_data,
coordinator_feedback, reviewed_at, reviewed_by
```

**alerts**
```sql
id, user_id (FK), project_id (FK), activity_id (FK),
type, title, message, is_read, created_at
```

### 3.2 Relazioni Chiave

```
projects (1) ──→ (N) work_packages
work_packages (1) ──→ (N) activities
activities (1) ──→ (N) activity_reports
users (1) ──→ (N) alerts
partners (N) ←──→ (M) projects (via project_partners)
```

### 3.3 Status Values

**Project Status:**
- `planning` - In fase di pianificazione
- `active` - Progetto attivo
- `suspended` - Sospeso temporaneamente
- `completed` - Completato

**Activity/WP Status:**
- `not_started` - Non iniziato
- `in_progress` - In corso
- `completed` - Completato
- `overdue` - In ritardo
- `delayed` - Ritardato

---

## 4. TEMPLATE PAGINA STANDARD

### 4.1 Struttura File PHP

```php
<?php
// ===================================================================
//  NOME PAGINA - DESCRIZIONE
// ===================================================================

// ===================================================================
//  PAGE CONFIGURATION
// ===================================================================

$page_title = 'Titolo Pagina';
$page_css_path = '../assets/css/pages/nome-pagina.css';
$page_js_path = '../assets/js/pages/nome-pagina.js';

// ===================================================================
//  INCLUDES
// ===================================================================

require_once '../includes/header.php';

// ===================================================================
//  AUTHORIZATION CHECK
// ===================================================================

if (!in_array($user_role, ['super_admin', 'coordinator'])) {
    $_SESSION['error'] = 'You do not have permission to access this page.';
    header('Location: dashboard.php');
    exit;
}

// ===================================================================
//  DATABASE CONNECTION & QUERIES
// ===================================================================

$database = new Database();
$conn = $database->connect();

// Query dati necessari
$stmt = $conn->prepare("SELECT * FROM table WHERE condition = ?");
$stmt->execute([$param]);
$data = $stmt->fetchAll();

// ===================================================================
//  HANDLE FORM SUBMISSIONS
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch($_POST['action']) {
            case 'create':
                // Logica creazione
                $_SESSION['success'] = 'Item created successfully!';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
                break;
            
            case 'update':
                // Logica update
                break;
            
            case 'delete':
                // Logica delete
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
}

?>

<!-- ===================================================================
     HTML CONTENT
     =================================================================== -->

<?php require_once '../includes/sidebar.php'; ?>
<?php require_once '../includes/navbar.php'; ?>

<div class="content">
    <!-- Alert Message -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <!-- Contenuto Principale -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Titolo Card</h4>
                </div>
                <div class="card-body">
                    <!-- Contenuto -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
```

### 4.2 Convenzioni Naming

**File:**
- PHP: `kebab-case.php` (es. `project-detail.php`)
- CSS: `kebab-case.css` (es. `project-detail.css`)
- JS: `kebab-case.js` (es. `project-detail.js`)

**Database:**
- Tabelle: `snake_case` (es. `activity_reports`)
- Campi: `snake_case` (es. `created_at`)

**PHP:**
- Variabili: `$snake_case` (es. `$user_id`)
- Funzioni: `camelCase()` (es. `getUserId()`)
- Classi: `PascalCase` (es. `AlertSystem`)

**JavaScript:**
- Variabili: `camelCase` (es. `activityId`)
- Funzioni: `camelCase()` (es. `loadActivities()`)

---

## 5. PATTERN API ENDPOINTS

### 5.1 GET Endpoint Standard

**File:** `api/get_entity_details.php`

```php
<?php
header('Content-Type: application/json');

// Minimal includes (NO full header.php)
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Start session for auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth check
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Validate parameters
$entity_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$entity_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->connect();
    
    // Query
    $stmt = $conn->prepare("SELECT * FROM entities WHERE id = ?");
    $stmt->execute([$entity_id]);
    $entity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$entity) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Not found']);
        exit;
    }
    
    // Authorization check
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];
    
    $can_access = ($user_role === 'super_admin' || $entity['owner_id'] === $user_id);
    
    if (!$can_access) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'data' => $entity
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
```

### 5.2 POST Endpoint Standard

**File:** `api/update_entity.php`

```php
<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Auth check
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Validate parameters
if (!isset($_POST['entity_id']) || !isset($_POST['field'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->connect();
    
    $entity_id = (int)$_POST['entity_id'];
    $field_value = trim($_POST['field']);
    $user_id = (int)$_SESSION['user_id'];
    
    // Authorization check
    // ... check ownership ...
    
    // Update
    $stmt = $conn->prepare("UPDATE entities SET field = ?, updated_at = NOW() WHERE id = ?");
    $success = $stmt->execute([$field_value, $entity_id]);
    
    if ($success && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Updated successfully'
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Entity not found or no changes made'
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
```

### 5.3 Response Format Standard

**Success:**
```json
{
    "success": true,
    "message": "Operation completed",
    "data": {
        "id": 123,
        "field": "value"
    }
}
```

**Error:**
```json
{
    "success": false,
    "message": "Error description",
    "error_code": "OPTIONAL_CODE"
}
```

---

## 6. PATTERN JAVASCRIPT/AJAX

### 6.1 AJAX Call Standard

```javascript
$.ajax({
    url: '../api/endpoint.php',
    type: 'POST', // o 'GET'
    data: {
        action: 'update',
        entity_id: entityId,
        field: value
    },
    dataType: 'json',
    beforeSend: function() {
        // Disable button, show loading
        $('#submitBtn').prop('disabled', true).html('Processing...');
    },
    success: function(response) {
        if (response.success) {
            // Update UI
            showNotification(response.message, 'success');
            
            // Optional: refresh or redirect
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(response.message, 'danger');
        }
    },
    error: function(xhr, status, error) {
        console.error('AJAX Error:', status, error);
        console.error('Response:', xhr.responseText);
        showNotification('Connection error. Please try again.', 'danger');
    },
    complete: function() {
        // Re-enable button
        $('#submitBtn').prop('disabled', false).html('Submit');
    }
});
```

### 6.2 Event Delegation Pattern

**Per elementi dinamici:**

```javascript
$(document).on('click', '.dynamic-button', function(e) {
    e.preventDefault();
    
    const $button = $(this);
    const itemId = $button.data('item-id');
    
    // Logica...
});
```

### 6.3 Modal Pattern

```javascript
function openEditModal(itemId) {
    const modal = $('#editModal');
    const modalBody = $('#editModalBody');
    
    // 1. Show loading
    modalBody.html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i></div>');
    modal.modal('show');
    
    // 2. Fetch data
    $.ajax({
        url: `../api/get_item_details.php?id=${itemId}`,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const item = response.data;
                
                // 3. Build form HTML
                const formHtml = `
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" 
                               value="${item.name || ''}" required>
                    </div>
                    <input type="hidden" name="item_id" value="${item.id}">
                `;
                
                modalBody.html(formHtml);
            } else {
                modalBody.html(`<div class="alert alert-danger">${response.message}</div>`);
            }
        },
        error: function() {
            modalBody.html('<div class="alert alert-danger">Error loading data</div>');
        }
    });
}
```

---

## 7. SICUREZZA

### 7.1 Checklist Sicurezza Obbligatoria

**Per OGNI endpoint API:**
- [ ] Header `Content-Type: application/json`
- [ ] Verifica metodo HTTP (`POST` vs `GET`)
- [ ] Controllo autenticazione (`isLoggedIn()`)
- [ ] Validazione input (type casting, whitelist)
- [ ] Controllo autorizzazione (ownership/role)
- [ ] Prepared statements SQL (NO string concatenation)
- [ ] Error logging (NON echo dell'errore)
- [ ] HTTP status code appropriati

**Per OGNI form HTML:**
- [ ] `htmlspecialchars()` su output
- [ ] Validazione server-side (oltre client-side)
- [ ] Sanitizzazione input con `trim()` e `strip_tags()`
- [ ] CSRF token (DA IMPLEMENTARE)

### 7.2 Input Validation

```php
// Integer
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// String (rimuovi spazi e tags)
$name = trim(strip_tags($_POST['name'] ?? ''));

// Email
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

// Date (YYYY-MM-DD)
$date = $_POST['date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = null;
}

// Enum (whitelist)
$status = $_POST['status'] ?? '';
$valid_statuses = ['not_started', 'in_progress', 'completed'];
if (!in_array($status, $valid_statuses)) {
    die('Invalid status');
}
```

### 7.3 Output Escaping

```php
// HTML
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');

// JavaScript (in inline script)
echo json_encode($user_input, JSON_HEX_TAG | JSON_HEX_AMP);

// URL
echo urlencode($user_input);

// SQL (usa sempre prepared statements)
$stmt = $conn->prepare("SELECT * FROM table WHERE field = ?");
$stmt->execute([$user_input]);
```

### 7.4 SQL Prepared Statements

**✅ CORRETTO:**
```php
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
```

**❌ MAI FARE:**
```php
$query = "SELECT * FROM users WHERE id = $user_id"; // SQL Injection!
$result = $conn->query($query);
```

---

## 8. SISTEMA NOTIFICHE E ALERT

### 8.1 Flash Messages (Sessione)

**Impostare messaggio:**
```php
$_SESSION['success'] = 'Operation completed successfully!';
$_SESSION['error'] = 'An error occurred.';
$_SESSION['warning'] = 'Warning message.';
$_SESSION['info'] = 'Information message.';
```

**Visualizzare in HTML:**
```php
<?php if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>
```

### 8.2 JavaScript Notifications

**Funzione helper:**
```javascript
function showNotification(message, type = 'info') {
    // type: 'success', 'danger', 'warning', 'info'
    
    const notification = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    `;
    
    $('#notification-container').html(notification);
    
    // Auto-dismiss dopo 5 secondi
    setTimeout(() => {
        $('.alert').fadeOut();
    }, 5000);
}
```

### 8.3 Sistema Alert Database

**Creare alert:**
```php
require_once '../includes/classes/AlertSystem.php';

$alertSystem = new AlertSystem($conn);

$alertSystem->sendDashboardAlert(
    $user_id,           // Destinatario
    'risk',             // Tipo
    'Risk Alert',       // Titolo
    'Risk description', // Messaggio
    $project_id,        // Progetto (optional)
    $activity_id        // Attività (optional)
);
```

**Tipi alert disponibili:**
- `deadline` - Scadenze
- `report_submitted` - Report inviato
- `milestone` - Milestone raggiunta
- `general` - Generico
- `risk` - Alert rischio
- `risk_persistent` - Rischio persistente critico

---

## 9. UTILITY FUNCTIONS

### 9.1 Functions Disponibili (`includes/functions.php`)

```php
// Auth helpers
isLoggedIn()                    // bool
requireLogin()                  // void (redirect)
getUserId()                     // int|null
getUserRole()                   // string|null

// Formatting
formatDate($date, $format)      // string (default: d/m/Y)
formatDateTime($datetime)       // string
getStatusBadge($status)         // string (HTML badge)

// Security
sanitizeInput($input)           // string (htmlspecialchars + trim)

// Navigation
redirectTo($url)                // void

// Messages (deprecated, usa $_SESSION direttamente)
showAlert($message, $type)      // void
displayAlert()                  // void
```

### 9.2 Database Class

```php
$database = new Database();
$conn = $database->connect();

// $conn è un oggetto PDO
$stmt = $conn->prepare("SELECT * FROM table WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
```

---

## 10. WORKFLOW SVILUPPO NUOVA FEATURE

### 10.1 Checklist Sviluppo

**1. Pianificazione**
- [ ] Definire requisiti funzionali
- [ ] Identificare tabelle DB coinvolte
- [ ] Definire autorizzazioni necessarie
- [ ] Creare wireframe/mockup (se UI)

**2. Database**
- [ ] Creare/modificare tabelle necessarie
- [ ] Aggiungere indici appropriati
- [ ] Definire foreign keys
- [ ] Testare query in phpMyAdmin

**3. Backend (PHP)**
- [ ] Creare pagina principale in `pages/`
- [ ] Implementare controlli autorizzazione
- [ ] Creare endpoint API in `api/` se necessario
- [ ] Implementare validazione input
- [ ] Testare con dati reali

**4. Frontend**
- [ ] Creare HTML seguendo template standard
- [ ] Creare CSS in `assets/css/pages/` se necessario
- [ ] Creare JS in `assets/js/pages/` se necessario
- [ ] Implementare AJAX calls
- [ ] Testare UI su diversi browser

**5. Testing**
- [ ] Test funzionalità come super_admin
- [ ] Test come coordinator
- [ ] Test come partner
- [ ] Test validazione input (XSS, SQL Injection)
- [ ] Test error handling
- [ ] Test su mobile

**6. Deploy**
- [ ] Commit codice su repository
- [ ] Backup database produzione
- [ ] Deploy su staging per test finale
- [ ] Deploy su produzione
- [ ] Monitorare error logs

### 10.2 Esempio: Aggiungere Nuova Pagina "Tasks"

**Step 1: Database**
```sql
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    activity_id INT,
    assigned_to INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATE,
    status ENUM('todo', 'in_progress', 'done') DEFAULT 'todo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE
);
```

**Step 2: Pagina Principale (`pages/tasks.php`)**
```php
<?php
$page_title = 'My Tasks';
$page_css_path = '../assets/css/pages/tasks.css';
$page_js_path = '../assets/js/pages/tasks.js';

require_once '../includes/header.php';

$database = new Database();
$conn = $database->connect();

// Query tasks per l'utente corrente
$stmt = $conn->prepare("
    SELECT t.*, p.name as project_name, a.name as activity_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN activities a ON t.activity_id = a.id
    WHERE t.assigned_to = ?
    ORDER BY t.due_date ASC
");
$stmt->execute([$user_id]);
$tasks = $stmt->fetchAll();
?>

<?php require_once '../includes/sidebar.php'; ?>
<?php require_once '../includes/navbar.php'; ?>

<div class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">My Tasks</h4>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Project</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($tasks as $task): ?>
                            <tr>
                                <td><?= htmlspecialchars($task['title']) ?></td>
                                <td><?= htmlspecialchars($task['project_name']) ?></td>
                                <td><?= formatDate($task['due_date']) ?></td>
                                <td><?= getStatusBadge($task['status']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary update-status" 
                                            data-task-id="<?= $task['id'] ?>">
                                        Update
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
```

**Step 3: API Endpoint (`api/update_task_status.php`)**
```php
<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
$new_status = $_POST['status'] ?? '';

$valid_statuses = ['todo', 'in_progress', 'done'];
if (!$task_id || !in_array($new_status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->connect();
    $user_id = (int)$_SESSION['user_id'];
    
    // Verifica ownership
    $stmt = $conn->prepare("SELECT assigned_to FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();
    
    if (!$task || $task['assigned_to'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    // Update
    $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ?");
    $success = $stmt->execute([$new_status, $task_id]);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Task updated']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
```

**Step 4: JavaScript (`assets/js/pages/tasks.js`)**
```javascript
$(document).ready(function() {
    
    $('.update-status').click(function() {
        const $button = $(this);
        const taskId = $button.data('task-id');
        
        // Mostra prompt per nuovo status
        const newStatus = prompt('Enter new status (todo/in_progress/done):');
        
        if (!newStatus) return;
        
        $.ajax({
            url: '../api/update_task_status.php',
            type: 'POST',
            data: {
                task_id: taskId,
                status: newStatus
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Task updated successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Connection error. Please try again.');
            }
        });
    });
    
});
```

**Step 5: Aggiungere al Menu (`includes/sidebar.php`)**
```html
<li>
    <a href="./tasks.php">
        <i class="nc-icon nc-check-2"></i>
        <p>My Tasks</p>
    </a>
</li>
```

---

## 11. DEBUGGING E TROUBLESHOOTING

### 11.1 Error Logging

**Locale (development):**
```php
// In config/environment.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

**Produzione:**
```php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/php-error.log');
```

### 11.2 Debug AJAX

**Console logging:**
```javascript
$.ajax({
    url: 'api/endpoint.php',
    // ...
    success: function(response) {
        console.log('Success Response:', response);
    },
    error: function(xhr, status, error) {
        console.error('AJAX Error:', status, error);
        console.error('Response Text:', xhr.responseText);
        console.error('Status Code:', xhr.status);
    }
});
```

### 11.3 Query Debugging

```php
try {
    $stmt = $conn->prepare("SELECT * FROM table WHERE id = ?");
    $stmt->execute([$id]);
    
    // Debug query
    error_log("Query executed: " . $stmt->queryString);
    error_log("Rows returned: " . $stmt->rowCount());
    
} catch (PDOException $e) {
    error_log("SQL Error: " . $e->getMessage());
    error_log("Query: " . $stmt->queryString);
}
```

### 11.4 Common Issues

| Problema | Causa | Soluzione |
|----------|-------|-----------|
| "Headers already sent" | Output prima di `header()` | Nessun echo/space prima di `header()` |
| Session data persa | Session non started | `session_start()` all'inizio |
| AJAX 500 error | PHP syntax error | Check error log |
| AJAX returns HTML | Full page invece JSON | Non includere `header.php` in API |
| DB connection fails | Credenziali errate | Verifica `database.php` |
| Permission denied | Controllo auth fallito | Debug con `var_dump($user_role)` |

---

## 12. BEST PRACTICES

### 12.1 Code Quality

**PHP:**
- Sempre usare prepared statements
- Type hint nei parametri funzioni
- Docblock per funzioni complesse
- Return early per ridurre nesting
- Nomi variabili descrittivi

**JavaScript:**
- Usare `const` e `let` invece di `var`
- Event delegation per elementi dinamici
- Cache delle query jQuery
- Evitare callback hell (usa Promises)
- Commenti per logica complessa

**SQL:**
- Indici su colonne usate in WHERE/JOIN
- LIMIT nelle query di lista
- Evitare SELECT *
- Normalizzazione fino a 3NF
- Foreign keys con ON DELETE/UPDATE

### 12.2 Performance

**Database:**
- N+1 queries → JOIN
- Paginazione per liste lunghe
- Indici su foreign keys
- EXPLAIN ANALYZE per query lente

**Frontend:**
- Minimizzare AJAX calls
- Cache client-side quando possibile
- Lazy loading immagini
- Debounce su input search

### 12.3 Manutenibilità

- Un file = una responsabilità
- Funzioni < 50 righe
- Commenti su "perché", non "cosa"
- Refactoring codice duplicato
- Versioning con Git

---

## 13. RIFERIMENTI RAPIDI

### 13.1 Variabili Globali Disponibili

```php
$conn          // PDO connection
$user_id       // int - Current user ID
$user_role     // string - Current user role
$user_partner_id // int|null - Partner organization
$page_title    // string - Page title
$page_css_path // string - Custom CSS path
$page_js_path  // string - Custom JS path
```

### 13.2 Bootstrap Classes Comuni

```html
<!-- Alerts -->
<div class="alert alert-success">Success message</div>
<div class="alert alert-danger">Error message</div>
<div class="alert alert-warning">Warning message</div>
<div class="alert alert-info">Info message</div>

<!-- Buttons -->
<button class="btn btn-primary">Primary</button>
<button class="btn btn-success">Success</button>
<button class="btn btn-danger">Danger</button>
<button class="btn btn-sm btn-outline-info">Small Outline</button>

<!-- Badges -->
<span class="badge badge-success">Completed</span>
<span class="badge badge-primary">In Progress</span>
<span class="badge badge-secondary">Not Started</span>

<!-- Cards -->
<div class="card">
    <div class="card-header">
        <h4 class="card-title">Title</h4>
    </div>
    <div class="card-body">Content</div>
</div>
```

### 13.3 Paper Dashboard Icons

```html
<i class="nc-icon nc-bank"></i>          <!-- Projects -->
<i class="nc-icon nc-briefcase-24"></i>  <!-- Work Packages -->
<i class="nc-icon nc-chart-bar-32"></i>  <!-- Reports -->
<i class="nc-icon nc-single-02"></i>     <!-- Users -->
<i class="nc-icon nc-settings"></i>      <!-- Settings -->
<i class="nc-icon nc-bell-55"></i>       <!-- Notifications -->
<i class="nc-icon nc-alert-circle-i"></i> <!-- Risks -->
```

### 13.4 SQL Queries Comuni

```sql
-- Get user projects
SELECT DISTINCT p.* 
FROM projects p
JOIN project_partners pp ON p.id = pp.project_id
WHERE pp.partner_id = ?;

-- Get activities for partner
SELECT a.*, wp.name as wp_name, p.name as project_name
FROM activities a
JOIN work_packages wp ON a.work_package_id = wp.id
JOIN projects p ON wp.project_id = p.id
WHERE a.responsible_partner_id = ?;

-- Count unread alerts
SELECT COUNT(*) FROM alerts 
WHERE user_id = ? AND is_read = 0;

-- Get project progress
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
FROM activities
WHERE project_id = ?;
```

---

## 14. CONTATTI E RISORSE

**Documentazione:**
- Bootstrap 4: https://getbootstrap.com/docs/4.4/
- jQuery: https://api.jquery.com/
- PHP PDO: https://www.php.net/manual/en/book.pdo.php

**Repository:**
- (Inserire link al repository Git)

**Sviluppatori:**
- (Inserire contatti team)

---

**FINE DOCUMENTAZIONE**

*Ultimo aggiornamento: Ottobre 2025*  
*Versione: 1.0*
