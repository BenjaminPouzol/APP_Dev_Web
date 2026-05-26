<?php
/**
 * app/helpers.php — Fonctions utilitaires globales
 *
 * Ce fichier est inclus par public/index.php juste après les modèles.
 * Toutes les fonctions définies ici sont donc disponibles dans les handlers,
 * les pages et les vues sans aucun import supplémentaire.
 *
 * Contenu :
 *   - Protection CSRF (génération et vérification du token)
 *   - Contrôle des rôles (is_admin, is_owner, require_*)
 *   - Création de notifications in-app
 *   - Upload et validation d'images
 *   - Génération de badges HTML pour les rôles
 *   - Barre de navigation admin
 *   - Journalisation des actions d'administration
 */

// ── CSRF ──────────────────────────────────────────────────────────────────────
// Le CSRF (Cross-Site Request Forgery) est une attaque où un site tiers envoie
// une requête POST à notre application au nom d'un utilisateur connecté.
// La protection consiste à inclure un token secret dans chaque formulaire ;
// sans ce token (ou avec un token différent), la requête est rejetée.

/**
 * Retourne le token CSRF de la session, en le créant s'il n'existe pas encore.
 * À insérer dans chaque formulaire POST : <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
 */
function csrf_token(): string { // Déclare la fonction qui génère ou retourne le token CSRF de la session courante
    if (empty($_SESSION['csrf_token'])) { // Vérifie si le token CSRF est absent ou vide dans la session
        // bin2hex(random_bytes(32)) génère 64 caractères hexadécimaux aléatoires
        // cryptographiquement sûrs — impossible à deviner pour un attaquant.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Génère 32 octets aléatoires sécurisés et les convertit en 64 caractères hexadécimaux
    }
    return $_SESSION['csrf_token']; // Retourne le token CSRF stocké en session
}

/**
 * Vérifie que le token CSRF du formulaire correspond à celui de la session.
 * À appeler en tête de chaque handler POST. Arrête le script avec HTTP 403 si invalide.
 */
function csrf_check(): void { // Déclare la fonction de vérification du token CSRF soumis dans le formulaire
    $token = $_POST['csrf_token'] ?? ''; // Récupère le token CSRF envoyé dans le formulaire (chaîne vide si absent)
    // hash_equals est résistant aux attaques par timing : il compare les deux chaînes
    // en temps constant, ce qui empêche de déduire la valeur du token par mesure du temps.
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) { // Compare le token de session avec celui du formulaire de façon sécurisée contre les attaques temporelles
        http_response_code(403); // Envoie le code HTTP 403 Forbidden pour indiquer un accès refusé
        die('Requête invalide. Veuillez recharger la page et réessayer.'); // Stoppe le script avec un message d'erreur lisible par l'utilisateur
    }
}


// ── RÔLES ─────────────────────────────────────────────────────────────────────
// Hiérarchie : utilisateur < admin < owner
// Un admin peut tout faire sauf toucher aux autres admins et à l'owner.
// L'owner est unique et a accès à toutes les fonctions, y compris transférer la propriété.

