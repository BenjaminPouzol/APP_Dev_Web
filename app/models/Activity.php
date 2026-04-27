<?php

class Activity {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ── Lecture publique ───────────────────────────────────

    public function getAll($city = '', $user_id = null, $category = '', $status_filter = '', $title = '', $page = 0, $per_page = 0) {
        $params = [];
        if ($user_id) {
            $visibility_clause = "(a.visibility = 'publique' OR a.creator_id = :user_id)";
            $params['user_id'] = (int)$user_id;
        } else {
            $visibility_clause = "a.visibility = 'publique'";
        }
        $sql = "SELECT a.*, u.prenom, u.nom, u.pseudo,
                       (SELECT COUNT(*) FROM registrations r WHERE r.activity_id = a.idactivities AND r.status = 'inscrit') AS nb_inscrits
                FROM activities a
                JOIN users u ON u.idusers = a.creator_id
                WHERE {$visibility_clause}";
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
        $sql .= " ORDER BY a.start_time ASC";
        if ($per_page > 0 && $page > 0) {
            $offset = ($page - 1) * $per_page;
            $sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countAll($city = '', $user_id = null, $category = '', $status_filter = '', $title = '') {
        $params = [];
        if ($user_id) {
            $visibility_clause = "(a.visibility = 'publique' OR a.creator_id = :user_id)";
            $params['user_id'] = (int)$user_id;
        } else {
            $visibility_clause = "a.visibility = 'publique'";
        }
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

    public function getById($id) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.prenom, u.nom, u.pseudo, u.note_moyenne AS creator_note,
                   (SELECT COUNT(*) FROM registrations r WHERE r.activity_id = a.idactivities AND r.status = 'inscrit') AS nb_inscrits,
                   (SELECT COUNT(*) FROM registrations r WHERE r.activity_id = a.idactivities AND r.status = 'en_attente') AS nb_attente
            FROM activities a
            JOIN users u ON u.idusers = a.creator_id
            WHERE a.idactivities = :id
        ");
        $stmt->execute(['id' => (int)$id]);
        return $stmt->fetch();
    }

    public function getByCreator($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT a.*,
                   (SELECT COUNT(*) FROM registrations r WHERE r.activity_id = a.idactivities AND r.status = 'inscrit') AS nb_inscrits
            FROM activities a
            WHERE a.creator_id = :id
            ORDER BY a.start_time DESC
        ");
        $stmt->execute(['id' => (int)$user_id]);
        return $stmt->fetchAll();
    }

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

    // ── Création ───────────────────────────────────────────

    public function create($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO activities
            (title, description, location, city, start_time, end_time, max_participants, visibility, category, liste_attente_active, status, creator_id, created_at)
            VALUES
            (:title, :description, :location, :city, :start_time, :end_time, :max_participants, :visibility, :category, :liste_attente_active, 'active', :creator_id, NOW())
        ");
        return $stmt->execute([
            'title'               => $data['title'],
            'description'         => $data['description'],
            'location'            => $data['location'],
            'city'                => $data['city'],
            'start_time'          => $data['start_time'],
            'end_time'            => $data['end_time'],
            'max_participants'    => $data['max_participants'],
            'visibility'          => $data['visibility'],
            'category'            => $data['category'],
            'liste_attente_active' => $data['liste_attente_active'] ? 1 : 0,
            'creator_id'          => $data['creator_id'],
        ]);
    }

    // ── Mise à jour ────────────────────────────────────────

    public function update($id, $data) {
        $stmt = $this->pdo->prepare("
            UPDATE activities
            SET title = :title, description = :description, location = :location, city = :city,
                start_time = :start_time, end_time = :end_time, max_participants = :max_participants,
                visibility = :visibility, category = :category, liste_attente_active = :liste_attente_active
            WHERE idactivities = :id AND creator_id = :creator_id
        ");
        return $stmt->execute([
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
            'creator_id'          => (int)$data['creator_id'],
        ]);
    }

    public function cancelByOrganizer($id, $creator_id) {
        $stmt = $this->pdo->prepare("
            UPDATE activities SET status = 'annulee'
            WHERE idactivities = :id AND creator_id = :creator_id AND status = 'active'
        ");
        return $stmt->execute(['id' => (int)$id, 'creator_id' => (int)$creator_id]);
    }

    // ── Inscriptions ───────────────────────────────────────

    public function getRegistrationStatus($activity_id, $user_id) {
        $stmt = $this->pdo->prepare("
            SELECT status FROM registrations
            WHERE activity_id = :a AND user_id = :u
        ");
        $stmt->execute(['a' => (int)$activity_id, 'u' => (int)$user_id]);
        $row = $stmt->fetch();
        return $row ? $row['status'] : null;
    }

    public function isRegistered($activity_id, $user_id) {
        return $this->getRegistrationStatus($activity_id, $user_id) === 'inscrit';
    }

    public function register($activity_id, $user_id) {
        $stmt = $this->pdo->prepare("
            INSERT INTO registrations (activity_id, user_id, status)
            VALUES (:a, :u, 'inscrit')
            ON DUPLICATE KEY UPDATE status = 'inscrit', cancelled_at = NULL
        ");
        return $stmt->execute(['a' => (int)$activity_id, 'u' => (int)$user_id]);
    }

    public function unregister($activity_id, $user_id) {
        $stmt = $this->pdo->prepare("
            UPDATE registrations SET status = 'annule', cancelled_at = NOW()
            WHERE activity_id = :a AND user_id = :u
        ");
        return $stmt->execute(['a' => (int)$activity_id, 'u' => (int)$user_id]);
    }

    // ── Liste d'attente ────────────────────────────────────

    public function registerWaitlist($activity_id, $user_id) {
        $stmt = $this->pdo->prepare("
            INSERT INTO registrations (activity_id, user_id, status)
            VALUES (:a, :u, 'en_attente')
            ON DUPLICATE KEY UPDATE status = 'en_attente', cancelled_at = NULL
        ");
        return $stmt->execute(['a' => (int)$activity_id, 'u' => (int)$user_id]);
    }

    public function getWaitlistCount($activity_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM registrations WHERE activity_id = :a AND status = 'en_attente'
        ");
        $stmt->execute(['a' => (int)$activity_id]);
        return (int)$stmt->fetchColumn();
    }

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

    public function promoteFromWaitlist($activity_id) {
        $stmt = $this->pdo->prepare("
            SELECT user_id FROM registrations
            WHERE activity_id = :a AND status = 'en_attente'
            ORDER BY registered_at ASC LIMIT 1
        ");
        $stmt->execute(['a' => (int)$activity_id]);
        $row = $stmt->fetch();
        if ($row) {
            $this->pdo->prepare("
                UPDATE registrations SET status = 'inscrit', cancelled_at = NULL
                WHERE activity_id = :a AND user_id = :u
            ")->execute(['a' => (int)$activity_id, 'u' => $row['user_id']]);
            return $row['user_id'];
        }
        return null;
    }

    // ── Commentaires ───────────────────────────────────────

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

    public function addComment($activity_id, $user_id, $content) {
        $stmt = $this->pdo->prepare("
            INSERT INTO comments (activity_id, user_id, content)
            VALUES (:a, :u, :content)
        ");
        return $stmt->execute(['a' => (int)$activity_id, 'u' => (int)$user_id, 'content' => $content]);
    }

    public function deleteComment($comment_id, $user_id) {
        $stmt = $this->pdo->prepare("DELETE FROM comments WHERE idcomments = :id AND user_id = :u");
        return $stmt->execute(['id' => (int)$comment_id, 'u' => (int)$user_id]);
    }

    public function deleteCommentAsAdmin($comment_id) {
        $stmt = $this->pdo->prepare("DELETE FROM comments WHERE idcomments = :id");
        return $stmt->execute(['id' => (int)$comment_id]);
    }

    // ── Notes / Avis ───────────────────────────────────────

    public function hasRated($notateur_id, $organizer_id, $activity_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM ratings
            WHERE notateur_id = :n AND note_id = :u AND activity_id = :a
        ");
        $stmt->execute(['n' => (int)$notateur_id, 'u' => (int)$organizer_id, 'a' => (int)$activity_id]);
        return $stmt->fetchColumn() > 0;
    }

    public function rate($notateur_id, $organizer_id, $activity_id, $note) {
        if ($note < 1 || $note > 5) return false;
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
            $this->pdo->prepare("
                UPDATE users SET note_moyenne = (
                    SELECT AVG(note) FROM ratings WHERE note_id = :u
                ) WHERE idusers = :u2
            ")->execute(['u' => (int)$organizer_id, 'u2' => (int)$organizer_id]);
        }
        return $ok;
    }

    // ── Administration ─────────────────────────────────────

    public function countAllForAdmin() {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn();
    }

    public function getAllForAdmin($page = 0, $per_page = 0) {
        $sql = "
            SELECT a.*, u.prenom, u.nom, u.pseudo,
                   (SELECT COUNT(*) FROM registrations r WHERE r.activity_id = a.idactivities AND r.status = 'inscrit') AS nb_inscrits,
                   (SELECT COUNT(*) FROM registrations r WHERE r.activity_id = a.idactivities AND r.status = 'en_attente') AS nb_attente,
                   (SELECT COUNT(*) FROM comments c WHERE c.activity_id = a.idactivities) AS nb_comments
            FROM activities a
            JOIN users u ON u.idusers = a.creator_id
            ORDER BY a.created_at DESC
        ";
        if ($per_page > 0 && $page > 0) {
            $sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)(($page - 1) * $per_page);
        }
        return $this->pdo->query($sql)->fetchAll();
    }

    public function setStatus($id, $status) {
        if (!in_array($status, ['active', 'annulee', 'terminee'])) return false;
        $stmt = $this->pdo->prepare("UPDATE activities SET status = :status WHERE idactivities = :id");
        return $stmt->execute(['status' => $status, 'id' => (int)$id]);
    }

    public function delete($id) {
        $this->pdo->prepare("DELETE FROM registrations WHERE activity_id = :id")->execute(['id' => $id]);
        $this->pdo->prepare("DELETE FROM comments WHERE activity_id = :id")->execute(['id' => $id]);
        $this->pdo->prepare("DELETE FROM ratings WHERE activity_id = :id")->execute(['id' => $id]);
        $stmt = $this->pdo->prepare("DELETE FROM activities WHERE idactivities = :id");
        return $stmt->execute(['id' => (int)$id]);
    }
}
