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
if ($page === 'connexion' && $_SERVER['REQUEST_METHOD'] === 'POST') { // Traite le formulaire uniquement si on est sur la page connexion avec une requête POST
    csrf_check();  // Vérifie le token anti-CSRF avant tout traitement du formulaire

    $email    = trim($_POST['email'] ?? '');    // Récupère l'email soumis et supprime les espaces superflus
    $password = $_POST['password'] ?? '';       // Récupère le mot de passe (sans trim pour ne pas altérer les espaces intentionnels)

    // Clés de session pour le rate limiting par email (md5 pour éviter les caractères invalides dans la clé)
    $attempt_key = 'login_attempts_' . md5($email); // Clé unique de session pour compter les tentatives de cet email
    $block_key   = 'login_blocked_'  . md5($email); // Clé unique de session pour stocker le timestamp de fin de blocage

    // Vérification du blocage temporaire (15 min après 5 tentatives échouées)
    if (!empty($_SESSION[$block_key]) && time() < $_SESSION[$block_key]) { // Vérifie si un blocage est actif et pas encore expiré
        $mins  = ceil(($_SESSION[$block_key] - time()) / 60);  // Calcule le nombre de minutes restantes avant déblocage (arrondi au-dessus)
        $error = "Trop de tentatives échouées. Réessayez dans {$mins} minute(s)."; // Informe l'utilisateur du délai d'attente restant

    } elseif (empty($email) || empty($password)) { // Vérifie que les deux champs obligatoires sont remplis
        $error = "Veuillez remplir tous les champs."; // Message d'erreur si un champ est vide

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // Vérifie que l'email a un format valide (contient @, un domaine, etc.)
        $error = "Adresse e-mail invalide."; // Message d'erreur si le format de l'email est incorrect

    } else {
        $userModel = new User($pdo);             // Instancie le modèle User pour accéder aux méthodes de la base de données
        $user = $userModel->findByEmail($email); // Recherche l'utilisateur en base par son adresse email

        // password_verify compare le mot de passe soumis au hash stocké en base
        if ($user && password_verify($password, $user['mot_de_passe'])) { // Vérifie que l'utilisateur existe et que le mot de passe correspond au hash en base
            if (!empty($user['is_banned'])) { // Vérifie si le compte a été suspendu par un administrateur
                // L'utilisateur est suspendu : on lui indique pourquoi la connexion échoue
                $error = "Votre compte a été suspendu. Contactez l'administrateur."; // Informe l'utilisateur que son compte est banni
            } else {
                // Connexion réussie : on réinitialise les compteurs de tentatives
                unset($_SESSION[$attempt_key], $_SESSION[$block_key]); // Supprime les clés de rate limiting puisque la connexion est réussie

                // session_regenerate_id(true) génère un nouvel ID de session et supprime l'ancien.
                // Empêche les attaques de fixation de session (session fixation attack).
                session_regenerate_id(true); // Crée un nouvel identifiant de session pour empêcher la réutilisation d'un ID volé

                // On ne stocke en session que les données nécessaires (pas le mot de passe hashé)
                $_SESSION['user'] = [ // Initialise le tableau de session avec les informations de l'utilisateur connecté
                    'id'     => $user['idusers'],                              // Identifiant unique de l'utilisateur en base de données
                    'nom'    => $user['nom'],                                  // Nom de famille de l'utilisateur
                    'prenom' => $user['prenom'],                               // Prénom de l'utilisateur
                    'pseudo' => $user['pseudo'] ?? $user['prenom'],            // Pseudo affiché (fallback sur prénom si pas de pseudo)
                    'email'  => $user['email'],                                // Adresse email (utile pour les actions nécessitant l'email)
                    'role'   => $user['role'],                                 // Rôle de l'utilisateur ('user', 'admin', etc.) pour les vérifications d'accès
                ];
                $_SESSION['flash'] = "Bienvenue, " . htmlspecialchars($user['prenom']) . " !"; // Prépare un message de bienvenue à afficher sur la prochaine page
                header('Location: /sharetime/public/');  // Redirige vers la page d'accueil après connexion réussie
                exit; // Stoppe l'exécution du script pour que la redirection soit prise en compte
            }
        } else {
            // Identifiants incorrects : on incrémente le compteur de tentatives
            $_SESSION[$attempt_key] = ($_SESSION[$attempt_key] ?? 0) + 1; // Incrémente le compteur de tentatives échouées pour cet email
            if ($_SESSION[$attempt_key] >= 5) { // Vérifie si le seuil de 5 tentatives échouées est atteint
                // 5 tentatives atteintes : blocage de 15 minutes (time() + 15*60 secondes)
                $_SESSION[$block_key]   = time() + 15 * 60; // Enregistre le timestamp de fin de blocage (heure actuelle + 900 secondes)
                $_SESSION[$attempt_key] = 0;                 // Remet le compteur à zéro pour le prochain cycle de blocage
                $error = "Trop de tentatives échouées. Compte temporairement bloqué pour 15 minutes."; // Informe l'utilisateur du blocage
            } else {
                $remaining = 5 - $_SESSION[$attempt_key]; // Calcule le nombre de tentatives restantes avant le blocage
                $error = "Email ou mot de passe incorrect. ({$remaining} tentative(s) restante(s))"; // Affiche le nombre de tentatives restantes pour avertir l'utilisateur
            }
        }
    }
}

