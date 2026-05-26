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
if ($page === 'profil_edit' && $_SERVER['REQUEST_METHOD'] === 'POST') { // Traite le formulaire d'édition de profil uniquement si on est sur la bonne page avec une requête POST

    // Seuls les utilisateurs connectés peuvent modifier leur propre profil
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; } // Redirige vers la connexion si l'utilisateur n'est pas authentifié
    csrf_check(); // Vérifie le token anti-CSRF avant de traiter la modification

    // Lecture et nettoyage des champs du formulaire d'édition
    $new_pseudo = trim($_POST['pseudo'] ?? '');  // Récupère et nettoie le nouveau pseudo (champ obligatoire)
    $new_ville  = trim($_POST['ville']  ?? '');  // Récupère et nettoie la nouvelle ville de résidence (optionnel)
    $new_bio    = trim($_POST['bio']    ?? '');  // Récupère et nettoie la nouvelle biographie de présentation (optionnel)

    // Chemin absolu vers le dossier où les photos de profil sont stockées
    $profils_upload_dir   = dirname(__DIR__, 2) . '/public/uploads/profils/'; // Remonte de 2 niveaux depuis app/handlers/ pour atteindre la racine du projet
    $uploaded_profil_photo = null;  // Sera rempli si un fichier image est soumis dans le formulaire

    // Tentative d'upload : retourne null si aucun fichier, lève RuntimeException si format/taille invalides
    try { $uploaded_profil_photo = upload_image('photo_profil', $profils_upload_dir); } // Tente de traiter le fichier uploadé (redimensionnement, validation du type MIME, etc.)
    catch (\RuntimeException $e) { $error = $e->getMessage(); }  // Capture l'erreur d'upload et la stocke pour l'afficher dans le formulaire

    if (empty($error)) { // Poursuit le traitement uniquement si l'upload n'a généré aucune erreur
        if (empty($new_pseudo)) { // Vérifie que le pseudo n'est pas vide (seul champ obligatoire du profil)
            // Le pseudo est le seul champ obligatoire ; les autres peuvent rester vides
            $error = "Le pseudo ne peut pas être vide."; // Message d'erreur si le pseudo est absent
        } else {
            $userModel    = new User($pdo);                                                              // Instancie le modèle User pour mettre à jour les données en base
            $profile_data = ['pseudo' => $new_pseudo, 'ville' => $new_ville, 'bio' => $new_bio]; // Rassemble les champs texte à mettre à jour dans un tableau associatif

            if ($uploaded_profil_photo !== null) { // Vérifie qu'une nouvelle photo de profil a bien été uploadée
                // Supprime l'ancienne photo du disque avant d'enregistrer la nouvelle
                // pour éviter d'accumuler des fichiers orphelins dans uploads/profils/
                $current_profile = $userModel->getById($_SESSION['user']['id']); // Récupère le profil actuel de l'utilisateur pour connaître le nom de son ancienne photo
                if (!empty($current_profile['photo_profil'])) { // Vérifie qu'une ancienne photo existe avant de tenter de la supprimer
                    @unlink($profils_upload_dir . $current_profile['photo_profil']);  // Supprime l'ancienne photo du disque (@ pour ignorer les warnings si le fichier est déjà absent)
                }
                // Ajoute la nouvelle photo au tableau de mise à jour
                $profile_data['photo_profil'] = $uploaded_profil_photo; // Ajoute le nom du nouveau fichier image au tableau de données à persister en base
            }
            // User::update ne touche à photo_profil que si la clé est présente dans $profile_data
            $userModel->update($_SESSION['user']['id'], $profile_data); // Met à jour les données du profil en base (seules les clés présentes dans $profile_data sont modifiées)

            // Synchronise immédiatement le pseudo dans la session pour que la navbar
            // affiche le nouveau pseudo sans attendre la prochaine reconnexion
            $_SESSION['user']['pseudo'] = $new_pseudo; // Rafraîchit le pseudo en session pour un affichage immédiat sans reconnexion
            $_SESSION['flash'] = "Profil mis à jour avec succès."; // Prépare un message de succès à afficher sur la page de profil
            header('Location: /sharetime/public/?page=profil'); // Redirige vers la page de profil après la mise à jour
            exit; // Stoppe l'exécution du script pour que la redirection soit effective
        }
    }
}

