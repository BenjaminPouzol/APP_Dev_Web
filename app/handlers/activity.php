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
// Déclenche ce bloc uniquement si la page est 'creer' ET que la requête est un envoi de formulaire
if ($page === 'creer' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // Seuls les utilisateurs connectés peuvent créer une activité
    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; } // redirige si non connecté
    csrf_check(); // vérifie le token CSRF pour rejeter les soumissions depuis un autre site

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
                           : 'publique'; // valeur par défaut si la valeur reçue n'est pas autorisée

    // Whitelist sur category : doit être une clé connue du $CATEGORY_MAP défini dans index.php
    $activity_category = in_array($_POST['category'] ?? '', array_keys($CATEGORY_MAP))
                         ? $_POST['category']
                         : 'autre';  // catégorie par défaut si valeur inconnue reçue

    // isset() retourne true si la checkbox est cochée, false si elle est absente du POST
    $waitlist_is_active = isset($_POST['liste_attente_active']) ? 1 : 0; // 1 si la liste d'attente est activée, 0 sinon

    // Tentative d'upload de la photo de couverture de l'activité
    // null si aucun fichier soumis (autorisé), RuntimeException si format ou taille invalides
    $uploaded_activity_photo = null; // valeur par défaut : pas de photo
    try { $uploaded_activity_photo = upload_image('photo', dirname(__DIR__, 2) . '/public/uploads/activites/'); } // tente d'uploader la photo dans le dossier dédié
    catch (\RuntimeException $e) { $error = $e->getMessage(); } // capture l'erreur si le fichier est invalide

    if (empty($error)) { // continue uniquement si l'upload n'a pas échoué
        // Validation des champs obligatoires avant toute écriture en base
        if (empty($activity_title) || empty($activity_description) || empty($activity_location)
            || empty($activity_city) || empty($activity_start_time) || empty($activity_end_time)) {
            $error = "Veuillez remplir tous les champs obligatoires."; // message d'erreur si un champ manque

        } elseif ($activity_max_places < 2) {
            // Une activité doit avoir au moins 2 places : l'organisateur + au moins 1 participant
            $error = "Le nombre de participants doit être d'au moins 2.";

        } elseif (strtotime($activity_end_time) <= strtotime($activity_start_time)) {
            // strtotime() convertit les chaînes 'Y-m-d H:i' en timestamps Unix pour la comparaison
            $error = "La date de fin doit être postérieure à la date de début.";

        } else {
            // Toutes les validations sont passées : on crée l'activité en base
            $activityModel = new Activity($pdo); // instancie le modèle Activity avec la connexion PDO
            $activityModel->create([ // appelle la méthode de création avec toutes les données du formulaire
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
            $_SESSION['flash'] = "Activité créée avec succès !"; // message de confirmation affiché après la redirection
            header('Location: /sharetime/public/?page=activites'); // redirige vers la liste des activités
            exit; // stoppe l'exécution du script après la redirection
        }
    }
}

// ── MODIFIER ACTIVITÉ (ORGANISATEUR) ──────────────────────────────────────────
// Déclenche ce bloc uniquement si la page est 'modifier_activite' ET que la requête est un POST
if ($page === 'modifier_activite' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; } // redirige si non connecté
    csrf_check(); // vérifie le token CSRF

    // Lecture des champs du formulaire de modification
    $activity_id          = intval($_POST['activity_id']     ?? 0);   // ID de l'activité à modifier
    $activity_title       = trim($_POST['title']             ?? '');   // nouveau titre
    $activity_description = trim($_POST['description']       ?? '');   // nouvelle description
    $activity_location    = trim($_POST['location']          ?? '');   // nouveau lieu
    $activity_city        = trim($_POST['city']              ?? '');   // nouvelle ville
    $activity_start_time  = $_POST['start_time']             ?? '';    // nouvelle date de début
    $activity_end_time    = $_POST['end_time']               ?? '';    // nouvelle date de fin
    $activity_max_places  = intval($_POST['max_participants'] ?? 0);   // nouveau nombre max de participants
    $activity_visibility  = in_array($_POST['visibility'] ?? '', ['publique', 'privee']) ? $_POST['visibility'] : 'publique'; // filtre la visibilité parmi les valeurs autorisées
    $activity_category    = in_array($_POST['category']   ?? '', array_keys($CATEGORY_MAP)) ? $_POST['category'] : 'autre'; // filtre la catégorie parmi les valeurs connues
    $waitlist_is_active   = isset($_POST['liste_attente_active']) ? 1 : 0; // 1 si la checkbox liste d'attente est cochée
    $activity_latitude    = $_POST['latitude']               ?? '';    // nouvelle latitude (optionnel)
    $activity_longitude   = $_POST['longitude']              ?? '';    // nouvelle longitude (optionnel)

    // Validation des champs obligatoires
    if (empty($activity_title) || empty($activity_description) || empty($activity_location)
        || empty($activity_city) || empty($activity_start_time) || empty($activity_end_time)) {
        $error = "Veuillez remplir tous les champs obligatoires."; // bloque la mise à jour si un champ obligatoire est vide

    } elseif ($activity_max_places < 2) {
        $error = "Le nombre de participants doit être d'au moins 2."; // interdit les activités solo

    } elseif (strtotime($activity_end_time) <= strtotime($activity_start_time)) {
        $error = "La date de fin doit être postérieure à la date de début."; // vérifie la cohérence des dates

    } else {
        $activityModel   = new Activity($pdo); // instancie le modèle Activity
        $existing_activity = $activityModel->getById($activity_id);  // données actuelles de l'activité en base

        // Triple vérification de sécurité :
        // 1. L'activité existe bien en base
        // 2. Elle appartient à l'utilisateur connecté (pas à quelqu'un d'autre)
        // 3. Elle est encore au statut 'active' (on ne peut pas modifier une activité annulée ou terminée)
        if (!$existing_activity
            || (int)$existing_activity['creator_id'] !== (int)$_SESSION['user']['id']
            || $existing_activity['status'] !== 'active') {
            header('Location: /sharetime/public/?page=activites'); exit; // accès non autorisé : redirige sans modifier
        }

        // Refuse de réduire le nombre max de participants en dessous du nombre d'inscrits actuels
        // pour éviter que des personnes déjà inscrites se retrouvent "hors quota"
        if ($activity_max_places < (int)$existing_activity['nb_inscrits']) {
            $error = "Le nombre de participants ne peut pas être inférieur au nombre d'inscrits ({$existing_activity['nb_inscrits']}).";
        } else {
            // Chemin vers le dossier de stockage des photos d'activités
            $activites_upload_dir  = dirname(__DIR__, 2) . '/public/uploads/activites/'; // remonte de 2 niveaux depuis /app/handlers/
            $new_activity_photo    = null;  // sera rempli si une nouvelle photo est uploadée

            // Tentative d'upload de la nouvelle photo (null si pas de fichier soumis)
            try { $new_activity_photo = upload_image('photo', $activites_upload_dir); } // null si aucun fichier envoyé
            catch (\RuntimeException $e) { $error = $e->getMessage(); } // capture l'erreur de format ou de taille

            if (empty($error)) { // continue uniquement si l'upload n'a pas échoué
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

                if ($new_activity_photo !== null) { // une nouvelle photo a été envoyée par l'utilisateur
                    // Supprime l'ancienne photo du disque pour éviter les fichiers orphelins
                    if (!empty($existing_activity['photo'])) {
                        @unlink($activites_upload_dir . $existing_activity['photo']); // @ supprime l'avertissement si le fichier est déjà absent
                    }
                    // Ajoute la nouvelle photo au tableau de mise à jour
                    $update_data['photo'] = $new_activity_photo; // inclut le nom du fichier uploadé dans les données
                }
                // La clé 'photo' n'est présente que si une nouvelle image est uploadée,
                // donc Activity::update ne modifiera la photo que dans ce cas

                $activityModel->update($activity_id, $update_data); // exécute la mise à jour en base

                // Notifie tous les inscrits que l'activité a été modifiée
                // (pour qu'ils vérifient les nouvelles dates, lieu, etc.)
                foreach ($activityModel->getRegisteredUserIds($activity_id) as $registered_user_id) { // boucle sur chaque ID d'inscrit
                    notify($pdo, (int)$registered_user_id, 'activite_modifiee', 'Activité modifiée',
                        "L'activité \"{$activity_title}\" à laquelle vous êtes inscrit(e) a été modifiée.",
                        $activity_id); // envoie une notification individuelle à chaque participant
                }
                $_SESSION['flash'] = "Activité modifiée avec succès."; // message de succès pour l'organisateur
                header('Location: /sharetime/public/?page=detail&id=' . $activity_id); // redirige vers la page de détail
                exit; // stoppe l'exécution après la redirection
            }
        }
    }
}

