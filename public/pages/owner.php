<?php
/**
 * public/pages/owner.php — Panel propriétaire (4 onglets)
 *
 * Variables disponibles (préparées par index.php routing) :
 *   $owner_tab            : onglet actif — 'dashboard' | 'users' | 'activities' | 'admins'
 *   $owner_users          : liste complète de tous les utilisateurs (pour les onglets users/admins)
 *   $admin_stats          : statistiques globales (membres, admins, activités, inscriptions, suspendus)
 *   $admin_recent_users   : 5 derniers membres inscrits
 *   $admin_recent_activities: 5 dernières activités créées
 *   $admin_activities_list: liste complète des activités (pour l'onglet activities)
 *   $flash                : message de succès après action
 *
 * Accessible uniquement par l'owner (require_owner() dans index.php).
 * Les actions POST sont traitées par handlers/admin.php (page=owner).
 *
 * Différences avec le panel admin classique :
 *   - Onglet "Administrateurs" : nommer/révoquer des admins + transférer la propriété
 *   - Aucune pagination (toutes les données chargées d'un coup — panel propriétaire)
 *   - Navigation par onglets (tabs) au lieu de pages séparées
 */

// Validation de l'onglet actif : whitelist pour éviter les valeurs arbitraires
$valid_tabs = ['dashboard', 'users', 'activities', 'admins'];
$tab = in_array($owner_tab ?? '', $valid_tabs) ? $owner_tab : 'dashboard';
$me  = (int)$_SESSION['user']['id'];  // ID de l'owner connecté (pour éviter auto-action)

// Définition des onglets : slug → [emoji, libellé]
$tab_def = [
    'dashboard'  => ['📊', 'Tableau de bord'],
    'users'      => ['👥', 'Utilisateurs'],
    'activities' => ['🎯', 'Activités'],
    'admins'     => ['👑', 'Administrateurs'],
];
?>

