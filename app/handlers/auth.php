<?php
/**
 * app/handlers/auth.php — Handlers d'authentification
 *
 * Inclus inconditionnellement par public/index.php avant le routing.
 * Chaque bloc if vérifie lui-même la page courante ($page) et la méthode HTTP
 * avant d'agir : si les conditions ne correspondent pas, le bloc est ignoré.
 *
 * Gère : connexion, inscription, mot de passe oublié, réinitialisation du mot de passe,
 *        vérification d'email, renvoi du lien de vérification, et déconnexion.
 */

// ── CONNEXION ──────────────────────────────────────────────────────────────────
if ($page === 'connexion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();  // Vérifie le token anti-CSRF avant tout traitement du formulaire

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Clés de session pour le rate limiting par email (md5 pour éviter les caractères invalides dans la clé)
    $attempt_key = 'login_attempts_' . md5($email);
    $block_key   = 'login_blocked_'  . md5($email);

    // Vérification du blocage temporaire (15 min après 5 tentatives échouées)
    if (!empty($_SESSION[$block_key]) && time() < $_SESSION[$block_key]) {
        $mins  = ceil(($_SESSION[$block_key] - time()) / 60);  // minutes restantes avant déblocage
        $error = "Trop de tentatives échouées. Réessayez dans {$mins} minute(s).";

    } elseif (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse e-mail invalide.";

    } else {
        $userModel = new User($pdo);
        $user = $userModel->findByEmail($email);

        // password_verify compare le mot de passe soumis au hash stocké en base
        if ($user && password_verify($password, $user['mot_de_passe'])) {
            if (!empty($user['is_banned'])) {
                // L'utilisateur est suspendu : on lui indique pourquoi la connexion échoue
                $error = "Votre compte a été suspendu. Contactez l'administrateur.";
            } else {
                // Connexion réussie : on réinitialise les compteurs de tentatives
                unset($_SESSION[$attempt_key], $_SESSION[$block_key]);

                // session_regenerate_id(true) génère un nouvel ID de session et supprime l'ancien.
                // Empêche les attaques de fixation de session (session fixation attack).
                session_regenerate_id(true);

                // On ne stocke en session que les données nécessaires (pas le mot de passe hashé)
                $_SESSION['user'] = [
                    'id'     => $user['idusers'],
                    'nom'    => $user['nom'],
                    'prenom' => $user['prenom'],
                    'pseudo' => $user['pseudo'] ?? $user['prenom'],  // fallback sur prénom si pas de pseudo
                    'email'  => $user['email'],
                    'role'   => $user['role'],
                ];
                $_SESSION['flash'] = "Bienvenue, " . htmlspecialchars($user['prenom']) . " !";
                header('Location: /sharetime/public/');
                exit;
            }
        } else {
            // Identifiants incorrects : on incrémente le compteur de tentatives
            $_SESSION[$attempt_key] = ($_SESSION[$attempt_key] ?? 0) + 1;
            if ($_SESSION[$attempt_key] >= 5) {
                // 5 tentatives atteintes : blocage de 15 minutes (time() + 15*60 secondes)
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

// ── INSCRIPTION ────────────────────────────────────────────────────────────────
if ($page === 'inscription' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Récupération et nettoyage des données du formulaire
    $prenom    = trim($_POST['firstname'] ?? '');
    $nom       = trim($_POST['lastname']  ?? '');
    $pseudo    = trim($_POST['username']  ?? '');
    $ville     = trim($_POST['city']      ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']          ?? '';
    $confirm   = $_POST['confirm-password']  ?? '';
    $birthdate = $_POST['birthdate']         ?? '';
    $terms     = isset($_POST['terms']);  // case à cocher CGU

    // Validations en cascade (chaque condition bloque le traitement si elle échoue)
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
            // On ne dit pas "compte déjà existant" pour éviter l'énumération d'emails
            // Note : ici on le dit quand même pour une meilleure UX en dev — à sécuriser en prod
            $error = "Cet email est déjà utilisé.";
        } else {
            // Création du compte (le hash du mot de passe est fait dans User::create)
            $new_user_id = $userModel->create([
                'prenom'         => $prenom,
                'nom'            => $nom,
                'pseudo'         => $pseudo,
                'email'          => $email,
                'password'       => $password,
                'ville'          => $ville,
                'date_naissance' => $birthdate ?: null,
                'cgu_acceptees'  => true,
            ]);

            // Génération du token de vérification email (64 hex = 32 octets aléatoires)
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("INSERT INTO email_verifications (user_id, token) VALUES (:u, :t)")
                ->execute(['u' => $new_user_id, 't' => $token]);

            // Lien de vérification envoyé par email (valable 24h)
            $verify_link = 'http://' . $_SERVER['HTTP_HOST'] . '/sharetime/public/?page=verifier_email&token=' . $token;
            $mail_sent = @mail(  // @ supprime les warnings si mail() n'est pas configuré (dev)
                $email,
                '[ShareTime] Vérifiez votre adresse e-mail',
                "Bonjour {$prenom},\n\nBienvenue sur ShareTime !\n\nCliquez sur le lien suivant pour vérifier votre adresse e-mail (valable 24h) :\n\n{$verify_link}\n\nL'équipe ShareTime",
                "From: noreply@sharetime.fr\r\nContent-Type: text/plain; charset=utf-8"
            );

            if (!$mail_sent) {
                // En mode développement (XAMPP sans serveur mail), on affiche le lien directement
                // dans le toast pour pouvoir tester la vérification sans configurer un mailer.
                $_SESSION['flash_html'] = "Compte créé ! <strong>Mode dev</strong> — <a href=\"" . htmlspecialchars($verify_link) . "\" style=\"color:var(--orange);font-weight:600;\">Vérifier l'email →</a>";
            } else {
                $_SESSION['flash'] = "Compte créé ! Un email de confirmation a été envoyé à {$email}.";
            }
            header('Location: /sharetime/public/?page=connexion');
            exit;
        }
    }
}

// ── MOT DE PASSE OUBLIÉ ────────────────────────────────────────────────────────
if ($page === 'mot_de_passe_oublie' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $reset_email = trim($_POST['email'] ?? '');

    if (empty($reset_email) || !filter_var($reset_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Veuillez saisir une adresse e-mail valide.";
    } else {
        $userModel  = new User($pdo);
        $userExists = $userModel->emailExists($reset_email);

        if ($userExists) {
            // Supprime les anciens tokens pour cet email avant d'en créer un nouveau
            // (un seul token actif à la fois par utilisateur)
            $pdo->prepare("DELETE FROM password_resets WHERE email = :email")
                ->execute(['email' => $reset_email]);

            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));  // valide 1 heure
            $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires)")
                ->execute(['email' => $reset_email, 'token' => $token, 'expires' => $expires]);

            $reset_link = 'http://' . $_SERVER['HTTP_HOST'] . '/sharetime/public/?page=reinitialiser_mdp&token=' . $token;
            $subject    = '[ShareTime] Réinitialisation de votre mot de passe';
            $body       = "Bonjour,\n\nCliquez sur le lien suivant pour réinitialiser votre mot de passe (valable 1 heure) :\n\n{$reset_link}\n\nSi vous n'avez pas demandé cette réinitialisation, ignorez ce message.\n\nL'équipe ShareTime";
            $headers    = "From: noreply@sharetime.fr\r\nContent-Type: text/plain; charset=utf-8";
            @mail($reset_email, $subject, $body, $headers);
        }

        // Même message si l'email existe ou pas : évite l'énumération des emails enregistrés
        // (un attaquant ne peut pas savoir si un compte existe en regardant la réponse)
        $success = "Si un compte est associé à cet email, vous recevrez un lien de réinitialisation dans quelques minutes.";
    }
}

// ── RÉINITIALISATION DU MOT DE PASSE ──────────────────────────────────────────
if ($page === 'reinitialiser_mdp' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $token    = trim($_POST['token'] ?? '');
    $new_pass = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (empty($token)) {
        $error = "Token invalide.";
    } elseif (strlen($new_pass) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif ($new_pass !== $confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        // Vérifie que le token existe, n'a pas déjà été utilisé (used=0), et n'est pas expiré
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = :token AND used = 0 AND expires_at > NOW()");
        $stmt->execute(['token' => $token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $error = "Ce lien est invalide ou a expiré. Veuillez faire une nouvelle demande.";
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET mot_de_passe = :hash WHERE email = :email")
                ->execute(['hash' => $hash, 'email' => $reset['email']]);

            // Marque le token comme utilisé (used=1) plutôt que de le supprimer :
            // permet de détecter les tentatives de réutilisation du même lien
            $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = :token")
                ->execute(['token' => $token]);

            $_SESSION['flash'] = "Mot de passe réinitialisé avec succès. Vous pouvez vous connecter.";
            header('Location: /sharetime/public/?page=connexion');
            exit;
        }
    }
}

// ── VÉRIFICATION EMAIL (GET) ───────────────────────────────────────────────────
// Ce handler est déclenché via un lien cliqué dans l'email, donc méthode GET (pas POST).
if ($page === 'verifier_email' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = trim($_GET['token'] ?? '');
    if (empty($token)) {
        $_SESSION['flash']      = "Lien de vérification invalide.";
        $_SESSION['flash_type'] = 'error';
        header('Location: /sharetime/public/');
        exit;
    }

    // Vérifie que le token existe et a moins de 24h (pas de colonne expires_at : on calcule via created_at)
    $stmt = $pdo->prepare("SELECT * FROM email_verifications WHERE token = :t AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute(['t' => $token]);
    $verif = $stmt->fetch();

    if (!$verif) {
        $_SESSION['flash']      = "Lien invalide ou expiré. Demandez un nouvel envoi depuis votre profil.";
        $_SESSION['flash_type'] = 'error';
        header('Location: /sharetime/public/?page=connexion');
        exit;
    }

    // Active le compte et supprime le token (usage unique)
    $pdo->prepare("UPDATE users SET email_verified = 1 WHERE idusers = :id")->execute(['id' => $verif['user_id']]);
    $pdo->prepare("DELETE FROM email_verifications WHERE user_id = :id")->execute(['id' => $verif['user_id']]);

    // Synchronise la session si l'utilisateur était déjà connecté au moment de la vérification
    if (isset($_SESSION['user']) && (int)$_SESSION['user']['id'] === (int)$verif['user_id']) {
        $_SESSION['user']['email_verified'] = 1;
    }
    $_SESSION['flash'] = "Email vérifié avec succès ! Vous pouvez maintenant vous connecter.";
    header('Location: /sharetime/public/?page=connexion');
    exit;
}

// ── RENVOYER LE LIEN DE VÉRIFICATION ──────────────────────────────────────────
if ($page === 'renvoyer_verification' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    $user_id     = (int)$_SESSION['user']['id'];
    $user_email  = $_SESSION['user']['email'];
    $user_prenom = $_SESSION['user']['prenom'];

    // Supprime l'ancien token avant d'en créer un nouveau (un seul token actif à la fois)
    $pdo->prepare("DELETE FROM email_verifications WHERE user_id = :id")->execute(['id' => $user_id]);

    $token = bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO email_verifications (user_id, token) VALUES (:u, :t)")
        ->execute(['u' => $user_id, 't' => $token]);

    $verify_link = 'http://' . $_SERVER['HTTP_HOST'] . '/sharetime/public/?page=verifier_email&token=' . $token;
    $mail_sent = @mail(
        $user_email,
        '[ShareTime] Vérifiez votre adresse e-mail',
        "Bonjour {$user_prenom},\n\nCliquez sur le lien suivant pour vérifier votre adresse e-mail (valable 24h) :\n\n{$verify_link}\n\nL'équipe ShareTime",
        "From: noreply@sharetime.fr\r\nContent-Type: text/plain; charset=utf-8"
    );

    if (!$mail_sent) {
        $_SESSION['flash_html'] = "Mode dev — <a href=\"" . htmlspecialchars($verify_link) . "\" style=\"color:var(--orange);font-weight:600;\">Vérifier l'email →</a>";
    } else {
        $_SESSION['flash'] = "Email de vérification renvoyé avec succès !";
    }
    header('Location: /sharetime/public/?page=profil');
    exit;
}

// ── DÉCONNEXION ────────────────────────────────────────────────────────────────
if ($page === 'logout') {
    $_SESSION = [];       // Efface toutes les données de session (cookie conservé, données supprimées)
    session_destroy();    // Détruit la session côté serveur (supprime le fichier de session)
    header('Location: /sharetime/public/');
    exit;
}
