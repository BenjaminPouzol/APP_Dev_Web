<?php
/**
 * public/pages/admin_users.php — Gestion des utilisateurs (panel admin)
 *
 * Variables disponibles (préparées par index.php routing) :
 *   $admin_users_list    : tableau des utilisateurs de la page courante
 *   $admin_total_count   : nombre total d'utilisateurs
 *   $admin_total_pages   : nombre de pages de pagination
 *   $admin_current_page  : page courante
 *   $flash               : message de succès/info après action
 *
 * Actions possibles selon le rôle :
 *   - Admin  : ban/unban uniquement sur les membres (role='utilisateur')
 *   - Superadmin : ban/unban sur tout le monde + set_role + transfer_ownership + delete
 *
 * Les actions POST sont traitées par handlers/admin.php (page=admin_users).
 * L'ID de l'admin connecté est stocké dans $connected_user_id pour bloquer
 * toute action sur sa propre ligne (un admin ne peut pas se bannir lui-même).
 */

// ID de l'utilisateur connecté : sert à détecter sa propre ligne dans le tableau
// et à désactiver les boutons d'action sur elle (évite l'auto-suspension).
$connected_user_id = (int)$_SESSION['user']['id'];
?>
<!-- ── EN-TÊTE ADMIN ──────────────────────────────────────────────────────────
     Bandeau navy commun à toutes les pages admin.
     Affiche le titre de la section + badge de rôle + prénom/nom de l'admin connecté. -->
<div style="background:var(--navy);padding:28px 0;">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <!-- Libellé de section affiché au-dessus du titre principal -->
            <p style="color:rgba(255,255,255,0.55);font-size:0.8rem;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;">Administration</p>
            <!-- Titre principal de la page de gestion des utilisateurs -->
            <h1 style="color:white;margin:0;font-size:1.6rem;">Gestion des utilisateurs</h1>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <!-- Badge coloré indiquant le rôle de l'admin connecté (admin ou owner) -->
            <?= role_badge($_SESSION['user']['role']) ?>
            <!-- Nom complet de l'admin connecté, protégé contre les injections XSS -->
            <span style="color:rgba(255,255,255,0.6);font-size:0.9rem;"><?= htmlspecialchars($_SESSION['user']['prenom'].' '.$_SESSION['user']['nom']) ?></span>
        </div>
    </div>
</div>

