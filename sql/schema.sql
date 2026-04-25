-- ShareTime — Schéma de base de données
-- Généré le : 2026-04-25
-- Usage : mysql -u root sharetime < sql/schema.sql
-- (créer la BDD au préalable : CREATE DATABASE IF NOT EXISTS sharetime CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────
--  USERS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `idusers`       INT(11)      NOT NULL AUTO_INCREMENT,
  `nom`           VARCHAR(100) NOT NULL,
  `prenom`        VARCHAR(100) NOT NULL,
  `pseudo`        VARCHAR(50)  DEFAULT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `mot_de_passe`  VARCHAR(255) NOT NULL,
  `ville`         VARCHAR(100) DEFAULT NULL,
  `bio`           TEXT         DEFAULT NULL,
  `photo_profil`  VARCHAR(255) DEFAULT NULL,
  `note_moyenne`  FLOAT        DEFAULT 0,
  `role`          ENUM('utilisateur','admin','owner') NOT NULL DEFAULT 'utilisateur',
  `is_banned`     TINYINT(1)   NOT NULL DEFAULT 0,
  `date_naissance` DATE        DEFAULT NULL,
  `cgu_acceptees` TINYINT(1)   NOT NULL DEFAULT 0,
  `cgu_version`   VARCHAR(20)  DEFAULT NULL,
  `date_creation` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idusers`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
--  ACTIVITIES
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `activities` (
  `idactivities`        INT(11)      NOT NULL AUTO_INCREMENT,
  `creator_id`          INT(11)      NOT NULL,
  `title`               VARCHAR(150) NOT NULL,
  `description`         TEXT         NOT NULL,
  `location`            VARCHAR(255) DEFAULT NULL,
  `city`                VARCHAR(100) DEFAULT NULL,
  `start_time`          DATETIME     NOT NULL,
  `end_time`            DATETIME     NOT NULL,
  `max_participants`    INT(11)      NOT NULL,
  `visibility`          ENUM('publique','privee') DEFAULT 'publique',
  `category`            VARCHAR(20)  NOT NULL DEFAULT 'autre',
  `status`              ENUM('active','annulee','terminee') DEFAULT 'active',
  `liste_attente_active` TINYINT(1)  DEFAULT 0,
  `rappel_actif`        TINYINT(1)   DEFAULT 0,
  `conditions`          TEXT         DEFAULT NULL,
  `created_at`          DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idactivities`),
  KEY `creator_id` (`creator_id`),
  CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `users` (`idusers`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
--  REGISTRATIONS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `registrations` (
  `idregistration`  INT(11) NOT NULL AUTO_INCREMENT,
  `activity_id`     INT(11) NOT NULL,
  `user_id`         INT(11) NOT NULL,
  `status`          ENUM('inscrit','en_attente','annule') DEFAULT 'inscrit',
  `position_attente` INT(11) DEFAULT NULL,
  `registered_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  `cancelled_at`    DATETIME DEFAULT NULL,
  PRIMARY KEY (`idregistration`),
  UNIQUE KEY `activity_id` (`activity_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`idactivities`) ON DELETE CASCADE,
  CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`idusers`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
--  COMMENTS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `comments` (
  `idcomments`  INT(11) NOT NULL AUTO_INCREMENT,
  `activity_id` INT(11) NOT NULL,
  `user_id`     INT(11) NOT NULL,
  `content`     TEXT    NOT NULL,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idcomments`),
  KEY `activity_id` (`activity_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`idactivities`) ON DELETE CASCADE,
  CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`idusers`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
--  RATINGS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ratings` (
  `idratings`    INT(11)    NOT NULL AUTO_INCREMENT,
  `notateur_id`  INT(11)    NOT NULL,
  `note_id`      INT(11)    NOT NULL,
  `activity_id`  INT(11)    NOT NULL,
  `note`         TINYINT(4) NOT NULL CHECK (`note` BETWEEN 1 AND 5),
  `created_at`   DATETIME   DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idratings`),
  UNIQUE KEY `notateur_id` (`notateur_id`,`note_id`,`activity_id`),
  KEY `note_id` (`note_id`),
  KEY `activity_id` (`activity_id`),
  CONSTRAINT `ratings_ibfk_1` FOREIGN KEY (`notateur_id`) REFERENCES `users` (`idusers`),
  CONSTRAINT `ratings_ibfk_2` FOREIGN KEY (`note_id`) REFERENCES `users` (`idusers`),
  CONSTRAINT `ratings_ibfk_3` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`idactivities`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
