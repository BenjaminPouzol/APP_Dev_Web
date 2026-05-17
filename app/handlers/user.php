<?php
/**
 * app/handlers/user.php — Handlers des actions utilisateur
 *
 * Inclus inconditionnellement par public/index.php avant le routing.
 * Chaque bloc vérifie $page et REQUEST_METHOD avant d'agir.
 *
 * Gère : édition de profil, formulaire de contact, follow/unfollow,
 *        envoi de message privé, marquage des notifications comme lues.
 */

// ── ÉDITION DU PROFIL ──────────────────────────────────────────────────────────
if ($page === 'profil_edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    $pseudo = trim($_POST['pseudo'] ?? '');
    $ville  = trim($_POST['ville']  ?? '');
    $bio    = trim($_POST['bio']    ?? '');

    // Dossier de destination pour les photos de profil
    $upload_dir_prof  = dirname(__DIR__, 2) . '/public/uploads/profils/';
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
                // Supprime l'ancienne photo du disque avant d'enregistrer la nouvelle
                $old_prof = $userModel->getById($_SESSION['user']['id']);
                if (!empty($old_prof['photo_profil'])) @unlink($upload_dir_prof . $old_prof['photo_profil']);
                $update_data['photo_profil'] = $photo_profil_new;
            }

            $userModel->update($_SESSION['user']['id'], $update_data);

            // Synchronise le pseudo dans la session pour que la navbar l'affiche immédiatement
            $_SESSION['user']['pseudo'] = $pseudo;
            $_SESSION['flash'] = "Profil mis à jour avec succès.";
            header('Location: /sharetime/public/?page=profil');
            exit;
        }
    }
}

// ── CONTACT ────────────────────────────────────────────────────────────────────
if ($page === 'contact' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();  // Accessible même non connecté, mais protégé contre le CSRF

    $contact_name    = trim($_POST['name']    ?? '');
    $contact_email   = trim($_POST['email']   ?? '');
    $contact_subject = trim($_POST['subject'] ?? '');
    $contact_message = trim($_POST['message'] ?? '');

    if (empty($contact_name) || empty($contact_email) || empty($contact_message)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse e-mail invalide.";
    } else {
        // Stocke le message en base (permet à l'admin de le consulter dans un futur panel)
        $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (:name, :email, :subject, :message)")
            ->execute(['name' => $contact_name, 'email' => $contact_email, 'subject' => $contact_subject, 'message' => $contact_message]);

        // Envoie également un email à l'adresse admin
        $to      = 'admin@sharetime.fr';
        $subject = '[ShareTime] ' . ($contact_subject ?: 'Nouveau message de contact');
        $body    = "Nom : {$contact_name}\nEmail : {$contact_email}\n\n{$contact_message}";
        // Reply-To permet à l'admin de répondre directement à l'expéditeur en un clic
        $headers = "From: noreply@sharetime.fr\r\nReply-To: {$contact_email}";
        @mail($to, $subject, $body, $headers);

        $success = "Votre message a bien été reçu. Nous vous répondrons rapidement.";
    }
}

// ── SUIVRE / NE PLUS SUIVRE ────────────────────────────────────────────────────
if ($page === 'suivre' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    $target_id = intval($_POST['user_id'] ?? 0);
    $me        = (int)$_SESSION['user']['id'];

    if ($target_id > 0 && $target_id !== $me) {  // on ne peut pas se suivre soi-même
        $um = new User($pdo);

        // Toggle : si déjà suivi → unfollow, sinon → follow + notification
        if ($um->isFollowing($me, $target_id)) {
            $um->unfollow($me, $target_id);
        } else {
            $um->follow($me, $target_id);
            // Notifie la cible qu'un utilisateur vient de la suivre
            notify($pdo, $target_id, 'nouveau_follower', 'Nouvel abonné',
                htmlspecialchars($_SESSION['user']['pseudo'] ?? $_SESSION['user']['prenom']) . ' a commencé à vous suivre.');
        }
    }
    // Redirige vers le profil de la cible (pas le profil de l'utilisateur connecté)
    header('Location: /sharetime/public/?page=profil&id=' . $target_id);
    exit;
}

