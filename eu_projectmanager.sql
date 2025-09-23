-- phpMyAdmin SQL Dump
-- version 5.2.2-1.fc42
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Creato il: Set 21, 2025 alle 18:00
-- Versione del server: 10.11.11-MariaDB
-- Versione PHP: 8.4.12

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
  `due_date` date DEFAULT NULL,
  `status` enum('not_started','in_progress','completed','overdue') DEFAULT 'not_started',
  `progress` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `project_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `activities`
--

INSERT INTO `activities` (`id`, `work_package_id`, `activity_number`, `name`, `description`, `responsible_partner_id`, `start_date`, `end_date`, `due_date`, `status`, `progress`, `created_at`, `updated_at`, `project_id`) VALUES
(35, 12, '1.1', 'First Setup and Governance', 'Online kick-off meeting organization\r\nDistribution partnership agreements to all partners\r\nDefining roles, responsibilities and operating procedures\r\nBuild integrated communication system (WhatsApp, Email, Google Drive)\r\nSetup e-learning platform for material exchanges', 1, '2025-08-03', '2025-10-01', NULL, 'completed', 0.00, '2025-08-07 06:37:41', '2025-09-17 18:50:51', 28),
(37, 12, '1.2', 'Online Kick off meeting', 'KOM online', 1, '2025-09-24', '2025-09-28', NULL, 'in_progress', 0.00, '2025-08-27 15:30:15', '2025-09-16 15:39:35', 28),
(41, 14, '2.1', 'Desk Research: Comparative analysis of reception and integration systems for migrant minors and professionals involved', 'This activity maps reception and integration models across partner countries, focusing on youth work, intercultural mediation, and education pathways. Key activities include: comparing national policies and identifying strengths, gaps, and best practices; analyzing the role of youth workers in supporting migrant youth; collecting case studies from at least five countries to inform training strategies. The activity will produce a comparative map that guides WP3 training content and ensures alignment with real policy and professional contexts. Expected outcomes: comprehensive overview of policies, professional roles, and best practices in youth work across five countries; identification of common challenges and gaps that hinder migrant youth inclusion; knowledge base for WP3 training development.', 8, '2025-09-01', '2025-10-31', NULL, 'not_started', 0.00, '2025-08-27 17:04:15', '2025-08-27 17:04:15', 28),
(42, 14, '2.2', 'Field Research: Training needs assessment', 'This phase ensures WP3 training directly addresses youth workers needs and prepares for WP4 peer education. Key activities include: surveying/interviewing 50+ youth workers and educators to assess skill gaps; organizing 5 transnational focus groups engaging youth workers and young migrants; identifying key competencies including empathetic listening, peer facilitation, and intercultural mediation. The activity will produce a Training Needs Analysis Report defining WP3 training priorities and methodologies. Expected participants: 50 social workers involved in education of foreign minors across partner countries; 20 operators and mediators; 60 migrant minors including at least 20 unaccompanied minors (UAMs). This activity directly informs WP3 by defining the competency framework that shapes training content.', 8, '2025-11-01', '2026-01-31', NULL, 'not_started', 0.00, '2025-08-27 17:04:15', '2025-08-27 17:04:15', 28),
(43, 14, '2.3', 'Development of Training Modules Framework', 'WP2 findings will shape structured, practice-oriented training for WP3, ensuring youth workers are equipped to facilitate WP4 peer education. Key activities include: organizing co-creation workshops where youth workers and young migrants refine training content (minimum 3 participatory workshops); defining learning objectives, participatory methods, and interactive tools; ensuring alignment with WP4 to enable youth workers to guide young migrants in co-developing and implementing Mixing Culture. The activity will produce a structured training module framework integrating empathetic listening, peer education facilitation, and intercultural mediation as core learning components. Expected outcomes: structured training program with clear learning objectives and interactive methodologies; development of participatory learning tools including case studies, role-playing, and scenario-based learning; co-created content ensuring smooth transition to WP4 peer education strategies.', 8, '2026-02-01', '2026-03-31', NULL, 'not_started', 0.00, '2025-08-27 17:04:15', '2025-08-27 17:04:15', 28),
(44, 14, '2.3', 'pova', 'qq', 1, '2025-09-17', '2025-09-18', NULL, 'not_started', 0.00, '2025-09-17 18:55:50', '2025-09-17 18:55:50', 28),
(45, 12, '1.3', 'www', 'wwww', 1, '2025-09-17', '2025-09-25', NULL, 'not_started', 0.00, '2025-09-17 18:56:08', '2025-09-17 18:56:08', 28),
(53, 24, '1.1', 'management', 'DESCR', 1, '2025-10-01', '2026-10-01', NULL, 'not_started', 0.00, '2025-09-21 06:37:25', '2025-09-21 06:37:25', 37),
(54, 25, '2.1', 'DESK', 'DESCR DESK', 2, '2025-11-01', '2025-02-01', NULL, 'not_started', 0.00, '2025-09-21 06:37:25', '2025-09-21 06:37:25', 37);

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
(14, 35, 1, 7, '2025-08-27', 'pROVA REPORT SEMPLICE', '\"20 GIOVANI\"', '2025-08-27 10:25:01', '2025-08-27 10:25:01', NULL, NULL, NULL, 28, NULL, NULL, NULL, NULL, NULL),
(15, 43, 2, 1, '2025-09-03', 'xzvzcxv', '\"xcxccxvcx\"', '2025-09-03 08:40:03', '2025-09-03 08:40:03', NULL, NULL, NULL, 28, 4, NULL, NULL, NULL, NULL),
(16, 35, 1, 1, '2025-09-03', 'sasadaafdfda', '', '2025-09-03 08:42:47', '2025-09-03 08:42:47', NULL, NULL, NULL, 28, 1, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Struttura della tabella `alerts`
--

CREATE TABLE `alerts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `activity_id` int(11) DEFAULT NULL,
  `type` enum('deadline','report_submitted','milestone','general','risk','risk_persistent') NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `alerts`
