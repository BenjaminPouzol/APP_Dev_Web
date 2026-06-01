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
if ($page === 'owner' && $_SERVER['REQUEST_METHOD'] === 'POST') { // déclenche uniquement sur la page owner en POST

    require_owner();  // Arrête l'exécution immédiatement si l'utilisateur n'est pas owner
    csrf_check();     // Vérifie le token anti-CSRF pour rejeter les requêtes forgées depuis un autre site

    // ── Lecture des paramètres communs à toutes les actions du panel owner ──
    $action           = $_POST['action'] ?? '';     // identifiant de l'action : 'ban', 'unban', 'set_role', 'delete', 'transfer_ownership'…
    $target_type      = $_POST['type']   ?? 'user'; // type de l'entité ciblée : 'user', 'activity', 'report' ou 'content'

    // Whitelist des onglets valides : empêche une redirection vers un onglet arbitraire
    $valid_tab_names  = ['dashboard', 'users', 'activities', 'admins', 'contact', 'contenu', 'signalements']; // liste des onglets autorisés
    $return_tab       = in_array($_POST['tab'] ?? '', $valid_tab_names)
                        ? $_POST['tab']
                        : 'dashboard';              // onglet de retour après l'action (fallback 'dashboard')

    $current_owner_id = (int)$_SESSION['user']['id'];  // ID de l'owner connecté, pour éviter les auto-actions

    // ── Branche "user" : actions sur un compte utilisateur ────────────────────
    if ($target_type === 'user') { // traite les actions ciblant un utilisateur

        $target_user_id = intval($_POST['user_id'] ?? 0);  // ID de l'utilisateur visé par l'action

        // Refuse toute action sur soi-même : l'owner ne peut pas se dégrader, se bannir ou se supprimer
        if ($target_user_id > 0 && $target_user_id !== $current_owner_id) { // vérifie que la cible est valide et différente de l'owner

            $userModel   = new User($pdo);                     // instance du modèle User pour les opérations en base
            $target_user = $userModel->getById($target_user_id);  // données complètes de la cible (pour les logs et les contrôles)

            if ($action === 'ban') { // action de suspension du compte
                // Suspension : passe is_banned à 1 → l'utilisateur sera déconnecté à la prochaine vérification de session
                $userModel->setBanned($target_user_id, true); // met is_banned = 1 en base
                // Log après l'action pour tracer qui a fait quoi et sur qui
                log_admin_action($pdo, 'ban', 'user', $target_user_id,
                    'Suspension de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? '')); // enregistre l'action dans les logs

            } elseif ($action === 'unban') { // action de réactivation du compte
                // Réactivation : repasse is_banned à 0, l'utilisateur peut se reconnecter
                $userModel->setBanned($target_user_id, false); // met is_banned = 0 en base
                log_admin_action($pdo, 'unban', 'user', $target_user_id,
                    'Réactivation de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? '')); // trace la réactivation

            } elseif ($action === 'set_role') { // action de changement de rôle
                // Changement de rôle : whitelist stricte — 'owner' est absent car le transfert
                // de propriété a sa propre action dédiée (transfer_ownership).
                $new_role = in_array($_POST['role'] ?? '', ['utilisateur', 'admin'])
                            ? $_POST['role']
                            : null;  // null si la valeur reçue n'est pas dans la whitelist

                if ($new_role) { // continue uniquement si le rôle est valide
                    // User::setRole vérifie en plus en base que la cible n'est pas owner (double protection)
                    $userModel->setRole($target_user_id, $new_role); // met à jour le rôle en base
                    log_admin_action($pdo, 'set_role', 'user', $target_user_id,
                        'Rôle → ' . $new_role . ' pour ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? '')); // trace le changement de rôle
                }

            } elseif ($action === 'transfer_ownership') { // action de transfert de propriété de la plateforme
                // Transfert de propriété : transaction atomique dans User::transferOwnership
                // → l'ancien owner devient admin EN MÊME TEMPS que le nouveau devient owner.
                if ($userModel->transferOwnership($target_user_id, $current_owner_id)) { // retourne true si le transfert a réussi
                    log_admin_action($pdo, 'transfer_ownership', 'user', $target_user_id,
                        'Transfert propriété à ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? '')); // trace le transfert

                    // Rétrograde immédiatement le rôle en session :
                    // l'ex-owner est maintenant admin, ce qui doit être reflété dans la navbar
                    $_SESSION['user']['role'] = 'admin'; // met à jour le rôle de l'ex-owner en session sans attendre une nouvelle connexion
                    $_SESSION['flash'] = "Prérogatives Super-Admin transférées avec succès."; // message de confirmation
                    header('Location: /sharetime/public/?page=owner&tab=admins'); // redirige vers l'onglet admins
                    exit; // stoppe l'exécution après la redirection
                }
                // Échec du transfert (cible déjà owner, inexistante, ou IDs identiques)
                $_SESSION['flash'] = "Transfert impossible."; // message d'erreur si le transfert a échoué
                header('Location: /sharetime/public/?page=owner&tab=admins'); // redirige tout de même
                exit; // stoppe l'exécution

            } elseif ($action === 'delete') { // action de suppression définitive du compte
                // Log AVANT la suppression : après User::delete(), la ligne n'existe plus en base,
                // donc on ne pourrait plus récupérer le nom de la cible pour le log.
                log_admin_action($pdo, 'delete_user', 'user', $target_user_id,
                    'Suppression de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? '')); // trace avant suppression
                // User::delete() supprime le compte + toutes ses données en cascade (transaction)
                $userModel->delete($target_user_id); // supprime le compte et toutes ses données associées
            }
        }

    // ── Branche "activity" : actions sur une activité ─────────────────────────
    } elseif ($target_type === 'activity') { // traite les actions ciblant une activité

        $target_activity_id = intval($_POST['activity_id'] ?? 0);  // ID de l'activité visée

        if ($target_activity_id > 0) { // vérifie que l'ID est valide
            $activityModel = new Activity($pdo);  // instance du modèle Activity

            if ($action === 'set_status') { // action de changement de statut d'une activité
                // Changement de statut : la whitelist est vérifiée dans Activity::setStatus
                $activityModel->setStatus($target_activity_id, $_POST['status'] ?? ''); // met à jour le statut en base
                log_admin_action($pdo, 'set_status', 'activity', $target_activity_id,
                    'Statut → ' . ($_POST['status'] ?? '')); // trace le changement de statut

            } elseif ($action === 'delete') { // action de suppression définitive d'une activité
                // Suppression de l'activité ET de ses inscrits, commentaires et notes associés
                log_admin_action($pdo, 'delete_activity', 'activity', $target_activity_id, 'Suppression activité'); // trace avant suppression
                $activityModel->delete($target_activity_id); // supprime l'activité et toutes ses données liées
            }
        }

    // ── Branche "report" : traitement d'un signalement ────────────────────────
    } elseif ($target_type === 'report') { // traite les actions ciblant un signalement

        $target_report_id  = intval($_POST['report_id'] ?? 0);  // ID du signalement à traiter

        // Whitelist sur le nouveau statut : seuls 'traite' et 'rejete' sont acceptables
        $new_report_status = in_array($_POST['status'] ?? '', ['traite', 'rejete'])
                             ? $_POST['status']
                             : null; // null si le statut soumis n'est pas dans la liste autorisée

        if ($target_report_id && $new_report_status) { // continue uniquement si l'ID et le statut sont valides
            // Met à jour le statut du signalement (traite = pris en charge, rejete = sans suite)
            $pdo->prepare("UPDATE reports SET status = :s WHERE idreports = :id")
                ->execute(['s' => $new_report_status, 'id' => $target_report_id]); // exécute la mise à jour en base
            log_admin_action($pdo, 'update_report', 'user', $target_report_id,
                'Signalement #' . $target_report_id . ' → ' . $new_report_status); // trace la décision prise sur le signalement
        }

        // Redirige vers l'onglet signalements, peu importe le résultat
        $_SESSION['flash'] = "Signalement mis à jour."; // message de confirmation pour l'owner
        header('Location: /sharetime/public/?page=owner&tab=signalements'); // retourne à l'onglet signalements
        exit; // stoppe l'exécution après la redirection

    // ── Branche "content" : gestion du contenu éditorial ─────────────────────
    } elseif ($target_type === 'content') { // traite les actions sur le contenu éditorial (FAQ, CGU, mentions)

        if ($action === 'add_faq') { // action d'ajout d'une nouvelle entrée FAQ
            $faq_question = trim($_POST['question'] ?? '');  // intitulé de la nouvelle question FAQ
            $faq_reponse  = trim($_POST['reponse']  ?? '');  // texte de la réponse à afficher

            if ($faq_question && $faq_reponse) { // les deux champs sont obligatoires
                // Insère une nouvelle entrée dans la table faq
                $pdo->prepare("INSERT INTO faq (question, reponse) VALUES (:q, :r)")
                    ->execute(['q' => $faq_question, 'r' => $faq_reponse]); // exécute l'insertion en base
                // Tronque à 80 caractères dans le log pour éviter les entrées trop longues
                log_admin_action($pdo, 'add_faq', 'user', $current_owner_id,
                    'Ajout FAQ : ' . mb_substr($faq_question, 0, 80)); // mb_substr gère correctement les caractères multi-octets (accents)
            }
            $_SESSION['flash'] = "Question ajoutée."; // message de confirmation

        } elseif ($action === 'edit_faq') { // action de modification d'une entrée FAQ existante
            $faq_id       = intval($_POST['faq_id'] ?? 0);   // ID de l'entrée FAQ à modifier
            $faq_question = trim($_POST['question'] ?? '');  // nouvel intitulé de la question
            $faq_reponse  = trim($_POST['reponse']  ?? '');  // nouveau texte de la réponse

            if ($faq_id && $faq_question && $faq_reponse) { // les trois champs sont obligatoires
                // Écrase la question et la réponse de l'entrée ciblée
                $pdo->prepare("UPDATE faq SET question = :q, reponse = :r WHERE idfaq = :id")
                    ->execute(['q' => $faq_question, 'r' => $faq_reponse, 'id' => $faq_id]); // exécute la mise à jour
                log_admin_action($pdo, 'edit_faq', 'user', $current_owner_id, 'Modif FAQ #' . $faq_id); // trace la modification
            }
            $_SESSION['flash'] = "Question mise à jour."; // message de confirmation

        } elseif ($action === 'delete_faq') { // action de suppression d'une entrée FAQ
            $faq_id = intval($_POST['faq_id'] ?? 0);  // ID de l'entrée FAQ à supprimer

            if ($faq_id) { // vérifie que l'ID est valide
                $pdo->prepare("DELETE FROM faq WHERE idfaq = :id")->execute(['id' => $faq_id]); // supprime l'entrée de la base
                log_admin_action($pdo, 'delete_faq', 'user', $current_owner_id, 'Suppression FAQ #' . $faq_id); // trace la suppression
            }
            $_SESSION['flash'] = "Question supprimée."; // message de confirmation

        } elseif ($action === 'update_cgu') { // action de mise à jour des Conditions Générales d'Utilisation
            $cgu_contenu = trim($_POST['contenu'] ?? '');  // nouveau texte des CGU (Markdown ou HTML)
            $cgu_version = trim($_POST['version'] ?? '');  // numéro de version des CGU, ex. 'v2.0'

            if ($cgu_contenu) { // le contenu est obligatoire
                // La table cgu ne contient qu'une seule ligne active : on vide avant de réinsérer
                // pour éviter les doublons et garantir qu'une seule version est en vigueur.
                $pdo->exec("DELETE FROM cgu"); // supprime la version précédente des CGU
                $pdo->prepare("INSERT INTO cgu (contenu, version) VALUES (:c, :v)")
                    ->execute(['c' => $cgu_contenu, 'v' => $cgu_version ?: null]); // insère la nouvelle version (version peut être null)
                log_admin_action($pdo, 'update_cgu', 'user', $current_owner_id, 'Mise à jour CGU'); // trace la mise à jour
            }
            $_SESSION['flash'] = "CGU mises à jour."; // message de confirmation

        } elseif ($action === 'update_mentions') { // action de mise à jour des mentions légales
            $mentions_contenu = trim($_POST['contenu'] ?? '');  // nouveau texte des mentions légales

            if ($mentions_contenu) { // le contenu est obligatoire
                // Même principe que les CGU : une seule ligne dans la table mentions
                $pdo->exec("DELETE FROM mentions"); // supprime les anciennes mentions légales
                $pdo->prepare("INSERT INTO mentions (contenu) VALUES (:c)")->execute(['c' => $mentions_contenu]); // insère les nouvelles mentions
                log_admin_action($pdo, 'update_mentions', 'user', $current_owner_id, 'Mise à jour mentions légales'); // trace la mise à jour
            }
            $_SESSION['flash'] = "Mentions légales mises à jour."; // message de confirmation
        }

        // Toutes les actions de contenu redirigent vers l'onglet contenu
        header('Location: /sharetime/public/?page=owner&tab=contenu'); // retourne à l'onglet de gestion du contenu
        exit; // stoppe l'exécution après la redirection
    }

    // Flash générique pour les actions qui n'ont pas déclenché de redirection propre (ban, rôle…)
    $_SESSION['flash'] = "Action effectuée."; // message générique de confirmation
    // Redirige vers l'onglet d'où provenait l'action pour préserver le contexte de navigation
    header('Location: /sharetime/public/?page=owner&tab=' . $return_tab); // retourne à l'onglet d'origine
    exit; // stoppe l'exécution après la redirection
}

// ── ADMIN : GESTION UTILISATEURS ───────────────────────────────────────────────
// Version restreinte du handler owner : les admins ne peuvent que ban/unban les membres.
// Ils ne peuvent PAS agir sur les autres admins ni sur l'owner.
if ($page === 'admin_users' && $_SERVER['REQUEST_METHOD'] === 'POST') { // déclenche uniquement sur la page admin_users en POST
    require_admin();  // Refuse l'accès si l'utilisateur n'est pas au moins admin
    csrf_check(); // vérifie le token CSRF

    $action           = $_POST['action'] ?? '';          // 'ban' ou 'unban'
    $target_user_id   = intval($_POST['user_id'] ?? 0);  // ID de l'utilisateur à suspendre ou réactiver
    $current_admin_id = (int)$_SESSION['user']['id'];    // ID de l'admin connecté

    // Refuse d'agir sur soi-même et rejette les IDs invalides
    if ($target_user_id > 0 && $target_user_id !== $current_admin_id) { // empêche l'admin de s'auto-bannir

        $userModel   = new User($pdo); // instancie le modèle User
        $target_user = $userModel->getById($target_user_id);  // données de la cible pour contrôler son rôle

        if ($action === 'ban') { // action de suspension
            // Un admin ne peut pas suspendre un autre admin : seul l'owner a ce droit
            if ($target_user && $target_user['role'] === 'admin') { // vérifie que la cible n'est pas admin
                $_SESSION['flash'] = "Vous n'avez pas le droit de suspendre un administrateur."; // message d'erreur
                header('Location: /sharetime/public/?page=admin_users'); exit; // redirige sans appliquer l'action
            }
            // Suspension : User::setBanned vérifie aussi AND role != 'owner' en base (double protection)
            $userModel->setBanned($target_user_id, true); // met is_banned = 1 en base
            log_admin_action($pdo, 'ban', 'user', $target_user_id,
                'Suspension de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? '')); // trace la suspension

        } elseif ($action === 'unban') { // action de réactivation
            // Même restriction pour la réactivation : un admin ne peut pas débannir un autre admin
            if ($target_user && $target_user['role'] === 'admin') { // vérifie que la cible n'est pas admin
                $_SESSION['flash'] = "Vous n'avez pas le droit de réactiver un administrateur."; // message d'erreur
                header('Location: /sharetime/public/?page=admin_users'); exit; // redirige sans appliquer l'action
            }
            $userModel->setBanned($target_user_id, false); // met is_banned = 0 en base
            log_admin_action($pdo, 'unban', 'user', $target_user_id,
                'Réactivation de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? '')); // trace la réactivation
        }
    }

    $_SESSION['flash'] = "Action effectuée."; // message générique de confirmation
    header('Location: /sharetime/public/?page=admin_users'); // retourne à la page de gestion des utilisateurs
    exit; // stoppe l'exécution après la redirection
}

// ── ADMIN : GESTION ACTIVITÉS ──────────────────────────────────────────────────
// Permet aux admins de modérer les activités : changer leur statut ou les supprimer.
if ($page === 'admin_activities' && $_SERVER['REQUEST_METHOD'] === 'POST') { // déclenche uniquement sur la page admin_activities en POST
    require_admin(); // refuse l'accès si l'utilisateur n'est pas au moins admin
    csrf_check(); // vérifie le token CSRF

    $action             = $_POST['action']            ?? '';  // 'delete' ou 'set_status'
    $target_activity_id = intval($_POST['activity_id'] ?? 0); // ID de l'activité à modérer

    if ($target_activity_id > 0) { // vérifie que l'ID est valide
        $activityModel = new Activity($pdo); // instancie le modèle Activity

        if ($action === 'delete') { // action de suppression de l'activité
            // Activity::delete() supprime aussi les inscriptions, commentaires et notes associés
            log_admin_action($pdo, 'delete_activity', 'activity', $target_activity_id, 'Suppression activité (admin)'); // trace avant suppression
            $activityModel->delete($target_activity_id); // supprime l'activité et ses données liées

        } elseif ($action === 'set_status') { // action de changement de statut de l'activité
            // La whitelist des statuts acceptables est vérifiée dans Activity::setStatus
            $activityModel->setStatus($target_activity_id, $_POST['status'] ?? ''); // met à jour le statut en base
            log_admin_action($pdo, 'set_status', 'activity', $target_activity_id,
                'Statut → ' . ($_POST['status'] ?? '') . ' (admin)'); // trace le changement avec mention de l'auteur
        }
    }
    header('Location: /sharetime/public/?page=admin_activities'); // retourne à la page de gestion des activités
    exit; // stoppe l'exécution après la redirection
}

// ── MESSAGES CONTACT : MARQUER LU / SUPPRIMER ─────────────────────────────────
// Ce bloc est partagé entre la page admin_contact (admins) et le panel owner (onglet contact).
// Il est déclenché par la présence de $_POST['contact_action'], distinct des autres actions POST.
if (in_array($page, ['admin_contact', 'owner']) && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['contact_action'])) { // déclenche uniquement si contact_action est présent dans le POST
    require_admin();  // Accessible aux admins ET à l'owner (is_admin() couvre les deux rôles)
    csrf_check(); // vérifie le token CSRF

    $contact_msg_id  = intval($_POST['msg_id'] ?? 0);       // ID du message de contact concerné
    $origin_page     = $_POST['from'] ?? 'admin_contact';   // page d'origine, détermine l'URL de retour

    if ($contact_msg_id > 0) { // vérifie que l'ID du message est valide

        if ($_POST['contact_action'] === 'mark_read') { // action de marquage comme lu
            // Marque ce message comme lu (is_read = 1) sans le supprimer
            $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = :id")->execute(['id' => $contact_msg_id]); // met is_read à 1 en base

        } elseif ($_POST['contact_action'] === 'mark_unread') { // action de marquage comme non lu
            // Repasse ce message en non-lu pour le traiter plus tard
            $pdo->prepare("UPDATE contact_messages SET is_read = 0 WHERE id = :id")->execute(['id' => $contact_msg_id]); // remet is_read à 0

        } elseif ($_POST['contact_action'] === 'delete') { // action de suppression du message
            // Suppression définitive du message de contact (aucune corbeille)
            $pdo->prepare("DELETE FROM contact_messages WHERE id = :id")->execute(['id' => $contact_msg_id]); // supprime le message de la base

        } elseif ($_POST['contact_action'] === 'mark_all_read') { // action de marquage de tous les messages comme lus
            // Marque TOUS les messages comme lus en une seule requête (sans WHERE sur l'ID)
            $pdo->exec("UPDATE contact_messages SET is_read = 1"); // met à jour toute la table en une seule requête
        }

    } elseif ($_POST['contact_action'] === 'mark_all_read') { // cas où "tout marquer lu" est envoyé sans ID précis
        // Cas où l'action "tout marquer lu" est envoyée sans cibler un message précis
        $pdo->exec("UPDATE contact_messages SET is_read = 1"); // marque tous les messages comme lus
    }

    // Redirige vers la bonne page selon la provenance de la requête
    $redirect_after_contact = $origin_page === 'owner'
        ? '/sharetime/public/?page=owner&tab=contact'  // si la requête vient du panel owner
        : '/sharetime/public/?page=admin_contact';     // sinon, retour à la page admin_contact
    header('Location: ' . $redirect_after_contact); // effectue la redirection vers la bonne page
    exit; // stoppe l'exécution après la redirection
}
