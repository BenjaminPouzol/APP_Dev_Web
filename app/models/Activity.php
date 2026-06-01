<?php
/**
 * app/models/Activity.php — Modèle des activités
 *
 * Encapsule toutes les requêtes SQL relatives aux activités, aux inscriptions,
 * à la liste d'attente, aux commentaires et aux notes.
 *
 * Instancié une seule fois dans public/index.php sous le nom $activityModel.
 * Aucune logique métier ni HTML ici — uniquement des accès base de données.
 */
class Activity {

    /** @var PDO Connexion à la base de données, injectée par le constructeur */
    private $pdo; // propriété privée : seule cette classe peut accéder à la connexion

    public function __construct($pdo) { // constructeur appelé lors de new Activity($pdo)
        $this->pdo = $pdo; // stocke la connexion PDO pour l'utiliser dans toutes les méthodes
    }

    // ── Lecture publique ───────────────────────────────────────────────────────

    /**
     * Récupère une liste d'activités avec filtres et pagination optionnels.
     *
     * @param string   $city          Filtre ville (recherche partielle LIKE)
     * @param int|null $user_id       Si fourni, inclut aussi les activités privées de cet utilisateur
     * @param string   $category      Filtre catégorie exacte (ex. 'sport')
     * @param string   $status_filter Filtre statut exact (ex. 'active', 'terminee')
     * @param string   $title         Recherche dans le titre ET la description
     * @param int      $page          Numéro de page (0 = pas de pagination)
     * @param int      $per_page      Nombre de résultats par page (0 = tous)
     * @return array   Tableau d'activités avec nb_inscrits calculé
     */
    public function getAll($city = '', $user_id = null, $category = '', $status_filter = '', $title = '', $page = 0, $per_page = 0) { // méthode principale de lecture, tous les filtres sont optionnels
        $params = []; // tableau des paramètres PDO, rempli dynamiquement selon les filtres actifs

        // Visibilité : un utilisateur connecté peut voir ses propres activités privées ;
        // un visiteur anonyme ne voit que les activités publiques.
        if ($user_id) { // si un utilisateur est connecté ($user_id non nul)
            $visibility_clause = "(a.visibility = 'publique' OR a.creator_id = :user_id)"; // il voit le public ET ses propres activités privées
            $params['user_id'] = (int)$user_id; // ajoute le paramètre au tableau de liaison
        } else { // visiteur anonyme
            $visibility_clause = "a.visibility = 'publique'"; // restreint aux activités publiques uniquement
        }

        // LEFT JOIN sur une table dérivée : calcule le nombre d'inscrits pour toutes les
        // activités en une seule passe, évitant une sous-requête corrélée par ligne (N+1).
        $sql = "SELECT a.*, u.prenom, u.nom, u.pseudo,
                       COALESCE(r_count.nb_inscrits, 0) AS nb_inscrits
                FROM activities a
                JOIN users u ON u.idusers = a.creator_id
                LEFT JOIN (
                    SELECT activity_id, COUNT(*) AS nb_inscrits
                    FROM registrations WHERE status = 'inscrit'
                    GROUP BY activity_id
                ) r_count ON r_count.activity_id = a.idactivities
                WHERE {$visibility_clause}"; // la clause de visibilité est injectée ici (pas de paramètre utilisateur si anonyme)

        // Ajout dynamique des clauses WHERE selon les filtres actifs
        if ($city !== '') { // filtre par ville activé si la chaîne n'est pas vide
            $sql .= " AND a.city LIKE :city"; // comparaison partielle pour accepter des recherches incomplètes
            $params['city'] = '%' . $city . '%';  // % de chaque côté pour une recherche partielle
        }
        if ($category !== '') { // filtre par catégorie activé si une catégorie est sélectionnée
            $sql .= " AND a.category = :category"; // comparaison exacte : la catégorie doit correspondre parfaitement
            $params['category'] = $category; // valeur transmise telle quelle (validée en amont dans le handler)
        }
        if ($status_filter !== '') { // filtre par statut activé si un statut est choisi
            $sql .= " AND a.status = :status_filter"; // comparaison exacte sur le statut de l'activité
            $params['status_filter'] = $status_filter; // ex. 'active', 'terminee', 'annulee'
        }
        if ($title !== '') { // filtre par titre activé si un terme de recherche est saisi
            // Recherche dans le titre ET la description pour plus de pertinence
            $sql .= " AND (a.title LIKE :title OR a.description LIKE :title_desc)"; // OR pour chercher dans les deux champs
            $params['title']      = '%' . $title . '%'; // terme entouré de % pour une recherche partielle dans le titre
            $params['title_desc'] = '%' . $title . '%'; // même valeur mais paramètre distinct (PDO interdit les doublons)
        }

        $sql .= " ORDER BY a.start_time ASC";  // Activités les plus proches en premier

        // Pagination : LIMIT et OFFSET sont cast en int car PDO les traite comme chaînes
        // ce qui provoquerait une erreur SQL si on utilisait bindValue avec PDO::PARAM_INT.
        if ($per_page > 0 && $page > 0) { // pagination active uniquement si les deux valeurs sont positives
            $offset = ($page - 1) * $per_page; // page 1 → offset 0, page 2 → offset $per_page, etc.
            $sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset; // concaténation directe en int pour éviter l'injection tout en contournant la limitation PDO
        }

        $stmt = $this->pdo->prepare($sql); // prépare la requête finale avec tous les filtres assemblés
        $stmt->execute($params); // exécute en liant les paramètres collectés dynamiquement
        return $stmt->fetchAll(); // retourne toutes les lignes sous forme de tableaux associatifs
    }