--

INSERT INTO `alerts` (`id`, `user_id`, `project_id`, `activity_id`, `type`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-29 04:22:22'),
(2, 1, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-29 04:24:14'),
(3, 1, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-29 04:24:23'),
(4, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-29 04:28:02'),
(5, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:11:41'),
(6, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:13:01'),
(7, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:14:08'),
(8, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:14:10'),
(9, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:14:11'),
(10, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:14:12'),
(11, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:20:09'),
(12, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:22:25'),
(13, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:26:32'),
(14, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:26:34'),
(15, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:26:34'),
(16, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:26:35'),
(17, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:26:36'),
(18, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:26:38'),
(19, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:28:35'),
(20, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:28:36'),
(21, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:28:36'),
(22, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:28:37'),
(23, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:31:16'),
(24, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:31:17'),
(25, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:31:27'),
(26, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:31:28'),
(27, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:31:29'),
(28, 7, 28, NULL, 'risk_persistent', 'Persistent Risk Alert: Bridge', 'Risk \'Difficoltà nel reclutamento e nel coinvolgimento del target group (giovani migranti)\' (Score: 16, Status: Critical) is currently critical. Please review.', 1, '2025-08-30 07:31:29'),
(29, 1, 28, NULL, 'risk_persistent', 'Critical Risk Alert: Bridge', 'Risk \'Problemi tecnici sulla piattaforma di progetto (downtime, bug, performance)\' (Score: 6, Status: Medium) is currently critical. Please review.', 1, '2025-09-03 08:45:26'),
(30, 1, 28, NULL, 'risk_persistent', 'Critical Risk Alert: Bridge', 'Risk \'Conflitti interculturali o problemi di comunicazione all&#039;interno del partenariato\' (Score: 12, Status: High) is currently critical. Please review.', 1, '2025-09-03 08:45:26'),
(31, 1, 28, NULL, 'risk_persistent', 'Critical Risk Alert: Bridge', 'Risk \'Scarsa efficacia delle attività di disseminazione e comunicazione dei risultati\' (Score: 9, Status: High) is currently critical. Please review.', 1, '2025-09-03 08:45:26'),
(32, 7, 28, NULL, 'risk_persistent', 'Critical Risk Alert: Bridge', 'Risk \'Problemi tecnici sulla piattaforma di progetto (downtime, bug, performance)\' (Score: 6, Status: Medium) is currently critical. Please review.', 1, '2025-09-10 08:40:43'),
(33, 7, 28, NULL, 'risk_persistent', 'Critical Risk Alert: Bridge', 'Risk \'Conflitti interculturali o problemi di comunicazione all&#039;interno del partenariato\' (Score: 12, Status: High) is currently critical. Please review.', 1, '2025-09-10 08:40:43'),
(34, 7, 28, NULL, 'risk_persistent', 'Critical Risk Alert: Bridge', 'Risk \'Scarsa efficacia delle attività di disseminazione e comunicazione dei risultati\' (Score: 9, Status: High) is currently critical. Please review.', 1, '2025-09-10 08:40:43'),
(35, 1, 28, NULL, 'risk_persistent', 'Critical Risk Alert: Bridge', 'Risk \'Problemi tecnici sulla piattaforma di progetto (downtime, bug, performance)\' (Score: 6, Status: Medium) is currently critical. Please review.', 1, '2025-09-11 08:25:36'),
(36, 1, 28, NULL, 'risk_persistent', 'Critical Risk Alert: Bridge', 'Risk \'Conflitti interculturali o problemi di comunicazione all&#039;interno del partenariato\' (Score: 12, Status: High) is currently critical. Please review.', 1, '2025-09-11 08:25:36'),
(37, 1, 28, NULL, 'risk_persistent', 'Critical Risk Alert: Bridge', 'Risk \'Scarsa efficacia delle attività di disseminazione e comunicazione dei risultati\' (Score: 9, Status: High) is currently critical. Please review.', 1, '2025-09-11 08:25:36'),
(38, 1, 28, NULL, 'risk_persistent', 'Critical Risk Alert: Bridge', 'Risk \'Problemi tecnici sulla piattaforma di progetto (downtime, bug, performance)\' (Score: 6, Status: Medium) is currently critical. Please review.', 0, '2025-09-15 12:57:13'),
(39, 1, 28, NULL, 'risk_persistent', 'Critical Risk Alert: Bridge', 'Risk \'Problemi tecnici sulla piattaforma di progetto (downtime, bug, performance)\' (Score: 6, Status: Medium) is currently critical. Please review.', 0, '2025-09-16 14:14:49'),
(40, 7, 28, NULL, 'risk_persistent', 'Critical Risk Alert: Bridge', 'Risk \'Problemi tecnici sulla piattaforma di progetto (downtime, bug, performance)\' (Score: 6, Status: Medium) is currently critical. Please review.', 1, '2025-09-16 14:46:17');

-- --------------------------------------------------------

--
-- Struttura della tabella `budget_travel_subsistence`
--

CREATE TABLE `budget_travel_subsistence` (
  `id` int(11) NOT NULL,
  `wp_partner_budget_id` int(11) NOT NULL COMMENT 'FK to work_package_partner_budgets',
  `activity_destination` varchar(255) NOT NULL COMMENT 'Activity description or destination',
  `persons` int(11) NOT NULL COMMENT 'Number of persons',
  `days` int(11) NOT NULL COMMENT 'Number of days',
  `travel_cost` decimal(10,2) NOT NULL COMMENT 'Travel cost in EUR',
  `daily_subsistence` decimal(10,2) NOT NULL COMMENT 'Daily subsistence rate in EUR',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Manual total cost entered by user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Travel and subsistence costs for work packages (max 3 per WP)';

--
-- Dump dei dati per la tabella `budget_travel_subsistence`
--

INSERT INTO `budget_travel_subsistence` (`id`, `wp_partner_budget_id`, `activity_destination`, `persons`, `days`, `travel_cost`, `daily_subsistence`, `created_at`, `updated_at`, `total`) VALUES
(14, 58, 'KOM ITALY', 2, 2, 200.00, 90.00, '2025-09-21 17:03:47', '2025-09-21 17:03:47', 760.00),
(15, 59, 'km spain', 3, 3, 100.00, 100.00, '2025-09-21 17:03:47', '2025-09-21 17:03:47', 1200.00);

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

--
-- Dump dei dati per la tabella `milestones`
--

INSERT INTO `milestones` (`id`, `project_id`, `work_package_id`, `name`, `description`, `due_date`, `status`, `completed_date`, `created_at`) VALUES
(12, 28, 12, 'Kick off Meeting online', 'Meeting online di avvio progetto', '2025-09-28', 'pending', NULL, '2025-08-28 19:31:02');

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
(9, 'Changes&Chances', 'NGO', 'Netherlands', '2025-08-04 16:11:11'),
(10, 'Pippi Peli Uniti', 'University', 'USA', '2025-08-28 15:47:10');

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
  `google_groups_url` varchar(500) DEFAULT NULL COMMENT 'URL del Google Group associato al progetto',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `projects`
--

INSERT INTO `projects` (`id`, `name`, `description`, `program_type`, `start_date`, `end_date`, `coordinator_id`, `status`, `budget`, `google_groups_url`, `created_at`, `updated_at`) VALUES
(28, 'Bridge', 'Project Bridge prevents youth radicalisation by fostering social inclusion of young migrants and unaccompanied minors. It trains youth workers in empathic listening and intercultural facilitation, while empowering migrants as peer educators. Co-created tools, including the game *Mixing Culture*, promote dialogue, participation, and sustainable integration. Key work packages cover research, training, peer education, and dissemination. Expected outcomes include comparative analysis, training modules for 200 youth workers, a peer education framework, and the tested *Mixing Culture* game.', 'erasmus_plus', '2025-09-01', '2027-08-31', 7, 'active', 250000.00, 'https://groups.google.com/g/erasmus-bridge', '2025-08-04 17:47:02', '2025-09-16 14:37:15'),
(29, 'Pinco pallo project', 'xxx', 'horizon_europe', '2025-09-17', '2025-09-30', 6, 'planning', 11111.00, NULL, '2025-09-17 07:24:35', '2025-09-17 07:24:35'),
(37, 'pROGETTO bUDGET pROVA', 'DESCrizione', 'erasmus_plus', '2025-10-01', '2026-10-01', 7, 'planning', 100000.00, NULL, '2025-09-21 06:35:33', '2025-09-21 16:35:16');

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
(56, 28, 3, 'partner', NULL, NULL, 43140.00, '2025-08-04 17:48:10'),
(57, 29, 1, 'coordinator', NULL, NULL, 10.00, '2025-09-17 07:24:35'),
(58, 29, 2, 'partner', NULL, NULL, 200.00, '2025-09-17 07:24:43'),
(79, 37, 1, 'coordinator', NULL, NULL, 50000.00, '2025-09-21 06:35:33'),
(80, 37, 2, 'partner', NULL, NULL, 50000.00, '2025-09-21 06:35:42');

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
(1, 28, 1, 1, 3, 3, 'Medium', '2025-09-03 08:42:47'),
(2, 28, 2, 1, 4, 4, 'Medium', '2025-09-03 08:42:47'),
(3, 28, 3, 2, 3, 6, 'Medium', '2025-09-03 08:42:47'),
(4, 28, 4, 3, 1, 3, 'Medium', '2025-09-11 08:26:08'),
(5, 28, 5, 1, 5, 5, 'Medium', '2025-09-03 08:42:47'),
(6, 28, 6, 1, 4, 4, 'Medium', '2025-09-03 08:42:47'),
(7, 28, 7, 3, 1, 3, 'Medium', '2025-09-11 08:26:14'),
(8, 28, 8, 1, 4, 4, 'Medium', '2025-09-03 08:42:47'),
(9, 28, 9, 2, 3, 6, 'Medium', '2025-09-03 08:42:47'),
(10, 28, 10, 3, 1, 3, 'Medium', '2025-09-11 08:26:19');

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
(2, 9, 1, 4, 'Manual update from admin panel.', '2025-08-27 13:28:33'),
(4, 1, 9, 16, 'Manual update from admin panel.', '2025-08-29 04:04:03'),
(5, 1, 16, 4, 'Manual update from admin panel.', '2025-08-29 04:15:51'),
(6, 2, 1, 16, 'Manual update from admin panel.', '2025-08-29 04:15:58'),
(7, 2, 16, 4, 'Manual update from admin panel.', '2025-08-30 07:37:53'),
(8, 1, 4, 3, 'Recalculated based on overdue milestones and activities.', '2025-09-03 08:42:47'),
(9, 3, 1, 6, 'Recalculated based on simulated technical issues.', '2025-09-03 08:42:47'),
(10, 4, 1, 12, 'Recalculated based on collaboration ratings.', '2025-09-03 08:42:47'),
(11, 5, 1, 5, 'Recalulated based on budget status reports.', '2025-09-03 08:42:47'),
(12, 6, 1, 4, 'Recalculated based on output quality checks.', '2025-09-03 08:42:47'),
(13, 7, 1, 9, 'Recalculated based on simulated dissemination metrics.', '2025-09-03 08:42:47'),
(14, 8, 1, 4, 'Recalculated based on simulated health emergencies.', '2025-09-03 08:42:47'),
(15, 9, 4, 6, 'Recalculated based on simulated staff turnover.', '2025-09-03 08:42:47'),
(16, 10, 1, 9, 'Recalculated based on simulated non-compliance.', '2025-09-03 08:42:47'),
(17, 4, 12, 3, 'Manual update from admin panel.', '2025-09-11 08:26:08'),
(18, 7, 9, 3, 'Manual update from admin panel.', '2025-09-11 08:26:14'),
(19, 10, 9, 3, 'Manual update from admin panel.', '2025-09-11 08:26:19');

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
(7, 'guidoricci.lavoro@gmail.com', 'guidoricci.lavoro@gmail.com', '$2y$12$exB8ORR89GFMa0fX6Tk2pOzyTUCgqmp38hk1M4hz/BRiLbliaDU5u', 'Guido Ricci', 'coordinator', 1, '2025-08-27 09:32:12', '2025-08-27 09:32:12', 1),
(8, 'pippopelo@gmail.it', 'pippopelo@gmail.it', '$2y$12$ARtgZ62eS6rEz3YE1MCiHuX5yjukjakw2J3ls8qbUXUjxLu5PLdf2', 'Pippo Pelo', 'coordinator', 2, '2025-08-28 15:46:10', '2025-08-28 15:46:10', 1);

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
(12, 28, 'WP1', 'PROJECT MANAGEMENT', 'WP1 ensures the overall coordination of the BRIDGE project through integrated quality, budget, timing and communication between partners. It includes continuous monitoring, risk management and reporting to ensure the achievement of project objectives and compliance with Erasmus+ requirements.', 1, '2025-09-01', '2027-08-31', 6000.00, 'not_started', 0.00, '2025-08-07 06:32:15'),
(14, 28, 'WP2', 'Knowing the phenomena: Analysis and comparison about young migrants and radicalization', 'WP2 establishes the methodological and operational foundation for BRIDGE, ensuring that the project’s actions are evidence-based and aligned with concrete needs. The main objective is to create a structured framework that informs training methodologies and participatory educational tools, integrating empathic listening as a guide approach. Includes: 1) Desk Research for comparative analysis of reception and integration systems in 5 countries, 2) Field Research with assessment of training needs through surveys / interviews with 50+ youth workers and 5 transnational focus groups, 3) Training Modules Development with participatory workshops. Main Deliverable: Comparative Map, Training Needs Analysis Report, Training Module Framework. It coordinates the development of structured content for WP3 (youth worker training) and prepares the framework for WP4 (peer education).', 3, '2025-09-01', '2026-03-31', 42854.00, 'not_started', 0.00, '2025-08-27 14:49:57'),
(16, 28, 'WP3', 'Prova terzo WP', 'ccvvvv', 9, '2025-11-11', '2025-12-11', 5000.00, 'not_started', 0.00, '2025-09-20 18:12:02'),
(24, 37, 'WP1', 'Project Management', 'DESRizione', 1, '2025-10-01', '2026-10-01', NULL, 'not_started', 0.00, '2025-09-21 06:37:25'),
(25, 37, 'WP2', 'Research', 'DESCR RES', 2, '2025-11-01', '2026-03-01', NULL, 'not_started', 0.00, '2025-09-21 06:37:25'),
(26, 37, 'WP3', 'Prova WP3', 'descrizione', 1, '2025-12-10', '2026-03-10', 0.00, 'not_started', 0.00, '2025-09-21 17:13:04');

-- --------------------------------------------------------

--
-- Struttura della tabella `work_package_partner_budgets`
--

CREATE TABLE `work_package_partner_budgets` (
  `id` int(11) NOT NULL,
  `work_package_id` int(11) NOT NULL COMMENT 'FK alla tabella work_packages',
  `partner_id` int(11) NOT NULL COMMENT 'FK alla tabella partners',
  `project_id` int(11) NOT NULL COMMENT 'FK alla tabella projects (per integrità)',
  `wp_type` enum('project_management','standard') DEFAULT 'standard' COMMENT 'Type of work package: project_management for WP1, standard for others',
  `project_management_cost` decimal(10,2) DEFAULT NULL COMMENT 'Flat rate cost for WP1 project management only',
  `working_days` int(11) DEFAULT NULL COMMENT 'Number of working days (for standard WP only)',
  `daily_rate` decimal(10,2) DEFAULT NULL COMMENT 'Daily rate in EUR (for standard WP only)',
  `working_days_total` decimal(10,2) DEFAULT NULL COMMENT 'Calculated: working_days * daily_rate',
  `other_costs` decimal(10,2) DEFAULT 0.00 COMMENT 'Other miscellaneous costs',
  `other_description` text DEFAULT NULL COMMENT 'Description of other costs',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `work_package_partner_budgets`
--

INSERT INTO `work_package_partner_budgets` (`id`, `work_package_id`, `partner_id`, `project_id`, `wp_type`, `project_management_cost`, `working_days`, `daily_rate`, `working_days_total`, `other_costs`, `other_description`, `created_at`, `updated_at`) VALUES
(6, 16, 1, 28, 'standard', NULL, 0, 0.00, NULL, 0.00, '', '2025-09-20 18:12:02', '2025-09-20 18:56:28'),
(7, 16, 2, 28, 'standard', NULL, 0, 0.00, NULL, 0.00, '', '2025-09-20 18:12:02', '2025-09-20 18:56:28'),
(8, 16, 9, 28, 'standard', NULL, 0, 0.00, NULL, 0.00, '', '2025-09-20 18:12:02', '2025-09-20 18:56:28'),
(9, 16, 8, 28, 'standard', NULL, NULL, NULL, NULL, 0.00, NULL, '2025-09-20 18:12:02', '2025-09-20 20:46:51'),
(10, 16, 3, 28, 'standard', NULL, 0, 0.00, NULL, 0.00, '', '2025-09-20 18:12:02', '2025-09-20 18:56:28'),
(16, 12, 1, 28, 'standard', NULL, NULL, NULL, NULL, 0.00, NULL, '2025-09-20 18:58:43', '2025-09-20 18:58:43'),
(17, 12, 2, 28, 'standard', NULL, NULL, NULL, NULL, 0.00, NULL, '2025-09-20 18:58:43', '2025-09-20 18:58:43'),
(18, 12, 9, 28, 'standard', NULL, NULL, NULL, NULL, 0.00, NULL, '2025-09-20 18:58:43', '2025-09-20 18:58:43'),
(19, 12, 8, 28, 'standard', NULL, NULL, NULL, NULL, 0.00, NULL, '2025-09-20 18:58:43', '2025-09-20 18:58:43'),
(20, 12, 3, 28, 'standard', NULL, NULL, NULL, NULL, 0.00, NULL, '2025-09-20 18:58:43', '2025-09-20 18:58:43'),
(57, 24, 1, 37, 'project_management', 10000.00, NULL, NULL, NULL, 0.00, '', '2025-09-21 06:37:45', '2025-09-21 17:03:47'),
(58, 24, 2, 37, 'project_management', 5000.00, NULL, NULL, NULL, 0.00, '', '2025-09-21 06:37:45', '2025-09-21 17:03:47'),
(59, 25, 1, 37, 'standard', NULL, 30, 300.00, 9000.00, 0.00, '', '2025-09-21 06:37:45', '2025-09-21 17:03:47'),
(60, 25, 2, 37, 'standard', NULL, 50, 200.00, 10000.00, 0.00, '', '2025-09-21 06:37:45', '2025-09-21 17:03:47'),
(74, 14, 1, 28, 'standard', NULL, NULL, NULL, NULL, 0.00, NULL, '2025-09-21 16:30:33', '2025-09-21 16:30:33'),
(75, 14, 2, 28, 'standard', NULL, NULL, NULL, NULL, 0.00, NULL, '2025-09-21 16:30:33', '2025-09-21 16:30:33'),
(76, 14, 3, 28, 'standard', NULL, NULL, NULL, NULL, 0.00, NULL, '2025-09-21 16:30:33', '2025-09-21 16:30:33'),
(77, 14, 8, 28, 'standard', NULL, NULL, NULL, NULL, 0.00, NULL, '2025-09-21 16:30:33', '2025-09-21 16:30:33'),
(78, 14, 9, 28, 'standard', NULL, NULL, NULL, NULL, 0.00, NULL, '2025-09-21 16:30:33', '2025-09-21 16:30:33');

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
-- Indici per le tabelle `budget_travel_subsistence`
--
ALTER TABLE `budget_travel_subsistence`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_travel_wp_budget` (`wp_partner_budget_id`),
  ADD KEY `idx_travel_activity` (`activity_destination`);

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
-- Indici per le tabelle `work_package_partner_budgets`
--
ALTER TABLE `work_package_partner_budgets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_wp_partner` (`work_package_id`,`partner_id`),
  ADD KEY `fk_wpbudget_work_package` (`work_package_id`),
  ADD KEY `fk_wpbudget_partner` (`partner_id`),
  ADD KEY `fk_wpbudget_project` (`project_id`),
  ADD KEY `idx_wp_type` (`wp_type`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `activities`
--
ALTER TABLE `activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT per la tabella `activity_reports`
--
ALTER TABLE `activity_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT per la tabella `alerts`
--
ALTER TABLE `alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT per la tabella `budget_travel_subsistence`
--
ALTER TABLE `budget_travel_subsistence`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT per la tabella `milestones`
--
ALTER TABLE `milestones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT per la tabella `participant_categories`
--
ALTER TABLE `participant_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT per la tabella `partners`
--
ALTER TABLE `partners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT per la tabella `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT per la tabella `project_partners`
--
ALTER TABLE `project_partners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT per la tabella `uploaded_files`
--
ALTER TABLE `uploaded_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT per la tabella `work_packages`
--
ALTER TABLE `work_packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT per la tabella `work_package_partner_budgets`
--
ALTER TABLE `work_package_partner_budgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=188;

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
-- Limiti per la tabella `budget_travel_subsistence`
--
ALTER TABLE `budget_travel_subsistence`
  ADD CONSTRAINT `fk_travel_wp_budget` FOREIGN KEY (`wp_partner_budget_id`) REFERENCES `work_package_partner_budgets` (`id`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `work_packages_ibfk_2` FOREIGN KEY (`lead_partner_id`) REFERENCES `partners` (`id`);

--
-- Limiti per la tabella `work_package_partner_budgets`
--
ALTER TABLE `work_package_partner_budgets`
  ADD CONSTRAINT `fk_wpbudget_partner` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_wpbudget_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_wpbudget_work_package` FOREIGN KEY (`work_package_id`) REFERENCES `work_packages` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
