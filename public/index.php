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
 *   7. Calcul des compteurs navbar (notifications, messages non lus)
 *   8. Rendu : header → page → footer
 */

// ── SESSION ────────────────────────────────────────────────────────────────────
session_start([
    'cookie_httponly' => true,   // Le cookie de session n'est pas accessible en JavaScript (protection XSS)
    'cookie_samesite' => 'Lax',  // Empêche l'envoi du cookie dans les requêtes cross-site (protection CSRF)
    'use_strict_mode' => true,   // Refuse les IDs de session non initialisés par le serveur (empêche la fixation de session)
]);

// ── CHARGEMENT DES DÉPENDANCES ─────────────────────────────────────────────────
require '../config/database.php';     // Ouvre $pdo et exécute les migrations automatiques au démarrage
require '../app/models/Activity.php'; // Classe Activity : toutes les requêtes SQL liées aux activités
require '../app/models/User.php';     // Classe User : toutes les requêtes SQL liées aux comptes
require '../app/helpers.php';         // Fonctions globales : csrf_token, notify, upload_image, is_admin…

// ── CONTRÔLE DE BAN / RÔLE EN SESSION ─────────────────────────────────────────
// Pour détecter rapidement les suspensions ou changements de rôle effectués par un admin
// sans requêter la base à chaque page, on relit is_banned et role toutes les 10 requêtes.
if (isset($_SESSION['user'])) {
    // Incrémente le compteur de requêtes depuis la dernière vérification
    $_SESSION['_req_count'] = ($_SESSION['_req_count'] ?? 0) + 1;

    if ($_SESSION['_req_count'] >= 10) {
        $_SESSION['_req_count'] = 0;  // remet le compteur à zéro pour les 10 prochaines requêtes

        // Récupère uniquement les colonnes sensibles (is_banned, role) pour éviter de charger
        // toute la ligne utilisateur inutilement
        $ban_check_stmt = $pdo->prepare("SELECT is_banned, role FROM users WHERE idusers = :id");
        $ban_check_stmt->execute(['id' => $_SESSION['user']['id']]);
        $ban_check_row = $ban_check_stmt->fetch();

        if ($ban_check_row) {
            if (!empty($ban_check_row['is_banned'])) {
                // L'utilisateur a été suspendu depuis sa dernière connexion : déconnexion forcée immédiate
                $_SESSION = [];       // efface toutes les données de session
                session_destroy();   // supprime le fichier de session côté serveur
                header('Location: /sharetime/public/?page=connexion'); // redirige vers la page de connexion
                exit; // arrête immédiatement l'exécution pour ne pas afficher de contenu
            }
            // Synchronise le rôle en session si un admin l'a modifié depuis la connexion
            // (ex. : un admin vient d'être rétrogradé en utilisateur)
            $_SESSION['user']['role'] = $ban_check_row['role'];
        }
    }
}

// ── ROUTING ────────────────────────────────────────────────────────────────────
$page    = $_GET['page'] ?? 'home';  // page demandée via ?page=NOM, 'home' par défaut
$error   = null;                      // message d'erreur à afficher dans les formulaires (null = aucune erreur)
$success = null;                      // message de succès à afficher dans les formulaires (null = aucun)

// ── MAPPING DES CATÉGORIES ─────────────────────────────────────────────────────
// Associe chaque identifiant de catégorie (clé en base) à [emoji, classe CSS, libellé lisible].
// Utilisé dans les vues pour afficher les badges colorés et dans les handlers pour la whitelist.
$CATEGORY_MAP = [
    'sport'      => ['🏃', 'sport',   'Sport'],      // catégorie activités sportives
    'creativite' => ['🎨', 'atelier', 'Créativité'], // catégorie ateliers créatifs
    'nature'     => ['🌲', 'sortie',  'Nature'],     // catégorie sorties nature/plein air
    'social'     => ['🤝', 'club',    'Social'],     // catégorie rencontres et clubs
    'culture'    => ['🖼️', 'art',     'Culture'],   // catégorie événements culturels
    'autre'      => ['⭐', 'sport',   'Autre'],      // catégorie par défaut non classée
];

