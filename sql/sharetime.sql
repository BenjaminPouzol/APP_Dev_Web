-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mer. 03 juin 2026 à 20:12
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `sharetime`
--

-- --------------------------------------------------------

--
-- Structure de la table `activities`
--

CREATE TABLE `activities` (
  `idactivities` int(11) NOT NULL,
  `creator_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `max_participants` int(11) NOT NULL,
  `visibility` enum('publique','privee') DEFAULT 'publique',
  `category` varchar(20) NOT NULL DEFAULT 'autre',
  `status` enum('active','annulee','terminee','en_cours') DEFAULT 'active',
  `liste_attente_active` tinyint(1) DEFAULT 0,
  `rappel_actif` tinyint(1) DEFAULT 0,
  `conditions` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `activities`
--

INSERT INTO `activities` (`idactivities`, `creator_id`, `title`, `description`, `photo`, `location`, `city`, `start_time`, `end_time`, `max_participants`, `visibility`, `category`, `status`, `liste_attente_active`, `rappel_actif`, `conditions`, `created_at`, `latitude`, `longitude`) VALUES
(4, 2, 'Visite du Louvre', 'Venez avec vos billets', NULL, 'Louvre', 'Paris', '2026-06-06 14:00:00', '2026-06-06 17:00:00', 10, 'publique', 'culture', 'active', 1, 0, NULL, '2026-06-02 17:17:30', 48.8611473, 2.3380277),
(5, 3, 'Marche dans le bois de Vincenne', 'Pensez à prendre de bonnes chausures, de la nourriture et de l\'eau', NULL, 'Bois de Vincennes', 'Paris', '2026-06-10 11:00:00', '2026-06-10 15:00:00', 30, 'publique', 'nature', 'active', 1, 0, NULL, '2026-06-02 17:20:14', 48.8315480, 2.4341479);

-- --------------------------------------------------------

--
-- Structure de la table `activity_category`
--

CREATE TABLE `activity_category` (
  `activity_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `target_type` enum('user','activity') NOT NULL,
  `target_id` int(11) NOT NULL,
  `details` varchar(255) DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `admin_id`, `action`, `target_type`, `target_id`, `details`, `created_at`) VALUES
(3, 2, 'delete_user', 'user', 1, 'Suppression de Benjamin', '2026-06-03 19:00:56');

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `idcategories` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `icone` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`idcategories`, `name`, `description`, `icone`) VALUES
(1, 'Sport', 'Activités sportives en groupe', 'sport'),
(2, 'Atelier', 'Ateliers créatifs', 'atelier'),
(3, 'Sortie', 'Sorties nature', 'sortie'),
(4, 'Club', 'Clubs réguliers', 'club'),
(5, 'Art', 'Activités artistiques', 'art');

-- --------------------------------------------------------

--
-- Structure de la table `cgu`
--