// ── CONTACT ────────────────────────────────────────────────────────────────────
if ($page === 'contact' && $_SERVER['REQUEST_METHOD'] === 'POST') { // Traite le formulaire de contact public uniquement lors d'une soumission POST
    csrf_check();  // Accessible sans être connecté, mais protégé contre le CSRF

    // Lecture et nettoyage des champs du formulaire de contact public
    $contact_sender_name    = trim($_POST['name']    ?? '');  // Récupère et nettoie le nom de la personne qui envoie le message
    $contact_sender_email   = trim($_POST['email']   ?? '');  // Récupère et nettoie l'email de l'expéditeur, pour pouvoir lui répondre
    $contact_subject        = trim($_POST['subject'] ?? '');  // Récupère et nettoie le sujet du message (optionnel)
    $contact_message_body   = trim($_POST['message'] ?? '');  // Récupère et nettoie le contenu textuel du message

    if (empty($contact_sender_name) || empty($contact_sender_email) || empty($contact_message_body)) { // Vérifie que les trois champs obligatoires sont remplis (le sujet est optionnel)
        // Nom, email et message sont obligatoires ; le sujet peut être vide
        $error = "Veuillez remplir tous les champs obligatoires."; // Message d'erreur générique pour les champs manquants

    } elseif (!filter_var($contact_sender_email, FILTER_VALIDATE_EMAIL)) { // Vérifie le format de l'email avant d'essayer d'envoyer quoi que ce soit
        $error = "Adresse e-mail invalide."; // Informe l'expéditeur que son email n'a pas le bon format

    } else {
        // Stocke le message en base pour qu'il apparaisse dans le panel admin/contact
        $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (:name, :email, :subject, :message)") // Prépare l'insertion du message de contact en base de données
            ->execute([
                'name'    => $contact_sender_name,   // Nom de l'expéditeur
                'email'   => $contact_sender_email,  // Email de l'expéditeur
                'subject' => $contact_subject,       // Sujet du message (peut être vide)
                'message' => $contact_message_body,  // Corps du message
            ]);

        // Envoie également un email de notification à l'adresse d'administration
        $admin_recipient_email = 'admin@sharetime.fr';                                                    // Adresse email de l'administrateur qui recevra les messages de contact
        $notification_subject  = '[ShareTime] ' . ($contact_subject ?: 'Nouveau message de contact');    // Construit l'objet de l'email (utilise le sujet soumis ou un libellé par défaut)
        $notification_body     = "Nom : {$contact_sender_name}\nEmail : {$contact_sender_email}\n\n{$contact_message_body}"; // Formate le corps de l'email de notification avec les informations de l'expéditeur

        // Reply-To permet à l'admin de répondre directement à l'expéditeur depuis son client mail
        $email_headers = "From: noreply@sharetime.fr\r\nReply-To: {$contact_sender_email}"; // En-têtes avec expéditeur technique et adresse de réponse pointant vers le visiteur
        @mail($admin_recipient_email, $notification_subject, $notification_body, $email_headers); // Envoie l'email de notification à l'admin (@ pour ignorer les erreurs en dev)

        // Message de succès affiché dans la vue après le traitement du formulaire
        $success = "Votre message a bien été reçu. Nous vous répondrons rapidement."; // Confirme à l'expéditeur que son message a été pris en compte
    }
}

// ── SUIVRE / NE PLUS SUIVRE ────────────────────────────────────────────────────
if ($page === 'suivre' && $_SERVER['REQUEST_METHOD'] === 'POST') { // Traite l'action de suivre ou ne plus suivre un utilisateur via un formulaire POST
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; } // Refuse l'accès si l'utilisateur n'est pas connecté
    csrf_check(); // Vérifie le token anti-CSRF avant de traiter l'action

    $target_user_id  = intval($_POST['user_id'] ?? 0);  // ID de l'utilisateur à suivre ou ne plus suivre
    $current_user_id = (int)$_SESSION['user']['id'];     // ID de l'utilisateur connecté (celui qui agit)

    // Interdit de se suivre soi-même (target !== current) et rejette les IDs invalides (> 0)
    if ($target_user_id > 0 && $target_user_id !== $current_user_id) { // Vérifie que la cible est valide et différente de l'utilisateur connecté
        $userModel = new User($pdo); // Instancie le modèle User pour accéder aux méthodes follow/unfollow/isFollowing

        // Toggle : si déjà abonné → désabonnement, sinon → abonnement + notification
        if ($userModel->isFollowing($current_user_id, $target_user_id)) { // Vérifie si l'utilisateur connecté suit déjà la cible
            // Désabonnement : supprime la ligne dans la table followers
            $userModel->unfollow($current_user_id, $target_user_id); // Supprime la relation d'abonnement entre les deux utilisateurs
        } else {
            // Abonnement : insère dans followers puis notifie l'utilisateur suivi
            $userModel->follow($current_user_id, $target_user_id); // Crée la relation d'abonnement en base de données
            // htmlspecialchars protège contre une injection XSS si le pseudo contient des caractères spéciaux
            $follower_display_name = htmlspecialchars($_SESSION['user']['pseudo'] ?? $_SESSION['user']['prenom']); // Récupère le pseudo affiché de l'abonné (fallback sur le prénom)
            notify($pdo, $target_user_id, 'nouveau_follower', 'Nouvel abonné', // Envoie une notification à l'utilisateur suivi pour l'informer du nouvel abonné
                $follower_display_name . ' a commencé à vous suivre.');
        }
    }

    // Redirige vers le profil de l'utilisateur ciblé (pas vers son propre profil)
    header('Location: /sharetime/public/?page=profil&id=' . $target_user_id); // Redirige vers le profil de la cible pour que l'utilisateur voie le changement
    exit; // Stoppe l'exécution du script après la redirection
}

