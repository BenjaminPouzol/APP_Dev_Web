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
} catch (PDOException $e) {
    die("Erreur connexion DB : " . $e->getMessage());
}