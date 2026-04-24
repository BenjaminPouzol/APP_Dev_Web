-- ============================================================
-- ShareTime — Migration v2
-- Ajouter les colonnes pseudo et date_naissance à users
-- + hasher le mot de passe admin
-- ============================================================

USE sharetime;

-- Ajout des nouvelles colonnes si elles n'existent pas encore
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS pseudo VARCHAR(100) AFTER prenom,
    ADD COLUMN IF NOT EXISTS date_naissance DATE AFTER ville;

-- Mise à jour du mot de passe admin (hash bcrypt de 'Admin1234!')
UPDATE users
SET mot_de_passe = '$2y$10$d2Omt3dYMXBHhrnP2A1H/.Y/BhPt09.TqOEG5H.0ILSAxZDAPP0l6'
WHERE email = 'admin@sharetime.fr';
