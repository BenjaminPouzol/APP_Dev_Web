<?php
/**
 * config/database.php — Connexion à la base de données et migrations automatiques
 *
 * Ce fichier est le premier inclus par public/index.php à chaque requête.
 * Il remplit deux rôles :
 *   1. Ouvrir la connexion PDO et la stocker dans $pdo.
 *   2. S'assurer que le schéma de la base est à jour via des ALTER TABLE / CREATE TABLE
 *      conditionnels (migrations automatiques) — aucun script à lancer à la main.
 */

$host     = 'localhost';  // Serveur MySQL — XAMPP écoute en local
$dbname   = 'sharetime';  // Nom de la base de données
$username = 'root';       // Utilisateur MySQL par défaut sous XAMPP
$password = '';           // Pas de mot de passe dans la configuration XAMPP standard

try {
    // Crée la connexion PDO. charset=utf8mb4 garantit le support des accents,
    // caractères spéciaux et emojis (utf8mb4 couvre l'intégralité d'Unicode,
    // contrairement à utf8 qui est limité à 3 octets par caractère).
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

    // ERRMODE_EXCEPTION : toute erreur SQL lève une PDOException plutôt que
    // d'échouer silencieusement. Indispensable pour détecter les bugs SQL.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ── Migrations automatiques ──────────────────────────────────────────────
    // Chaque bloc ci-dessous vérifie si une colonne ou une table existe déjà
    // avant de l'ajouter. Cela permet de faire évoluer le schéma sans script
    // manuel : la base se met à jour toute seule au premier accès après un déploiement.

    // -- Table users : colonne pseudo (nom d'affichage choisi par l'utilisateur)
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'pseudo'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN pseudo VARCHAR(50) DEFAULT NULL AFTER prenom");
    }

    // -- Table users : colonne is_banned (0 = actif, 1 = suspendu par un admin)
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'is_banned'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_banned TINYINT(1) NOT NULL DEFAULT 0 AFTER role");
    }

    // -- Table users : date de naissance (optionnelle, utilisée pour l'affichage profil)
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'date_naissance'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN date_naissance DATE DEFAULT NULL AFTER is_banned");
    }

    // -- Table users : ajout du rôle 'owner' à l'ENUM existant si absent.
    // information_schema.COLUMNS contient la définition des colonnes de toutes les tables.
    $roleType = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                              WHERE TABLE_SCHEMA = DATABASE()
                              AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'")->fetchColumn();
    if (strpos($roleType, 'owner') === false) {
        // Modifie le type ENUM pour y inclure 'owner' sans toucher aux données existantes
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('utilisateur','admin','owner') NOT NULL DEFAULT 'utilisateur'");
    }

    // -- Table activities : catégorie (sport, culture, nature, etc.)
    if (!$pdo->query("SHOW COLUMNS FROM activities LIKE 'category'")->fetch()) {
        $pdo->exec("ALTER TABLE activities ADD COLUMN category VARCHAR(20) NOT NULL DEFAULT 'autre' AFTER visibility");
    }

    // -- Table activities : liste d'attente (1 = activée, les places sont distribuées dans l'ordre d'inscription)
    if (!$pdo->query("SHOW COLUMNS FROM activities LIKE 'liste_attente_active'")->fetch()) {
        $pdo->exec("ALTER TABLE activities ADD COLUMN liste_attente_active TINYINT(1) NOT NULL DEFAULT 0");
    }

    // -- Table users : note moyenne de l'organisateur (recalculée à chaque nouveau vote)
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'note_moyenne'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN note_moyenne FLOAT DEFAULT NULL");
    }

    // ── Tables créées à la volée si elles n'existent pas ─────────────────────
    // CREATE TABLE IF NOT EXISTS ne fait rien si la table existe déjà :
    // sans risque de perte de données.

    // Tokens de réinitialisation de mot de passe (valables 1h, usage unique)
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(150) NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,      -- token aléatoire de 32 octets en hex, unique pour éviter les collisions
        expires_at DATETIME NOT NULL,           -- date d'expiration calculée à +1h lors de la création
        used TINYINT(1) NOT NULL DEFAULT 0,     -- passe à 1 après utilisation pour rendre le lien inutilisable une seconde fois
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Messages du formulaire de contact (stockés pour consultation côté admin)
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        subject VARCHAR(200) DEFAULT '',
        message TEXT NOT NULL,
        sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) NOT NULL DEFAULT 0   -- permet de marquer les messages comme traités
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // -- Table activities : photo de couverture (nom du fichier stocké dans public/uploads/activites/)
    if (!$pdo->query("SHOW COLUMNS FROM activities LIKE 'photo'")->fetch()) {
        $pdo->exec("ALTER TABLE activities ADD COLUMN photo VARCHAR(255) DEFAULT NULL AFTER description");
    }

    // -- Table activities : coordonnées géographiques pour la carte interactive
    if (!$pdo->query("SHOW COLUMNS FROM activities LIKE 'latitude'")->fetch()) {
        $pdo->exec("ALTER TABLE activities ADD COLUMN latitude DECIMAL(10,7) DEFAULT NULL");
    }
    if (!$pdo->query("SHOW COLUMNS FROM activities LIKE 'longitude'")->fetch()) {
        $pdo->exec("ALTER TABLE activities ADD COLUMN longitude DECIMAL(10,7) DEFAULT NULL");
    }

    // Notifications in-app (cloche dans la navbar)
    // KEY (user_id) : index simple pour accélérer la récupération par utilisateur
    // ON DELETE CASCADE : si l'utilisateur est supprimé, ses notifications le sont aussi
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        idnotifications INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT(11) NOT NULL,
        activity_id INT(11) DEFAULT NULL,        -- activité concernée (nullable car certaines notifs n'ont pas d'activité)
        type        VARCHAR(45)  DEFAULT NULL,   -- ex. 'nouvelle_inscription', 'promotion_attente', 'nouveau_follower'
        title       VARCHAR(150) DEFAULT NULL,
        content     VARCHAR(500) DEFAULT NULL,
        is_read     TINYINT(1)   DEFAULT 0,      -- 0 = non lue (comptée dans la navbar), 1 = lue
        created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
        KEY (user_id),
        CONSTRAINT notif_user_fk  FOREIGN KEY (user_id)      REFERENCES users (idusers) ON DELETE CASCADE,
        CONSTRAINT notif_activ_fk FOREIGN KEY (activity_id)  REFERENCES activities (idactivities) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Abonnements entre utilisateurs (follower suit following)
    // Clé primaire composite (follower_id, following_id) : empêche qu'un utilisateur
    // suive deux fois le même autre utilisateur sans avoir besoin d'un UNIQUE séparé.
    $pdo->exec("CREATE TABLE IF NOT EXISTS followers (
        follower_id  INT(11) NOT NULL,   -- utilisateur qui suit
        following_id INT(11) NOT NULL,   -- utilisateur suivi
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (follower_id, following_id),
        KEY (following_id),              -- index pour compter rapidement les abonnés d'un utilisateur
        CONSTRAINT fol_follower_fk  FOREIGN KEY (follower_id)  REFERENCES users (idusers) ON DELETE CASCADE,
        CONSTRAINT fol_following_fk FOREIGN KEY (following_id) REFERENCES users (idusers) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // -- Table users : email_verified (0 = email non confirmé, 1 = confirmé via le lien envoyé par mail)
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER email");
    }

    // Tokens de vérification d'adresse email (valables 24h, supprimés après validation)
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_verifications (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        token      VARCHAR(64) NOT NULL UNIQUE,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT ev_user_fk FOREIGN KEY (user_id) REFERENCES users (idusers) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Messagerie privée entre utilisateurs
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        sender_id   INT NOT NULL,
        receiver_id INT NOT NULL,
        content     TEXT NOT NULL,
        is_read     TINYINT(1) NOT NULL DEFAULT 0,    -- 0 = non lu (comptabilisé dans le badge navbar)
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY (sender_id),
        KEY (receiver_id),
        KEY (created_at),
        CONSTRAINT msg_sender_fk   FOREIGN KEY (sender_id)   REFERENCES users (idusers) ON DELETE CASCADE,
        CONSTRAINT msg_receiver_fk FOREIGN KEY (receiver_id) REFERENCES users (idusers) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Journal des actions de modération (qui a fait quoi, sur qui, quand)
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_logs (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        admin_id    INT NOT NULL,                                   -- l'admin ou owner qui a agi
        action      VARCHAR(50)  NOT NULL,                          -- ex. 'ban', 'delete_user', 'set_role'
        target_type ENUM('user','activity') NOT NULL,               -- type de la cible de l'action
        target_id   INT NOT NULL,                                   -- id de la cible (user ou activité)
        details     VARCHAR(255) DEFAULT '',                        -- texte libre pour contextualiser l'action
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY (admin_id),
        KEY (created_at),
        CONSTRAINT al_admin_fk FOREIGN KEY (admin_id) REFERENCES users (idusers) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // FAQ (questions/réponses dynamiques, éditables par le super-admin)
    $pdo->exec("CREATE TABLE IF NOT EXISTS faq (
        idfaq      INT AUTO_INCREMENT PRIMARY KEY,
        question   VARCHAR(255) NOT NULL,
        reponse    TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Signalements entre utilisateurs (signaleur_id signale signale_id)
    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        idreports    INT AUTO_INCREMENT PRIMARY KEY,
        signaleur_id INT NOT NULL,
        signale_id   INT NOT NULL,
        motif        TEXT NOT NULL,
        status       ENUM('en_attente','traite','rejete') DEFAULT 'en_attente',
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY (signaleur_id),
        KEY (signale_id),
        CONSTRAINT rpt_signaleur_fk FOREIGN KEY (signaleur_id) REFERENCES users (idusers) ON DELETE CASCADE,
        CONSTRAINT rpt_signale_fk   FOREIGN KEY (signale_id)   REFERENCES users (idusers) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Contenu CGU géré depuis le panel super-admin (une seule ligne active)
    $pdo->exec("CREATE TABLE IF NOT EXISTS cgu (
        idcgu      INT AUTO_INCREMENT PRIMARY KEY,
        contenu    TEXT NOT NULL,
        version    VARCHAR(20) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Mentions légales gérées depuis le panel super-admin (une seule ligne active)
    $pdo->exec("CREATE TABLE IF NOT EXISTS mentions (
        idmentions INT AUTO_INCREMENT PRIMARY KEY,
        contenu    TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Index pour les colonnes de filtrage et comptage ──────────────────────
    // Ces index sont créés une seule fois (vérification via information_schema).
    // Ils accélèrent les filtres de recherche et les COUNT fréquents.
    $idx_check = "SELECT COUNT(*) FROM information_schema.STATISTICS
                  WHERE table_schema = DATABASE() AND table_name = :t AND index_name = :i";
    $chk = $pdo->prepare($idx_check);

    // Index sur activities.status : utilisé dans presque tous les filtres de getAll()
    $chk->execute(['t' => 'activities', 'i' => 'idx_act_status']);
    if (!$chk->fetchColumn()) $pdo->exec("ALTER TABLE activities ADD INDEX idx_act_status (status)");

    // Index sur activities.category : filtre par catégorie sur la page /activites
    $chk->execute(['t' => 'activities', 'i' => 'idx_act_category']);
    if (!$chk->fetchColumn()) $pdo->exec("ALTER TABLE activities ADD INDEX idx_act_category (category)");

    // Index sur activities.creator_id : JOIN avec users et getByCreator()
    $chk->execute(['t' => 'activities', 'i' => 'idx_act_creator']);
    if (!$chk->fetchColumn()) $pdo->exec("ALTER TABLE activities ADD INDEX idx_act_creator (creator_id)");

    // Index sur activities.city (préfixe 50 car VARCHAR) : filtre LIKE '%ville%'
    $chk->execute(['t' => 'activities', 'i' => 'idx_act_city']);
    if (!$chk->fetchColumn()) $pdo->exec("ALTER TABLE activities ADD INDEX idx_act_city (city(50))");

    // Index composite (activity_id, status) : utilisé dans les LEFT JOIN de comptage
    // des inscrits et des personnes en attente (les deux valeurs sont toujours filtrées ensemble)
    $chk->execute(['t' => 'registrations', 'i' => 'idx_reg_act_status']);
    if (!$chk->fetchColumn()) $pdo->exec("ALTER TABLE registrations ADD INDEX idx_reg_act_status (activity_id, status)");

    // Index composite (user_id, status) : getUserRegistrations() et comptage côté profil
    $chk->execute(['t' => 'registrations', 'i' => 'idx_reg_user_status']);
    if (!$chk->fetchColumn()) $pdo->exec("ALTER TABLE registrations ADD INDEX idx_reg_user_status (user_id, status)");

    // Index composite (user_id, is_read) : compteur de notifications non lues dans la navbar
    $chk->execute(['t' => 'notifications', 'i' => 'idx_notif_user_read']);
    if (!$chk->fetchColumn()) $pdo->exec("ALTER TABLE notifications ADD INDEX idx_notif_user_read (user_id, is_read)");

    // Index composite (receiver_id, is_read) : compteur de messages non lus dans la navbar
    $chk->execute(['t' => 'messages', 'i' => 'idx_msg_receiver_read']);
    if (!$chk->fetchColumn()) $pdo->exec("ALTER TABLE messages ADD INDEX idx_msg_receiver_read (receiver_id, is_read)");

    // Index sur users.email : utilisé à chaque connexion (findByEmail) et vérification d'unicité
    $chk->execute(['t' => 'users', 'i' => 'idx_users_email']);
    if (!$chk->fetchColumn()) $pdo->exec("ALTER TABLE users ADD INDEX idx_users_email (email)");

    // ── Nettoyage périodique des tokens expirés (environ 1 requête sur 50) ──
    // Évite que les tables password_resets et email_verifications grossissent indéfiniment.
    // mt_rand(1,50) === 1 signifie que le nettoyage n'a lieu qu'en moyenne 1 fois sur 50 requêtes,
    // ce qui évite un DELETE coûteux à chaque page.
    if (mt_rand(1, 50) === 1) {
        $pdo->exec("DELETE FROM password_resets WHERE expires_at < NOW() OR used = 1");
        $pdo->exec("DELETE FROM email_verifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    }

} catch (PDOException $e) {
    // Arrête l'exécution et affiche l'erreur si la connexion échoue.
    // En production, il faudrait logger l'erreur sans l'afficher à l'utilisateur.
    die("Erreur connexion DB : " . $e->getMessage());
}