// ── ENVOYER UN MESSAGE PRIVÉ ───────────────────────────────────────────────────
if ($page === 'envoyer_message' && $_SERVER['REQUEST_METHOD'] === 'POST') { // Traite l'envoi d'un message privé entre deux utilisateurs
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; } // Refuse l'accès si l'utilisateur n'est pas connecté
    csrf_check(); // Vérifie le token anti-CSRF avant de traiter l'envoi

    $receiver_user_id = intval($_POST['receiver_id'] ?? 0);  // ID du destinataire du message privé
    $message_content  = trim($_POST['content']       ?? ''); // texte du message à envoyer
    $current_user_id  = (int)$_SESSION['user']['id'];        // ID de l'expéditeur (utilisateur connecté)

    // Refuse les messages vers soi-même et les IDs de destinataire invalides (0 ou négatifs)
    if ($receiver_user_id <= 0 || $receiver_user_id === $current_user_id) { // Vérifie que le destinataire est valide et différent de l'expéditeur
        header('Location: /sharetime/public/?page=messages'); exit; // Redirige vers la liste des messages si la cible est invalide
    }

    if (empty($message_content)) { // Vérifie que le contenu du message n'est pas vide
        // Message vide : affiche une erreur et retourne à la conversation en cours
        $_SESSION['flash']      = "Le message ne peut pas être vide."; // Message d'erreur pour contenu absent
        $_SESSION['flash_type'] = 'error';                             // Indique que le flash est de type erreur (pour le style CSS)
        header('Location: /sharetime/public/?page=messages&with=' . $receiver_user_id); exit; // Redirige vers la conversation en cours
    }

    if (mb_strlen($message_content) > 1000) { // Vérifie que le message ne dépasse pas 1000 caractères
        // mb_strlen est nécessaire pour compter correctement les caractères multi-octets
        // (accents, emojis, caractères asiatiques…) — strlen() compterait les octets, pas les caractères
        $_SESSION['flash']      = "Message trop long (max 1000 caractères)."; // Message d'erreur pour contenu trop long
        $_SESSION['flash_type'] = 'error';                                     // Indique que le flash est de type erreur
        header('Location: /sharetime/public/?page=messages&with=' . $receiver_user_id); exit; // Redirige vers la conversation en cours
    }

    $userModel     = new User($pdo); // Instancie le modèle User pour vérifier l'existence et l'état du destinataire
    $receiver_user = $userModel->getById($receiver_user_id);  // données du destinataire (vérification existence + ban)

    // Refuse d'envoyer un message à un compte suspendu ou inexistant en base
    if (!$receiver_user || !empty($receiver_user['is_banned'])) { // Vérifie que le destinataire existe et n'est pas banni
        header('Location: /sharetime/public/?page=messages'); exit; // Redirige vers la liste si le destinataire est invalide ou banni
    }

    // Insère le message ; les paramètres positionnels (?) sont dans l'ordre : sender, receiver, content
    $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)") // Prépare l'insertion du message privé en base de données
        ->execute([$current_user_id, $receiver_user_id, $message_content]); // Insère l'expéditeur, le destinataire et le contenu du message

    // Redirige vers la conversation pour que l'utilisateur voie immédiatement son message envoyé
    header('Location: /sharetime/public/?page=messages&with=' . $receiver_user_id); // Redirige vers la conversation avec le destinataire
    exit; // Stoppe l'exécution du script après la redirection
}