// ── INSCRIPTION ────────────────────────────────────────────────────────────────
if ($page === 'inscription' && $_SERVER['REQUEST_METHOD'] === 'POST') { // Traite le formulaire d'inscription uniquement si on est sur la bonne page avec une requête POST
    csrf_check(); // Vérifie le token anti-CSRF pour empêcher les soumissions depuis d'autres sites

    // Récupération et nettoyage des données du formulaire
    $prenom    = trim($_POST['firstname'] ?? '');          // Récupère et nettoie le prénom soumis
    $nom       = trim($_POST['lastname']  ?? '');          // Récupère et nettoie le nom de famille soumis
    $pseudo    = trim($_POST['username']  ?? '');          // Récupère et nettoie le pseudo souhaité
    $ville     = trim($_POST['city']      ?? '');          // Récupère et nettoie la ville (optionnel)
    $email     = trim($_POST['email']     ?? '');          // Récupère et nettoie l'adresse email soumise
    $password  = $_POST['password']          ?? '';        // Récupère le mot de passe (sans trim pour conserver les espaces éventuels)
    $confirm   = $_POST['confirm-password']  ?? '';        // Récupère la confirmation du mot de passe pour vérifier la saisie
    $birthdate = $_POST['birthdate']         ?? '';        // Récupère la date de naissance au format texte (optionnel)
    $terms     = isset($_POST['terms']);  // Vérifie si la case d'acceptation des CGU a été cochée

    // Validations en cascade (chaque condition bloque le traitement si elle échoue)
    if (empty($prenom) || empty($nom) || empty($pseudo) || empty($email) || empty($password)) { // Vérifie que tous les champs obligatoires sont remplis
        $error = "Veuillez remplir tous les champs obligatoires."; // Message d'erreur générique pour les champs manquants
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // Vérifie le format de l'email avec le filtre natif PHP
        $error = "Adresse e-mail invalide."; // Informe l'utilisateur que son email n'a pas le bon format
    } elseif ($password !== $confirm) { // Vérifie que le mot de passe et sa confirmation sont identiques
        $error = "Les mots de passe ne correspondent pas."; // Informe que les deux saisies du mot de passe diffèrent
    } elseif (strlen($password) < 8) { // Vérifie que le mot de passe fait au moins 8 caractères
        $error = "Le mot de passe doit contenir au moins 8 caractères."; // Informe de la longueur minimale requise
    } elseif (!preg_match('/[A-Z]/', $password)) { // Vérifie la présence d'au moins une lettre majuscule dans le mot de passe
        $error = "Le mot de passe doit contenir au moins une lettre majuscule."; // Informe de l'exigence de majuscule
    } elseif (!preg_match('/[a-z]/', $password)) { // Vérifie la présence d'au moins une lettre minuscule dans le mot de passe
        $error = "Le mot de passe doit contenir au moins une lettre minuscule."; // Informe de l'exigence de minuscule
    } elseif (!preg_match('/[0-9]/', $password)) { // Vérifie la présence d'au moins un chiffre dans le mot de passe
        $error = "Le mot de passe doit contenir au moins un chiffre."; // Informe de l'exigence d'un chiffre
    } elseif (!$terms) { // Vérifie que l'utilisateur a accepté les conditions générales d'utilisation
        $error = "Vous devez accepter les conditions générales d'utilisation."; // Informe que l'acceptation des CGU est obligatoire
    } else {
        $userModel = new User($pdo); // Instancie le modèle User pour interagir avec la table users en base
        if ($userModel->emailExists($email)) { // Vérifie si un compte existe déjà avec cette adresse email
            // On ne dit pas "compte déjà existant" pour éviter l'énumération d'emails
            // Note : ici on le dit quand même pour une meilleure UX en dev — à sécuriser en prod
            $error = "Cet email est déjà utilisé."; // Informe que l'email est déjà associé à un compte existant
        } else {
            // Création du compte (le hash du mot de passe est fait dans User::create)
            $new_user_id = $userModel->create([ // Crée l'utilisateur en base et récupère l'ID auto-incrémenté
                'prenom'         => $prenom,                   // Prénom de l'utilisateur
                'nom'            => $nom,                      // Nom de famille de l'utilisateur
                'pseudo'         => $pseudo,                   // Pseudo choisi lors de l'inscription
                'email'          => $email,                    // Adresse email qui servira d'identifiant de connexion
                'password'       => $password,                 // Mot de passe en clair (sera hashé dans User::create)
                'ville'          => $ville,                    // Ville de résidence (peut être vide)
                'date_naissance' => $birthdate ?: null,        // Date de naissance ou null si non renseignée
                'cgu_acceptees'  => true,                      // Marque l'acceptation des CGU au moment de l'inscription
            ]);

            // Génération du token de vérification email (64 hex = 32 octets aléatoires)
            $token = bin2hex(random_bytes(32)); // Génère un token aléatoire cryptographiquement sûr de 64 caractères hexadécimaux
            $pdo->prepare("INSERT INTO email_verifications (user_id, token) VALUES (:u, :t)") // Prépare la requête d'insertion du token de vérification
                ->execute(['u' => $new_user_id, 't' => $token]); // Insère le token en base lié à l'ID du nouvel utilisateur

            // Lien de vérification envoyé par email (valable 24h)
            $verify_link = 'http://' . $_SERVER['HTTP_HOST'] . '/sharetime/public/?page=verifier_email&token=' . $token; // Construit l'URL complète de vérification d'email avec le token unique
            $mail_sent = @mail(  // @ supprime les warnings si mail() n'est pas configuré (dev)
                $email,          // Destinataire : l'adresse email de l'utilisateur qui vient de s'inscrire
                '[ShareTime] Vérifiez votre adresse e-mail', // Objet de l'email de vérification
                "Bonjour {$prenom},\n\nBienvenue sur ShareTime !\n\nCliquez sur le lien suivant pour vérifier votre adresse e-mail (valable 24h) :\n\n{$verify_link}\n\nL'équipe ShareTime", // Corps de l'email avec le lien de vérification
                "From: noreply@sharetime.fr\r\nContent-Type: text/plain; charset=utf-8" // En-têtes : expéditeur et encodage du contenu
            );

            if (!$mail_sent) { // Vérifie si l'envoi de l'email a échoué (typiquement en environnement de développement sans serveur mail)
                // En mode développement (XAMPP sans serveur mail), on affiche le lien directement
                // dans le toast pour pouvoir tester la vérification sans configurer un mailer.
                $_SESSION['flash_html'] = "Compte créé ! <strong>Mode dev</strong> — <a href=\"" . htmlspecialchars($verify_link) . "\" style=\"color:var(--orange);font-weight:600;\">Vérifier l'email →</a>"; // Affiche le lien cliquable directement dans l'interface pour contourner l'absence de mailer
            } else {
                $_SESSION['flash'] = "Compte créé ! Un email de confirmation a été envoyé à {$email}."; // Informe l'utilisateur qu'un email de vérification lui a été envoyé
            }
            header('Location: /sharetime/public/?page=connexion'); // Redirige vers la page de connexion après inscription réussie
            exit; // Stoppe l'exécution du script pour que la redirection soit effective
        }
    }
}

