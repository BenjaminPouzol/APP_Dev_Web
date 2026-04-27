<main class="container" style="padding:40px 0; max-width:700px; margin:auto;">

    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:28px;">
        <div>
            <h1 style="color:var(--navy); margin-bottom:4px;">Notifications</h1>
            <?php $unread = count(array_filter($notifications, fn($n) => !$n['is_read'])); ?>
            <?php if ($unread > 0): ?>
                <p style="color:var(--orange); font-size:0.88rem; font-weight:600;"><?= $unread ?> non lue<?= $unread > 1 ? 's' : '' ?></p>
            <?php else: ?>
                <p style="color:var(--gray-400); font-size:0.88rem;">Tout est lu</p>
            <?php endif; ?>
        </div>
        <?php if ($unread > 0): ?>
        <form method="post" action="/sharetime/public/?page=notifs_lues" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <button type="submit" class="btn btn-outline-navy btn-sm">✓ Tout marquer comme lu</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <div style="text-align:center; padding:80px 0; color:var(--gray-400);">
            <p style="font-size:2.5rem; margin-bottom:12px;">🔔</p>
            <p style="font-size:1rem; font-weight:600; color:var(--gray-600);">Aucune notification pour le moment.</p>
        </div>
    <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:10px;">
        <?php foreach ($notifications as $n):
            $icons = [
                'nouvelle_inscription' => '👤',
                'promotion_attente'    => '🎉',
                'activite_modifiee'    => '✏️',
                'activite_annulee'     => '❌',
                'nouveau_follower'     => '⭐',
            ];
            $icon    = $icons[$n['type']] ?? '🔔';
            $is_read = !empty($n['is_read']);
            $date    = (new DateTime($n['created_at']))->format('d/m/Y à H:i');
        ?>
        <div style="background:<?= $is_read ? 'white' : '#FFF8F0' ?>; border:1.5px solid <?= $is_read ? 'var(--gray-200)' : 'rgba(232,129,26,0.3)' ?>;
                    border-radius:12px; padding:16px 20px; display:flex; gap:14px; align-items:flex-start;">
            <span style="font-size:1.4rem; flex-shrink:0; line-height:1.2;"><?= $icon ?></span>
            <div style="flex:1; min-width:0;">
                <p style="margin:0 0 4px; font-weight:<?= $is_read ? '400' : '600' ?>; color:var(--gray-900); font-size:0.92rem;">
                    <?= htmlspecialchars($n['title']) ?>
                </p>
                <p style="margin:0 0 6px; color:var(--gray-600); font-size:0.85rem; line-height:1.5;">
                    <?= htmlspecialchars($n['content']) ?>
                </p>
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <span style="font-size:0.78rem; color:var(--gray-400);"><?= $date ?></span>
                    <?php if ($n['activity_id']): ?>
                    <a href="/sharetime/public/?page=detail&id=<?= (int)$n['activity_id'] ?>"
                       style="font-size:0.8rem; color:var(--orange); font-weight:600; text-decoration:none;">
                        Voir l'activité →
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!$is_read): ?>
            <div style="width:8px; height:8px; border-radius:50%; background:var(--orange); flex-shrink:0; margin-top:6px;"></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>
