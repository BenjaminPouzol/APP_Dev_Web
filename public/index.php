<?php
/**
 * public/index.php — Front controller (point d'entrée unique de l'application)
 *
 * TOUTES les requêtes HTTP passent par ce fichier (configuré via .htaccess ou
 * accès direct par ?page=NOM). Il orchestre l'application dans cet ordre :
 *
 *   1. Démarrage de la session + vérification du ban/rôle en base
 *   2. Chargement de la connexion DB, des modèles et des helpers
 *   3. Définition du mapping catégories → [emoji, classe CSS, libellé]
 *   4. Lecture et effacement des flash messages
 *   5. Inclusion des handlers POST (auth, activités, utilisateur, admin)
 *   6. Routing GET : chargement des données selon la page demandée
 *   7. Calcul des compteurs navbar (notifications, messages)
 *   8. Rendu : header → page → footer
 */

// ── SESSION ────────────────────────────────────────────────────────────────────
session_start([
    'cookie_httponly' => true,   // Le cookie de session n'est pas accessible en JavaScript (protection XSS)
    'cookie_samesite' => 'Lax',  // Empêche l'envoi du cookie dans les requêtes cross-site (protection CSRF)
    'use_strict_mode' => true,   // Refuse les IDs de session non initialisés par le serveur
]);

// ── CHARGEMENT DES DÉPENDANCES ─────────────────────────────────────────────────
require '../config/database.php';   // Ouvre $pdo et exécute les migrations automatiques
require '../app/models/Activity.php'; // Classe Activity
require '../app/models/User.php';     // Classe User
require '../app/helpers.php';         // Fonctions globales (csrf_token, notify, upload_image…)

// ── CONTRÔLE DE BAN EN SESSION ─────────────────────────────────────────────────
// Pour détecter rapidement les suspensions ou changements de rôle sans re-requêter la base
// à chaque page, on relit users.is_banned et users.role toutes les 10 requêtes.
if (isset($_SESSION['user'])) {
    $_SESSION['_req_count'] = ($_SESSION['_req_count'] ?? 0) + 1;
    if ($_SESSION['_req_count'] >= 10) {
        $_SESSION['_req_count'] = 0;  // remise à zéro du compteur
        $stmt_ban = $pdo->prepare("SELECT is_banned, role FROM users WHERE idusers = :id");
        $stmt_ban->execute(['id' => $_SESSION['user']['id']]);
        $row_ban = $stmt_ban->fetch();
        if ($row_ban) {
            if (!empty($row_ban['is_banned'])) {
                // L'utilisateur a été suspendu depuis sa dernière connexion : déconnexion forcée
                $_SESSION = [];
                session_destroy();
                header('Location: /sharetime/public/?page=connexion');
                exit;
            }
            // Met à jour le rôle en session si un admin l'a modifié depuis la connexion
            $_SESSION['user']['role'] = $row_ban['role'];
        }
    }
}

// ── ROUTING ────────────────────────────────────────────────────────────────────
$page  = $_GET['page'] ?? 'home';  // page par défaut si ?page= absent
$error = null;    // message d'erreur affiché dans les formulaires
$success = null;  // message de succès affiché dans les formulaires

// ── MAPPING DES CATÉGORIES ─────────────────────────────────────────────────────
// Associe chaque identifiant de catégorie à [emoji, classe CSS, libellé lisible].
// Utilisé dans les vues pour afficher les badges et filtres de catégorie.
$CATEGORY_MAP = [
    'sport'      => ['🏃', 'sport',   'Sport'],
    'creativite' => ['🎨', 'atelier', 'Créativité'],
    'nature'     => ['🌲', 'sortie',  'Nature'],
    'social'     => ['🤝', 'club',    'Social'],
    'culture'    => ['🖼️', 'art',     'Culture'],
    'autre'      => ['⭐', 'sport',   'Autre'],
];

// ── FLASH MESSAGES ─────────────────────────────────────────────────────────────
// Les flash messages sont stockés en session par les handlers POST, puis lus et
// immédiatement effacés ici pour n'être affichés qu'une seule fois.
// $flash = texte brut, $flash_html = HTML autorisé (pour les liens de dev)
$flash      = $_SESSION['flash']      ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'success';  // 'success', 'error', ou 'info'
$flash_html = $_SESSION['flash_html'] ?? null;
unset($_SESSION['flash'], $_SESSION['flash_type'], $_SESSION['flash_html']);