// ── MOT DE PASSE OUBLIÉ ────────────────────────────────────────────────────────
if ($page === 'mot_de_passe_oublie' && $_SERVER['REQUEST_METHOD'] === 'POST') { // Traite la demande de réinitialisation de mot de passe
    csrf_check(); // Vérifie le token anti-CSRF avant de traiter la demande
    $reset_email = trim($_POST['email'] ?? ''); // Récupère et nettoie l'email saisi dans le formulaire de récupération

    if (empty($reset_email) || !filter_var($reset_email, FILTER_VALIDATE_EMAIL)) { // Vérifie que l'email est présent et a un format valide
        $error = "Veuillez saisir une adresse e-mail valide."; // Message d'erreur si l'email est absent ou mal formaté
    } else {
        $userModel  = new User($pdo);                        // Instancie le modèle User pour interroger la base
        $userExists = $userModel->emailExists($reset_email); // Vérifie discrètement si un compte existe avec cet email

        if ($userExists) { // N'envoie l'email et ne crée le token que si le compte existe réellement
            // Supprime les anciens tokens pour cet email avant d'en créer un nouveau
            // (un seul token actif à la fois par utilisateur)
            $pdo->prepare("DELETE FROM password_resets WHERE email = :email") // Prépare la suppression des anciens tokens de réinitialisation pour cet email
                ->execute(['email' => $reset_email]); // Exécute la suppression pour garantir l'unicité du token actif

            $token   = bin2hex(random_bytes(32));                        // Génère un token aléatoire sécurisé de 64 caractères hexadécimaux
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));  // Calcule la date d'expiration du token (dans 1 heure depuis maintenant)
            $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires)") // Prépare l'insertion du nouveau token de réinitialisation
                ->execute(['email' => $reset_email, 'token' => $token, 'expires' => $expires]); // Insère le token avec sa date d'expiration en base

            $reset_link = 'http://' . $_SERVER['HTTP_HOST'] . '/sharetime/public/?page=reinitialiser_mdp&token=' . $token; // Construit l'URL complète de réinitialisation contenant le token unique
            $subject    = '[ShareTime] Réinitialisation de votre mot de passe';                                             // Objet de l'email de réinitialisation
            $body       = "Bonjour,\n\nCliquez sur le lien suivant pour réinitialiser votre mot de passe (valable 1 heure) :\n\n{$reset_link}\n\nSi vous n'avez pas demandé cette réinitialisation, ignorez ce message.\n\nL'équipe ShareTime"; // Corps de l'email avec le lien et une mention de sécurité
            $headers    = "From: noreply@sharetime.fr\r\nContent-Type: text/plain; charset=utf-8"; // En-têtes de l'email : expéditeur et encodage
            @mail($reset_email, $subject, $body, $headers); // Envoie l'email de réinitialisation (@ pour ignorer les erreurs en dev)
        }

        // Même message si l'email existe ou pas : évite l'énumération des emails enregistrés
        // (un attaquant ne peut pas savoir si un compte existe en regardant la réponse)
        $success = "Si un compte est associé à cet email, vous recevrez un lien de réinitialisation dans quelques minutes."; // Message neutre affiché dans tous les cas pour ne pas révéler l'existence d'un compte
    }
}

