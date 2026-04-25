-- ShareTime — Données de test
-- Mot de passe de tous les comptes : password123
-- Usage : mysql -u root sharetime < sql/seed.sql
--
-- ⚠️  Ce fichier est prévu pour le développement uniquement.
--     Ne jamais exécuter en production.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Remise à zéro (le seed est rejouable)
TRUNCATE TABLE `ratings`;
TRUNCATE TABLE `comments`;
TRUNCATE TABLE `registrations`;
TRUNCATE TABLE `activities`;
TRUNCATE TABLE `faq`;
TRUNCATE TABLE `users`;

-- ─────────────────────────────────────────────────────────────
--  UTILISATEURS
--  Mot de passe commun : password123
--  Hash bcrypt généré avec password_hash('password123', PASSWORD_DEFAULT)
-- ─────────────────────────────────────────────────────────────
INSERT INTO `users`
  (nom, prenom, pseudo, email, mot_de_passe, ville, bio, role, is_banned, cgu_acceptees, cgu_version, note_moyenne)
VALUES
  -- Propriétaire
  ('Dupont',   'Alice',   'alice',   'alice@test.local',   '$2y$10$.A/M6RXig.nyIqnFNIEgGe92jPmXmqUipqvd4j.cqXKCocRE/iqsa', 'Paris',   'Fondatrice de ShareTime.',              'owner',       0, 1, 'v1.0', 4.8),
  -- Admin
  ('Martin',   'Bob',     'bob',     'bob@test.local',     '$2y$10$.A/M6RXig.nyIqnFNIEgGe92jPmXmqUipqvd4j.cqXKCocRE/iqsa', 'Lyon',    'Modérateur de la plateforme.',          'admin',       0, 1, 'v1.0', 4.2),
  -- Utilisateurs
  ('Bernard',  'Claire',  'claire',  'claire@test.local',  '$2y$10$.A/M6RXig.nyIqnFNIEgGe92jPmXmqUipqvd4j.cqXKCocRE/iqsa', 'Nantes',  'Passionnée de randonnée et de yoga.',   'utilisateur', 0, 1, 'v1.0', 0),
  ('Leroy',    'David',   'david',   'david@test.local',   '$2y$10$.A/M6RXig.nyIqnFNIEgGe92jPmXmqUipqvd4j.cqXKCocRE/iqsa', 'Bordeaux','Amateur de cuisine et de voyages.',     'utilisateur', 0, 1, 'v1.0', 0),
  -- Utilisateur banni (pour tester la fonctionnalité)
  ('Simon',    'Eve',     'eve',     'eve@test.local',     '$2y$10$.A/M6RXig.nyIqnFNIEgGe92jPmXmqUipqvd4j.cqXKCocRE/iqsa', 'Lille',   NULL,                                    'utilisateur', 1, 1, 'v1.0', 0);

-- ─────────────────────────────────────────────────────────────
--  ACTIVITÉS
-- ─────────────────────────────────────────────────────────────
INSERT INTO `activities`
  (creator_id, title, description, location, city, start_time, end_time, max_participants, visibility, category, status, liste_attente_active)
VALUES
  -- Active, organisée par Alice (owner)
  (1, 'Randonnée en forêt de Fontainebleau',
      'Une belle randonnée de 15 km à travers la forêt. Prévoir de bonnes chaussures et de l''eau.',
      'Forêt de Fontainebleau', 'Fontainebleau',
      DATE_ADD(NOW(), INTERVAL 7 DAY),
      DATE_ADD(DATE_ADD(NOW(), INTERVAL 7 DAY), INTERVAL 5 HOUR),
      10, 'publique', 'sport', 'active', 1),

  -- Active, organisée par Bob (admin)
  (2, 'Atelier cuisine du monde',
      'Venez découvrir la cuisine thaïlandaise : cours collectif de 2h, tous les ingrédients fournis.',
      'Espace cuisine du Marché', 'Lyon',
      DATE_ADD(NOW(), INTERVAL 14 DAY),
      DATE_ADD(DATE_ADD(NOW(), INTERVAL 14 DAY), INTERVAL 2 HOUR),
      6, 'publique', 'cuisine', 'active', 0),

  -- Active, organisée par Claire — presque pleine
  (3, 'Séance de yoga en plein air',
      'Séance de yoga pour tous niveaux dans le parc. Apportez votre tapis.',
      'Parc de la Beaujoire', 'Nantes',
      DATE_ADD(NOW(), INTERVAL 3 DAY),
      DATE_ADD(DATE_ADD(NOW(), INTERVAL 3 DAY), INTERVAL 2 HOUR),
      4, 'publique', 'sport', 'active', 1),

  -- Terminée, organisée par Alice — pour tester les notes
  (1, 'Visite du Louvre',
      'Visite guidée des collections permanentes du Louvre, durée 3h.',
      'Musée du Louvre', 'Paris',
      DATE_SUB(NOW(), INTERVAL 10 DAY),
      DATE_ADD(DATE_SUB(NOW(), INTERVAL 10 DAY), INTERVAL 3 HOUR),
      15, 'publique', 'culture', 'terminee', 0),

  -- Annulée, organisée par David
  (4, 'Concert jazz annulé',
      'Soirée jazz en plein air — annulée en raison des conditions météo.',
      'Place des Arts', 'Bordeaux',
      DATE_SUB(NOW(), INTERVAL 2 DAY),
      DATE_ADD(DATE_SUB(NOW(), INTERVAL 2 DAY), INTERVAL 3 HOUR),
      50, 'publique', 'musique', 'annulee', 0);

