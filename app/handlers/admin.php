<?php
/**
 * app/handlers/admin.php — Handlers des actions d'administration
 *
 * Inclus inconditionnellement par public/index.php avant le routing.
 * Chaque bloc vérifie $page et REQUEST_METHOD avant d'agir.
 *
 * Séparation des responsabilités :
 *   - handler "owner" : toutes les actions (ban, rôle, suppression, transfert, activités)
 *   - handler "admin_users" : uniquement ban/unban des membres (pas des autres admins)
 *   - handler "admin_activities" : modération des activités par les admins
 *
 * Toute action destructive ou de modération est tracée via log_admin_action().
 */

// ── OWNER : TOUTES LES ACTIONS ─────────────────────────────────────────────────
// Ce handler reçoit les formulaires POST soumis depuis le panel propriétaire.
// L'owner peut agir sur les utilisateurs (ban, rôle, suppression, transfert)
// et sur les activités (statut, suppression).
if ($page === 'owner' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_owner();  // Arrête l'exécution si l'utilisateur n'est pas owner
    csrf_check();

    $action     = $_POST['action'] ?? '';    // action demandée (ban, unban, set_role, delete, transfer_ownership…)
    $type       = $_POST['type']   ?? 'user'; // type de la cible : 'user' ou 'activity'
    $valid_tabs = ['dashboard', 'users', 'activities', 'admins', 'contact', 'contenu', 'signalements'];
    $tab        = in_array($_POST['tab'] ?? '', $valid_tabs) ? $_POST['tab'] : 'dashboard';  // onglet de retour
    $me         = (int)$_SESSION['user']['id'];  // ID de l'owner connecté (pour éviter l'auto-action)

    if ($type === 'user') {
        $target_id = intval($_POST['user_id'] ?? 0);

        // Refuse d'agir sur soi-même (l'owner ne peut pas se dégrader ou se supprimer)
        if ($target_id > 0 && $target_id !== $me) {
            $um          = new User($pdo);
            $target_user = $um->getById($target_id);  // récupéré pour les logs (nom de la cible)

            if ($action === 'ban') {
                $um->setBanned($target_id, true);
                log_admin_action($pdo, 'ban', 'user', $target_id,
                    'Suspension de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));

            } elseif ($action === 'unban') {
                $um->setBanned($target_id, false);
                log_admin_action($pdo, 'unban', 'user', $target_id,
                    'Réactivation de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));

            } elseif ($action === 'set_role') {
                // Whitelist sur le rôle : seuls 'utilisateur' et 'admin' sont acceptables ici
                // (l'owner ne peut pas créer un autre owner via ce formulaire — c'est pour ça que 'owner' est absent)
                $new_role = in_array($_POST['role'] ?? '', ['utilisateur', 'admin']) ? $_POST['role'] : null;
                if ($new_role) {
                    $um->setRole($target_id, $new_role);
                    log_admin_action($pdo, 'set_role', 'user', $target_id,
                        'Rôle → ' . $new_role . ' pour ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));
                }

            } elseif ($action === 'transfer_ownership') {
                // transferOwnership est une transaction atomique : les deux UPDATE (ancien → admin,
                // nouveau → owner) réussissent ensemble ou échouent ensemble.
                if ($um->transferOwnership($target_id, $me)) {
                    log_admin_action($pdo, 'transfer_ownership', 'user', $target_id,
                        'Transfert propriété à ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));

                    // Dégrade le rôle en session : l'owner actuel est maintenant admin
                    $_SESSION['user']['role'] = 'admin';
                    $_SESSION['flash'] = "Propriété transférée avec succès.";
                    header('Location: /sharetime/public/?page=owner&tab=admins');
                    exit;
                }
                $_SESSION['flash'] = "Transfert impossible.";
                header('Location: /sharetime/public/?page=owner&tab=admins');
                exit;

            } elseif ($action === 'delete') {
                // Log avant suppression pour conserver le nom dans les logs
                // (après User::delete(), la ligne n'existe plus en base)
                log_admin_action($pdo, 'delete_user', 'user', $target_id,
                    'Suppression de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));
                $um->delete($target_id);
            }
        }

    } elseif ($type === 'activity') {
        $activity_id = intval($_POST['activity_id'] ?? 0);
        if ($activity_id > 0) {
            $am = new Activity($pdo);
            if ($action === 'set_status') {
                $am->setStatus($activity_id, $_POST['status'] ?? '');
                log_admin_action($pdo, 'set_status', 'activity', $activity_id,
                    'Statut → ' . ($_POST['status'] ?? ''));
            } elseif ($action === 'delete') {
                log_admin_action($pdo, 'delete_activity', 'activity', $activity_id, 'Suppression activité');
                $am->delete($activity_id);
            }
        }

    } elseif ($type === 'report') {
        $report_id = intval($_POST['report_id'] ?? 0);
        $new_status = in_array($_POST['status'] ?? '', ['traite', 'rejete']) ? $_POST['status'] : null;
        if ($report_id && $new_status) {
            $pdo->prepare("UPDATE reports SET status = :s WHERE idreports = :id")
                ->execute(['s' => $new_status, 'id' => $report_id]);
            log_admin_action($pdo, 'update_report', 'user', $report_id,
                'Signalement #' . $report_id . ' → ' . $new_status);
        }
        $_SESSION['flash'] = "Signalement mis à jour.";
        header('Location: /sharetime/public/?page=owner&tab=signalements');
        exit;

    } elseif ($type === 'content') {
        // Gestion du contenu éditorial : FAQ, CGU, Mentions légales
        if ($action === 'add_faq') {
            $q = trim($_POST['question'] ?? '');
            $r = trim($_POST['reponse']  ?? '');
            if ($q && $r) {
                $pdo->prepare("INSERT INTO faq (question, reponse) VALUES (:q, :r)")
                    ->execute(['q' => $q, 'r' => $r]);
                log_admin_action($pdo, 'add_faq', 'user', $me, 'Ajout FAQ : ' . mb_substr($q, 0, 80));
            }
            $_SESSION['flash'] = "Question ajoutée.";

        } elseif ($action === 'edit_faq') {
            $fid = intval($_POST['faq_id'] ?? 0);
            $q   = trim($_POST['question'] ?? '');
            $r   = trim($_POST['reponse']  ?? '');
            if ($fid && $q && $r) {
                $pdo->prepare("UPDATE faq SET question = :q, reponse = :r WHERE idfaq = :id")
                    ->execute(['q' => $q, 'r' => $r, 'id' => $fid]);
                log_admin_action($pdo, 'edit_faq', 'user', $me, 'Modif FAQ #' . $fid);
            }
            $_SESSION['flash'] = "Question mise à jour.";

        } elseif ($action === 'delete_faq') {
            $fid = intval($_POST['faq_id'] ?? 0);
            if ($fid) {
                $pdo->prepare("DELETE FROM faq WHERE idfaq = :id")->execute(['id' => $fid]);
                log_admin_action($pdo, 'delete_faq', 'user', $me, 'Suppression FAQ #' . $fid);
            }
            $_SESSION['flash'] = "Question supprimée.";

        } elseif ($action === 'update_cgu') {
            $contenu = trim($_POST['contenu'] ?? '');
            $version = trim($_POST['version'] ?? '');
            if ($contenu) {
                // Vide la table et réinsère (une seule ligne active, jamais de doublon)
                $pdo->exec("DELETE FROM cgu");
                $pdo->prepare("INSERT INTO cgu (contenu, version) VALUES (:c, :v)")
                    ->execute(['c' => $contenu, 'v' => $version ?: null]);
                log_admin_action($pdo, 'update_cgu', 'user', $me, 'Mise à jour CGU');
            }
            $_SESSION['flash'] = "CGU mises à jour.";

        } elseif ($action === 'update_mentions') {
            $contenu = trim($_POST['contenu'] ?? '');
            if ($contenu) {
                $pdo->exec("DELETE FROM mentions");
                $pdo->prepare("INSERT INTO mentions (contenu) VALUES (:c)")->execute(['c' => $contenu]);
                log_admin_action($pdo, 'update_mentions', 'user', $me, 'Mise à jour mentions légales');
            }
            $_SESSION['flash'] = "Mentions légales mises à jour.";
        }

        header('Location: /sharetime/public/?page=owner&tab=contenu');
        exit;
    }

    $_SESSION['flash'] = "Action effectuée.";
    // Redirige vers l'onglet d'où provenait l'action pour ne pas perdre le contexte
    header('Location: /sharetime/public/?page=owner&tab=' . $tab);
    exit;
}

// ── ADMIN : GESTION UTILISATEURS ───────────────────────────────────────────────
// Version restreinte du handler owner : les admins ne peuvent que ban/unban,
// et uniquement sur les membres (pas sur les autres admins ni sur l'owner).
if ($page === 'admin_users' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    csrf_check();

    $action    = $_POST['action'] ?? '';
    $target_id = intval($_POST['user_id'] ?? 0);
    $me        = (int)$_SESSION['user']['id'];

    if ($target_id > 0 && $target_id !== $me) {
        $um          = new User($pdo);
        $target_user = $um->getById($target_id);

        if ($action === 'ban') {
            // Un admin ne peut pas suspendre un autre admin (seul l'owner peut le faire)
            if ($target_user && $target_user['role'] === 'admin') {
                $_SESSION['flash'] = "Vous n'avez pas le droit de suspendre un administrateur.";
                header('Location: /sharetime/public/?page=admin_users'); exit;
            }
            $um->setBanned($target_id, true);
            log_admin_action($pdo, 'ban', 'user', $target_id,
                'Suspension de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));

        } elseif ($action === 'unban') {
            // Même restriction pour la réactivation
            if ($target_user && $target_user['role'] === 'admin') {
                $_SESSION['flash'] = "Vous n'avez pas le droit de réactiver un administrateur.";
                header('Location: /sharetime/public/?page=admin_users'); exit;
            }
            $um->setBanned($target_id, false);
            log_admin_action($pdo, 'unban', 'user', $target_id,
                'Réactivation de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));
        }
    }
    $_SESSION['flash'] = "Action effectuée.";
    header('Location: /sharetime/public/?page=admin_users');
    exit;
}

// ── ADMIN : GESTION ACTIVITÉS ──────────────────────────────────────────────────
// Permet aux admins de modérer les activités (changer le statut ou supprimer).
if ($page === 'admin_activities' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    csrf_check();

    $action      = $_POST['action']      ?? '';
    $activity_id = intval($_POST['activity_id'] ?? 0);

    if ($activity_id > 0) {
        $am = new Activity($pdo);
        if ($action === 'delete') {
            log_admin_action($pdo, 'delete_activity', 'activity', $activity_id, 'Suppression activité (admin)');
            $am->delete($activity_id);  // supprime aussi registrations, comments, ratings associés
        } elseif ($action === 'set_status') {
            $am->setStatus($activity_id, $_POST['status'] ?? '');
            log_admin_action($pdo, 'set_status', 'activity', $activity_id,
                'Statut → ' . ($_POST['status'] ?? '') . ' (admin)');
        }
    }
    header('Location: /sharetime/public/?page=admin_activities');
    exit;
}

// ── MESSAGES CONTACT : MARQUER LU / SUPPRIMER ─────────────────────────────────
if (in_array($page, ['admin_contact', 'owner']) && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['contact_action'])) {
    require_admin();
    csrf_check();

    $msg_id = intval($_POST['msg_id'] ?? 0);
    $from   = $_POST['from'] ?? 'admin_contact';  // page de retour

    if ($msg_id > 0) {
        if ($_POST['contact_action'] === 'mark_read') {
            $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = :id")->execute(['id' => $msg_id]);
        } elseif ($_POST['contact_action'] === 'mark_unread') {
            $pdo->prepare("UPDATE contact_messages SET is_read = 0 WHERE id = :id")->execute(['id' => $msg_id]);
        } elseif ($_POST['contact_action'] === 'delete') {
            $pdo->prepare("DELETE FROM contact_messages WHERE id = :id")->execute(['id' => $msg_id]);
        } elseif ($_POST['contact_action'] === 'mark_all_read') {
            $pdo->exec("UPDATE contact_messages SET is_read = 1");
        }
    } elseif ($_POST['contact_action'] === 'mark_all_read') {
        $pdo->exec("UPDATE contact_messages SET is_read = 1");
    }

    $redirect = $from === 'owner'
        ? '/sharetime/public/?page=owner&tab=contact'
        : '/sharetime/public/?page=admin_contact';
    header('Location: ' . $redirect);
    exit;
}
