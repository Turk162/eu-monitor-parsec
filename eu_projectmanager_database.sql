-- phpMyAdmin SQL Dump
-- version 5.2.2-1.fc42
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Creato il: Ago 27, 2025 alle 17:54
-- Versione del server: 10.11.11-MariaDB
-- Versione PHP: 8.4.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `eu_projectmanager`
--

DELIMITER $$
--
-- Procedure
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateRiskScore` (IN `p_project_risk_id` INT, IN `p_new_probability` INT, IN `p_new_impact` INT, IN `p_change_reason` VARCHAR(255))   BEGIN
    -- Dichiarazione variabili
    DECLARE v_old_score INT;
    DECLARE v_new_score INT;
    DECLARE v_new_status ENUM('Low', 'Medium', 'High', 'Critical');

    -- Gestione degli errori con rollback
    DECLARE exit handler for sqlexception
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    -- Inizio della transazione
    START TRANSACTION;

    -- 1. Ottieni il vecchio score e blocca la riga per l\'aggiornamento
    SELECT `current_score` INTO v_old_score
    FROM `project_risks`
    WHERE `id` = p_project_risk_id
    FOR UPDATE;

    -- 2. Calcola il nuovo score
    SET v_new_score = p_new_probability * p_new_impact;

    -- 3. Determina il nuovo status in base allo score
    CASE
        WHEN v_new_score > 12 THEN SET v_new_status = 'Critical';
        WHEN v_new_score > 6 THEN SET v_new_status = 'High';
        WHEN v_new_score > 2 THEN SET v_new_status = 'Medium';
        ELSE SET v_new_status = 'Low';
    END CASE;

    -- 4. Aggiorna la tabella `project_risks` con i nuovi valori
    UPDATE `project_risks`
    SET
        `current_probability` = p_new_probability,
        `current_impact` = p_new_impact,
        `current_score` = v_new_score,
        `status` = v_new_status
    WHERE `id` = p_project_risk_id;

    -- 5. Inserisci un record nella cronologia se lo score è cambiato
    IF v_old_score <> v_new_score THEN
        INSERT INTO `risk_history` (`project_risk_id`, `old_score`, `new_score`, `change_reason`)
        VALUES (p_project_risk_id, v_old_score, v_new_score, p_change_reason);
    END IF;

    -- Fine della transazione
    COMMIT;

END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Struttura della tabella `activities`
--

CREATE TABLE `activities` (
  `id` int(11) NOT NULL,
  `work_package_id` int(11) NOT NULL,
  `activity_number` varchar(20) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `responsible_partner_id` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('not_started','in_progress','completed','overdue') DEFAULT 'not_started',
  `progress` decimal(5,2) DEFAULT 0.00,
  `budget` decimal(8,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `project_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `activities`
--

