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
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        // bin2hex(random_bytes(32)) génère 64 caractères hexadécimaux aléatoires
        // cryptographiquement sûrs — impossible à deviner pour un attaquant.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie que le token CSRF du formulaire correspond à celui de la session.
 * À appeler en tête de chaque handler POST. Arrête le script avec HTTP 403 si invalide.
 */
function csrf_check(): void {
    $token = $_POST['csrf_token'] ?? '';
    // hash_equals est résistant aux attaques par timing : il compare les deux chaînes
    // en temps constant, ce qui empêche de déduire la valeur du token par mesure du temps.
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Requête invalide. Veuillez recharger la page et réessayer.');
    }
}


// ── RÔLES ─────────────────────────────────────────────────────────────────────
// Hiérarchie : utilisateur < admin < owner
// Un admin peut tout faire sauf toucher aux autres admins et à l'owner.
// L'owner est unique et a accès à toutes les fonctions, y compris transférer la propriété.

/** Retourne true si l'utilisateur connecté est admin OU owner. */
function is_admin(): bool {
    // L'owner hérite de tous les droits admin : on vérifie les deux rôles.
    return isset($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin', 'owner']);
}

/** Retourne true si l'utilisateur connecté est exactement owner (pas seulement admin). */
function is_owner(): bool {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'owner';
}

/**
 * Redirige vers l'accueil si l'utilisateur n'est pas admin/owner.
 * À appeler au début des blocs de routing réservés aux admins.
 */
function require_admin(): void {
    if (!is_admin()) { header('Location: /sharetime/public/'); exit; }
}

/**
 * Redirige vers la page admin si l'utilisateur n'est pas owner.
 * À appeler au début des blocs de routing réservés au seul owner.
 */
function require_owner(): void {
    // Redirige vers admin (pas home) pour que les admins voient un message clair
    if (!is_owner()) { header('Location: /sharetime/public/?page=admin'); exit; }
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
function notify(PDO $pdo, int $user_id, string $type, string $title, string $content, ?int $activity_id = null): void {
    try {
        $pdo->prepare("INSERT INTO notifications (user_id, activity_id, type, title, content) VALUES (:u, :a, :t, :ti, :c)")
            ->execute(['u' => $user_id, 'a' => $activity_id, 't' => $type, 'ti' => $title, 'c' => $content]);
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
function upload_image(string $field, string $dest_dir): ?string {
    // Pas de fichier soumis ou champ vide : comportement normal (pas une erreur)
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;

    // Correspondance type MIME → extension autorisée
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    // finfo lit les "magic bytes" du fichier réel plutôt que l'extension déclarée par le client.
    // Cela empêche un attaquant de renommer un script PHP en .jpg pour l'uploader.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) throw new \RuntimeException("Format non supporté. Utilisez JPG, PNG, GIF ou WebP.");

    // Limite de 2 Mo (2 * 1024 * 1024 octets)
    if ($_FILES[$field]['size'] > 2 * 1024 * 1024) throw new \RuntimeException("Image trop volumineuse (max 2 Mo).");

    // uniqid('img_', true) génère un nom unique basé sur l'horodatage en microsecondes
    // pour éviter les collisions si deux images sont uploadées simultanément.
    $filename = uniqid('img_', true) . '.' . $allowed[$mime];

    // Déplace le fichier du répertoire temporaire PHP vers le dossier uploads
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], rtrim($dest_dir, '/') . '/' . $filename)) {
        throw new \RuntimeException("Erreur lors de l'enregistrement de l'image.");
    }
    return $filename;
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
function role_badge(string $role, bool $banned = false): string {
    // La suspension a priorité sur le rôle : un utilisateur suspendu reste suspendu
    // même s'il avait un rôle particulier.
    if ($banned) {
        return '<span style="background:#FEE2E2;color:#DC2626;padding:3px 12px;border-radius:99px;font-size:0.75rem;font-weight:700;">Suspendu</span>';
    }
    switch ($role) {
        case 'owner': return '<span style="background:#FEF3E2;color:#E8811A;padding:3px 12px;border-radius:99px;font-size:0.75rem;font-weight:700;">Propriétaire</span>';
        case 'admin': return '<span style="background:#EBF0F8;color:#1E3A6E;padding:3px 12px;border-radius:99px;font-size:0.75rem;font-weight:700;">Admin</span>';
        default:      return '<span style="background:#F3F4F6;color:#6B7280;padding:3px 12px;border-radius:99px;font-size:0.75rem;font-weight:700;">Membre</span>';
    }
}


// ── NAVIGATION ADMIN ─────────────────────────────────────────────────────────

/**
 * Affiche la barre d'onglets de navigation commune à toutes les pages admin.
 * L'onglet correspondant à $current est surligné en orange.
 *
 * @param string $current  Valeur de $page pour l'onglet actif (ex. 'admin_users')
 */
function admin_nav(string $current): void {
    // Définition des onglets : clé = valeur de ?page=, valeur = [icône, libellé]
    $tabs = [
        'admin'            => ['📊', 'Tableau de bord'],
        'admin_users'      => ['👥', 'Utilisateurs'],
        'admin_activities' => ['🎯', 'Activités'],
        'admin_contact'    => ['✉️', 'Messages contact'],
        'admin_logs'       => ['📋', 'Logs'],
    ];
    echo '<div style="background:white;border-bottom:2px solid var(--gray-200);margin-bottom:32px;">';
    echo '<div class="container" style="display:flex;gap:0;overflow-x:auto;">';
    foreach ($tabs as $p => [$icon, $label]) {
        $active = $p === $current;
        // L'onglet actif a une bordure inférieure orange et le texte en navy ; les autres sont gris
        $style  = 'padding:14px 20px;font-weight:600;font-size:0.9rem;text-decoration:none;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;border-bottom:3px solid ' . ($active ? 'var(--orange)' : 'transparent') . ';color:' . ($active ? 'var(--navy)' : 'var(--gray-500)') . ';transition:all 0.15s;';
        echo "<a href='/sharetime/public/?page={$p}' style='{$style}'>{$icon} {$label}</a>";
    }
    echo '</div></div>';
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
function log_admin_action(PDO $pdo, string $action, string $target_type, int $target_id, string $details = ''): void {
    // Sécurité : ne rien faire si personne n'est connecté (ne devrait pas arriver normalement)
    if (!isset($_SESSION['user'])) return;
    try {
        $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target_type, target_id, details) VALUES (:a, :ac, :tt, :ti, :d)")
            ->execute(['a' => (int)$_SESSION['user']['id'], 'ac' => $action, 'tt' => $target_type, 'ti' => $target_id, 'd' => $details]);
    } catch (\Throwable $e) {
        // Échec silencieux : un log raté ne doit pas bloquer l'action de modération
    }
}