    /**
     * Compte le nombre total d'activités correspondant aux mêmes filtres que getAll().
     * Utilisé pour calculer le nombre de pages de pagination.
     * Les paramètres sont identiques à getAll() sauf page/per_page (inutiles pour un COUNT).
     */
    public function countAll($city = '', $user_id = null, $category = '', $status_filter = '', $title = '') { // même logique de filtres que getAll() mais retourne uniquement un entier
        $params = []; // tableau de paramètres PDO, construit de la même façon que dans getAll()
        if ($user_id) { // applique la même clause de visibilité que getAll()
            $visibility_clause = "(a.visibility = 'publique' OR a.creator_id = :user_id)"; // l'utilisateur connecté voit aussi ses activités privées
            $params['user_id'] = (int)$user_id; // ajoute l'ID utilisateur aux paramètres
        } else {
            $visibility_clause = "a.visibility = 'publique'"; // visiteur anonyme : uniquement le public
        }
        // COUNT(*) ne retourne qu'un seul entier : pas besoin de JOIN pour nb_inscrits
        $sql = "SELECT COUNT(*) FROM activities a WHERE {$visibility_clause}"; // requête allégée : pas de JOIN, pas de SELECT des champs détaillés
        if ($city !== '') { // filtre ville identique à getAll()
            $sql .= " AND a.city LIKE :city"; // recherche partielle sur la ville
            $params['city'] = '%' . $city . '%'; // terme encadré de wildcards SQL
        }
        if ($category !== '') { // filtre catégorie identique à getAll()
            $sql .= " AND a.category = :category"; // correspondance exacte sur la catégorie
            $params['category'] = $category; // valeur brute de la catégorie
        }
        if ($status_filter !== '') { // filtre statut identique à getAll()
            $sql .= " AND a.status = :status_filter"; // correspondance exacte sur le statut
            $params['status_filter'] = $status_filter; // ex. 'active', 'annulee'
        }
        if ($title !== '') { // filtre titre/description identique à getAll()
            $sql .= " AND (a.title LIKE :title OR a.description LIKE :title_desc)"; // recherche dans le titre ou la description
            $params['title']      = '%' . $title . '%'; // wildcard autour du terme cherché
            $params['title_desc'] = '%' . $title . '%'; // paramètre distinct pour la description (PDO n'accepte pas les doublons)
        }
        $stmt = $this->pdo->prepare($sql); // prépare la requête COUNT avec les filtres actifs
        $stmt->execute($params); // exécute en liant tous les paramètres collectés
        return (int)$stmt->fetchColumn(); // lit le résultat du COUNT et le cast en entier
    }