// ── RÉINITIALISATION DU MOT DE PASSE ──────────────────────────────────────────
if ($page === 'reinitialiser_mdp' && $_SERVER['REQUEST_METHOD'] === 'POST') { // Traite le formulaire de choix du nouveau mot de passe (accessible via le lien reçu par email)
    csrf_check(); // Vérifie le token anti-CSRF avant tout traitement
    $token    = trim($_POST['token'] ?? '');   // Récupère le token de réinitialisation transmis via le champ caché du formulaire
    $new_pass = $_POST['password'] ?? '';      // Récupère le nouveau mot de passe saisi par l'utilisateur
    $confirm  = $_POST['confirm']  ?? '';      // Récupère la confirmation du nouveau mot de passe

    if (empty($token)) { // Vérifie que le token est présent (champ caché du formulaire)
        $error = "Token invalide."; // Message d'erreur si le token est absent
    } elseif (strlen($new_pass) < 8) { // Vérifie que le nouveau mot de passe fait au moins 8 caractères
        $error = "Le mot de passe doit contenir au moins 8 caractères."; // Informe de la longueur minimale requise
    } elseif (!preg_match('/[A-Z]/', $new_pass)) { // Vérifie la présence d'au moins une majuscule dans le nouveau mot de passe
        $error = "Le mot de passe doit contenir au moins une lettre majuscule."; // Informe de l'exigence de majuscule
    } elseif (!preg_match('/[a-z]/', $new_pass)) { // Vérifie la présence d'au moins une minuscule dans le nouveau mot de passe
        $error = "Le mot de passe doit contenir au moins une lettre minuscule."; // Informe de l'exigence de minuscule
    } elseif (!preg_match('/[0-9]/', $new_pass)) { // Vérifie la présence d'au moins un chiffre dans le nouveau mot de passe
        $error = "Le mot de passe doit contenir au moins un chiffre."; // Informe de l'exigence d'un chiffre
    } elseif ($new_pass !== $confirm) { // Vérifie que le nouveau mot de passe et sa confirmation sont identiques
        $error = "Les mots de passe ne correspondent pas."; // Informe que les deux saisies ne correspondent pas
    } else {
        // Vérifie que le token existe, n'a pas déjà été utilisé (used=0), et n'est pas expiré
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = :token AND used = 0 AND expires_at > NOW()"); // Prépare la requête qui valide le token (non utilisé et non expiré)
        $stmt->execute(['token' => $token]); // Exécute la recherche du token en base de données
        $reset = $stmt->fetch(); // Récupère la ligne correspondante (false si aucun résultat)

        if (!$reset) { // Vérifie que le token est valide (existe, non utilisé, non expiré)
            $error = "Ce lien est invalide ou a expiré. Veuillez faire une nouvelle demande."; // Informe que le lien n'est plus utilisable
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT); // Hache le nouveau mot de passe avec l'algorithme recommandé par PHP (bcrypt par défaut)
            $pdo->prepare("UPDATE users SET mot_de_passe = :hash WHERE email = :email") // Prépare la mise à jour du mot de passe en base
                ->execute(['hash' => $hash, 'email' => $reset['email']]); // Remplace l'ancien hash par le nouveau pour l'email associé au token

            // Marque le token comme utilisé (used=1) plutôt que de le supprimer :
            // permet de détecter les tentatives de réutilisation du même lien
            $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = :token") // Prépare la désactivation du token après usage
                ->execute(['token' => $token]); // Marque ce token comme utilisé pour empêcher toute réutilisation

            $_SESSION['flash'] = "Mot de passe réinitialisé avec succès. Vous pouvez vous connecter."; // Prépare un message de succès pour la page de connexion
            header('Location: /sharetime/public/?page=connexion'); // Redirige vers la page de connexion après la réinitialisation
            exit; // Stoppe l'exécution du script pour que la redirection soit prise en compte
        }
    }
}