INSERT INTO `activities` (`id`, `work_package_id`, `activity_number`, `name`, `description`, `responsible_partner_id`, `start_date`, `end_date`, `status`, `progress`, `budget`, `created_at`, `updated_at`, `project_id`) VALUES
(35, 12, '1.1', 'First Setup and Governance', 'Online kick-off meeting organization\r\nDistribution partnership agreements to all partners\r\nDefining roles, responsibilities and operating procedures\r\nBuild integrated communication system (WhatsApp, Email, Google Drive)\r\nSetup e-learning platform for material exchanges', 1, '2025-08-01', '2025-10-01', 'in_progress', 25.00, 0.00, '2025-08-07 06:37:41', '2025-08-27 05:49:12', 28),
(37, 12, '1.2', 'Online Kick off meeting', 'KOM online', 1, '2025-09-10', '2025-09-15', 'not_started', 0.00, 0.00, '2025-08-27 15:30:15', '2025-08-27 15:30:15', 28),
(41, 14, '2.1', 'Desk Research: Comparative analysis of reception and integration systems for migrant minors and professionals involved', 'This activity maps reception and integration models across partner countries, focusing on youth work, intercultural mediation, and education pathways. Key activities include: comparing national policies and identifying strengths, gaps, and best practices; analyzing the role of youth workers in supporting migrant youth; collecting case studies from at least five countries to inform training strategies. The activity will produce a comparative map that guides WP3 training content and ensures alignment with real policy and professional contexts. Expected outcomes: comprehensive overview of policies, professional roles, and best practices in youth work across five countries; identification of common challenges and gaps that hinder migrant youth inclusion; knowledge base for WP3 training development.', 8, '2025-09-01', '2025-10-31', 'not_started', 0.00, 12244.00, '2025-08-27 17:04:15', '2025-08-27 17:04:15', 28),
(42, 14, '2.2', 'Field Research: Training needs assessment', 'This phase ensures WP3 training directly addresses youth workers needs and prepares for WP4 peer education. Key activities include: surveying/interviewing 50+ youth workers and educators to assess skill gaps; organizing 5 transnational focus groups engaging youth workers and young migrants; identifying key competencies including empathetic listening, peer facilitation, and intercultural mediation. The activity will produce a Training Needs Analysis Report defining WP3 training priorities and methodologies. Expected participants: 50 social workers involved in education of foreign minors across partner countries; 20 operators and mediators; 60 migrant minors including at least 20 unaccompanied minors (UAMs). This activity directly informs WP3 by defining the competency framework that shapes training content.', 8, '2025-11-01', '2026-01-31', 'not_started', 0.00, 18366.00, '2025-08-27 17:04:15', '2025-08-27 17:04:15', 28),
(43, 14, '2.3', 'Development of Training Modules Framework', 'WP2 findings will shape structured, practice-oriented training for WP3, ensuring youth workers are equipped to facilitate WP4 peer education. Key activities include: organizing co-creation workshops where youth workers and young migrants refine training content (minimum 3 participatory workshops); defining learning objectives, participatory methods, and interactive tools; ensuring alignment with WP4 to enable youth workers to guide young migrants in co-developing and implementing Mixing Culture. The activity will produce a structured training module framework integrating empathetic listening, peer education facilitation, and intercultural mediation as core learning components. Expected outcomes: structured training program with clear learning objectives and interactive methodologies; development of participatory learning tools including case studies, role-playing, and scenario-based learning; co-created content ensuring smooth transition to WP4 peer education strategies.', 8, '2026-02-01', '2026-03-31', 'not_started', 0.00, 12244.00, '2025-08-27 17:04:15', '2025-08-27 17:04:15', 28);

-- --------------------------------------------------------

--
-- Struttura della tabella `activity_reports`
--

