<?php
// ===================================================================
//  TEST PROJECT CARD PAGE
// ===================================================================
// Pagina di test per verificare il funzionamento del pulsante View Details
// ===================================================================

session_start();

// Dati di test simulati
$test_project = [
    'id' => 1,
    'name' => 'Bridge - Building Resilience through Interactive Development of Gaming and Education',
    'description' => 'This project aims to develop innovative gaming solutions for educational purposes, focusing on building resilience in young people through interactive learning experiences.',
    'program_type' => 'Erasmus+',
    'status' => 'active',
    'coordinator_name' => 'Guido Ricci',
    'avg_progress' => 65,
    'partner_count' => 8,
    'start_date' => '2024-01-15',
    'end_date' => '2026-12-31',
    'budget' => 450000
];

// Simulazione ruolo utente
$user_role = 'coordinator'; // Cambia questo valore per testare diversi ruoli

// Funzioni helper simulate
function getProgressColor($progress) {
    if ($progress >= 75) return 'success';
    if ($progress >= 50) return 'warning';
    if ($progress >= 25) return 'info';
    return 'danger';
}

function getStatusBadge($status) {
    $badges = [
        'planning' => '<span class="badge badge-secondary">Planning</span>',
        'active' => '<span class="badge badge-success">Active</span>',
        'suspended' => '<span class="badge badge-warning">Suspended</span>',
        'completed' => '<span class="badge badge-primary">Completed</span>'
    ];
    return $badges[$status] ?? '<span class="badge badge-secondary">Unknown</span>';
}

