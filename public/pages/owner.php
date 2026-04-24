<?php
$admins  = array_filter($owner_users, fn($u) => $u['role'] === 'admin');
$members = array_filter($owner_users, fn($u) => $u['role'] === 'utilisateur' && empty($u['is_banned']));
?>

<!-- En-tête -->
<div style="background:linear-gradient(135deg,var(--orange) 0%,#c96a10 100%);padding:28px 0;">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <p style="color:rgba(255,255,255,0.65);font-size:0.8rem;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;">Propriétaire</p>
            <h1 style="color:white;margin:0;font-size:1.6rem;">Espace Propriétaire</h1>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <?= role_badge($_SESSION['user']['role']) ?>
            <span style="color:rgba(255,255,255,0.7);font-size:0.9rem;"><?= htmlspecialchars($_SESSION['user']['prenom'].' '.$_SESSION['user']['nom']) ?></span>
            <a href="/sharetime/public/?page=admin"
               style="padding:7px 16px;border-radius:8px;border:1.5px solid rgba(255,255,255,0.4);color:white;font-size:0.82rem;font-weight:600;text-decoration:none;">
                ⚙️ Panel admin
            </a>
        </div>
    </div>
</div>

<main>
    <!-- Nav -->
    <div style="background:white;border-bottom:2px solid var(--gray-200);margin-bottom:32px;">
        <div class="container" style="display:flex;gap:0;overflow-x:auto;">
            <span style="padding:14px 20px;font-weight:600;font-size:0.9rem;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;border-bottom:3px solid var(--orange);color:var(--navy);">
                👑 Gestion des rôles
            </span>
        </div>
    </div>

    <div class="container" style="padding-bottom:48px;max-width:900px;">

        <?php if ($flash): ?>
        <div style="background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0;border-radius:10px;padding:12px 18px;margin-bottom:24px;font-weight:600;">
            <?= htmlspecialchars($flash) ?>
        </div>
        <?php endif; ?>

        <!-- ── ADMINS ACTUELS ── -->
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;margin-bottom:24px;">
            <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;">
                <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                    Administrateurs actuels
                    <span style="margin-left:8px;background:#EBF0F8;color:var(--navy);font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= count($admins) ?></span>
                </h2>
            </div>

            <?php if (empty($admins)): ?>
            <p style="padding:24px 20px;color:var(--gray-500);margin:0;">Aucun administrateur pour le moment.</p>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                    <thead>
                        <tr style="background:#F9FAFB;border-bottom:1px solid var(--gray-200);">
                            <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Utilisateur</th>
                            <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Email</th>
                            <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Membre depuis</th>
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
                        <td style="padding:12px 16px;color:var(--gray-500);font-size:0.82rem;"><?= (new DateTime($u['date_creation']))->format('d/m/Y') ?></td>
                        <td style="padding:12px 16px;">
                            <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;">
                                <!-- Révoquer → membre -->
                                <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['idusers'] ?>">
                                    <input type="hidden" name="action" value="set_role">
                                    <input type="hidden" name="role" value="utilisateur">
                                    <button type="submit"
                                        style="padding:5px 12px;border-radius:6px;border:1.5px solid #DC2626;background:white;color:#DC2626;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                        onclick="return confirm('Révoquer le rôle admin de <?= htmlspecialchars(addslashes($u['prenom'].' '.$u['nom'])) ?> ?')">
                                        ⊘ Révoquer
                                    </button>
                                </form>
                                <!-- Transférer la propriété -->
                                <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
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
            </div>
            <?php endif; ?>
        </div>

        <!-- ── NOMMER UN ADMIN ── -->
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
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                    <thead>
                        <tr style="background:#F9FAFB;border-bottom:1px solid var(--gray-200);">
                            <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Membre</th>
                            <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Email</th>
                            <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Activités créées</th>
                            <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($members as $u): ?>
                    <tr style="border-bottom:1px solid var(--gray-50);">
                        <td style="padding:12px 16px;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:36px;height:36px;border-radius:50%;background:var(--gray-200);display:flex;align-items:center;justify-content:center;color:var(--gray-600);font-weight:700;font-size:0.9rem;flex-shrink:0;">
                                    <?= strtoupper(mb_substr($u['prenom'],0,1)) ?>
                                </div>
                                <div>
                                    <p style="margin:0;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></p>
                                    <?php if ($u['pseudo']): ?><p style="margin:0;color:var(--gray-400);font-size:0.75rem;">@<?= htmlspecialchars($u['pseudo']) ?></p><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td style="padding:12px 16px;color:var(--gray-500);font-size:0.85rem;"><?= htmlspecialchars($u['email']) ?></td>
                        <td style="padding:12px 16px;color:var(--gray-700);font-weight:600;"><?= (int)$u['nb_activities'] ?></td>
                        <td style="padding:12px 16px;">
                            <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
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
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<style>
@media (max-width: 768px) {
    table { font-size: 0.8rem !important; }
    td, th { padding: 8px 10px !important; }
}
</style>