/** Retourne true si l'utilisateur connecté est admin OU owner. */
function is_admin(): bool { // Déclare la fonction qui teste si l'utilisateur courant possède des droits d'administration
    // L'owner hérite de tous les droits admin : on vérifie les deux rôles.
    return isset($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin', 'owner']); // Retourne vrai si une session utilisateur existe et que son rôle est 'admin' ou 'owner'
}

/** Retourne true si l'utilisateur connecté est exactement owner (pas seulement admin). */
function is_owner(): bool { // Déclare la fonction qui teste si l'utilisateur courant est le super-administrateur owner
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'owner'; // Retourne vrai uniquement si le rôle en session vaut exactement 'owner'
}

/**
 * Redirige vers l'accueil si l'utilisateur n'est pas admin/owner.
 * À appeler au début des blocs de routing réservés aux admins.
 */
function require_admin(): void { // Déclare la fonction de garde qui bloque l'accès aux pages réservées aux admins
    if (!is_admin()) { header('Location: /sharetime/public/'); exit; } // Redirige vers l'accueil et stoppe le script si l'utilisateur n'est pas admin
}

/**
 * Redirige vers la page admin si l'utilisateur n'est pas owner.
 * À appeler au début des blocs de routing réservés au seul owner.
 */
function require_owner(): void { // Déclare la fonction de garde qui bloque l'accès aux pages réservées au seul owner
    // Redirige vers admin (pas home) pour que les admins voient un message clair
    if (!is_owner()) { header('Location: /sharetime/public/?page=admin'); exit; } // Redirige vers le tableau de bord admin et stoppe le script si l'utilisateur n'est pas owner
}


// ── NOTIFICATIONS ─────────────────────────────────────────────────────────────

/**
 * Insère une notification in-app dans la table notifications.
 *
 * @param PDO    $pdo         Connexion à la base de données
 * @param int    $user_id     Destinataire de la notification
 * @param string $type        Type machine (ex. 'nouvelle_inscription', 'promotion_attente')
 * @param string $title       Titre affiché dans la liste des notifications
 * @param string $content     Texte descriptif de la notification
 * @param int|null $activity_id  Activité liée (null si la notification ne concerne pas une activité)
 */
function notify(PDO $pdo, int $user_id, string $type, string $title, string $content, ?int $activity_id = null): void { // Déclare la fonction d'envoi d'une notification in-app à un utilisateur
    try {
        $pdo->prepare("INSERT INTO notifications (user_id, activity_id, type, title, content) VALUES (:u, :a, :t, :ti, :c)") // Prépare la requête d'insertion de la notification dans la base
            ->execute(['u' => $user_id, 'a' => $activity_id, 't' => $type, 'ti' => $title, 'c' => $content]); // Exécute l'insertion en liant les paramètres nommés aux valeurs reçues
    } catch (\Throwable $e) {
        // Échec silencieux : une notification ratée ne doit pas bloquer l'action principale
        // (inscription, désinscription, modification…).
    }
}


// ── UPLOAD IMAGE ──────────────────────────────────────────────────────────────

/**
 * Valide et déplace un fichier image uploadé vers le répertoire de destination.
 *
 * @param string $field     Nom du champ <input type="file"> dans le formulaire
 * @param string $dest_dir  Chemin absolu du dossier de destination (ex. /…/public/uploads/activites/)
 * @return string|null      Nom du fichier enregistré, ou null si aucun fichier n'a été soumis
 * @throws \RuntimeException Si le format est invalide ou si le fichier dépasse 2 Mo
 */
function upload_image(string $field, string $dest_dir): ?string { // Déclare la fonction qui valide et enregistre une image uploadée
    // Pas de fichier soumis ou champ vide : comportement normal (pas une erreur)
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null; // Retourne null immédiatement si aucun fichier n'a été soumis ou si l'upload a échoué

    // Correspondance type MIME → extension autorisée
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp']; // Tableau associatif des types MIME acceptés et leurs extensions correspondantes

    // finfo lit les "magic bytes" du fichier réel plutôt que l'extension déclarée par le client.
    // Cela empêche un attaquant de renommer un script PHP en .jpg pour l'uploader.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name']); // Détecte le vrai type MIME du fichier en lisant ses octets magiques (indépendamment de l'extension)
    if (!isset($allowed[$mime])) throw new \RuntimeException("Format non supporté. Utilisez JPG, PNG, GIF ou WebP."); // Rejette le fichier si son type MIME ne fait pas partie des formats autorisés

    // Limite de 2 Mo (2 * 1024 * 1024 octets)
    if ($_FILES[$field]['size'] > 2 * 1024 * 1024) throw new \RuntimeException("Image trop volumineuse (max 2 Mo)."); // Rejette le fichier si sa taille dépasse 2 mégaoctets

    // uniqid('img_', true) génère un nom unique basé sur l'horodatage en microsecondes
    // pour éviter les collisions si deux images sont uploadées simultanément.
    $filename = uniqid('img_', true) . '.' . $allowed[$mime]; // Génère un nom de fichier unique préfixé par 'img_' suivi de l'extension autorisée

    // Déplace le fichier du répertoire temporaire PHP vers le dossier uploads
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], rtrim($dest_dir, '/') . '/' . $filename)) { // Tente de déplacer le fichier temporaire vers le dossier de destination
        throw new \RuntimeException("Erreur lors de l'enregistrement de l'image."); // Lance une exception si le déplacement du fichier a échoué
    }
    return $filename; // Retourne le nom du fichier enregistré pour le stocker en base de données
}


// ── BADGE RÔLE ────────────────────────────────────────────────────────────────

/**
 * Retourne le HTML d'un badge coloré représentant le rôle (ou la suspension) d'un utilisateur.
 * Utilisé dans les listes admin et sur les pages de profil.
 *
 * @param string $role   Valeur de users.role ('utilisateur', 'admin', 'owner')
 * @param bool   $banned True si l'utilisateur est suspendu (is_banned = 1)
 * @return string        Balise <span> stylisée inline
 */