// ── ANNULER ACTIVITÉ (ORGANISATEUR) ───────────────────────────────────────────
// Déclenche ce bloc uniquement si la page est 'annuler_activite' ET que la requête est un POST
if ($page === 'annuler_activite' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; } // redirige si non connecté
    csrf_check(); // vérifie le token CSRF

    $activity_id = intval($_POST['activity_id'] ?? 0);  // ID de l'activité à annuler

    if ($activity_id > 0) { // vérifie que l'ID reçu est valide (positif)
        $activityModel      = new Activity($pdo); // instancie le modèle Activity
        $activity_to_cancel = $activityModel->getById($activity_id);  // données de l'activité (pour les notifications)

        // cancelByOrganizer vérifie en base que l'utilisateur est bien le créateur de cette activité
        // et que son statut est 'active' (impossible d'annuler une activité déjà annulée ou terminée)
        if ($activityModel->cancelByOrganizer($activity_id, $_SESSION['user']['id'])) { // retourne true si l'annulation a réussi
            if ($activity_to_cancel) { // vérifie que l'activité existait bien en base
                // Notifie chaque inscrit de l'annulation pour qu'il soit informé rapidement
                foreach ($activityModel->getRegisteredUserIds($activity_id) as $registered_user_id) { // boucle sur les IDs des inscrits
                    notify($pdo, (int)$registered_user_id, 'activite_annulee', 'Activité annulée',
                        "L'activité \"{$activity_to_cancel['title']}\" à laquelle vous étiez inscrit(e) a été annulée.",
                        $activity_id); // envoie une notification à chaque participant inscrit
                }
            }
            $_SESSION['flash'] = "Votre activité a été annulée."; // message de confirmation pour l'organisateur
        } else {
            // Annulation impossible : l'activité n'existe pas, n'appartient pas à cet utilisateur,
            // ou n'est plus au statut 'active'
            $_SESSION['flash']      = "Impossible d'annuler cette activité.";
            $_SESSION['flash_type'] = 'error'; // type 'error' pour afficher le message en rouge dans l'interface
        }
    }
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id); // redirige vers la page de l'activité
    exit; // stoppe l'exécution après la redirection
}

