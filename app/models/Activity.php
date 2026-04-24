<?php

class Activity {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ── Lecture publique ───────────────────────────────────

    public function getAll($city = '', $user_id = null, $category = '') {
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
        $sql .= " ORDER BY a.start_time ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.prenom, u.nom, u.pseudo,
                   (SELECT COUNT(*) FROM registrations r WHERE r.activity_id = a.idactivities AND r.status = 'inscrit') AS nb_inscrits
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
            WHERE r.user_id = :id AND r.status = 'inscrit'
            ORDER BY a.start_time ASC
        ");
        $stmt->execute(['id' => (int)$user_id]);
        return $stmt->fetchAll();
    }

    // ── Création ───────────────────────────────────────────

    public function create($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO activities
            (title, description, location, city, start_time, end_time, max_participants, visibility, category, status, creator_id, created_at)
            VALUES
            (:title, :description, :location, :city, :start_time, :end_time, :max_participants, :visibility, :category, 'active', :creator_id, NOW())
        ");
        return $stmt->execute([
            'title'            => $data['title'],
            'description'      => $data['description'],
            'location'         => $data['location'],
            'city'             => $data['city'],
            'start_time'       => $data['start_time'],
            'end_time'         => $data['end_time'],
            'max_participants' => $data['max_participants'],
            'visibility'       => $data['visibility'],
            'category'         => $data['category'],
            'creator_id'       => $data['creator_id'],
        ]);
    }

    // ── Inscriptions ───────────────────────────────────────

    public function isRegistered($activity_id, $user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM registrations
            WHERE activity_id = :a AND user_id = :u AND status = 'inscrit'
        ");
        $stmt->execute(['a' => (int)$activity_id, 'u' => (int)$user_id]);
        return $stmt->fetchColumn() > 0;
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

    // ── Administration ─────────────────────────────────────

    public function getAllForAdmin() {
        $stmt = $this->pdo->query("
            SELECT a.*, u.prenom, u.nom, u.pseudo,
                   (SELECT COUNT(*) FROM registrations r WHERE r.activity_id = a.idactivities AND r.status = 'inscrit') AS nb_inscrits
            FROM activities a
            JOIN users u ON u.idusers = a.creator_id
            ORDER BY a.created_at DESC
        ");
        return $stmt->fetchAll();
    }

    public function setStatus($id, $status) {
        if (!in_array($status, ['active', 'annulee', 'terminee'])) return false;
        $stmt = $this->pdo->prepare("UPDATE activities SET status = :status WHERE idactivities = :id");
        return $stmt->execute(['status' => $status, 'id' => (int)$id]);
    }

    public function delete($id) {
        $this->pdo->prepare("DELETE FROM registrations WHERE activity_id = :id")->execute(['id' => $id]);
        $this->pdo->prepare("DELETE FROM comments WHERE activity_id = :id")->execute(['id' => $id]);
        $stmt = $this->pdo->prepare("DELETE FROM activities WHERE idactivities = :id");
        return $stmt->execute(['id' => (int)$id]);
    }
}