<!-- ── EN-TÊTE OWNER ──────────────────────────────────────────────────────────
     Gradient orange (vs navy pour l'admin) pour signifier le niveau supérieur.
     Affiche le badge de rôle owner + prénom/nom de l'owner connecté. -->
<div style="background:linear-gradient(135deg,var(--orange) 0%,#c96a10 100%);padding:28px 0;">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <p style="color:rgba(255,255,255,0.65);font-size:0.8rem;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;">Propriétaire</p>
            <h1 style="color:white;margin:0;font-size:1.6rem;">Panel Propriétaire</h1>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <?= role_badge($_SESSION['user']['role']) ?>
            <span style="color:rgba(255,255,255,0.7);font-size:0.9rem;"><?= htmlspecialchars($_SESSION['user']['prenom'].' '.$_SESSION['user']['nom']) ?></span>
        </div>
    </div>
</div>

<!-- ── NAVIGATION PAR ONGLETS ────────────────────────────────────────────────
     Barre d'onglets en dessous du header. L'onglet actif a une bordure inférieure
     orange et une couleur navy, les autres sont gris. overflow-x:auto pour mobile. -->
<div style="background:white;border-bottom:2px solid var(--gray-200);margin-bottom:32px;">
    <div class="container" style="display:flex;gap:0;overflow-x:auto;">
        <?php foreach ($tab_def as $t => [$icon, $label]): ?>
        <a href="/sharetime/public/?page=owner&tab=<?= $t ?>"
           style="padding:14px 20px;font-weight:600;font-size:0.9rem;text-decoration:none;white-space:nowrap;
                  display:inline-flex;align-items:center;gap:6px;transition:all 0.15s;
                  border-bottom:3px solid <?= $tab === $t ? 'var(--orange)' : 'transparent' ?>;
                  color:<?= $tab === $t ? 'var(--navy)' : 'var(--gray-500)' ?>;">
            <?= $icon ?> <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<main>
<div class="container" style="padding-bottom:48px;">

<!-- Message de succès après une action (ban, set_role, transfer, delete…) -->
<?php if ($flash): ?>
<div style="background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0;border-radius:10px;padding:12px 18px;margin-bottom:24px;font-weight:600;">
    <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<?php /* ══════════════════════════════════════════════════════════════
   ONGLET DASHBOARD : statistiques globales + derniers membres/activités
   Même contenu que admin.php mais avec les liens qui pointent vers owner&tab=...
══════════════════════════════════════════════════════════════════ */ ?>
<?php if ($tab === 'dashboard'): ?>

    <!-- Cards de statistiques globales -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:36px;">
        <?php
        // Même structure que admin.php : [libellé, valeur, emoji, fond coloré, couleur texte]
        $cards = [
            ['Membres',      $admin_stats['membres'],      '👤', '#EBF0F8', 'var(--navy)'],
            ['Admins',       $admin_stats['admins'],       '🛡️', '#FEF3E2', 'var(--orange)'],
            ['Activités',    $admin_stats['activites'],    '🎯', '#D1FAE5', '#065F46'],
            ['Inscriptions', $admin_stats['inscriptions'], '✅', '#EDE9FE', '#7C3AED'],
            ['Suspendus',    $admin_stats['suspendus'],    '🚫', '#FEE2E2', '#DC2626'],
        ];
        foreach ($cards as [$label, $val, $icon, $bg, $color]):
        ?>
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;padding:20px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <span style="font-size:0.75rem;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;"><?= $label ?></span>
                <span style="font-size:1.3rem;background:<?= $bg ?>;padding:6px;border-radius:8px;"><?= $icon ?></span>
            </div>
            <p style="font-size:2rem;font-weight:800;color:<?= $color ?>;margin:0;"><?= $val ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Deux colonnes : derniers membres + dernières activités -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
        <!-- Derniers membres inscrits -->
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
            <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;">
                <h2 style="margin:0;font-size:1rem;color:var(--navy);">Derniers membres</h2>
                <!-- Lien "Tout voir" vers l'onglet users du même panel -->
                <a href="/sharetime/public/?page=owner&tab=users" style="font-size:0.82rem;color:var(--orange);font-weight:600;text-decoration:none;">Tout voir →</a>
            </div>
            <?php foreach ($admin_recent_users as $u): ?>
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-50);display:flex;align-items:center;justify-content:space-between;gap:10px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.9rem;flex-shrink:0;">
                        <?= strtoupper(mb_substr($u['prenom'],0,1)) ?>
                    </div>
                    <div>
                        <p style="margin:0;font-size:0.88rem;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></p>
                        <p style="margin:0;font-size:0.78rem;color:var(--gray-500);"><?= htmlspecialchars($u['email']) ?></p>
                    </div>
                </div>
                <?= role_badge($u['role'], !empty($u['is_banned'])) ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Dernières activités créées -->
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
            <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;">
                <h2 style="margin:0;font-size:1rem;color:var(--navy);">Dernières activités</h2>
                <a href="/sharetime/public/?page=owner&tab=activities" style="font-size:0.82rem;color:var(--orange);font-weight:600;text-decoration:none;">Tout voir →</a>
            </div>
            <?php foreach ($admin_recent_activities as $a):
                $start = new DateTime($a['start_time']);
                $statusColors = ['active'=>['#D1FAE5','#065F46'],'annulee'=>['#FEE2E2','#DC2626'],'terminee'=>['#F3F4F6','#6B7280']];
                [$sbg,$scol] = $statusColors[$a['status']] ?? ['#F3F4F6','#6B7280'];
            ?>
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-50);display:flex;align-items:center;justify-content:space-between;gap:10px;">
                <div>
                    <p style="margin:0;font-size:0.88rem;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($a['title']) ?></p>
                    <p style="margin:0;font-size:0.78rem;color:var(--gray-500);"><?= htmlspecialchars($a['city']) ?> · <?= $start->format('d/m/Y') ?></p>
                </div>
                <span style="background:<?= $sbg ?>;color:<?= $scol ?>;padding:3px 10px;border-radius:99px;font-size:0.75rem;font-weight:600;white-space:nowrap;"><?= ucfirst($a['status']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php /* ══════════════════════════════════════════════════════════════
   ONGLET USERS : tableau complet de tous les utilisateurs
   L'owner peut : ban/unban, set_role, supprimer, transférer la propriété.
   Toutes les actions POST vers page=owner avec type=user + tab=users.
══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($tab === 'users'): ?>

    <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
        <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);">
            <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                Tous les utilisateurs
                <span style="margin-left:8px;background:#F3F4F6;color:#6B7280;font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= count($owner_users) ?></span>
            </h2>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                <thead>
                    <tr style="background:#F9FAFB;border-bottom:1px solid var(--gray-200);">
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Utilisateur</th>
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Rôle</th>
                        <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Activités</th>
                        <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Inscriptions</th>
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Depuis</th>
                        <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($owner_users as $u):
                    $uid          = (int)$u['idusers'];
                    $is_me        = $uid === $me;           // ligne de l'owner lui-même
                    $is_owner_row = $u['role'] === 'owner'; // il n'y a qu'un seul owner = $me ici
                    $banned       = !empty($u['is_banned']);
                    // can_act = false sur sa propre ligne et sur la ligne owner (se protéger soi-même)
                    $can_act      = !$is_me && !$is_owner_row;
                ?>
                <tr style="border-bottom:1px solid var(--gray-50);">
                    <!-- Utilisateur : avatar initiale + nom + email -->
                    <td style="padding:12px 16px;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.9rem;flex-shrink:0;">
                                <?= strtoupper(mb_substr($u['prenom'],0,1)) ?>
                            </div>
                            <div>
                                <p style="margin:0;font-weight:600;color:var(--gray-900);">
                                    <?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?>
                                    <?php if ($is_me): ?><span style="font-size:0.72rem;color:var(--gray-400);"> (vous)</span><?php endif; ?>
                                </p>
                                <p style="margin:0;color:var(--gray-500);font-size:0.78rem;"><?= htmlspecialchars($u['email']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td style="padding:12px 16px;"><?= role_badge($u['role'], $banned) ?></td>
                    <td style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-700);"><?= $u['nb_activities'] ?></td>
                    <td style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-700);"><?= $u['nb_registrations'] ?></td>
                    <td style="padding:12px 16px;color:var(--gray-500);font-size:0.82rem;"><?= (new DateTime($u['date_creation']))->format('d/m/Y') ?></td>
                    <td style="padding:12px 16px;">
                        <?php if ($can_act): ?>
                        <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;">
                            <!-- Bouton Suspendre / Réactiver : type=user + tab=users pour retour correct -->
                            <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="type" value="user">
                                <input type="hidden" name="tab" value="users">
                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                <input type="hidden" name="action" value="<?= $banned ? 'unban' : 'ban' ?>">
                                <button type="submit"
                                    style="padding:5px 12px;border-radius:6px;border:1.5px solid <?= $banned ? '#059669' : '#DC2626' ?>;background:white;color:<?= $banned ? '#059669' : '#DC2626' ?>;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                    onclick="return confirm('<?= $banned ? 'Réactiver ce compte ?' : 'Suspendre ce compte ?' ?>')">
                                    <?= $banned ? '✓ Réactiver' : '⊘ Suspendre' ?>
                                </button>
                            </form>
                            <!-- Changer le rôle : masqué si banni -->
                            <?php if (!$banned): ?>
                            <form method="POST" action="/sharetime/public/?page=owner" style="display:flex;gap:4px;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="type" value="user">
                                <input type="hidden" name="tab" value="users">
                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                <input type="hidden" name="action" value="set_role">
                                <select name="role" style="padding:5px 8px;border-radius:6px;border:1.5px solid var(--gray-200);font-size:0.78rem;color:var(--gray-700);">
                                    <option value="utilisateur" <?= $u['role']==='utilisateur'?'selected':'' ?>>Membre</option>
                                    <option value="admin"       <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
                                </select>
                                <button type="submit" style="padding:5px 10px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-700);font-size:0.78rem;font-weight:600;cursor:pointer;">OK</button>
                            </form>
                            <?php endif; ?>
                            <!-- Suppression définitive -->
                            <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="type" value="user">
                                <input type="hidden" name="tab" value="users">
                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit"
                                    style="padding:5px 10px;border-radius:6px;border:1.5px solid #DC2626;background:#DC2626;color:white;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                    onclick="return confirm('Supprimer définitivement ce compte ?')">
                                    🗑
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                        <!-- Aucune action sur sa propre ligne -->
                        <span style="color:var(--gray-300);font-size:0.8rem;font-style:italic;"><?= $is_me ? 'Vous' : 'Protégé' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php /* ══════════════════════════════════════════════════════════════
   ONGLET ACTIVITIES : tableau complet de toutes les activités
   L'owner peut changer le statut et supprimer. Même structure que admin_activities.php.
   Les actions POST vers page=owner avec type=activity + tab=activities.
══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($tab === 'activities'): ?>

    <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
        <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);">
            <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                Toutes les activités
                <span style="margin-left:8px;background:#F3F4F6;color:#6B7280;font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= count($admin_activities_list) ?></span>
            </h2>
        </div>
        <?php if (empty($admin_activities_list)): ?>
        <div style="padding:48px;text-align:center;color:var(--gray-400);">
            <p style="font-size:2rem;margin-bottom:8px;">🎯</p><p>Aucune activité.</p>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                <thead>
                    <tr style="background:#F9FAFB;border-bottom:1px solid var(--gray-200);">
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Activité</th>
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Créateur</th>
                        <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Participants</th>
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Date</th>
                        <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Statut</th>
                        <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $statusColors = ['active'=>['#D1FAE5','#065F46'],'annulee'=>['#FEE2E2','#DC2626'],'terminee'=>['#F3F4F6','#6B7280']];
                foreach ($admin_activities_list as $a):
                    $start = new DateTime($a['start_time']);
                    [$sbg, $scol] = $statusColors[$a['status']] ?? ['#F3F4F6','#6B7280'];
                ?>
                <tr style="border-bottom:1px solid var(--gray-50);">
                    <td style="padding:12px 16px;max-width:200px;">
                        <p style="margin:0;font-weight:600;color:var(--gray-900);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($a['title']) ?></p>
                        <p style="margin:0;color:var(--gray-500);font-size:0.78rem;"><?= htmlspecialchars($a['city']) ?></p>
                    </td>
                    <td style="padding:12px 16px;color:var(--gray-700);"><?= htmlspecialchars($a['prenom'].' '.$a['nom']) ?></td>
                    <td style="padding:12px 16px;text-align:center;">
                        <span style="font-weight:600;"><?= (int)$a['nb_inscrits'] ?></span><span style="color:var(--gray-400);">/</span><span style="color:var(--gray-500);"><?= (int)$a['max_participants'] ?></span>
                    </td>
                    <td style="padding:12px 16px;color:var(--gray-600);font-size:0.82rem;white-space:nowrap;"><?= $start->format('d/m/Y') ?></td>
                    <td style="padding:12px 16px;text-align:center;">
                        <span style="background:<?= $sbg ?>;color:<?= $scol ?>;padding:3px 10px;border-radius:99px;font-size:0.75rem;font-weight:600;"><?= ucfirst($a['status']) ?></span>
                    </td>
                    <td style="padding:12px 16px;">
                        <div style="display:flex;gap:6px;justify-content:flex-end;align-items:center;flex-wrap:wrap;">
                            <!-- Changer le statut : type=activity + tab=activities -->
                            <form method="POST" action="/sharetime/public/?page=owner" style="display:flex;gap:4px;align-items:center;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="type" value="activity">
                                <input type="hidden" name="tab" value="activities">
                                <input type="hidden" name="activity_id" value="<?= (int)$a['idactivities'] ?>">
                                <input type="hidden" name="action" value="set_status">
                                <select name="status" style="padding:5px 8px;border-radius:6px;border:1.5px solid var(--gray-200);font-size:0.78rem;color:var(--gray-700);">
                                    <option value="active"   <?= $a['status']==='active'  ?'selected':'' ?>>Active</option>
                                    <option value="annulee"  <?= $a['status']==='annulee' ?'selected':'' ?>>Annulée</option>
                                    <option value="terminee" <?= $a['status']==='terminee'?'selected':'' ?>>Terminée</option>
                                </select>
                                <button type="submit" style="padding:5px 10px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-700);font-size:0.78rem;font-weight:600;cursor:pointer;">OK</button>
                            </form>
                            <!-- Lien vers la page de détail publique de l'activité -->
                            <a href="/sharetime/public/?page=detail&id=<?= (int)$a['idactivities'] ?>"
                               style="padding:5px 10px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-700);font-size:0.78rem;font-weight:600;text-decoration:none;">👁</a>
                            <!-- Suppression définitive de l'activité -->
                            <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="type" value="activity">
                                <input type="hidden" name="tab" value="activities">
                                <input type="hidden" name="activity_id" value="<?= (int)$a['idactivities'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit"
                                    style="padding:5px 10px;border-radius:6px;border:1.5px solid #DC2626;background:#DC2626;color:white;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                    onclick="return confirm('Supprimer cette activité et ses inscriptions ?')">🗑</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

<?php /* ══════════════════════════════════════════════════════════════
   ONGLET ADMINS : gestion des administrateurs
   Deux sections :
   1. Administrateurs actuels → révoquer ou transférer la propriété
   2. Nommer un administrateur → liste des membres actifs non bannis
══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($tab === 'admins'): ?>

    <?php
    // Sépare les admins des membres pour deux tableaux distincts
    $admins  = array_values(array_filter($owner_users, fn($u) => $u['role'] === 'admin'));
    $members = array_values(array_filter($owner_users, fn($u) => $u['role'] === 'utilisateur' && empty($u['is_banned'])));
    ?>

    <!-- Rappel des règles (transfert irréversible) -->
    <div style="background:#FEF3E2;border:1.5px solid rgba(232,129,26,0.3);border-radius:12px;padding:16px 20px;margin-bottom:28px;display:flex;align-items:center;gap:14px;">
        <span style="font-size:1.5rem;">👑</span>
        <p style="margin:0;font-size:0.88rem;color:var(--gray-700);">
            <strong>Seul le propriétaire</strong> peut nommer ou révoquer des administrateurs et transférer la propriété du site.
            Le transfert est <strong>irréversible</strong> sans intervention du nouveau propriétaire.
        </p>
    </div>

    <!-- ── Section 1 : Administrateurs actuels ────────────────────────── -->
    <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;margin-bottom:24px;">
        <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);">
            <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                Administrateurs actuels
                <span style="margin-left:8px;background:#EBF0F8;color:var(--navy);font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= count($admins) ?></span>
            </h2>
        </div>
        <?php if (empty($admins)): ?>
        <p style="padding:24px 20px;color:var(--gray-500);margin:0;">Aucun administrateur pour le moment.</p>
        <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
            <thead>
                <tr style="background:#F9FAFB;border-bottom:1px solid var(--gray-200);">
                    <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Administrateur</th>
                    <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Email</th>
                    <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($admins as $u): ?>
            <tr style="border-bottom:1px solid var(--gray-50);">
                <td style="padding:12px 16px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.9rem;flex-shrink:0;">
                            <?= strtoupper(mb_substr($u['prenom'],0,1)) ?>
                        </div>
                        <p style="margin:0;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></p>
                    </div>
                </td>
                <td style="padding:12px 16px;color:var(--gray-500);font-size:0.85rem;"><?= htmlspecialchars($u['email']) ?></td>
                <td style="padding:12px 16px;">
                    <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;">
                        <!-- Révoquer le rôle admin → repasse en membre (utilisateur) -->
                        <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="type" value="user">
                            <input type="hidden" name="tab" value="admins">
                            <input type="hidden" name="user_id" value="<?= (int)$u['idusers'] ?>">
                            <input type="hidden" name="action" value="set_role">
                            <input type="hidden" name="role" value="utilisateur">
                            <button type="submit"
                                style="padding:5px 12px;border-radius:6px;border:1.5px solid #DC2626;background:white;color:#DC2626;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                onclick="return confirm('Révoquer le rôle admin de <?= htmlspecialchars(addslashes($u['prenom'].' '.$u['nom'])) ?> ?')">
                                ⊘ Révoquer
                            </button>
                        </form>
                        <!-- Transférer la propriété : irréversible, double confirmation JS -->
                        <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="type" value="user">
                            <input type="hidden" name="tab" value="admins">
                            <input type="hidden" name="user_id" value="<?= (int)$u['idusers'] ?>">
                            <input type="hidden" name="action" value="transfer_ownership">
                            <button type="submit"
                                style="padding:5px 12px;border-radius:6px;border:1.5px solid var(--orange);background:white;color:var(--orange);font-size:0.78rem;font-weight:600;cursor:pointer;"
                                onclick="return confirm('Transférer la propriété à <?= htmlspecialchars(addslashes($u['prenom'].' '.$u['nom'])) ?> ?\n\nVous deviendrez administrateur. Action irréversible.')">
                                👑 Transférer
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ── Section 2 : Nommer un administrateur ──────────────────────── -->
    <!-- Liste filtrée : membres actifs (role='utilisateur') non bannis -->
    <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
        <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);">
            <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                Nommer un administrateur
                <span style="margin-left:8px;background:#F3F4F6;color:#6B7280;font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= count($members) ?> membres</span>
            </h2>
        </div>
        <?php if (empty($members)): ?>
        <p style="padding:24px 20px;color:var(--gray-500);margin:0;">Aucun membre disponible.</p>
        <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
            <thead>
                <tr style="background:#F9FAFB;border-bottom:1px solid var(--gray-200);">
                    <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Membre</th>
                    <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Email</th>
                    <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Activités</th>
                    <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($members as $u): ?>
            <tr style="border-bottom:1px solid var(--gray-50);">
                <td style="padding:12px 16px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <!-- Avatar gris (vs navy pour les admins déjà nommés) -->
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--gray-200);display:flex;align-items:center;justify-content:center;color:var(--gray-600);font-weight:700;font-size:0.9rem;flex-shrink:0;">
                            <?= strtoupper(mb_substr($u['prenom'],0,1)) ?>
                        </div>
                        <p style="margin:0;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></p>
                    </div>
                </td>
                <td style="padding:12px 16px;color:var(--gray-500);font-size:0.85rem;"><?= htmlspecialchars($u['email']) ?></td>
                <td style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-700);"><?= (int)$u['nb_activities'] ?></td>
                <td style="padding:12px 16px;text-align:right;">
                    <!-- Nommer admin : set_role avec role=admin + tab=admins pour retour correct -->
                    <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="type" value="user">
                        <input type="hidden" name="tab" value="admins">
                        <input type="hidden" name="user_id" value="<?= (int)$u['idusers'] ?>">
                        <input type="hidden" name="action" value="set_role">
                        <input type="hidden" name="role" value="admin">
                        <button type="submit"
                            style="padding:5px 12px;border-radius:6px;border:1.5px solid #059669;background:white;color:#059669;font-size:0.78rem;font-weight:600;cursor:pointer;"
                            onclick="return confirm('Nommer <?= htmlspecialchars(addslashes($u['prenom'].' '.$u['nom'])) ?> administrateur ?')">
                            ✓ Nommer admin
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

<?php endif; ?>

</div>
</main>

<!-- Responsive : grilles 2 colonnes → 1 colonne, tables réduites sur mobile -->
<style>
@media (max-width: 768px) {
    div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
    table { font-size: 0.8rem !important; }
    td, th { padding: 8px 10px !important; }
}
</style>