--  FAQ
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `faq` (
  `idfaq`      INT(11)      NOT NULL AUTO_INCREMENT,
  `question`   VARCHAR(255) NOT NULL,
  `reponse`    TEXT         NOT NULL,
  `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idfaq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
--  CONTACT MESSAGES
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `name`     VARCHAR(100) NOT NULL,
  `email`    VARCHAR(150) NOT NULL,
  `subject`  VARCHAR(200) DEFAULT '',
  `message`  TEXT         NOT NULL,
  `sent_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read`  TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
--  PASSWORD RESETS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(150) NOT NULL,
  `token`      VARCHAR(64)  NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  `used`       TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
--  TABLES SANS UI (réservées à de futures fonctionnalités)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categories` (
  `idcategories` INT(11)      NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(100) NOT NULL,
  `description`  VARCHAR(500) DEFAULT NULL,
  `icone`        VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`idcategories`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `activity_category` (
  `activity_id`  INT(11) NOT NULL,
  `category_id`  INT(11) NOT NULL,
  PRIMARY KEY (`activity_id`,`category_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `activity_category_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`idactivities`) ON DELETE CASCADE,
  CONSTRAINT `activity_category_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`idcategories`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `followers` (
  `follower_id`  INT(11) NOT NULL,
  `following_id` INT(11) NOT NULL,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`follower_id`,`following_id`),
  KEY `following_id` (`following_id`),
  CONSTRAINT `followers_ibfk_1` FOREIGN KEY (`follower_id`) REFERENCES `users` (`idusers`) ON DELETE CASCADE,
  CONSTRAINT `followers_ibfk_2` FOREIGN KEY (`following_id`) REFERENCES `users` (`idusers`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `messages` (
  `idmessage`   INT(11) NOT NULL AUTO_INCREMENT,
  `sender_id`   INT(11) NOT NULL,
  `receiver_id` INT(11) NOT NULL,
  `content`     TEXT    NOT NULL,
  `is_read`     TINYINT(1) DEFAULT 0,
  `created_at`  DATETIME   DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idmessage`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`)   REFERENCES `users` (`idusers`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`idusers`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `idnotifications` INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`         INT(11)      NOT NULL,
  `activity_id`     INT(11)      DEFAULT NULL,
  `type`            VARCHAR(45)  DEFAULT NULL,
  `title`           VARCHAR(150) DEFAULT NULL,
  `content`         VARCHAR(500) DEFAULT NULL,
  `is_read`         TINYINT(1)   DEFAULT 0,
  `created_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idnotifications`),
  KEY `user_id` (`user_id`),
  KEY `activity_id` (`activity_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`)      REFERENCES `users` (`idusers`) ON DELETE CASCADE,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`activity_id`)  REFERENCES `activities` (`idactivities`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `reports` (
  `idreports`    INT(11) NOT NULL AUTO_INCREMENT,
  `signaleur_id` INT(11) NOT NULL,
  `signale_id`   INT(11) NOT NULL,
  `motif`        TEXT    NOT NULL,
  `status`       ENUM('en_attente','traite','rejete') DEFAULT 'en_attente',
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idreports`),
  KEY `signaleur_id` (`signaleur_id`),
  KEY `signale_id` (`signale_id`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`signaleur_id`) REFERENCES `users` (`idusers`),
  CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`signale_id`)   REFERENCES `users` (`idusers`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `user_interests` (
  `user_id`     INT(11) NOT NULL,
  `category_id` INT(11) NOT NULL,
  PRIMARY KEY (`user_id`,`category_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `user_interests_ibfk_1` FOREIGN KEY (`user_id`)     REFERENCES `users` (`idusers`) ON DELETE CASCADE,
  CONSTRAINT `user_interests_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`idcategories`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
