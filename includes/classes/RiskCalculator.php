<?php
require_once __DIR__ . '/../../config/database.php';

/**
 * Classe per calcolare e aggiornare i punteggi di rischio del progetto.
 * Legge i dati da varie fonti (report, milestone, etc.) e utilizza
 * la stored procedure `UpdateRiskScore` per aggiornare il database.
 */
class RiskCalculator {
    private $pdo;

    /**
     * Il costruttore stabilisce la connessione al database.
     * Può accettare una connessione PDO esistente o crearne una nuova.
     *
     * @param PDO|null $pdo Una connessione PDO esistente.
     */
    public function __construct($pdo = null) {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            try {
                $database = new Database();
                $this->pdo = $database->connect();
            } catch (PDOException $e) {
                // In un'applicazione reale, questo dovrebbe essere gestito più elegantemente
                // (es. loggare l'errore e mostrare un messaggio generico).
                die("Errore di connessione al database: " . $e->getMessage());
            }
        }
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Elabora un report di attività appena creato per ricalcolare i rischi del progetto associato.
     *
     * @param int $reportId L'ID del report di attività.
     */
    public function processReport($reportId) {
        // 1. Trova il project_id dal report
        $stmt = $this->pdo->prepare("SELECT project_id FROM activity_reports WHERE id = :reportId");
        $stmt->execute(['reportId' => $reportId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && !empty($result['project_id'])) {
            $projectId = $result['project_id'];
            // 2. Esegui il ricalcolo di tutti i rischi per quel progetto
            $this->calculateAllRisksForProject($projectId);
        } else {
            // Opzionale: logga un errore se il report o il progetto non vengono trovati
            // Per esempio: error_log("RiskCalculator: Impossibile trovare il progetto per il report ID: " . $reportId);
        }
    }

    /**
     * Metodo principale per eseguire il calcolo di tutti i rischi per un dato progetto.
     *
     * @param int $projectId L'ID del progetto da analizzare.
     */
    public function calculateAllRisksForProject($projectId) {
        // Ottiene l'ID di tutti i rischi predefiniti
        $riskIds = $this->getRiskIds();

        // Cicla su ogni rischio ed esegue il calcolo specifico
        foreach ($riskIds as $risk) {
            $methodName = 'calculateR' . str_pad($risk['id'], 2, '0', STR_PAD_LEFT);
            if (method_exists(get_class($this), $methodName)) {
                $this->$methodName($projectId, $risk['id']);
            }
        }
    }

    /**
     * R01 - Ritardi Coordinamento WP2-4
     * Logica: Basato su milestone e attività in ritardo.
     *
     * @param int $projectId L'ID del progetto.
     * @param int $riskId L'ID del rischio (1).
     */
    private function calculateR01($projectId, $riskId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(m.id) as overdue_milestones_count
            FROM milestones m
            WHERE m.project_id = :projectId AND m.due_date < CURDATE() AND m.status != 'completed'
        ");
        $stmt->execute(['projectId' => $projectId]);
        $overdueMilestones = $stmt->fetch(PDO::FETCH_ASSOC)['overdue_milestones_count'];

        $stmt = $this->pdo->prepare("
            SELECT COUNT(a.id) as overdue_activities_count
            FROM activities a
            WHERE a.project_id = :projectId AND a.end_date < CURDATE() AND a.status != 'completed'
        ");
        $stmt->execute(['projectId' => $projectId]);
        $overdueActivities = $stmt->fetch(PDO::FETCH_ASSOC)['overdue_activities_count'];

        $probability = 1; // Default Low
        $impact = 3; // Default Medium

        if ($overdueMilestones > 0 || $overdueActivities > 0) {
            $probability = 3; // Medium
            if ($overdueMilestones > 1 || $overdueActivities > 2) {
                $probability = 5; // High
            }
            $impact = 4; // High impact for delays
        }

        $this->updateRiskScore($projectId, $riskId, $probability, $impact, "Recalculated based on overdue milestones and activities.");
    }

    /**
     * R02 - Calcola il rischio legato alla difficoltà di reclutamento del target group.
     * Utilizza il campo `risk_recruitment_difficulty` dai report di attività.
     *
     * @param int $projectId L'ID del progetto.
     * @param int $riskId L'ID del rischio (2).
     */
    private function calculateR02($projectId, $riskId) {
        $stmt = $this->pdo->prepare("
            SELECT AVG(risk_recruitment_difficulty) as avg_difficulty
            FROM activity_reports
            WHERE project_id = :projectId AND risk_recruitment_difficulty IS NOT NULL
        ");
        $stmt->execute(['projectId' => $projectId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $avgDifficulty = $result['avg_difficulty'] ?? 1; // Se non ci sono dati, il rischio è basso

        // Mappatura della difficoltà (1-5) su probabilità (1-5)
        // Difficoltà alta (5) -> Probabilità alta (5)
        $probability = $avgDifficulty;
        $impact = 4; // Impatto critico per il fallimento del reclutamento

        $this->updateRiskScore($projectId, $riskId, $probability, $impact, "Recalculated based on recruitment difficulty.");
    }

    /**
     * R03 - Problemi Tecnici Piattaforma
     * Logica: Simulato, in futuro da integrare con monitoraggio esterno.
     *
     * @param int $projectId L'ID del progetto.
     * @param int $riskId L'ID del rischio (3).
     */
    private function calculateR03($projectId, $riskId) {
        // Placeholder: In un sistema reale, qui si integrerebbero dati da monitoraggio uptime, reclami, ecc.
        // Per ora, assumiamo un rischio medio-basso.
        $probability = 2; // Low-Medium
        $impact = 3; // Medium

        // Esempio di logica più complessa (se avessimo un campo `platform_issues_reported`)
        // $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM platform_issues WHERE project_id = :projectId AND resolved = 0");
        // $stmt->execute(['projectId' => $projectId]);
        // $openIssues = $stmt->fetchColumn();
        // if ($openIssues > 0) { $probability = 4; $impact = 4; }

        $this->updateRiskScore($projectId, $riskId, $probability, $impact, "Recalculated based on simulated technical issues.");
    }

    /**
     * R04 - Calcola il rischio legato a conflitti e problemi di comunicazione.
     * Utilizza il campo `risk_collaboration_rating` dai report di attività.
     *
     * @param int $projectId L'ID del progetto.
     * @param int $riskId L'ID del rischio (4).
     */
    private function calculateR04($projectId, $riskId) {
        $stmt = $this->pdo->prepare("
            SELECT AVG(risk_collaboration_rating) as avg_rating
            FROM activity_reports
            WHERE project_id = :projectId AND risk_collaboration_rating IS NOT NULL
        ");
        $stmt->execute(['projectId' => $projectId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $avgRating = $result['avg_rating'] ?? 5; // Se non ci sono dati, il rischio è basso

        // Mappatura del rating (1-5) su probabilità (1-5)
        // Rating basso (1) -> Probabilità alta (5)
        $probability = 6 - $avgRating;
        $impact = 3; // Impatto medio-alto per i conflitti

        $this->updateRiskScore($projectId, $riskId, $probability, $impact, "Recalculated based on collaboration ratings.");
    }

    /**
     * R05 - Calcola il rischio di Budget Overrun.
     * Utilizza il campo `risk_budget_status` dai report di attività.
     *
     * @param int $projectId L'ID del progetto.
     * @param int $riskId L'ID del rischio (5).
     */
    private function calculateR05($projectId, $riskId) {
        $stmt = $this->pdo->prepare("
            SELECT risk_budget_status
            FROM activity_reports
            WHERE project_id = :projectId AND risk_budget_status IS NOT NULL
            ORDER BY report_date DESC
            LIMIT 1
        ");
        $stmt->execute(['projectId' => $projectId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $status = $result['risk_budget_status'] ?? 'Green';

        $probability = 1;
        if ($status === 'Red') {
            $probability = 5;
        } elseif ($status === 'Yellow') {
            $probability = 3;
        }

        $impact = 5; // L'impatto di un overrun è sempre critico

        $this->updateRiskScore($projectId, $riskId, $probability, $impact, "Recalulated based on budget status reports.");
    }

    /**
     * R06 - Qualità Output WP3-4
     * Logica: Basato sul campo `risk_quality_check` dai report di attività.
     *
     * @param int $projectId L'ID del progetto.
     * @param int $riskId L'ID del rischio (6).
     */
    private function calculateR06($projectId, $riskId) {
        $stmt = $this->pdo->prepare("
            SELECT AVG(risk_quality_check) as avg_quality
            FROM activity_reports
            WHERE project_id = :projectId AND risk_quality_check IS NOT NULL
        ");
        $stmt->execute(['projectId' => $projectId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $avgQuality = $result['avg_quality'] ?? 5; // Se non ci sono dati, la qualità è alta

        // Mappatura del rating (1-5) su probabilità (1-5)
        // Rating basso (1) -> Probabilità alta (5)
        $probability = 6 - $avgQuality;
        $impact = 4; // Impatto alto per la qualità degli output

        $this->updateRiskScore($projectId, $riskId, $probability, $impact, "Recalculated based on output quality checks.");
    }

    /**
     * R07 - Disseminazione Insufficiente
     * Logica: Simulato, in futuro da integrare con Google Analytics/Social Media API.
     *
     * @param int $projectId L'ID del progetto.
     * @param int $riskId L'ID del rischio (7).
     */
    private function calculateR07($projectId, $riskId) {
        // Placeholder: In un sistema reale, qui si integrerebbero dati da Google Analytics, Social Media API, ecc.
        // Per ora, assumiamo un rischio medio.
        $probability = 3; // Medium
        $impact = 3; // Medium

        $this->updateRiskScore($projectId, $riskId, $probability, $impact, "Recalculated based on simulated dissemination metrics.");
    }

    /**
     * R08 - Emergenze Sanitarie
     * Logica: Simulato, in futuro da integrare con dati esterni.
     *
     * @param int $projectId L'ID del progetto.
     * @param int $riskId L'ID del rischio (8).
     */
    private function calculateR08($projectId, $riskId) {
        // Placeholder: In un sistema reale, qui si integrerebbero dati da fonti esterne su restrizioni sanitarie, ecc.
        // Per ora, assumiamo un rischio basso.
        $probability = 1; // Low
        $impact = 4; // High (se si verifica, l'impatto è alto)

        $this->updateRiskScore($projectId, $riskId, $probability, $impact, "Recalculated based on simulated health emergencies.");
    }

    /**
     * R09 - Turnover Staff
     * Logica: Simulato, in futuro da integrare con dati HR o report specifici.
     *
     * @param int $projectId L'ID del progetto.
     * @param int $riskId L'ID del rischio (9).
     */
    private function calculateR09($projectId, $riskId) {
        // Placeholder: In un sistema reale, qui si integrerebbero dati da HR o report specifici sul turnover.
        // Per ora, assumiamo un rischio medio-basso.
        $probability = 2; // Low-Medium
        $impact = 3; // Medium

        $this->updateRiskScore($projectId, $riskId, $probability, $impact, "Recalculated based on simulated staff turnover.");
    }

    /**
     * R10 - Non Conformità
     * Logica: Simulato, in futuro da integrare con monitoraggio normativo.
     *
     * @param int $projectId L'ID del progetto.
     * @param int $riskId L'ID del rischio (10).
     */
    private function calculateR10($projectId, $riskId) {
        // Placeholder: In un sistema reale, qui si integrerebbero dati da monitoraggio normativo o audit.
        // Per ora, assumiamo un rischio medio.
        $probability = 3; // Medium
        $impact = 3; // Medium

        $this->updateRiskScore($projectId, $riskId, $probability, $impact, "Recalculated based on simulated non-compliance.");
    }

    /**
     * Esegue la Stored Procedure per aggiornare lo score di un rischio.
     *
     * @param int $projectId L'ID del progetto.
     * @param int $riskId L'ID del rischio.
     * @param int $probability La nuova probabilità (1-5).
     * @param int $impact Il nuovo impatto (1-5).
     * @param string $reason La motivazione dell'aggiornamento.
     */
    private function updateRiskScore($projectId, $riskId, $probability, $impact, $reason) {
        // Prima, otteniamo l'ID corretto dalla tabella `project_risks`
        $stmt = $this->pdo->prepare("SELECT id FROM project_risks WHERE project_id = :projectId AND risk_id = :riskId");
        $stmt->execute(['projectId' => $projectId, 'riskId' => $riskId]);
        $projectRisk = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($projectRisk) {
            $projectRiskId = $projectRisk['id'];
            
            // Ora chiamiamo la stored procedure
            $sp_stmt = $this->pdo->prepare("CALL UpdateRiskScore(:project_risk_id, :probability, :impact, :reason)");
            $sp_stmt->execute([
                'project_risk_id' => $projectRiskId,
                'probability' => $probability,
                'impact' => $impact,
                'reason' => $reason
            ]);
        }
    }

    /**
     * Recupera gli ID di tutti i rischi standard dalla tabella `risks`.
     *
     * @return array
     */
    private function getRiskIds() {
        $stmt = $this->pdo->query("SELECT id, risk_code FROM risks ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>