<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

require '../config/database.php';
require '../app/models/Activity.php';
require '../app/models/User.php';

// ── CONTRÔLE DE BAN EN SESSION ────────────────────────
// Vérifie toutes les 10 requêtes si l'utilisateur n'a pas été banni
if (isset($_SESSION['user'])) {
    $_SESSION['_req_count'] = ($_SESSION['_req_count'] ?? 0) + 1;
    if ($_SESSION['_req_count'] >= 10) {
        $_SESSION['_req_count'] = 0;
        $stmt_ban = $pdo->prepare("SELECT is_banned, role FROM users WHERE idusers = :id");
        $stmt_ban->execute(['id' => $_SESSION['user']['id']]);
        $row_ban = $stmt_ban->fetch();
        if ($row_ban) {
            if (!empty($row_ban['is_banned'])) {
                $_SESSION = [];
                session_destroy();
                header('Location: /sharetime/public/?page=connexion');
                exit;
            }
            $_SESSION['user']['role'] = $row_ban['role'];
        }
    }
}

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

$flash      = $_SESSION['flash']      ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash'], $_SESSION['flash_type']);

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

// ── Notifications ───────────────────────────────────────
function notify(PDO $pdo, int $user_id, string $type, string $title, string $content, ?int $activity_id = null): void {
    try {
        $pdo->prepare("INSERT INTO notifications (user_id, activity_id, type, title, content) VALUES (:u, :a, :t, :ti, :c)")
            ->execute(['u' => $user_id, 'a' => $activity_id, 't' => $type, 'ti' => $title, 'c' => $content]);
    } catch (\Throwable $e) {}
}