// ── FLASH MESSAGES ─────────────────────────────────────────────────────────────
// Les flash messages sont écrits en session par les handlers POST,
// puis lus et immédiatement effacés ici pour n'être affichés qu'une seule fois.
// $flash      = texte brut (échappé par htmlspecialchars dans la vue)
// $flash_html = HTML autorisé (pour les liens de dev comme le lien de vérification email)
// $flash_type = 'success', 'error' ou 'info' — détermine la couleur du toast
$flash      = $_SESSION['flash']      ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'success';
$flash_html = $_SESSION['flash_html'] ?? null;
unset($_SESSION['flash'], $_SESSION['flash_type'], $_SESSION['flash_html']);  // efface après lecture (usage unique)

// ── WHITELIST DES PAGES ────────────────────────────────────────────────────────
// Seules ces pages sont autorisées. Toute valeur de ?page= absente de cette liste
// est remplacée par 'home' pour éviter l'inclusion de fichiers arbitraires (path traversal).
$allowed_pages = [
    'home', 'activites', 'connexion', 'inscription', 'contact', 'creer',
    'detail', 'faq', 'profil', 'profil_edit', 'cgu', 'mentions',
    's_inscrire', 'se_desinscrire', 'commenter', 'supprimer_commentaire', 'noter',
    'admin', 'admin_users', 'admin_activities', 'admin_logs', 'owner',
    'mot_de_passe_oublie', 'reinitialiser_mdp',
    'modifier_activite', 'notifications', 'verifier_email', 'renvoyer_verification',
    'messages', 'envoyer_message', 'logout', 'carte', 'admin_contact',
    'suivre', 'signaler',
    'notifs_lues',
];
// Si $page n'est pas dans la whitelist, on redirige silencieusement vers l'accueil
if (!in_array($page, $allowed_pages)) $page = 'home'; // toute page inconnue retombe sur l'accueil (évite l'inclusion de fichiers arbitraires)

// Instances des modèles partagées entre le routing GET et les handlers POST
$activityModel = new Activity($pdo); // instance du modèle activités, partagée entre routing GET et handlers POST
$userModel     = new User($pdo);     // instance du modèle utilisateurs, partagée entre routing GET et handlers POST

// ── HANDLERS POST ──────────────────────────────────────────────────────────────
// Inclus inconditionnellement : chaque fichier vérifie lui-même $page et REQUEST_METHOD.
// L'ordre d'inclusion n'a pas d'importance (pas de dépendances croisées entre handlers).
require '../app/handlers/auth.php';      // connexion, inscription, vérification email, mot de passe oublié
require '../app/handlers/activity.php'; // créer, modifier, annuler, s'inscrire, commenter, noter
require '../app/handlers/user.php';     // profil, contact, follow/unfollow, messages, signalement, notifs
require '../app/handlers/admin.php';    // ban, rôles, suppression, transfert propriété, contenu éditorial

// ── INITIALISATION DES VARIABLES DE PAGE ──────────────────────────────────────
// Toutes les variables lues dans les fichiers de pages sont initialisées ici à leur valeur vide.
// Cela évite des notices "undefined variable" si une page charge des variables
// qu'un autre bloc de routing n'aurait pas définies.

$notif_count           = 0;     // nombre de notifications non lues (badge navbar)
$msg_count             = 0;     // nombre de messages privés non lus (badge navbar)
$conversations         = [];    // liste des conversations de la messagerie privée
$conversation_user     = null;  // données de l'interlocuteur sélectionné dans les messages
$conversation_messages = [];    // liste des messages de la conversation sélectionnée
$with_id               = 0;     // ID de l'interlocuteur sélectionné (?with=ID)

$activities          = [];  // liste des activités affichées (accueil, catalogue, carte)
$user_activities     = [];  // activités créées par l'utilisateur dont on consulte le profil
$user_registrations  = [];  // activités auxquelles l'utilisateur connecté est inscrit (profil perso)
$faq_items           = [];  // entrées de la FAQ

$activity          = null;  // activité affichée sur la page de détail
$profile           = null;  // utilisateur dont on affiche le profil
$reg_status        = null;  // statut d'inscription de l'utilisateur connecté pour l'activité de détail

$waitlist_count    = 0;     // nombre de personnes en liste d'attente pour l'activité de détail
$waitlist_position = 0;     // position de l'utilisateur connecté dans la liste d'attente