    /**
     * Retourne une activité complète avec ses statistiques (inscrits, attente).
     * Utilisé sur la page de détail et lors de validations dans les handlers.
     *
     * @param int $id  ID de l'activité
     * @return array|false  Ligne de la base ou false si non trouvée
     */
    public function getById($id) { // récupère une activité précise avec toutes ses informations agrégées
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.prenom, u.nom, u.pseudo, u.note_moyenne AS creator_note,
                   COALESCE(r_stats.nb_inscrits, 0) AS nb_inscrits,
                   COALESCE(r_stats.nb_attente, 0)  AS nb_attente
            FROM activities a
            JOIN users u ON u.idusers = a.creator_id
            LEFT JOIN (
                SELECT activity_id,
                       SUM(status = 'inscrit')    AS nb_inscrits,  -- SUM de booléens = COUNT conditionnel
                       SUM(status = 'en_attente') AS nb_attente
                FROM registrations
                WHERE activity_id = :id2   -- filtre dans la table dérivée pour ne lire qu'une activité
                GROUP BY activity_id
            ) r_stats ON r_stats.activity_id = a.idactivities
            WHERE a.idactivities = :id
        "); // SUM(condition) compte les lignes où la condition est vraie (MySQL traite les booléens comme 0 ou 1)
        // :id et :id2 sont deux paramètres distincts pour la même valeur car PDO ne permet pas
        // de lier le même paramètre nommé deux fois dans une requête.
        $stmt->execute(['id' => (int)$id, 'id2' => (int)$id]); // passe l'ID deux fois avec des noms différents
        return $stmt->fetch(); // retourne la ligne de l'activité ou false si l'ID n'existe pas
    }

