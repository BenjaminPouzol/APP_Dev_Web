<?php
/**
 * app/handlers/user.php — Handlers des actions utilisateur
 *
 * Inclus inconditionnellement par public/index.php avant le routing.
 * Chaque bloc vérifie $page et REQUEST_METHOD avant d'agir.
 *
 * Gère : édition de profil, formulaire de contact public, follow/unfollow,
 *        envoi de message privé, signalement d'utilisateur,
 *        et marquage de toutes les notifications comme lues.
 */

// ── ÉDITION DU PROFIL ──────────────────────────────────────────────────────────
if ($page === 'profil_edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // Seuls les utilisateurs connectés peuvent modifier leur propre profil
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    // Lecture et nettoyage des champs du formulaire d'édition
    $new_pseudo = trim($_POST['pseudo'] ?? '');  // nouveau pseudo (champ obligatoire)
    $new_ville  = trim($_POST['ville']  ?? '');  // nouvelle ville de résidence (optionnel)
    $new_bio    = trim($_POST['bio']    ?? '');  // nouvelle biographie de présentation (optionnel)

    // Chemin absolu vers le dossier où les photos de profil sont stockées
    $profils_upload_dir   = dirname(__DIR__, 2) . '/public/uploads/profils/';
    $uploaded_profil_photo = null;  // sera rempli si un fichier est soumis dans le formulaire

    // Tentative d'upload : retourne null si aucun fichier, lève RuntimeException si format/taille invalides
    try { $uploaded_profil_photo = upload_image('photo_profil', $profils_upload_dir); }
    catch (\RuntimeException $e) { $error = $e->getMessage(); }  // le message d'erreur sera affiché dans le formulaire

    if (empty($error)) {
        if (empty($new_pseudo)) {
            // Le pseudo est le seul champ obligatoire ; les autres peuvent rester vides
            $error = "Le pseudo ne peut pas être vide.";
        } else {
            $userModel    = new User($pdo);
            $profile_data = ['pseudo' => $new_pseudo, 'ville' => $new_ville, 'bio' => $new_bio];

            if ($uploaded_profil_photo !== null) {
                // Supprime l'ancienne photo du disque avant d'enregistrer la nouvelle
                // pour éviter d'accumuler des fichiers orphelins dans uploads/profils/
                $current_profile = $userModel->getById($_SESSION['user']['id']);
                if (!empty($current_profile['photo_profil'])) {
                    @unlink($profils_upload_dir . $current_profile['photo_profil']);  // @ supprime les warnings si le fichier n'existe plus
                }
                // Ajoute la nouvelle photo au tableau de mise à jour
                $profile_data['photo_profil'] = $uploaded_profil_photo;
            }
            // User::update ne touche à photo_profil que si la clé est présente dans $profile_data
            $userModel->update($_SESSION['user']['id'], $profile_data);

            // Synchronise immédiatement le pseudo dans la session pour que la navbar
            // affiche le nouveau pseudo sans attendre la prochaine reconnexion
            $_SESSION['user']['pseudo'] = $new_pseudo;
            $_SESSION['flash'] = "Profil mis à jour avec succès.";
            header('Location: /sharetime/public/?page=profil');
            exit;
        }
    }
}

