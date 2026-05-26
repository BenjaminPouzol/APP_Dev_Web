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
    private $pdo; // propriété privée : seule cette classe peut accéder à la connexion

    public function __construct($pdo) { // constructeur appelé lors de new User($pdo)
        $this->pdo = $pdo; // stocke la connexion PDO pour l'utiliser dans toutes les méthodes
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    /**
     * Recherche un utilisateur par son adresse email.
     * Utilisé à la connexion pour vérifier les identifiants.
     *
     * @return array|false  Ligne utilisateur complète, ou false si non trouvé
     */
    public function findByEmail($email) { // recherche un utilisateur à partir de son email
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1"); // prépare la requête avec un paramètre nommé pour éviter les injections SQL
        $stmt->execute(['email' => $email]); // injecte la valeur de l'email dans la requête
        return $stmt->fetch(); // retourne la ligne trouvée, ou false si aucune correspondance
    }

    /**
     * Retourne un utilisateur par son ID.
     * Utilisé partout : page profil, vérification d'existence, rechargement de session.
     *
     * @return array|false
     */
    public function getById($id) { // récupère un utilisateur précis grâce à son identifiant numérique
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE idusers = :id LIMIT 1"); // prépare la requête paramétrée pour sécuriser l'entrée
        $stmt->execute(['id' => (int)$id]); // cast en entier pour garantir que l'ID est bien un nombre
        return $stmt->fetch(); // retourne le tableau associatif de l'utilisateur, ou false
    }

    /**
     * Vérifie si une adresse email est déjà enregistrée.
     * Utilisé lors de l'inscription pour éviter les doublons.
     */
    public function emailExists($email) { // retourne true si l'email est déjà présent en base
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email"); // compte le nombre de lignes correspondant à cet email
        $stmt->execute(['email' => $email]); // exécute la requête avec l'email fourni
        return $stmt->fetchColumn() > 0; // fetchColumn() lit la première colonne (le COUNT) ; > 0 signifie que l'email existe
    }

    /** Retourne le nombre total d'utilisateurs (pour la pagination du panel admin). */
    public function countAllForAdmin() { // exécute un COUNT(*) direct sans paramètre car aucun filtre n'est nécessaire
        return (int)$this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(); // cast en int pour garantir un type numérique
    }

    /**
     * Retourne la liste des utilisateurs pour le panel admin, avec leurs statistiques.
     * Si $page et $per_page valent 0, retourne tous les utilisateurs sans pagination (panel owner).
     *
     * L'ORDER BY FIELD garantit un affichage cohérent : owner en tête, puis admins, puis membres.
     */
    public function getAllForAdmin($page = 0, $per_page = 0) { // $page et $per_page à 0 = pas de pagination
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
        "; // COALESCE remplace NULL par 0 quand l'utilisateur n'a aucune activité ou inscription
        if ($per_page > 0 && $page > 0) { // si la pagination est activée, on ajoute LIMIT et OFFSET
            $sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)(($page - 1) * $per_page); // calcule l'offset : page 1 → offset 0, page 2 → offset $per_page, etc.
        }
        return $this->pdo->query($sql)->fetchAll(); // exécute et retourne toutes les lignes d'un coup
    }

    // ── Création / Modification ───────────────────────────────────────────────

    /**
     * Crée un nouveau compte utilisateur.
     * Le mot de passe est hashé ici avec password_hash (bcrypt par défaut).
     *
     * @param array $data  Données validées venant du handler d'inscription
     * @return int         ID du nouvel utilisateur
     */
    public function create($data): int { // retourne toujours un entier : l'ID auto-incrémenté du nouvel enregistrement
        // password_hash utilise un sel aléatoire automatiquement et le stocke dans le hash
        $hash = password_hash($data['password'], PASSWORD_DEFAULT); // hash le mot de passe en clair avec bcrypt (irréversible)
        $stmt = $this->pdo->prepare("
            INSERT INTO users (prenom, nom, pseudo, email, mot_de_passe, ville, date_naissance, role, cgu_acceptees, cgu_version)
            VALUES (:prenom, :nom, :pseudo, :email, :password, :ville, :date_naissance, 'utilisateur', :cgu, 'v1.0')
        "); // insère un nouvel utilisateur avec le rôle 'utilisateur' par défaut
        $stmt->execute([
            'prenom'         => $data['prenom'],         // prénom saisi dans le formulaire
            'nom'            => $data['nom'],             // nom de famille
            'pseudo'         => $data['pseudo'],          // pseudo public affiché sur le site
            'email'          => $data['email'],           // adresse email (identifiant de connexion)
            'password'       => $hash,                    // hash bcrypt du mot de passe
            'ville'          => $data['ville'],           // ville de résidence
            'date_naissance' => $data['date_naissance'] ?: null,  // null si non renseignée
            'cgu'            => $data['cgu_acceptees'] ? 1 : 0,   // stocke 1 si les CGU ont été acceptées, 0 sinon
        ]);
        return (int)$this->pdo->lastInsertId(); // retourne l'ID généré automatiquement par MySQL pour la nouvelle ligne
    }

    /**
     * Met à jour le profil d'un utilisateur (pseudo, ville, bio, photo optionnelle).
     * La photo n'est modifiée que si la clé 'photo_profil' est présente dans $data.
     */
    public function update($id, $data) { // met à jour uniquement les champs du profil modifiables par l'utilisateur
        $set  = "pseudo = :pseudo, ville = :ville, bio = :bio"; // clause SET de base, toujours présente
        $bind = ['pseudo' => $data['pseudo'], 'ville' => $data['ville'], 'bio' => $data['bio'], 'id' => (int)$id]; // tableau des valeurs à lier à la requête
        if (array_key_exists('photo_profil', $data)) { // vérifie si une nouvelle photo a été fournie
            $set .= ", photo_profil = :photo_profil"; // ajoute la mise à jour de la photo dans le SET
            $bind['photo_profil'] = $data['photo_profil']; // ajoute le nom du fichier photo dans les paramètres
        }
        return $this->pdo->prepare("UPDATE users SET {$set} WHERE idusers = :id")->execute($bind); // exécute la requête UPDATE construite dynamiquement
    }

    // ── Follow ────────────────────────────────────────────────────────────────

    /**
     * Abonne $follower_id à $following_id.
     * INSERT IGNORE évite l'erreur si la relation existe déjà (doublon sur PRIMARY KEY).
     */
    public function follow(int $follower_id, int $following_id): bool { // crée une relation d'abonnement entre deux utilisateurs
        if ($follower_id === $following_id) return false;  // impossible de se suivre soi-même
        try {
            $this->pdo->prepare("INSERT IGNORE INTO followers (follower_id, following_id) VALUES (:f, :g)")
                ->execute(['f' => $follower_id, 'g' => $following_id]); // INSERT IGNORE ne déclenche pas d'erreur si la ligne existe déjà
            return true; // retourne true si l'abonnement a été créé ou existait déjà
        } catch (\Throwable $e) { return false; } // retourne false si une erreur inattendue survient
    }

    /** Désabonne $follower_id de $following_id. */
    public function unfollow(int $follower_id, int $following_id): bool { // supprime la relation d'abonnement entre deux utilisateurs
        return $this->pdo->prepare("DELETE FROM followers WHERE follower_id = :f AND following_id = :g")
            ->execute(['f' => $follower_id, 'g' => $following_id]); // retourne true si la suppression a réussi
    }

    /** Retourne true si $follower_id suit $following_id. */
    public function isFollowing(int $follower_id, int $following_id): bool { // vérifie l'existence de la relation dans la table followers
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM followers WHERE follower_id = :f AND following_id = :g"); // compte les lignes correspondant à cette paire d'utilisateurs
        $stmt->execute(['f' => $follower_id, 'g' => $following_id]); // passe les deux IDs en paramètres
        return $stmt->fetchColumn() > 0; // true si au moins une ligne trouvée (l'abonnement existe)
    }

    /** Retourne le nombre d'abonnés (personnes qui suivent $user_id). */
    public function getFollowerCount(int $user_id): int { // compte les lignes où following_id = $user_id (quelqu'un suit cet utilisateur)
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM followers WHERE following_id = :u"); // recherche toutes les personnes qui suivent $user_id
        $stmt->execute(['u' => $user_id]); // injecte l'ID de l'utilisateur
        return (int)$stmt->fetchColumn(); // retourne le nombre d'abonnés sous forme d'entier
    }

    /** Retourne le nombre d'abonnements (personnes que $user_id suit). */
    public function getFollowingCount(int $user_id): int { // compte les lignes où follower_id = $user_id (cet utilisateur suit quelqu'un)
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM followers WHERE follower_id = :u"); // recherche toutes les personnes que $user_id suit
        $stmt->execute(['u' => $user_id]); // injecte l'ID de l'utilisateur
        return (int)$stmt->fetchColumn(); // retourne le nombre d'abonnements sous forme d'entier
    }

    // ── Administration ────────────────────────────────────────────────────────

    /**
     * Change le rôle d'un utilisateur ('utilisateur' ou 'admin').
     * La clause AND role != 'owner' empêche de dégrader un owner via cette méthode.
     * Le transfert de propriété passe par transferOwnership() qui est une transaction.
     */
    public function setRole($id, $role) { // modifie le rôle d'un utilisateur (promotion ou rétrogradation)
        if (!in_array($role, ['utilisateur', 'admin'])) return false;  // whitelist : seuls ces deux rôles sont changeables ici
        $stmt = $this->pdo->prepare("
            UPDATE users SET role = :role
            WHERE idusers = :id AND role != 'owner'
        "); // AND role != 'owner' protège le propriétaire contre un changement accidentel de rôle
        return $stmt->execute(['role' => $role, 'id' => (int)$id]); // retourne true si la mise à jour a affecté au moins une ligne
    }

    /**
     * Transfère la propriété de l'application à un autre utilisateur.
     * Opération atomique (transaction) : l'ancien owner devient admin en même temps
     * que le nouveau devient owner. En cas d'échec, les deux restent inchangés.
     *
     * @return bool  false si la cible est déjà owner, n'existe pas, ou si $new == $current
     */
    public function transferOwnership(int $new_owner_id, int $current_owner_id): bool { // transfert de propriété irréversible, sécurisé par une transaction
        if ($new_owner_id === $current_owner_id) return false; // refuse le transfert vers soi-même
        $target = $this->getById($new_owner_id); // vérifie que le futur propriétaire existe bien en base
        if (!$target || $target['role'] === 'owner') return false;  // ne peut pas transférer à un owner (impossible en pratique)

        $this->pdo->beginTransaction(); // démarre une transaction : les deux UPDATE sont atomiques
        try {
            // L'ancien propriétaire perd son rôle owner et devient admin
            $this->pdo->prepare("UPDATE users SET role = 'admin' WHERE idusers = :id")
                       ->execute(['id' => $current_owner_id]); // rétrograde l'ancien propriétaire en admin
            // Le nouveau propriétaire est promu owner et débanni au cas où il était suspendu
            $this->pdo->prepare("UPDATE users SET role = 'owner', is_banned = 0 WHERE idusers = :id")
                       ->execute(['id' => $new_owner_id]); // promeut le nouvel owner et lève l'éventuelle suspension
            $this->pdo->commit(); // valide les deux modifications simultanément
            return true; // transfert réussi
        } catch (\Throwable $e) {
            $this->pdo->rollBack();  // annule les deux UPDATE si l'un d'eux échoue
            return false; // retourne false pour signaler l'échec au code appelant
        }
    }

    /** Vérifie si un owner existe déjà en base (utile à l'initialisation de l'application). */
    public function hasOwner(): bool { // retourne true dès qu'un utilisateur avec role='owner' est trouvé
        return (bool) $this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'owner'")->fetchColumn(); // cast en bool : 0 → false (pas d'owner), ≥1 → true
    }

    /**
     * Active ou désactive la suspension d'un compte.
     * La clause AND role != 'owner' empêche de suspendre le propriétaire.
     *
     * @param int  $id      ID de l'utilisateur
     * @param bool $banned  true = suspendre, false = réactiver
     */
    public function setBanned($id, $banned) { // met à jour le drapeau is_banned pour activer ou lever une suspension
        $stmt = $this->pdo->prepare("
            UPDATE users SET is_banned = :banned
            WHERE idusers = :id AND role != 'owner'
        "); // AND role != 'owner' : impossible de bannir le propriétaire de l'application
        return $stmt->execute(['banned' => $banned ? 1 : 0, 'id' => (int)$id]); // stocke 1 pour banni, 0 pour actif
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
    public function delete($id): bool { // suppression complète et irréversible d'un compte utilisateur
        $id = (int)$id; // cast en entier une seule fois pour toutes les requêtes suivantes
        $this->pdo->beginTransaction(); // toutes les suppressions seront annulées si l'une échoue
        try {
            // 1. Supprimer les données liées aux activités que cet utilisateur a créées
            //    (sous-requête pour cibler uniquement ses activités)
            $this->pdo->prepare("
                DELETE FROM comments WHERE activity_id IN
                (SELECT idactivities FROM activities WHERE creator_id = :id)
            ")->execute(['id' => $id]); // supprime les commentaires laissés sur les activités de cet utilisateur
            $this->pdo->prepare("
                DELETE FROM ratings WHERE activity_id IN
                (SELECT idactivities FROM activities WHERE creator_id = :id)
            ")->execute(['id' => $id]); // supprime les notes attribuées aux activités de cet utilisateur
            $this->pdo->prepare("
                DELETE FROM registrations WHERE activity_id IN
                (SELECT idactivities FROM activities WHERE creator_id = :id)
            ")->execute(['id' => $id]); // supprime les inscriptions aux activités de cet utilisateur

            // 2. Supprimer les activités créées par cet utilisateur
            $this->pdo->prepare("DELETE FROM activities WHERE creator_id = :id")
                ->execute(['id' => $id]); // supprime toutes les activités dont il est l'organisateur

            // 3. Supprimer les données de participation de cet utilisateur en tant que membre
            $this->pdo->prepare("DELETE FROM registrations WHERE user_id = :id")->execute(['id' => $id]); // supprime ses inscriptions à d'autres activités
            $this->pdo->prepare("DELETE FROM comments WHERE user_id = :id")->execute(['id' => $id]); // supprime les commentaires qu'il a rédigés
            $this->pdo->prepare("DELETE FROM ratings WHERE notateur_id = :id")->execute(['id' => $id]); // supprime les notes qu'il a attribuées à d'autres organisateurs

            // 4. Supprimer le compte utilisateur
            //    followers, messages, notifications, email_verifications, admin_logs
            //    sont supprimés automatiquement par les contraintes ON DELETE CASCADE.
            //    La clause AND role != 'owner' protège contre la suppression accidentelle du propriétaire.
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE idusers = :id AND role != 'owner'"); // protection finale : l'owner ne peut pas être supprimé par cette méthode
            $stmt->execute(['id' => $id]); // exécute la suppression du compte utilisateur

            $this->pdo->commit(); // valide toutes les suppressions en une seule fois
            return $stmt->rowCount() > 0;  // false si aucune ligne supprimée (ex. tentative sur owner)
        } catch (\Throwable $e) {
            $this->pdo->rollBack();  // annule tout si une étape échoue
            throw $e; // propage l'exception pour que le code appelant puisse la gérer
        }
    }
}