// ── S'INSCRIRE ─────────────────────────────────────────────────────────────────
// Déclenche ce bloc uniquement si la page est 's_inscrire' ET que la requête est un POST
if ($page === 's_inscrire' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; } // redirige si non connecté
    csrf_check(); // vérifie le token CSRF

    $activity_id              = intval($_POST['activity_id'] ?? 0); // ID de l'activité sur laquelle s'inscrire
    if ($activity_id > 0) { // vérifie que l'ID est valide
        $activityModel        = new Activity($pdo); // instancie le modèle Activity
        $activity             = $activityModel->getById($activity_id); // récupère les données de l'activité
        $current_reg_status   = $activityModel->getRegistrationStatus($activity_id, $_SESSION['user']['id']); // statut d'inscription actuel de l'utilisateur
        // Valeurs possibles de $current_reg_status : null (jamais inscrit), 'inscrit', 'en_attente', 'annule'

        // Conditions pour pouvoir s'inscrire :
        // - L'activité existe et est au statut 'active' (pas annulée ni terminée)
        // - L'utilisateur n'est pas déjà inscrit ni en attente
        //   (null = jamais eu de ligne, 'annule' = s'était désinscrit → peut se réinscrire)
        if ($activity && $activity['status'] === 'active'
            && (!$current_reg_status || $current_reg_status === 'annule')) { // autoriser la réinscription après désinscription

            // Nom affiché dans la notification envoyée à l'organisateur
            $participant_display_name = htmlspecialchars($_SESSION['user']['pseudo'] ?? $_SESSION['user']['prenom']); // échappe les caractères HTML pour éviter les injections

            if ($activity['nb_inscrits'] < $activity['max_participants']) { // vérifie s'il reste de la place
                // Il reste au moins une place disponible : inscription directe
                $activityModel->register($activity_id, $_SESSION['user']['id']); // inscrit l'utilisateur dans la base
                // Notifie l'organisateur qu'il a une nouvelle inscription
                notify($pdo, (int)$activity['creator_id'], 'nouvelle_inscription', 'Nouvelle inscription',
                    "{$participant_display_name} s'est inscrit(e) à votre activité \"{$activity['title']}\".",
                    $activity_id); // envoie la notification à l'organisateur
                $_SESSION['flash'] = "Inscription confirmée ! À bientôt."; // message de succès pour le participant

            } elseif (!empty($activity['liste_attente_active'])) { // vérifie si la liste d'attente est activée
                // L'activité est complète mais la liste d'attente est activée par l'organisateur
                $activityModel->registerWaitlist($activity_id, $_SESSION['user']['id']); // ajoute l'utilisateur en liste d'attente
                $_SESSION['flash'] = "Activité complète. Vous avez été ajouté(e) à la liste d'attente.";
            }
            // Si l'activité est complète ET sans liste d'attente, on ne fait rien (cas géré par l'UI)
        }
    }
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id); // retourne à la page de détail
    exit; // stoppe l'exécution après la redirection
}