// ── CONTACT ────────────────────────────────────────────────────────────────────
if ($page === 'contact' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();  // Accessible sans être connecté, mais protégé contre le CSRF

    // Lecture et nettoyage des champs du formulaire de contact public
    $contact_sender_name    = trim($_POST['name']    ?? '');  // nom de la personne qui envoie le message
    $contact_sender_email   = trim($_POST['email']   ?? '');  // email de l'expéditeur, pour pouvoir lui répondre
    $contact_subject        = trim($_POST['subject'] ?? '');  // sujet du message (optionnel)
    $contact_message_body   = trim($_POST['message'] ?? '');  // contenu textuel du message

    if (empty($contact_sender_name) || empty($contact_sender_email) || empty($contact_message_body)) {
        // Nom, email et message sont obligatoires ; le sujet peut être vide
        $error = "Veuillez remplir tous les champs obligatoires.";

    } elseif (!filter_var($contact_sender_email, FILTER_VALIDATE_EMAIL)) {
        // Vérifie le format de l'email avant d'essayer d'envoyer quoi que ce soit
        $error = "Adresse e-mail invalide.";

    } else {
        // Stocke le message en base pour qu'il apparaisse dans le panel admin/contact
        $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (:name, :email, :subject, :message)")
            ->execute([
                'name'    => $contact_sender_name,
                'email'   => $contact_sender_email,
                'subject' => $contact_subject,
                'message' => $contact_message_body,
            ]);

        // Envoie également un email de notification à l'adresse d'administration
        $admin_recipient_email = 'admin@sharetime.fr';
        $notification_subject  = '[ShareTime] ' . ($contact_subject ?: 'Nouveau message de contact');
        $notification_body     = "Nom : {$contact_sender_name}\nEmail : {$contact_sender_email}\n\n{$contact_message_body}";

        // Reply-To permet à l'admin de répondre directement à l'expéditeur depuis son client mail
        $email_headers = "From: noreply@sharetime.fr\r\nReply-To: {$contact_sender_email}";
        @mail($admin_recipient_email, $notification_subject, $notification_body, $email_headers);

        // Message de succès affiché dans la vue après le traitement du formulaire
        $success = "Votre message a bien été reçu. Nous vous répondrons rapidement.";
    }
}

// ── SUIVRE / NE PLUS SUIVRE ────────────────────────────────────────────────────
if ($page === 'suivre' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    $target_user_id  = intval($_POST['user_id'] ?? 0);  // ID de l'utilisateur à suivre ou ne plus suivre
    $current_user_id = (int)$_SESSION['user']['id'];     // ID de l'utilisateur connecté (celui qui agit)

    // Interdit de se suivre soi-même (target !== current) et rejette les IDs invalides (> 0)
    if ($target_user_id > 0 && $target_user_id !== $current_user_id) {
        $userModel = new User($pdo);

        // Toggle : si déjà abonné → désabonnement, sinon → abonnement + notification
        if ($userModel->isFollowing($current_user_id, $target_user_id)) {
            // Désabonnement : supprime la ligne dans la table followers
            $userModel->unfollow($current_user_id, $target_user_id);
        } else {
            // Abonnement : insère dans followers puis notifie l'utilisateur suivi
            $userModel->follow($current_user_id, $target_user_id);
            // htmlspecialchars protège contre une injection XSS si le pseudo contient des caractères spéciaux
            $follower_display_name = htmlspecialchars($_SESSION['user']['pseudo'] ?? $_SESSION['user']['prenom']);
            notify($pdo, $target_user_id, 'nouveau_follower', 'Nouvel abonné',
                $follower_display_name . ' a commencé à vous suivre.');
        }
    }

    // Redirige vers le profil de l'utilisateur ciblé (pas vers son propre profil)
    header('Location: /sharetime/public/?page=profil&id=' . $target_user_id);
    exit;
}