$comments  = [];    // commentaires de l'activité de détail
$has_rated = false; // true si l'utilisateur a déjà noté l'organisateur pour cette activité

$city_filter     = '';  // filtre ville saisi dans le catalogue d'activités
$category_filter = '';  // filtre catégorie saisi dans le catalogue
$title_filter    = '';  // filtre recherche textuelle saisi dans le catalogue
$status_filter   = '';  // filtre statut saisi dans le catalogue

$current_page  = 1;  // numéro de page courant dans la pagination du catalogue
$total_pages   = 1;  // nombre total de pages disponibles
$total_count   = 0;  // nombre total d'activités correspondant aux filtres actifs

// Variables du panel admin (pagination, listes, statistiques)
$admin_stats           = [];  // tableau de statistiques globales (membres, activités, admins, suspendus)
$admin_users_list      = [];  // liste paginée des utilisateurs affichée dans admin_users
$admin_activities_list = [];  // liste paginée des activités affichée dans admin_activities
$owner_users           = [];  // liste complète des utilisateurs pour le panel owner (sans pagination)

$admin_current_page = 1;  // page courante dans la pagination du panel admin
$admin_total_pages  = 1;  // nombre total de pages disponibles dans le panel admin
$admin_total_count  = 0;  // nombre total d'entrées correspondant aux filtres actifs dans le panel admin

$follower_count  = 0;     // nombre d'abonnés de l'utilisateur dont on consulte le profil
$following_count = 0;     // nombre d'abonnements de l'utilisateur dont on consulte le profil
$is_following    = false; // true si l'utilisateur connecté suit le profil affiché

$notifications     = [];  // liste des notifications de l'utilisateur connecté
$admin_logs        = [];  // entrées du journal d'administration

$log_action_filter = '';  // filtre "type d'action" dans les logs admin (whitelist : ban, delete, etc.)
$log_admin_filter  = '';  // filtre "nom d'admin" dans les logs admin (recherche partielle sur pseudo/prénom/nom)

// ── ROUTING GET : DONNÉES PAR PAGE ─────────────────────────────────────────────
// Chaque branche charge uniquement les données nécessaires à la page demandée
// pour éviter des requêtes inutiles sur les autres pages.
// Les variables définies ici sont accessibles directement dans les fichiers de page
// car ils sont inclus via require dans la même portée PHP.

