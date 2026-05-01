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
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
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
    public function getAll($city = '', $user_id = null, $category = '', $status_filter = '', $title = '', $page = 0, $per_page = 0) {
        $params = [];

        // Visibilité : un utilisateur connecté peut voir ses propres activités privées ;
        // un visiteur anonyme ne voit que les activités publiques.
        if ($user_id) {
            $visibility_clause = "(a.visibility = 'publique' OR a.creator_id = :user_id)";
            $params['user_id'] = (int)$user_id;
        } else {
            $visibility_clause = "a.visibility = 'publique'";
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
                WHERE {$visibility_clause}";

        // Ajout dynamique des clauses WHERE selon les filtres actifs
        if ($city !== '') {
            $sql .= " AND a.city LIKE :city";
            $params['city'] = '%' . $city . '%';  // % de chaque côté pour une recherche partielle
        }
        if ($category !== '') {
            $sql .= " AND a.category = :category";
            $params['category'] = $category;
        }
        if ($status_filter !== '') {
            $sql .= " AND a.status = :status_filter";
            $params['status_filter'] = $status_filter;
        }
        if ($title !== '') {
            // Recherche dans le titre ET la description pour plus de pertinence
            $sql .= " AND (a.title LIKE :title OR a.description LIKE :title_desc)";
            $params['title']      = '%' . $title . '%';
            $params['title_desc'] = '%' . $title . '%';
        }

        $sql .= " ORDER BY a.start_time ASC";  // Activités les plus proches en premier

        // Pagination : LIMIT et OFFSET sont cast en int car PDO les traite comme chaînes
        // ce qui provoquerait une erreur SQL si on utilisait bindValue avec PDO::PARAM_INT.
        if ($per_page > 0 && $page > 0) {
            $offset = ($page - 1) * $per_page;
            $sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Compte le nombre total d'activités correspondant aux mêmes filtres que getAll().
     * Utilisé pour calculer le nombre de pages de pagination.
     * Les paramètres sont identiques à getAll() sauf page/per_page (inutiles pour un COUNT).
     */
    public function countAll($city = '', $user_id = null, $category = '', $status_filter = '', $title = '') {
        $params = [];
        if ($user_id) {
            $visibility_clause = "(a.visibility = 'publique' OR a.creator_id = :user_id)";
            $params['user_id'] = (int)$user_id;
        } else {
            $visibility_clause = "a.visibility = 'publique'";
        }
        // COUNT(*) ne retourne qu'un seul entier : pas besoin de JOIN pour nb_inscrits
        $sql = "SELECT COUNT(*) FROM activities a WHERE {$visibility_clause}";
        if ($city !== '') {
            $sql .= " AND a.city LIKE :city";
            $params['city'] = '%' . $city . '%';
        }
        if ($category !== '') {
            $sql .= " AND a.category = :category";
            $params['category'] = $category;
        }
        if ($status_filter !== '') {
            $sql .= " AND a.status = :status_filter";
            $params['status_filter'] = $status_filter;
        }
        if ($title !== '') {
            $sql .= " AND (a.title LIKE :title OR a.description LIKE :title_desc)";
            $params['title']      = '%' . $title . '%';
            $params['title_desc'] = '%' . $title . '%';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Retourne une activité complète avec ses statistiques (inscrits, attente).
     * Utilisé sur la page de détail et lors de validations dans les handlers.
     *
     * @param int $id  ID de l'activité
     * @return array|false  Ligne de la base ou false si non trouvée
     */
    public function getById($id) {
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
        ");
        // :id et :id2 sont deux paramètres distincts pour la même valeur car PDO ne permet pas
        // de lier le même paramètre nommé deux fois dans une requête.
        $stmt->execute(['id' => (int)$id, 'id2' => (int)$id]);
        return $stmt->fetch();
    }

    /**
     * Retourne toutes les activités créées par un utilisateur donné (page profil).
     *
     * @param int $user_id  ID du créateur
     * @return array
     */
    public function getByCreator($user_id) {
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
        ");
        $stmt->execute(['id' => (int)$user_id]);
        return $stmt->fetchAll();
    }

    /**
     * Retourne les activités auxquelles un utilisateur est inscrit ou en attente.
     * Affiché dans l'onglet "Mes inscriptions" du profil.
     *
     * @param int $user_id
     * @return array  Lignes activities + reg_status + registered_at
     */
    public function getUserRegistrations($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, r.status AS reg_status, r.registered_at
            FROM registrations r
            JOIN activities a ON a.idactivities = r.activity_id
            WHERE r.user_id = :id AND r.status IN ('inscrit', 'en_attente')
            ORDER BY a.start_time ASC
        ");
        $stmt->execute(['id' => (int)$user_id]);
        return $stmt->fetchAll();
    }

    // ── Création ──────────────────────────────────────────────────────────────

    /**
     * Insère une nouvelle activité en base.
     *
     * @param array $data  Données validées (titre, description, dates, etc.)
     * @return int|false   ID de la nouvelle activité, ou false en cas d'erreur
     */
    public function create($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO activities
            (title, description, photo, location, city, start_time, end_time, max_participants, visibility, category, liste_attente_active, status, creator_id, created_at)
            VALUES
            (:title, :description, :photo, :location, :city, :start_time, :end_time, :max_participants, :visibility, :category, :liste_attente_active, 'active', :creator_id, NOW())
        ");
        $ok = $stmt->execute([
            'title'               => $data['title'],
            'description'         => $data['description'],
            'photo'               => $data['photo'] ?? null,        // null si pas d'image uploadée
            'location'            => $data['location'],
            'city'                => $data['city'],
            'start_time'          => $data['start_time'],
            'end_time'            => $data['end_time'],
            'max_participants'    => $data['max_participants'],
            'visibility'          => $data['visibility'],
            'category'            => $data['category'],
            'liste_attente_active' => $data['liste_attente_active'] ? 1 : 0,  // booléen → entier pour MySQL
            'creator_id'          => $data['creator_id'],
        ]);
        return $ok ? (int)$this->pdo->lastInsertId() : false;
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
    public function update($id, $data) {
        // Construction dynamique du SET pour n'ajouter photo que si une nouvelle image est fournie
        $photo_sql = array_key_exists('photo', $data) ? ", photo = :photo" : "";
        $bind = [
            'title'               => $data['title'],
            'description'         => $data['description'],
            'location'            => $data['location'],
            'city'                => $data['city'],
            'start_time'          => $data['start_time'],
            'end_time'            => $data['end_time'],
            'max_participants'    => (int)$data['max_participants'],
            'visibility'          => $data['visibility'],
            'category'            => $data['category'],
            'liste_attente_active' => $data['liste_attente_active'] ? 1 : 0,
            'id'                  => (int)$id,
            'creator_id'          => (int)$data['creator_id'],  // double sécurité : seul le créateur peut modifier
        ];
        if (array_key_exists('photo', $data)) $bind['photo'] = $data['photo'];
        return $this->pdo->prepare("
            UPDATE activities
            SET title = :title, description = :description, location = :location, city = :city,
                start_time = :start_time, end_time = :end_time, max_participants = :max_participants,
                visibility = :visibility, category = :category, liste_attente_active = :liste_attente_active{$photo_sql}
            WHERE idactivities = :id AND creator_id = :creator_id
        ")->execute($bind);
    }

    /**
     * Retourne la liste des user_id inscrits (status='inscrit') à une activité.
     * Utilisé pour envoyer des notifications à tous les inscrits lors d'une modification ou annulation.
     */
    public function getRegisteredUserIds(int $activity_id): array {
        $stmt = $this->pdo->prepare("SELECT user_id FROM registrations WHERE activity_id = :a AND status = 'inscrit'");
        $stmt->execute(['a' => $activity_id]);
        return array_column($stmt->fetchAll(), 'user_id');  // extrait uniquement la colonne user_id
    }

    /**
     * Annule une activité (status → 'annulee') par son organisateur.
     * La clause AND creator_id garantit qu'un utilisateur ne peut pas annuler une activité qui ne lui appartient pas.
     */
    public function cancelByOrganizer($id, $creator_id) {
        $stmt = $this->pdo->prepare("
            UPDATE activities SET status = 'annulee'
            WHERE idactivities = :id AND creator_id = :creator_id AND status = 'active'
        ");
        return $stmt->execute(['id' => (int)$id, 'creator_id' => (int)$creator_id]);
    }

    // ── Inscriptions ──────────────────────────────────────────────────────────

    /**
     * Retourne le statut d'inscription d'un utilisateur pour une activité.
     *
     * @return string|null  'inscrit', 'en_attente', 'annule', ou null si pas de ligne
     */
    public function getRegistrationStatus($activity_id, $user_id) {
        $stmt = $this->pdo->prepare("
            SELECT status FROM registrations
            WHERE activity_id = :a AND user_id = :u
        ");
        $stmt->execute(['a' => (int)$activity_id, 'u' => (int)$user_id]);
        $row = $stmt->fetch();
        return $row ? $row['status'] : null;  // null = jamais inscrit (pas de ligne en base)
    }

    /** Raccourci : retourne true si l'utilisateur est inscrit (status = 'inscrit', pas en attente). */
    public function isRegistered($activity_id, $user_id) {
        return $this->getRegistrationStatus($activity_id, $user_id) === 'inscrit';
    }

    /**
     * Inscrit un utilisateur à une activité.
     * ON DUPLICATE KEY UPDATE permet de réinscrire quelqu'un qui s'était désinscrit
     * sans créer de doublon (la contrainte UNIQUE sur activity_id+user_id existe en base).
     */
    public function register($activity_id, $user_id) {
        $stmt = $this->pdo->prepare("
            INSERT INTO registrations (activity_id, user_id, status)
            VALUES (:a, :u, 'inscrit')
            ON DUPLICATE KEY UPDATE status = 'inscrit', cancelled_at = NULL
        ");
        return $stmt->execute(['a' => (int)$activity_id, 'u' => (int)$user_id]);
    }

    /**
     * Désinscrit un utilisateur (status → 'annule').
     * On conserve la ligne pour l'historique et pour que promoteFromWaitlist sache
     * qu'une place vient de se libérer.
     */
    public function unregister($activity_id, $user_id) {
        $stmt = $this->pdo->prepare("
            UPDATE registrations SET status = 'annule', cancelled_at = NOW()
            WHERE activity_id = :a AND user_id = :u
        ");
        return $stmt->execute(['a' => (int)$activity_id, 'u' => (int)$user_id]);
    }

    // ── Liste d'attente ───────────────────────────────────────────────────────

    /**
     * Place un utilisateur en liste d'attente.
     * Même mécanique que register() : ON DUPLICATE KEY pour gérer les réinscriptions.
     */
    public function registerWaitlist($activity_id, $user_id) {
        $stmt = $this->pdo->prepare("
            INSERT INTO registrations (activity_id, user_id, status)
            VALUES (:a, :u, 'en_attente')
            ON DUPLICATE KEY UPDATE status = 'en_attente', cancelled_at = NULL
        ");
        return $stmt->execute(['a' => (int)$activity_id, 'u' => (int)$user_id]);
    }

    /** Retourne le nombre de personnes en attente (affiché sur la page de détail). */
    public function getWaitlistCount($activity_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM registrations WHERE activity_id = :a AND status = 'en_attente'
        ");
        $stmt->execute(['a' => (int)$activity_id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Retourne la position de l'utilisateur dans la liste d'attente (1 = premier).
     * Compte combien de personnes en attente se sont inscrites AVANT lui (registered_at <=).
     */
    public function getWaitlistPosition($activity_id, $user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM registrations
            WHERE activity_id = :a AND status = 'en_attente'
            AND registered_at <= (
                SELECT registered_at FROM registrations WHERE activity_id = :a2 AND user_id = :u
            )
        ");
        $stmt->execute(['a' => (int)$activity_id, 'a2' => (int)$activity_id, 'u' => (int)$user_id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Fait passer la première personne en attente au statut 'inscrit'.
     * Appelé automatiquement quand un inscrit se désinscrit (handler se_desinscrire).
     * Retourne le user_id promu (pour lui envoyer une notification), ou null.
     */
    public function promoteFromWaitlist($activity_id) {
        // Récupère la personne en attente depuis le plus longtemps (ORDER BY registered_at ASC)
        $stmt = $this->pdo->prepare("
            SELECT user_id FROM registrations
            WHERE activity_id = :a AND status = 'en_attente'
            ORDER BY registered_at ASC LIMIT 1
        ");
        $stmt->execute(['a' => (int)$activity_id]);
        $row = $stmt->fetch();
        if ($row) {
            // Passe cette personne en 'inscrit' et remet cancelled_at à NULL
            $this->pdo->prepare("
                UPDATE registrations SET status = 'inscrit', cancelled_at = NULL
                WHERE activity_id = :a AND user_id = :u
            ")->execute(['a' => (int)$activity_id, 'u' => $row['user_id']]);
            return $row['user_id'];  // renvoyé pour pouvoir notifier l'utilisateur promu
        }
        return null;  // personne en attente
    }

    // ── Commentaires ──────────────────────────────────────────────────────────

    /**
     * Retourne tous les commentaires d'une activité avec les infos de l'auteur.
     * Triés du plus ancien au plus récent (ordre chronologique naturel).
     */
    public function getComments($activity_id) {
        $stmt = $this->pdo->prepare("
            SELECT c.*, u.prenom, u.nom, u.pseudo
            FROM comments c
            JOIN users u ON u.idusers = c.user_id
            WHERE c.activity_id = :id
            ORDER BY c.created_at ASC
        ");
        $stmt->execute(['id' => (int)$activity_id]);
        return $stmt->fetchAll();
    }

    /** Insère un commentaire. La validation (contenu non vide, utilisateur connecté) est faite dans le handler. */
    public function addComment($activity_id, $user_id, $content) {
        $stmt = $this->pdo->prepare("
            INSERT INTO comments (activity_id, user_id, content)
            VALUES (:a, :u, :content)
        ");
        return $stmt->execute(['a' => (int)$activity_id, 'u' => (int)$user_id, 'content' => $content]);
    }

    /** Supprime un commentaire en vérifiant que c'est bien son auteur qui le fait. */
    public function deleteComment($comment_id, $user_id) {
        // AND user_id empêche la suppression d'un commentaire appartenant à quelqu'un d'autre
        $stmt = $this->pdo->prepare("DELETE FROM comments WHERE idcomments = :id AND user_id = :u");
        return $stmt->execute(['id' => (int)$comment_id, 'u' => (int)$user_id]);
    }

    /** Supprime un commentaire sans vérification d'auteur (réservé aux admins). */
    public function deleteCommentAsAdmin($comment_id) {
        $stmt = $this->pdo->prepare("DELETE FROM comments WHERE idcomments = :id");
        return $stmt->execute(['id' => (int)$comment_id]);
    }

    // ── Notes / Avis ──────────────────────────────────────────────────────────

    /**
     * Vérifie si un utilisateur a déjà noté un organisateur pour une activité donnée.
     * Empêche de voter deux fois (un vote par activité, seuls les inscrits peuvent noter).
     */
    public function hasRated($notateur_id, $organizer_id, $activity_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM ratings
            WHERE notateur_id = :n AND note_id = :u AND activity_id = :a
        ");
        $stmt->execute(['n' => (int)$notateur_id, 'u' => (int)$organizer_id, 'a' => (int)$activity_id]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Enregistre une note (1-5) et recalcule immédiatement la note moyenne de l'organisateur.
     * La mise à jour de note_moyenne est atomique avec l'insertion : si l'INSERT réussit,
     * la moyenne est toujours à jour.
     */
    public function rate($notateur_id, $organizer_id, $activity_id, $note) {
        if ($note < 1 || $note > 5) return false;  // validation de la plage (double-check côté modèle)
        $stmt = $this->pdo->prepare("
            INSERT INTO ratings (notateur_id, note_id, activity_id, note)
            VALUES (:n, :u, :a, :note)
        ");
        $ok = $stmt->execute([
            'n'    => (int)$notateur_id,
            'u'    => (int)$organizer_id,
            'a'    => (int)$activity_id,
            'note' => (int)$note,
        ]);
        if ($ok) {
            // Recalcule la moyenne sur TOUTES les notes reçues par cet organisateur
            $this->pdo->prepare("
                UPDATE users SET note_moyenne = (
                    SELECT AVG(note) FROM ratings WHERE note_id = :u
                ) WHERE idusers = :u2
            ")->execute(['u' => (int)$organizer_id, 'u2' => (int)$organizer_id]);
        }
        return $ok;
    }

    // ── Administration ────────────────────────────────────────────────────────

    /** Retourne le nombre total d'activités (pour la pagination du panel admin). */
    public function countAllForAdmin() {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn();
    }

    /**
     * Retourne toutes les activités pour le panel admin avec leurs statistiques.
     * Si $page et $per_page valent 0, retourne tout sans pagination (panel owner).
     */
    public function getAllForAdmin($page = 0, $per_page = 0) {
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
        ";
        if ($per_page > 0 && $page > 0) {
            $sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)(($page - 1) * $per_page);
        }
        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Modifie le statut d'une activité (admin/owner uniquement).
     * Valeurs autorisées : 'active', 'annulee', 'terminee'.
     */
    public function setStatus($id, $status) {
        if (!in_array($status, ['active', 'annulee', 'terminee'])) return false;  // whitelist stricte
        $stmt = $this->pdo->prepare("UPDATE activities SET status = :status WHERE idactivities = :id");
        return $stmt->execute(['status' => $status, 'id' => (int)$id]);
    }

    /**
     * Supprime une activité et toutes ses données associées (registrations, commentaires, notes).
     * L'ordre des DELETE est important : il faut supprimer les dépendances avant l'activité elle-même
     * pour respecter les contraintes de clé étrangère.
     */
    public function delete($id) {
        $this->pdo->prepare("DELETE FROM registrations WHERE activity_id = :id")->execute(['id' => $id]);
        $this->pdo->prepare("DELETE FROM comments WHERE activity_id = :id")->execute(['id' => $id]);
        $this->pdo->prepare("DELETE FROM ratings WHERE activity_id = :id")->execute(['id' => $id]);
        $stmt = $this->pdo->prepare("DELETE FROM activities WHERE idactivities = :id");
        return $stmt->execute(['id' => (int)$id]);
    }
}
