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
<main class="container" style="padding:40px 0; max-width:700px; margin:auto;"> <!-- conteneur centré limité à 700px pour une lisibilité optimale sur grand écran -->

    <!-- ── EN-TÊTE ────────────────────────────────────────────────────────── -->
    <div style="margin-bottom:28px;">
        <h1 style="color:var(--navy); margin-bottom:4px;">Notifications</h1>
        <?php
        // Compte les notifications non lues dans le tableau récupéré avant le rendu
        // (is_read = 0 au moment du fetch — permet l'affichage du compteur sans requête supplémentaire)
        $unread_notif_count = count(array_filter($notifications, fn($notif_item) => !$notif_item['is_read'])); // array_filter garde uniquement les éléments dont is_read vaut 0 (non lus)
        ?>
        <?php if ($unread_notif_count > 0): // au moins une notification non lue ?>
            <p style="color:var(--orange); font-size:0.88rem; font-weight:600;"><?= $unread_notif_count ?> nouvelle<?= $unread_notif_count > 1 ? 's' : '' ?></p> <!-- accord au pluriel si plus d'une notification non lue -->
        <?php else: // toutes les notifications ont été lues ?>
            <p style="color:var(--gray-400); font-size:0.88rem;">Tout est lu</p>
        <?php endif; ?>
    </div>

    <!-- ── ÉTAT VIDE ──────────────────────────────────────────────────────── -->
    <?php if (empty($notifications)): // aucune notification enregistrée pour cet utilisateur ?>
        <div style="text-align:center; padding:80px 0; color:var(--gray-400);">
            <p style="font-size:2.5rem; margin-bottom:12px;">🔔</p>
            <p style="font-size:1rem; font-weight:600; color:var(--gray-600);">Aucune notification pour le moment.</p>
        </div>

    <?php else: // des notifications existent : on les affiche ?>
        <!-- ── LISTE DES NOTIFICATIONS ────────────────────────────────────── -->
        <div style="display:flex; flex-direction:column; gap:10px;"> <!-- conteneur flex vertical avec espace entre chaque carte -->
        <?php foreach ($notifications as $notif_item): // boucle sur chaque notification de l'utilisateur
            // Mapping type de notification → emoji pour distinguer visuellement chaque catégorie
            $type_icon_map = [
                'nouvelle_inscription' => '👤',  // quelqu'un s'est inscrit à une activité que l'utilisateur organise
                'promotion_attente'    => '🎉',  // l'utilisateur a été promu de la liste d'attente vers les inscrits
                'activite_modifiee'    => '✏️',  // une activité à laquelle l'utilisateur est inscrit a été modifiée
                'activite_annulee'     => '❌',  // une activité à laquelle l'utilisateur est inscrit a été annulée
                'nouveau_follower'     => '⭐',  // un autre utilisateur a commencé à suivre ce profil
            ];
            $notif_icon          = $type_icon_map[$notif_item['type']] ?? '🔔';  // 🔔 par défaut si type inconnu
            $is_already_read     = !empty($notif_item['is_read']); // true si is_read = 1 (déjà consultée), false sinon
            $notif_formatted_date = (new DateTime($notif_item['created_at']))->format('d/m/Y à H:i'); // formate la date SQL en "22/05/2025 à 14:32"
        ?>
        <!-- Card notification : fond légèrement orangé + bordure orange si non lue,
             fond blanc + gris si déjà lue — différenciation visuelle pour les non-lues. -->
        <!-- couleurs conditionnelles selon l'état de lecture -->
        <div style="background:<?= $is_already_read ? 'white' : '#FFF8F0' ?>; border:1.5px solid <?= $is_already_read ? 'var(--gray-200)' : 'rgba(232,129,26,0.3)' ?>;
                    border-radius:12px; padding:16px 20px; display:flex; gap:14px; align-items:flex-start;">
            <!-- Emoji de type de notification -->
            <span style="font-size:1.4rem; flex-shrink:0; line-height:1.2;"><?= $notif_icon ?></span> <!-- icône figée, ne rétrécit pas avec flex -->
            <div style="flex:1; min-width:0;"> <!-- min-width:0 nécessaire pour que text-overflow fonctionne dans un conteneur flex -->
                <!-- Titre : gras si non lu, grammage normal si lu — accentue visuellement le non-lu -->
                <p style="margin:0 0 4px; font-weight:<?= $is_already_read ? '400' : '600' ?>; color:var(--gray-900); font-size:0.92rem;"> <!-- font-weight 600 = gras pour attirer l'attention sur le non-lu -->
                    <?= htmlspecialchars($notif_item['title']) ?> <!-- titre protégé contre le XSS -->
                </p>
                <!-- Contenu détaillé de la notification (nom de l'utilisateur concerné, etc.) -->
                <p style="margin:0 0 6px; color:var(--gray-600); font-size:0.85rem; line-height:1.5;">
                    <?= htmlspecialchars($notif_item['content']) ?> <!-- texte explicatif protégé contre le XSS -->
                </p>
                <!-- Méta : date + lien vers l'activité si la notification y est liée -->
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;"> <!-- flex-wrap pour que le lien passe à la ligne sur mobile si besoin -->
                    <span style="font-size:0.78rem; color:var(--gray-400);"><?= $notif_formatted_date ?></span> <!-- horodatage formaté de la notification -->
                    <?php if ($notif_item['activity_id']): // n'affiche le lien que si la notification est liée à une activité ?>
                    <!-- Lien contextuel vers l'activité concernée par la notification -->
                    <!-- ID casté en entier pour éviter toute injection dans l'URL -->
                    <a href="/sharetime/public/?page=detail&id=<?= (int)$notif_item['activity_id'] ?>"
                       style="font-size:0.8rem; color:var(--orange); font-weight:600; text-decoration:none;">
                        Voir l'activité →
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Point orange indicateur de non-lu : affiché seulement si is_read = 0 -->
            <?php if (!$is_already_read): // pastille visible uniquement pour les notifications non lues ?>
            <div style="width:8px; height:8px; border-radius:50%; background:var(--orange); flex-shrink:0; margin-top:6px;"></div> <!-- petit cercle orange positionné en haut à droite de la carte -->
            <?php endif; ?>
        </div>
        <?php endforeach; // fin de la boucle sur les notifications ?>
        </div>
    <?php endif; ?>

</main>
