<?php
/**
 * app/handlers/admin.php — Handlers des actions d'administration
 *
 * Inclus inconditionnellement par public/index.php avant le routing.
 * Chaque bloc vérifie $page et REQUEST_METHOD avant d'agir.
 *
 * Séparation des responsabilités :
 *   - handler "owner"            : toutes les actions (ban, rôle, suppression, transfert, activités, contenu)
 *   - handler "admin_users"      : uniquement ban/unban des membres (pas des admins ni de l'owner)
 *   - handler "admin_activities" : modération des activités par les admins
 *   - handler "contact"          : marquer lu / supprimer des messages de contact (admins + owner)
 *
 * Toute action destructive ou de modération est tracée via log_admin_action().
 */

// ── OWNER : TOUTES LES ACTIONS ─────────────────────────────────────────────────
// Ce handler reçoit TOUS les formulaires POST soumis depuis le panel propriétaire.
// L'owner peut agir sur les utilisateurs (ban, rôle, suppression, transfert)
// et sur les activités (statut, suppression) et sur le contenu éditorial (FAQ, CGU, Mentions).
if ($page === 'owner' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    require_owner();  // Arrête l'exécution immédiatement si l'utilisateur n'est pas owner
    csrf_check();     // Vérifie le token anti-CSRF pour rejeter les requêtes forgées depuis un autre site

    // ── Lecture des paramètres communs à toutes les actions du panel owner ──
    $action           = $_POST['action'] ?? '';     // identifiant de l'action : 'ban', 'unban', 'set_role', 'delete', 'transfer_ownership'…
    $target_type      = $_POST['type']   ?? 'user'; // type de l'entité ciblée : 'user', 'activity', 'report' ou 'content'

    // Whitelist des onglets valides : empêche une redirection vers un onglet arbitraire
    $valid_tab_names  = ['dashboard', 'users', 'activities', 'admins', 'contact', 'contenu', 'signalements'];
    $return_tab       = in_array($_POST['tab'] ?? '', $valid_tab_names)
                        ? $_POST['tab']
                        : 'dashboard';              // onglet de retour après l'action (fallback 'dashboard')

    $current_owner_id = (int)$_SESSION['user']['id'];  // ID de l'owner connecté, pour éviter les auto-actions

    // ── Branche "user" : actions sur un compte utilisateur ────────────────────
    if ($target_type === 'user') {

        $target_user_id = intval($_POST['user_id'] ?? 0);  // ID de l'utilisateur visé par l'action

        // Refuse toute action sur soi-même : l'owner ne peut pas se dégrader, se bannir ou se supprimer
        if ($target_user_id > 0 && $target_user_id !== $current_owner_id) {

            $userModel   = new User($pdo);                     // instance du modèle User pour les opérations en base
            $target_user = $userModel->getById($target_user_id);  // données complètes de la cible (pour les logs et les contrôles)

            if ($action === 'ban') {
                // Suspension : passe is_banned à 1 → l'utilisateur sera déconnecté à la prochaine vérification de session
                $userModel->setBanned($target_user_id, true);
                // Log après l'action pour tracer qui a fait quoi et sur qui
                log_admin_action($pdo, 'ban', 'user', $target_user_id,
                    'Suspension de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));

            } elseif ($action === 'unban') {
                // Réactivation : repasse is_banned à 0, l'utilisateur peut se reconnecter
                $userModel->setBanned($target_user_id, false);
                log_admin_action($pdo, 'unban', 'user', $target_user_id,
                    'Réactivation de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));

            } elseif ($action === 'set_role') {
                // Changement de rôle : whitelist stricte — 'owner' est absent car le transfert
                // de propriété a sa propre action dédiée (transfer_ownership).
                $new_role = in_array($_POST['role'] ?? '', ['utilisateur', 'admin'])
                            ? $_POST['role']
                            : null;  // null si la valeur reçue n'est pas dans la whitelist

                if ($new_role) {
                    // User::setRole vérifie en plus en base que la cible n'est pas owner (double protection)
                    $userModel->setRole($target_user_id, $new_role);
                    log_admin_action($pdo, 'set_role', 'user', $target_user_id,
                        'Rôle → ' . $new_role . ' pour ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));
                }

            } elseif ($action === 'transfer_ownership') {
                // Transfert de propriété : transaction atomique dans User::transferOwnership
                // → l'ancien owner devient admin EN MÊME TEMPS que le nouveau devient owner.
                if ($userModel->transferOwnership($target_user_id, $current_owner_id)) {
                    log_admin_action($pdo, 'transfer_ownership', 'user', $target_user_id,
                        'Transfert propriété à ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));

                    // Rétrograde immédiatement le rôle en session :
                    // l'ex-owner est maintenant admin, ce qui doit être reflété dans la navbar
                    $_SESSION['user']['role'] = 'admin';
                    $_SESSION['flash'] = "Propriété transférée avec succès.";
                    header('Location: /sharetime/public/?page=owner&tab=admins');
                    exit;
                }
                // Échec du transfert (cible déjà owner, inexistante, ou IDs identiques)
                $_SESSION['flash'] = "Transfert impossible.";
                header('Location: /sharetime/public/?page=owner&tab=admins');
                exit;

            } elseif ($action === 'delete') {
                // Log AVANT la suppression : après User::delete(), la ligne n'existe plus en base,
                // donc on ne pourrait plus récupérer le nom de la cible pour le log.
                log_admin_action($pdo, 'delete_user', 'user', $target_user_id,
                    'Suppression de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));
                // User::delete() supprime le compte + toutes ses données en cascade (transaction)
                $userModel->delete($target_user_id);
            }
        }

    // ── Branche "activity" : actions sur une activité ─────────────────────────
    } elseif ($target_type === 'activity') {

        $target_activity_id = intval($_POST['activity_id'] ?? 0);  // ID de l'activité visée

        if ($target_activity_id > 0) {
            $activityModel = new Activity($pdo);  // instance du modèle Activity

            if ($action === 'set_status') {
                // Changement de statut : la whitelist est vérifiée dans Activity::setStatus
                $activityModel->setStatus($target_activity_id, $_POST['status'] ?? '');
                log_admin_action($pdo, 'set_status', 'activity', $target_activity_id,
                    'Statut → ' . ($_POST['status'] ?? ''));

            } elseif ($action === 'delete') {
                // Suppression de l'activité ET de ses inscrits, commentaires et notes associés
                log_admin_action($pdo, 'delete_activity', 'activity', $target_activity_id, 'Suppression activité');
                $activityModel->delete($target_activity_id);
            }
        }

    // ── Branche "report" : traitement d'un signalement ────────────────────────
    } elseif ($target_type === 'report') {

        $target_report_id  = intval($_POST['report_id'] ?? 0);  // ID du signalement à traiter

        // Whitelist sur le nouveau statut : seuls 'traite' et 'rejete' sont acceptables
        $new_report_status = in_array($_POST['status'] ?? '', ['traite', 'rejete'])
                             ? $_POST['status']
                             : null;

        if ($target_report_id && $new_report_status) {
            // Met à jour le statut du signalement (traite = pris en charge, rejete = sans suite)
            $pdo->prepare("UPDATE reports SET status = :s WHERE idreports = :id")
                ->execute(['s' => $new_report_status, 'id' => $target_report_id]);
            log_admin_action($pdo, 'update_report', 'user', $target_report_id,
                'Signalement #' . $target_report_id . ' → ' . $new_report_status);
        }

        // Redirige vers l'onglet signalements, peu importe le résultat
        $_SESSION['flash'] = "Signalement mis à jour.";
        header('Location: /sharetime/public/?page=owner&tab=signalements');
        exit;

    // ── Branche "content" : gestion du contenu éditorial ─────────────────────
    } elseif ($target_type === 'content') {

        if ($action === 'add_faq') {
            $faq_question = trim($_POST['question'] ?? '');  // intitulé de la nouvelle question FAQ
            $faq_reponse  = trim($_POST['reponse']  ?? '');  // texte de la réponse à afficher

            if ($faq_question && $faq_reponse) {
                // Insère une nouvelle entrée dans la table faq
                $pdo->prepare("INSERT INTO faq (question, reponse) VALUES (:q, :r)")
                    ->execute(['q' => $faq_question, 'r' => $faq_reponse]);
                // Tronque à 80 caractères dans le log pour éviter les entrées trop longues
                log_admin_action($pdo, 'add_faq', 'user', $current_owner_id,
                    'Ajout FAQ : ' . mb_substr($faq_question, 0, 80));
            }
            $_SESSION['flash'] = "Question ajoutée.";

        } elseif ($action === 'edit_faq') {
            $faq_id       = intval($_POST['faq_id'] ?? 0);   // ID de l'entrée FAQ à modifier
            $faq_question = trim($_POST['question'] ?? '');  // nouvel intitulé de la question
            $faq_reponse  = trim($_POST['reponse']  ?? '');  // nouveau texte de la réponse

            if ($faq_id && $faq_question && $faq_reponse) {
                // Écrase la question et la réponse de l'entrée ciblée
                $pdo->prepare("UPDATE faq SET question = :q, reponse = :r WHERE idfaq = :id")
                    ->execute(['q' => $faq_question, 'r' => $faq_reponse, 'id' => $faq_id]);
                log_admin_action($pdo, 'edit_faq', 'user', $current_owner_id, 'Modif FAQ #' . $faq_id);
            }
            $_SESSION['flash'] = "Question mise à jour.";

        } elseif ($action === 'delete_faq') {
            $faq_id = intval($_POST['faq_id'] ?? 0);  // ID de l'entrée FAQ à supprimer

            if ($faq_id) {
                $pdo->prepare("DELETE FROM faq WHERE idfaq = :id")->execute(['id' => $faq_id]);
                log_admin_action($pdo, 'delete_faq', 'user', $current_owner_id, 'Suppression FAQ #' . $faq_id);
            }
            $_SESSION['flash'] = "Question supprimée.";

        } elseif ($action === 'update_cgu') {
            $cgu_contenu = trim($_POST['contenu'] ?? '');  // nouveau texte des CGU (Markdown ou HTML)
            $cgu_version = trim($_POST['version'] ?? '');  // numéro de version des CGU, ex. 'v2.0'

            if ($cgu_contenu) {
                // La table cgu ne contient qu'une seule ligne active : on vide avant de réinsérer
                // pour éviter les doublons et garantir qu'une seule version est en vigueur.
                $pdo->exec("DELETE FROM cgu");
                $pdo->prepare("INSERT INTO cgu (contenu, version) VALUES (:c, :v)")
                    ->execute(['c' => $cgu_contenu, 'v' => $cgu_version ?: null]);
                log_admin_action($pdo, 'update_cgu', 'user', $current_owner_id, 'Mise à jour CGU');
            }
            $_SESSION['flash'] = "CGU mises à jour.";

        } elseif ($action === 'update_mentions') {
            $mentions_contenu = trim($_POST['contenu'] ?? '');  // nouveau texte des mentions légales

            if ($mentions_contenu) {
                // Même principe que les CGU : une seule ligne dans la table mentions
                $pdo->exec("DELETE FROM mentions");
                $pdo->prepare("INSERT INTO mentions (contenu) VALUES (:c)")->execute(['c' => $mentions_contenu]);
                log_admin_action($pdo, 'update_mentions', 'user', $current_owner_id, 'Mise à jour mentions légales');
            }
            $_SESSION['flash'] = "Mentions légales mises à jour.";
        }

        // Toutes les actions de contenu redirigent vers l'onglet contenu
        header('Location: /sharetime/public/?page=owner&tab=contenu');
        exit;
    }

    // Flash générique pour les actions qui n'ont pas déclenché de redirection propre (ban, rôle…)
    $_SESSION['flash'] = "Action effectuée.";
    // Redirige vers l'onglet d'où provenait l'action pour préserver le contexte de navigation
    header('Location: /sharetime/public/?page=owner&tab=' . $return_tab);
    exit;
}

// ── ADMIN : GESTION UTILISATEURS ───────────────────────────────────────────────
// Version restreinte du handler owner : les admins ne peuvent que ban/unban les membres.
// Ils ne peuvent PAS agir sur les autres admins ni sur l'owner.
if ($page === 'admin_users' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();  // Refuse l'accès si l'utilisateur n'est pas au moins admin
    csrf_check();

    $action           = $_POST['action'] ?? '';          // 'ban' ou 'unban'
    $target_user_id   = intval($_POST['user_id'] ?? 0);  // ID de l'utilisateur à suspendre ou réactiver
    $current_admin_id = (int)$_SESSION['user']['id'];    // ID de l'admin connecté

    // Refuse d'agir sur soi-même et rejette les IDs invalides
    if ($target_user_id > 0 && $target_user_id !== $current_admin_id) {

        $userModel   = new User($pdo);
        $target_user = $userModel->getById($target_user_id);  // données de la cible pour contrôler son rôle

        if ($action === 'ban') {
            // Un admin ne peut pas suspendre un autre admin : seul l'owner a ce droit
            if ($target_user && $target_user['role'] === 'admin') {
                $_SESSION['flash'] = "Vous n'avez pas le droit de suspendre un administrateur.";
                header('Location: /sharetime/public/?page=admin_users'); exit;
            }
            // Suspension : User::setBanned vérifie aussi AND role != 'owner' en base (double protection)
            $userModel->setBanned($target_user_id, true);
            log_admin_action($pdo, 'ban', 'user', $target_user_id,
                'Suspension de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));

        } elseif ($action === 'unban') {
            // Même restriction pour la réactivation : un admin ne peut pas débannir un autre admin
            if ($target_user && $target_user['role'] === 'admin') {
                $_SESSION['flash'] = "Vous n'avez pas le droit de réactiver un administrateur.";
                header('Location: /sharetime/public/?page=admin_users'); exit;
            }
            $userModel->setBanned($target_user_id, false);
            log_admin_action($pdo, 'unban', 'user', $target_user_id,
                'Réactivation de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));
        }
    }

    $_SESSION['flash'] = "Action effectuée.";
    header('Location: /sharetime/public/?page=admin_users');
    exit;
}

// ── ADMIN : GESTION ACTIVITÉS ──────────────────────────────────────────────────
// Permet aux admins de modérer les activités : changer leur statut ou les supprimer.
if ($page === 'admin_activities' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    csrf_check();

    $action             = $_POST['action']            ?? '';  // 'delete' ou 'set_status'
    $target_activity_id = intval($_POST['activity_id'] ?? 0); // ID de l'activité à modérer

    if ($target_activity_id > 0) {
        $activityModel = new Activity($pdo);

        if ($action === 'delete') {
            // Activity::delete() supprime aussi les inscriptions, commentaires et notes associés
            log_admin_action($pdo, 'delete_activity', 'activity', $target_activity_id, 'Suppression activité (admin)');
            $activityModel->delete($target_activity_id);

        } elseif ($action === 'set_status') {
            // La whitelist des statuts acceptables est vérifiée dans Activity::setStatus
            $activityModel->setStatus($target_activity_id, $_POST['status'] ?? '');
            log_admin_action($pdo, 'set_status', 'activity', $target_activity_id,
                'Statut → ' . ($_POST['status'] ?? '') . ' (admin)');
        }
    }
    header('Location: /sharetime/public/?page=admin_activities');
    exit;
}

// ── MESSAGES CONTACT : MARQUER LU / SUPPRIMER ─────────────────────────────────
// Ce bloc est partagé entre la page admin_contact (admins) et le panel owner (onglet contact).
// Il est déclenché par la présence de $_POST['contact_action'], distinct des autres actions POST.
if (in_array($page, ['admin_contact', 'owner']) && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['contact_action'])) {
    require_admin();  // Accessible aux admins ET à l'owner (is_admin() couvre les deux rôles)
    csrf_check();

    $contact_msg_id  = intval($_POST['msg_id'] ?? 0);       // ID du message de contact concerné
    $origin_page     = $_POST['from'] ?? 'admin_contact';   // page d'origine, détermine l'URL de retour

    if ($contact_msg_id > 0) {

        if ($_POST['contact_action'] === 'mark_read') {
            // Marque ce message comme lu (is_read = 1) sans le supprimer
            $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = :id")->execute(['id' => $contact_msg_id]);

        } elseif ($_POST['contact_action'] === 'mark_unread') {
            // Repasse ce message en non-lu pour le traiter plus tard
            $pdo->prepare("UPDATE contact_messages SET is_read = 0 WHERE id = :id")->execute(['id' => $contact_msg_id]);

        } elseif ($_POST['contact_action'] === 'delete') {
            // Suppression définitive du message de contact (aucune corbeille)
            $pdo->prepare("DELETE FROM contact_messages WHERE id = :id")->execute(['id' => $contact_msg_id]);

        } elseif ($_POST['contact_action'] === 'mark_all_read') {
            // Marque TOUS les messages comme lus en une seule requête (sans WHERE sur l'ID)
            $pdo->exec("UPDATE contact_messages SET is_read = 1");
        }

    } elseif ($_POST['contact_action'] === 'mark_all_read') {
        // Cas où l'action "tout marquer lu" est envoyée sans cibler un message précis
        $pdo->exec("UPDATE contact_messages SET is_read = 1");
    }

    // Redirige vers la bonne page selon la provenance de la requête
    $redirect_after_contact = $origin_page === 'owner'
        ? '/sharetime/public/?page=owner&tab=contact'
        : '/sharetime/public/?page=admin_contact';
    header('Location: ' . $redirect_after_contact);
    exit;
}
