<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

require '../config/database.php';
require '../app/models/Activity.php';
require '../app/models/User.php';

$page    = $_GET['page'] ?? 'home';
$error   = null;
$success = null;

// ── CATÉGORIES ───────────────────────────────────────────
// [emoji, classe CSS, libellé]
$CATEGORY_MAP = [
    'sport'      => ['🏃', 'sport',   'Sport'],
    'creativite' => ['🎨', 'atelier', 'Créativité'],
    'nature'     => ['🌲', 'sortie',  'Nature'],
    'social'     => ['🤝', 'club',    'Social'],
    'culture'    => ['🖼️', 'art',     'Culture'],
    'autre'      => ['⭐', 'sport',   'Autre'],
];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ── CSRF ────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_check(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Requête invalide. Veuillez recharger la page et réessayer.');
    }
}

// ── RÔLES ───────────────────────────────────────────────
function is_admin(): bool {
    return isset($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin', 'owner']);
}
function is_owner(): bool {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'owner';
}
function require_admin(): void {
    if (!is_admin()) { header('Location: /sharetime/public/'); exit; }
}
function require_owner(): void {
    if (!is_owner()) { header('Location: /sharetime/public/?page=admin'); exit; }
}

// ── Badge HTML ───────────────────────────────────────────
function role_badge(string $role, bool $banned = false): string {
    if ($banned) {
        return '<span style="background:#FEE2E2;color:#DC2626;padding:3px 12px;border-radius:99px;font-size:0.75rem;font-weight:700;">Suspendu</span>';
    }
    switch ($role) {
        case 'owner': return '<span style="background:#FEF3E2;color:#E8811A;padding:3px 12px;border-radius:99px;font-size:0.75rem;font-weight:700;">Propriétaire</span>';
        case 'admin': return '<span style="background:#EBF0F8;color:#1E3A6E;padding:3px 12px;border-radius:99px;font-size:0.75rem;font-weight:700;">Admin</span>';
        default:      return '<span style="background:#F3F4F6;color:#6B7280;padding:3px 12px;border-radius:99px;font-size:0.75rem;font-weight:700;">Membre</span>';
    }
}

// ── CONNEXION ──────────────────────────────────────────
if ($page === 'connexion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse e-mail invalide.";
    } else {
        $userModel = new User($pdo);
        $user = $userModel->findByEmail($email);

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            if (!empty($user['is_banned'])) {
                $error = "Votre compte a été suspendu. Contactez l'administrateur.";
            } else {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'     => $user['idusers'],
                    'nom'    => $user['nom'],
                    'prenom' => $user['prenom'],
                    'pseudo' => $user['pseudo'] ?? $user['prenom'],
                    'email'  => $user['email'],
                    'role'   => $user['role'],
                ];
                header('Location: /sharetime/public/');
                exit;
            }
        } else {
            $error = "Email ou mot de passe incorrect.";
        }
    }
}

