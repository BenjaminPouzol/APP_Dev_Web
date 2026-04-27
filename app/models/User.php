<?php

class User {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ── Lecture ────────────────────────────────────────────

    public function findByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE idusers = :id LIMIT 1");
        $stmt->execute(['id' => (int)$id]);
        return $stmt->fetch();
    }

    public function emailExists($email) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        return $stmt->fetchColumn() > 0;
    }

    public function countAllForAdmin() {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    public function getAllForAdmin($page = 0, $per_page = 0) {
        $sql = "
            SELECT u.*,
                   (SELECT COUNT(*) FROM activities WHERE creator_id = u.idusers) AS nb_activities,
                   (SELECT COUNT(*) FROM registrations WHERE user_id = u.idusers AND status = 'inscrit') AS nb_registrations
            FROM users u
            ORDER BY FIELD(u.role,'owner','admin','utilisateur'), u.date_creation DESC
        ";
        if ($per_page > 0 && $page > 0) {
            $sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)(($page - 1) * $per_page);
        }
        return $this->pdo->query($sql)->fetchAll();
    }

    // ── Création / Modification ────────────────────────────

    public function create($data): int {
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("
            INSERT INTO users (prenom, nom, pseudo, email, mot_de_passe, ville, date_naissance, role, cgu_acceptees, cgu_version)
            VALUES (:prenom, :nom, :pseudo, :email, :password, :ville, :date_naissance, 'utilisateur', :cgu, 'v1.0')
        ");
        $stmt->execute([
            'prenom'         => $data['prenom'],
            'nom'            => $data['nom'],
            'pseudo'         => $data['pseudo'],
            'email'          => $data['email'],
            'password'       => $hash,
            'ville'          => $data['ville'],
            'date_naissance' => $data['date_naissance'] ?: null,
            'cgu'            => $data['cgu_acceptees'] ? 1 : 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update($id, $data) {
        $set  = "pseudo = :pseudo, ville = :ville, bio = :bio";
        $bind = ['pseudo' => $data['pseudo'], 'ville' => $data['ville'], 'bio' => $data['bio'], 'id' => (int)$id];
        if (array_key_exists('photo_profil', $data)) {
            $set .= ", photo_profil = :photo_profil";
            $bind['photo_profil'] = $data['photo_profil'];
        }
        return $this->pdo->prepare("UPDATE users SET {$set} WHERE idusers = :id")->execute($bind);
    }

    // ── Follow ─────────────────────────────────────────────

    public function follow(int $follower_id, int $following_id): bool {
        if ($follower_id === $following_id) return false;
        try {
            $this->pdo->prepare("INSERT IGNORE INTO followers (follower_id, following_id) VALUES (:f, :g)")
                ->execute(['f' => $follower_id, 'g' => $following_id]);
            return true;
        } catch (\Throwable $e) { return false; }
    }

    public function unfollow(int $follower_id, int $following_id): bool {
        return $this->pdo->prepare("DELETE FROM followers WHERE follower_id = :f AND following_id = :g")
            ->execute(['f' => $follower_id, 'g' => $following_id]);
    }

    public function isFollowing(int $follower_id, int $following_id): bool {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM followers WHERE follower_id = :f AND following_id = :g");
        $stmt->execute(['f' => $follower_id, 'g' => $following_id]);
        return $stmt->fetchColumn() > 0;
    }

    public function getFollowerCount(int $user_id): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM followers WHERE following_id = :u");
        $stmt->execute(['u' => $user_id]);
        return (int)$stmt->fetchColumn();
    }

    public function getFollowingCount(int $user_id): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM followers WHERE follower_id = :u");
        $stmt->execute(['u' => $user_id]);
        return (int)$stmt->fetchColumn();
    }

    // ── Administration ─────────────────────────────────────

    public function setRole($id, $role) {
        if (!in_array($role, ['utilisateur', 'admin'])) return false;
        // On ne peut pas changer le rôle d'un owner
        $stmt = $this->pdo->prepare("
            UPDATE users SET role = :role
            WHERE idusers = :id AND role != 'owner'
        ");
        return $stmt->execute(['role' => $role, 'id' => (int)$id]);
    }

    // Transfère la propriété : l'ancien owner devient admin, le nouveau devient owner.
    // Retourne false si la cible est déjà owner ou n'existe pas.
    public function transferOwnership(int $new_owner_id, int $current_owner_id): bool {
        if ($new_owner_id === $current_owner_id) return false;
        $target = $this->getById($new_owner_id);
        if (!$target || $target['role'] === 'owner') return false;

        $this->pdo->beginTransaction();
        try {
            // L'ancien propriétaire devient admin
            $this->pdo->prepare("UPDATE users SET role = 'admin' WHERE idusers = :id")
                       ->execute(['id' => $current_owner_id]);
            // Le nouveau utilisateur devient owner
            $this->pdo->prepare("UPDATE users SET role = 'owner', is_banned = 0 WHERE idusers = :id")
                       ->execute(['id' => $new_owner_id]);
            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function hasOwner(): bool {
        return (bool) $this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'owner'")->fetchColumn();
    }

    public function setBanned($id, $banned) {
        // On ne peut pas suspendre un owner
        $stmt = $this->pdo->prepare("
            UPDATE users SET is_banned = :banned
            WHERE idusers = :id AND role != 'owner'
        ");
        return $stmt->execute(['banned' => $banned ? 1 : 0, 'id' => (int)$id]);
    }

    public function delete($id) {
        // Nettoyage en cascade (les FK ne sont pas toutes ON DELETE CASCADE)
        $this->pdo->prepare("DELETE FROM registrations WHERE user_id = :id")->execute(['id' => $id]);
        $this->pdo->prepare("DELETE FROM comments WHERE user_id = :id")->execute(['id' => $id]);
        $this->pdo->prepare("
            DELETE FROM registrations
            WHERE activity_id IN (SELECT idactivities FROM activities WHERE creator_id = :id)
        ")->execute(['id' => $id]);
        $this->pdo->prepare("DELETE FROM activities WHERE creator_id = :id")->execute(['id' => $id]);
        // Jamais supprimer un owner
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE idusers = :id AND role != 'owner'");
        return $stmt->execute(['id' => (int)$id]);
    }
}
