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
$valid_tabs = ['dashboard', 'users', 'activities', 'admins', 'contact', 'contenu', 'signalements'];
$tab = in_array($owner_tab ?? '', $valid_tabs) ? $owner_tab : 'dashboard';
$me  = (int)$_SESSION['user']['id'];  // ID de l'owner connecté (pour éviter auto-action)

// Définition des onglets : slug → [emoji, libellé]
$tab_def = [
    'dashboard'  => ['📊', 'Tableau de bord'],
    'users'      => ['👥', 'Utilisateurs'],
    'activities' => ['🎯', 'Activités'],
    'admins'     => ['👑', 'Administrateurs'],
    'contact'    => ['✉️', 'Messages contact'],
    'contenu'       => ['📝', 'Contenu du site'],
    'signalements'  => ['🚩', 'Signalements'],
];

// Données pour l'onglet signalements
$reports_list        = [];
$reports_pending_count = 0;
if ($tab === 'signalements') {
    $reports_list = $pdo->query("
        SELECT r.*,
               u1.prenom AS sg_prenom, u1.nom AS sg_nom, u1.pseudo AS sg_pseudo,
               u2.prenom AS sd_prenom, u2.nom AS sd_nom, u2.pseudo AS sd_pseudo
        FROM reports r
        JOIN users u1 ON u1.idusers = r.signaleur_id
        JOIN users u2 ON u2.idusers = r.signale_id
        ORDER BY FIELD(r.status,'en_attente','traite','rejete'), r.created_at DESC
    ")->fetchAll();
    $reports_pending_count = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'en_attente'")->fetchColumn();
}

// Données pour l'onglet contenu
$faq_items_owner   = [];
$cgu_owner         = '';
$cgu_version_owner = '';
$mentions_owner    = '';
if ($tab === 'contenu') {
    $faq_items_owner   = $pdo->query("SELECT * FROM faq ORDER BY idfaq ASC")->fetchAll();
    $cgu_row           = $pdo->query("SELECT contenu, version FROM cgu ORDER BY idcgu DESC LIMIT 1")->fetch();
    $cgu_owner         = $cgu_row['contenu'] ?? '';
    $cgu_version_owner = $cgu_row['version'] ?? '';
    $mentions_row      = $pdo->query("SELECT contenu FROM mentions ORDER BY idmentions DESC LIMIT 1")->fetch();
    $mentions_owner    = $mentions_row['contenu'] ?? '';
}
?>

<!-- ── EN-TÊTE OWNER ──────────────────────────────────────────────────────────
     Gradient orange (vs navy pour l'admin) pour signifier le niveau supérieur.
     Affiche le badge de rôle owner + prénom/nom de l'owner connecté. -->
<div style="background:linear-gradient(135deg,var(--orange) 0%,#c96a10 100%);padding:28px 0;">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <p style="color:rgba(255,255,255,0.65);font-size:0.8rem;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;">Super-Admin</p>
            <h1 style="color:white;margin:0;font-size:1.6rem;">Panel Super-Admin</h1>
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
                $statusColors = ['active'=>['#D1FAE5','#065F46'],'en_cours'=>['#FEF3C7','#92400E'],'annulee'=>['#FEE2E2','#DC2626'],'terminee'=>['#F3F4F6','#6B7280']];
                $statusLabels = ['active'=>'À venir','en_cours'=>'En cours','annulee'=>'Annulée','terminee'=>'Terminée'];
                [$sbg,$scol]  = $statusColors[$a['status']] ?? ['#F3F4F6','#6B7280'];
            ?>
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-50);display:flex;align-items:center;justify-content:space-between;gap:10px;">
                <div>
                    <p style="margin:0;font-size:0.88rem;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($a['title']) ?></p>
                    <p style="margin:0;font-size:0.78rem;color:var(--gray-500);"><?= htmlspecialchars($a['city']) ?> · <?= $start->format('d/m/Y') ?></p>
                </div>
                <span style="background:<?= $sbg ?>;color:<?= $scol ?>;padding:3px 10px;border-radius:99px;font-size:0.75rem;font-weight:600;white-space:nowrap;"><?= $statusLabels[$a['status']] ?? ucfirst($a['status']) ?></span>
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
                $statusColors = ['active'=>['#D1FAE5','#065F46'],'en_cours'=>['#FEF3C7','#92400E'],'annulee'=>['#FEE2E2','#DC2626'],'terminee'=>['#F3F4F6','#6B7280']];
                $statusLabels = ['active'=>'À venir','en_cours'=>'En cours','annulee'=>'Annulée','terminee'=>'Terminée'];
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
                        <span style="background:<?= $sbg ?>;color:<?= $scol ?>;padding:3px 10px;border-radius:99px;font-size:0.75rem;font-weight:600;"><?= $statusLabels[$a['status']] ?? ucfirst($a['status']) ?></span>
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
                                    <option value="active"   <?= $a['status']==='active'   ?'selected':'' ?>>À venir</option>
                                    <option value="en_cours" <?= $a['status']==='en_cours' ?'selected':'' ?>>En cours</option>
                                    <option value="annulee"  <?= $a['status']==='annulee'  ?'selected':'' ?>>Annulée</option>
                                    <option value="terminee" <?= $a['status']==='terminee' ?'selected':'' ?>>Terminée</option>
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
            <strong>Seul le super-admin</strong> peut nommer ou révoquer des administrateurs et transférer la propriété du site.
            Le transfert est <strong>irréversible</strong> sans intervention du nouveau super-admin.
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
                                onclick="return confirm('Transférer le rôle Super-Admin à <?= htmlspecialchars(addslashes($u['prenom'].' '.$u['nom'])) ?> ?\n\nVous deviendrez administrateur. Action irréversible.')">
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

<?php elseif ($tab === 'contact'): ?>

    <!-- ── ONGLET MESSAGES CONTACT ──────────────────────────────────────── -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
        <div>
            <h2 style="color:var(--navy); margin:0 0 4px;">Messages de contact</h2>
            <p style="color:var(--gray-500); font-size:0.9rem; margin:0;">
                <?= count($contact_messages ?? []) ?> message<?= count($contact_messages ?? []) > 1 ? 's' : '' ?>
                <?php if (($contact_unread ?? 0) > 0): ?>
                    — <span style="color:var(--orange); font-weight:600;"><?= $contact_unread ?> non lu<?= $contact_unread > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </p>
        </div>
        <?php if (($contact_unread ?? 0) > 0): ?>
        <form method="POST" action="/sharetime/public/?page=owner">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="contact_action" value="mark_all_read">
            <input type="hidden" name="msg_id" value="0">
            <input type="hidden" name="from" value="owner">
            <button type="submit" class="btn btn-outline-navy btn-sm">✓ Tout marquer comme lu</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (empty($contact_messages)): ?>
        <div style="text-align:center; padding:64px 0; color:var(--gray-400);">
            <div style="font-size:3rem; margin-bottom:16px;">📭</div>
            <p style="font-size:1rem; font-weight:600; color:var(--gray-500);">Aucun message reçu</p>
            <p style="font-size:0.85rem;">Les messages du formulaire de contact apparaîtront ici.</p>
        </div>
    <?php else: ?>
    <div style="display:flex; flex-direction:column; gap:12px;">
        <?php foreach ($contact_messages as $msg):
            $is_read = (bool)$msg['is_read'];
            $dt      = new DateTime($msg['sent_at']);
        ?>
        <div style="background:white; border:1.5px solid <?= $is_read ? 'var(--gray-200)' : 'var(--orange)' ?>;
                    border-radius:12px; padding:20px 24px;
                    <?= $is_read ? '' : 'box-shadow:0 2px 8px rgba(232,129,26,0.1);' ?>">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                <div style="flex:1; min-width:0;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; flex-wrap:wrap;">
                        <?php if (!$is_read): ?>
                            <span style="background:var(--orange);color:white;font-size:0.65rem;font-weight:700;padding:2px 8px;border-radius:99px;text-transform:uppercase;letter-spacing:0.5px;">Nouveau</span>
                        <?php endif; ?>
                        <strong style="color:var(--navy); font-size:0.95rem;"><?= htmlspecialchars($msg['name']) ?></strong>
                        <a href="mailto:<?= htmlspecialchars($msg['email']) ?>" style="color:var(--orange);font-size:0.85rem;text-decoration:none;"><?= htmlspecialchars($msg['email']) ?></a>
                        <span style="color:var(--gray-400);font-size:0.8rem;margin-left:auto;"><?= $dt->format('d/m/Y à H\hi') ?></span>
                    </div>
                    <?php if (!empty($msg['subject'])): ?>
                    <p style="font-weight:600;color:var(--gray-700);margin:0 0 8px;font-size:0.9rem;"><?= htmlspecialchars($msg['subject']) ?></p>
                    <?php endif; ?>
                    <p style="color:var(--gray-600);font-size:0.88rem;margin:0;line-height:1.6;white-space:pre-wrap;"><?= htmlspecialchars($msg['message']) ?></p>
                </div>
                <div style="display:flex; flex-direction:column; gap:6px; flex-shrink:0;">
                    <form method="POST" action="/sharetime/public/?page=owner">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="msg_id" value="<?= (int)$msg['id'] ?>">
                        <input type="hidden" name="from" value="owner">
                        <input type="hidden" name="contact_action" value="<?= $is_read ? 'mark_unread' : 'mark_read' ?>">
                        <button type="submit" style="width:100%;padding:6px 14px;font-size:0.78rem;font-weight:600;background:<?= $is_read ? 'var(--gray-100)' : 'var(--navy)' ?>;color:<?= $is_read ? 'var(--gray-600)' : 'white' ?>;border:1.5px solid <?= $is_read ? 'var(--gray-300)' : 'var(--navy)' ?>;border-radius:8px;cursor:pointer;white-space:nowrap;">
                            <?= $is_read ? '↩ Marquer non lu' : '✓ Marquer lu' ?>
                        </button>
                    </form>
                    <a href="mailto:<?= htmlspecialchars($msg['email']) ?>?subject=Re: <?= htmlspecialchars(urlencode($msg['subject'] ?: 'Votre message')) ?>"
                       style="display:block;text-align:center;padding:6px 14px;font-size:0.78rem;font-weight:600;background:var(--orange);color:white;border-radius:8px;text-decoration:none;white-space:nowrap;">
                        ✉ Répondre
                    </a>
                    <form method="POST" action="/sharetime/public/?page=owner" onsubmit="return confirm('Supprimer ce message ?')">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="msg_id" value="<?= (int)$msg['id'] ?>">
                        <input type="hidden" name="from" value="owner">
                        <input type="hidden" name="contact_action" value="delete">
                        <button type="submit" style="width:100%;padding:6px 14px;font-size:0.78rem;font-weight:600;background:white;color:#DC2626;border:1.5px solid #FECACA;border-radius:8px;cursor:pointer;white-space:nowrap;">
                            🗑 Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php elseif ($tab === 'contenu'): ?>

    <!-- ── ONGLET CONTENU DU SITE : FAQ, CGU, Mentions ──────────────────────── -->

    <!-- ── FAQ ──────────────────────────────────────────────────────────────── -->
    <div style="margin-bottom:36px;">
        <h2 style="color:var(--navy);margin-bottom:16px;font-size:1.1rem;">📋 Foire aux questions</h2>

        <!-- Liste des questions existantes -->
        <?php if (empty($faq_items_owner)): ?>
            <p style="color:var(--gray-500);margin-bottom:16px;">Aucune question pour le moment.</p>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">
            <?php foreach ($faq_items_owner as $fq): ?>
            <div style="background:white;border:1.5px solid var(--gray-200);border-radius:10px;padding:14px 16px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                    <div style="flex:1;">
                        <p style="font-weight:600;color:var(--gray-900);margin:0 0 4px;"><?= htmlspecialchars($fq['question']) ?></p>
                        <p style="color:var(--gray-500);font-size:0.88rem;margin:0;"><?= htmlspecialchars(mb_substr($fq['reponse'], 0, 120)) ?>...</p>
                    </div>
                    <div style="display:flex;gap:6px;flex-shrink:0;">
                        <!-- Bouton éditer : ouvre le formulaire pré-rempli via JS -->
                        <button type="button"
                            onclick="openEditFaq(<?= $fq['idfaq'] ?>, <?= htmlspecialchars(json_encode($fq['question'])) ?>, <?= htmlspecialchars(json_encode($fq['reponse'])) ?>)"
                            style="padding:5px 12px;border-radius:6px;border:1.5px solid var(--navy);background:white;color:var(--navy);font-size:0.78rem;font-weight:600;cursor:pointer;">
                            ✏️ Éditer
                        </button>
                        <form method="POST" action="/sharetime/public/?page=owner" onsubmit="return confirm('Supprimer cette question ?')">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="type" value="content">
                            <input type="hidden" name="action" value="delete_faq">
                            <input type="hidden" name="faq_id" value="<?= $fq['idfaq'] ?>">
                            <button type="submit" style="padding:5px 12px;border-radius:6px;border:1.5px solid #FECACA;background:white;color:#DC2626;font-size:0.78rem;font-weight:600;cursor:pointer;">
                                🗑
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire ajout/édition FAQ -->
        <div style="background:var(--gray-50);border:1.5px solid var(--gray-200);border-radius:12px;padding:20px;">
            <h3 id="faq-form-title" style="color:var(--navy);margin:0 0 14px;font-size:0.95rem;">+ Ajouter une question</h3>
            <form id="faq-form" method="POST" action="/sharetime/public/?page=owner" style="display:flex;flex-direction:column;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="type" value="content">
                <input type="hidden" id="faq-action" name="action" value="add_faq">
                <input type="hidden" id="faq-id" name="faq_id" value="">
                <div>
                    <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:6px;font-size:0.88rem;">Question *</label>
                    <input type="text" id="faq-q" name="question" required placeholder="Ex : Comment créer une activité ?"
                        style="width:100%;padding:10px 14px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:0.9rem;font-family:inherit;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:6px;font-size:0.88rem;">Réponse *</label>
                    <textarea id="faq-r" name="reponse" required rows="3" placeholder="Réponse complète..."
                        style="width:100%;padding:10px 14px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:0.9rem;font-family:inherit;resize:vertical;box-sizing:border-box;"></textarea>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn btn-navy">Enregistrer</button>
                    <button type="button" id="faq-cancel" onclick="resetFaqForm()" style="display:none;padding:10px 18px;border:1.5px solid var(--gray-300);border-radius:8px;background:white;font-size:0.9rem;cursor:pointer;">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── CGU ───────────────────────────────────────────────────────────────── -->
    <div style="margin-bottom:36px;">
        <h2 style="color:var(--navy);margin-bottom:16px;font-size:1.1rem;">📄 Conditions Générales d'Utilisation</h2>
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:12px;padding:20px;">
            <form method="POST" action="/sharetime/public/?page=owner" style="display:flex;flex-direction:column;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="type" value="content">
                <input type="hidden" name="action" value="update_cgu">
                <div>
                    <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:6px;font-size:0.88rem;">Version (ex : v1.1)</label>
                    <input type="text" name="version" value="<?= htmlspecialchars($cgu_version_owner) ?>" placeholder="v1.0"
                        style="width:200px;padding:10px 14px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:0.9rem;font-family:inherit;">
                </div>
                <div>
                    <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:6px;font-size:0.88rem;">Contenu *</label>
                    <p style="font-size:0.8rem;color:var(--gray-500);margin:0 0 8px;">Le texte sera affiché tel quel sur la page CGU. Utilisez des sauts de ligne pour structurer.</p>
                    <textarea name="contenu" required rows="12"
                        style="width:100%;padding:12px 14px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:0.88rem;font-family:inherit;resize:vertical;box-sizing:border-box;line-height:1.6;"><?= htmlspecialchars($cgu_owner) ?></textarea>
                </div>
                <button type="submit" class="btn btn-navy" style="width:fit-content;">Enregistrer les CGU</button>
            </form>
        </div>
    </div>

    <!-- ── MENTIONS LÉGALES ──────────────────────────────────────────────────── -->
    <div>
        <h2 style="color:var(--navy);margin-bottom:16px;font-size:1.1rem;">⚖️ Mentions légales</h2>
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:12px;padding:20px;">
            <form method="POST" action="/sharetime/public/?page=owner" style="display:flex;flex-direction:column;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="type" value="content">
                <input type="hidden" name="action" value="update_mentions">
                <div>
                    <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:6px;font-size:0.88rem;">Contenu *</label>
                    <p style="font-size:0.8rem;color:var(--gray-500);margin:0 0 8px;">Affiché sur la page Mentions légales. Sauts de ligne préservés.</p>
                    <textarea name="contenu" required rows="12"
                        style="width:100%;padding:12px 14px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:0.88rem;font-family:inherit;resize:vertical;box-sizing:border-box;line-height:1.6;"><?= htmlspecialchars($mentions_owner) ?></textarea>
                </div>
                <button type="submit" class="btn btn-navy" style="width:fit-content;">Enregistrer les mentions</button>
            </form>
        </div>
    </div>

<?php elseif ($tab === 'signalements'): ?>

    <!-- ── ONGLET SIGNALEMENTS ─────────────────────────────────────────────── -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="color:var(--navy);margin:0 0 4px;">Signalements utilisateurs</h2>
            <p style="color:var(--gray-500);font-size:0.9rem;margin:0;">
                <?= count($reports_list) ?> signalement<?= count($reports_list) > 1 ? 's' : '' ?>
                <?php if ($reports_pending_count > 0): ?>
                    — <span style="color:#DC2626;font-weight:600;"><?= $reports_pending_count ?> en attente</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (empty($reports_list)): ?>
        <div style="text-align:center;padding:60px 0;color:var(--gray-400);">
            <p style="font-size:2rem;margin-bottom:12px;">✅</p>
            <p>Aucun signalement pour le moment.</p>
        </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px;">
    <?php foreach ($reports_list as $r):
        $is_pending = $r['status'] === 'en_attente';
        $status_style = match($r['status']) {
            'en_attente' => 'background:#FEE2E2;color:#DC2626;',
            'traite'     => 'background:#D1FAE5;color:#065F46;',
            'rejete'     => 'background:#F3F4F6;color:var(--gray-500);',
            default      => ''
        };
        $status_label = match($r['status']) {
            'en_attente' => '⏳ En attente',
            'traite'     => '✓ Traité',
            'rejete'     => '✗ Rejeté',
            default      => $r['status']
        };
        $sg_name = htmlspecialchars(($r['sg_pseudo'] ?: $r['sg_prenom']) . ' ' . $r['sg_nom']);
        $sd_name = htmlspecialchars(($r['sd_pseudo'] ?: $r['sd_prenom']) . ' ' . $r['sd_nom']);
    ?>
    <div style="background:white;border:1.5px solid <?= $is_pending ? '#FECACA' : 'var(--gray-200)' ?>;border-radius:12px;padding:18px 20px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
                    <span style="font-size:0.72rem;font-weight:700;padding:3px 10px;border-radius:99px;<?= $status_style ?>"><?= $status_label ?></span>
                    <span style="color:var(--gray-400);font-size:0.8rem;"><?= date('d/m/Y à H:i', strtotime($r['created_at'])) ?></span>
                </div>
                <p style="margin:0 0 6px;font-size:0.9rem;color:var(--gray-700);">
                    <strong>Signaleur :</strong>
                    <a href="/sharetime/public/?page=profil&id=<?= $r['signaleur_id'] ?>" style="color:var(--navy);font-weight:600;"><?= $sg_name ?></a>
                </p>
                <p style="margin:0 0 8px;font-size:0.9rem;color:var(--gray-700);">
                    <strong>Signalé :</strong>
                    <a href="/sharetime/public/?page=profil&id=<?= $r['signale_id'] ?>" style="color:#DC2626;font-weight:600;"><?= $sd_name ?></a>
                </p>
                <p style="margin:0;font-size:0.88rem;color:var(--gray-600);background:var(--gray-50);padding:8px 12px;border-radius:8px;">
                    <?= htmlspecialchars($r['motif']) ?>
                </p>
            </div>
            <?php if ($is_pending): ?>
            <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
                <!-- Marquer traité -->
                <form method="POST" action="/sharetime/public/?page=owner">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="type" value="report">
                    <input type="hidden" name="action" value="update_report">
                    <input type="hidden" name="report_id" value="<?= $r['idreports'] ?>">
                    <input type="hidden" name="status" value="traite">
                    <input type="hidden" name="tab" value="signalements">
                    <button type="submit" style="width:100%;padding:6px 14px;border-radius:6px;border:1.5px solid #059669;background:white;color:#059669;font-size:0.78rem;font-weight:600;cursor:pointer;">
                        ✓ Traité
                    </button>
                </form>
                <!-- Rejeter -->
                <form method="POST" action="/sharetime/public/?page=owner">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="type" value="report">
                    <input type="hidden" name="action" value="update_report">
                    <input type="hidden" name="report_id" value="<?= $r['idreports'] ?>">
                    <input type="hidden" name="status" value="rejete">
                    <input type="hidden" name="tab" value="signalements">
                    <button type="submit" style="width:100%;padding:6px 14px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-500);font-size:0.78rem;font-weight:600;cursor:pointer;">
                        ✗ Rejeter
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

<script>
function openEditFaq(id, question, reponse) {
    document.getElementById('faq-form-title').textContent = '✏️ Modifier la question';
    document.getElementById('faq-action').value = 'edit_faq';
    document.getElementById('faq-id').value = id;
    document.getElementById('faq-q').value = question;
    document.getElementById('faq-r').value = reponse;
    document.getElementById('faq-cancel').style.display = 'block';
    document.getElementById('faq-form').scrollIntoView({behavior:'smooth', block:'center'});
}
function resetFaqForm() {
    document.getElementById('faq-form-title').textContent = '+ Ajouter une question';
    document.getElementById('faq-action').value = 'add_faq';
    document.getElementById('faq-id').value = '';
    document.getElementById('faq-q').value = '';
    document.getElementById('faq-r').value = '';
    document.getElementById('faq-cancel').style.display = 'none';
}
</script>

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