<main>
    <!-- Barre de navigation admin avec l'onglet 'admin_users' mis en surbrillance -->
    <?php admin_nav('admin_users'); ?>

    <div class="container" style="padding-bottom:48px;">

        <!-- Message de succès après une action (ban, unban, changement de rôle…) -->
        <?php if ($flash): // Affiche le bandeau vert uniquement si un message flash existe ?>
        <div style="background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0;border-radius:10px;padding:12px 18px;margin-bottom:24px;font-weight:600;">
            <!-- htmlspecialchars protège contre toute injection dans le message flash -->
            <?= htmlspecialchars($flash) ?>
        </div>
        <?php endif; ?>

        <!-- ── BANDEAU OWNER ──────────────────────────────────────────────────
             Ce rappel informe l'owner de ses capacités exclusives (nommer admins,
             transférer la propriété) qui ne sont pas accessibles aux admins normaux.
             Affiché uniquement si le visiteur est owner (is_owner() vérifié côté PHP). -->
        <?php if (is_owner()): // is_owner() retourne true uniquement si role === 'superadmin' ?>
        <div style="background:#FEF3E2;border:1.5px solid rgba(232,129,26,0.3);border-radius:12px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;gap:14px;">
            <!-- Icône couronne signalant visuellement le statut propriétaire -->
            <span style="font-size:1.5rem;">👑</span>
            <div>
                <!-- Titre du bandeau en orange pour attirer l'attention -->
                <p style="margin:0;font-weight:700;color:var(--orange);font-size:0.9rem;">Vous êtes Super-Admin</p>
                <!-- Explication des droits supplémentaires de l'owner avec avertissement sur le transfert -->
                <p style="margin:2px 0 0;font-size:0.82rem;color:var(--gray-600);">
                    Vous pouvez nommer ou révoquer des administrateurs, et transférer vos prérogatives Super-Admin à un autre membre.
                    Le transfert est <strong>irréversible</strong> sans intervention du nouveau super-admin.
                </p>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── TABLEAU DES UTILISATEURS ───────────────────────────────────── -->
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
            <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                    Tous les utilisateurs
                    <!-- Compteur total en badge gris discret : nombre exact d'utilisateurs en base -->
                    <span style="margin-left:8px;background:#F3F4F6;color:#6B7280;font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= $admin_total_count ?></span>
                </h2>
                <!-- Indicateur de pagination affiché uniquement s'il y a plus d'une page -->
                <?php if ($admin_total_pages > 1): ?>
                <span style="font-size:0.82rem;color:var(--gray-500);">Page <?= $admin_current_page ?> / <?= $admin_total_pages ?></span>
                <?php endif; ?>
            </div>

            <!-- overflow-x:auto permet un scroll horizontal sur mobile sans casser la mise en page -->
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                    <thead>
                        <!-- Ligne d'en-tête avec les labels des 6 colonnes du tableau -->
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
                    <?php foreach ($admin_users_list as $user_row):
                        // ID numérique de la ligne traitée, casté pour les comparaisons strictes
                        $user_row_id = (int)$user_row['idusers'];

                        // true si cette ligne correspond à l'admin actuellement connecté
                        // → empêche toute action sur sa propre ligne (auto-ban impossible)
                        $is_connected_user = $user_row_id === $connected_user_id;

                        // true si cette ligne est le superadmin : le superadmin est intouchable même par un admin
                        $is_owner_account = $user_row['role'] === 'superadmin';

                        // true si le compte est suspendu (is_banned = 1 en BDD)
                        $is_user_banned = !empty($user_row['is_banned']);

                        // Date d'inscription formatée en jour/mois/année pour affichage en colonne
                        $registration_date = (new DateTime($user_row['date_creation']))->format('d/m/Y');

                        // can_perform_actions = false sur la propre ligne de l'admin ET sur la ligne superadmin
                        // Principe : on ne peut pas agir sur soi-même ni sur un compte de niveau supérieur
                        $can_perform_actions = !$is_connected_user && !$is_owner_account;
                    ?>
                    <!-- Ligne du tableau pour un utilisateur, séparée par une bordure bas -->
                    <tr style="border-bottom:1px solid var(--gray-50);">
                        <!-- Colonne Utilisateur : avatar initiale + nom complet + email + pseudo optionnel -->
                        <td style="padding:12px 16px;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <!-- Avatar : initiale du prénom sur fond gradient navy -->
                                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.9rem;flex-shrink:0;">
                                    <!-- strtoupper + mb_substr extrait la première lettre du prénom (compatible accents) -->
                                    <?= strtoupper(mb_substr($user_row['prenom'],0,1)) ?>
                                </div>
                                <div>
                                    <p style="margin:0;font-weight:600;color:var(--gray-900);">
                                        <!-- Nom complet protégé contre les XSS -->
                                        <?= htmlspecialchars($user_row['prenom'].' '.$user_row['nom']) ?>
                                        <!-- "(vous)" affiché discrètement sur la propre ligne de l'admin connecté -->
                                        <?php if ($is_connected_user): ?><span style="font-size:0.72rem;color:var(--gray-400);font-weight:400;">(vous)</span><?php endif; ?>
                                    </p>
                                    <!-- Email de l'utilisateur en texte secondaire plus petit -->
                                    <p style="margin:0;color:var(--gray-500);font-size:0.78rem;"><?= htmlspecialchars($user_row['email']) ?></p>
                                    <!-- Pseudo affiché uniquement s'il est défini (champ facultatif à l'inscription) -->
                                    <?php if ($user_row['pseudo']): ?>
                                    <p style="margin:0;color:var(--gray-400);font-size:0.75rem;">@<?= htmlspecialchars($user_row['pseudo']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <!-- Colonne Rôle : badge coloré via role_badge() + état banni si applicable -->
                        <!-- role_badge() gère lui-même le badge "Banni" rouge si $is_user_banned est true -->
                        <td style="padding:12px 16px;"><?= role_badge($user_row['role'], $is_user_banned) ?></td>

                        <!-- Compteurs issus du LEFT JOIN effectué dans User::getAllForAdmin -->
                        <!-- Nombre d'activités créées par cet utilisateur -->
                        <td style="padding:12px 16px;text-align:center;color:var(--gray-700);font-weight:600;"><?= $user_row['nb_activities'] ?></td>
                        <!-- Nombre d'inscriptions à des activités de cet utilisateur -->
                        <td style="padding:12px 16px;text-align:center;color:var(--gray-700);font-weight:600;"><?= $user_row['nb_registrations'] ?></td>

                        <!-- Date d'inscription formatée depuis date_creation en BDD -->
                        <td style="padding:12px 16px;color:var(--gray-500);font-size:0.82rem;"><?= $registration_date ?></td>

                        <!-- Colonne Actions : boutons conditionnels selon rôle du visiteur et état du compte -->
                        <td style="padding:12px 16px;">
                            <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;">

                                <?php if ($can_perform_actions): // Des actions sont disponibles pour ce compte ?>

                                    <!-- Bouton Suspendre / Réactiver ─────────────────────────────────
                                         Règle de visibilité :
                                         - Owner  : peut agir sur n'importe quel compte non-owner
                                         - Admin  : uniquement sur les membres (role='utilisateur')
                                         L'action POST (ban / unban) est traitée par handlers/admin.php. -->
                                    <?php if (is_owner() || $user_row['role'] === 'utilisateur'): ?>
                                    <form method="POST" action="/sharetime/public/?page=admin_users" style="display:inline;">
                                        <!-- Token CSRF pour sécuriser le formulaire contre les attaques forgées -->
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <!-- ID de l'utilisateur cible transmis de manière invisible -->
                                        <input type="hidden" name="user_id" value="<?= $user_row_id ?>">
                                        <!-- L'action est dynamique : unban si déjà suspendu, ban sinon -->
                                        <input type="hidden" name="action" value="<?= $is_user_banned ? 'unban' : 'ban' ?>">
                                        <!-- Bordure et couleur du bouton changent selon l'action disponible (vert=réactiver, rouge=suspendre) -->
                                        <button type="submit"
                                            style="padding:5px 12px;border-radius:6px;border:1.5px solid <?= $is_user_banned ? '#059669' : '#DC2626' ?>;background:white;color:<?= $is_user_banned ? '#059669' : '#DC2626' ?>;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                            onclick="return confirm('<?= $is_user_banned ? 'Réactiver ce compte ?' : 'Suspendre ce compte ?' ?>')">
                                            <!-- Libellé du bouton adapté selon l'état du compte -->
                                            <?= $is_user_banned ? '✓ Réactiver' : '⊘ Suspendre' ?>
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <!-- Actions exclusives à l'owner (nommer, transférer, supprimer) ──── -->
                                    <?php if (is_owner()): // Bloc réservé au super-administrateur ?>

                                        <!-- Changer le rôle membre ↔ admin (masqué si banni pour cohérence UI) -->
                                        <?php if (!$is_user_banned): ?>
                                        <form method="POST" action="/sharetime/public/?page=admin_users" style="display:inline;display:flex;gap:4px;">
                                            <!-- Token CSRF pour sécuriser le changement de rôle -->
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <!-- ID de l'utilisateur dont on change le rôle -->
                                            <input type="hidden" name="user_id" value="<?= $user_row_id ?>">
                                            <!-- Action 'set_role' indique au handler de modifier le champ role en BDD -->
                                            <input type="hidden" name="action" value="set_role">
                                            <!-- Select pré-sélectionné sur le rôle actuel de l'utilisateur -->
                                            <select name="role" style="padding:5px 8px;border-radius:6px;border:1.5px solid var(--gray-200);font-size:0.78rem;color:var(--gray-700);">
                                                <option value="utilisateur" <?= $user_row['role']==='utilisateur'?'selected':'' ?>>Membre</option>
                                                <option value="admin"       <?= $user_row['role']==='admin'?'selected':'' ?>>Admin</option>
                                            </select>
                                            <!-- Bouton de soumission du changement de rôle -->
                                            <button type="submit" style="padding:5px 10px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-700);font-size:0.78rem;font-weight:600;cursor:pointer;">OK</button>
                                        </form>
                                        <?php endif; ?>

                                        <!-- Transférer la propriété : action irréversible → double confirmation JS -->
                                        <!-- addslashes nécessaire pour échapper le nom dans la chaîne de confirm() -->
                                        <form method="POST" action="/sharetime/public/?page=admin_users" style="display:inline;">
                                            <!-- Token CSRF pour sécuriser le transfert de propriété -->
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <!-- ID du futur owner cible du transfert -->
                                            <input type="hidden" name="user_id" value="<?= $user_row_id ?>">
                                            <!-- Action 'transfer_ownership' change le role 'owner' vers cet utilisateur -->
                                            <input type="hidden" name="action" value="transfer_ownership">
                                            <button type="submit"
                                                style="padding:5px 10px;border-radius:6px;border:1.5px solid var(--orange);background:white;color:var(--orange);font-size:0.78rem;font-weight:600;cursor:pointer;"
                                                onclick="return confirm('Transférer le rôle Super-Admin à <?= htmlspecialchars(addslashes($user_row['prenom'].' '.$user_row['nom'])) ?> ?\n\nVous deviendrez administrateur. Cette action est irréversible sans intervention du nouveau super-admin.')">
                                                <!-- Icône couronne pour signaler la nature de l'action -->
                                                👑 Super-Admin
                                            </button>
                                        </form>

                                        <!-- Supprimer définitivement le compte (supprime aussi ses activités, inscriptions, messages…) -->
                                        <form method="POST" action="/sharetime/public/?page=admin_users" style="display:inline;">
                                            <!-- Token CSRF pour sécuriser la suppression définitive -->
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <!-- ID de l'utilisateur à supprimer définitivement -->
                                            <input type="hidden" name="user_id" value="<?= $user_row_id ?>">
                                            <!-- Action 'delete' déclenche la suppression en cascade dans le handler -->
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit"
                                                style="padding:5px 10px;border-radius:6px;border:1.5px solid #DC2626;background:#DC2626;color:white;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                                onclick="return confirm('Supprimer définitivement ce compte et toutes ses données ?')">
                                                🗑 Suppr.
                                            </button>
                                        </form>

                                    <?php endif; // Fin des actions réservées à l'owner ?>

                                <?php else: ?>
                                    <!-- Aucune action disponible : soit c'est la propre ligne de l'admin (protection
                                         contre l'auto-ban), soit c'est la ligne de l'owner (protégée par conception). -->
                                    <span style="color:var(--gray-300);font-size:0.8rem;font-style:italic;">
                                        <!-- "Vous" si c'est sa propre ligne, "Protégé" si c'est l'owner -->
                                        <?= $is_connected_user ? 'Vous' : 'Protégé' ?>
                                    </span>
                                <?php endif; ?>

                            </div>
                        </td>
                    </tr>
                    <?php endforeach; // Fin du foreach sur les utilisateurs de la page courante ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── PAGINATION ─────────────────────────────────────────────────── -->
        <?php if ($admin_total_pages > 1): // Affiche la pagination uniquement s'il y a plusieurs pages ?>
        <div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:28px;flex-wrap:wrap;">
            <!-- Lien "Précédent" masqué sur la première page pour ne pas dépasser la limite -->
            <?php if ($admin_current_page > 1): ?>
                <a href="/sharetime/public/?page=admin_users&p=<?= $admin_current_page - 1 ?>" class="btn btn-outline-navy btn-sm">← Précédent</a>
            <?php endif; ?>
            <!-- Fenêtre glissante de 5 pages centrée sur la page courante (±2) -->
            <?php for ($page_number = max(1, $admin_current_page - 2); $page_number <= min($admin_total_pages, $admin_current_page + 2); $page_number++): ?>
                <a href="/sharetime/public/?page=admin_users&p=<?= $page_number ?>"
                   style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;font-size:0.9rem;font-weight:600;text-decoration:none;
                          background:<?= $page_number === $admin_current_page ? 'var(--navy)' : 'var(--gray-100)' ?>;
                          color:<?= $page_number === $admin_current_page ? 'white' : 'var(--gray-600)' ?>;">
                    <!-- Numéro de page : fond navy si page active, fond gris clair sinon -->
                    <?= $page_number ?>
                </a>
            <?php endfor; ?>
            <!-- Lien "Suivant" masqué sur la dernière page pour ne pas dépasser la limite -->
            <?php if ($admin_current_page < $admin_total_pages): ?>
                <a href="/sharetime/public/?page=admin_users&p=<?= $admin_current_page + 1 ?>" class="btn btn-outline-navy btn-sm">Suivant →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- Table responsive sur mobile : réduit la taille de police et le padding des cellules -->
<style>
@media (max-width: 768px) {
    table { font-size: 0.8rem !important; }  /* Police réduite pour faire tenir le tableau sur petit écran */
    td, th { padding: 8px 10px !important; } /* Padding réduit pour gagner de la place horizontalement */
}
</style>
