-- ============================================================
-- ShareTime — Migration v3
-- Ajout du rôle 'owner' et de la colonne is_banned
-- ============================================================

USE sharetime;

ALTER TABLE users
    MODIFY COLUMN role ENUM('utilisateur','admin','owner') NOT NULL DEFAULT 'utilisateur',
    ADD COLUMN IF NOT EXISTS is_banned TINYINT(1) NOT NULL DEFAULT 0;

-- Promouvoir l'admin en owner
UPDATE users SET role = 'owner' WHERE email = 'admin@sharetime.fr';