// ── SIGNALEMENT D'UN UTILISATEUR ──────────────────────────────────────────────
if ($page === 'signaler' && $_SERVER['REQUEST_METHOD'] === 'POST') { // Traite le formulaire de signalement d'un utilisateur envers la modération
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; } // Refuse l'accès si l'utilisateur n'est pas connecté
    csrf_check(); // Vérifie le token anti-CSRF avant de traiter le signalement

    $current_user_id  = (int)$_SESSION['user']['id'];          // ID de l'utilisateur qui signale
    $reported_user_id = intval($_POST['signale_id'] ?? 0);     // ID de l'utilisateur signalé
    $report_reason    = trim($_POST['motif'] ?? '');            // motif du signalement (obligatoire)
    $redirect_after   = trim($_POST['redirect'] ?? '/sharetime/public/');  // URL de retour après le signalement

    // Interdit de se signaler soi-même et rejette les IDs invalides
    if ($reported_user_id <= 0 || $reported_user_id === $current_user_id) { // Vérifie que la cible est valide et différente de l'utilisateur connecté
        $_SESSION['flash']      = "Signalement invalide.";  // Message d'erreur pour signalement invalide
        $_SESSION['flash_type'] = 'error';                   // Indique que le flash est de type erreur
        header('Location: ' . $redirect_after); exit;        // Redirige vers la page d'origine
    }

    if (empty($report_reason)) { // Vérifie que le motif du signalement est renseigné
        // Le motif est obligatoire pour permettre à la modération de traiter le signalement efficacement
        $_SESSION['flash']      = "Veuillez indiquer un motif."; // Message d'erreur si le motif est absent
        $_SESSION['flash_type'] = 'error';                        // Indique que le flash est de type erreur
        header('Location: ' . $redirect_after); exit;             // Redirige vers la page d'origine
    }

    // Vérifie s'il existe déjà un signalement en attente de cet utilisateur contre la même cible
    // pour éviter les doublons et le spam de signalement
    $duplicate_report_check = $pdo->prepare( // Prépare la requête de vérification des doublons de signalement
        "SELECT COUNT(*) FROM reports WHERE signaleur_id = :s AND signale_id = :t AND status = 'en_attente'"
    );
    $duplicate_report_check->execute(['s' => $current_user_id, 't' => $reported_user_id]); // Exécute la vérification pour la paire expéditeur/cible
    if ($duplicate_report_check->fetchColumn()) { // Vérifie si un signalement en attente existe déjà pour cette paire
        $_SESSION['flash']      = "Vous avez déjà signalé cet utilisateur (signalement en attente)."; // Informe l'utilisateur qu'un signalement est déjà en cours de traitement
        $_SESSION['flash_type'] = 'error';                   // Indique que le flash est de type erreur
        header('Location: ' . $redirect_after); exit;        // Redirige vers la page d'origine
    }

    // Crée le nouveau signalement avec le statut 'en_attente' (valeur par défaut de la colonne en base)
    $pdo->prepare("INSERT INTO reports (signaleur_id, signale_id, motif) VALUES (:s, :t, :m)") // Prépare l'insertion du signalement en base de données
        ->execute(['s' => $current_user_id, 't' => $reported_user_id, 'm' => $report_reason]); // Insère le signaleur, la cible et le motif

    $_SESSION['flash'] = "Signalement envoyé. L'équipe le traitera dans les plus brefs délais."; // Prépare un message de confirmation pour l'utilisateur
    header('Location: ' . $redirect_after); // Redirige vers la page d'origine indiquée dans le formulaire
    exit; // Stoppe l'exécution du script après la redirection
}

// ── MARQUER TOUTES LES NOTIFICATIONS COMME LUES ───────────────────────────────
if ($page === 'notifs_lues' && $_SERVER['REQUEST_METHOD'] === 'POST') { // Traite la demande de marquage de toutes les notifications comme lues
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; } // Refuse l'accès si l'utilisateur n'est pas connecté
    csrf_check(); // Vérifie le token anti-CSRF avant d'effectuer la mise à jour en masse

    // Met is_read = 1 sur TOUTES les notifications de l'utilisateur en une seule requête
    // Le badge de notification dans la navbar affichera 0 dès la prochaine requête
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :u") // Prépare la mise à jour en masse de toutes les notifications de l'utilisateur
        ->execute(['u' => $_SESSION['user']['id']]); // Marque toutes les notifications de l'utilisateur connecté comme lues

    // Redirige vers la liste des notifications pour que l'utilisateur voie la mise à jour
    header('Location: /sharetime/public/?page=notifications'); // Redirige vers la page des notifications pour afficher l'état mis à jour
    exit; // Stoppe l'exécution du script après la redirection
}
