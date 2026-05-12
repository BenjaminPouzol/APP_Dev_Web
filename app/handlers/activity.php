<?php
/**
 * app/handlers/activity.php — Handlers des actions sur les activités
 *
 * Inclus inconditionnellement par public/index.php avant le routing.
 * Chaque bloc vérifie $page et REQUEST_METHOD avant d'agir.
 *
 * Gère : création, modification, annulation d'activité par l'organisateur,
 *        inscription/désinscription, commentaires, suppression de commentaire, notation.
 */

// ── CRÉATION D'ACTIVITÉ ────────────────────────────────────────────────────────
if ($page === 'creer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    // Lecture et nettoyage des champs du formulaire
    $title               = trim($_POST['title']            ?? '');
    $description         = trim($_POST['description']      ?? '');
    $location            = trim($_POST['location']         ?? '');
    $city                = trim($_POST['city']             ?? '');
    $start_time          = $_POST['start_time']            ?? '';
    $end_time            = $_POST['end_time']              ?? '';
    $max_participants    = intval($_POST['max_participants'] ?? 0);
    $latitude            = $_POST['latitude']              ?? '';
    $longitude           = $_POST['longitude']             ?? '';

    // Whitelist sur visibility : seules ces deux valeurs sont acceptées
    $visibility = in_array($_POST['visibility'] ?? '', ['publique', 'privee']) ? $_POST['visibility'] : 'publique';

    // Whitelist sur category : doit être une clé du $CATEGORY_MAP défini dans index.php
    $category = in_array($_POST['category'] ?? '', array_keys($CATEGORY_MAP)) ? $_POST['category'] : 'autre';

    // isset() retourne true si la checkbox est cochée, false sinon
    $liste_attente_active = isset($_POST['liste_attente_active']) ? 1 : 0;

    // Tentative d'upload photo (null si pas de fichier soumis, exception si format/taille invalides)
    $photo_creer = null;
    try { $photo_creer = upload_image('photo', dirname(__DIR__, 2) . '/public/uploads/activites/'); }
    catch (\RuntimeException $e) { $error = $e->getMessage(); }

    if (empty($error)) {
        if (empty($title) || empty($description) || empty($location) || empty($city) || empty($start_time) || empty($end_time)) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } elseif ($max_participants < 2) {
            // Une activité doit avoir au moins 2 participants (l'organisateur + 1 autre)
            $error = "Le nombre de participants doit être d'au moins 2.";
        } elseif (strtotime($end_time) <= strtotime($start_time)) {
            // strtotime() convertit les chaînes datetime en timestamps Unix pour la comparaison
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
                'latitude'            => $latitude,
                'longitude'           => $longitude,
            ]);
            $_SESSION['flash'] = "Activité créée avec succès !";
            header('Location: /sharetime/public/?page=activites');
            exit;
        }
    }
}