// ── Upload image ────────────────────────────────────────
function upload_image(string $field, string $dest_dir): ?string {
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) throw new \RuntimeException("Format non supporté. Utilisez JPG, PNG, GIF ou WebP.");
    if ($_FILES[$field]['size'] > 2 * 1024 * 1024) throw new \RuntimeException("Image trop volumineuse (max 2 Mo).");
    $filename = uniqid('img_', true) . '.' . $allowed[$mime];
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], rtrim($dest_dir, '/') . '/' . $filename)) {
        throw new \RuntimeException("Erreur lors de l'enregistrement de l'image.");
    }
    return $filename;
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

    // Rate limiting : 5 tentatives max, blocage 15 min
    $attempt_key = 'login_attempts_' . md5($email);
    $block_key   = 'login_blocked_' . md5($email);
    if (!empty($_SESSION[$block_key]) && time() < $_SESSION[$block_key]) {
        $mins  = ceil(($_SESSION[$block_key] - time()) / 60);
        $error = "Trop de tentatives échouées. Réessayez dans {$mins} minute(s).";
    } elseif (empty($email) || empty($password)) {
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
                unset($_SESSION[$attempt_key], $_SESSION[$block_key]);
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'     => $user['idusers'],
                    'nom'    => $user['nom'],
                    'prenom' => $user['prenom'],
                    'pseudo' => $user['pseudo'] ?? $user['prenom'],
                    'email'  => $user['email'],
                    'role'   => $user['role'],
                ];
                $_SESSION['flash'] = "Bienvenue, " . htmlspecialchars($user['prenom']) . " !";
                header('Location: /sharetime/public/');
                exit;
            }
        } else {
            $_SESSION[$attempt_key] = ($_SESSION[$attempt_key] ?? 0) + 1;
            if ($_SESSION[$attempt_key] >= 5) {
                $_SESSION[$block_key]   = time() + 15 * 60;
                $_SESSION[$attempt_key] = 0;
                $error = "Trop de tentatives échouées. Compte temporairement bloqué pour 15 minutes.";
            } else {
                $remaining = 5 - $_SESSION[$attempt_key];
                $error = "Email ou mot de passe incorrect. ({$remaining} tentative(s) restante(s))";
            }
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

// ── MOT DE PASSE OUBLIÉ ───────────────────────────────
if ($page === 'mot_de_passe_oublie' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $reset_email = trim($_POST['email'] ?? '');

    if (empty($reset_email) || !filter_var($reset_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Veuillez saisir une adresse e-mail valide.";
    } else {
        $userModel  = new User($pdo);
        $userExists = $userModel->emailExists($reset_email);

        if ($userExists) {
            $pdo->prepare("DELETE FROM password_resets WHERE email = :email")
                ->execute(['email' => $reset_email]);

            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires)")
                ->execute(['email' => $reset_email, 'token' => $token, 'expires' => $expires]);

            $reset_link = 'http://' . $_SERVER['HTTP_HOST'] . '/sharetime/public/?page=reinitialiser_mdp&token=' . $token;
            $subject    = '[ShareTime] Réinitialisation de votre mot de passe';
            $body       = "Bonjour,\n\nCliquez sur le lien suivant pour réinitialiser votre mot de passe (valable 1 heure) :\n\n{$reset_link}\n\nSi vous n'avez pas demandé cette réinitialisation, ignorez ce message.\n\nL'équipe ShareTime";
            $headers    = "From: noreply@sharetime.fr\r\nContent-Type: text/plain; charset=utf-8";
            @mail($reset_email, $subject, $body, $headers);
        }
        $success = "Si un compte est associé à cet email, vous recevrez un lien de réinitialisation dans quelques minutes.";
    }
}

// ── RÉINITIALISATION DU MOT DE PASSE ──────────────────
if ($page === 'reinitialiser_mdp' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $token    = trim($_POST['token'] ?? '');
    $new_pass = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (empty($token)) {
        $error = "Token invalide.";
    } elseif (strlen($new_pass) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif ($new_pass !== $confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = :token AND used = 0 AND expires_at > NOW()");
        $stmt->execute(['token' => $token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $error = "Ce lien est invalide ou a expiré. Veuillez faire une nouvelle demande.";
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET mot_de_passe = :hash WHERE email = :email")
                ->execute(['hash' => $hash, 'email' => $reset['email']]);
            $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = :token")
                ->execute(['token' => $token]);
            $_SESSION['flash'] = "Mot de passe réinitialisé avec succès. Vous pouvez vous connecter.";
            header('Location: /sharetime/public/?page=connexion');
            exit;
        }
    }
}

// ── MODIFIER ACTIVITÉ (ORGANISATEUR) ──────────────────
if ($page === 'modifier_activite' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $activity_id          = intval($_POST['activity_id'] ?? 0);
    $title                = trim($_POST['title'] ?? '');
    $description          = trim($_POST['description'] ?? '');
    $location             = trim($_POST['location'] ?? '');
    $city                 = trim($_POST['city'] ?? '');
    $start_time           = $_POST['start_time'] ?? '';
    $end_time             = $_POST['end_time'] ?? '';
    $max_participants     = intval($_POST['max_participants'] ?? 0);
    $visibility           = in_array($_POST['visibility'] ?? '', ['publique', 'privee']) ? $_POST['visibility'] : 'publique';
    $category             = in_array($_POST['category'] ?? '', array_keys($CATEGORY_MAP)) ? $_POST['category'] : 'autre';
    $liste_attente_active = isset($_POST['liste_attente_active']) ? 1 : 0;

    if (empty($title) || empty($description) || empty($location) || empty($city) || empty($start_time) || empty($end_time)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif ($max_participants < 2) {
        $error = "Le nombre de participants doit être d'au moins 2.";
    } elseif (strtotime($end_time) <= strtotime($start_time)) {
        $error = "La date de fin doit être postérieure à la date de début.";
    } else {
        $activityModel = new Activity($pdo);
        $existing      = $activityModel->getById($activity_id);
        if (!$existing || (int)$existing['creator_id'] !== (int)$_SESSION['user']['id'] || $existing['status'] !== 'active') {
            header('Location: /sharetime/public/?page=activites'); exit;
        }
        if ($max_participants < (int)$existing['nb_inscrits']) {
            $error = "Le nombre de participants ne peut pas être inférieur au nombre d'inscrits ({$existing['nb_inscrits']}).";
        } else {
            $upload_dir_act = dirname(__DIR__) . '/public/uploads/activites/';
            $photo_act = null;
            try { $photo_act = upload_image('photo', $upload_dir_act); } catch (\RuntimeException $e) { $error = $e->getMessage(); }
            if (empty($error)) {
                $update_data = [
                    'title'               => $title,
                    'description'         => $description,
                    'location'            => $location,
                    'city'                => $city,
                    'start_time'          => $start_time,
                    'end_time'            => $end_time,
                    'max_participants'    => $max_participants,
                    'visibility'          => $visibility,
                    'category'            => $category,
                    'liste_attente_active' => $liste_attente_active,
                    'creator_id'          => $_SESSION['user']['id'],
                ];
                if ($photo_act !== null) {
                    if (!empty($existing['photo'])) @unlink($upload_dir_act . $existing['photo']);
                    $update_data['photo'] = $photo_act;
                }
                $activityModel->update($activity_id, $update_data);
                // Notifier tous les inscrits
                foreach ($activityModel->getRegisteredUserIds($activity_id) as $uid) {
                    notify($pdo, (int)$uid, 'activite_modifiee', 'Activité modifiée',
                        "L'activité \"{$title}\" à laquelle vous êtes inscrit(e) a été modifiée.", $activity_id);
                }
                $_SESSION['flash'] = "Activité modifiée avec succès.";
                header('Location: /sharetime/public/?page=detail&id=' . $activity_id);
                exit;
            }
        }
    }
}

// ── ANNULER ACTIVITÉ (ORGANISATEUR) ────────────────────
if ($page === 'annuler_activite' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $activity_id = intval($_POST['activity_id'] ?? 0);
    if ($activity_id > 0) {
        $activityModel = new Activity($pdo);
        $act_to_cancel = $activityModel->getById($activity_id);
        if ($activityModel->cancelByOrganizer($activity_id, $_SESSION['user']['id'])) {
            // Notifier tous les inscrits
            if ($act_to_cancel) {
                foreach ($activityModel->getRegisteredUserIds($activity_id) as $uid) {
                    notify($pdo, (int)$uid, 'activite_annulee', 'Activité annulée',
                        "L'activité \"{$act_to_cancel['title']}\" à laquelle vous étiez inscrit(e) a été annulée.", $activity_id);
                }
            }
            $_SESSION['flash'] = "Votre activité a été annulée.";
        } else {
            $_SESSION['flash']      = "Impossible d'annuler cette activité.";
            $_SESSION['flash_type'] = 'error';
        }
    }
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id);
    exit;
}

// ── CRÉATION D'ACTIVITÉ ────────────────────────────────
if ($page === 'creer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $title               = trim($_POST['title'] ?? '');
    $description         = trim($_POST['description'] ?? '');
    $location            = trim($_POST['location'] ?? '');
    $city                = trim($_POST['city'] ?? '');
    $start_time          = $_POST['start_time'] ?? '';
    $end_time            = $_POST['end_time'] ?? '';
    $max_participants    = intval($_POST['max_participants'] ?? 0);
    $visibility          = in_array($_POST['visibility'] ?? '', ['publique', 'privee']) ? $_POST['visibility'] : 'publique';
    $category            = in_array($_POST['category'] ?? '', array_keys($CATEGORY_MAP)) ? $_POST['category'] : 'autre';
    $liste_attente_active = isset($_POST['liste_attente_active']) ? 1 : 0;

    $photo_creer = null;
    try { $photo_creer = upload_image('photo', dirname(__DIR__) . '/public/uploads/activites/'); }
    catch (\RuntimeException $e) { $error = $e->getMessage(); }

    if (empty($error)) {
        if (empty($title) || empty($description) || empty($location) || empty($city) || empty($start_time) || empty($end_time)) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } elseif ($max_participants < 2) {
            $error = "Le nombre de participants doit être d'au moins 2.";
        } elseif (strtotime($end_time) <= strtotime($start_time)) {
            $error = "La date de fin doit être postérieure à la date de début.";
        } else {
            $activityModel = new Activity($pdo);
            $activityModel->create([
                'title'               => $title,
                'description'         => $description,
                'photo'               => $photo_creer,
                'location'            => $location,
                'city'                => $city,
                'start_time'          => $start_time,
                'end_time'            => $end_time,
                'max_participants'    => $max_participants,
                'visibility'          => $visibility,
                'category'            => $category,
                'liste_attente_active' => $liste_attente_active,
                'creator_id'          => $_SESSION['user']['id'],
            ]);
            $_SESSION['flash'] = "Activité créée avec succès !";
            header('Location: /sharetime/public/?page=activites');
            exit;
        }
    }
}

// ── S'INSCRIRE ─────────────────────────────────────────
if ($page === 's_inscrire' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $activity_id = intval($_POST['activity_id'] ?? 0);
    if ($activity_id > 0) {
        $activityModel = new Activity($pdo);
        $activity      = $activityModel->getById($activity_id);
        $reg_status    = $activityModel->getRegistrationStatus($activity_id, $_SESSION['user']['id']);

        if ($activity && $activity['status'] === 'active' && (!$reg_status || $reg_status === 'annule')) {
            $pseudo = htmlspecialchars($_SESSION['user']['pseudo'] ?? $_SESSION['user']['prenom']);
            if ($activity['nb_inscrits'] < $activity['max_participants']) {
                $activityModel->register($activity_id, $_SESSION['user']['id']);
                notify($pdo, (int)$activity['creator_id'], 'nouvelle_inscription', 'Nouvelle inscription',
                    "{$pseudo} s'est inscrit(e) à votre activité \"{$activity['title']}\".", $activity_id);
            } elseif (!empty($activity['liste_attente_active'])) {
                $activityModel->registerWaitlist($activity_id, $_SESSION['user']['id']);
                $_SESSION['flash'] = "Activité complète. Vous avez été ajouté(e) à la liste d'attente.";
            }
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
        $activity      = $activityModel->getById($activity_id);
        $was_inscrit   = $activityModel->getRegistrationStatus($activity_id, $_SESSION['user']['id']) === 'inscrit';
        $activityModel->unregister($activity_id, $_SESSION['user']['id']);
        // Promouvoir le premier de la liste d'attente si la place se libère
        if ($was_inscrit && $activity && !empty($activity['liste_attente_active'])) {
            $promoted = $activityModel->promoteFromWaitlist($activity_id);
            if ($promoted) {
                notify($pdo, (int)$promoted, 'promotion_attente', 'Place libérée !',
                    "Vous avez été promu(e) de la liste d'attente pour \"{$activity['title']}\".", $activity_id);
            }
        }
    }
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id);
    exit;
}

// ── COMMENTER ──────────────────────────────────────────
if ($page === 'commenter' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $activity_id = intval($_POST['activity_id'] ?? 0);
    $content     = trim($_POST['content'] ?? '');
    if ($activity_id > 0 && $content !== '') {
        $activityModel = new Activity($pdo);
        $activityModel->addComment($activity_id, $_SESSION['user']['id'], $content);
    }
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id . '#comments');
    exit;
}

// ── SUPPRIMER COMMENTAIRE ──────────────────────────────
if ($page === 'supprimer_commentaire' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $comment_id  = intval($_POST['comment_id'] ?? 0);
    $activity_id = intval($_POST['activity_id'] ?? 0);
    if ($comment_id > 0) {
        $activityModel = new Activity($pdo);
        if (is_admin()) {
            $activityModel->deleteCommentAsAdmin($comment_id);
        } else {
            $activityModel->deleteComment($comment_id, $_SESSION['user']['id']);
        }
    }
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id . '#comments');
    exit;
}

