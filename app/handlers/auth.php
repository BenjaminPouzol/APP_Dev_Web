<?php
// Handlers d'authentification — inclus depuis public/index.php

// ── CONNEXION ──────────────────────────────────────────────────
if ($page === 'connexion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

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

// ── INSCRIPTION ────────────────────────────────────────────────
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

            // Génération du token de vérification email
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("INSERT INTO email_verifications (user_id, token) VALUES (:u, :t)")
                ->execute(['u' => $new_user_id, 't' => $token]);

            $verify_link = 'http://' . $_SERVER['HTTP_HOST'] . '/sharetime/public/?page=verifier_email&token=' . $token;
            $mail_sent = @mail(
                $email,
                '[ShareTime] Vérifiez votre adresse e-mail',
                "Bonjour {$prenom},\n\nBienvenue sur ShareTime !\n\nCliquez sur le lien suivant pour vérifier votre adresse e-mail (valable 24h) :\n\n{$verify_link}\n\nL'équipe ShareTime",
                "From: noreply@sharetime.fr\r\nContent-Type: text/plain; charset=utf-8"
            );

            if (!$mail_sent) {
                // Mode développement — afficher le lien directement dans le toast
                $_SESSION['flash_html'] = "Compte créé ! <strong>Mode dev</strong> — <a href=\"" . htmlspecialchars($verify_link) . "\" style=\"color:var(--orange);font-weight:600;\">Vérifier l'email →</a>";
            } else {
                $_SESSION['flash'] = "Compte créé ! Un email de confirmation a été envoyé à {$email}.";
            }
            header('Location: /sharetime/public/?page=connexion');
            exit;
        }
    }
}

// ── MOT DE PASSE OUBLIÉ ────────────────────────────────────────
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

// ── RÉINITIALISATION DU MOT DE PASSE ──────────────────────────
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

// ── VÉRIFICATION EMAIL ─────────────────────────────────────────
if ($page === 'verifier_email' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = trim($_GET['token'] ?? '');
    if (empty($token)) {
        $_SESSION['flash']      = "Lien de vérification invalide.";
        $_SESSION['flash_type'] = 'error';
        header('Location: /sharetime/public/');
        exit;
    }
    $stmt = $pdo->prepare("SELECT * FROM email_verifications WHERE token = :t AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute(['t' => $token]);
    $verif = $stmt->fetch();
    if (!$verif) {
        $_SESSION['flash']      = "Lien invalide ou expiré. Demandez un nouvel envoi depuis votre profil.";
        $_SESSION['flash_type'] = 'error';
        header('Location: /sharetime/public/?page=connexion');
        exit;
    }
    $pdo->prepare("UPDATE users SET email_verified = 1 WHERE idusers = :id")->execute(['id' => $verif['user_id']]);
    $pdo->prepare("DELETE FROM email_verifications WHERE user_id = :id")->execute(['id' => $verif['user_id']]);
    // Mettre à jour la session si l'utilisateur est connecté
    if (isset($_SESSION['user']) && (int)$_SESSION['user']['id'] === (int)$verif['user_id']) {
        $_SESSION['user']['email_verified'] = 1;
    }
    $_SESSION['flash'] = "Email vérifié avec succès ! Vous pouvez maintenant vous connecter.";
    header('Location: /sharetime/public/?page=connexion');
    exit;
}

// ── RENVOYER VÉRIFICATION ──────────────────────────────────────
if ($page === 'renvoyer_verification' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $user_id    = (int)$_SESSION['user']['id'];
    $user_email = $_SESSION['user']['email'];
    $user_prenom = $_SESSION['user']['prenom'];

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

// ── DÉCONNEXION ────────────────────────────────────────────────
if ($page === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: /sharetime/public/');
    exit;
}