// ── ENVOYER UN MESSAGE PRIVÉ ───────────────────────────────────────────────────
if ($page === 'envoyer_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    $receiver_id = intval($_POST['receiver_id'] ?? 0);
    $content     = trim($_POST['content']       ?? '');
    $me          = (int)$_SESSION['user']['id'];

    // Vérifie que le destinataire est valide et différent de l'expéditeur
    if ($receiver_id <= 0 || $receiver_id === $me) {
        header('Location: /sharetime/public/?page=messages'); exit;
    }

    if (empty($content)) {
        $_SESSION['flash']      = "Le message ne peut pas être vide.";
        $_SESSION['flash_type'] = 'error';
        header('Location: /sharetime/public/?page=messages&with=' . $receiver_id); exit;
    }

    if (mb_strlen($content) > 1000) {
        // mb_strlen compte correctement les caractères multi-octets (accents, emojis…)
        $_SESSION['flash']      = "Message trop long (max 1000 caractères).";
        $_SESSION['flash_type'] = 'error';
        header('Location: /sharetime/public/?page=messages&with=' . $receiver_id); exit;
    }

    $um       = new User($pdo);
    $receiver = $um->getById($receiver_id);

    // Refuse d'envoyer un message à un utilisateur suspendu ou inexistant
    if (!$receiver || !empty($receiver['is_banned'])) {
        header('Location: /sharetime/public/?page=messages'); exit;
    }

    $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)")
        ->execute([$me, $receiver_id, $content]);

    // Redirige vers la conversation pour que l'utilisateur voie son message envoyé
    header('Location: /sharetime/public/?page=messages&with=' . $receiver_id);
    exit;
}

// ── SIGNALEMENT D'UN UTILISATEUR ──────────────────────────────────────────────
if ($page === 'signaler' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    $me        = (int)$_SESSION['user']['id'];
    $signale_id = intval($_POST['signale_id'] ?? 0);
    $motif      = trim($_POST['motif'] ?? '');
    $redirect   = trim($_POST['redirect'] ?? '/sharetime/public/');

    if ($signale_id <= 0 || $signale_id === $me) {
        $_SESSION['flash']      = "Signalement invalide.";
        $_SESSION['flash_type'] = 'error';
        header('Location: ' . $redirect); exit;
    }

    if (empty($motif)) {
        $_SESSION['flash']      = "Veuillez indiquer un motif.";
        $_SESSION['flash_type'] = 'error';
        header('Location: ' . $redirect); exit;
    }

    // Empêche un doublon : un seul signalement en attente par (signaleur, signalé)
    $exists = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE signaleur_id = :s AND signale_id = :t AND status = 'en_attente'");
    $exists->execute(['s' => $me, 't' => $signale_id]);
    if ($exists->fetchColumn()) {
        $_SESSION['flash']      = "Vous avez déjà signalé cet utilisateur (signalement en attente).";
        $_SESSION['flash_type'] = 'error';
        header('Location: ' . $redirect); exit;
    }

    $pdo->prepare("INSERT INTO reports (signaleur_id, signale_id, motif) VALUES (:s, :t, :m)")
        ->execute(['s' => $me, 't' => $signale_id, 'm' => $motif]);

    $_SESSION['flash'] = "Signalement envoyé. L'équipe le traitera dans les plus brefs délais.";
    header('Location: ' . $redirect);
    exit;
}

// ── MARQUER TOUTES LES NOTIFICATIONS COMME LUES ───────────────────────────────
if ($page === 'notifs_lues' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    // Met toutes les notifications de l'utilisateur à is_read = 1
    // Le compteur dans la navbar sera donc à 0 à la prochaine page
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :u")
        ->execute(['u' => $_SESSION['user']['id']]);

    header('Location: /sharetime/public/?page=notifications');
    exit;
}