// ── ENVOYER UN MESSAGE PRIVÉ ───────────────────────────────────────────────────
if ($page === 'envoyer_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    $receiver_user_id = intval($_POST['receiver_id'] ?? 0);  // ID du destinataire du message privé
    $message_content  = trim($_POST['content']       ?? ''); // texte du message à envoyer
    $current_user_id  = (int)$_SESSION['user']['id'];        // ID de l'expéditeur (utilisateur connecté)

    // Refuse les messages vers soi-même et les IDs de destinataire invalides (0 ou négatifs)
    if ($receiver_user_id <= 0 || $receiver_user_id === $current_user_id) {
        header('Location: /sharetime/public/?page=messages'); exit;
    }

    if (empty($message_content)) {
        // Message vide : affiche une erreur et retourne à la conversation en cours
        $_SESSION['flash']      = "Le message ne peut pas être vide.";
        $_SESSION['flash_type'] = 'error';
        header('Location: /sharetime/public/?page=messages&with=' . $receiver_user_id); exit;
    }

    if (mb_strlen($message_content) > 1000) {
        // mb_strlen est nécessaire pour compter correctement les caractères multi-octets
        // (accents, emojis, caractères asiatiques…) — strlen() compterait les octets, pas les caractères
        $_SESSION['flash']      = "Message trop long (max 1000 caractères).";
        $_SESSION['flash_type'] = 'error';
        header('Location: /sharetime/public/?page=messages&with=' . $receiver_user_id); exit;
    }

    $userModel     = new User($pdo);
    $receiver_user = $userModel->getById($receiver_user_id);  // données du destinataire (vérification existence + ban)

    // Refuse d'envoyer un message à un compte suspendu ou inexistant en base
    if (!$receiver_user || !empty($receiver_user['is_banned'])) {
        header('Location: /sharetime/public/?page=messages'); exit;
    }

    // Insère le message ; les paramètres positionnels (?) sont dans l'ordre : sender, receiver, content
    $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)")
        ->execute([$current_user_id, $receiver_user_id, $message_content]);

    // Redirige vers la conversation pour que l'utilisateur voie immédiatement son message envoyé
    header('Location: /sharetime/public/?page=messages&with=' . $receiver_user_id);
    exit;
}

// ── SIGNALEMENT D'UN UTILISATEUR ──────────────────────────────────────────────
if ($page === 'signaler' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    $current_user_id  = (int)$_SESSION['user']['id'];          // ID de l'utilisateur qui signale
    $reported_user_id = intval($_POST['signale_id'] ?? 0);     // ID de l'utilisateur signalé
    $report_reason    = trim($_POST['motif'] ?? '');            // motif du signalement (obligatoire)
    $redirect_after   = trim($_POST['redirect'] ?? '/sharetime/public/');  // URL de retour après le signalement

    // Interdit de se signaler soi-même et rejette les IDs invalides
    if ($reported_user_id <= 0 || $reported_user_id === $current_user_id) {
        $_SESSION['flash']      = "Signalement invalide.";
        $_SESSION['flash_type'] = 'error';
        header('Location: ' . $redirect_after); exit;
    }

    if (empty($report_reason)) {
        // Le motif est obligatoire pour permettre à la modération de traiter le signalement efficacement
        $_SESSION['flash']      = "Veuillez indiquer un motif.";
        $_SESSION['flash_type'] = 'error';
        header('Location: ' . $redirect_after); exit;
    }

    // Vérifie s'il existe déjà un signalement en attente de cet utilisateur contre la même cible
    // pour éviter les doublons et le spam de signalement
    $duplicate_report_check = $pdo->prepare(
        "SELECT COUNT(*) FROM reports WHERE signaleur_id = :s AND signale_id = :t AND status = 'en_attente'"
    );
    $duplicate_report_check->execute(['s' => $current_user_id, 't' => $reported_user_id]);
    if ($duplicate_report_check->fetchColumn()) {
        $_SESSION['flash']      = "Vous avez déjà signalé cet utilisateur (signalement en attente).";
        $_SESSION['flash_type'] = 'error';
        header('Location: ' . $redirect_after); exit;
    }

    // Crée le nouveau signalement avec le statut 'en_attente' (valeur par défaut de la colonne en base)
    $pdo->prepare("INSERT INTO reports (signaleur_id, signale_id, motif) VALUES (:s, :t, :m)")
        ->execute(['s' => $current_user_id, 't' => $reported_user_id, 'm' => $report_reason]);

    $_SESSION['flash'] = "Signalement envoyé. L'équipe le traitera dans les plus brefs délais.";
    header('Location: ' . $redirect_after);
    exit;
}

// ── MARQUER TOUTES LES NOTIFICATIONS COMME LUES ───────────────────────────────
if ($page === 'notifs_lues' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    // Met is_read = 1 sur TOUTES les notifications de l'utilisateur en une seule requête
    // Le badge de notification dans la navbar affichera 0 dès la prochaine requête
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :u")
        ->execute(['u' => $_SESSION['user']['id']]);

    // Redirige vers la liste des notifications pour que l'utilisateur voie la mise à jour
    header('Location: /sharetime/public/?page=notifications');
    exit;
}
