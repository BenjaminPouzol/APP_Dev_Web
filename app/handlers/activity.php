<?php
/**
 * app/handlers/activity.php — Handlers des actions sur les activités
 *
 * Inclus inconditionnellement par public/index.php avant le routing.
 * Chaque bloc vérifie $page et REQUEST_METHOD avant d'agir.
 *
 * Gère : création, modification, annulation d'activité par l'organisateur,
 *        inscription/désinscription, gestion de la liste d'attente,
 *        commentaires, suppression de commentaire, et notation de l'organisateur.
 */

// ── CRÉATION D'ACTIVITÉ ────────────────────────────────────────────────────────
if ($page === 'creer' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // Seuls les utilisateurs connectés peuvent créer une activité
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    // Lecture et nettoyage de tous les champs du formulaire de création
    $activity_title        = trim($_POST['title']             ?? '');  // titre de l'activité (obligatoire)
    $activity_description  = trim($_POST['description']       ?? '');  // description détaillée (obligatoire)
    $activity_location     = trim($_POST['location']          ?? '');  // adresse précise du lieu (obligatoire)
    $activity_city         = trim($_POST['city']              ?? '');  // ville de l'activité pour les filtres (obligatoire)
    $activity_start_time   = $_POST['start_time']             ?? '';   // date + heure de début, format 'Y-m-d H:i' (obligatoire)
    $activity_end_time     = $_POST['end_time']               ?? '';   // date + heure de fin, format 'Y-m-d H:i' (obligatoire)
    $activity_max_places   = intval($_POST['max_participants'] ?? 0);  // nombre maximum de participants acceptés
    $activity_latitude     = $_POST['latitude']               ?? '';   // coordonnée GPS latitude (optionnel, carte)
    $activity_longitude    = $_POST['longitude']              ?? '';   // coordonnée GPS longitude (optionnel, carte)

    // Whitelist sur visibility : seules ces deux valeurs sont acceptées, sinon on force 'publique'
    $activity_visibility = in_array($_POST['visibility'] ?? '', ['publique', 'privee'])
                           ? $_POST['visibility']
                           : 'publique';

    // Whitelist sur category : doit être une clé connue du $CATEGORY_MAP défini dans index.php
    $activity_category = in_array($_POST['category'] ?? '', array_keys($CATEGORY_MAP))
                         ? $_POST['category']
                         : 'autre';  // catégorie par défaut si valeur inconnue reçue

    // isset() retourne true si la checkbox est cochée, false si elle est absente du POST
    $waitlist_is_active = isset($_POST['liste_attente_active']) ? 1 : 0;

    // Tentative d'upload de la photo de couverture de l'activité
    // null si aucun fichier soumis (autorisé), RuntimeException si format ou taille invalides
    $uploaded_activity_photo = null;
    try { $uploaded_activity_photo = upload_image('photo', dirname(__DIR__, 2) . '/public/uploads/activites/'); }
    catch (\RuntimeException $e) { $error = $e->getMessage(); }

    if (empty($error)) {
        // Validation des champs obligatoires avant toute écriture en base
        if (empty($activity_title) || empty($activity_description) || empty($activity_location)
            || empty($activity_city) || empty($activity_start_time) || empty($activity_end_time)) {
            $error = "Veuillez remplir tous les champs obligatoires.";

        } elseif ($activity_max_places < 2) {
            // Une activité doit avoir au moins 2 places : l'organisateur + au moins 1 participant
            $error = "Le nombre de participants doit être d'au moins 2.";

        } elseif (strtotime($activity_end_time) <= strtotime($activity_start_time)) {
            // strtotime() convertit les chaînes 'Y-m-d H:i' en timestamps Unix pour la comparaison
            $error = "La date de fin doit être postérieure à la date de début.";

        } else {
            // Toutes les validations sont passées : on crée l'activité en base
            $activityModel = new Activity($pdo);
            $activityModel->create([
                'title'                => $activity_title,
                'description'          => $activity_description,
                'photo'                => $uploaded_activity_photo,  // null si pas de photo soumise
                'location'             => $activity_location,
                'city'                 => $activity_city,
                'start_time'           => $activity_start_time,
                'end_time'             => $activity_end_time,
                'max_participants'     => $activity_max_places,
                'visibility'           => $activity_visibility,
                'category'             => $activity_category,
                'liste_attente_active' => $waitlist_is_active,
                'creator_id'           => $_SESSION['user']['id'],  // l'utilisateur connecté est l'organisateur
                'latitude'             => $activity_latitude,
                'longitude'            => $activity_longitude,
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

    // Lecture des champs du formulaire de modification
    $activity_id          = intval($_POST['activity_id']     ?? 0);   // ID de l'activité à modifier
    $activity_title       = trim($_POST['title']             ?? '');   // nouveau titre
    $activity_description = trim($_POST['description']       ?? '');   // nouvelle description
    $activity_location    = trim($_POST['location']          ?? '');   // nouveau lieu
    $activity_city        = trim($_POST['city']              ?? '');   // nouvelle ville
    $activity_start_time  = $_POST['start_time']             ?? '';    // nouvelle date de début
    $activity_end_time    = $_POST['end_time']               ?? '';    // nouvelle date de fin
    $activity_max_places  = intval($_POST['max_participants'] ?? 0);   // nouveau nombre max de participants
    $activity_visibility  = in_array($_POST['visibility'] ?? '', ['publique', 'privee']) ? $_POST['visibility'] : 'publique';
    $activity_category    = in_array($_POST['category']   ?? '', array_keys($CATEGORY_MAP)) ? $_POST['category'] : 'autre';
    $waitlist_is_active   = isset($_POST['liste_attente_active']) ? 1 : 0;
    $activity_latitude    = $_POST['latitude']               ?? '';    // nouvelle latitude (optionnel)
    $activity_longitude   = $_POST['longitude']              ?? '';    // nouvelle longitude (optionnel)

    // Validation des champs obligatoires
    if (empty($activity_title) || empty($activity_description) || empty($activity_location)
        || empty($activity_city) || empty($activity_start_time) || empty($activity_end_time)) {
        $error = "Veuillez remplir tous les champs obligatoires.";

    } elseif ($activity_max_places < 2) {
        $error = "Le nombre de participants doit être d'au moins 2.";

    } elseif (strtotime($activity_end_time) <= strtotime($activity_start_time)) {
        $error = "La date de fin doit être postérieure à la date de début.";

    } else {
        $activityModel   = new Activity($pdo);
        $existing_activity = $activityModel->getById($activity_id);  // données actuelles de l'activité en base

        // Triple vérification de sécurité :
        // 1. L'activité existe bien en base
        // 2. Elle appartient à l'utilisateur connecté (pas à quelqu'un d'autre)
        // 3. Elle est encore au statut 'active' (on ne peut pas modifier une activité annulée ou terminée)
        if (!$existing_activity
            || (int)$existing_activity['creator_id'] !== (int)$_SESSION['user']['id']
            || $existing_activity['status'] !== 'active') {
            header('Location: /sharetime/public/?page=activites'); exit;
        }

        // Refuse de réduire le nombre max de participants en dessous du nombre d'inscrits actuels
        // pour éviter que des personnes déjà inscrites se retrouvent "hors quota"
        if ($activity_max_places < (int)$existing_activity['nb_inscrits']) {
            $error = "Le nombre de participants ne peut pas être inférieur au nombre d'inscrits ({$existing_activity['nb_inscrits']}).";
        } else {
            // Chemin vers le dossier de stockage des photos d'activités
            $activites_upload_dir  = dirname(__DIR__, 2) . '/public/uploads/activites/';
            $new_activity_photo    = null;  // sera rempli si une nouvelle photo est uploadée

            // Tentative d'upload de la nouvelle photo (null si pas de fichier soumis)
            try { $new_activity_photo = upload_image('photo', $activites_upload_dir); }
            catch (\RuntimeException $e) { $error = $e->getMessage(); }

            if (empty($error)) {
                // Tableau des données à mettre à jour
                $update_data = [
                    'title'                => $activity_title,
                    'description'          => $activity_description,
                    'location'             => $activity_location,
                    'city'                 => $activity_city,
                    'start_time'           => $activity_start_time,
                    'end_time'             => $activity_end_time,
                    'max_participants'     => $activity_max_places,
                    'visibility'           => $activity_visibility,
                    'category'             => $activity_category,
                    'liste_attente_active' => $waitlist_is_active,
                    'creator_id'           => $_SESSION['user']['id'],  // nécessaire pour la clause WHERE dans Activity::update
                    'latitude'             => $activity_latitude,
                    'longitude'            => $activity_longitude,
                ];

                if ($new_activity_photo !== null) {
                    // Supprime l'ancienne photo du disque pour éviter les fichiers orphelins
                    if (!empty($existing_activity['photo'])) {
                        @unlink($activites_upload_dir . $existing_activity['photo']);
                    }
                    // Ajoute la nouvelle photo au tableau de mise à jour
                    $update_data['photo'] = $new_activity_photo;
                }
                // La clé 'photo' n'est présente que si une nouvelle image est uploadée,
                // donc Activity::update ne modifiera la photo que dans ce cas

                $activityModel->update($activity_id, $update_data);

                // Notifie tous les inscrits que l'activité a été modifiée
                // (pour qu'ils vérifient les nouvelles dates, lieu, etc.)
                foreach ($activityModel->getRegisteredUserIds($activity_id) as $registered_user_id) {
                    notify($pdo, (int)$registered_user_id, 'activite_modifiee', 'Activité modifiée',
                        "L'activité \"{$activity_title}\" à laquelle vous êtes inscrit(e) a été modifiée.",
                        $activity_id);
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

    $activity_id = intval($_POST['activity_id'] ?? 0);  // ID de l'activité à annuler

    if ($activity_id > 0) {
        $activityModel      = new Activity($pdo);
        $activity_to_cancel = $activityModel->getById($activity_id);  // données de l'activité (pour les notifications)

        // cancelByOrganizer vérifie en base que l'utilisateur est bien le créateur de cette activité
        // et que son statut est 'active' (impossible d'annuler une activité déjà annulée ou terminée)
        if ($activityModel->cancelByOrganizer($activity_id, $_SESSION['user']['id'])) {
            if ($activity_to_cancel) {
                // Notifie chaque inscrit de l'annulation pour qu'il soit informé rapidement
                foreach ($activityModel->getRegisteredUserIds($activity_id) as $registered_user_id) {
                    notify($pdo, (int)$registered_user_id, 'activite_annulee', 'Activité annulée',
                        "L'activité \"{$activity_to_cancel['title']}\" à laquelle vous étiez inscrit(e) a été annulée.",
                        $activity_id);
                }
            }
            $_SESSION['flash'] = "Votre activité a été annulée.";
        } else {
            // Annulation impossible : l'activité n'existe pas, n'appartient pas à cet utilisateur,
            // ou n'est plus au statut 'active'
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

    $activity_id              = intval($_POST['activity_id'] ?? 0);
    if ($activity_id > 0) {
        $activityModel        = new Activity($pdo);
        $activity             = $activityModel->getById($activity_id);
        $current_reg_status   = $activityModel->getRegistrationStatus($activity_id, $_SESSION['user']['id']);
        // Valeurs possibles de $current_reg_status : null (jamais inscrit), 'inscrit', 'en_attente', 'annule'

        // Conditions pour pouvoir s'inscrire :
        // - L'activité existe et est au statut 'active' (pas annulée ni terminée)
        // - L'utilisateur n'est pas déjà inscrit ni en attente
        //   (null = jamais eu de ligne, 'annule' = s'était désinscrit → peut se réinscrire)
        if ($activity && $activity['status'] === 'active'
            && (!$current_reg_status || $current_reg_status === 'annule')) {

            // Nom affiché dans la notification envoyée à l'organisateur
            $participant_display_name = htmlspecialchars($_SESSION['user']['pseudo'] ?? $_SESSION['user']['prenom']);

            if ($activity['nb_inscrits'] < $activity['max_participants']) {
                // Il reste au moins une place disponible : inscription directe
                $activityModel->register($activity_id, $_SESSION['user']['id']);
                // Notifie l'organisateur qu'il a une nouvelle inscription
                notify($pdo, (int)$activity['creator_id'], 'nouvelle_inscription', 'Nouvelle inscription',
                    "{$participant_display_name} s'est inscrit(e) à votre activité \"{$activity['title']}\".",
                    $activity_id);
                $_SESSION['flash'] = "Inscription confirmée ! À bientôt.";

            } elseif (!empty($activity['liste_attente_active'])) {
                // L'activité est complète mais la liste d'attente est activée par l'organisateur
                $activityModel->registerWaitlist($activity_id, $_SESSION['user']['id']);
                $_SESSION['flash'] = "Activité complète. Vous avez été ajouté(e) à la liste d'attente.";
            }
            // Si l'activité est complète ET sans liste d'attente, on ne fait rien (cas géré par l'UI)
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

        // On mémorise le statut AVANT la désinscription pour distinguer un inscrit confirmé
        // d'une personne seulement en attente (seule la libération d'un inscrit confirmé déclenche une promotion)
        $was_confirmed_inscrit = $activityModel->getRegistrationStatus($activity_id, $_SESSION['user']['id']) === 'inscrit';

        // Passe le statut de la ligne de registration à 'annule' (conservée pour l'historique)
        $activityModel->unregister($activity_id, $_SESSION['user']['id']);
        $_SESSION['flash'] = "Vous vous êtes désinscrit(e) de cette activité.";

        // Promeut automatiquement la première personne en liste d'attente si :
        // - L'utilisateur était bien inscrit (pas juste en attente)
        // - La liste d'attente est activée sur cette activité
        if ($was_confirmed_inscrit && $activity && !empty($activity['liste_attente_active'])) {
            $promoted_user_id = $activityModel->promoteFromWaitlist($activity_id);
            if ($promoted_user_id) {
                // Notifie la personne promue qu'une place vient de se libérer pour elle
                notify($pdo, (int)$promoted_user_id, 'promotion_attente', 'Place libérée !',
                    "Vous avez été promu(e) de la liste d'attente pour \"{$activity['title']}\".",
                    $activity_id);
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

    $activity_id     = intval($_POST['activity_id'] ?? 0);  // activité sur laquelle poster le commentaire
    $comment_content = trim($_POST['content'] ?? '');        // texte du commentaire

    // Validation minimale : l'ID d'activité doit être valide et le commentaire non vide
    if ($activity_id > 0 && $comment_content !== '') {
        $activityModel = new Activity($pdo);
        $activityModel->addComment($activity_id, $_SESSION['user']['id'], $comment_content);
    }

    // L'ancre #comments amène directement l'utilisateur à la section commentaires après la soumission
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id . '#comments');
    exit;
}

// ── SUPPRIMER COMMENTAIRE ──────────────────────────────────────────────────────
if ($page === 'supprimer_commentaire' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    $comment_id_to_delete = intval($_POST['comment_id']  ?? 0);  // ID du commentaire à supprimer
    $activity_id          = intval($_POST['activity_id'] ?? 0);  // ID de l'activité (pour la redirection)

    if ($comment_id_to_delete > 0) {
        $activityModel = new Activity($pdo);
        if (is_admin()) {
            // Un admin peut supprimer n'importe quel commentaire à des fins de modération
            $activityModel->deleteCommentAsAdmin($comment_id_to_delete);
        } else {
            // Un utilisateur normal ne peut supprimer que ses propres commentaires
            // La vérification AND user_id = :u est faite dans Activity::deleteComment
            $activityModel->deleteComment($comment_id_to_delete, $_SESSION['user']['id']);
        }
    }
    // L'ancre #comments replace l'utilisateur dans la section commentaires
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id . '#comments');
    exit;
}

// ── NOTER UN ORGANISATEUR ──────────────────────────────────────────────────────
if ($page === 'noter' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; }
    csrf_check();

    $activity_id  = intval($_POST['activity_id'] ?? 0);  // activité pour laquelle on note l'organisateur
    $rating_value = intval($_POST['note']        ?? 0);  // note donnée, comprise entre 1 et 5

    // Validation de plage côté handler (la même vérification existe aussi dans Activity::rate)
    if ($activity_id > 0 && $rating_value >= 1 && $rating_value <= 5) {
        $activityModel = new Activity($pdo);
        $activity      = $activityModel->getById($activity_id);

        // Deux conditions pour pouvoir noter :
        // 1. L'activité doit être au statut 'terminee' (pas de notation pendant l'activité)
        // 2. L'utilisateur doit avoir le statut 'inscrit' (pas 'annule', pas 'en_attente')
        //    pour s'assurer qu'il a bien participé à l'activité
        if ($activity && $activity['status'] === 'terminee') {
            $voter_reg_status = $activityModel->getRegistrationStatus($activity_id, $_SESSION['user']['id']);
            if ($voter_reg_status === 'inscrit') {
                // Activity::rate vérifie hasRated() en interne pour éviter les votes doubles
                $activityModel->rate($_SESSION['user']['id'], $activity['creator_id'], $activity_id, $rating_value);
                $_SESSION['flash'] = "Votre note a bien été enregistrée. Merci !";
            }
        }
    }
    // L'ancre #rating ramène l'utilisateur directement à la section de notation
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id . '#rating');
    exit;
}
