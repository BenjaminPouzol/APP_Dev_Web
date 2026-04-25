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

    // Nettoyage périodique des tokens expirés (environ 1 requête sur 50)
    if (mt_rand(1, 50) === 1) {
        $pdo->exec("DELETE FROM password_resets WHERE expires_at < NOW() OR used = 1");
    }

} catch (PDOException $e) {
    die("Erreur connexion DB : " . $e->getMessage());
}