// ── WHITELIST DES PAGES ────────────────────────────────────────────────────────
// Seules ces pages sont autorisées. Toute valeur de ?page= absente de cette liste
// est remplacée par 'home' pour éviter des includes de fichiers arbitraires.
$allowed_pages = [
    'home', 'activites', 'connexion', 'inscription', 'contact', 'creer',
    'detail', 'faq', 'profil', 'profil_edit', 'cgu', 'mentions',
    's_inscrire', 'se_desinscrire', 'commenter', 'supprimer_commentaire', 'noter',
    'admin', 'admin_users', 'admin_activities', 'admin_logs', 'owner',
    'mot_de_passe_oublie', 'reinitialiser_mdp',
    'modifier_activite', 'notifications', 'verifier_email', 'renvoyer_verification',
    'messages', 'envoyer_message', 'logout', 'carte', 'admin_contact',
    'suivre', 'signaler',
];
if (!in_array($page, $allowed_pages)) $page = 'home';

// Instances des modèles réutilisées par le routing et les handlers
$activityModel = new Activity($pdo);
$userModel     = new User($pdo);

// ── HANDLERS POST ──────────────────────────────────────────────────────────────
// Inclus inconditionnellement : chaque fichier vérifie lui-même $page et REQUEST_METHOD.
// L'ordre d'inclusion n'a pas d'importance (pas de dépendances entre handlers).
require '../app/handlers/auth.php';      // connexion, inscription, mot de passe, email
require '../app/handlers/activity.php'; // créer, modifier, s'inscrire, commenter, noter
require '../app/handlers/user.php';     // profil, contact, follow, messages, notifs
require '../app/handlers/admin.php';    // ban, rôles, suppression, transfert propriété

// ── INITIALISATION DES VARIABLES ──────────────────────────────────────────────
// Toutes les variables utilisées dans les pages sont initialisées ici à leur valeur par défaut.
// Cela évite des "undefined variable" dans les vues si le bloc de routing ne les définit pas.
$notif_count   = 0;
$msg_count     = 0;
$conversations = [];
$conversation_user     = null;
$conversation_messages = [];
$with_id = 0;

$activities     = $user_activities = $user_registrations = $faq_items = [];
$activity       = $profile = null;
$reg_status     = null;
$waitlist_count = $waitlist_position = 0;
$comments       = [];
$has_rated      = false;
$city_filter    = $category_filter = $title_filter = $status_filter = '';
$current_page   = 1;
$total_pages    = 1;
$total_count    = 0;
$admin_stats    = $admin_users_list = $admin_activities_list = $owner_users = [];
$admin_current_page = 1;
$admin_total_pages  = 1;
$admin_total_count  = 0;
$follower_count  = $following_count = 0;
$is_following    = false;
$notifications      = [];
$admin_logs         = [];
$log_action_filter  = '';
$log_admin_filter   = '';

// ── ROUTING GET : DONNÉES PAR PAGE ─────────────────────────────────────────────
// Chaque branche charge uniquement les données nécessaires à la page demandée.
// Les variables définies ici sont accessibles directement dans les fichiers de page
// (ils sont inclus dans la même portée PHP via require).