// ── VÉRIFICATION EMAIL (GET) ───────────────────────────────────────────────────
// Ce handler est déclenché via un lien cliqué dans l'email, donc méthode GET (pas POST).
if ($page === 'verifier_email' && $_SERVER['REQUEST_METHOD'] === 'GET') { // Traite la vérification d'email via le lien cliqué dans l'email de confirmation
    $token = trim($_GET['token'] ?? ''); // Récupère le token passé en paramètre GET dans l'URL du lien de vérification
    if (empty($token)) { // Vérifie que le token est présent dans l'URL
        $_SESSION['flash']      = "Lien de vérification invalide."; // Prépare un message d'erreur pour l'utilisateur
        $_SESSION['flash_type'] = 'error';                          // Indique que le flash est de type erreur (pour le style CSS)
        header('Location: /sharetime/public/');                     // Redirige vers l'accueil si le token est absent
        exit; // Stoppe l'exécution immédiatement après la redirection
    }

    // Vérifie que le token existe et a moins de 24h (pas de colonne expires_at : on calcule via created_at)
    $stmt = $pdo->prepare("SELECT * FROM email_verifications WHERE token = :t AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"); // Prépare la requête qui vérifie la validité du token (non expiré selon created_at)
    $stmt->execute(['t' => $token]); // Exécute la recherche du token en base
    $verif = $stmt->fetch(); // Récupère la ligne du token (false si token inconnu ou expiré)

    if (!$verif) { // Vérifie que le token est valide et non expiré
        $_SESSION['flash']      = "Lien invalide ou expiré. Demandez un nouvel envoi depuis votre profil."; // Informe l'utilisateur que le lien n'est plus valide
        $_SESSION['flash_type'] = 'error';                          // Indique que le flash est de type erreur
        header('Location: /sharetime/public/?page=connexion');      // Redirige vers la connexion pour que l'utilisateur puisse demander un renvoi
        exit; // Stoppe l'exécution après la redirection
    }

    // Active le compte et supprime le token (usage unique)
    $pdo->prepare("UPDATE users SET email_verified = 1 WHERE idusers = :id")->execute(['id' => $verif['user_id']]); // Active le flag email_verified pour débloquer les fonctionnalités nécessitant un email vérifié
    $pdo->prepare("DELETE FROM email_verifications WHERE user_id = :id")->execute(['id' => $verif['user_id']]);     // Supprime le token de vérification utilisé pour qu'il ne puisse pas être réutilisé

    // Synchronise la session si l'utilisateur était déjà connecté au moment de la vérification
    if (isset($_SESSION['user']) && (int)$_SESSION['user']['id'] === (int)$verif['user_id']) { // Vérifie si l'utilisateur est connecté et que c'est bien son propre compte qu'il vérifie
        $_SESSION['user']['email_verified'] = 1; // Met à jour le flag dans la session pour que la vérification soit immédiatement prise en compte sans reconnexion
    }
    $_SESSION['flash'] = "Email vérifié avec succès ! Vous pouvez maintenant vous connecter."; // Prépare un message de succès pour la page de connexion
    header('Location: /sharetime/public/?page=connexion'); // Redirige vers la connexion après validation de l'email
    exit; // Stoppe l'exécution du script
}

