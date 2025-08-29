<?php
// require_once __DIR__ . '/../../config/database.php'; // Assuming database connection is handled elsewhere or passed

class AlertSystem {
    private $pdo;
    private $senderEmail;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->senderEmail = 'no-reply@eu-projectmanager.com';
    }

    /**
     * Sends an email alert.
     * @param string $toEmail Recipient email address.
     * @param string $subject Email subject.
     * @param string $body Email body.
     * @return bool True on success, false on failure.
     */
    public function sendEmailAlert($toEmail, $subject, $body) {
        $headers = 'From: ' . $this->senderEmail . "\r\n" .
                   'Reply-To: ' . $this->senderEmail . "\r\n" .
                   'MIME-Version: 1.0' . "\r\n" .
                   'Content-type: text/html; charset=UTF-8' . "\r\n";
        return mail($toEmail, $subject, $body, $headers);
    }

    /**
     * Triggers an alert based on the risk level and logs it to the database.
     * @param int $projectRiskId The ID of the project_risk entry.
     * @param int $alertLevel The escalation level (1, 2, or 3).
     * @param string $message The alert message.
     * @param int $projectId The project ID.
     * @param int $activityId Optional activity ID.
     * @return bool True on success, false on failure.
     */
    public function triggerRiskAlert($projectRiskId, $alertLevel, $message, $projectId, $activityId = null) {
        try {
            // Log the alert in the database (risk_alerts table)
            $stmt = $this->pdo->prepare("INSERT INTO risk_alerts (project_risk_id, alert_level, message, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$projectRiskId, $alertLevel, $message]);
            $alertId = $this->pdo->lastInsertId();

            // Determine recipients and send alerts based on level
            $projectCoordinatorEmail = $this->getProjectCoordinatorEmail($projectId);
            $projectCoordinatorUserId = $this->getProjectCoordinatorUserId($projectId);
            $superAdminUserIds = $this->getSuperAdminUserIds();

            $alertSubject = "Risk Alert Level {$alertLevel} for Project ID {$projectId}";
            $alertBody = "<h3>Risk Alert!</h3>" .
                         "<p><strong>Project:</strong> " . htmlspecialchars($this->getProjectName($projectId)) . "</p>" .
                         "<p><strong>Risk ID:</strong> {$projectRiskId}</p>" .
                         "<p><strong>Level:</strong> {$alertLevel}</p>" .
                         "<p><strong>Message:</strong> " . htmlspecialchars($message) . "</p>" .
                         "<p>Please review the risk dashboard for more details.</p>";

            $dashboardAlertTitle = "Risk Alert: " . htmlspecialchars($this->getProjectName($projectId));
            $dashboardAlertMessage = "Level {$alertLevel} Risk: " . htmlspecialchars($message);

            switch ($alertLevel) {
                case 1:
                    // Level 1: QM automatic management (no direct email from here, but log it)
                    // This level is handled by QM directly, as per roadmap.
                    // Create dashboard alert for Super Admins
                    foreach ($superAdminUserIds as $saId) {
                        $this->createDashboardAlert($saId, $projectId, $activityId, 'risk', $dashboardAlertTitle, $dashboardAlertMessage);
                    }
                    break;
                case 2:
                    // Level 2: Alert Project Coordinator (Email + Dashboard)
                    if ($projectCoordinatorEmail) {
                        $this->sendEmailAlert($projectCoordinatorEmail, $alertSubject, $alertBody);
                        error_log("Sent Level 2 email alert to: " . $projectCoordinatorEmail);
                    }
                    if ($projectCoordinatorUserId) {
                        $this->createDashboardAlert($projectCoordinatorUserId, $projectId, $activityId, 'risk', $dashboardAlertTitle, $dashboardAlertMessage);
                    }
                    // Also alert Super Admins
                    foreach ($superAdminUserIds as $saId) {
                        $this->createDashboardAlert($saId, $projectId, $activityId, 'risk', $dashboardAlertTitle, $dashboardAlertMessage);
                    }
                    break;
                case 3:
                    // Level 3: Escalation PSC + Emergency Meeting (Email + Dashboard)
                    if ($projectCoordinatorEmail) {
                        $this->sendEmailAlert($projectCoordinatorEmail, $alertSubject, $alertBody);
                        error_log("Sent Level 3 email alert to: " . $projectCoordinatorEmail);
                    }
                    if ($projectCoordinatorUserId) {
                        $this->createDashboardAlert($projectCoordinatorUserId, $projectId, $activityId, 'risk', $dashboardAlertTitle, $dashboardAlertMessage);
                    }
                    // Also alert Super Admins
                    foreach ($superAdminUserIds as $saId) {
                        $this->createDashboardAlert($saId, $projectId, $activityId, 'risk', $dashboardAlertTitle, $dashboardAlertMessage);
                    }
                    break;
            }
            return true;
        } catch (PDOException $e) {
            error_log("Database Error in AlertSystem: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("General Error in AlertSystem: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Creates a new alert entry in the 'alerts' table for dashboard display.
     * @param int $userId The ID of the user to whom the alert is directed.
     * @param int $projectId The project ID related to the alert.
     * @param int|null $activityId Optional activity ID related to the alert.
     * @param string $type The type of alert (e.g., 'deadline', 'report_submitted', 'milestone', 'risk', 'general').
     * @param string $title The title of the alert.
     * @param string $message The detailed message of the alert.
     * @return bool True on success, false on failure.
     */
    public function createDashboardAlert($userId, $projectId, $activityId, $type, $title, $message) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO alerts (user_id, project_id, activity_id, type, title, message, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$userId, $projectId, $activityId, $type, $title, $message]);
            return true;
        } catch (PDOException $e) {
            error_log("Error creating dashboard alert: " . $e->getMessage());
            return false;
        }
    }

    // --- Helper methods to fetch recipient details ---
    private function getProjectCoordinatorEmail($projectId) {
        $stmt = $this->pdo->prepare("SELECT u.email FROM projects p JOIN users u ON p.coordinator_id = u.id WHERE p.id = ?");
        $stmt->execute([$projectId]);
        return $stmt->fetchColumn();
    }

    private function getProjectCoordinatorUserId($projectId) {
        $stmt = $this->pdo->prepare("SELECT u.id FROM projects p JOIN users u ON p.coordinator_id = u.id WHERE p.id = ?");
        $stmt->execute([$projectId]);
        return $stmt->fetchColumn();
    }

    private function getSuperAdminUserIds() {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE role = 'super_admin'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getProjectCoordinatorPhoneNumber($projectId) {
        // Placeholder: In a real system, you'd fetch this from the users table or a contact info table
        // For now, return a dummy number
        error_log("Fetching coordinator phone number for project {$projectId} (placeholder)");
        return '+1234567890'; // Dummy number
    }

    private function getQualityManagerEmails($projectId) {
        // Placeholder: Assuming Quality Managers are users with a specific role or linked to partners
        // For now, return an empty array or a dummy email
        error_log("Fetching Quality Manager emails for project {$projectId} (placeholder)");
        return []; // Return an array of emails
    }

    public function getProjectName($projectId) {
        $stmt = $this->pdo->prepare("SELECT name FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        return $stmt->fetchColumn();
    }
}