// ── NOTER UN ORGANISATEUR ─────────────────────────────
if ($page === 'noter' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $activity_id = intval($_POST['activity_id'] ?? 0);
    $note        = intval($_POST['note'] ?? 0);
    if ($activity_id > 0 && $note >= 1 && $note <= 5) {
        $activityModel = new Activity($pdo);
        $activity      = $activityModel->getById($activity_id);
        if ($activity && $activity['status'] === 'terminee') {
            $reg_status = $activityModel->getRegistrationStatus($activity_id, $_SESSION['user']['id']);
            if ($reg_status === 'inscrit') {
                $activityModel->rate($_SESSION['user']['id'], $activity['creator_id'], $activity_id, $note);
            }
        }
    }
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id . '#rating');
    exit;
}

// ── ÉDITION DU PROFIL ──────────────────────────────────
if ($page === 'profil_edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $pseudo = trim($_POST['pseudo'] ?? '');
    $ville  = trim($_POST['ville'] ?? '');
    $bio    = trim($_POST['bio'] ?? '');

    $upload_dir_prof = dirname(__DIR__) . '/public/uploads/profils/';
    $photo_profil_new = null;
    try { $photo_profil_new = upload_image('photo_profil', $upload_dir_prof); }
    catch (\RuntimeException $e) { $error = $e->getMessage(); }

    if (empty($error)) {
        if (empty($pseudo)) {
            $error = "Le pseudo ne peut pas être vide.";
        } else {
            $userModel   = new User($pdo);
            $update_data = ['pseudo' => $pseudo, 'ville' => $ville, 'bio' => $bio];
            if ($photo_profil_new !== null) {
                $old_prof = $userModel->getById($_SESSION['user']['id']);
                if (!empty($old_prof['photo_profil'])) @unlink($upload_dir_prof . $old_prof['photo_profil']);
                $update_data['photo_profil'] = $photo_profil_new;
            }
            $userModel->update($_SESSION['user']['id'], $update_data);
            $_SESSION['user']['pseudo'] = $pseudo;
            $_SESSION['flash'] = "Profil mis à jour avec succès.";
            header('Location: /sharetime/public/?page=profil');
            exit;
        }
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
        $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (:name, :email, :subject, :message)")
            ->execute(['name' => $contact_name, 'email' => $contact_email, 'subject' => $contact_subject, 'message' => $contact_message]);

        $to      = 'admin@sharetime.fr';
        $subject = '[ShareTime] ' . ($contact_subject ?: 'Nouveau message de contact');
        $body    = "Nom : {$contact_name}\nEmail : {$contact_email}\n\n{$contact_message}";
        $headers = "From: noreply@sharetime.fr\r\nReply-To: {$contact_email}";
        @mail($to, $subject, $body, $headers);

        $success = "Votre message a bien été reçu. Nous vous répondrons rapidement.";
    }
}

