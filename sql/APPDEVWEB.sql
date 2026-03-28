-- ============================================================
-- ShareTime - Base de données finale complète
-- ============================================================

DROP DATABASE IF EXISTS sharetime;
CREATE DATABASE sharetime CHARACTER SET utf8mb4;
USE sharetime;

-- ================= USERS =================
CREATE TABLE users (
  idusers INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  mot_de_passe VARCHAR(255) NOT NULL,
  ville VARCHAR(100),
  bio TEXT,
  photo_profil VARCHAR(255),
  note_moyenne FLOAT DEFAULT 0,
  role ENUM('utilisateur','admin') NOT NULL DEFAULT 'utilisateur',
  cgu_acceptees TINYINT(1) NOT NULL DEFAULT 0,
  cgu_version VARCHAR(20),
  date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ================= CGU =================
CREATE TABLE cgu (
  idcgu INT AUTO_INCREMENT PRIMARY KEY,
  contenu TEXT NOT NULL,
  version VARCHAR(20),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ================= FAQ =================
CREATE TABLE faq (
  idfaq INT AUTO_INCREMENT PRIMARY KEY,
  question VARCHAR(255) NOT NULL,
  reponse TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ================= CATEGORIES =================
CREATE TABLE categories (
  idcategories INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(500),
  icone VARCHAR(100)
) ENGINE=InnoDB;

-- ================= ACTIVITIES =================
CREATE TABLE activities (
  idactivities INT AUTO_INCREMENT PRIMARY KEY,
  creator_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  location VARCHAR(255),
  city VARCHAR(100),
  start_time DATETIME NOT NULL,
  end_time DATETIME NOT NULL,
  max_participants INT NOT NULL,
  visibility ENUM('publique','privee') DEFAULT 'publique',
  status ENUM('active','annulee','terminee') DEFAULT 'active',
  liste_attente_active TINYINT(1) DEFAULT 0,
  rappel_actif TINYINT(1) DEFAULT 0,
  conditions TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (creator_id) REFERENCES users(idusers)
) ENGINE=InnoDB;

-- ================= ACTIVITY CATEGORY =================
CREATE TABLE activity_category (
  activity_id INT,
  category_id INT,
  PRIMARY KEY (activity_id, category_id),
  FOREIGN KEY (activity_id) REFERENCES activities(idactivities) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(idcategories) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ================= REGISTRATIONS =================
CREATE TABLE registrations (
  idregistration INT AUTO_INCREMENT PRIMARY KEY,
  activity_id INT NOT NULL,
  user_id INT NOT NULL,
  status ENUM('inscrit','en_attente','annule') DEFAULT 'inscrit',
  position_attente INT,
  registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  cancelled_at DATETIME,
  UNIQUE (activity_id, user_id),
  FOREIGN KEY (activity_id) REFERENCES activities(idactivities) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(idusers)
) ENGINE=InnoDB;

-- ================= COMMENTS =================
CREATE TABLE comments (
  idcomments INT AUTO_INCREMENT PRIMARY KEY,
  activity_id INT NOT NULL,
  user_id INT NOT NULL,
  content TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (activity_id) REFERENCES activities(idactivities) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(idusers)
) ENGINE=InnoDB;

-- ================= RATINGS =================
CREATE TABLE ratings (
  idratings INT AUTO_INCREMENT PRIMARY KEY,
  notateur_id INT NOT NULL,
  note_id INT NOT NULL,
  activity_id INT NOT NULL,
  note TINYINT NOT NULL CHECK (note BETWEEN 1 AND 5),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (notateur_id, note_id, activity_id),
  FOREIGN KEY (notateur_id) REFERENCES users(idusers),
  FOREIGN KEY (note_id) REFERENCES users(idusers),
  FOREIGN KEY (activity_id) REFERENCES activities(idactivities) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ================= REPORTS =================
CREATE TABLE reports (
  idreports INT AUTO_INCREMENT PRIMARY KEY,
  signaleur_id INT NOT NULL,
  signale_id INT NOT NULL,
  motif TEXT NOT NULL,
  status ENUM('en_attente','traite','rejete') DEFAULT 'en_attente',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (signaleur_id) REFERENCES users(idusers),
  FOREIGN KEY (signale_id) REFERENCES users(idusers)
) ENGINE=InnoDB;

-- ================= NOTIFICATIONS =================
CREATE TABLE notifications (
  idnotifications INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  activity_id INT,
  type VARCHAR(45),
  title VARCHAR(150),
  content VARCHAR(500),
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(idusers) ON DELETE CASCADE,
  FOREIGN KEY (activity_id) REFERENCES activities(idactivities) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ================= USER INTERESTS =================
CREATE TABLE user_interests (
  user_id INT,
  category_id INT,
  PRIMARY KEY (user_id, category_id),
  FOREIGN KEY (user_id) REFERENCES users(idusers) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(idcategories) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ================= FOLLOWERS =================
CREATE TABLE followers (
  follower_id INT,
  following_id INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (follower_id, following_id),
  FOREIGN KEY (follower_id) REFERENCES users(idusers) ON DELETE CASCADE,
  FOREIGN KEY (following_id) REFERENCES users(idusers) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ================= MESSAGES =================
CREATE TABLE messages (
  idmessage INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  content TEXT NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id) REFERENCES users(idusers) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(idusers) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ================= DATA =================
INSERT IGNORE INTO categories (name, description, icone) VALUES
('Sport', 'Activités sportives en groupe', 'sport'),
('Atelier', 'Ateliers créatifs', 'atelier'),
('Sortie', 'Sorties nature', 'sortie'),
('Club', 'Clubs réguliers', 'club'),
('Art', 'Activités artistiques', 'art');

-- ================= FAQ =================
INSERT INTO faq (question, reponse) VALUES
('Comment créer une activité ?', 'Cliquez sur créer une activité et remplissez le formulaire.'),
('Comment annuler une inscription ?', 'Allez dans votre activité et cliquez sur annuler.'),
('Comment signaler un utilisateur ?', 'Utilisez le bouton signaler sur son profil.');

-- ================= CGU =================
INSERT INTO cgu (contenu, version) VALUES
('En utilisant ShareTime, vous acceptez les règles de la plateforme.', 'v1.0');

-- ================= ADMIN =================
INSERT INTO users (nom, prenom, email, mot_de_passe, role, cgu_acceptees)
VALUES ('Admin', 'ShareTime', 'admin@sharetime.fr', 'Admin1234!', 'admin', 1);