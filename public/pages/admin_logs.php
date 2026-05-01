<main class="container" style="padding:40px 0;">

    <?php admin_nav('admin_logs'); ?>

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:16px;">
        <div>
            <h1 style="color:var(--navy); margin-bottom:4px;">Logs d'administration</h1>
            <p style="color:var(--gray-500); font-size:0.9rem;"><?= $admin_total_count ?> action<?= $admin_total_count > 1 ? 's' : '' ?> enregistrée<?= $admin_total_count > 1 ? 's' : '' ?></p>
        </div>
    </div>

    <form method="get" action="/sharetime/public/" style="margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <input type="hidden" name="page" value="admin_logs">
        <input type="text" name="admin" value="<?= htmlspecialchars($log_admin_filter) ?>"
               placeholder="Pseudo ou nom de l'admin…"
               style="padding:9px 14px; border:1.5px solid var(--gray-300); border-radius:8px; font-size:0.88rem; font-family:inherit; min-width:180px;">
        <select name="action"
                style="padding:9px 14px; border:1.5px solid var(--gray-300); border-radius:8px; font-size:0.88rem; font-family:inherit; background:white;">
            <option value="">Toutes les actions</option>
            <?php foreach (['ban','unban','delete_user','delete_activity','set_role','set_status','transfer_ownership'] as $a): ?>
                <option value="<?= $a ?>" <?= $log_action_filter === $a ? 'selected' : '' ?>><?= $a ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-navy btn-sm">Filtrer</button>
        <?php if ($log_action_filter || $log_admin_filter): ?>
            <a href="/sharetime/public/?page=admin_logs" class="btn btn-outline-navy btn-sm">✕ Réinitialiser</a>
        <?php endif; ?>
    </form>

    <?php if (empty($admin_logs)): ?>
        <div style="text-align:center; padding:80px 0; color:var(--gray-400);">
            <p style="font-size:2.5rem; margin-bottom:12px;">📋</p>
            <p style="font-size:1rem; font-weight:600; color:var(--gray-600);">Aucune action enregistrée pour le moment.</p>
        </div>
    <?php else: ?>
        <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:0.88rem;">
                <thead>
                    <tr style="background:var(--gray-50); border-bottom:1.5px solid var(--gray-200);">
                        <th style="padding:12px 16px; text-align:left; font-weight:600; color:var(--gray-600); white-space:nowrap;">Date</th>
                        <th style="padding:12px 16px; text-align:left; font-weight:600; color:var(--gray-600);">Admin</th>
                        <th style="padding:12px 16px; text-align:left; font-weight:600; color:var(--gray-600);">Action</th>
                        <th style="padding:12px 16px; text-align:left; font-weight:600; color:var(--gray-600);">Cible</th>
                        <th style="padding:12px 16px; text-align:left; font-weight:600; color:var(--gray-600);">Détails</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admin_logs as $log):
                        $date = (new DateTime($log['created_at']))->format('d/m/Y H:i');
                        $action_colors = [
                            'ban'              => ['#FEE2E2', '#DC2626'],
                            'unban'            => ['#D1FAE5', '#065F46'],
                            'delete_user'      => ['#FEE2E2', '#DC2626'],
                            'delete_activity'  => ['#FEE2E2', '#DC2626'],
                            'set_role'         => ['#EBF0F8', '#1E3A6E'],
                            'set_status'       => ['#FEF3E2', '#92400E'],
                            'transfer_ownership' => ['#FEF3E2', '#E8811A'],
                        ];
                        [$bg, $color] = $action_colors[$log['action']] ?? ['#F3F4F6', '#6B7280'];
                    ?>
                    <tr style="border-bottom:1px solid var(--gray-100);" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background='white'">
                        <td style="padding:12px 16px; color:var(--gray-500); white-space:nowrap;"><?= $date ?></td>
                        <td style="padding:12px 16px; font-weight:600; color:var(--navy);">
                            <?= htmlspecialchars($log['admin_pseudo'] ?? $log['admin_prenom'] ?? '—') ?>
                        </td>
                        <td style="padding:12px 16px;">
                            <span style="background:<?= $bg ?>; color:<?= $color ?>; padding:3px 10px; border-radius:99px; font-size:0.78rem; font-weight:700; white-space:nowrap;">
                                <?= htmlspecialchars($log['action']) ?>
                            </span>
                        </td>
                        <td style="padding:12px 16px; color:var(--gray-600);">
                            <span style="font-size:0.78rem; background:var(--gray-100); padding:2px 8px; border-radius:4px; margin-right:6px; font-weight:600; color:var(--gray-500);">
                                <?= $log['target_type'] ?>
                            </span>
                            #<?= $log['target_id'] ?>
                        </td>
                        <td style="padding:12px 16px; color:var(--gray-600); max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            <?= htmlspecialchars($log['details']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($admin_total_pages > 1):
            $log_qs = http_build_query(array_filter([
                'page'   => 'admin_logs',
                'action' => $log_action_filter,
                'admin'  => $log_admin_filter,
            ]));
        ?>
        <div style="display:flex; justify-content:center; align-items:center; gap:8px; margin-top:32px; flex-wrap:wrap;">
            <?php if ($admin_current_page > 1): ?>
                <a href="/sharetime/public/?<?= $log_qs ?>&p=<?= $admin_current_page - 1 ?>" class="btn btn-outline-navy btn-sm">← Précédent</a>
            <?php endif; ?>
            <?php for ($i = max(1, $admin_current_page - 2); $i <= min($admin_total_pages, $admin_current_page + 2); $i++): ?>
                <a href="/sharetime/public/?<?= $log_qs ?>&p=<?= $i ?>"
                   style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;font-size:0.9rem;font-weight:600;text-decoration:none;
                          background:<?= $i === $admin_current_page ? 'var(--navy)' : 'var(--gray-100)' ?>;
                          color:<?= $i === $admin_current_page ? 'white' : 'var(--gray-600)' ?>;"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($admin_current_page < $admin_total_pages): ?>
                <a href="/sharetime/public/?<?= $log_qs ?>&p=<?= $admin_current_page + 1 ?>" class="btn btn-outline-navy btn-sm">Suivant →</a>
            <?php endif; ?>
        </div>
        <p style="text-align:center; color:var(--gray-400); font-size:0.82rem; margin-top:12px;">Page <?= $admin_current_page ?> / <?= $admin_total_pages ?></p>
        <?php endif; ?>
    <?php endif; ?>

</main>