// ── RENVOYER LE LIEN DE VÉRIFICATION ──────────────────────────────────────────
if ($page === 'renvoyer_verification' && $_SERVER['REQUEST_METHOD'] === 'POST') { // Traite la demande de renvoi du lien de vérification d'email
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; } // Refuse l'accès si l'utilisateur n'est pas connecté
    csrf_check(); // Vérifie le token anti-CSRF avant de traiter la demande

    $user_id     = (int)$_SESSION['user']['id'];     // Récupère l'ID de l'utilisateur connecté depuis la session
    $user_email  = $_SESSION['user']['email'];        // Récupère l'email de l'utilisateur connecté pour l'envoi
    $user_prenom = $_SESSION['user']['prenom'];       // Récupère le prénom pour personnaliser le corps de l'email

    // Supprime l'ancien token avant d'en créer un nouveau (un seul token actif à la fois)
    $pdo->prepare("DELETE FROM email_verifications WHERE user_id = :id")->execute(['id' => $user_id]); // Supprime l'éventuel ancien token pour garantir qu'un seul lien de vérification est actif

    $token = bin2hex(random_bytes(32)); // Génère un nouveau token aléatoire sécurisé de 64 caractères
    $pdo->prepare("INSERT INTO email_verifications (user_id, token) VALUES (:u, :t)") // Prépare l'insertion du nouveau token de vérification
        ->execute(['u' => $user_id, 't' => $token]); // Insère le token en base lié à l'utilisateur connecté

    $verify_link = 'http://' . $_SERVER['HTTP_HOST'] . '/sharetime/public/?page=verifier_email&token=' . $token; // Construit l'URL complète de vérification avec le nouveau token
    $mail_sent = @mail( // Tente l'envoi de l'email de vérification (@ pour ignorer les erreurs en développement)
        $user_email, // Destinataire : l'email de l'utilisateur connecté
        '[ShareTime] Vérifiez votre adresse e-mail', // Objet de l'email
        "Bonjour {$user_prenom},\n\nCliquez sur le lien suivant pour vérifier votre adresse e-mail (valable 24h) :\n\n{$verify_link}\n\nL'équipe ShareTime", // Corps de l'email avec le lien de vérification
        "From: noreply@sharetime.fr\r\nContent-Type: text/plain; charset=utf-8" // En-têtes de l'email
    );

    if (!$mail_sent) { // Vérifie si l'envoi a échoué (typiquement en développement sans serveur mail configuré)
        $_SESSION['flash_html'] = "Mode dev — <a href=\"" . htmlspecialchars($verify_link) . "\" style=\"color:var(--orange);font-weight:600;\">Vérifier l'email →</a>"; // Affiche le lien cliquable directement dans l'interface en mode développement
    } else {
        $_SESSION['flash'] = "Email de vérification renvoyé avec succès !"; // Informe l'utilisateur que l'email a bien été renvoyé
    }
    header('Location: /sharetime/public/?page=profil'); // Redirige vers la page de profil après le renvoi
    exit; // Stoppe l'exécution du script
}

// ── DÉCONNEXION ────────────────────────────────────────────────────────────────
if ($page === 'logout') { // Traite la déconnexion de l'utilisateur
    $_SESSION = [];       // Efface toutes les données de session (cookie conservé, données supprimées)
    session_destroy();    // Détruit la session côté serveur (supprime le fichier de session)
    header('Location: /sharetime/public/'); // Redirige vers la page d'accueil après déconnexion
    exit; // Stoppe l'exécution du script
}