// ── SE DÉSINSCRIRE ─────────────────────────────────────────────────────────────
// Déclenche ce bloc uniquement si la page est 'se_desinscrire' ET que la requête est un POST
if ($page === 'se_desinscrire' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; } // redirige si non connecté
    csrf_check(); // vérifie le token CSRF

    $activity_id = intval($_POST['activity_id'] ?? 0); // ID de l'activité dont l'utilisateur veut se désinscrire
    if ($activity_id > 0) { // vérifie que l'ID est valide
        $activityModel = new Activity($pdo); // instancie le modèle Activity
        $activity      = $activityModel->getById($activity_id); // récupère les données de l'activité (pour la liste d'attente)

        // On mémorise le statut AVANT la désinscription pour distinguer un inscrit confirmé
        // d'une personne seulement en attente (seule la libération d'un inscrit confirmé déclenche une promotion)
        $was_confirmed_inscrit = $activityModel->getRegistrationStatus($activity_id, $_SESSION['user']['id']) === 'inscrit'; // true si l'utilisateur était bien confirmé

        // Passe le statut de la ligne de registration à 'annule' (conservée pour l'historique)
        $activityModel->unregister($activity_id, $_SESSION['user']['id']); // marque l'inscription comme annulée en base
        $_SESSION['flash'] = "Vous vous êtes désinscrit(e) de cette activité."; // message de confirmation

        // Promeut automatiquement la première personne en liste d'attente si :
        // - L'utilisateur était bien inscrit (pas juste en attente)
        // - La liste d'attente est activée sur cette activité
        if ($was_confirmed_inscrit && $activity && !empty($activity['liste_attente_active'])) { // vérifie les deux conditions
            $promoted_user_id = $activityModel->promoteFromWaitlist($activity_id); // retourne l'ID du premier en attente, ou null
            if ($promoted_user_id) { // si quelqu'un a été promu
                // Notifie la personne promue qu'une place vient de se libérer pour elle
                notify($pdo, (int)$promoted_user_id, 'promotion_attente', 'Place libérée !',
                    "Vous avez été promu(e) de la liste d'attente pour \"{$activity['title']}\".",
                    $activity_id); // envoie la notification à la personne promue
            }
        }
    }
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id); // retourne à la page de détail
    exit; // stoppe l'exécution après la redirection
}