// ── OWNER : TOUTES LES ACTIONS ─────────────────────────
if ($page === 'owner' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_owner();
    csrf_check();
    $action     = $_POST['action'] ?? '';
    $type       = $_POST['type']   ?? 'user';
    $valid_tabs = ['dashboard', 'users', 'activities', 'admins'];
    $tab        = in_array($_POST['tab'] ?? '', $valid_tabs) ? $_POST['tab'] : 'dashboard';
    $me         = (int)$_SESSION['user']['id'];

    if ($type === 'user') {
        $target_id = intval($_POST['user_id'] ?? 0);
        if ($target_id > 0 && $target_id !== $me) {
            $um = new User($pdo);
            if ($action === 'ban') {
                $um->setBanned($target_id, true);
            } elseif ($action === 'unban') {
                $um->setBanned($target_id, false);
            } elseif ($action === 'set_role') {
                $new_role = in_array($_POST['role'] ?? '', ['utilisateur', 'admin']) ? $_POST['role'] : null;
                if ($new_role) $um->setRole($target_id, $new_role);
            } elseif ($action === 'transfer_ownership') {
                if ($um->transferOwnership($target_id, $me)) {
                    $_SESSION['user']['role'] = 'admin';
                    $_SESSION['flash'] = "Propriété transférée avec succès.";
                    header('Location: /sharetime/public/?page=owner&tab=admins');
                    exit;
                }
                $_SESSION['flash'] = "Transfert impossible.";
                header('Location: /sharetime/public/?page=owner&tab=admins');
                exit;
            } elseif ($action === 'delete') {
                $um->delete($target_id);
            }
        }
    } elseif ($type === 'activity') {
        $activity_id = intval($_POST['activity_id'] ?? 0);
        if ($activity_id > 0) {
            $am = new Activity($pdo);
            if ($action === 'set_status') {
                $am->setStatus($activity_id, $_POST['status'] ?? '');
            } elseif ($action === 'delete') {
                $am->delete($activity_id);
            }
        }
    }
    $_SESSION['flash'] = "Action effectuée.";
    header('Location: /sharetime/public/?page=owner&tab=' . $tab);
    exit;
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
            if ($target_user && $target_user['role'] === 'admin') {
                $_SESSION['flash'] = "Vous n'avez pas le droit de suspendre un administrateur.";
                header('Location: /sharetime/public/?page=admin_users'); exit;
            }
            $um->setBanned($target_id, true);
        } elseif ($action === 'unban') {
            $target_user = $um->getById($target_id);
            if ($target_user && $target_user['role'] === 'admin') {
                $_SESSION['flash'] = "Vous n'avez pas le droit de réactiver un administrateur.";
                header('Location: /sharetime/public/?page=admin_users'); exit;
            }
            $um->setBanned($target_id, false);
        }
    }
    $_SESSION['flash'] = "Action effectuée.";
    header('Location: /sharetime/public/?page=admin_users');
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

