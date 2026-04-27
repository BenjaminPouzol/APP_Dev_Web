<?php
// Handlers utilisateur — inclus depuis public/index.php

// ── ÉDITION DU PROFIL ──────────────────────────────────────────
if ($page === 'profil_edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $pseudo = trim($_POST['pseudo'] ?? '');
    $ville  = trim($_POST['ville'] ?? '');
    $bio    = trim($_POST['bio'] ?? '');

    $upload_dir_prof = dirname(__DIR__, 2) . '/public/uploads/profils/';
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

// ── CONTACT ────────────────────────────────────────────────────
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

// ── SUIVRE / NE PLUS SUIVRE ────────────────────────────────────
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

// ── ENVOYER UN MESSAGE PRIVÉ ───────────────────────────────────
if ($page === 'envoyer_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $receiver_id = intval($_POST['receiver_id'] ?? 0);
    $content     = trim($_POST['content'] ?? '');
    $me          = (int)$_SESSION['user']['id'];

    if ($receiver_id <= 0 || $receiver_id === $me) {
        header('Location: /sharetime/public/?page=messages'); exit;
    }
    if (empty($content)) {
        $_SESSION['flash']      = "Le message ne peut pas être vide.";
        $_SESSION['flash_type'] = 'error';
        header('Location: /sharetime/public/?page=messages&with=' . $receiver_id); exit;
    }
    if (mb_strlen($content) > 1000) {
        $_SESSION['flash']      = "Message trop long (max 1000 caractères).";
        $_SESSION['flash_type'] = 'error';
        header('Location: /sharetime/public/?page=messages&with=' . $receiver_id); exit;
    }

    $um = new User($pdo);
    $receiver = $um->getById($receiver_id);
    if (!$receiver || !empty($receiver['is_banned'])) {
        header('Location: /sharetime/public/?page=messages'); exit;
    }

    $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)")
        ->execute([$me, $receiver_id, $content]);

    header('Location: /sharetime/public/?page=messages&with=' . $receiver_id);
    exit;
}

// ── MARQUER NOTIFICATIONS LUES ─────────────────────────────────
if ($page === 'notifs_lues' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :u")
        ->execute(['u' => $_SESSION['user']['id']]);
    header('Location: /sharetime/public/?page=notifications');
    exit;
}
