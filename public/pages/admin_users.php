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

        <?php if (is_owner()): ?>
        <div style="background:#FEF3E2;border:1.5px solid rgba(232,129,26,0.3);border-radius:12px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;gap:14px;">
            <span style="font-size:1.5rem;">👑</span>
            <div>
                <p style="margin:0;font-weight:700;color:var(--orange);font-size:0.9rem;">Vous êtes propriétaire</p>
                <p style="margin:2px 0 0;font-size:0.82rem;color:var(--gray-600);">
                    Vous pouvez nommer ou révoquer des administrateurs, et transférer la propriété du site à un autre membre.
                    Le transfert est <strong>irréversible</strong> sans intervention du nouveau propriétaire.
                </p>
            </div>
        </div>
        <?php endif; ?>

        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
            <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                    Tous les utilisateurs
                    <span style="margin-left:8px;background:#F3F4F6;color:#6B7280;font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= $admin_total_count ?></span>
                </h2>
                <?php if ($admin_total_pages > 1): ?>
                <span style="font-size:0.82rem;color:var(--gray-500);">Page <?= $admin_current_page ?> / <?= $admin_total_pages ?></span>
                <?php endif; ?>
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

                                    <!-- Suspendre / Réactiver : owner pour tout le monde, admin uniquement pour les membres -->
                                    <?php if (is_owner() || $u['role'] === 'utilisateur'): ?>
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
                                    <?php endif; ?>

                                    <?php if (is_owner()): ?>

                                        <!-- Changer le rôle admin/membre (owner uniquement) -->
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

                                        <!-- Transférer la propriété (owner uniquement) -->
                                        <form method="POST" action="/sharetime/public/?page=admin_users" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="transfer_ownership">
                                            <button type="submit"
                                                style="padding:5px 10px;border-radius:6px;border:1.5px solid var(--orange);background:white;color:var(--orange);font-size:0.78rem;font-weight:600;cursor:pointer;"
                                                onclick="return confirm('Transférer la propriété du site à <?= htmlspecialchars(addslashes($u['prenom'].' '.$u['nom'])) ?> ?\n\nVous deviendrez administrateur. Cette action est irréversible sans intervention du nouveau propriétaire.')">
                                                👑 Propriétaire
                                            </button>
                                        </form>

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

        <!-- Pagination -->
        <?php if ($admin_total_pages > 1): ?>
        <div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:28px;flex-wrap:wrap;">
            <?php if ($admin_current_page > 1): ?>
                <a href="/sharetime/public/?page=admin_users&p=<?= $admin_current_page - 1 ?>" class="btn btn-outline-navy btn-sm">← Précédent</a>
            <?php endif; ?>
            <?php for ($i = max(1, $admin_current_page - 2); $i <= min($admin_total_pages, $admin_current_page + 2); $i++): ?>
                <a href="/sharetime/public/?page=admin_users&p=<?= $i ?>"
                   style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;font-size:0.9rem;font-weight:600;text-decoration:none;
                          background:<?= $i === $admin_current_page ? 'var(--navy)' : 'var(--gray-100)' ?>;
                          color:<?= $i === $admin_current_page ? 'white' : 'var(--gray-600)' ?>;">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            <?php if ($admin_current_page < $admin_total_pages): ?>
                <a href="/sharetime/public/?page=admin_users&p=<?= $admin_current_page + 1 ?>" class="btn btn-outline-navy btn-sm">Suivant →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<style>
@media (max-width: 768px) {
    table { font-size: 0.8rem !important; }
    td, th { padding: 8px 10px !important; }
}
</style>