// ── COMMENTER ──────────────────────────────────────────────────────────────────
// Déclenche ce bloc uniquement si la page est 'commenter' ET que la requête est un POST
if ($page === 'commenter' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; } // redirige si non connecté
    csrf_check(); // vérifie le token CSRF

    $activity_id     = intval($_POST['activity_id'] ?? 0);  // activité sur laquelle poster le commentaire
    $comment_content = trim($_POST['content'] ?? '');        // texte du commentaire (espaces en début/fin supprimés)

    // Validation minimale : l'ID d'activité doit être valide et le commentaire non vide
    if ($activity_id > 0 && $comment_content !== '') { // refuse les commentaires vides
        $activityModel = new Activity($pdo); // instancie le modèle Activity
        $activityModel->addComment($activity_id, $_SESSION['user']['id'], $comment_content); // insère le commentaire en base
    }

    // L'ancre #comments amène directement l'utilisateur à la section commentaires après la soumission
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id . '#comments'); // redirige avec ancre
    exit; // stoppe l'exécution après la redirection
}

// ── SUPPRIMER COMMENTAIRE ──────────────────────────────────────────────────────
// Déclenche ce bloc uniquement si la page est 'supprimer_commentaire' ET que la requête est un POST
if ($page === 'supprimer_commentaire' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; } // redirige si non connecté
    csrf_check(); // vérifie le token CSRF

    $comment_id_to_delete = intval($_POST['comment_id']  ?? 0);  // ID du commentaire à supprimer
    $activity_id          = intval($_POST['activity_id'] ?? 0);  // ID de l'activité (pour la redirection)

    if ($comment_id_to_delete > 0) { // vérifie que l'ID du commentaire est valide
        $activityModel = new Activity($pdo); // instancie le modèle Activity
        if (is_admin()) { // vérifie si l'utilisateur connecté est un administrateur ou l'owner
            // Un admin peut supprimer n'importe quel commentaire à des fins de modération
            $activityModel->deleteCommentAsAdmin($comment_id_to_delete); // suppression sans vérifier l'auteur
        } else {
            // Un utilisateur normal ne peut supprimer que ses propres commentaires
            // La vérification AND user_id = :u est faite dans Activity::deleteComment
            $activityModel->deleteComment($comment_id_to_delete, $_SESSION['user']['id']); // suppression conditionnelle à l'auteur
        }
    }
    // L'ancre #comments replace l'utilisateur dans la section commentaires
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id . '#comments'); // redirige avec ancre
    exit; // stoppe l'exécution après la redirection
}

// ── NOTER UN ORGANISATEUR ──────────────────────────────────────────────────────
// Déclenche ce bloc uniquement si la page est 'noter' ET que la requête est un POST
if ($page === 'noter' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user'])) { header('Location: /sharetime/public/?page=connexion'); exit; } // redirige si non connecté
    csrf_check(); // vérifie le token CSRF

    $activity_id  = intval($_POST['activity_id'] ?? 0);  // activité pour laquelle on note l'organisateur
    $rating_value = intval($_POST['note']        ?? 0);  // note donnée, comprise entre 1 et 5

    // Validation de plage côté handler (la même vérification existe aussi dans Activity::rate)
    if ($activity_id > 0 && $rating_value >= 1 && $rating_value <= 5) { // rejette les notes hors plage
        $activityModel = new Activity($pdo); // instancie le modèle Activity
        $activity      = $activityModel->getById($activity_id); // récupère les données de l'activité

        // Deux conditions pour pouvoir noter :
        // 1. L'activité doit être au statut 'terminee' (pas de notation pendant l'activité)
        // 2. L'utilisateur doit avoir le statut 'inscrit' (pas 'annule', pas 'en_attente')
        //    pour s'assurer qu'il a bien participé à l'activité
        if ($activity && $activity['status'] === 'terminee') { // refuse la notation si l'activité n'est pas terminée
            $voter_reg_status = $activityModel->getRegistrationStatus($activity_id, $_SESSION['user']['id']); // statut d'inscription du votant
            if ($voter_reg_status === 'inscrit') { // seuls les participants confirmés peuvent noter
                // Activity::rate vérifie hasRated() en interne pour éviter les votes doubles
                $activityModel->rate($_SESSION['user']['id'], $activity['creator_id'], $activity_id, $rating_value); // enregistre la note en base
                $_SESSION['flash'] = "Votre note a bien été enregistrée. Merci !"; // message de confirmation
            }
        }
    }
    // L'ancre #rating ramène l'utilisateur directement à la section de notation
    header('Location: /sharetime/public/?page=detail&id=' . $activity_id . '#rating'); // redirige avec ancre
    exit; // stoppe l'exécution après la redirection
}