// ── SUIVRE / NE PLUS SUIVRE ────────────────────────────
if ($page === 'suivre' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $target_id = intval($_POST['user_id'] ?? 0);
    $me        = (int)$_SESSION['user']['id'];
    if ($target_id > 0 && $target_id !== $me) {
        $um = new User($pdo);
        if ($um->isFollowing($me, $target_id)) {
            $um->unfollow($me, $target_id);
        } else {
            $um->follow($me, $target_id);
            notify($pdo, $target_id, 'nouveau_follower', 'Nouvel abonné',
                htmlspecialchars($_SESSION['user']['pseudo'] ?? $_SESSION['user']['prenom']) . ' a commencé à vous suivre.');
        }
    }
    header('Location: /sharetime/public/?page=profil&id=' . $target_id);
    exit;
}

// ── MARQUER NOTIFICATIONS LUES ─────────────────────────
if ($page === 'notifs_lues' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :u")
        ->execute(['u' => $_SESSION['user']['id']]);
    header('Location: /sharetime/public/?page=notifications');
    exit;
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
    's_inscrire', 'se_desinscrire', 'commenter', 'supprimer_commentaire', 'noter',
    'admin', 'admin_users', 'admin_activities', 'owner',
    'mot_de_passe_oublie', 'reinitialiser_mdp',
    'modifier_activite', 'notifications',
];