// ── MODIFIER ACTIVITÉ (ORGANISATEUR) ──────────────────────────────────────────
if ($page === 'modifier_activite' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    $activity_id          = intval($_POST['activity_id']     ?? 0);
    $title                = trim($_POST['title']             ?? '');
    $description          = trim($_POST['description']       ?? '');
    $location             = trim($_POST['location']          ?? '');
    $city                 = trim($_POST['city']              ?? '');
    $start_time           = $_POST['start_time']             ?? '';
    $end_time             = $_POST['end_time']               ?? '';
    $max_participants     = intval($_POST['max_participants'] ?? 0);
    $visibility           = in_array($_POST['visibility'] ?? '', ['publique', 'privee']) ? $_POST['visibility'] : 'publique';
    $category             = in_array($_POST['category']   ?? '', array_keys($CATEGORY_MAP)) ? $_POST['category'] : 'autre';
    $liste_attente_active = isset($_POST['liste_attente_active']) ? 1 : 0;
    $latitude_mod         = $_POST['latitude']               ?? '';
    $longitude_mod        = $_POST['longitude']              ?? '';

    if (empty($title) || empty($description) || empty($location) || empty($city) || empty($start_time) || empty($end_time)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif ($max_participants < 2) {
        $error = "Le nombre de participants doit être d'au moins 2.";
    } elseif (strtotime($end_time) <= strtotime($start_time)) {
        $error = "La date de fin doit être postérieure à la date de début.";
    } else {
        $activityModel = new Activity($pdo);
        $existing = $activityModel->getById($activity_id);

        // Triple vérification de sécurité : l'activité existe, elle appartient à l'utilisateur connecté,
        // et elle est encore active (impossible de modifier une activité annulée ou terminée)
        if (!$existing || (int)$existing['creator_id'] !== (int)$_SESSION['user']['id'] || $existing['status'] !== 'active') {
            header('Location: /sharetime/public/?page=activites'); exit;
        }

        // On ne peut pas réduire max_participants en dessous du nombre de personnes déjà inscrites
        if ($max_participants < (int)$existing['nb_inscrits']) {
            $error = "Le nombre de participants ne peut pas être inférieur au nombre d'inscrits ({$existing['nb_inscrits']}).";
        } else {
            $upload_dir_act = dirname(__DIR__, 2) . '/public/uploads/activites/';
            $photo_act = null;
            try { $photo_act = upload_image('photo', $upload_dir_act); }
            catch (\RuntimeException $e) { $error = $e->getMessage(); }

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
                    'latitude'            => $latitude_mod,
                    'longitude'           => $longitude_mod,
                ];

                if ($photo_act !== null) {
                    // Supprime l'ancienne photo du disque avant d'enregistrer la nouvelle
                    if (!empty($existing['photo'])) @unlink($upload_dir_act . $existing['photo']);
                    $update_data['photo'] = $photo_act;
                }

                $activityModel->update($activity_id, $update_data);

                // Notifie tous les inscrits que l'activité a été modifiée
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

// ── ANNULER ACTIVITÉ (ORGANISATEUR) ───────────────────────────────────────────
if ($page === 'annuler_activite' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    $activity_id = intval($_POST['activity_id'] ?? 0);
    if ($activity_id > 0) {
        $activityModel = new Activity($pdo);
        $act_to_cancel = $activityModel->getById($activity_id);

        // cancelByOrganizer vérifie en base que l'utilisateur est bien le créateur
        if ($activityModel->cancelByOrganizer($activity_id, $_SESSION['user']['id'])) {
            if ($act_to_cancel) {
                // Notifie tous les inscrits de l'annulation
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

// ── S'INSCRIRE ─────────────────────────────────────────────────────────────────
if ($page === 's_inscrire' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    $activity_id = intval($_POST['activity_id'] ?? 0);
    if ($activity_id > 0) {
        $activityModel = new Activity($pdo);
        $activity      = $activityModel->getById($activity_id);
        $reg_status    = $activityModel->getRegistrationStatus($activity_id, $_SESSION['user']['id']);

        // Conditions pour pouvoir s'inscrire :
        // - l'activité existe et est active (pas annulée ni terminée)
        // - l'utilisateur n'est pas déjà inscrit (null = jamais inscrit, 'annule' = s'était désinscrit)
        if ($activity && $activity['status'] === 'active' && (!$reg_status || $reg_status === 'annule')) {
            $pseudo = htmlspecialchars($_SESSION['user']['pseudo'] ?? $_SESSION['user']['prenom']);

            if ($activity['nb_inscrits'] < $activity['max_participants']) {
                // Il reste de la place : inscription directe
                $activityModel->register($activity_id, $_SESSION['user']['id']);
                // Notifie l'organisateur qu'il a une nouvelle inscription
                notify($pdo, (int)$activity['creator_id'], 'nouvelle_inscription', 'Nouvelle inscription',
                    "{$pseudo} s'est inscrit(e) à votre activité \"{$activity['title']}\".", $activity_id);
                $_SESSION['flash'] = "Inscription confirmée ! À bientôt.";
            } elseif (!empty($activity['liste_attente_active'])) {
                // Activité complète mais liste d'attente activée : mise en attente
                $activityModel->registerWaitlist($activity_id, $_SESSION['user']['id']);
                $_SESSION['flash'] = "Activité complète. Vous avez été ajouté(e) à la liste d'attente.";
            }
            // Si ni place disponible ni liste d'attente : on ne fait rien (cas géré par l'UI)
        }
    }
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id);
    exit;
}

// ── SE DÉSINSCRIRE ─────────────────────────────────────────────────────────────
if ($page === 'se_desinscrire' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    $activity_id = intval($_POST['activity_id'] ?? 0);
    if ($activity_id > 0) {
        $activityModel = new Activity($pdo);
        $activity      = $activityModel->getById($activity_id);

        // On mémorise le statut AVANT la désinscription pour savoir si une place se libère
        $was_inscrit = $activityModel->getRegistrationStatus($activity_id, $_SESSION['user']['id']) === 'inscrit';
        $activityModel->unregister($activity_id, $_SESSION['user']['id']);
        $_SESSION['flash'] = "Vous vous êtes désinscrit(e) de cette activité.";

        // Si l'utilisateur était vraiment inscrit (pas juste en attente) et que la liste d'attente
        // est activée, on promeut automatiquement la première personne en attente
        if ($was_inscrit && $activity && !empty($activity['liste_attente_active'])) {
            $promoted = $activityModel->promoteFromWaitlist($activity_id);
            if ($promoted) {
                // Notifie la personne promue qu'une place vient de se libérer pour elle
                notify($pdo, (int)$promoted, 'promotion_attente', 'Place libérée !',
                    "Vous avez été promu(e) de la liste d'attente pour \"{$activity['title']}\".", $activity_id);
            }
        }
    }
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id);
    exit;
}

// ── COMMENTER ──────────────────────────────────────────────────────────────────
if ($page === 'commenter' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    $activity_id = intval($_POST['activity_id'] ?? 0);
    $content     = trim($_POST['content'] ?? '');

    // Validation minimale : activité valide et commentaire non vide
    if ($activity_id > 0 && $content !== '') {
        $activityModel = new Activity($pdo);
        $activityModel->addComment($activity_id, $_SESSION['user']['id'], $content);
    }

    // Redirection avec ancre #comments pour revenir directement à la section commentaires
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id . '#comments');
    exit;
}

// ── SUPPRIMER COMMENTAIRE ──────────────────────────────────────────────────────
if ($page === 'supprimer_commentaire' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    $comment_id  = intval($_POST['comment_id']  ?? 0);
    $activity_id = intval($_POST['activity_id'] ?? 0);

    if ($comment_id > 0) {
        $activityModel = new Activity($pdo);
        if (is_admin()) {
            // Un admin peut supprimer n'importe quel commentaire (modération)
            $activityModel->deleteCommentAsAdmin($comment_id);
        } else {
            // Un utilisateur normal ne peut supprimer que ses propres commentaires
            // (la vérification user_id est faite dans deleteComment)
            $activityModel->deleteComment($comment_id, $_SESSION['user']['id']);
        }
    }
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id . '#comments');
    exit;
}

// ── NOTER UN ORGANISATEUR ──────────────────────────────────────────────────────
if ($page === 'noter' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    $activity_id = intval($_POST['activity_id'] ?? 0);
    $note        = intval($_POST['note']        ?? 0);  // valeur 1-5

    if ($activity_id > 0 && $note >= 1 && $note <= 5) {
        $activityModel = new Activity($pdo);
        $activity      = $activityModel->getById($activity_id);

        // Conditions pour noter :
        // 1. L'activité est terminée (on ne note pas avant la fin)
        // 2. L'utilisateur est inscrit à cette activité (pas juste en attente ou désinscrit)
        // hasRated() est vérifié dans rate() pour éviter un double vote
        if ($activity && $activity['status'] === 'terminee') {
            $reg_status = $activityModel->getRegistrationStatus($activity_id, $_SESSION['user']['id']);
            if ($reg_status === 'inscrit') {
                $activityModel->rate($_SESSION['user']['id'], $activity['creator_id'], $activity_id, $note);
                $_SESSION['flash'] = "Votre note a bien été enregistrée. Merci !";
            }
        }
    }
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id . '#rating');
    exit;
}