-- ─────────────────────────────────────────────────────────────
--  INSCRIPTIONS
-- ─────────────────────────────────────────────────────────────
INSERT INTO `registrations` (activity_id, user_id, status) VALUES
  -- Randonnée (id=1) : Bob et Claire inscrits, David en attente
  (1, 2, 'inscrit'),
  (1, 3, 'inscrit'),
  (1, 4, 'en_attente'),
  -- Atelier cuisine (id=2) : Claire inscrite
  (2, 3, 'inscrit'),
  -- Yoga (id=3) : Bob, David inscrits
  (3, 2, 'inscrit'),
  (3, 4, 'inscrit'),
  -- Louvre terminé (id=4) : Bob et Claire inscrits (pour pouvoir noter)
  (4, 2, 'inscrit'),
  (4, 3, 'inscrit');

-- ─────────────────────────────────────────────────────────────
--  COMMENTAIRES
-- ─────────────────────────────────────────────────────────────
INSERT INTO `comments` (activity_id, user_id, content) VALUES
  (1, 2, 'Très belle initiative ! J''ai hâte d''y être.'),
  (1, 3, 'Est-ce qu''il y a un niveau minimum requis pour la randonnée ?'),
  (1, 1, 'Aucun niveau requis, c''est accessible à tous. On marche à allure modérée.'),
  (2, 3, 'Super atelier, j''adore la cuisine thaï !'),
  (4, 2, 'Excellente visite, Alice est une super guide. Je recommande !');

-- ─────────────────────────────────────────────────────────────
--  NOTES
-- ─────────────────────────────────────────────────────────────
INSERT INTO `ratings` (notateur_id, note_id, activity_id, note) VALUES
  -- Bob note Alice pour le Louvre (id=4)
  (2, 1, 4, 5),
  -- Claire note Alice pour le Louvre (id=4)
  (3, 1, 4, 4);

-- Recalcul de note_moyenne pour Alice
UPDATE `users`
SET note_moyenne = (SELECT AVG(note) FROM ratings WHERE note_id = 1)
WHERE idusers = 1;

-- ─────────────────────────────────────────────────────────────
--  FAQ
-- ─────────────────────────────────────────────────────────────
INSERT INTO `faq` (question, reponse) VALUES
  ('Comment créer une activité ?',
   'Connectez-vous puis cliquez sur "Créer une activité" dans la barre de navigation. Remplissez le formulaire et validez.'),
  ('Comment m''inscrire à une activité ?',
   'Rendez-vous sur la page de l''activité et cliquez sur "S''inscrire". Vous devez être connecté.'),
  ('Comment annuler mon inscription ?',
   'Allez sur la page de l''activité concernée et cliquez sur "Se désinscrire". Si une liste d''attente existe, la première personne en attente sera automatiquement promue.'),
  ('Comment noter un organisateur ?',
   'Une fois l''activité terminée, retournez sur sa page. Si vous étiez inscrit(e), un formulaire de notation apparaîtra.'),
  ('Comment signaler un problème ?',
   'Utilisez le formulaire de la page "Contact" pour nous écrire directement.');

SET FOREIGN_KEY_CHECKS = 1;