if ($page === 'home') {
    // Charge uniquement les 6 prochaines activités actives pour la page d'accueil
    // (LIMIT 6 évite de charger toutes les activités — la boucle de la vue s'arrête de toute façon à 6)
    $activities = $activityModel->getAll('', $_SESSION['user']['id'] ?? null, '', 'active', '', 1, 6);

} elseif ($page === 'activites') {
    // Lecture et validation des filtres GET (évite les valeurs arbitraires)
    $city_filter     = trim($_GET['city']     ?? '');
    $raw_cat         = trim($_GET['category'] ?? '');
    $category_filter = isset($CATEGORY_MAP[$raw_cat]) ? $raw_cat : '';  // whitelist via $CATEGORY_MAP
    $title_filter    = trim($_GET['search']   ?? '');
    $valid_statuts   = ['active', 'annulee', 'terminee'];
    $status_filter   = in_array($_GET['statut'] ?? '', $valid_statuts) ? $_GET['statut'] : '';

    // Pagination
    $current_page = max(1, intval($_GET['p'] ?? 1));
    $per_page     = 12;
    $total_count  = $activityModel->countAll($city_filter, $_SESSION['user']['id'] ?? null, $category_filter, $status_filter, $title_filter);
    $total_pages  = max(1, (int)ceil($total_count / $per_page));
    $current_page = min($current_page, $total_pages);  // évite de dépasser la dernière page
    $activities   = $activityModel->getAll($city_filter, $_SESSION['user']['id'] ?? null, $category_filter, $status_filter, $title_filter, $current_page, $per_page);

} elseif ($page === 'detail') {
    $activity_id = intval($_GET['id'] ?? 0);
    $activity    = $activity_id ? $activityModel->getById($activity_id) : null;
    if ($activity) {
        $comments = $activityModel->getComments($activity_id);
        if (isset($_SESSION['user'])) {
            // Statut d'inscription de l'utilisateur connecté pour cette activité
            $reg_status = $activityModel->getRegistrationStatus($activity_id, $_SESSION['user']['id']);

            if (!empty($activity['liste_attente_active'])) {
                $waitlist_count = $activityModel->getWaitlistCount($activity_id);
                // Position en attente uniquement si l'utilisateur est lui-même en attente
                if ($reg_status === 'en_attente') {
                    $waitlist_position = $activityModel->getWaitlistPosition($activity_id, $_SESSION['user']['id']);
                }
            }

            // Le formulaire de notation n'est affiché que si l'activité est terminée
            // et que l'utilisateur ne l'a pas encore notée
            if ($activity['status'] === 'terminee' && $reg_status === 'inscrit') {
                $has_rated = $activityModel->hasRated($_SESSION['user']['id'], $activity['creator_id'], $activity_id);
            }
        }
    }

} elseif ($page === 'modifier_activite') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    $activity_id = intval($_GET['id'] ?? $_POST['activity_id'] ?? 0);
    $activity    = $activity_id ? $activityModel->getById($activity_id) : null;
    // Redirige si l'activité n'existe pas, n'appartient pas à l'utilisateur, ou n'est pas active
    if (!$activity || (int)$activity['creator_id'] !== (int)$_SESSION['user']['id'] || $activity['status'] !== 'active') {
        header('Location: /sharetime/public/?page=activites'); exit;
    }

} elseif ($page === 'profil') {
    if (!isset($_SESSION['user']) && empty($_GET['id'])) {
        header('Location: /sharetime/public/?page=connexion'); exit;
    }
    $profile_id      = intval($_GET['id'] ?? $_SESSION['user']['id'] ?? 0);
    $profile         = $profile_id ? $userModel->getById($profile_id) : null;
    $user_activities = $profile ? $activityModel->getByCreator($profile_id) : [];

    // Les inscriptions ne sont affichées que sur son propre profil (pas celui des autres)
    $user_registrations = (isset($_SESSION['user']) && $profile_id === (int)$_SESSION['user']['id'])
                          ? $activityModel->getUserRegistrations($profile_id) : [];

    $follower_count  = $profile ? $userModel->getFollowerCount($profile_id)  : 0;
    $following_count = $profile ? $userModel->getFollowingCount($profile_id) : 0;

    // is_following uniquement si on consulte le profil de quelqu'un d'autre
    $is_following = (isset($_SESSION['user']) && $profile && (int)$_SESSION['user']['id'] !== $profile_id)
                    ? $userModel->isFollowing((int)$_SESSION['user']['id'], $profile_id) : false;

} elseif ($page === 'profil_edit') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    // Charge les données actuelles du profil pour préremplir le formulaire d'édition
    $profile = $userModel->getById($_SESSION['user']['id']);

} elseif ($page === 'faq') {
    // Questions/réponses triées par ordre d'insertion (idfaq croissant)
    $faq_items = $pdo->query("SELECT * FROM faq ORDER BY idfaq ASC")->fetchAll();

} elseif ($page === 'creer' && !isset($_SESSION['user'])) {
    // Redirige les visiteurs non connectés qui tentent d'accéder à la page de création
    header('Location: /sharetime/public/?page=connexion'); exit;

} elseif ($page === 'admin') {
    // L'owner ne doit jamais voir les pages admin classiques : redirection vers son panel
    if (is_owner()) { header('Location: /sharetime/public/?page=owner&tab=dashboard'); exit; }
    require_admin();

    // Une seule requête avec sous-sélections pour éviter 5 allers-retours séparés
    $s = $pdo->query("SELECT
        (SELECT SUM(role != 'owner') FROM users) AS membres,
        (SELECT COUNT(*) FROM activities) AS activites,
        (SELECT COUNT(*) FROM registrations WHERE status = 'inscrit') AS inscriptions,
        (SELECT SUM(role = 'admin') FROM users) AS admins,
        (SELECT SUM(is_banned = 1) FROM users) AS suspendus
    ")->fetch();
    $admin_stats = [
        'membres'      => (int)$s['membres'],
        'activites'    => (int)$s['activites'],
        'inscriptions' => (int)$s['inscriptions'],
        'admins'       => (int)$s['admins'],
        'suspendus'    => (int)$s['suspendus'],
    ];
    $admin_recent_users = $pdo->query("SELECT * FROM users ORDER BY date_creation DESC LIMIT 5")->fetchAll();
    $admin_recent_activities = $pdo->query("
        SELECT a.*, u.prenom, u.nom FROM activities a
        JOIN users u ON u.idusers = a.creator_id ORDER BY a.created_at DESC LIMIT 5
    ")->fetchAll();

} elseif ($page === 'admin_users') {
    if (is_owner()) { header('Location: /sharetime/public/?page=owner&tab=users'); exit; }
    require_admin();
    $per_page_admin     = 25;
    $admin_total_count  = $userModel->countAllForAdmin();
    $admin_total_pages  = max(1, (int)ceil($admin_total_count / $per_page_admin));
    $admin_current_page = max(1, min($admin_total_pages, intval($_GET['p'] ?? 1)));
    $admin_users_list   = $userModel->getAllForAdmin($admin_current_page, $per_page_admin);

} elseif ($page === 'admin_activities') {
    if (is_owner()) { header('Location: /sharetime/public/?page=owner&tab=activities'); exit; }
    require_admin();
    $per_page_admin        = 25;
    $admin_total_count     = $activityModel->countAllForAdmin();
    $admin_total_pages     = max(1, (int)ceil($admin_total_count / $per_page_admin));
    $admin_current_page    = max(1, min($admin_total_pages, intval($_GET['p'] ?? 1)));
    $admin_activities_list = $activityModel->getAllForAdmin($admin_current_page, $per_page_admin);

} elseif ($page === 'admin_logs') {
    if (is_owner()) { header('Location: /sharetime/public/?page=owner&tab=dashboard'); exit; }
    require_admin();

    // Validation des filtres de la page logs
    $valid_log_actions = ['ban','unban','delete_user','delete_activity','set_role','set_status','transfer_ownership'];
    $log_action_filter = in_array($_GET['action'] ?? '', $valid_log_actions) ? $_GET['action'] : '';
    $log_admin_filter  = trim($_GET['admin'] ?? '');
    $per_page_admin    = 50;

    // Construction dynamique de la clause WHERE selon les filtres actifs
    $where  = [];
    $params = [];
    if ($log_action_filter) {
        $where[]          = 'l.action = :action';
        $params['action'] = $log_action_filter;
    }
    if ($log_admin_filter) {
        // Recherche partielle sur pseudo, prénom ou nom de l'admin
        $where[]         = '(u.pseudo LIKE :adm1 OR u.prenom LIKE :adm2 OR u.nom LIKE :adm3)';
        $params['adm1']  = $params['adm2'] = $params['adm3'] = '%' . $log_admin_filter . '%';
    }
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Compte total pour la pagination
    $cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_logs l JOIN users u ON u.idusers = l.admin_id $where_sql");
    $cnt_stmt->execute($params);
    $admin_total_count  = (int)$cnt_stmt->fetchColumn();
    $admin_total_pages  = max(1, (int)ceil($admin_total_count / $per_page_admin));
    $admin_current_page = max(1, min($admin_total_pages, intval($_GET['p'] ?? 1)));
    $offset = ($admin_current_page - 1) * $per_page_admin;

    // LIMIT/OFFSET interpolés directement (cast en int) car PDO les traite comme des chaînes
    // et certains pilotes MySQL refusent les paramètres liés pour LIMIT/OFFSET
    $log_stmt = $pdo->prepare("
        SELECT l.*, u.pseudo AS admin_pseudo, u.prenom AS admin_prenom
        FROM admin_logs l
        JOIN users u ON u.idusers = l.admin_id
        $where_sql
        ORDER BY l.created_at DESC
        LIMIT {$per_page_admin} OFFSET {$offset}
    ");
    $log_stmt->execute($params);
    $admin_logs = $log_stmt->fetchAll();

} elseif ($page === 'notifications') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }

    // Les 50 dernières notifications, avec le titre de l'activité associée si elle existe
    $stmt_notifs = $pdo->prepare("
        SELECT n.*, a.title AS activity_title
        FROM notifications n
        LEFT JOIN activities a ON a.idactivities = n.activity_id
        WHERE n.user_id = :u
        ORDER BY n.created_at DESC
        LIMIT 50
    ");
    $stmt_notifs->execute(['u' => $_SESSION['user']['id']]);
    $notifications = $stmt_notifs->fetchAll();

    // Marque toutes les notifications comme lues dès la visite de la page.
    // On fait le fetch avant pour conserver les indicateurs visuels "non lu" sur cette page,
    // mais le badge dans la navbar sera à 0 dès la prochaine requête.
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :u AND is_read = 0")
        ->execute(['u' => $_SESSION['user']['id']]);

} elseif ($page === 'messages') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    $me      = (int)$_SESSION['user']['id'];
    $with_id = intval($_GET['with'] ?? 0);  // ID de l'interlocuteur sélectionné (0 si aucun)

    // Charge la liste des conversations : une ligne par interlocuteur, avec le dernier message
    // et le nombre de messages non lus de cet interlocuteur.
    // Sous-requête interne : regroupe les messages par interlocuteur (other_id) et retient
    // l'ID du dernier message (MAX(id)) pour pouvoir le JOINer et afficher son contenu.
    $stmt_convs = $pdo->prepare("
        SELECT
            u.idusers, u.prenom, u.nom, u.pseudo, u.photo_profil,
            m.content AS last_content,
            m.created_at AS last_time,
            m.sender_id AS last_sender_id,
            (SELECT COUNT(*) FROM messages
             WHERE receiver_id = ? AND sender_id = u.idusers AND is_read = 0) AS unread_count
        FROM (
            SELECT
                CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS other_id,
                MAX(id) AS last_id
            FROM messages
            WHERE sender_id = ? OR receiver_id = ?
            GROUP BY other_id
        ) conv
        JOIN users u ON u.idusers = conv.other_id
        JOIN messages m ON m.id = conv.last_id
        ORDER BY conv.last_id DESC
    ");
    // Les paramètres positionnels (?) sont passés dans l'ordre d'apparition dans la requête
    $stmt_convs->execute([$me, $me, $me, $me]);
    $conversations = $stmt_convs->fetchAll();

    if ($with_id > 0) {
        $conversation_user = $userModel->getById($with_id);
        if ($conversation_user) {
            // Marque comme lus tous les messages reçus de cet interlocuteur
            // (fait avant le chargement pour que les messages apparaissent déjà lus)
            $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0")
                ->execute([$me, $with_id]);

            // Charge les 100 derniers messages de cette conversation dans l'ordre chronologique
            $stmt_msgs = $pdo->prepare("
                SELECT m.*, u.prenom, u.nom, u.pseudo, u.photo_profil
                FROM messages m
                JOIN users u ON u.idusers = m.sender_id
                WHERE (m.sender_id = ? AND m.receiver_id = ?)
                   OR (m.sender_id = ? AND m.receiver_id = ?)
                ORDER BY m.created_at ASC
                LIMIT 100
            ");
            $stmt_msgs->execute([$me, $with_id, $with_id, $me]);
            $conversation_messages = $stmt_msgs->fetchAll();
        }
    }

} elseif ($page === 'admin_contact') {
    require_admin();
    $contact_messages = $pdo->query("SELECT * FROM contact_messages ORDER BY sent_at DESC")->fetchAll();
    $contact_unread   = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();

} elseif ($page === 'owner') {
    require_owner();  // Arrête si pas owner
    $valid_tabs = ['dashboard', 'users', 'activities', 'admins', 'contact', 'contenu', 'signalements'];
    $owner_tab  = in_array($_GET['tab'] ?? '', $valid_tabs) ? ($_GET['tab'] ?? 'dashboard') : 'dashboard';

    // Charge tous les utilisateurs et activités sans pagination (panel owner = vision globale)
    $owner_users           = $userModel->getAllForAdmin();
    $admin_activities_list = $activityModel->getAllForAdmin();

    // Mêmes stats que la page admin mais dans le panel owner
    $s = $pdo->query("SELECT
        (SELECT SUM(role != 'owner') FROM users) AS membres,
        (SELECT COUNT(*) FROM activities) AS activites,
        (SELECT COUNT(*) FROM registrations WHERE status = 'inscrit') AS inscriptions,
        (SELECT SUM(role = 'admin') FROM users) AS admins,
        (SELECT SUM(is_banned = 1) FROM users) AS suspendus
    ")->fetch();
    $admin_stats = [
        'membres'      => (int)$s['membres'],
        'activites'    => (int)$s['activites'],
        'inscriptions' => (int)$s['inscriptions'],
        'admins'       => (int)$s['admins'],
        'suspendus'    => (int)$s['suspendus'],
    ];
    $admin_recent_users = $pdo->query("SELECT * FROM users ORDER BY date_creation DESC LIMIT 5")->fetchAll();
    $admin_recent_activities = $pdo->query("
        SELECT a.*, u.prenom, u.nom FROM activities a
        JOIN users u ON u.idusers = a.creator_id ORDER BY a.created_at DESC LIMIT 5
    ")->fetchAll();

    if ($owner_tab === 'contact') {
        $contact_messages = $pdo->query("SELECT * FROM contact_messages ORDER BY sent_at DESC")->fetchAll();
        $contact_unread   = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
    }
}

// ── COMPTEURS NAVBAR ───────────────────────────────────────────────────────────
// Calculés après le routing pour être toujours à jour (ex. la page messages vient de marquer des
// messages comme lus : le compteur doit refléter cela).
// Une seule requête avec deux sous-sélections pour éviter deux allers-retours séparés.
if (isset($_SESSION['user'])) {
    try {
        $stmt_counts = $pdo->prepare("SELECT
            (SELECT COUNT(*) FROM notifications WHERE user_id = :u  AND is_read = 0) AS nc,
            (SELECT COUNT(*) FROM messages   WHERE receiver_id = :u2 AND is_read = 0) AS mc
        ");
        $stmt_counts->execute(['u' => $_SESSION['user']['id'], 'u2' => $_SESSION['user']['id']]);
        $counts      = $stmt_counts->fetch();
        $notif_count = (int)$counts['nc'];  // affiché en badge orange sur la cloche
        $msg_count   = (int)$counts['mc'];  // affiché en badge bleu sur l'enveloppe
    } catch (\Throwable $e) {}  // silencieux : un badge manquant ne doit pas casser la page
}

// ── RENDU ──────────────────────────────────────────────────────────────────────
// Header commun à toutes les pages (navbar, styles, flash toast)
require '../app/views/header.php';

// Liste des pages ayant un fichier PHP correspondant dans public/pages/
$php_pages = [
    'home', 'activites', 'connexion', 'inscription', 'creer', 'detail',
    'profil', 'profil_edit', 'faq', 'contact', 'cgu', 'mentions',
    'admin', 'admin_users', 'admin_activities', 'admin_logs', 'owner',
    'mot_de_passe_oublie', 'reinitialiser_mdp',
    'modifier_activite', 'notifications', 'verifier_email', 'messages', 'carte', 'admin_contact',
];

if (in_array($page, $php_pages)) {
    require "pages/{$page}.php";  // Inclut le fichier de la page demandée
} else {
    require 'pages/home.php';     // Fallback vers la page d'accueil
}

// Footer commun (scripts JS éventuels, fermeture du body)
require '../app/views/footer.php';