// ── INSCRIPTION ────────────────────────────────────────
if ($page === 'inscription' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $prenom    = trim($_POST['firstname'] ?? '');
    $nom       = trim($_POST['lastname'] ?? '');
    $pseudo    = trim($_POST['username'] ?? '');
    $ville     = trim($_POST['city'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm-password'] ?? '';
    $birthdate = $_POST['birthdate'] ?? '';
    $terms     = isset($_POST['terms']);

    if (empty($prenom) || empty($nom) || empty($pseudo) || empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse e-mail invalide.";
    } elseif ($password !== $confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif (!$terms) {
        $error = "Vous devez accepter les conditions générales d'utilisation.";
    } else {
        $userModel = new User($pdo);
        if ($userModel->emailExists($email)) {
            $error = "Cet email est déjà utilisé.";
        } else {
            $userModel->create([
                'prenom'         => $prenom,
                'nom'            => $nom,
                'pseudo'         => $pseudo,
                'email'          => $email,
                'password'       => $password,
                'ville'          => $ville,
                'date_naissance' => $birthdate ?: null,
                'cgu_acceptees'  => true,
            ]);
            $_SESSION['flash'] = "Compte créé avec succès ! Vous pouvez vous connecter.";
            header('Location: /sharetime/public/?page=connexion');
            exit;
        }
    }
}

// ── CRÉATION D'ACTIVITÉ ────────────────────────────────
if ($page === 'creer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $title            = trim($_POST['title'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $location         = trim($_POST['location'] ?? '');
    $city             = trim($_POST['city'] ?? '');
    $start_time       = $_POST['start_time'] ?? '';
    $end_time         = $_POST['end_time'] ?? '';
    $max_participants = intval($_POST['max_participants'] ?? 0);
    $visibility       = in_array($_POST['visibility'] ?? '', ['publique', 'privee']) ? $_POST['visibility'] : 'publique';
    $category         = in_array($_POST['category'] ?? '', array_keys($CATEGORY_MAP)) ? $_POST['category'] : 'autre';

    if (empty($title) || empty($description) || empty($location) || empty($city) || empty($start_time) || empty($end_time)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif ($max_participants < 2) {
        $error = "Le nombre de participants doit être d'au moins 2.";
    } elseif (strtotime($end_time) <= strtotime($start_time)) {
        $error = "La date de fin doit être postérieure à la date de début.";
    } else {
        $activityModel = new Activity($pdo);
        $activityModel->create([
            'title'            => $title,
            'description'      => $description,
            'location'         => $location,
            'city'             => $city,
            'start_time'       => $start_time,
            'end_time'         => $end_time,
            'max_participants' => $max_participants,
            'visibility'       => $visibility,
            'category'         => $category,
            'creator_id'       => $_SESSION['user']['id'],
        ]);
        header('Location: /sharetime/public/?page=activites');
        exit;
    }
}

// ── S'INSCRIRE ─────────────────────────────────────────
if ($page === 's_inscrire' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $activity_id = intval($_POST['activity_id'] ?? 0);
    if ($activity_id > 0) {
        $activityModel = new Activity($pdo);
        $activity = $activityModel->getById($activity_id);
        if ($activity && $activity['nb_inscrits'] < $activity['max_participants'] && $activity['status'] === 'active') {
            $activityModel->register($activity_id, $_SESSION['user']['id']);
        }
    }
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id);
    exit;
}

// ── SE DÉSINSCRIRE ─────────────────────────────────────
if ($page === 'se_desinscrire' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $activity_id = intval($_POST['activity_id'] ?? 0);
    if ($activity_id > 0) {
        $activityModel = new Activity($pdo);
        $activityModel->unregister($activity_id, $_SESSION['user']['id']);
    }
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id);
    exit;
}

// ── ÉDITION DU PROFIL ──────────────────────────────────
if ($page === 'profil_edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $pseudo = trim($_POST['pseudo'] ?? '');
    $ville  = trim($_POST['ville'] ?? '');
    $bio    = trim($_POST['bio'] ?? '');

    if (empty($pseudo)) {
        $error = "Le pseudo ne peut pas être vide.";
    } else {
        $userModel = new User($pdo);
        $userModel->update($_SESSION['user']['id'], ['pseudo' => $pseudo, 'ville' => $ville, 'bio' => $bio]);
        $_SESSION['user']['pseudo'] = $pseudo;
        $_SESSION['flash'] = "Profil mis à jour avec succès.";
        header('Location: /sharetime/public/?page=profil');
        exit;
    }
}

// ── CONTACT ────────────────────────────────────────────
if ($page === 'contact' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $contact_name    = trim($_POST['name'] ?? '');
    $contact_email   = trim($_POST['email'] ?? '');
    $contact_subject = trim($_POST['subject'] ?? '');
    $contact_message = trim($_POST['message'] ?? '');

    if (empty($contact_name) || empty($contact_email) || empty($contact_message)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse e-mail invalide.";
    } else {
        // Crée la table si elle n'existe pas encore
        $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL,
            subject VARCHAR(200) DEFAULT '',
            message TEXT NOT NULL,
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_read TINYINT(1) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $pdo->prepare("
            INSERT INTO contact_messages (name, email, subject, message)
            VALUES (:name, :email, :subject, :message)
        ");
        $stmt->execute([
            'name'    => $contact_name,
            'email'   => $contact_email,
            'subject' => $contact_subject,
            'message' => $contact_message,
        ]);

        // Tentative d'envoi email (nécessite un SMTP configuré)
        $to      = 'admin@sharetime.fr';
        $subject = '[ShareTime] ' . ($contact_subject ?: 'Nouveau message de contact');
        $body    = "Nom : {$contact_name}\nEmail : {$contact_email}\n\n{$contact_message}";
        $headers = "From: noreply@sharetime.fr\r\nReply-To: {$contact_email}";
        @mail($to, $subject, $body, $headers);

        $success = "Votre message a bien été reçu. Nous vous répondrons rapidement.";
    }
}

// ── ADMIN : GESTION UTILISATEURS ───────────────────────
if ($page === 'admin_users' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    csrf_check();
    $action    = $_POST['action'] ?? '';
    $target_id = intval($_POST['user_id'] ?? 0);
    $me        = (int)$_SESSION['user']['id'];

    if ($target_id > 0 && $target_id !== $me) {
        $um = new User($pdo);
        if ($action === 'ban') {
            $target_user = $um->getById($target_id);
            // Seul le propriétaire peut suspendre un admin
            if ($target_user && $target_user['role'] === 'admin' && !is_owner()) {
                $_SESSION['flash'] = "Vous n'avez pas le droit de suspendre un administrateur.";
                header('Location: /sharetime/public/?page=admin_users');
                exit;
            }
            $um->setBanned($target_id, true);
        } elseif ($action === 'unban') {
            $target_user = $um->getById($target_id);
            // Seul le propriétaire peut réactiver un admin suspendu
            if ($target_user && $target_user['role'] === 'admin' && !is_owner()) {
                $_SESSION['flash'] = "Vous n'avez pas le droit de réactiver un administrateur.";
                header('Location: /sharetime/public/?page=admin_users');
                exit;
            }
            $um->setBanned($target_id, false);
        } elseif ($action === 'set_role' && is_owner()) {
            $new_role = in_array($_POST['role'] ?? '', ['utilisateur', 'admin']) ? $_POST['role'] : null;
            if ($new_role) $um->setRole($target_id, $new_role);
        } elseif ($action === 'transfer_ownership' && is_owner()) {
            if ($um->transferOwnership($target_id, $me)) {
                // Le propriétaire actuel est maintenant admin : on met à jour sa session
                $_SESSION['user']['role'] = 'admin';
                $_SESSION['flash'] = "La propriété a été transférée avec succès.";
            } else {
                $_SESSION['flash'] = "Transfert impossible.";
            }
            header('Location: /sharetime/public/?page=admin_users');
            exit;
        } elseif ($action === 'delete' && is_owner()) {
            $um->delete($target_id);
        }
    }
    $_SESSION['flash'] = "Action effectuée.";
    header('Location: /sharetime/public/?page=admin_users');
    exit;
}

// ── OWNER : ACTIONS ────────────────────────────────────
if ($page === 'owner' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_owner();
    csrf_check();
    $action    = $_POST['action'] ?? '';
    $target_id = intval($_POST['user_id'] ?? 0);
    $me        = (int)$_SESSION['user']['id'];

    if ($target_id > 0 && $target_id !== $me) {
        $um = new User($pdo);
        if ($action === 'set_role') {
            $new_role = in_array($_POST['role'] ?? '', ['utilisateur', 'admin']) ? $_POST['role'] : null;
            if ($new_role) $um->setRole($target_id, $new_role);
            $_SESSION['flash'] = "Rôle mis à jour.";
        } elseif ($action === 'transfer_ownership') {
            if ($um->transferOwnership($target_id, $me)) {
                $_SESSION['user']['role'] = 'admin';
                $_SESSION['flash'] = "Propriété transférée avec succès.";
            } else {
                $_SESSION['flash'] = "Transfert impossible.";
            }
        }
    }
    header('Location: /sharetime/public/?page=owner');
    exit;
}

// ── ADMIN : GESTION ACTIVITÉS ──────────────────────────
if ($page === 'admin_activities' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    csrf_check();
    $action      = $_POST['action'] ?? '';
    $activity_id = intval($_POST['activity_id'] ?? 0);

    if ($activity_id > 0) {
        $am = new Activity($pdo);
        if ($action === 'delete') {
            $am->delete($activity_id);
        } elseif ($action === 'set_status') {
            $am->setStatus($activity_id, $_POST['status'] ?? '');
        }
    }
    header('Location: /sharetime/public/?page=admin_activities');
    exit;
}

// ── ADMIN : NAV PARTAGÉE ───────────────────────────────
function admin_nav(string $current): void {
    $tabs = [
        'admin'            => ['📊', 'Tableau de bord'],
        'admin_users'      => ['👥', 'Utilisateurs'],
        'admin_activities' => ['🎯', 'Activités'],
    ];
    echo '<div style="background:white;border-bottom:2px solid var(--gray-200);margin-bottom:32px;">';
    echo '<div class="container" style="display:flex;gap:0;overflow-x:auto;">';
    foreach ($tabs as $p => [$icon, $label]) {
        $active = $p === $current;
        $style  = 'padding:14px 20px;font-weight:600;font-size:0.9rem;text-decoration:none;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;border-bottom:3px solid ' . ($active ? 'var(--orange)' : 'transparent') . ';color:' . ($active ? 'var(--navy)' : 'var(--gray-500)') . ';transition:all 0.15s;';
        echo "<a href='/sharetime/public/?page={$p}' style='{$style}'>{$icon} {$label}</a>";
    }
    echo '</div></div>';
}

// ── DÉCONNEXION ────────────────────────────────────────
if ($page === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: /sharetime/public/');
    exit;
}

// ── ROUTING ────────────────────────────────────────────
$allowed_pages = [
    'home', 'activites', 'connexion', 'inscription', 'contact', 'creer',
    'detail', 'faq', 'profil', 'profil_edit', 'cgu', 'mentions',
    's_inscrire', 'se_desinscrire',
    'admin', 'admin_users', 'admin_activities', 'owner',
];
if (!in_array($page, $allowed_pages)) $page = 'home';

$activityModel = new Activity($pdo);
$userModel     = new User($pdo);

// Données selon la page
$activities = $city_filter = $category_filter = '';
$activities = $user_activities = $user_registrations = $faq_items = [];
$activity = $profile = null;
$is_registered = false;
$admin_stats = $admin_users_list = $admin_activities_list = $owner_users = [];

if ($page === 'home') {
    $activities = $activityModel->getAll('', $_SESSION['user']['id'] ?? null);

} elseif ($page === 'activites') {
    $city_filter     = trim($_GET['city'] ?? '');
    $raw_cat         = trim($_GET['category'] ?? '');
    $category_filter = isset($CATEGORY_MAP[$raw_cat]) ? $raw_cat : '';
    $activities      = $activityModel->getAll($city_filter, $_SESSION['user']['id'] ?? null, $category_filter);

} elseif ($page === 'detail') {
    $activity_id = intval($_GET['id'] ?? 0);
    $activity    = $activity_id ? $activityModel->getById($activity_id) : null;
    if ($activity && isset($_SESSION['user'])) {
        $is_registered = $activityModel->isRegistered($activity_id, $_SESSION['user']['id']);
    }

} elseif ($page === 'profil') {
    if (!isset($_SESSION['user']) && empty($_GET['id'])) {
        header('Location: /sharetime/public/?page=connexion'); exit;
    }
    $profile_id         = intval($_GET['id'] ?? $_SESSION['user']['id'] ?? 0);
    $profile            = $profile_id ? $userModel->getById($profile_id) : null;
    $user_activities    = $profile ? $activityModel->getByCreator($profile_id) : [];
    $user_registrations = (isset($_SESSION['user']) && $profile_id === (int)$_SESSION['user']['id'])
                          ? $activityModel->getUserRegistrations($profile_id) : [];

} elseif ($page === 'profil_edit') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    $profile = $userModel->getById($_SESSION['user']['id']);

} elseif ($page === 'faq') {
    $faq_items = $pdo->query("SELECT * FROM faq ORDER BY idfaq ASC")->fetchAll();

} elseif ($page === 'creer' && !isset($_SESSION['user'])) {
    header('Location: /sharetime/public/?page=connexion'); exit;

} elseif ($page === 'admin') {
    require_admin();
    $admin_stats = [
        'membres'       => $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'owner'")->fetchColumn(),
        'activites'     => $pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn(),
        'inscriptions'  => $pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'inscrit'")->fetchColumn(),
        'admins'        => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
        'suspendus'     => $pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 1")->fetchColumn(),
    ];
    $admin_recent_users = $pdo->query("
        SELECT * FROM users ORDER BY date_creation DESC LIMIT 5
    ")->fetchAll();
    $admin_recent_activities = $pdo->query("
        SELECT a.*, u.prenom, u.nom
        FROM activities a JOIN users u ON u.idusers = a.creator_id
        ORDER BY a.created_at DESC LIMIT 5
    ")->fetchAll();

} elseif ($page === 'admin_users') {
    require_admin();
    $admin_users_list = $userModel->getAllForAdmin();

} elseif ($page === 'admin_activities') {
    require_admin();
    $admin_activities_list = $activityModel->getAllForAdmin();

} elseif ($page === 'owner') {
    require_owner();
    $owner_users = $userModel->getAllForAdmin();
}

// ── RENDU ──────────────────────────────────────────────
require '../app/views/header.php';

$php_pages  = ['home', 'activites', 'connexion', 'inscription', 'creer', 'detail',
               'profil', 'profil_edit', 'faq', 'contact',
               'admin', 'admin_users', 'admin_activities', 'owner'];
$html_pages = ['cgu', 'mentions'];

if (in_array($page, $php_pages)) {
    require "pages/{$page}.php";
} elseif (in_array($page, $html_pages)) {
    require "pages/{$page}.html";
} else {
    require 'pages/home.php';
}

require '../app/views/footer.php';