function formatDate($date) {
    return date('M j, Y', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Test Project Card - EU Project Manager</title>
    <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no' name='viewport' />
    
    <!-- Bootstrap CSS -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Paper Dashboard CSS -->
    <link href="../assets/css/paper-dashboard.css" rel="stylesheet" />
    <!-- Projects Page CSS -->
    <link href="../assets/css/pages/projects.css" rel="stylesheet" />
    <!-- Custom CSS -->
    <link href="../assets/css/custom.css" rel="stylesheet" />
    
    <style>
        body {
            background: #f4f3ef;
            padding: 20px;
        }
        .test-container {
            max-width: 400px;
            margin: 0 auto;
        }
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .debug-info h5 {
            color: #495057;
            margin-bottom: 10px;
        }
        .debug-info code {
            background: #e9ecef;
            padding: 2px 4px;
            border-radius: 3px;
        }
    </style>
</head>

<body>
    <div class="test-container">
        <h2 class="text-center mb-4">🧪 Test Project Card</h2>
        
        <!-- Debug Information -->
        <div class="debug-info">
            <h5>📋 Debug Info</h5>
            <p><strong>Project ID:</strong> <code><?= $test_project['id'] ?></code></p>
            <p><strong>User Role:</strong> <code><?= $user_role ?></code></p>
            <p><strong>View Details URL:</strong> <code>project-detail.php?id=<?= $test_project['id'] ?></code></p>
            <p><strong>Expected Result:</strong> Il pulsante dovrebbe portare alla pagina di dettaglio del progetto</p>
        </div>

        <!-- Project Card (Copia esatta dal file projects.php) -->
        <div class="project-card card">
            <!-- Project Header -->
            <div class="project-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h5 class="mb-1">
                            <i class="nc-icon nc-badge text-info"></i>
                            <?= htmlspecialchars($test_project['name']) ?>
                        </h5>
                        
                        <div class="mb-2">
                            <span class="program-badge bg-info text-white">
                                <?= $test_project['program_type'] ?>
                            </span>
                            <?= getStatusBadge($test_project['status']) ?>
                        </div>
                        
                        <small class="text-muted">
                            <i class="nc-icon nc-single-02"></i>
                            Coordinator: <strong><?= htmlspecialchars($test_project['coordinator_name'] ?? 'Not assigned') ?></strong>
                        </small>
                    </div>
                    
                    <div class="text-center">
                        <div class="progress-circle bg-<?= getProgressColor($test_project['avg_progress'] ?? 0) ?>">
                            <?= number_format($test_project['avg_progress'] ?? 0, 0) ?>%
                        </div>
                        <small class="text-muted">Progress</small>
                    </div>
                </div>
            </div>
            
            <!-- Project Body -->
            <div class="project-body">
                <p class="text-muted mb-3" style="font-size: 14px; line-height: 1.4;">
                    <?= htmlspecialchars(substr($test_project['description'], 0, 120)) ?>
                    <?= strlen($test_project['description']) > 120 ? '...' : '' ?>
                </p>
                
                <div class="row text-center">
                    <div class="col-4">
                        <div class="partner-count">
                            <i class="nc-icon nc-world-2"></i>
                            <?= $test_project['partner_count'] ?> Partners
                        </div>
                    </div>
                    <div class="col-8">
                        <small class="text-muted">
                            <i class="nc-icon nc-calendar-60"></i>
                            <?= formatDate($test_project['start_date']) ?> - <?= formatDate($test_project['end_date']) ?>
                        </small>
                    </div>
                </div>
                
                <?php if ($test_project['budget']): ?>
                <div class="text-center mt-2">
                    <small class="budget-info">
                        <i class="nc-icon nc-money-coins"></i>
                        Budget: €<?= number_format($test_project['budget'], 0, ',', '.') ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Project Footer - QUESTA È LA SEZIONE CRITICA -->
            <div class="project-footer">
                <div class="d-flex justify-content-between">
                    <!-- PULSANTE VIEW DETAILS -->
                    <a href="project-detail.php?id=<?= $test_project['id'] ?>" 
                       class="btn btn-primary btn-sm"
                       onclick="alert('Cliccato View Details! URL: project-detail.php?id=<?= $test_project['id'] ?>'); return false;">
                        <i class="nc-icon nc-zoom-split"></i>
                        View Details
                    </a>
                    
                    <div>
                        <a href="activities.php?project=<?= $test_project['id'] ?>" 
                           class="btn btn-outline-info btn-sm"
                           onclick="alert('Cliccato Activities! URL: activities.php?project=<?= $test_project['id'] ?>'); return false;">
                            <i class="nc-icon nc-paper"></i>
                            Activities
                        </a>
                        
                        <?php if ($user_role === 'super_admin' || $user_role === 'coordinator'): ?>
                        <a href="reports.php?project=<?= $test_project['id'] ?>" 
                           class="btn btn-outline-success btn-sm"
                           onclick="alert('Cliccato Reports! URL: reports.php?project=<?= $test_project['id'] ?>'); return false;">
                            <i class="nc-icon nc-chart-bar-32"></i>
                            Reports
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Istruzioni per il test -->
        <div class="debug-info mt-4">
            <h5>🔍 Come testare</h5>
            <ol>
                <li>Clicca sul pulsante <strong>"View Details"</strong></li>
                <li>Dovresti vedere un alert con l'URL</li>
                <li>Se vedi l'alert, il pulsante funziona</li>
                <li>Se non vedi il pulsante, c'è un problema CSS</li>
                <li>Controlla la console del browser (F12) per errori</li>
            </ol>
            
            <p><strong>Note:</strong></p>
            <ul>
                <li>Ho aggiunto <code>onclick="alert(...)"</code> per testare</li>
                <li>Il pulsante "Reports" appare solo per coordinator/super_admin</li>
                <li>I CSS dovrebbero essere gli stessi del progetto originale</li>
            </ul>
        </div>
    </div>

    <!-- JavaScript per debug aggiuntivo -->
    <script>
        console.log('🧪 Test Project Card caricato');
        console.log('Project ID:', <?= $test_project['id'] ?>);
        console.log('User Role:', '<?= $user_role ?>');
        
        // Verifica che il pulsante View Details esista
        document.addEventListener('DOMContentLoaded', function() {
            const viewDetailsBtn = document.querySelector('a[href*="project-detail.php"]');
            if (viewDetailsBtn) {
                console.log('✅ Pulsante View Details trovato:', viewDetailsBtn);
                console.log('URL del pulsante:', viewDetailsBtn.href);
            } else {
                console.error('❌ Pulsante View Details NON trovato!');
            }
            
            // Lista tutti i pulsanti nel footer
            const footerButtons = document.querySelectorAll('.project-footer .btn');
            console.log('Pulsanti nel footer:', footerButtons.length);
            footerButtons.forEach((btn, index) => {
                console.log(`Pulsante ${index + 1}:`, btn.textContent.trim(), btn.href);
            });
        });
    </script>
</body>
</html>