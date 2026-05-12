<?php
/**
 * public/pages/admin_contact.php — Gestion des messages du formulaire contact
 *
 * Variables disponibles (index.php routing) :
 *   $contact_messages : tous les messages, du plus récent au plus ancien
 *   $contact_unread   : nombre de messages non lus
 */
?>
<main class="container" style="padding: 40px 0;">

    <?php admin_nav('admin_contact'); ?>

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
        <div>
            <h1 style="color:var(--navy); margin-bottom:4px;">Messages de contact</h1>
            <p style="color:var(--gray-500); font-size:0.9rem;">
                <?= count($contact_messages) ?> message<?= count($contact_messages) > 1 ? 's' : '' ?>
                <?php if ($contact_unread > 0): ?>
                    — <span style="color:var(--orange); font-weight:600;"><?= $contact_unread ?> non lu<?= $contact_unread > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </p>
        </div>
        <?php if ($contact_unread > 0): ?>
        <form method="POST" action="/sharetime/public/?page=admin_contact">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="contact_action" value="mark_all_read">
            <input type="hidden" name="msg_id" value="0">
            <input type="hidden" name="from" value="admin_contact">
            <button type="submit" class="btn btn-outline-navy btn-sm">
                ✓ Tout marquer comme lu
            </button>
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

                <!-- Infos expéditeur + message -->
                <div style="flex:1; min-width:0;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; flex-wrap:wrap;">
                        <?php if (!$is_read): ?>
                            <span style="background:var(--orange); color:white; font-size:0.65rem; font-weight:700;
                                         padding:2px 8px; border-radius:99px; text-transform:uppercase; letter-spacing:0.5px;">
                                Nouveau
                            </span>
                        <?php endif; ?>
                        <strong style="color:var(--navy); font-size:0.95rem;">
                            <?= htmlspecialchars($msg['name']) ?>
                        </strong>
                        <a href="mailto:<?= htmlspecialchars($msg['email']) ?>"
                           style="color:var(--orange); font-size:0.85rem; text-decoration:none;">
                            <?= htmlspecialchars($msg['email']) ?>
                        </a>
                        <span style="color:var(--gray-400); font-size:0.8rem; margin-left:auto;">
                            <?= $dt->format('d/m/Y à H\hi') ?>
                        </span>
                    </div>

                    <?php if (!empty($msg['subject'])): ?>
                    <p style="font-weight:600; color:var(--gray-700); margin:0 0 8px; font-size:0.9rem;">
                        <?= htmlspecialchars($msg['subject']) ?>
                    </p>
                    <?php endif; ?>

                    <p style="color:var(--gray-600); font-size:0.88rem; margin:0; line-height:1.6; white-space:pre-wrap;">
                        <?= htmlspecialchars($msg['message']) ?>
                    </p>
                </div>

                <!-- Actions -->
                <div style="display:flex; flex-direction:column; gap:6px; flex-shrink:0;">
                    <form method="POST" action="/sharetime/public/?page=admin_contact">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="msg_id" value="<?= (int)$msg['id'] ?>">
                        <input type="hidden" name="from" value="admin_contact">
                        <input type="hidden" name="contact_action" value="<?= $is_read ? 'mark_unread' : 'mark_read' ?>">
                        <button type="submit" style="width:100%; padding:6px 14px; font-size:0.78rem; font-weight:600;
                                background:<?= $is_read ? 'var(--gray-100)' : 'var(--navy)' ?>;
                                color:<?= $is_read ? 'var(--gray-600)' : 'white' ?>;
                                border:1.5px solid <?= $is_read ? 'var(--gray-300)' : 'var(--navy)' ?>;
                                border-radius:8px; cursor:pointer; white-space:nowrap;">
                            <?= $is_read ? '↩ Marquer non lu' : '✓ Marquer lu' ?>
                        </button>
                    </form>
                    <a href="mailto:<?= htmlspecialchars($msg['email']) ?>?subject=Re: <?= htmlspecialchars(urlencode($msg['subject'] ?: 'Votre message')) ?>"
                       style="display:block; text-align:center; padding:6px 14px; font-size:0.78rem; font-weight:600;
                              background:var(--orange); color:white; border-radius:8px; text-decoration:none; white-space:nowrap;">
                        ✉ Répondre
                    </a>
                    <form method="POST" action="/sharetime/public/?page=admin_contact"
                          onsubmit="return confirm('Supprimer ce message définitivement ?')">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="msg_id" value="<?= (int)$msg['id'] ?>">
                        <input type="hidden" name="from" value="admin_contact">
                        <input type="hidden" name="contact_action" value="delete">
                        <button type="submit" style="width:100%; padding:6px 14px; font-size:0.78rem; font-weight:600;
                                background:white; color:#DC2626; border:1.5px solid #FECACA;
                                border-radius:8px; cursor:pointer; white-space:nowrap;">
                            🗑 Supprimer
                        </button>
                    </form>
                </div>

            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

</main>