function role_badge(string $role, bool $banned = false): string { // Déclare la fonction qui génère le badge HTML coloré selon le rôle ou l'état de l'utilisateur
    // La suspension a priorité sur le rôle : un utilisateur suspendu reste suspendu
    // même s'il avait un rôle particulier.
    if ($banned) { // Vérifie si l'utilisateur est suspendu, auquel cas la suspension prime sur le rôle
        return '<span style="background:#FEE2E2;color:#DC2626;padding:3px 12px;border-radius:99px;font-size:0.75rem;font-weight:700;">Suspendu</span>'; // Retourne un badge rouge 'Suspendu' avec un style pill arrondi
    }
    switch ($role) { // Sélectionne le badge à afficher selon la valeur du rôle
        case 'owner': return '<span style="background:#FEF3E2;color:#E8811A;padding:3px 12px;border-radius:99px;font-size:0.75rem;font-weight:700;">Super-Admin</span>'; // Badge orange pour le rôle owner (super-administrateur)
        case 'admin': return '<span style="background:#EBF0F8;color:#1E3A6E;padding:3px 12px;border-radius:99px;font-size:0.75rem;font-weight:700;">Admin</span>'; // Badge bleu navy pour le rôle admin
        default:      return '<span style="background:#F3F4F6;color:#6B7280;padding:3px 12px;border-radius:99px;font-size:0.75rem;font-weight:700;">Membre</span>'; // Badge gris neutre pour les utilisateurs standards
    }
}


// ── NAVIGATION ADMIN ─────────────────────────────────────────────────────────

/**
 * Affiche la barre d'onglets de navigation commune à toutes les pages admin.
 * L'onglet correspondant à $current est surligné en orange.
 *
 * @param string $current  Valeur de $page pour l'onglet actif (ex. 'admin_users')
 */
function admin_nav(string $current): void { // Déclare la fonction qui affiche la barre de navigation des pages d'administration
    // Définition des onglets : clé = valeur de ?page=, valeur = [icône, libellé]
    $tabs = [ // Tableau associatif décrivant chaque onglet : clé = paramètre d'URL, valeur = [icône, libellé]
        'admin'            => ['📊', 'Tableau de bord'],
        'admin_users'      => ['👥', 'Utilisateurs'],
        'admin_activities' => ['🎯', 'Activités'],
        'admin_contact'    => ['✉️', 'Messages contact'],
        'admin_logs'       => ['📋', 'Logs'],
    ];
    echo '<div style="background:white;border-bottom:2px solid var(--gray-200);margin-bottom:32px;">'; // Ouvre le conteneur de la barre de navigation avec une bordure inférieure grise
    echo '<div class="container" style="display:flex;gap:0;overflow-x:auto;">'; // Ouvre le conteneur flex scrollable horizontalement pour les onglets
    foreach ($tabs as $p => [$icon, $label]) { // Parcourt chaque onglet défini pour générer son lien HTML
        $active = $p === $current; // Détermine si cet onglet est l'onglet actuellement actif
        // L'onglet actif a une bordure inférieure orange et le texte en navy ; les autres sont gris
        $style  = 'padding:14px 20px;font-weight:600;font-size:0.9rem;text-decoration:none;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;border-bottom:3px solid ' . ($active ? 'var(--orange)' : 'transparent') . ';color:' . ($active ? 'var(--navy)' : 'var(--gray-500)') . ';transition:all 0.15s;'; // Construit les styles CSS de l'onglet avec une bordure orange si actif, grise sinon
        echo "<a href='/sharetime/public/?page={$p}' style='{$style}'>{$icon} {$label}</a>"; // Génère le lien HTML de l'onglet avec son icône et son libellé
    }
    echo '</div></div>'; // Ferme les deux conteneurs de la barre de navigation
}


// ── LOG D'ACTION ADMIN ────────────────────────────────────────────────────────

/**
 * Enregistre une action de modération dans la table admin_logs.
 * Doit être appelée dans chaque handler admin/owner après toute action importante
 * (ban, suppression, changement de rôle…) pour assurer la traçabilité.
 *
 * @param PDO    $pdo         Connexion à la base de données
 * @param string $action      Code de l'action (ex. 'ban', 'delete_user', 'set_role')
 * @param string $target_type Type de la cible : 'user' ou 'activity'
 * @param int    $target_id   ID de l'utilisateur ou de l'activité ciblé
 * @param string $details     Texte libre pour contextualiser (ex. nom de l'utilisateur affecté)
 */
function log_admin_action(PDO $pdo, string $action, string $target_type, int $target_id, string $details = ''): void { // Déclare la fonction qui enregistre une action de modération dans le journal admin_logs
    // Sécurité : ne rien faire si personne n'est connecté (ne devrait pas arriver normalement)
    if (!isset($_SESSION['user'])) return; // Interrompt la fonction si aucun utilisateur n'est connecté en session
    try {
        $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target_type, target_id, details) VALUES (:a, :ac, :tt, :ti, :d)") // Prépare la requête d'insertion du log de modération
            ->execute(['a' => (int)$_SESSION['user']['id'], 'ac' => $action, 'tt' => $target_type, 'ti' => $target_id, 'd' => $details]); // Exécute l'insertion en liant l'ID de l'admin connecté et les détails de l'action
    } catch (\Throwable $e) {
        // Échec silencieux : un log raté ne doit pas bloquer l'action de modération
    }
}