// Compte les notifications non lues (disponible globalement pour la navbar)
$notif_count = 0;
if (isset($_SESSION['user'])) {
    try {
        $stmt_nc = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0");
        $stmt_nc->execute(['u' => $_SESSION['user']['id']]);
        $notif_count = (int)$stmt_nc->fetchColumn();
    } catch (\Throwable $e) {}
}
if (!in_array($page, $allowed_pages)) $page = 'home';

$activityModel = new Activity($pdo);
$userModel     = new User($pdo);

// Données selon la page
$activities     = $user_activities = $user_registrations = $faq_items = [];
$activity       = $profile = null;
$reg_status     = null;
$waitlist_count = $waitlist_position = 0;
$comments       = [];
$has_rated      = false;
$city_filter    = $category_filter = $title_filter = $status_filter = '';
$current_page   = 1;
$total_pages    = 1;
$admin_stats    = $admin_users_list = $admin_activities_list = $owner_users = [];
$admin_current_page = 1;
$admin_total_pages  = 1;
$admin_total_count  = 0;
$follower_count  = $following_count = 0;
$is_following    = false;
$notifications   = [];

if ($page === 'home') {
    $activities = $activityModel->getAll('', $_SESSION['user']['id'] ?? null, '', 'active');

} elseif ($page === 'activites') {
    $city_filter     = trim($_GET['city'] ?? '');
    $raw_cat         = trim($_GET['category'] ?? '');
    $category_filter = isset($CATEGORY_MAP[$raw_cat]) ? $raw_cat : '';
    $title_filter    = trim($_GET['search'] ?? '');
    $valid_statuts   = ['active', 'annulee', 'terminee'];
    $status_filter   = in_array($_GET['statut'] ?? '', $valid_statuts) ? $_GET['statut'] : '';
    $current_page    = max(1, intval($_GET['p'] ?? 1));
    $per_page        = 12;
    $total_count     = $activityModel->countAll($city_filter, $_SESSION['user']['id'] ?? null, $category_filter, $status_filter, $title_filter);
    $total_pages     = max(1, (int)ceil($total_count / $per_page));
    $current_page    = min($current_page, $total_pages);
    $activities      = $activityModel->getAll($city_filter, $_SESSION['user']['id'] ?? null, $category_filter, $status_filter, $title_filter, $current_page, $per_page);

} elseif ($page === 'detail') {
    $activity_id = intval($_GET['id'] ?? 0);
    $activity    = $activity_id ? $activityModel->getById($activity_id) : null;
    if ($activity) {
        $comments = $activityModel->getComments($activity_id);
        if (isset($_SESSION['user'])) {
            $reg_status = $activityModel->getRegistrationStatus($activity_id, $_SESSION['user']['id']);
            if (!empty($activity['liste_attente_active'])) {
                $waitlist_count = $activityModel->getWaitlistCount($activity_id);
                if ($reg_status === 'en_attente') {
                    $waitlist_position = $activityModel->getWaitlistPosition($activity_id, $_SESSION['user']['id']);
                }
            }
            if ($activity['status'] === 'terminee' && $reg_status === 'inscrit') {
                $has_rated = $activityModel->hasRated($_SESSION['user']['id'], $activity['creator_id'], $activity_id);
            }
        }
    }

} elseif ($page === 'modifier_activite') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    $activity_id = intval($_GET['id'] ?? $_POST['activity_id'] ?? 0);
    $activity    = $activity_id ? $activityModel->getById($activity_id) : null;
    if (!$activity || (int)$activity['creator_id'] !== (int)$_SESSION['user']['id'] || $activity['status'] !== 'active') {
        header('Location: /sharetime/public/?page=activites'); exit;
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
    $follower_count  = $profile ? $userModel->getFollowerCount($profile_id) : 0;
    $following_count = $profile ? $userModel->getFollowingCount($profile_id) : 0;
    $is_following    = (isset($_SESSION['user']) && $profile && (int)$_SESSION['user']['id'] !== $profile_id)
                       ? $userModel->isFollowing((int)$_SESSION['user']['id'], $profile_id) : false;

} elseif ($page === 'profil_edit') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    $profile = $userModel->getById($_SESSION['user']['id']);

} elseif ($page === 'faq') {
    $faq_items = $pdo->query("SELECT * FROM faq ORDER BY idfaq ASC")->fetchAll();

} elseif ($page === 'creer' && !isset($_SESSION['user'])) {
    header('Location: /sharetime/public/?page=connexion'); exit;

} elseif ($page === 'admin') {
    if (is_owner()) { header('Location: /sharetime/public/?page=owner&tab=dashboard'); exit; }
    require_admin();
    $admin_stats = [
        'membres'      => $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'owner'")->fetchColumn(),
        'activites'    => $pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn(),
        'inscriptions' => $pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'inscrit'")->fetchColumn(),
        'admins'       => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
        'suspendus'    => $pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 1")->fetchColumn(),
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

} elseif ($page === 'notifications') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
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

} elseif ($page === 'owner') {
    require_owner();
    $valid_tabs  = ['dashboard', 'users', 'activities', 'admins'];
    $owner_tab   = in_array($_GET['tab'] ?? '', $valid_tabs) ? $_GET['tab'] : 'dashboard';
    $owner_users = $userModel->getAllForAdmin();
    $admin_activities_list = $activityModel->getAllForAdmin();
    $admin_stats = [
        'membres'      => $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'owner'")->fetchColumn(),
        'activites'    => $pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn(),
        'inscriptions' => $pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'inscrit'")->fetchColumn(),
        'admins'       => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
        'suspendus'    => $pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 1")->fetchColumn(),
    ];
    $admin_recent_users = $pdo->query("SELECT * FROM users ORDER BY date_creation DESC LIMIT 5")->fetchAll();
    $admin_recent_activities = $pdo->query("
        SELECT a.*, u.prenom, u.nom FROM activities a
        JOIN users u ON u.idusers = a.creator_id ORDER BY a.created_at DESC LIMIT 5
    ")->fetchAll();
}

// ── RENDU ──────────────────────────────────────────────
require '../app/views/header.php';

$php_pages = ['home', 'activites', 'connexion', 'inscription', 'creer', 'detail',
              'profil', 'profil_edit', 'faq', 'contact', 'cgu', 'mentions',
              'admin', 'admin_users', 'admin_activities', 'owner',
              'mot_de_passe_oublie', 'reinitialiser_mdp',
              'modifier_activite', 'notifications'];

if (in_array($page, $php_pages)) {
    require "pages/{$page}.php";
} else {
    require 'pages/home.php';
}

require '../app/views/footer.php';
