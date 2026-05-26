<?php
/**
 * public/pages/admin_contact.php — Gestion des messages du formulaire contact
 *
 * Variables disponibles (index.php routing) :
 *   $contact_messages : tous les messages, du plus récent au plus ancien
 *   $contact_unread   : nombre de messages non lus
 *
 * Actions disponibles (POST vers handlers/admin.php, page=admin_contact) :
 *   - mark_read      : marquer un message individuel comme lu
 *   - mark_unread    : repasser un message en non lu
 *   - mark_all_read  : marquer tous les messages comme lus d'un coup
 *   - delete         : supprimer définitivement un message
 *
 * Le champ hidden "from" indique au handler de quelle page vient la requête
 * pour rediriger correctement après action.
 */
?>
<!-- Conteneur principal de la page contact admin avec padding vertical -->
<main class="container" style="padding: 40px 0;">

    <!-- Barre de navigation admin avec l'onglet 'admin_contact' actif -->
    <?php admin_nav('admin_contact'); ?>

    <!-- ── EN-TÊTE ─────────────────────────────────────────────────────────── -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
        <div>
            <h1 style="color:var(--navy); margin-bottom:4px;">Messages de contact</h1>
            <!-- Compteur total de messages avec accord au pluriel, et badge orange si messages non lus -->
            <p style="color:var(--gray-500); font-size:0.9rem;">
                <?= count($contact_messages) ?> message<?= count($contact_messages) > 1 ? 's' : '' ?>
                <?php if ($contact_unread > 0): // Affiche le nombre de non lus uniquement s'il en existe ?>
                    — <span style="color:var(--orange); font-weight:600;"><?= $contact_unread ?> non lu<?= $contact_unread > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </p>
        </div>
        <!-- Bouton global "Tout marquer comme lu" : visible seulement si au moins 1 non lu -->
        <?php if ($contact_unread > 0): ?>
        <form method="POST" action="/sharetime/public/?page=admin_contact">
            <!-- Token CSRF pour sécuriser l'action de masse contre les requêtes forgées -->
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <!-- Action de masse : marque tous les messages comme lus en une seule requête -->
            <input type="hidden" name="contact_action" value="mark_all_read">
            <!-- msg_id à 0 : le handler détecte la valeur 0 pour appliquer l'action à tous -->
            <input type="hidden" name="msg_id" value="0">
            <!-- Champ "from" indique au handler la page source pour la redirection post-action -->
            <input type="hidden" name="from" value="admin_contact">
            <button type="submit" class="btn btn-outline-navy btn-sm">
                ✓ Tout marquer comme lu
            </button>
        </form>
        <?php endif; ?>
    </div>

    <!-- ── ÉTAT VIDE ─────────────────────────────────────────────────────── -->
    <?php if (empty($contact_messages)): // Aucun message reçu via le formulaire de contact ?>
        <div style="text-align:center; padding:64px 0; color:var(--gray-400);">
            <!-- Icône boîte aux lettres vide pour signaler visuellement l'absence de messages -->
            <div style="font-size:3rem; margin-bottom:16px;">📭</div>
            <p style="font-size:1rem; font-weight:600; color:var(--gray-500);">Aucun message reçu</p>
            <p style="font-size:0.85rem;">Les messages du formulaire de contact apparaîtront ici.</p>
        </div>
    <?php else: ?>

    <!-- ── LISTE DES MESSAGES ─────────────────────────────────────────────── -->
    <!-- Colonne flex pour empiler les cards de messages verticalement avec espacement -->
    <div style="display:flex; flex-direction:column; gap:12px;">
        <?php foreach ($contact_messages as $contact_message_item):
            // Booléen pour conditionner les styles (bordure orange = non lu, grise = lu)
            $is_already_read  = (bool)$contact_message_item['is_read'];
            // Objet DateTime pour formater la date d'envoi en affichage lisible
            $message_datetime = new DateTime($contact_message_item['sent_at']);
        ?>
        <!-- Card message : bordure orange + ombre si non lu, bordure grise si lu -->
        <div style="background:white; border:1.5px solid <?= $is_already_read ? 'var(--gray-200)' : 'var(--orange)' ?>;
                    border-radius:12px; padding:20px 24px;
                    <?= $is_already_read ? '' : 'box-shadow:0 2px 8px rgba(232,129,26,0.1);' ?>">

            <!-- Disposition flex : infos expéditeur à gauche, boutons d'action à droite -->
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap;">

                <!-- Zone gauche : infos expéditeur + contenu du message -->
                <div style="flex:1; min-width:0;">
                    <!-- Ligne d'en-tête : badge Nouveau + nom + email + date sur une seule ligne -->
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; flex-wrap:wrap;">
                        <!-- Badge orange "Nouveau" uniquement pour les messages non lus -->
                        <?php if (!$is_already_read): ?>
                            <span style="background:var(--orange); color:white; font-size:0.65rem; font-weight:700;
                                         padding:2px 8px; border-radius:99px; text-transform:uppercase; letter-spacing:0.5px;">
                                Nouveau
                            </span>
                        <?php endif; ?>
                        <!-- Nom de l'expéditeur en gras navy -->
                        <strong style="color:var(--navy); font-size:0.95rem;">
                            <?= htmlspecialchars($contact_message_item['name']) ?>
                        </strong>
                        <!-- Email de l'expéditeur : lien mailto pour répondre directement depuis le panel -->
                        <a href="mailto:<?= htmlspecialchars($contact_message_item['email']) ?>"
                           style="color:var(--orange); font-size:0.85rem; text-decoration:none;">
                            <?= htmlspecialchars($contact_message_item['email']) ?>
                        </a>
                        <!-- Date d'envoi alignée à droite avec margin-left:auto -->
                        <span style="color:var(--gray-400); font-size:0.8rem; margin-left:auto;">
                            <!-- Format : JJ/MM/AAAA à HHhMM (ex : 26/05/2026 à 14h30) -->
                            <?= $message_datetime->format('d/m/Y à H\hi') ?>
                        </span>
                    </div>

                    <!-- Sujet : affiché uniquement s'il a été renseigné par l'expéditeur -->
                    <?php if (!empty($contact_message_item['subject'])): ?>
                    <p style="font-weight:600; color:var(--gray-700); margin:0 0 8px; font-size:0.9rem;">
                        <?= htmlspecialchars($contact_message_item['subject']) ?>
                    </p>
                    <?php endif; ?>

                    <!-- Corps du message : white-space:pre-wrap préserve les sauts de ligne -->
                    <p style="color:var(--gray-600); font-size:0.88rem; margin:0; line-height:1.6; white-space:pre-wrap;">
                        <?= htmlspecialchars($contact_message_item['message']) ?>
                    </p>
                </div>

                <!-- Zone droite : 3 boutons d'action empilés verticalement -->
                <div style="display:flex; flex-direction:column; gap:6px; flex-shrink:0;">

                    <!-- Bouton toggle lu/non lu : bascule selon l'état actuel -->
                    <form method="POST" action="/sharetime/public/?page=admin_contact">
                        <!-- Token CSRF pour sécuriser le changement d'état lu/non lu -->
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <!-- ID du message ciblé, casté en entier pour éviter les injections -->
                        <input type="hidden" name="msg_id" value="<?= (int)$contact_message_item['id'] ?>">
                        <!-- Page source transmise au handler pour la redirection après action -->
                        <input type="hidden" name="from" value="admin_contact">
                        <!-- contact_action : 'mark_unread' si déjà lu, 'mark_read' si non lu -->
                        <input type="hidden" name="contact_action" value="<?= $is_already_read ? 'mark_unread' : 'mark_read' ?>">
                        <!-- Style du bouton : fond navy si non lu (mise en avant), gris si déjà lu -->
                        <button type="submit" style="width:100%; padding:6px 14px; font-size:0.78rem; font-weight:600;
                                background:<?= $is_already_read ? 'var(--gray-100)' : 'var(--navy)' ?>;
                                color:<?= $is_already_read ? 'var(--gray-600)' : 'white' ?>;
                                border:1.5px solid <?= $is_already_read ? 'var(--gray-300)' : 'var(--navy)' ?>;
                                border-radius:8px; cursor:pointer; white-space:nowrap;">
                            <!-- Libellé inversé : propose de repasser en non lu si déjà lu, et vice versa -->
                            <?= $is_already_read ? '↩ Marquer non lu' : '✓ Marquer lu' ?>
                        </button>
                    </form>

                    <!-- Bouton Répondre : ouvre le client mail avec sujet pré-rempli en Re: -->
                    <!-- urlencode encodé le sujet pour éviter les espaces dans l'URL mailto -->
                    <a href="mailto:<?= htmlspecialchars($contact_message_item['email']) ?>?subject=Re: <?= htmlspecialchars(urlencode($contact_message_item['subject'] ?: 'Votre message')) ?>"
                       style="display:block; text-align:center; padding:6px 14px; font-size:0.78rem; font-weight:600;
                              background:var(--orange); color:white; border-radius:8px; text-decoration:none; white-space:nowrap;">
                        ✉ Répondre
                    </a>

                    <!-- Bouton Supprimer : suppression définitive avec confirmation JS -->
                    <!-- onsubmit sur le formulaire bloque la soumission si l'admin clique "Annuler" -->
                    <form method="POST" action="/sharetime/public/?page=admin_contact"
                          onsubmit="return confirm('Supprimer ce message définitivement ?')">
                        <!-- Token CSRF pour sécuriser la suppression définitive -->
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <!-- ID du message à supprimer, casté en entier pour la sécurité -->
                        <input type="hidden" name="msg_id" value="<?= (int)$contact_message_item['id'] ?>">
                        <!-- Page source pour la redirection après suppression -->
                        <input type="hidden" name="from" value="admin_contact">
                        <!-- Action 'delete' déclenche la suppression définitive dans le handler -->
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
        <?php endforeach; // Fin du foreach sur les messages de contact ?>
    </div>

    <?php endif; ?>

</main>
