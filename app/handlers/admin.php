<?php
// Handlers admin — inclus depuis public/index.php

// ── OWNER : TOUTES LES ACTIONS ─────────────────────────────────
if ($page === 'owner' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_owner();
    csrf_check();
    $action     = $_POST['action'] ?? '';
    $type       = $_POST['type']   ?? 'user';
    $valid_tabs = ['dashboard', 'users', 'activities', 'admins'];
    $tab        = in_array($_POST['tab'] ?? '', $valid_tabs) ? $_POST['tab'] : 'dashboard';
    $me         = (int)$_SESSION['user']['id'];

    if ($type === 'user') {
        $target_id = intval($_POST['user_id'] ?? 0);
        if ($target_id > 0 && $target_id !== $me) {
            $um = new User($pdo);
            $target_user = $um->getById($target_id);
            if ($action === 'ban') {
                $um->setBanned($target_id, true);
                log_admin_action($pdo, 'ban', 'user', $target_id,
                    'Suspension de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));
            } elseif ($action === 'unban') {
                $um->setBanned($target_id, false);
                log_admin_action($pdo, 'unban', 'user', $target_id,
                    'Réactivation de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));
            } elseif ($action === 'set_role') {
                $new_role = in_array($_POST['role'] ?? '', ['utilisateur', 'admin']) ? $_POST['role'] : null;
                if ($new_role) {
                    $um->setRole($target_id, $new_role);
                    log_admin_action($pdo, 'set_role', 'user', $target_id,
                        'Rôle → ' . $new_role . ' pour ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));
                }
            } elseif ($action === 'transfer_ownership') {
                if ($um->transferOwnership($target_id, $me)) {
                    log_admin_action($pdo, 'transfer_ownership', 'user', $target_id,
                        'Transfert propriété à ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));
                    $_SESSION['user']['role'] = 'admin';
                    $_SESSION['flash'] = "Propriété transférée avec succès.";
                    header('Location: /sharetime/public/?page=owner&tab=admins');
                    exit;
                }
                $_SESSION['flash'] = "Transfert impossible.";
                header('Location: /sharetime/public/?page=owner&tab=admins');
                exit;
            } elseif ($action === 'delete') {
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
    }
    $_SESSION['flash'] = "Action effectuée.";
    header('Location: /sharetime/public/?page=owner&tab=' . $tab);
    exit;
}

// ── ADMIN : GESTION UTILISATEURS ───────────────────────────────
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
            if ($target_user && $target_user['role'] === 'admin') {
                $_SESSION['flash'] = "Vous n'avez pas le droit de suspendre un administrateur.";
                header('Location: /sharetime/public/?page=admin_users'); exit;
            }
            $um->setBanned($target_id, true);
            log_admin_action($pdo, 'ban', 'user', $target_id,
                'Suspension de ' . ($target_user['pseudo'] ?? $target_user['prenom'] ?? ''));
        } elseif ($action === 'unban') {
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

// ── ADMIN : GESTION ACTIVITÉS ──────────────────────────────────
if ($page === 'admin_activities' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    csrf_check();
    $action      = $_POST['action'] ?? '';
    $activity_id = intval($_POST['activity_id'] ?? 0);

    if ($activity_id > 0) {
        $am = new Activity($pdo);
        if ($action === 'delete') {
            log_admin_action($pdo, 'delete_activity', 'activity', $activity_id, 'Suppression activité (admin)');
            $am->delete($activity_id);
        } elseif ($action === 'set_status') {
            $am->setStatus($activity_id, $_POST['status'] ?? '');
            log_admin_action($pdo, 'set_status', 'activity', $activity_id,
                'Statut → ' . ($_POST['status'] ?? '') . ' (admin)');
        }
    }
    header('Location: /sharetime/public/?page=admin_activities');
    exit;
}