CREATE TABLE `activity_reports` (
  `id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `partner_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `report_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `participants_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `coordinator_feedback` text DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `risk_collaboration_rating` int(11) DEFAULT NULL,
  `risk_recruitment_difficulty` int(11) DEFAULT NULL,
  `risk_quality_check` int(11) DEFAULT NULL,
  `risk_budget_status` varchar(10) DEFAULT NULL,
  `test_col` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `activity_reports`
--

INSERT INTO `activity_reports` (`id`, `activity_id`, `partner_id`, `user_id`, `report_date`, `description`, `participants_data`, `created_at`, `updated_at`, `coordinator_feedback`, `reviewed_at`, `reviewed_by`, `project_id`, `risk_collaboration_rating`, `risk_recruitment_difficulty`, `risk_quality_check`, `risk_budget_status`, `test_col`) VALUES
(14, 35, 1, 7, '2025-08-27', 'pROVA REPORT SEMPLICE', '\"20 GIOVANI\"', '2025-08-27 10:25:01', '2025-08-27 10:25:01', NULL, NULL, NULL, 28, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Struttura della tabella `alerts`
--

CREATE TABLE `alerts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `activity_id` int(11) DEFAULT NULL,
  `type` enum('deadline','report_submitted','milestone','general') NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `milestones`
--

CREATE TABLE `milestones` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `work_package_id` int(11) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date NOT NULL,
  `status` enum('pending','completed','overdue') DEFAULT 'pending',
  `completed_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `participant_categories`
--

CREATE TABLE `participant_categories` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `partners`
--

CREATE TABLE `partners` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `organization_type` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `partners`
--

INSERT INTO `partners` (`id`, `name`, `organization_type`, `country`, `created_at`) VALUES
(1, 'Cooperativa Parsec', 'NGO', 'Italy', '2025-07-31 10:53:10'),
(2, 'Asociación Creativa', 'NGO', 'Spain', '2025-07-31 10:53:10'),
(3, 'VELA Foundation', 'NGO', 'Greece', '2025-07-31 10:53:10'),
(8, 'Gewerkstatt', 'NGO', 'Germany', '2025-08-04 16:10:24'),
(9, 'Changes&Chances', 'NGO', 'Netherlands', '2025-08-04 16:11:11');

-- --------------------------------------------------------

--
-- Struttura della tabella `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `program_type` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `coordinator_id` int(11) DEFAULT NULL,
  `status` enum('planning','active','suspended','completed') DEFAULT 'planning',
  `budget` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `projects`
--

INSERT INTO `projects` (`id`, `name`, `description`, `program_type`, `start_date`, `end_date`, `coordinator_id`, `status`, `budget`, `created_at`, `updated_at`) VALUES
(28, 'Bridge - Building Resilience through Interactive Development of Gaming and Education', 'Project Bridge prevents youth radicalisation by fostering social inclusion of young migrants and unaccompanied minors. It trains youth workers in empathic listening and intercultural facilitation, while empowering migrants as peer educators. Co-created tools, including the game *Mixing Culture*, promote dialogue, participation, and sustainable integration. Key work packages cover research, training, peer education, and dissemination. Expected outcomes include comparative analysis, training modules for 200 youth workers, a peer education framework, and the tested *Mixing Culture* game.', 'erasmus_plus', '2025-09-01', '2027-08-31', 7, 'active', 250000.00, '2025-08-04 17:47:02', '2025-08-27 14:56:13');

-- --------------------------------------------------------

--
-- Struttura della tabella `project_partners`
--

CREATE TABLE `project_partners` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `partner_id` int(11) NOT NULL,
  `role` enum('coordinator','partner') DEFAULT 'partner',
  `organization` varchar(100) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `budget_allocated` decimal(10,2) DEFAULT NULL,
  `joined_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `project_partners`
--

INSERT INTO `project_partners` (`id`, `project_id`, `partner_id`, `role`, `organization`, `country`, `budget_allocated`, `joined_at`) VALUES
(52, 28, 1, 'coordinator', NULL, NULL, 60056.00, '2025-08-04 17:47:02'),
(53, 28, 2, 'partner', NULL, NULL, 43894.00, '2025-08-04 17:48:10'),
(54, 28, 8, 'partner', NULL, NULL, 51928.00, '2025-08-04 17:48:10'),
(55, 28, 9, 'partner', NULL, NULL, 50982.00, '2025-08-04 17:48:10'),
(56, 28, 3, 'partner', NULL, NULL, 43140.00, '2025-08-04 17:48:10');

-- --------------------------------------------------------

--
-- Struttura della tabella `project_partners_old`
--

CREATE TABLE `project_partners_old` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `partner_id` int(11) NOT NULL,
  `qqq` int(11) NOT NULL,
  `role` enum('coordinator','partner') DEFAULT 'partner',
  `organization` varchar(100) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `budget_allocated` decimal(10,2) DEFAULT NULL,
  `joined_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `project_risks`
--

CREATE TABLE `project_risks` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL COMMENT 'FK alla tabella dei progetti',
  `risk_id` int(11) NOT NULL COMMENT 'FK alla tabella anagrafica rischi',
  `current_probability` int(11) NOT NULL DEFAULT 1 COMMENT 'Probabilità attuale (1-5)',
  `current_impact` int(11) NOT NULL DEFAULT 1 COMMENT 'Impatto attuale (1-5)',
  `current_score` int(11) NOT NULL DEFAULT 1 COMMENT 'Score calcolato (probabilità * impatto)',
  `status` enum('Low','Medium','High','Critical') NOT NULL DEFAULT 'Low' COMMENT 'Stato del rischio basato sullo score',
  `last_updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `project_risks`
--

INSERT INTO `project_risks` (`id`, `project_id`, `risk_id`, `current_probability`, `current_impact`, `current_score`, `status`, `last_updated`) VALUES
(1, 28, 1, 3, 3, 9, 'High', '2025-08-27 13:11:18'),
(2, 28, 2, 1, 1, 1, 'Low', '2025-08-27 13:11:18'),
(3, 28, 3, 1, 1, 1, 'Low', '2025-08-27 13:11:18'),
(4, 28, 4, 1, 1, 1, 'Low', '2025-08-27 13:11:18'),
(5, 28, 5, 1, 1, 1, 'Low', '2025-08-27 13:11:18'),
(6, 28, 6, 1, 1, 1, 'Low', '2025-08-27 13:11:18'),
(7, 28, 7, 1, 1, 1, 'Low', '2025-08-27 13:11:18'),
(8, 28, 8, 1, 1, 1, 'Low', '2025-08-27 13:11:18'),
(9, 28, 9, 2, 2, 4, 'Medium', '2025-08-27 13:28:33'),
(10, 28, 10, 1, 1, 1, 'Low', '2025-08-27 13:11:18');

-- --------------------------------------------------------

--
-- Struttura della tabella `risks`
--

CREATE TABLE `risks` (
  `id` int(11) NOT NULL,
  `risk_code` varchar(10) NOT NULL COMMENT 'Codice univoco del rischio (es. R01, R02)',
  `category` varchar(100) NOT NULL COMMENT 'Categoria del rischio (es. Coordinamento, Budget)',
  `description` text NOT NULL COMMENT 'Descrizione dettagliata del rischio',
  `critical_threshold` int(11) NOT NULL COMMENT 'Punteggio oltre il quale scatta un alert critico specifico'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `risks`
--

INSERT INTO `risks` (`id`, `risk_code`, `category`, `description`, `critical_threshold`) VALUES
(1, 'BR01', 'Coordinamento', 'Ritardi nel coordinamento e nella gestione dei Work Package 2-4', 12),
(2, 'BR02', 'Target Group', 'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)', 12),
(3, 'BR03', 'Tecnico', 'Problemi tecnici sulla piattaforma di progetto (downtime, bug, performance)', 6),
(4, 'BR04', 'Team', 'Conflitti interculturali o problemi di comunicazione all\'interno del partenariato', 8),
(5, 'BR05', 'Budget', 'Superamento del budget previsto (overrun) o gestione finanziaria inefficiente', 12),
(6, 'BR06', 'Qualità', 'Qualità degli output dei Work Package 3-4 non conforme agli standard attesi', 12),
(7, 'BR07', 'Disseminazione', 'Scarsa efficacia delle attività di disseminazione e comunicazione dei risultati', 9),
(8, 'BR08', 'Esterno', 'Impatto di emergenze sanitarie o altre crisi esterne sul progetto', 8),
(9, 'BR09', 'Team', 'Turnover dello staff chiave all\'interno dei partner di progetto', 9),
(10, 'BR10', 'Normativo', 'Mancata conformità con le normative (es. GDPR) o con le regole del programma', 10);

-- --------------------------------------------------------

--
-- Struttura della tabella `risk_alerts`
--

CREATE TABLE `risk_alerts` (
  `id` int(11) NOT NULL,
  `project_risk_id` int(11) NOT NULL COMMENT 'FK alla tabella project_risks',
  `alert_level` int(11) NOT NULL COMMENT 'Livello di escalation (1, 2, 3)',
  `message` text NOT NULL COMMENT 'Messaggio di alert generato',
  `is_acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  `acknowledged_by_user_id` int(11) DEFAULT NULL COMMENT 'FK alla tabella utenti che ha gestito l''alert',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `risk_history`
--

CREATE TABLE `risk_history` (
  `id` int(11) NOT NULL,
  `project_risk_id` int(11) NOT NULL COMMENT 'FK alla tabella project_risks',
  `old_score` int(11) DEFAULT NULL,
  `new_score` int(11) NOT NULL,
  `change_reason` varchar(255) DEFAULT NULL COMMENT 'Motivazione della variazione (es. Report mensile partner X)',
  `timestamp` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `risk_history`
--

INSERT INTO `risk_history` (`id`, `project_risk_id`, `old_score`, `new_score`, `change_reason`, `timestamp`) VALUES
(1, 1, 1, 9, 'Test: Aumento rischio coordinamento da report mensile', '2025-08-27 08:57:49'),
(2, 9, 1, 4, 'Manual update from admin panel.', '2025-08-27 13:28:33');

-- --------------------------------------------------------

--
-- Struttura della tabella `uploaded_files`
--

CREATE TABLE `uploaded_files` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('super_admin','coordinator','partner','admin') NOT NULL,
  `partner_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `role`, `partner_id`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'admin', 'admin@cooperativaparsec.it', '$2y$10$gy2bXvzryp/zhVf9dX0Wqe9eBOyXOyJ4M7jcoJPtDE52LezychRAe', 'Super Admin Parsec', 'super_admin', NULL, '2025-07-30 05:18:44', '2025-07-30 05:18:44', 1),
(2, 'coordinator', 'coord@cooperativaparsec.it', '$2y$10$Thi4MIIrn5ufxm4TKUMU9.2OMD4yOVZnOD6VSCZd.b86k9l8g181u', 'Mario Rossi', 'coordinator', 1, '2025-07-30 05:18:44', '2025-07-31 10:57:03', 1),
(3, 'partner_spain', 'partner@spain.eu', '$2y$10$LZ7Pv9cdow989LAihIEW5uMwen4a3V1Lvkn7iZYDjsxEqXjShM3Oi', 'Carlos Garcia', 'partner', 2, '2025-07-30 05:18:44', '2025-07-31 10:57:03', 1),
(4, 'partner_greece', 'partner@greece.eu', '$2y$10$LZ7Pv9cdow989LAihIEW5uMwen4a3V1Lvkn7iZYDjsxEqXjShM3Oi', 'Maria Papadopoulos', 'partner', 3, '2025-07-30 05:18:44', '2025-07-31 10:57:03', 1),
(5, 'guido@sweden.it', 'guido@sweden.it', '$2y$12$FjK9siq0HNr11TqPI5z9juCajxSSt34idvrmUvMpQKk/JcpBZ8ZtK', 'Guido Ricci', 'partner', NULL, '2025-07-31 10:36:11', '2025-07-31 10:57:03', 1),
(6, 'project@cooperativaparsec.it', 'project@cooperativaparsec.it', '$2y$12$.EJ5tB5wpsGu6rnLw/FpjewiUlZJWsMKzt.vERXcvSR3oZUNN8HEG', 'Carmen Silipo', 'coordinator', 1, '2025-07-31 12:46:26', '2025-07-31 12:46:26', 1),
(7, 'guidoricci.lavoro@gmail.com', 'guidoricci.lavoro@gmail.com', '$2y$12$exB8ORR89GFMa0fX6Tk2pOzyTUCgqmp38hk1M4hz/BRiLbliaDU5u', 'Guido Ricci', 'coordinator', 1, '2025-08-27 09:32:12', '2025-08-27 09:32:12', 1);

-- --------------------------------------------------------

--
-- Struttura della tabella `work_packages`
--

CREATE TABLE `work_packages` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `wp_number` varchar(10) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `lead_partner_id` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `budget` decimal(10,2) DEFAULT NULL,
  `status` enum('not_started','in_progress','completed','delayed') DEFAULT 'not_started',
  `progress` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `work_packages`
--

INSERT INTO `work_packages` (`id`, `project_id`, `wp_number`, `name`, `description`, `lead_partner_id`, `start_date`, `end_date`, `budget`, `status`, `progress`, `created_at`) VALUES
(12, 28, 'WP1', 'PROJECT MANAGEMENT', 'WP1 ensures the overall coordination of the BRIDGE project through integrated quality, budget, timing and communication between partners. It includes continuous monitoring, risk management and reporting to ensure the achievement of project objectives and compliance with Erasmus+ requirements.', 1, '2025-09-01', '2027-08-31', 32421.00, 'not_started', 0.00, '2025-08-07 06:32:15'),
(14, 28, 'WP2', 'Knowing the phenomena: Analysis and comparison about young migrants and radicalization', 'WP2 establishes the methodological and operational foundation for BRIDGE, ensuring that the project’s actions are evidence-based and aligned with concrete needs. The main objective is to create a structured framework that informs training methodologies and participatory educational tools, integrating empathic listening as a guide approach. Includes: 1) Desk Research for comparative analysis of reception and integration systems in 5 countries, 2) Field Research with assessment of training needs through surveys / interviews with 50+ youth workers and 5 transnational focus groups, 3) Training Modules Development with participatory workshops. Main Deliverable: Comparative Map, Training Needs Analysis Report, Training Module Framework. It coordinates the development of structured content for WP3 (youth worker training) and prepares the framework for WP4 (peer education).', 3, '2025-09-01', '2026-03-31', 42854.00, 'not_started', 0.00, '2025-08-27 14:49:57');

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `work_package_id` (`work_package_id`),
  ADD KEY `responsible_partner_id` (`responsible_partner_id`),
  ADD KEY `fk_activity_project` (`project_id`);

--
-- Indici per le tabelle `activity_reports`
--
ALTER TABLE `activity_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activity_id` (`activity_id`),
  ADD KEY `partner_id` (`partner_id`),
  ADD KEY `fk_report_user` (`user_id`),
  ADD KEY `fk_activity_report_project` (`project_id`);

--
-- Indici per le tabelle `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `activity_id` (`activity_id`);

--
-- Indici per le tabelle `milestones`
--
ALTER TABLE `milestones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `work_package_id` (`work_package_id`);

--
-- Indici per le tabelle `participant_categories`
--
ALTER TABLE `participant_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indici per le tabelle `partners`
--
ALTER TABLE `partners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name_unique` (`name`);

--
-- Indici per le tabelle `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coordinator_id` (`coordinator_id`);

--
-- Indici per le tabelle `project_partners`
--
ALTER TABLE `project_partners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_project_partner` (`project_id`,`partner_id`),
  ADD KEY `fk_project_partner_link` (`partner_id`);

--
-- Indici per le tabelle `project_partners_old`
--
ALTER TABLE `project_partners_old`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_project_user` (`project_id`,`qqq`),
  ADD KEY `fk_project_partner_link` (`partner_id`);

--
-- Indici per le tabelle `project_risks`
--
ALTER TABLE `project_risks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_risk_unique` (`project_id`,`risk_id`),
  ADD KEY `risk_id` (`risk_id`);

--
-- Indici per le tabelle `risks`
--
ALTER TABLE `risks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `risk_code` (`risk_code`);

--
-- Indici per le tabelle `risk_alerts`
--
ALTER TABLE `risk_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_risk_id` (`project_risk_id`);

--
-- Indici per le tabelle `risk_history`
--
ALTER TABLE `risk_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_risk_id` (`project_risk_id`);

--
-- Indici per le tabelle `uploaded_files`
--
ALTER TABLE `uploaded_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indici per le tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_user_partner` (`partner_id`);

--
-- Indici per le tabelle `work_packages`
--
ALTER TABLE `work_packages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `lead_partner_id` (`lead_partner_id`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `activities`
--
ALTER TABLE `activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT per la tabella `activity_reports`
--
ALTER TABLE `activity_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT per la tabella `alerts`
--
ALTER TABLE `alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `milestones`
--
ALTER TABLE `milestones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT per la tabella `participant_categories`
--
ALTER TABLE `participant_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT per la tabella `partners`
--
ALTER TABLE `partners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT per la tabella `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT per la tabella `project_partners`
--
ALTER TABLE `project_partners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT per la tabella `project_partners_old`
--
ALTER TABLE `project_partners_old`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT per la tabella `project_risks`
--
ALTER TABLE `project_risks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT per la tabella `risks`
--
ALTER TABLE `risks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT per la tabella `risk_alerts`
--
ALTER TABLE `risk_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `risk_history`
--
ALTER TABLE `risk_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT per la tabella `uploaded_files`
--
ALTER TABLE `uploaded_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT per la tabella `work_packages`
--
ALTER TABLE `work_packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Limiti per le tabelle scaricate
--

--
-- Limiti per la tabella `activities`
--
ALTER TABLE `activities`
  ADD CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`work_package_id`) REFERENCES `work_packages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `activities_ibfk_2` FOREIGN KEY (`responsible_partner_id`) REFERENCES `partners` (`id`),
  ADD CONSTRAINT `fk_activity_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `activity_reports`
--
ALTER TABLE `activity_reports`
  ADD CONSTRAINT `activity_reports_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_activity_report_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_report_to_partner_organization` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_report_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Limiti per la tabella `alerts`
--
ALTER TABLE `alerts`
  ADD CONSTRAINT `alerts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alerts_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alerts_ibfk_3` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `milestones`
--
ALTER TABLE `milestones`
  ADD CONSTRAINT `milestones_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `milestones_ibfk_2` FOREIGN KEY (`work_package_id`) REFERENCES `work_packages` (`id`);

--
-- Limiti per la tabella `participant_categories`
--
ALTER TABLE `participant_categories`
  ADD CONSTRAINT `participant_categories_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`coordinator_id`) REFERENCES `users` (`id`);

--
-- Limiti per la tabella `project_partners`
--
ALTER TABLE `project_partners`
  ADD CONSTRAINT `project_partners_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_partners_ibfk_2` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `project_partners_old`
--
ALTER TABLE `project_partners_old`
  ADD CONSTRAINT `fk_project_partner_link` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_partners_old_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `project_risks`
--
ALTER TABLE `project_risks`
  ADD CONSTRAINT `project_risks_ibfk_1` FOREIGN KEY (`risk_id`) REFERENCES `risks` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `risk_alerts`
--
ALTER TABLE `risk_alerts`
  ADD CONSTRAINT `risk_alerts_ibfk_1` FOREIGN KEY (`project_risk_id`) REFERENCES `project_risks` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `risk_history`
--
ALTER TABLE `risk_history`
  ADD CONSTRAINT `risk_history_ibfk_1` FOREIGN KEY (`project_risk_id`) REFERENCES `project_risks` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `uploaded_files`
--
ALTER TABLE `uploaded_files`
  ADD CONSTRAINT `uploaded_files_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `activity_reports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `uploaded_files_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Limiti per la tabella `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_partner` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL;

--
-- Limiti per la tabella `work_packages`
--
ALTER TABLE `work_packages`
  ADD CONSTRAINT `work_packages_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `work_packages_ibfk_2` FOREIGN KEY (`lead_partner_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
