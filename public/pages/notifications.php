<?php
/**
 * public/pages/notifications.php — Centre de notifications in-app
 *
 * Variables disponibles (préparées par index.php routing) :
 *   $notifications : tableau de toutes les notifications de l'utilisateur connecté,
 *                    triées par date décroissante (les plus récentes en premier)
 *
 * Chaque notification a les champs :
 *   - type       : 'nouvelle_inscription' | 'promotion_attente' | 'activite_modifiee'
 *                  | 'activite_annulee' | 'nouveau_follower' | autres
 *   - title      : titre court de la notification
 *   - content    : message détaillé
 *   - is_read    : 0 = non lue, 1 = lue
 *   - activity_id: ID de l'activité liée (ou null)
 *   - created_at : horodatage d'insertion
 *
 * Le bouton "Tout marquer comme lu" soumet un POST vers handlers/user.php
 * (page=notifs_lues) qui met à jour toutes les is_read à 1.
 */
?>
<main class="container" style="padding:40px 0; max-width:700px; margin:auto;">

    <!-- ── EN-TÊTE ────────────────────────────────────────────────────────── -->
    <div style="margin-bottom:28px;">
        <h1 style="color:var(--navy); margin-bottom:4px;">Notifications</h1>
        <?php
        // Compte les notifications affichées qui étaient non lues à l'arrivée sur la page
        // (is_read est encore à 0 dans le tableau fetchié avant le UPDATE automatique)
        $unread = count(array_filter($notifications, fn($n) => !$n['is_read']));
        ?>
        <?php if ($unread > 0): ?>
            <p style="color:var(--orange); font-size:0.88rem; font-weight:600;"><?= $unread ?> nouvelle<?= $unread > 1 ? 's' : '' ?></p>
        <?php else: ?>
            <p style="color:var(--gray-400); font-size:0.88rem;">Tout est lu</p>
        <?php endif; ?>
    </div>

    <!-- ── ÉTAT VIDE ──────────────────────────────────────────────────────── -->
    <?php if (empty($notifications)): ?>
        <div style="text-align:center; padding:80px 0; color:var(--gray-400);">
            <p style="font-size:2.5rem; margin-bottom:12px;">🔔</p>
            <p style="font-size:1rem; font-weight:600; color:var(--gray-600);">Aucune notification pour le moment.</p>
        </div>

    <?php else: ?>
        <!-- ── LISTE DES NOTIFICATIONS ────────────────────────────────────── -->
        <div style="display:flex; flex-direction:column; gap:10px;">
        <?php foreach ($notifications as $n):
            // Mapping type → emoji pour distinguer visuellement les notifications
            $icons = [
                'nouvelle_inscription' => '👤',  // quelqu'un s'est inscrit à ton activité
                'promotion_attente'    => '🎉',  // promu de la liste d'attente
                'activite_modifiee'    => '✏️',  // une activité inscrite a été modifiée
                'activite_annulee'     => '❌',  // une activité inscrite a été annulée
                'nouveau_follower'     => '⭐',  // quelqu'un te suit
            ];
            $icon    = $icons[$n['type']] ?? '🔔';   // 🔔 par défaut si type inconnu
            $is_read = !empty($n['is_read']);
            $date    = (new DateTime($n['created_at']))->format('d/m/Y à H:i');
        ?>
        <!-- Card notification : fond légèrement orangé + bordure orange si non lue,
             blanc + gris si déjà lue — différenciation visuelle pour les non-lues. -->
        <div style="background:<?= $is_read ? 'white' : '#FFF8F0' ?>; border:1.5px solid <?= $is_read ? 'var(--gray-200)' : 'rgba(232,129,26,0.3)' ?>;
                    border-radius:12px; padding:16px 20px; display:flex; gap:14px; align-items:flex-start;">
            <!-- Emoji de type -->
            <span style="font-size:1.4rem; flex-shrink:0; line-height:1.2;"><?= $icon ?></span>
            <div style="flex:1; min-width:0;">
                <!-- Titre : gras si non lu, normal si lu -->
                <p style="margin:0 0 4px; font-weight:<?= $is_read ? '400' : '600' ?>; color:var(--gray-900); font-size:0.92rem;">
                    <?= htmlspecialchars($n['title']) ?>
                </p>
                <!-- Contenu détaillé de la notification -->
                <p style="margin:0 0 6px; color:var(--gray-600); font-size:0.85rem; line-height:1.5;">
                    <?= htmlspecialchars($n['content']) ?>
                </p>
                <!-- Méta : date + lien vers l'activité si la notification en a une -->
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <span style="font-size:0.78rem; color:var(--gray-400);"><?= $date ?></span>
                    <?php if ($n['activity_id']): ?>
                    <!-- Lien contextuel vers l'activité concernée -->
                    <a href="/sharetime/public/?page=detail&id=<?= (int)$n['activity_id'] ?>"
                       style="font-size:0.8rem; color:var(--orange); font-weight:600; text-decoration:none;">
                        Voir l'activité →
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Point orange indicateur de non-lu : affiché seulement si is_read = 0 -->
            <?php if (!$is_read): ?>
            <div style="width:8px; height:8px; border-radius:50%; background:var(--orange); flex-shrink:0; margin-top:6px;"></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>