    /**
     * Retourne toutes les activités créées par un utilisateur donné (page profil).
     *
     * @param int $user_id  ID du créateur
     * @return array
     */
    public function getByCreator($user_id) { // liste les activités organisées par un utilisateur précis
        $stmt = $this->pdo->prepare("
            SELECT a.*,
                   COALESCE(r_count.nb_inscrits, 0) AS nb_inscrits
            FROM activities a
            LEFT JOIN (
                SELECT activity_id, COUNT(*) AS nb_inscrits
                FROM registrations WHERE status = 'inscrit'
                GROUP BY activity_id
            ) r_count ON r_count.activity_id = a.idactivities
            WHERE a.creator_id = :id
            ORDER BY a.start_time DESC  -- activités les plus récentes en premier sur le profil
        "); // LEFT JOIN pour ne pas exclure les activités sans inscrits (COALESCE remplace NULL par 0)
        $stmt->execute(['id' => (int)$user_id]); // filtre sur l'ID du créateur
        return $stmt->fetchAll(); // retourne toutes les activités de cet organisateur
    }

    /**
     * Retourne les activités auxquelles un utilisateur est inscrit ou en attente.
     * Affiché dans l'onglet "Mes inscriptions" du profil.
     *
     * @param int $user_id
     * @return array  Lignes activities + reg_status + registered_at
     */
    public function getUserRegistrations($user_id) { // récupère le carnet de participations d'un utilisateur
        $stmt = $this->pdo->prepare("
            SELECT a.*, r.status AS reg_status, r.registered_at
            FROM registrations r
            JOIN activities a ON a.idactivities = r.activity_id
            WHERE r.user_id = :id AND r.status IN ('inscrit', 'en_attente')
            ORDER BY a.start_time ASC
        "); // r.status AS reg_status : renommé pour ne pas écraser a.status (statut de l'activité)
        $stmt->execute(['id' => (int)$user_id]); // filtre sur l'ID de l'utilisateur connecté
        return $stmt->fetchAll(); // retourne la liste des participations triées par date de début
    }

    // ── Création ──────────────────────────────────────────────────────────────

    /**
     * Insère une nouvelle activité en base.
     *
     * @param array $data  Données validées (titre, description, dates, etc.)
     * @return int|false   ID de la nouvelle activité, ou false en cas d'erreur
     */
    public function create($data) { // crée une activité et retourne son nouvel ID auto-incrémenté
        $stmt = $this->pdo->prepare("
            INSERT INTO activities
            (title, description, photo, location, city, start_time, end_time, max_participants, visibility, category, liste_attente_active, status, creator_id, latitude, longitude, created_at)
            VALUES
            (:title, :description, :photo, :location, :city, :start_time, :end_time, :max_participants, :visibility, :category, :liste_attente_active, 'active', :creator_id, :latitude, :longitude, NOW())
        "); // le statut est 'active' par défaut et created_at est rempli par NOW() côté MySQL
        $ok = $stmt->execute([
            'title'               => $data['title'],               // titre de l'activité
            'description'         => $data['description'],         // description longue de l'activité
            'photo'               => $data['photo'] ?? null,       // photo optionnelle : null si aucune image uploadée
            'location'            => $data['location'],            // adresse précise du lieu
            'city'                => $data['city'],                // ville (utilisée pour les filtres de recherche)
            'start_time'          => $data['start_time'],          // date et heure de début (format DATETIME)
            'end_time'            => $data['end_time'],            // date et heure de fin (format DATETIME)
            'max_participants'    => $data['max_participants'],     // nombre maximum de places disponibles
            'visibility'          => $data['visibility'],          // 'publique' ou 'privee'
            'category'            => $data['category'],            // catégorie (ex. 'sport', 'culture')
            'liste_attente_active' => $data['liste_attente_active'] ? 1 : 0, // 1 = liste d'attente activée, 0 = non
            'creator_id'          => $data['creator_id'],          // ID de l'utilisateur qui crée l'activité
            'latitude'            => isset($data['latitude'])  && $data['latitude']  !== '' ? (float)$data['latitude']  : null, // coordonnée GPS latitude, null si non fournie
            'longitude'           => isset($data['longitude']) && $data['longitude'] !== '' ? (float)$data['longitude'] : null, // coordonnée GPS longitude, null si non fournie
        ]);
        return $ok ? (int)$this->pdo->lastInsertId() : false; // retourne l'ID généré si succès, false si l'INSERT a échoué
    }

    // ── Mise à jour ───────────────────────────────────────────────────────────

    /**
     * Met à jour une activité existante.
     * La photo n'est modifiée que si une nouvelle a été uploadée (présence de la clé 'photo' dans $data).
     *
     * @param int   $id    ID de l'activité à modifier
     * @param array $data  Nouvelles valeurs (+ creator_id pour vérification d'appartenance)
     * @return bool
     */
    public function update($id, $data) { // met à jour les champs d'une activité existante
        // Construction dynamique du SET pour n'ajouter photo que si une nouvelle image est fournie
        $photo_sql = array_key_exists('photo', $data) ? ", photo = :photo" : ""; // chaîne vide si pas de nouvelle photo
        $bind = [
            'title'               => $data['title'],               // nouveau titre
            'description'         => $data['description'],         // nouvelle description
            'location'            => $data['location'],            // nouveau lieu
            'city'                => $data['city'],                // nouvelle ville
            'start_time'          => $data['start_time'],          // nouvelle date/heure de début
            'end_time'            => $data['end_time'],            // nouvelle date/heure de fin
            'max_participants'    => (int)$data['max_participants'], // cast en entier pour sécuriser la valeur
            'visibility'          => $data['visibility'],          // nouvelle visibilité
            'category'            => $data['category'],            // nouvelle catégorie
            'liste_attente_active' => $data['liste_attente_active'] ? 1 : 0, // 1 ou 0 pour le booléen MySQL
            'latitude'            => isset($data['latitude'])  && $data['latitude']  !== '' ? (float)$data['latitude']  : null, // latitude GPS ou null
            'longitude'           => isset($data['longitude']) && $data['longitude'] !== '' ? (float)$data['longitude'] : null, // longitude GPS ou null
            'id'                  => (int)$id,                    // ID de l'activité à modifier
            'creator_id'          => (int)$data['creator_id'],    // vérifie que c'est bien le propriétaire qui modifie
        ];
        if (array_key_exists('photo', $data)) $bind['photo'] = $data['photo']; // ajoute la photo aux paramètres seulement si elle a changé
        return $this->pdo->prepare("
            UPDATE activities
            SET title = :title, description = :description, location = :location, city = :city,
                start_time = :start_time, end_time = :end_time, max_participants = :max_participants,
                visibility = :visibility, category = :category, liste_attente_active = :liste_attente_active,
                latitude = :latitude, longitude = :longitude{$photo_sql}
            WHERE idactivities = :id AND creator_id = :creator_id
        ")->execute($bind); // AND creator_id : sécurité supplémentaire, un utilisateur ne peut modifier que ses propres activités
    }

    /**
     * Retourne toutes les activités publiques actives ayant des coordonnées, pour la carte interactive.
     */
    public function getForMap(): array { // récupère uniquement les activités géolocalisées pour affichage sur la carte
        $stmt = $this->pdo->query("
            SELECT a.idactivities, a.title, a.location, a.city, a.category, a.start_time, a.latitude, a.longitude,
                   u.prenom, u.nom, u.pseudo
            FROM activities a
            JOIN users u ON u.idusers = a.creator_id
            WHERE a.status = 'active'
              AND a.visibility = 'publique'
              AND a.latitude IS NOT NULL
              AND a.longitude IS NOT NULL
            ORDER BY a.start_time ASC
        "); // filtre sur latitude/longitude IS NOT NULL : exclut les activités sans coordonnées GPS
        return $stmt->fetchAll(); // retourne uniquement les colonnes nécessaires à l'affichage des marqueurs
    }

    /**
     * Retourne la liste des user_id inscrits (status='inscrit') à une activité.
     * Utilisé pour envoyer des notifications à tous les inscrits lors d'une modification ou annulation.
     */
    public function getRegisteredUserIds(int $activity_id): array { // récupère uniquement les IDs pour minimiser les données transférées
        $stmt = $this->pdo->prepare("SELECT user_id FROM registrations WHERE activity_id = :a AND status = 'inscrit'"); // sélectionne uniquement les inscrits confirmés (pas les en_attente)
        $stmt->execute(['a' => $activity_id]); // filtre sur l'ID de l'activité
        return array_column($stmt->fetchAll(), 'user_id');  // extrait uniquement la colonne user_id
    }

    /**
     * Annule une activité (status → 'annulee') par son organisateur.
     * La clause AND creator_id garantit qu'un utilisateur ne peut pas annuler une activité qui ne lui appartient pas.
     */
    public function cancelByOrganizer($id, $creator_id) { // passage du statut à 'annulee', réservé à l'organisateur
        $stmt = $this->pdo->prepare("
            UPDATE activities SET status = 'annulee'
            WHERE idactivities = :id AND creator_id = :creator_id AND status = 'active'
        "); // AND status = 'active' : on ne peut annuler que ce qui est encore actif (pas déjà terminé ou annulé)
        return $stmt->execute(['id' => (int)$id, 'creator_id' => (int)$creator_id]); // retourne true si la mise à jour a affecté une ligne
    }

    // ── Inscriptions ──────────────────────────────────────────────────────────

    /**
     * Retourne le statut d'inscription d'un utilisateur pour une activité.
     *
     * @return string|null  'inscrit', 'en_attente', 'annule', ou null si pas de ligne
     */
    public function getRegistrationStatus($activity_id, $user_id) { // lit le statut de la ligne de registrations pour cet utilisateur/activité
        $stmt = $this->pdo->prepare("
            SELECT status FROM registrations
            WHERE activity_id = :a AND user_id = :u
        "); // sélectionne uniquement la colonne status pour alléger la réponse
        $stmt->execute(['a' => (int)$activity_id, 'u' => (int)$user_id]); // filtre sur la paire activité+utilisateur
        $row = $stmt->fetch(); // tente de récupérer la ligne
        return $row ? $row['status'] : null;  // null = jamais inscrit (pas de ligne en base)
    }

    /** Raccourci : retourne true si l'utilisateur est inscrit (status = 'inscrit', pas en attente). */
    public function isRegistered($activity_id, $user_id) { // compare le statut retourné par getRegistrationStatus() à 'inscrit'
        return $this->getRegistrationStatus($activity_id, $user_id) === 'inscrit'; // === : comparaison stricte (type + valeur)
    }

    /**
     * Inscrit un utilisateur à une activité.
     * ON DUPLICATE KEY UPDATE permet de réinscrire quelqu'un qui s'était désinscrit
     * sans créer de doublon (la contrainte UNIQUE sur activity_id+user_id existe en base).
     */
    public function register($activity_id, $user_id) { // crée ou réactive une inscription au statut 'inscrit'
        $stmt = $this->pdo->prepare("
            INSERT INTO registrations (activity_id, user_id, status)
            VALUES (:a, :u, 'inscrit')
            ON DUPLICATE KEY UPDATE status = 'inscrit', cancelled_at = NULL
        "); // ON DUPLICATE KEY UPDATE : si la ligne existe déjà, on la réactive au lieu de déclencher une erreur
        return $stmt->execute(['a' => (int)$activity_id, 'u' => (int)$user_id]); // retourne true si l'inscription a réussi
    }

    /**
     * Désinscrit un utilisateur (status → 'annule').
     * On conserve la ligne pour l'historique et pour que promoteFromWaitlist sache
     * qu'une place vient de se libérer.
     */
    public function unregister($activity_id, $user_id) { // marque l'inscription comme annulée sans supprimer la ligne
        $stmt = $this->pdo->prepare("
            UPDATE registrations SET status = 'annule', cancelled_at = NOW()
            WHERE activity_id = :a AND user_id = :u
        "); // cancelled_at = NOW() : horodatage de la désinscription pour l'historique
        return $stmt->execute(['a' => (int)$activity_id, 'u' => (int)$user_id]); // retourne true si la mise à jour a réussi
    }

    // ── Liste d'attente ───────────────────────────────────────────────────────

    /**
     * Place un utilisateur en liste d'attente.
     * Même mécanique que register() : ON DUPLICATE KEY pour gérer les réinscriptions.
     */
    public function registerWaitlist($activity_id, $user_id) { // crée ou réactive une ligne de registrations au statut 'en_attente'
        $stmt = $this->pdo->prepare("
            INSERT INTO registrations (activity_id, user_id, status)
            VALUES (:a, :u, 'en_attente')
            ON DUPLICATE KEY UPDATE status = 'en_attente', cancelled_at = NULL
        "); // ON DUPLICATE KEY UPDATE : évite l'erreur si une ligne annulée existe déjà pour cette paire
        return $stmt->execute(['a' => (int)$activity_id, 'u' => (int)$user_id]); // retourne true si la mise en attente a réussi
    }

    /** Retourne le nombre de personnes en attente (affiché sur la page de détail). */
    public function getWaitlistCount($activity_id) { // compte uniquement les lignes avec status='en_attente'
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM registrations WHERE activity_id = :a AND status = 'en_attente'
        "); // filtre sur l'activité ET le statut pour ne compter que la file d'attente
        $stmt->execute(['a' => (int)$activity_id]); // filtre sur l'ID de l'activité
        return (int)$stmt->fetchColumn(); // retourne le nombre de personnes en attente sous forme d'entier
    }

    /**
     * Retourne la position de l'utilisateur dans la liste d'attente (1 = premier).
     * Compte combien de personnes en attente se sont inscrites AVANT lui (registered_at <=).
     */
    public function getWaitlistPosition($activity_id, $user_id) { // calcule la position dans la file d'attente par comparaison de dates
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM registrations
            WHERE activity_id = :a AND status = 'en_attente'
            AND registered_at <= (
                SELECT registered_at FROM registrations WHERE activity_id = :a2 AND user_id = :u
            )
        "); // sous-requête : récupère la date d'inscription de l'utilisateur, puis compte ceux qui se sont inscrits avant ou en même temps
        $stmt->execute(['a' => (int)$activity_id, 'a2' => (int)$activity_id, 'u' => (int)$user_id]); // :a et :a2 sont le même ID car PDO interdit les paramètres nommés dupliqués
        return (int)$stmt->fetchColumn(); // retourne la position (1 = premier en liste d'attente)
    }

    /**
     * Fait passer la première personne en attente au statut 'inscrit'.
     * Appelé automatiquement quand un inscrit se désinscrit (handler se_desinscrire).
     * Retourne le user_id promu (pour lui envoyer une notification), ou null.
     */
    public function promoteFromWaitlist($activity_id) { // promotion FIFO : la personne la plus ancienne en attente est inscrite en premier
        // Récupère la personne en attente depuis le plus longtemps (ORDER BY registered_at ASC)
        $stmt = $this->pdo->prepare("
            SELECT user_id FROM registrations
            WHERE activity_id = :a AND status = 'en_attente'
            ORDER BY registered_at ASC LIMIT 1
        "); // ORDER BY registered_at ASC + LIMIT 1 : sélectionne le premier arrivé dans la file
        $stmt->execute(['a' => (int)$activity_id]); // filtre sur l'activité concernée
        $row = $stmt->fetch(); // récupère la première personne en attente (ou false si la liste est vide)
        if ($row) { // si quelqu'un est en attente
            // Passe cette personne en 'inscrit' et remet cancelled_at à NULL
            $this->pdo->prepare("
                UPDATE registrations SET status = 'inscrit', cancelled_at = NULL
                WHERE activity_id = :a AND user_id = :u
            ")->execute(['a' => (int)$activity_id, 'u' => $row['user_id']]); // promeut la personne de 'en_attente' à 'inscrit'
            return $row['user_id'];  // renvoyé pour pouvoir notifier l'utilisateur promu
        }
        return null;  // personne en attente
    }

    // ── Commentaires ──────────────────────────────────────────────────────────

    /**
     * Retourne tous les commentaires d'une activité avec les infos de l'auteur.
     * Triés du plus ancien au plus récent (ordre chronologique naturel).
     */
    public function getComments($activity_id) { // récupère la liste complète des commentaires pour affichage
        $stmt = $this->pdo->prepare("
            SELECT c.*, u.prenom, u.nom, u.pseudo
            FROM comments c
            JOIN users u ON u.idusers = c.user_id
            WHERE c.activity_id = :id
            ORDER BY c.created_at ASC
        "); // JOIN users pour afficher le nom de l'auteur à côté de chaque commentaire
        $stmt->execute(['id' => (int)$activity_id]); // filtre sur l'activité cible
        return $stmt->fetchAll(); // retourne tous les commentaires sous forme de tableau associatif
    }

    /** Insère un commentaire. La validation (contenu non vide, utilisateur connecté) est faite dans le handler. */
    public function addComment($activity_id, $user_id, $content) { // insère un nouveau commentaire lié à une activité et un utilisateur
        $stmt = $this->pdo->prepare("
            INSERT INTO comments (activity_id, user_id, content)
            VALUES (:a, :u, :content)
        "); // created_at est renseigné automatiquement par la valeur DEFAULT de la colonne en base
        return $stmt->execute(['a' => (int)$activity_id, 'u' => (int)$user_id, 'content' => $content]); // retourne true si l'insertion a réussi
    }

    /** Supprime un commentaire en vérifiant que c'est bien son auteur qui le fait. */
    public function deleteComment($comment_id, $user_id) { // suppression sécurisée : seul l'auteur du commentaire peut le supprimer
        // AND user_id empêche la suppression d'un commentaire appartenant à quelqu'un d'autre
        $stmt = $this->pdo->prepare("DELETE FROM comments WHERE idcomments = :id AND user_id = :u"); // double condition : ID du commentaire ET auteur
        return $stmt->execute(['id' => (int)$comment_id, 'u' => (int)$user_id]); // retourne true si une ligne a été supprimée
    }

    /** Supprime un commentaire sans vérification d'auteur (réservé aux admins). */
    public function deleteCommentAsAdmin($comment_id) { // suppression administrative sans contrôle d'appartenance
        $stmt = $this->pdo->prepare("DELETE FROM comments WHERE idcomments = :id"); // supprime uniquement sur l'ID du commentaire, sans restriction d'auteur
        return $stmt->execute(['id' => (int)$comment_id]); // retourne true si le commentaire existait et a été supprimé
    }

    // ── Notes / Avis ──────────────────────────────────────────────────────────

    /**
     * Vérifie si un utilisateur a déjà noté un organisateur pour une activité donnée.
     * Empêche de voter deux fois (un vote par activité, seuls les inscrits peuvent noter).
     */
    public function hasRated($notateur_id, $organizer_id, $activity_id) { // vérifie l'existence d'une note pour ce triplet notateur/organisateur/activité
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM ratings
            WHERE notateur_id = :n AND note_id = :u AND activity_id = :a
        "); // la combinaison des trois champs est unique : un utilisateur ne peut noter qu'une fois par activité
        $stmt->execute(['n' => (int)$notateur_id, 'u' => (int)$organizer_id, 'a' => (int)$activity_id]); // passe les trois IDs en paramètres
        return $stmt->fetchColumn() > 0; // true si une note existe déjà pour ce triplet
    }

    /**
     * Enregistre une note (1-5) et recalcule immédiatement la note moyenne de l'organisateur.
     * La mise à jour de note_moyenne est atomique avec l'insertion : si l'INSERT réussit,
     * la moyenne est toujours à jour.
     */
    public function rate($notateur_id, $organizer_id, $activity_id, $note) { // enregistre une note et met à jour la moyenne de l'organisateur
        if ($note < 1 || $note > 5) return false;  // validation de la plage (double-check côté modèle)
        $stmt = $this->pdo->prepare("
            INSERT INTO ratings (notateur_id, note_id, activity_id, note)
            VALUES (:n, :u, :a, :note)
        "); // insère la note avec les IDs du notateur, de l'organisateur et de l'activité
        $ok = $stmt->execute([
            'n'    => (int)$notateur_id,    // ID de l'utilisateur qui attribue la note
            'u'    => (int)$organizer_id,   // ID de l'organisateur noté
            'a'    => (int)$activity_id,    // ID de l'activité concernée
            'note' => (int)$note,           // valeur de la note entre 1 et 5
        ]);
        if ($ok) { // si l'insertion a réussi, on recalcule la moyenne
            // Recalcule la moyenne sur TOUTES les notes reçues par cet organisateur
            $this->pdo->prepare("
                UPDATE users SET note_moyenne = (
                    SELECT AVG(note) FROM ratings WHERE note_id = :u
                ) WHERE idusers = :u2
            ")->execute(['u' => (int)$organizer_id, 'u2' => (int)$organizer_id]); // :u et :u2 sont la même valeur (PDO interdit les doublons de paramètres nommés)
        }
        return $ok; // retourne true si la note a bien été enregistrée
    }

    // ── Administration ────────────────────────────────────────────────────────

    /** Retourne le nombre total d'activités (pour la pagination du panel admin). */
    public function countAllForAdmin() { // COUNT(*) simple sans filtre pour le panel admin
        return (int)$this->pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn(); // cast en int pour garantir un type numérique
    }

    /**
     * Retourne toutes les activités pour le panel admin avec leurs statistiques.
     * Si $page et $per_page valent 0, retourne tout sans pagination (panel superadmin).
     */
    public function getAllForAdmin($page = 0, $per_page = 0) { // liste complète des activités avec statistiques agrégées pour les admins
        // Trois LEFT JOINs sur tables dérivées au lieu de sous-requêtes corrélées :
        // les comptages sont calculés en une passe unique sur chaque table, pas une fois par ligne.
        $sql = "
            SELECT a.*, u.prenom, u.nom, u.pseudo,
                   COALESCE(r_stats.nb_inscrits, 0)  AS nb_inscrits,
                   COALESCE(r_stats.nb_attente, 0)   AS nb_attente,
                   COALESCE(c_count.nb_comments, 0)  AS nb_comments
            FROM activities a
            JOIN users u ON u.idusers = a.creator_id
            LEFT JOIN (
                SELECT activity_id,
                       SUM(status = 'inscrit')    AS nb_inscrits,
                       SUM(status = 'en_attente') AS nb_attente
                FROM registrations
                GROUP BY activity_id
            ) r_stats ON r_stats.activity_id = a.idactivities
            LEFT JOIN (
                SELECT activity_id, COUNT(*) AS nb_comments
                FROM comments
                GROUP BY activity_id
            ) c_count ON c_count.activity_id = a.idactivities
            ORDER BY a.created_at DESC
        "; // COALESCE remplace NULL par 0 quand une activité n'a ni inscrits, ni attente, ni commentaires
        if ($per_page > 0 && $page > 0) { // applique la pagination seulement si les deux paramètres sont positifs
            $sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)(($page - 1) * $per_page); // calcule l'offset à partir du numéro de page
        }
        return $this->pdo->query($sql)->fetchAll(); // exécute et retourne toutes les lignes en une fois
    }

    /**
     * Modifie le statut d'une activité (admin/owner uniquement).
     * Valeurs autorisées : 'active', 'annulee', 'terminee'.
     */
    public function setStatus($id, $status) { // met à jour le statut d'une activité depuis le panel admin
        if (!in_array($status, ['active', 'en_cours', 'annulee', 'terminee'])) return false;  // whitelist stricte
        $stmt = $this->pdo->prepare("UPDATE activities SET status = :status WHERE idactivities = :id"); // mise à jour simple d'une seule colonne
        return $stmt->execute(['status' => $status, 'id' => (int)$id]); // retourne true si la mise à jour a réussi
    }

    /**
     * Supprime une activité et toutes ses données associées (registrations, commentaires, notes).
     * L'ordre des DELETE est important : il faut supprimer les dépendances avant l'activité elle-même
     * pour respecter les contraintes de clé étrangère.
     */
    public function delete($id) { // suppression complète d'une activité et de toutes ses données liées
        $this->pdo->prepare("DELETE FROM registrations WHERE activity_id = :id")->execute(['id' => $id]); // supprime d'abord les inscriptions (clé étrangère vers activities)
        $this->pdo->prepare("DELETE FROM comments WHERE activity_id = :id")->execute(['id' => $id]); // supprime ensuite les commentaires (clé étrangère vers activities)
        $this->pdo->prepare("DELETE FROM ratings WHERE activity_id = :id")->execute(['id' => $id]); // supprime les notes associées à cette activité
        $stmt = $this->pdo->prepare("DELETE FROM activities WHERE idactivities = :id"); // supprime enfin l'activité elle-même (plus de dépendances FK)
        return $stmt->execute(['id' => (int)$id]); // retourne true si l'activité a bien été supprimée
    }
}
