<?php
$host = 'localhost';
$dbname = 'sharetime';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ── Migrations automatiques ──────────────────────────────

    // users : colonne pseudo
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'pseudo'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN pseudo VARCHAR(50) DEFAULT NULL AFTER prenom");
    }
    // users : colonne is_banned
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'is_banned'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_banned TINYINT(1) NOT NULL DEFAULT 0 AFTER role");
    }
    // users : colonne date_naissance
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'date_naissance'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN date_naissance DATE DEFAULT NULL AFTER is_banned");
    }
    // users : role ENUM inclut 'owner'
    $roleType = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                              WHERE TABLE_SCHEMA = DATABASE()
                              AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'")->fetchColumn();
    if (strpos($roleType, 'owner') === false) {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('utilisateur','admin','owner') NOT NULL DEFAULT 'utilisateur'");
    }

    // activities : colonne category
    if (!$pdo->query("SHOW COLUMNS FROM activities LIKE 'category'")->fetch()) {
        $pdo->exec("ALTER TABLE activities ADD COLUMN category VARCHAR(20) NOT NULL DEFAULT 'autre' AFTER visibility");
    }
    // activities : colonne liste_attente_active
    if (!$pdo->query("SHOW COLUMNS FROM activities LIKE 'liste_attente_active'")->fetch()) {
        $pdo->exec("ALTER TABLE activities ADD COLUMN liste_attente_active TINYINT(1) NOT NULL DEFAULT 0");
    }
    // users : colonne note_moyenne
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'note_moyenne'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN note_moyenne FLOAT DEFAULT NULL");
    }

    // Tables créées à la volée — garanties dès le démarrage
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(150) NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        subject VARCHAR(200) DEFAULT '',
        message TEXT NOT NULL,
        sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // activities : colonne photo
    if (!$pdo->query("SHOW COLUMNS FROM activities LIKE 'photo'")->fetch()) {
        $pdo->exec("ALTER TABLE activities ADD COLUMN photo VARCHAR(255) DEFAULT NULL AFTER description");
    }

    // Tables sociales (créées à la volée si absentes)
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        idnotifications INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT(11) NOT NULL,
        activity_id INT(11) DEFAULT NULL,
        type        VARCHAR(45)  DEFAULT NULL,
        title       VARCHAR(150) DEFAULT NULL,
        content     VARCHAR(500) DEFAULT NULL,
        is_read     TINYINT(1)   DEFAULT 0,
        created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
        KEY (user_id),
        CONSTRAINT notif_user_fk  FOREIGN KEY (user_id)      REFERENCES users (idusers) ON DELETE CASCADE,
        CONSTRAINT notif_activ_fk FOREIGN KEY (activity_id)  REFERENCES activities (idactivities) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS followers (
        follower_id  INT(11) NOT NULL,
        following_id INT(11) NOT NULL,
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (follower_id, following_id),
        KEY (following_id),
        CONSTRAINT fol_follower_fk  FOREIGN KEY (follower_id)  REFERENCES users (idusers) ON DELETE CASCADE,
        CONSTRAINT fol_following_fk FOREIGN KEY (following_id) REFERENCES users (idusers) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // users : colonne email_verified
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER email");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS email_verifications (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        token      VARCHAR(64) NOT NULL UNIQUE,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT ev_user_fk FOREIGN KEY (user_id) REFERENCES users (idusers) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        sender_id   INT NOT NULL,
        receiver_id INT NOT NULL,
        content     TEXT NOT NULL,
        is_read     TINYINT(1) NOT NULL DEFAULT 0,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY (sender_id),
        KEY (receiver_id),
        KEY (created_at),
        CONSTRAINT msg_sender_fk   FOREIGN KEY (sender_id)   REFERENCES users (idusers) ON DELETE CASCADE,
        CONSTRAINT msg_receiver_fk FOREIGN KEY (receiver_id) REFERENCES users (idusers) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_logs (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        admin_id    INT NOT NULL,
        action      VARCHAR(50)  NOT NULL,
        target_type ENUM('user','activity') NOT NULL,
        target_id   INT NOT NULL,
        details     VARCHAR(255) DEFAULT '',
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY (admin_id),
        KEY (created_at),
        CONSTRAINT al_admin_fk FOREIGN KEY (admin_id) REFERENCES users (idusers) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Nettoyage périodique des tokens expirés (environ 1 requête sur 50)
    if (mt_rand(1, 50) === 1) {
        $pdo->exec("DELETE FROM password_resets WHERE expires_at < NOW() OR used = 1");
        $pdo->exec("DELETE FROM email_verifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    }

} catch (PDOException $e) {
    die("Erreur connexion DB : " . $e->getMessage());
}