<?php
/**
 * app/models/User.php — Modèle des utilisateurs
 *
 * Encapsule toutes les requêtes SQL relatives aux comptes utilisateurs :
 * lecture, création, modification, gestion des follows, et administration.
 *
 * Instancié une seule fois dans public/index.php sous le nom $userModel.
 */
class User {

    /** @var PDO Connexion à la base de données, injectée par le constructeur */
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    /**
     * Recherche un utilisateur par son adresse email.
     * Utilisé à la connexion pour vérifier les identifiants.
     *
     * @return array|false  Ligne utilisateur complète, ou false si non trouvé
     */
    public function findByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    /**
     * Retourne un utilisateur par son ID.
     * Utilisé partout : page profil, vérification d'existence, rechargement de session.
     *
     * @return array|false
     */
    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE idusers = :id LIMIT 1");
        $stmt->execute(['id' => (int)$id]);
        return $stmt->fetch();
    }

    /**
     * Vérifie si une adresse email est déjà enregistrée.
     * Utilisé lors de l'inscription pour éviter les doublons.
     */
    public function emailExists($email) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        return $stmt->fetchColumn() > 0;
    }

    /** Retourne le nombre total d'utilisateurs (pour la pagination du panel admin). */
    public function countAllForAdmin() {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    /**
     * Retourne la liste des utilisateurs pour le panel admin, avec leurs statistiques.
     * Si $page et $per_page valent 0, retourne tous les utilisateurs sans pagination (panel owner).
     *
     * L'ORDER BY FIELD garantit un affichage cohérent : owner en tête, puis admins, puis membres.
     */
    public function getAllForAdmin($page = 0, $per_page = 0) {
        $sql = "
            SELECT u.*,
                   COALESCE(a_count.nb_activities, 0)    AS nb_activities,
                   COALESCE(r_count.nb_registrations, 0) AS nb_registrations
            FROM users u
            LEFT JOIN (
                -- Nombre d'activités créées par chaque utilisateur
                SELECT creator_id, COUNT(*) AS nb_activities
                FROM activities
                GROUP BY creator_id
            ) a_count ON a_count.creator_id = u.idusers
            LEFT JOIN (
                -- Nombre d'activités auxquelles l'utilisateur est inscrit (pas en attente)
                SELECT user_id, COUNT(*) AS nb_registrations
                FROM registrations WHERE status = 'inscrit'
                GROUP BY user_id
            ) r_count ON r_count.user_id = u.idusers
            ORDER BY FIELD(u.role,'owner','admin','utilisateur'), u.date_creation DESC
        ";
        if ($per_page > 0 && $page > 0) {
            $sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)(($page - 1) * $per_page);
        }
        return $this->pdo->query($sql)->fetchAll();
    }

    // ── Création / Modification ───────────────────────────────────────────────

    /**
     * Crée un nouveau compte utilisateur.
     * Le mot de passe est hashé ici avec password_hash (bcrypt par défaut).
     *
     * @param array $data  Données validées venant du handler d'inscription
     * @return int         ID du nouvel utilisateur
     */
    public function create($data): int {
        // password_hash utilise un sel aléatoire automatiquement et le stocke dans le hash
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
            'date_naissance' => $data['date_naissance'] ?: null,  // null si non renseignée
            'cgu'            => $data['cgu_acceptees'] ? 1 : 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Met à jour le profil d'un utilisateur (pseudo, ville, bio, photo optionnelle).
     * La photo n'est modifiée que si la clé 'photo_profil' est présente dans $data.
     */
    public function update($id, $data) {
        $set  = "pseudo = :pseudo, ville = :ville, bio = :bio";
        $bind = ['pseudo' => $data['pseudo'], 'ville' => $data['ville'], 'bio' => $data['bio'], 'id' => (int)$id];
        if (array_key_exists('photo_profil', $data)) {
            $set .= ", photo_profil = :photo_profil";
            $bind['photo_profil'] = $data['photo_profil'];
        }
        return $this->pdo->prepare("UPDATE users SET {$set} WHERE idusers = :id")->execute($bind);
    }

    // ── Follow ────────────────────────────────────────────────────────────────

    /**
     * Abonne $follower_id à $following_id.
     * INSERT IGNORE évite l'erreur si la relation existe déjà (doublon sur PRIMARY KEY).
     */
    public function follow(int $follower_id, int $following_id): bool {
        if ($follower_id === $following_id) return false;  // impossible de se suivre soi-même
        try {
            $this->pdo->prepare("INSERT IGNORE INTO followers (follower_id, following_id) VALUES (:f, :g)")
                ->execute(['f' => $follower_id, 'g' => $following_id]);
            return true;
        } catch (\Throwable $e) { return false; }
    }

    /** Désabonne $follower_id de $following_id. */
    public function unfollow(int $follower_id, int $following_id): bool {
        return $this->pdo->prepare("DELETE FROM followers WHERE follower_id = :f AND following_id = :g")
            ->execute(['f' => $follower_id, 'g' => $following_id]);
    }

    /** Retourne true si $follower_id suit $following_id. */
    public function isFollowing(int $follower_id, int $following_id): bool {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM followers WHERE follower_id = :f AND following_id = :g");
        $stmt->execute(['f' => $follower_id, 'g' => $following_id]);
        return $stmt->fetchColumn() > 0;
    }

    /** Retourne le nombre d'abonnés (personnes qui suivent $user_id). */
    public function getFollowerCount(int $user_id): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM followers WHERE following_id = :u");
        $stmt->execute(['u' => $user_id]);
        return (int)$stmt->fetchColumn();
    }

    /** Retourne le nombre d'abonnements (personnes que $user_id suit). */
    public function getFollowingCount(int $user_id): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM followers WHERE follower_id = :u");
        $stmt->execute(['u' => $user_id]);
        return (int)$stmt->fetchColumn();
    }

    // ── Administration ────────────────────────────────────────────────────────

    /**
     * Change le rôle d'un utilisateur ('utilisateur' ou 'admin').
     * La clause AND role != 'owner' empêche de dégrader un owner via cette méthode.
     * Le transfert de propriété passe par transferOwnership() qui est une transaction.
     */
    public function setRole($id, $role) {
        if (!in_array($role, ['utilisateur', 'admin'])) return false;  // whitelist : seuls ces deux rôles sont changeables ici
        $stmt = $this->pdo->prepare("
            UPDATE users SET role = :role
            WHERE idusers = :id AND role != 'owner'
        ");
        return $stmt->execute(['role' => $role, 'id' => (int)$id]);
    }

    /**
     * Transfère la propriété de l'application à un autre utilisateur.
     * Opération atomique (transaction) : l'ancien owner devient admin en même temps
     * que le nouveau devient owner. En cas d'échec, les deux restent inchangés.
     *
     * @return bool  false si la cible est déjà owner, n'existe pas, ou si $new == $current
     */
    public function transferOwnership(int $new_owner_id, int $current_owner_id): bool {
        if ($new_owner_id === $current_owner_id) return false;
        $target = $this->getById($new_owner_id);
        if (!$target || $target['role'] === 'owner') return false;  // ne peut pas transférer à un owner (impossible en pratique)

        $this->pdo->beginTransaction();
        try {
            // L'ancien propriétaire perd son rôle owner et devient admin
            $this->pdo->prepare("UPDATE users SET role = 'admin' WHERE idusers = :id")
                       ->execute(['id' => $current_owner_id]);
            // Le nouveau propriétaire est promu owner et débanni au cas où il était suspendu
            $this->pdo->prepare("UPDATE users SET role = 'owner', is_banned = 0 WHERE idusers = :id")
                       ->execute(['id' => $new_owner_id]);
            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();  // annule les deux UPDATE si l'un d'eux échoue
            return false;
        }
    }

    /** Vérifie si un owner existe déjà en base (utile à l'initialisation de l'application). */
    public function hasOwner(): bool {
        return (bool) $this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'owner'")->fetchColumn();
    }

    /**
     * Active ou désactive la suspension d'un compte.
     * La clause AND role != 'owner' empêche de suspendre le propriétaire.
     *
     * @param int  $id      ID de l'utilisateur
     * @param bool $banned  true = suspendre, false = réactiver
     */
    public function setBanned($id, $banned) {
        $stmt = $this->pdo->prepare("
            UPDATE users SET is_banned = :banned
            WHERE idusers = :id AND role != 'owner'
        ");
        return $stmt->execute(['banned' => $banned ? 1 : 0, 'id' => (int)$id]);
    }

    /**
     * Supprime un compte et toutes ses données en cascade, dans une transaction.
     *
     * Ordre obligatoire des suppressions (contraintes FK) :
     *   1. Données liées aux activités créées (comments, ratings, registrations sur ces activités)
     *   2. Les activités elles-mêmes
     *   3. Participations de l'utilisateur (registrations, comments, ratings en tant que membre)
     *   4. Le compte utilisateur (followers, messages, notifications sont en ON DELETE CASCADE)
     *
     * @return bool  true si le compte a bien été supprimé (rowCount > 0)
     */
    public function delete($id): bool {
        $id = (int)$id;
        $this->pdo->beginTransaction();
        try {
            // 1. Supprimer les données liées aux activités que cet utilisateur a créées
            //    (sous-requête pour cibler uniquement ses activités)
            $this->pdo->prepare("
                DELETE FROM comments WHERE activity_id IN
                (SELECT idactivities FROM activities WHERE creator_id = :id)
            ")->execute(['id' => $id]);
            $this->pdo->prepare("
                DELETE FROM ratings WHERE activity_id IN
                (SELECT idactivities FROM activities WHERE creator_id = :id)
            ")->execute(['id' => $id]);
            $this->pdo->prepare("
                DELETE FROM registrations WHERE activity_id IN
                (SELECT idactivities FROM activities WHERE creator_id = :id)
            ")->execute(['id' => $id]);

            // 2. Supprimer les activités créées par cet utilisateur
            $this->pdo->prepare("DELETE FROM activities WHERE creator_id = :id")
                ->execute(['id' => $id]);

            // 3. Supprimer les données de participation de cet utilisateur en tant que membre
            $this->pdo->prepare("DELETE FROM registrations WHERE user_id = :id")->execute(['id' => $id]);
            $this->pdo->prepare("DELETE FROM comments WHERE user_id = :id")->execute(['id' => $id]);
            $this->pdo->prepare("DELETE FROM ratings WHERE notateur_id = :id")->execute(['id' => $id]);

            // 4. Supprimer le compte utilisateur
            //    followers, messages, notifications, email_verifications, admin_logs
            //    sont supprimés automatiquement par les contraintes ON DELETE CASCADE.
            //    La clause AND role != 'owner' protège contre la suppression accidentelle du propriétaire.
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE idusers = :id AND role != 'owner'");
            $stmt->execute(['id' => $id]);

            $this->pdo->commit();
            return $stmt->rowCount() > 0;  // false si aucune ligne supprimée (ex. tentative sur owner)
        } catch (\Throwable $e) {
            $this->pdo->rollBack();  // annule tout si une étape échoue
            throw $e;
        }
    }
}