CREATE TABLE `cgu` (
  `idcgu` int(11) NOT NULL,
  `contenu` text NOT NULL,
  `version` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `cgu`
--

INSERT INTO `cgu` (`idcgu`, `contenu`, `version`, `created_at`) VALUES
(1, 'En utilisant ShareTime, vous acceptez les règles de la plateforme.', 'v1.0', '2026-04-04 12:50:59');

-- --------------------------------------------------------

--
-- Structure de la table `comments`
--

CREATE TABLE `comments` (
  `idcomments` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(200) DEFAULT '',
  `message` text NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `faq`
--

CREATE TABLE `faq` (
  `idfaq` int(11) NOT NULL,
  `question` varchar(255) NOT NULL,
  `reponse` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `faq`
--

INSERT INTO `faq` (`idfaq`, `question`, `reponse`, `created_at`) VALUES
(1, 'Comment créer une activité ?', 'Connectez-vous puis cliquez sur \"Créer une activité\" dans la barre de navigation. Remplissez le formulaire et validez.', '2026-04-25 21:08:07'),
(2, 'Comment m\'inscrire à une activité ?', 'Rendez-vous sur la page de l\'activité et cliquez sur \"S\'inscrire\". Vous devez être connecté.', '2026-04-25 21:08:07'),
(3, 'Comment annuler mon inscription ?', 'Allez sur la page de l\'activité concernée et cliquez sur \"Se désinscrire\". Si une liste d\'attente existe, la première personne en attente sera automatiquement promue.', '2026-04-25 21:08:07'),
(4, 'Comment noter un organisateur ?', 'Une fois l\'activité terminée, retournez sur sa page. Si vous étiez inscrit(e), un formulaire de notation apparaîtra.', '2026-04-25 21:08:07'),
(5, 'Comment signaler un problème ?', 'Utilisez le formulaire de la page \"Contact\" pour nous écrire directement.', '2026-04-25 21:08:07');

-- --------------------------------------------------------

--
-- Structure de la table `followers`
--

CREATE TABLE `followers` (
  `follower_id` int(11) NOT NULL,
  `following_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `followers`
--

INSERT INTO `followers` (`follower_id`, `following_id`, `created_at`) VALUES
(2, 3, '2026-06-02 17:32:52');

-- --------------------------------------------------------

--
-- Structure de la table `mentions`
--

CREATE TABLE `mentions` (
  `idmentions` int(11) NOT NULL,
  `contenu` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `idmessage` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `messages`
--

INSERT INTO `messages` (`idmessage`, `sender_id`, `receiver_id`, `content`, `is_read`, `created_at`) VALUES
(1, 3, 2, 'bonjour', 1, '2026-06-02 17:29:49');

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `idnotifications` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_id` int(11) DEFAULT NULL,
  `type` varchar(45) DEFAULT NULL,
  `title` varchar(150) DEFAULT NULL,
  `content` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`idnotifications`, `user_id`, `activity_id`, `type`, `title`, `content`, `is_read`, `created_at`) VALUES
(1, 3, NULL, 'nouveau_follower', 'Nouvel abonné', 'Louis a commencé à vous suivre.', 1, '2026-06-02 17:32:52');

-- --------------------------------------------------------

--
-- Structure de la table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ratings`
--

CREATE TABLE `ratings` (
  `idratings` int(11) NOT NULL,
  `notateur_id` int(11) NOT NULL,
  `note_id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `note` tinyint(4) NOT NULL CHECK (`note` between 1 and 5),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `registrations`
--

CREATE TABLE `registrations` (
  `idregistration` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('inscrit','en_attente','annule') DEFAULT 'inscrit',
  `position_attente` int(11) DEFAULT NULL,
  `registered_at` datetime DEFAULT current_timestamp(),
  `cancelled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `reports`
--

CREATE TABLE `reports` (
  `idreports` int(11) NOT NULL,
  `signaleur_id` int(11) NOT NULL,
  `signale_id` int(11) NOT NULL,
  `motif` text NOT NULL,
  `status` enum('en_attente','traite','rejete') DEFAULT 'en_attente',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `idusers` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `pseudo` varchar(50) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `mot_de_passe` varchar(255) NOT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `photo_profil` varchar(255) DEFAULT NULL,
  `note_moyenne` float DEFAULT 0,
  `role` enum('utilisateur','admin','superadmin') NOT NULL DEFAULT 'utilisateur',
  `is_banned` tinyint(1) NOT NULL DEFAULT 0,
  `date_naissance` date DEFAULT NULL,
  `cgu_acceptees` tinyint(1) NOT NULL DEFAULT 0,
  `cgu_version` varchar(20) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`idusers`, `nom`, `prenom`, `pseudo`, `email`, `email_verified`, `mot_de_passe`, `ville`, `bio`, `photo_profil`, `note_moyenne`, `role`, `is_banned`, `date_naissance`, `cgu_acceptees`, `cgu_version`, `date_creation`) VALUES
(2, 'De Normandie', 'Louis', 'Louis', 'louis.de_normandie@gmail.com', 0, '$2y$10$3GVcx5aaPfWNk8rhX.oADuVEaJP0brpKwDYfezQ39Z9tH/VdrVCp6', 'Lille', NULL, NULL, 0, 'superadmin', 0, '1989-11-05', 1, 'v1.0', '2026-06-01 18:24:20'),
(3, 'Dessendre', 'Charles', 'Charles', 'charles.dessendre@gmail.com', 0, '$2y$10$ep5gVkcJEEziZtlgmcCVdeR1eKs4vwpg9i9eQbs/0RkPjU8n1zzFG', 'Lyon', '', NULL, 0, 'utilisateur', 0, '1989-06-19', 1, 'v1.0', '2026-06-02 17:08:33');

-- --------------------------------------------------------

--
-- Structure de la table `user_interests`
--

CREATE TABLE `user_interests` (
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`idactivities`),
  ADD KEY `idx_act_status` (`status`),
  ADD KEY `idx_act_category` (`category`),
  ADD KEY `idx_act_creator` (`creator_id`),
  ADD KEY `idx_act_city` (`city`(50));

--
-- Index pour la table `activity_category`
--
ALTER TABLE `activity_category`
  ADD PRIMARY KEY (`activity_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Index pour la table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`idcategories`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Index pour la table `cgu`
--
ALTER TABLE `cgu`
  ADD PRIMARY KEY (`idcgu`);

--
-- Index pour la table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`idcomments`),
  ADD KEY `activity_id` (`activity_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `ev_user_fk` (`user_id`);

--
-- Index pour la table `faq`
--
ALTER TABLE `faq`
  ADD PRIMARY KEY (`idfaq`);

--
-- Index pour la table `followers`
--
ALTER TABLE `followers`
  ADD PRIMARY KEY (`follower_id`,`following_id`),
  ADD KEY `following_id` (`following_id`);

--
-- Index pour la table `mentions`
--
ALTER TABLE `mentions`
  ADD PRIMARY KEY (`idmentions`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`idmessage`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `idx_msg_receiver_read` (`receiver_id`,`is_read`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`idnotifications`),
  ADD KEY `activity_id` (`activity_id`),
  ADD KEY `idx_notif_user_read` (`user_id`,`is_read`);

--
-- Index pour la table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

--
-- Index pour la table `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`idratings`),
  ADD UNIQUE KEY `notateur_id` (`notateur_id`,`note_id`,`activity_id`),
  ADD KEY `note_id` (`note_id`),
  ADD KEY `activity_id` (`activity_id`);

--
-- Index pour la table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`idregistration`),
  ADD UNIQUE KEY `activity_id` (`activity_id`,`user_id`),
  ADD KEY `idx_reg_act_status` (`activity_id`,`status`),
  ADD KEY `idx_reg_user_status` (`user_id`,`status`);

--
-- Index pour la table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`idreports`),
  ADD KEY `signaleur_id` (`signaleur_id`),
  ADD KEY `signale_id` (`signale_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`idusers`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_email` (`email`);

--
-- Index pour la table `user_interests`
--
ALTER TABLE `user_interests`
  ADD PRIMARY KEY (`user_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `activities`
--
ALTER TABLE `activities`
  MODIFY `idactivities` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `idcategories` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `cgu`
--
ALTER TABLE `cgu`
  MODIFY `idcgu` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `comments`
--
ALTER TABLE `comments`
  MODIFY `idcomments` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `faq`
--
ALTER TABLE `faq`
  MODIFY `idfaq` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `mentions`
--
ALTER TABLE `mentions`
  MODIFY `idmentions` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `idmessage` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `idnotifications` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `idratings` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `idregistration` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `reports`
--
ALTER TABLE `reports`
  MODIFY `idreports` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `idusers` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `activities`
--
ALTER TABLE `activities`
  ADD CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `users` (`idusers`);

--
-- Contraintes pour la table `activity_category`
--
ALTER TABLE `activity_category`
  ADD CONSTRAINT `activity_category_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`idactivities`) ON DELETE CASCADE,
  ADD CONSTRAINT `activity_category_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`idcategories`) ON DELETE CASCADE;

--
-- Contraintes pour la table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `al_admin_fk` FOREIGN KEY (`admin_id`) REFERENCES `users` (`idusers`) ON DELETE CASCADE;

--
-- Contraintes pour la table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`idactivities`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`idusers`);

--
-- Contraintes pour la table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD CONSTRAINT `ev_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`idusers`) ON DELETE CASCADE;

--
-- Contraintes pour la table `followers`
--
ALTER TABLE `followers`
  ADD CONSTRAINT `followers_ibfk_1` FOREIGN KEY (`follower_id`) REFERENCES `users` (`idusers`) ON DELETE CASCADE,
  ADD CONSTRAINT `followers_ibfk_2` FOREIGN KEY (`following_id`) REFERENCES `users` (`idusers`) ON DELETE CASCADE;

--
-- Contraintes pour la table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`idusers`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`idusers`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`idusers`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`idactivities`) ON DELETE SET NULL;

--
-- Contraintes pour la table `ratings`
--
ALTER TABLE `ratings`
  ADD CONSTRAINT `ratings_ibfk_1` FOREIGN KEY (`notateur_id`) REFERENCES `users` (`idusers`),
  ADD CONSTRAINT `ratings_ibfk_2` FOREIGN KEY (`note_id`) REFERENCES `users` (`idusers`),
  ADD CONSTRAINT `ratings_ibfk_3` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`idactivities`) ON DELETE CASCADE;

--
-- Contraintes pour la table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`idactivities`) ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`idusers`);

--
-- Contraintes pour la table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`signaleur_id`) REFERENCES `users` (`idusers`),
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`signale_id`) REFERENCES `users` (`idusers`);

--
-- Contraintes pour la table `user_interests`
--
ALTER TABLE `user_interests`
  ADD CONSTRAINT `user_interests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`idusers`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_interests_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`idcategories`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
