<!-- En-tête admin -->
<div style="background:var(--navy);padding:28px 0;">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <p style="color:rgba(255,255,255,0.55);font-size:0.8rem;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;">Administration</p>
            <h1 style="color:white;margin:0;font-size:1.6rem;">Gestion des activités</h1>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <?= role_badge($_SESSION['user']['role']) ?>
            <span style="color:rgba(255,255,255,0.6);font-size:0.9rem;"><?= htmlspecialchars($_SESSION['user']['prenom'].' '.$_SESSION['user']['nom']) ?></span>
        </div>
    </div>
</div>

<main>
    <?php admin_nav('admin_activities'); ?>

    <div class="container" style="padding-bottom:48px;">

        <?php if ($flash): ?>
        <div style="background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0;border-radius:10px;padding:12px 18px;margin-bottom:24px;font-weight:600;">
            <?= htmlspecialchars($flash) ?>
        </div>
        <?php endif; ?>

        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
            <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                    Toutes les activités
                    <span style="margin-left:8px;background:#F3F4F6;color:#6B7280;font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= $admin_total_count ?></span>
                </h2>
                <?php if ($admin_total_pages > 1): ?>
                <span style="font-size:0.82rem;color:var(--gray-500);">Page <?= $admin_current_page ?> / <?= $admin_total_pages ?></span>
                <?php endif; ?>
            </div>

            <?php if (empty($admin_activities_list)): ?>
            <div style="padding:48px;text-align:center;color:var(--gray-400);">
                <p style="font-size:2rem;margin-bottom:8px;">🎯</p>
                <p>Aucune activité enregistrée.</p>
            </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                    <thead>
                        <tr style="background:#F9FAFB;border-bottom:1px solid var(--gray-200);">
                            <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Activité</th>
                            <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Créateur</th>
                            <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Participants</th>
                            <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Date</th>
                            <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Statut</th>
                            <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $statusColors = [
                        'active'   => ['#D1FAE5', '#065F46'],
                        'annulee'  => ['#FEE2E2', '#DC2626'],
                        'terminee' => ['#F3F4F6', '#6B7280'],
                    ];
                    foreach ($admin_activities_list as $a):
                        $start = new DateTime($a['start_time']);
                        [$sbg, $scol] = $statusColors[$a['status']] ?? ['#F3F4F6', '#6B7280'];
                        $visibility_badge = $a['visibility'] === 'privee'
                            ? '<span style="background:#EDE9FE;color:#7C3AED;font-size:0.7rem;padding:1px 7px;border-radius:99px;font-weight:600;margin-left:4px;">Privée</span>'
                            : '';
                    ?>
                    <tr style="border-bottom:1px solid var(--gray-50);">
                        <!-- Activité -->
                        <td style="padding:12px 16px;max-width:220px;">
                            <p style="margin:0;font-weight:600;color:var(--gray-900);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?= htmlspecialchars($a['title']) ?><?= $visibility_badge ?>
                            </p>
                            <p style="margin:0;color:var(--gray-500);font-size:0.78rem;"><?= htmlspecialchars($a['city']) ?></p>
                        </td>

                        <!-- Créateur -->
                        <td style="padding:12px 16px;">
                            <p style="margin:0;color:var(--gray-700);"><?= htmlspecialchars($a['prenom'].' '.$a['nom']) ?></p>
                        </td>

                        <!-- Participants -->
                        <td style="padding:12px 16px;text-align:center;">
                            <span style="font-weight:600;color:var(--gray-900);"><?= (int)$a['nb_inscrits'] ?></span>
                            <span style="color:var(--gray-400);">/</span>
                            <span style="color:var(--gray-500);"><?= (int)$a['max_participants'] ?></span>
                        </td>

                        <!-- Date -->
                        <td style="padding:12px 16px;color:var(--gray-600);font-size:0.82rem;white-space:nowrap;"><?= $start->format('d/m/Y') ?></td>

                        <!-- Statut actuel -->
                        <td style="padding:12px 16px;text-align:center;">
                            <span style="background:<?= $sbg ?>;color:<?= $scol ?>;padding:3px 10px;border-radius:99px;font-size:0.75rem;font-weight:600;white-space:nowrap;">
                                <?= ucfirst($a['status']) ?>
                            </span>
                        </td>

                        <!-- Actions -->
                        <td style="padding:12px 16px;">
                            <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;align-items:center;">

                                <!-- Changer le statut -->
                                <form method="POST" action="/sharetime/public/?page=admin_activities" style="display:flex;gap:4px;align-items:center;">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="activity_id" value="<?= (int)$a['idactivities'] ?>">
                                    <input type="hidden" name="action" value="set_status">
                                    <select name="status" style="padding:5px 8px;border-radius:6px;border:1.5px solid var(--gray-200);font-size:0.78rem;color:var(--gray-700);">
                                        <option value="active"   <?= $a['status']==='active'  ?'selected':'' ?>>Active</option>
                                        <option value="annulee"  <?= $a['status']==='annulee' ?'selected':'' ?>>Annulée</option>
                                        <option value="terminee" <?= $a['status']==='terminee'?'selected':'' ?>>Terminée</option>
                                    </select>
                                    <button type="submit" style="padding:5px 10px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-700);font-size:0.78rem;font-weight:600;cursor:pointer;">OK</button>
                                </form>

                                <!-- Voir le détail -->
                                <a href="/sharetime/public/?page=detail&id=<?= (int)$a['idactivities'] ?>"
                                   style="padding:5px 10px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-700);font-size:0.78rem;font-weight:600;text-decoration:none;"
                                   title="Voir l'activité">👁</a>

                                <!-- Supprimer -->
                                <form method="POST" action="/sharetime/public/?page=admin_activities" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="activity_id" value="<?= (int)$a['idactivities'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit"
                                        style="padding:5px 10px;border-radius:6px;border:1.5px solid #DC2626;background:#DC2626;color:white;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                        onclick="return confirm('Supprimer définitivement cette activité et toutes ses inscriptions ?')">
                                        🗑
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

        <!-- Pagination -->
        <?php if ($admin_total_pages > 1): ?>
        <div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:28px;flex-wrap:wrap;">
            <?php if ($admin_current_page > 1): ?>
                <a href="/sharetime/public/?page=admin_activities&p=<?= $admin_current_page - 1 ?>" class="btn btn-outline-navy btn-sm">← Précédent</a>
            <?php endif; ?>
            <?php for ($i = max(1, $admin_current_page - 2); $i <= min($admin_total_pages, $admin_current_page + 2); $i++): ?>
                <a href="/sharetime/public/?page=admin_activities&p=<?= $i ?>"
                   style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;font-size:0.9rem;font-weight:600;text-decoration:none;
                          background:<?= $i === $admin_current_page ? 'var(--navy)' : 'var(--gray-100)' ?>;
                          color:<?= $i === $admin_current_page ? 'white' : 'var(--gray-600)' ?>;">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            <?php if ($admin_current_page < $admin_total_pages): ?>
                <a href="/sharetime/public/?page=admin_activities&p=<?= $admin_current_page + 1 ?>" class="btn btn-outline-navy btn-sm">Suivant →</a>
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