if ($page === 'home') {
    // Charge les 6 prochaines activités actives pour le bloc "à venir" de la page d'accueil.
    // La pagination n'est pas nécessaire ici : on affiche toujours exactement 6 activités.
    $activities = $activityModel->getAll(
        '',                               // pas de filtre ville
        $_SESSION['user']['id'] ?? null,  // inclut les activités privées de l'utilisateur si connecté
        '',                               // pas de filtre catégorie
        'active',                         // seulement les activités actives (pas annulées ni terminées)
        '',                               // pas de recherche textuelle
        1,                                // page 1
        6                                 // 6 résultats maximum
    );

} elseif ($page === 'activites') {
    // Lecture et validation des filtres GET pour éviter des valeurs arbitraires en base
    $city_filter     = trim($_GET['city']     ?? '');   // filtre sur la ville (recherche partielle)
    $raw_category    = trim($_GET['category'] ?? '');   // valeur brute du filtre catégorie
    $category_filter = isset($CATEGORY_MAP[$raw_category]) ? $raw_category : '';  // whitelist via $CATEGORY_MAP
    $title_filter    = trim($_GET['search']   ?? '');   // recherche textuelle sur titre et description

    // Whitelist sur le filtre statut
    $valid_statut_values = ['active', 'en_cours', 'annulee', 'terminee'];
    $status_filter = in_array($_GET['statut'] ?? '', $valid_statut_values) ? $_GET['statut'] : '';

    // Calcul de la pagination
    $activities_per_page = 12;  // nombre d'activités par page dans le catalogue
    $current_page        = max(1, intval($_GET['p'] ?? 1));  // page demandée, minimum 1

    // Compte le total pour calculer le nombre de pages
    $total_count = $activityModel->countAll(
        $city_filter,
        $_SESSION['user']['id'] ?? null,
        $category_filter,
        $status_filter,
        $title_filter
    );
    $total_pages  = max(1, (int)ceil($total_count / $activities_per_page));  // au moins 1 page
    $current_page = min($current_page, $total_pages);  // évite de dépasser la dernière page disponible

    // Charge la page d'activités correspondant aux filtres et à la pagination
    $activities = $activityModel->getAll(
        $city_filter,
        $_SESSION['user']['id'] ?? null,
        $category_filter,
        $status_filter,
        $title_filter,
        $current_page,
        $activities_per_page
    );

} elseif ($page === 'detail') {
    // Récupère l'ID de l'activité depuis l'URL (?id=X)
    $detail_activity_id = intval($_GET['id'] ?? 0);
    $activity           = $detail_activity_id ? $activityModel->getById($detail_activity_id) : null;

    if ($activity) {
        // Charge les commentaires triés du plus ancien au plus récent
        $comments = $activityModel->getComments($detail_activity_id);

        if (isset($_SESSION['user'])) {
            // Statut d'inscription de l'utilisateur connecté : 'inscrit', 'en_attente', 'annule' ou null
            $reg_status = $activityModel->getRegistrationStatus($detail_activity_id, $_SESSION['user']['id']);

            if (!empty($activity['liste_attente_active'])) {
                // Nombre total de personnes en attente (affiché sur la page)
                $waitlist_count = $activityModel->getWaitlistCount($detail_activity_id);

                if ($reg_status === 'en_attente') {
                    // Position dans la liste d'attente : uniquement si l'utilisateur est lui-même en attente
                    $waitlist_position = $activityModel->getWaitlistPosition($detail_activity_id, $_SESSION['user']['id']);
                }
            }

            // Le formulaire de notation n'est pertinent que si l'activité est terminée
            // et que l'utilisateur a participé (statut 'inscrit')
            if ($activity['status'] === 'terminee' && $reg_status === 'inscrit') {
                $has_rated = $activityModel->hasRated($_SESSION['user']['id'], $activity['creator_id'], $detail_activity_id);
            }
        }
    }

} elseif ($page === 'modifier_activite') {
    // Redirige les visiteurs non connectés qui tenteraient d'accéder à cette page via GET
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }

    // L'ID peut être passé en GET (lien depuis la page de détail) ou en POST (après erreur de formulaire)
    $detail_activity_id = intval($_GET['id'] ?? $_POST['activity_id'] ?? 0);
    $activity           = $detail_activity_id ? $activityModel->getById($detail_activity_id) : null;

    // Redirige si l'activité n'existe pas, n'appartient pas à l'utilisateur connecté, ou n'est plus active
    if (!$activity
        || (int)$activity['creator_id'] !== (int)$_SESSION['user']['id']
        || $activity['status'] !== 'active') {
        header('Location: /sharetime/public/?page=activites'); exit;
    }

} elseif ($page === 'profil') {
    // Un visiteur non connecté peut voir un profil public (?id=X), mais pas /profil sans ID
    if (!isset($_SESSION['user']) && empty($_GET['id'])) {
        header('Location: /sharetime/public/?page=connexion'); exit;
    }

    // ID du profil à afficher : celui du GET ou, par défaut, celui de l'utilisateur connecté
    $profile_id      = intval($_GET['id'] ?? $_SESSION['user']['id'] ?? 0);
    $profile         = $profile_id ? $userModel->getById($profile_id) : null;
    $user_activities = $profile ? $activityModel->getByCreator($profile_id) : [];

    // Les inscriptions ne sont visibles que sur son propre profil, pas sur celui des autres
    $user_registrations = (isset($_SESSION['user']) && $profile_id === (int)$_SESSION['user']['id'])
                          ? $activityModel->getUserRegistrations($profile_id)
                          : [];

    // Compteurs d'abonnés et d'abonnements pour le profil affiché
    $follower_count  = $profile ? $userModel->getFollowerCount($profile_id)  : 0;
    $following_count = $profile ? $userModel->getFollowingCount($profile_id) : 0;

    // Vérifie si l'utilisateur connecté suit ce profil (uniquement si ce n'est pas son propre profil)
    $is_following = (isset($_SESSION['user']) && $profile && (int)$_SESSION['user']['id'] !== $profile_id)
                    ? $userModel->isFollowing((int)$_SESSION['user']['id'], $profile_id)
                    : false;

} elseif ($page === 'profil_edit') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    // Charge les données actuelles du profil pour préremplir le formulaire d'édition
    $profile = $userModel->getById($_SESSION['user']['id']);

} elseif ($page === 'faq') {
    // Questions/réponses triées par ordre d'insertion (idfaq croissant = ordre de saisie)
    $faq_items = $pdo->query("SELECT * FROM faq ORDER BY idfaq ASC")->fetchAll();

} elseif ($page === 'creer' && !isset($_SESSION['user'])) {
    // Redirige les visiteurs non connectés qui tentent d'accéder à la page de création
    header('Location: /sharetime/public/?page=connexion'); exit;

} elseif ($page === 'admin') {
    // L'owner ne doit jamais voir les pages admin classiques : redirection vers son panel
    if (is_owner()) { header('Location: /sharetime/public/?page=owner&tab=dashboard'); exit; } // l'owner ne doit pas voir la page admin classique
    require_admin();  // bloque les non-admins (redirige vers l'accueil avec un message d'erreur)

    // Une seule requête avec plusieurs sous-sélections pour éviter 5 allers-retours séparés
    $raw_stats = $pdo->query("SELECT
        (SELECT SUM(role != 'superadmin') FROM users) AS membres,
        (SELECT COUNT(*) FROM activities) AS activites,
        (SELECT COUNT(*) FROM registrations WHERE status = 'inscrit') AS inscriptions,
        (SELECT SUM(role = 'admin') FROM users) AS admins,
        (SELECT SUM(is_banned = 1) FROM users) AS suspendus
    ")->fetch();

    // Convertit les valeurs en entiers pour éviter les problèmes de comparaison avec null ou string
    $admin_stats = [
        'membres'      => (int)$raw_stats['membres'],
        'activites'    => (int)$raw_stats['activites'],
        'inscriptions' => (int)$raw_stats['inscriptions'],
        'admins'       => (int)$raw_stats['admins'],
        'suspendus'    => (int)$raw_stats['suspendus'],
    ];

    // 5 derniers comptes créés, pour le bloc "Derniers inscrits" du tableau de bord
    $admin_recent_users = $pdo->query("SELECT * FROM users ORDER BY date_creation DESC LIMIT 5")->fetchAll();
    // 5 dernières activités créées, avec le nom de leur organisateur
    $admin_recent_activities = $pdo->query("
        SELECT a.*, u.prenom, u.nom FROM activities a
        JOIN users u ON u.idusers = a.creator_id ORDER BY a.created_at DESC LIMIT 5
    ")->fetchAll();

} elseif ($page === 'admin_users') {
    if (is_owner()) { header('Location: /sharetime/public/?page=owner&tab=users'); exit; } // l'owner consulte ses utilisateurs dans son propre panel
    require_admin();

    $admin_per_page     = 25;                                          // nombre d'utilisateurs par page dans le panel
    $admin_total_count  = $userModel->countAllForAdmin();              // total pour calculer la pagination
    $admin_total_pages  = max(1, (int)ceil($admin_total_count / $admin_per_page));
    $admin_current_page = max(1, min($admin_total_pages, intval($_GET['p'] ?? 1)));  // page clampée entre 1 et le max
    $admin_users_list   = $userModel->getAllForAdmin($admin_current_page, $admin_per_page);

} elseif ($page === 'admin_activities') {
    if (is_owner()) { header('Location: /sharetime/public/?page=owner&tab=activities'); exit; } // l'owner consulte les activités dans son propre panel
    require_admin();

    $admin_per_page        = 25;                                                                          // nombre d'activités par page dans le panel
    $admin_total_count     = $activityModel->countAllForAdmin();                                          // total pour calculer le nombre de pages
    $admin_total_pages     = max(1, (int)ceil($admin_total_count / $admin_per_page));                    // au moins 1 page même si aucune activité
    $admin_current_page    = max(1, min($admin_total_pages, intval($_GET['p'] ?? 1)));                   // page clampée entre 1 et le max
    $admin_activities_list = $activityModel->getAllForAdmin($admin_current_page, $admin_per_page);        // charge uniquement la page courante

} elseif ($page === 'admin_logs') {
    if (is_owner()) { header('Location: /sharetime/public/?page=owner&tab=dashboard'); exit; } // l'owner n'a pas de page logs dédiée : renvoi vers son dashboard
    require_admin();

    // Whitelist des types d'actions affichables dans les logs
    $valid_log_action_types = ['ban','unban','delete_user','delete_activity','set_role','set_status','transfer_ownership'];
    $log_action_filter = in_array($_GET['action'] ?? '', $valid_log_action_types) ? $_GET['action'] : '';
    $log_admin_filter  = trim($_GET['admin'] ?? '');  // filtre textuel sur le nom/pseudo de l'admin

    $admin_per_page = 50;  // les logs peuvent être nombreux : 50 par page

    // Construction dynamique de la clause WHERE selon les filtres actifs
    $where_clauses  = [];
    $filter_params  = [];
    if ($log_action_filter) {
        $where_clauses[]          = 'l.action = :action';
        $filter_params['action']  = $log_action_filter;
    }
    if ($log_admin_filter) {
        // Recherche partielle sur pseudo, prénom ou nom de l'admin auteur du log
        $where_clauses[]          = '(u.pseudo LIKE :adm1 OR u.prenom LIKE :adm2 OR u.nom LIKE :adm3)';
        $filter_params['adm1']    = '%' . $log_admin_filter . '%';
        $filter_params['adm2']    = '%' . $log_admin_filter . '%';
        $filter_params['adm3']    = '%' . $log_admin_filter . '%';
    }
    // Construit la clause WHERE complète ou une chaîne vide si aucun filtre actif
    $where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // Compte le nombre total de logs correspondant aux filtres pour la pagination
    $count_stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM admin_logs l JOIN users u ON u.idusers = l.admin_id $where_sql"
    );
    $count_stmt->execute($filter_params);
    $admin_total_count  = (int)$count_stmt->fetchColumn();
    $admin_total_pages  = max(1, (int)ceil($admin_total_count / $admin_per_page));
    $admin_current_page = max(1, min($admin_total_pages, intval($_GET['p'] ?? 1)));
    $logs_page_offset   = ($admin_current_page - 1) * $admin_per_page;  // décalage pour OFFSET

    // LIMIT et OFFSET sont interpolés directement car PDO les traite comme des chaînes,
    // ce qui cause une erreur sur certains pilotes MySQL si on utilise des paramètres liés.
    $logs_stmt = $pdo->prepare("
        SELECT l.*, u.pseudo AS admin_pseudo, u.prenom AS admin_prenom
        FROM admin_logs l
        JOIN users u ON u.idusers = l.admin_id
        $where_sql
        ORDER BY l.created_at DESC
        LIMIT {$admin_per_page} OFFSET {$logs_page_offset}
    ");
    $logs_stmt->execute($filter_params);
    $admin_logs = $logs_stmt->fetchAll();

} elseif ($page === 'notifications') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; } // page réservée aux utilisateurs connectés

    // Les 50 dernières notifications, avec le titre de l'activité associée si elle existe encore
    $notifs_stmt = $pdo->prepare("
        SELECT n.*, a.title AS activity_title
        FROM notifications n
        LEFT JOIN activities a ON a.idactivities = n.activity_id
        WHERE n.user_id = :u
        ORDER BY n.created_at DESC
        LIMIT 50
    ");
    $notifs_stmt->execute(['u' => $_SESSION['user']['id']]);
    $notifications = $notifs_stmt->fetchAll();

    // Marque toutes les notifications comme lues APRÈS le fetch :
    // ainsi les indicateurs visuels "non lu" sont encore visibles sur cette page,
    // mais le badge dans la navbar sera à 0 dès la prochaine requête.
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :u AND is_read = 0")
        ->execute(['u' => $_SESSION['user']['id']]);

} elseif ($page === 'messages') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; } // la messagerie est réservée aux utilisateurs connectés

    $current_user_id = (int)$_SESSION['user']['id'];  // ID de l'utilisateur connecté (expéditeur ou destinataire)
    $with_id         = intval($_GET['with'] ?? 0);    // ID de l'interlocuteur sélectionné (0 si aucune conversation ouverte)

    // Charge la liste des conversations : une ligne par interlocuteur,
    // avec le contenu et la date du dernier message échangé,
    // et le nombre de messages non lus reçus de cet interlocuteur.
    //
    // Sous-requête interne (conv) :
    //   - regroupe les messages par "autre partie" (other_id)
    //   - retient l'ID du dernier message (MAX(id)) pour récupérer son contenu via JOIN
    $conversations_stmt = $pdo->prepare("
        SELECT
            u.idusers, u.prenom, u.nom, u.pseudo, u.photo_profil,
            m.content AS last_content,        -- texte du dernier message échangé
            m.created_at AS last_time,        -- date du dernier message (tri de la liste)
            m.sender_id AS last_sender_id,    -- permet d'afficher 'Vous : …' si c'est nous qui avons envoyé
            (SELECT COUNT(*) FROM messages
             WHERE receiver_id = ? AND sender_id = u.idusers AND is_read = 0) AS unread_count
        FROM (
            SELECT
                CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS other_id,
                MAX(idmessage) AS last_id
            FROM messages
            WHERE sender_id = ? OR receiver_id = ?
            GROUP BY other_id
        ) conv
        JOIN users u ON u.idusers = conv.other_id
        JOIN messages m ON m.idmessage = conv.last_id
        ORDER BY conv.last_id DESC
    ");
    // Les quatre paramètres positionnels (?) correspondent dans l'ordre aux quatre occurrences
    $conversations_stmt->execute([$current_user_id, $current_user_id, $current_user_id, $current_user_id]);
    $conversations = $conversations_stmt->fetchAll();

    if ($with_id > 0) {
        $conversation_user = $userModel->getById($with_id);  // données de l'interlocuteur sélectionné

        if ($conversation_user) {
            // Marque tous les messages reçus de cet interlocuteur comme lus
            // AVANT de les charger : ils apparaîtront comme "déjà lus" dans la vue
            $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0")
                ->execute([$current_user_id, $with_id]);

            // Charge les 100 derniers messages de cette conversation dans l'ordre chronologique
            $msgs_stmt = $pdo->prepare("
                SELECT m.*, u.prenom, u.nom, u.pseudo, u.photo_profil
                FROM messages m
                JOIN users u ON u.idusers = m.sender_id
                WHERE (m.sender_id = ? AND m.receiver_id = ?)
                   OR (m.sender_id = ? AND m.receiver_id = ?)
                ORDER BY m.created_at ASC
                LIMIT 100
            ");
            // Les quatre paramètres : me→with, with→me pour couvrir les deux sens de la conversation
            $msgs_stmt->execute([$current_user_id, $with_id, $with_id, $current_user_id]);
            $conversation_messages = $msgs_stmt->fetchAll();
        }
    }

} elseif ($page === 'admin_contact') {
    require_admin(); // seuls les admins et l'owner peuvent consulter les messages de contact
    // Charge tous les messages de contact triés du plus récent au plus ancien
    $contact_messages = $pdo->query("SELECT * FROM contact_messages ORDER BY sent_at DESC")->fetchAll();
    // Compte séparément les non lus pour afficher le badge dans la navbar admin
    $contact_unread   = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn(); // (int) évite null si la table est vide

} elseif ($page === 'owner') {
    require_owner();  // arrête immédiatement si l'utilisateur n'est pas owner (défini dans helpers.php)

    // Whitelist des onglets du panel owner
    $valid_owner_tabs = ['dashboard', 'users', 'activities', 'admins', 'contact', 'contenu', 'signalements'];
    $owner_tab        = in_array($_GET['tab'] ?? '', $valid_owner_tabs)
                        ? ($_GET['tab'] ?? 'dashboard')
                        : 'dashboard';  // onglet par défaut si la valeur est absente ou invalide

    // Charge tous les utilisateurs et activités sans pagination (l'owner a une vision globale)
    $owner_users           = $userModel->getAllForAdmin();        // tous les utilisateurs sans limite de pagination
    $admin_activities_list = $activityModel->getAllForAdmin();    // toutes les activités sans limite de pagination

    // Mêmes statistiques que la page admin, recalculées ici pour le panel owner
    $raw_stats = $pdo->query("SELECT
        (SELECT SUM(role != 'superadmin') FROM users) AS membres,
        (SELECT COUNT(*) FROM activities) AS activites,
        (SELECT COUNT(*) FROM registrations WHERE status = 'inscrit') AS inscriptions,
        (SELECT SUM(role = 'admin') FROM users) AS admins,
        (SELECT SUM(is_banned = 1) FROM users) AS suspendus
    ")->fetch();
    $admin_stats = [
        'membres'      => (int)$raw_stats['membres'],
        'activites'    => (int)$raw_stats['activites'],
        'inscriptions' => (int)$raw_stats['inscriptions'],
        'admins'       => (int)$raw_stats['admins'],
        'suspendus'    => (int)$raw_stats['suspendus'],
    ];

    // Blocs "Derniers inscrits" et "Dernières activités" du tableau de bord owner
    $admin_recent_users = $pdo->query("SELECT * FROM users ORDER BY date_creation DESC LIMIT 5")->fetchAll(); // 5 derniers comptes créés
    $admin_recent_activities = $pdo->query("
        SELECT a.*, u.prenom, u.nom FROM activities a
        JOIN users u ON u.idusers = a.creator_id ORDER BY a.created_at DESC LIMIT 5
    ")->fetchAll(); // 5 dernières activités avec le nom de l'organisateur

    // Charge les messages de contact uniquement quand l'onglet 'contact' est actif
    // (évite une requête inutile sur les autres onglets)
    if ($owner_tab === 'contact') {
        $contact_messages = $pdo->query("SELECT * FROM contact_messages ORDER BY sent_at DESC")->fetchAll(); // tous les messages de contact, du plus récent au plus ancien
        $contact_unread   = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn(); // compteur de messages non lus pour le badge
    }
}

// ── COMPTEURS NAVBAR ───────────────────────────────────────────────────────────
// Calculés APRÈS le routing GET pour être toujours à jour :
// par exemple, la page 'messages' vient de marquer des messages comme lus,
// le compteur doit en tenir compte immédiatement.
// Une seule requête avec deux sous-sélections pour éviter deux allers-retours séparés.
if (isset($_SESSION['user'])) {
    try {
        $navbar_counts_stmt = $pdo->prepare("SELECT
            (SELECT COUNT(*) FROM notifications WHERE user_id = :u  AND is_read = 0) AS notif_count,
            (SELECT COUNT(*) FROM messages   WHERE receiver_id = :u2 AND is_read = 0) AS msg_count
        ");
        $navbar_counts_stmt->execute([
            'u'  => $_SESSION['user']['id'],
            'u2' => $_SESSION['user']['id'],
        ]);
        $navbar_counts = $navbar_counts_stmt->fetch();
        $notif_count   = (int)$navbar_counts['notif_count'];  // affiché en badge orange sur la cloche
        $msg_count     = (int)$navbar_counts['msg_count'];    // affiché en badge bleu sur l'enveloppe
    } catch (\Throwable $e) {
        // Silencieux : un badge manquant ne doit pas casser la page en cours de rendu
        // (ex. : table notifications ou messages absente lors d'une migration incomplète)
    }
}

// ── RENDU ──────────────────────────────────────────────────────────────────────
// Inclut le header commun : navbar, styles CSS, affichage du toast flash
require '../app/views/header.php'; // ouvre <html>, <head>, <body> et affiche la navbar

// Liste des pages qui ont un fichier PHP correspondant dans public/pages/
$php_pages = [
    'home', 'activites', 'connexion', 'inscription', 'creer', 'detail',
    'profil', 'profil_edit', 'faq', 'contact', 'cgu', 'mentions',
    'admin', 'admin_users', 'admin_activities', 'admin_logs', 'owner',
    'mot_de_passe_oublie', 'reinitialiser_mdp',
    'modifier_activite', 'notifications', 'verifier_email', 'messages', 'carte', 'admin_contact',
]; // pages ayant un template HTML : les pages d'action (logout, s_inscrire…) n'en ont pas

if (in_array($page, $php_pages)) {
    // Inclut le fichier de la page dans la portée courante (variables disponibles directement)
    require "pages/{$page}.php"; // chemin relatif au répertoire public/
} else {
    // Fallback : pages d'action pure (s_inscrire, logout…) qui redirigent toujours
    // Ne devraient jamais arriver ici, mais on affiche l'accueil par sécurité
    require 'pages/home.php'; // affiche l'accueil par défaut si aucun template ne correspond
}

// Inclut le footer commun : fermeture du body, scripts JS éventuels
require '../app/views/footer.php'; // ferme </body> et </html>, affiche les scripts JS globaux
