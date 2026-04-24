<?php $me = (int)$_SESSION['user']['id']; ?>

<!-- En-tête admin -->
<div style="background:var(--navy);padding:28px 0;">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <p style="color:rgba(255,255,255,0.55);font-size:0.8rem;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;">Administration</p>
            <h1 style="color:white;margin:0;font-size:1.6rem;">Gestion des utilisateurs</h1>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <?= role_badge($_SESSION['user']['role']) ?>
            <span style="color:rgba(255,255,255,0.6);font-size:0.9rem;"><?= htmlspecialchars($_SESSION['user']['prenom'].' '.$_SESSION['user']['nom']) ?></span>
        </div>
    </div>
</div>

<main>
    <?php admin_nav('admin_users'); ?>

    <div class="container" style="padding-bottom:48px;">

        <?php if ($flash): ?>
        <div style="background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0;border-radius:10px;padding:12px 18px;margin-bottom:24px;font-weight:600;">
            <?= htmlspecialchars($flash) ?>
        </div>
        <?php endif; ?>

        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
            <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);">
                <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                    Tous les utilisateurs
                    <span style="margin-left:8px;background:#F3F4F6;color:#6B7280;font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= count($admin_users_list) ?></span>
                </h2>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                    <thead>
                        <tr style="background:#F9FAFB;border-bottom:1px solid var(--gray-200);">
                            <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Utilisateur</th>
                            <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Rôle</th>
                            <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Activités</th>
                            <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Inscriptions</th>
                            <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Membre depuis</th>
                            <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($admin_users_list as $u):
                        $uid      = (int)$u['idusers'];
                        $is_me    = $uid === $me;
                        $is_owner_row = $u['role'] === 'owner';
                        $banned   = !empty($u['is_banned']);
                        $joined   = (new DateTime($u['date_creation']))->format('d/m/Y');
                        $can_act  = !$is_me && !$is_owner_row;
                    ?>
                    <tr style="border-bottom:1px solid var(--gray-50);">
                        <!-- Utilisateur -->
                        <td style="padding:12px 16px;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.9rem;flex-shrink:0;">
                                    <?= strtoupper(mb_substr($u['prenom'],0,1)) ?>
                                </div>
                                <div>
                                    <p style="margin:0;font-weight:600;color:var(--gray-900);">
                                        <?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?>
                                        <?php if ($is_me): ?><span style="font-size:0.72rem;color:var(--gray-400);font-weight:400;">(vous)</span><?php endif; ?>
                                    </p>
                                    <p style="margin:0;color:var(--gray-500);font-size:0.78rem;"><?= htmlspecialchars($u['email']) ?></p>
                                    <?php if ($u['pseudo']): ?>
                                    <p style="margin:0;color:var(--gray-400);font-size:0.75rem;">@<?= htmlspecialchars($u['pseudo']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <!-- Rôle -->
                        <td style="padding:12px 16px;"><?= role_badge($u['role'], $banned) ?></td>

                        <!-- Activités -->
                        <td style="padding:12px 16px;text-align:center;color:var(--gray-700);font-weight:600;"><?= $u['nb_activities'] ?></td>

                        <!-- Inscriptions -->
                        <td style="padding:12px 16px;text-align:center;color:var(--gray-700);font-weight:600;"><?= $u['nb_registrations'] ?></td>

                        <!-- Date -->
                        <td style="padding:12px 16px;color:var(--gray-500);font-size:0.82rem;"><?= $joined ?></td>

                        <!-- Actions -->
                        <td style="padding:12px 16px;">
                            <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;">

                                <?php if ($can_act): ?>

                                    <!-- Suspendre / Réactiver (admin + owner) -->
                                    <form method="POST" action="/sharetime/public/?page=admin_users" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                        <input type="hidden" name="action" value="<?= $banned ? 'unban' : 'ban' ?>">
                                        <button type="submit"
                                            style="padding:5px 12px;border-radius:6px;border:1.5px solid <?= $banned ? '#059669' : '#DC2626' ?>;background:white;color:<?= $banned ? '#059669' : '#DC2626' ?>;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                            onclick="return confirm('<?= $banned ? 'Réactiver ce compte ?' : 'Suspendre ce compte ?' ?>')">
                                            <?= $banned ? '✓ Réactiver' : '⊘ Suspendre' ?>
                                        </button>
                                    </form>

                                    <?php if (is_owner()): ?>

                                        <!-- Changer le rôle (owner uniquement) -->
                                        <?php if (!$banned): ?>
                                        <form method="POST" action="/sharetime/public/?page=admin_users" style="display:inline;display:flex;gap:4px;">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="set_role">
                                            <select name="role" style="padding:5px 8px;border-radius:6px;border:1.5px solid var(--gray-200);font-size:0.78rem;color:var(--gray-700);">
                                                <option value="utilisateur" <?= $u['role']==='utilisateur'?'selected':'' ?>>Membre</option>
                                                <option value="admin"       <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
                                            </select>
                                            <button type="submit" style="padding:5px 10px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-700);font-size:0.78rem;font-weight:600;cursor:pointer;">OK</button>
                                        </form>
                                        <?php endif; ?>

                                        <!-- Supprimer (owner uniquement) -->
                                        <form method="POST" action="/sharetime/public/?page=admin_users" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit"
                                                style="padding:5px 10px;border-radius:6px;border:1.5px solid #DC2626;background:#DC2626;color:white;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                                onclick="return confirm('Supprimer définitivement ce compte et toutes ses données ?')">
                                                🗑 Suppr.
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                <?php else: ?>
                                    <span style="color:var(--gray-300);font-size:0.8rem;font-style:italic;">
                                        <?= $is_me ? 'Vous' : 'Protégé' ?>
                                    </span>
                                <?php endif; ?>

                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<style>
@media (max-width: 768px) {
    table { font-size: 0.8rem !important; }
    td, th { padding: 8px 10px !important; }
}
</style>
