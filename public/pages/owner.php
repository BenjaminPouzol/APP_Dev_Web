<?php
/**
 * public/pages/owner.php — Panel propriétaire (7 onglets)
 *
 * Variables disponibles (préparées par index.php routing) :
 *   $owner_tab              : onglet actif transmis en GET (?tab=…)
 *   $owner_users            : liste complète de tous les utilisateurs (onglets users/admins)
 *   $admin_stats            : statistiques globales (membres, admins, activités, inscriptions, suspendus)
 *   $admin_recent_users     : 5 derniers membres inscrits (onglet dashboard)
 *   $admin_recent_activities: 5 dernières activités créées (onglet dashboard)
 *   $admin_activities_list  : liste complète des activités (onglet activities)
 *   $contact_messages       : messages du formulaire de contact (onglet contact)
 *   $contact_unread         : compteur de messages non lus (onglet contact)
 *   $flash                  : message de succès après action
 *
 * Accessible uniquement par l'owner (require_owner() dans index.php).
 * Les actions POST sont traitées par handlers/admin.php (page=owner).
 *
 * Différences avec le panel admin classique :
 *   - Onglet "Administrateurs" : nommer/révoquer des admins + transférer la propriété
 *   - Onglets "Contenu du site" et "Signalements" réservés à l'owner
 *   - Navigation par onglets (tabs) au lieu de pages séparées
 *   - Aucune pagination : toutes les données chargées d'un coup pour le panel owner
 */

// Validation de l'onglet actif : whitelist pour éviter les valeurs GET arbitraires
$valid_tabs = ['dashboard', 'users', 'activities', 'admins', 'contact', 'contenu', 'signalements'];
$active_tab = in_array($owner_tab ?? '', $valid_tabs) ? $owner_tab : 'dashboard';

// ID de l'owner connecté : utilisé pour protéger sa propre ligne contre les auto-actions
$owner_user_id = (int)$_SESSION['user']['id'];

// Définition des onglets : slug → [emoji, libellé] pour la barre de navigation
$tab_definitions = [
    'dashboard'    => ['📊', 'Tableau de bord'],
    'users'        => ['👥', 'Utilisateurs'],
    'activities'   => ['🎯', 'Activités'],
    'admins'       => ['👑', 'Administrateurs'],
    'contact'      => ['✉️', 'Messages contact'],
    'contenu'      => ['📝', 'Contenu du site'],
    'signalements' => ['🚩', 'Signalements'],
];

// ── Données chargées à la demande selon l'onglet actif ─────────────────────
// Charger ces données uniquement si l'onglet correspondant est ouvert
// évite des requêtes SQL inutiles pour les onglets non consultés.

// Données de l'onglet "signalements"
$reports_list          = [];
$reports_pending_count = 0;
if ($active_tab === 'signalements') {
    // JOIN double sur users pour récupérer les infos du signaleur et du signalé
    // ORDER BY FIELD trie : en_attente d'abord, puis traité, puis rejeté
    $reports_list = $pdo->query("
        SELECT r.*,
               u1.prenom AS sg_prenom, u1.nom AS sg_nom, u1.pseudo AS sg_pseudo,
               u2.prenom AS sd_prenom, u2.nom AS sd_nom, u2.pseudo AS sd_pseudo
        FROM reports r
        JOIN users u1 ON u1.idusers = r.signaleur_id
        JOIN users u2 ON u2.idusers = r.signale_id
        ORDER BY FIELD(r.status,'en_attente','traite','rejete'), r.created_at DESC
    ")->fetchAll();
    // Compteur de signalements en attente affiché dans le titre de l'onglet
    $reports_pending_count = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'en_attente'")->fetchColumn();
}

// Données de l'onglet "contenu" : FAQ, CGU, Mentions légales
$faq_items_owner   = [];
$cgu_owner         = '';   // texte des CGU actuelles
$cgu_version_owner = '';   // numéro de version des CGU (ex : "v1.1")
$mentions_owner    = '';   // texte des mentions légales actuelles
if ($active_tab === 'contenu') {
    // Toutes les questions FAQ, triées par ID (ordre de création)
    $faq_items_owner   = $pdo->query("SELECT * FROM faq ORDER BY idfaq ASC")->fetchAll();
    // La version la plus récente des CGU (ORDER BY DESC LIMIT 1)
    $cgu_row           = $pdo->query("SELECT contenu, version FROM cgu ORDER BY idcgu DESC LIMIT 1")->fetch();
    $cgu_owner         = $cgu_row['contenu'] ?? '';
    $cgu_version_owner = $cgu_row['version'] ?? '';
    // La version la plus récente des mentions légales
    $mentions_row      = $pdo->query("SELECT contenu FROM mentions ORDER BY idmentions DESC LIMIT 1")->fetch();
    $mentions_owner    = $mentions_row['contenu'] ?? '';
}
?>

<!-- ── EN-TÊTE OWNER ──────────────────────────────────────────────────────────
     Gradient orange (vs navy pour l'admin) pour signifier le niveau hiérarchique supérieur.
     Affiche le badge de rôle owner + prénom/nom de l'owner connecté. -->
<div style="background:linear-gradient(135deg,var(--orange) 0%,#c96a10 100%);padding:28px 0;">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <p style="color:rgba(255,255,255,0.65);font-size:0.8rem;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;">Super-Admin</p>
            <h1 style="color:white;margin:0;font-size:1.6rem;">Panel Super-Admin</h1>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <!-- role_badge() génère le badge HTML coloré selon le rôle -->
            <?= role_badge($_SESSION['user']['role']) ?>
            <span style="color:rgba(255,255,255,0.7);font-size:0.9rem;"><?= htmlspecialchars($_SESSION['user']['prenom'].' '.$_SESSION['user']['nom']) ?></span>
        </div>
    </div>
</div>

<!-- ── NAVIGATION PAR ONGLETS ────────────────────────────────────────────────
     Barre d'onglets sous le header : l'onglet actif a une bordure orange et couleur navy,
     les autres sont gris. overflow-x:auto permet de scroller sur mobile. -->
<div style="background:white;border-bottom:2px solid var(--gray-200);margin-bottom:32px;">
    <div class="container" style="display:flex;gap:0;overflow-x:auto;">
        <?php foreach ($tab_definitions as $tab_slug => [$tab_icon, $tab_label]): ?>
        <!-- Lien d'onglet : bordure inférieure orange + couleur navy si actif, gris sinon -->
        <a href="/sharetime/public/?page=owner&tab=<?= $tab_slug ?>"
           style="padding:14px 20px;font-weight:600;font-size:0.9rem;text-decoration:none;white-space:nowrap;
                  display:inline-flex;align-items:center;gap:6px;transition:all 0.15s;
                  border-bottom:3px solid <?= $active_tab === $tab_slug ? 'var(--orange)' : 'transparent' ?>;
                  color:<?= $active_tab === $tab_slug ? 'var(--navy)' : 'var(--gray-500)' ?>;">
            <?= $tab_icon ?> <?= $tab_label ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<main>
<div class="container" style="padding-bottom:48px;">

<!-- Message flash de succès après une action (ban, set_role, transfer, delete…) -->
<?php if ($flash): ?>
<div style="background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0;border-radius:10px;padding:12px 18px;margin-bottom:24px;font-weight:600;">
    <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<?php /* ══════════════════════════════════════════════════════════════
   ONGLET DASHBOARD : statistiques globales + aperçu des derniers membres/activités.
   Même données que admin.php, mais les liens "Tout voir" pointent vers
   les onglets du panel owner (?page=owner&tab=…) et non vers les pages admin séparées.
══════════════════════════════════════════════════════════════════ */ ?>
<?php if ($active_tab === 'dashboard'): ?>

    <!-- Grille de cards de statistiques globales (auto-fit pour s'adapter à l'écran) -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:36px;">
        <?php
        // Définition des 5 indicateurs : [libellé, valeur numérique, emoji, fond coloré, couleur texte]
        $stats_card_definitions = [
            ['Membres',      $admin_stats['membres'],      '👤', '#EBF0F8', 'var(--navy)'],
            ['Admins',       $admin_stats['admins'],       '🛡️', '#FEF3E2', 'var(--orange)'],
            ['Activités',    $admin_stats['activites'],    '🎯', '#D1FAE5', '#065F46'],
            ['Inscriptions', $admin_stats['inscriptions'], '✅', '#EDE9FE', '#7C3AED'],
            ['Suspendus',    $admin_stats['suspendus'],    '🚫', '#FEE2E2', '#DC2626'],
        ];
        foreach ($stats_card_definitions as [$card_label, $card_value, $card_icon, $card_bg_color, $card_text_color]):
        ?>
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;padding:20px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <span style="font-size:0.75rem;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;"><?= $card_label ?></span>
                <!-- Icône dans un fond coloré spécifique à chaque indicateur -->
                <span style="font-size:1.3rem;background:<?= $card_bg_color ?>;padding:6px;border-radius:8px;"><?= $card_icon ?></span>
            </div>
            <!-- Valeur numérique en grand, colorée selon l'indicateur -->
            <p style="font-size:2rem;font-weight:800;color:<?= $card_text_color ?>;margin:0;"><?= $card_value ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Deux colonnes : derniers membres inscrits + dernières activités créées -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

        <!-- Derniers membres inscrits (5 derniers) -->
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
            <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;">
                <h2 style="margin:0;font-size:1rem;color:var(--navy);">Derniers membres</h2>
                <!-- "Tout voir" pointe vers l'onglet users du panel owner -->
                <a href="/sharetime/public/?page=owner&tab=users" style="font-size:0.82rem;color:var(--orange);font-weight:600;text-decoration:none;">Tout voir →</a>
            </div>
            <?php foreach ($admin_recent_users as $recent_user): ?>
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-50);display:flex;align-items:center;justify-content:space-between;gap:10px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <!-- Avatar initiale sur fond gradient navy -->
                    <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.9rem;flex-shrink:0;">
                        <?= strtoupper(mb_substr($recent_user['prenom'],0,1)) ?>
                    </div>
                    <div>
                        <p style="margin:0;font-size:0.88rem;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($recent_user['prenom'].' '.$recent_user['nom']) ?></p>
                        <p style="margin:0;font-size:0.78rem;color:var(--gray-500);"><?= htmlspecialchars($recent_user['email']) ?></p>
                    </div>
                </div>
                <!-- Badge rôle + état banni si applicable -->
                <?= role_badge($recent_user['role'], !empty($recent_user['is_banned'])) ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Dernières activités créées (5 dernières) -->
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
            <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;">
                <h2 style="margin:0;font-size:1rem;color:var(--navy);">Dernières activités</h2>
                <a href="/sharetime/public/?page=owner&tab=activities" style="font-size:0.82rem;color:var(--orange);font-weight:600;text-decoration:none;">Tout voir →</a>
            </div>
            <?php foreach ($admin_recent_activities as $recent_activity):
                // Formatage de la date de début pour affichage compact dans la liste
                $start_datetime = new DateTime($recent_activity['start_time']);
                // Tables de correspondance statut → couleurs du badge
                $status_badge_colors = ['active'=>['#D1FAE5','#065F46'],'en_cours'=>['#FEF3C7','#92400E'],'annulee'=>['#FEE2E2','#DC2626'],'terminee'=>['#F3F4F6','#6B7280']];
                $status_badge_labels = ['active'=>'À venir','en_cours'=>'En cours','annulee'=>'Annulée','terminee'=>'Terminée'];
                // Déstructuration du tableau de couleurs (fond + texte) avec fallback gris
                [$status_badge_bg, $status_badge_color] = $status_badge_colors[$recent_activity['status']] ?? ['#F3F4F6','#6B7280'];
            ?>
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-50);display:flex;align-items:center;justify-content:space-between;gap:10px;">
                <div>
                    <p style="margin:0;font-size:0.88rem;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($recent_activity['title']) ?></p>
                    <p style="margin:0;font-size:0.78rem;color:var(--gray-500);"><?= htmlspecialchars($recent_activity['city']) ?> · <?= $start_datetime->format('d/m/Y') ?></p>
                </div>
                <!-- Badge statut coloré selon l'état actuel de l'activité -->
                <span style="background:<?= $status_badge_bg ?>;color:<?= $status_badge_color ?>;padding:3px 10px;border-radius:99px;font-size:0.75rem;font-weight:600;white-space:nowrap;"><?= $status_badge_labels[$recent_activity['status']] ?? ucfirst($recent_activity['status']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php /* ══════════════════════════════════════════════════════════════
   ONGLET USERS : tableau complet de tous les utilisateurs.
   L'owner peut : ban/unban, set_role, supprimer, transférer la propriété.
   Toutes les actions POST vers page=owner avec type=user + tab=users
   pour que le handler redirige vers le bon onglet après traitement.
══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($active_tab === 'users'): ?>

    <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
        <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);">
            <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                Tous les utilisateurs
                <!-- Compteur total en badge gris -->
                <span style="margin-left:8px;background:#F3F4F6;color:#6B7280;font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= count($owner_users) ?></span>
            </h2>
        </div>
        <!-- overflow-x:auto pour scroller horizontalement sur mobile -->
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                <thead>
                    <tr style="background:#F9FAFB;border-bottom:1px solid var(--gray-200);">
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Utilisateur</th>
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Rôle</th>
                        <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Activités</th>
                        <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Inscriptions</th>
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Depuis</th>
                        <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($owner_users as $owner_user_row):
                    // ID de la ligne courante, casté en int pour les comparaisons strictes
                    $owner_user_row_id = (int)$owner_user_row['idusers'];

                    // true si cette ligne correspond à l'owner connecté (auto-protection)
                    $is_connected_owner = $owner_user_row_id === $owner_user_id;

                    // true si le rôle de cette ligne est 'owner' (il n'y en a qu'un = $owner_user_id)
                    $is_owner_account = $owner_user_row['role'] === 'owner';

                    // true si le compte est actuellement suspendu
                    $is_user_banned = !empty($owner_user_row['is_banned']);

                    // can_perform_actions = false sur la propre ligne + sur la ligne owner
                    $can_perform_actions = !$is_connected_owner && !$is_owner_account;
                ?>
                <tr style="border-bottom:1px solid var(--gray-50);">
                    <!-- Utilisateur : avatar initiale + nom complet + email -->
                    <td style="padding:12px 16px;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.9rem;flex-shrink:0;">
                                <?= strtoupper(mb_substr($owner_user_row['prenom'],0,1)) ?>
                            </div>
                            <div>
                                <p style="margin:0;font-weight:600;color:var(--gray-900);">
                                    <?= htmlspecialchars($owner_user_row['prenom'].' '.$owner_user_row['nom']) ?>
                                    <!-- Indication discrète "(vous)" sur la propre ligne de l'owner -->
                                    <?php if ($is_connected_owner): ?><span style="font-size:0.72rem;color:var(--gray-400);"> (vous)</span><?php endif; ?>
                                </p>
                                <p style="margin:0;color:var(--gray-500);font-size:0.78rem;"><?= htmlspecialchars($owner_user_row['email']) ?></p>
                            </div>
                        </div>
                    </td>
                    <!-- Badge rôle + état banni -->
                    <td style="padding:12px 16px;"><?= role_badge($owner_user_row['role'], $is_user_banned) ?></td>
                    <!-- Compteurs issus du JOIN dans User::getAllForAdmin -->
                    <td style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-700);"><?= $owner_user_row['nb_activities'] ?></td>
                    <td style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-700);"><?= $owner_user_row['nb_registrations'] ?></td>
                    <!-- Date d'inscription formatée -->
                    <td style="padding:12px 16px;color:var(--gray-500);font-size:0.82rem;"><?= (new DateTime($owner_user_row['date_creation']))->format('d/m/Y') ?></td>
                    <td style="padding:12px 16px;">
                        <?php if ($can_perform_actions): ?>
                        <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;">
                            <!-- Suspendre / Réactiver : type=user + tab=users pour retour correct après POST -->
                            <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="type" value="user">
                                <input type="hidden" name="tab" value="users">
                                <input type="hidden" name="user_id" value="<?= $owner_user_row_id ?>">
                                <input type="hidden" name="action" value="<?= $is_user_banned ? 'unban' : 'ban' ?>">
                                <button type="submit"
                                    style="padding:5px 12px;border-radius:6px;border:1.5px solid <?= $is_user_banned ? '#059669' : '#DC2626' ?>;background:white;color:<?= $is_user_banned ? '#059669' : '#DC2626' ?>;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                    onclick="return confirm('<?= $is_user_banned ? 'Réactiver ce compte ?' : 'Suspendre ce compte ?' ?>')">
                                    <?= $is_user_banned ? '✓ Réactiver' : '⊘ Suspendre' ?>
                                </button>
                            </form>
                            <!-- Changer le rôle (membre ↔ admin) : masqué si banni pour cohérence -->
                            <?php if (!$is_user_banned): ?>
                            <form method="POST" action="/sharetime/public/?page=owner" style="display:flex;gap:4px;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="type" value="user">
                                <input type="hidden" name="tab" value="users">
                                <input type="hidden" name="user_id" value="<?= $owner_user_row_id ?>">
                                <input type="hidden" name="action" value="set_role">
                                <select name="role" style="padding:5px 8px;border-radius:6px;border:1.5px solid var(--gray-200);font-size:0.78rem;color:var(--gray-700);">
                                    <option value="utilisateur" <?= $owner_user_row['role']==='utilisateur'?'selected':'' ?>>Membre</option>
                                    <option value="admin"       <?= $owner_user_row['role']==='admin'?'selected':'' ?>>Admin</option>
                                </select>
                                <button type="submit" style="padding:5px 10px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-700);font-size:0.78rem;font-weight:600;cursor:pointer;">OK</button>
                            </form>
                            <?php endif; ?>
                            <!-- Suppression définitive du compte (supprime aussi ses activités, inscriptions…) -->
                            <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="type" value="user">
                                <input type="hidden" name="tab" value="users">
                                <input type="hidden" name="user_id" value="<?= $owner_user_row_id ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit"
                                    style="padding:5px 10px;border-radius:6px;border:1.5px solid #DC2626;background:#DC2626;color:white;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                    onclick="return confirm('Supprimer définitivement ce compte ?')">
                                    🗑
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                        <!-- Aucune action sur la propre ligne de l'owner ni sur son compte protégé -->
                        <span style="color:var(--gray-300);font-size:0.8rem;font-style:italic;"><?= $is_connected_owner ? 'Vous' : 'Protégé' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php /* ══════════════════════════════════════════════════════════════
   ONGLET ACTIVITIES : tableau complet de toutes les activités.
   L'owner peut changer le statut et supprimer. Même structure que admin_activities.php
   mais les actions POST sont envoyées vers page=owner avec type=activity + tab=activities.
══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($active_tab === 'activities'): ?>

    <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
        <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);">
            <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                Toutes les activités
                <span style="margin-left:8px;background:#F3F4F6;color:#6B7280;font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= count($admin_activities_list) ?></span>
            </h2>
        </div>
        <?php if (empty($admin_activities_list)): ?>
        <!-- État vide : aucune activité créée sur la plateforme -->
        <div style="padding:48px;text-align:center;color:var(--gray-400);">
            <p style="font-size:2rem;margin-bottom:8px;">🎯</p><p>Aucune activité.</p>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                <thead>
                    <tr style="background:#F9FAFB;border-bottom:1px solid var(--gray-200);">
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Activité</th>
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Créateur</th>
                        <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Participants</th>
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Date</th>
                        <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Statut</th>
                        <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                // Tables de correspondance statut → couleurs des badges, définies hors de la boucle
                $status_badge_colors = ['active'=>['#D1FAE5','#065F46'],'en_cours'=>['#FEF3C7','#92400E'],'annulee'=>['#FEE2E2','#DC2626'],'terminee'=>['#F3F4F6','#6B7280']];
                $status_badge_labels = ['active'=>'À venir','en_cours'=>'En cours','annulee'=>'Annulée','terminee'=>'Terminée'];
                foreach ($admin_activities_list as $activity_row):
                    // Formatage de la date de début pour la colonne "Date"
                    $start_datetime = new DateTime($activity_row['start_time']);
                    // Couleurs du badge statut avec fallback gris pour les statuts inconnus
                    [$status_badge_bg, $status_badge_color] = $status_badge_colors[$activity_row['status']] ?? ['#F3F4F6','#6B7280'];
                ?>
                <tr style="border-bottom:1px solid var(--gray-50);">
                    <!-- Titre tronqué par ellipsis + ville -->
                    <td style="padding:12px 16px;max-width:200px;">
                        <p style="margin:0;font-weight:600;color:var(--gray-900);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($activity_row['title']) ?></p>
                        <p style="margin:0;color:var(--gray-500);font-size:0.78rem;"><?= htmlspecialchars($activity_row['city']) ?></p>
                    </td>
                    <!-- Prénom + Nom du créateur de l'activité -->
                    <td style="padding:12px 16px;color:var(--gray-700);"><?= htmlspecialchars($activity_row['prenom'].' '.$activity_row['nom']) ?></td>
                    <!-- Inscrits / max participants -->
                    <td style="padding:12px 16px;text-align:center;">
                        <span style="font-weight:600;"><?= (int)$activity_row['nb_inscrits'] ?></span><span style="color:var(--gray-400);">/</span><span style="color:var(--gray-500);"><?= (int)$activity_row['max_participants'] ?></span>
                    </td>
                    <!-- Date de début formatée -->
                    <td style="padding:12px 16px;color:var(--gray-600);font-size:0.82rem;white-space:nowrap;"><?= $start_datetime->format('d/m/Y') ?></td>
                    <!-- Badge statut coloré -->
                    <td style="padding:12px 16px;text-align:center;">
                        <span style="background:<?= $status_badge_bg ?>;color:<?= $status_badge_color ?>;padding:3px 10px;border-radius:99px;font-size:0.75rem;font-weight:600;"><?= $status_badge_labels[$activity_row['status']] ?? ucfirst($activity_row['status']) ?></span>
                    </td>
                    <td style="padding:12px 16px;">
                        <div style="display:flex;gap:6px;justify-content:flex-end;align-items:center;flex-wrap:wrap;">
                            <!-- Changer le statut : type=activity + tab=activities pour retour correct -->
                            <form method="POST" action="/sharetime/public/?page=owner" style="display:flex;gap:4px;align-items:center;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="type" value="activity">
                                <input type="hidden" name="tab" value="activities">
                                <input type="hidden" name="activity_id" value="<?= (int)$activity_row['idactivities'] ?>">
                                <input type="hidden" name="action" value="set_status">
                                <select name="status" style="padding:5px 8px;border-radius:6px;border:1.5px solid var(--gray-200);font-size:0.78rem;color:var(--gray-700);">
                                    <option value="active"   <?= $activity_row['status']==='active'   ?'selected':'' ?>>À venir</option>
                                    <option value="en_cours" <?= $activity_row['status']==='en_cours' ?'selected':'' ?>>En cours</option>
                                    <option value="annulee"  <?= $activity_row['status']==='annulee'  ?'selected':'' ?>>Annulée</option>
                                    <option value="terminee" <?= $activity_row['status']==='terminee' ?'selected':'' ?>>Terminée</option>
                                </select>
                                <button type="submit" style="padding:5px 10px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-700);font-size:0.78rem;font-weight:600;cursor:pointer;">OK</button>
                            </form>
                            <!-- Lien "Voir" : ouvre la page de détail publique de l'activité -->
                            <a href="/sharetime/public/?page=detail&id=<?= (int)$activity_row['idactivities'] ?>"
                               style="padding:5px 10px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-700);font-size:0.78rem;font-weight:600;text-decoration:none;">👁</a>
                            <!-- Suppression définitive : confirmation JS requise -->
                            <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="type" value="activity">
                                <input type="hidden" name="tab" value="activities">
                                <input type="hidden" name="activity_id" value="<?= (int)$activity_row['idactivities'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit"
                                    style="padding:5px 10px;border-radius:6px;border:1.5px solid #DC2626;background:#DC2626;color:white;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                    onclick="return confirm('Supprimer cette activité et ses inscriptions ?')">🗑</button>
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

<?php /* ══════════════════════════════════════════════════════════════
   ONGLET ADMINS : gestion des administrateurs.
   Deux sections distinctes :
   1. Administrateurs actuels → révoquer ou transférer la propriété
   2. Nommer un administrateur → membres actifs et non bannis disponibles
══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($active_tab === 'admins'): ?>

    <?php
    // Filtre la liste $owner_users en deux sous-listes distinctes :
    // admins actuels (role='admin') et membres éligibles (role='utilisateur', non bannis)
    $admin_users_list    = array_values(array_filter($owner_users, fn($u) => $u['role'] === 'admin'));
    $eligible_member_list = array_values(array_filter($owner_users, fn($u) => $u['role'] === 'utilisateur' && empty($u['is_banned'])));
    ?>

    <!-- Rappel des règles importantes pour éviter les mauvaises manipulations -->
    <div style="background:#FEF3E2;border:1.5px solid rgba(232,129,26,0.3);border-radius:12px;padding:16px 20px;margin-bottom:28px;display:flex;align-items:center;gap:14px;">
        <span style="font-size:1.5rem;">👑</span>
        <p style="margin:0;font-size:0.88rem;color:var(--gray-700);">
            <strong>Seul le super-admin</strong> peut nommer ou révoquer des administrateurs et transférer la propriété du site.
            Le transfert est <strong>irréversible</strong> sans intervention du nouveau super-admin.
        </p>
    </div>

    <!-- ── Section 1 : Administrateurs actuels ──────────────────────────────── -->
    <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;margin-bottom:24px;">
        <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);">
            <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                Administrateurs actuels
                <span style="margin-left:8px;background:#EBF0F8;color:var(--navy);font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= count($admin_users_list) ?></span>
            </h2>
        </div>
        <?php if (empty($admin_users_list)): ?>
        <p style="padding:24px 20px;color:var(--gray-500);margin:0;">Aucun administrateur pour le moment.</p>
        <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
            <thead>
                <tr style="background:#F9FAFB;border-bottom:1px solid var(--gray-200);">
                    <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Administrateur</th>
                    <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Email</th>
                    <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($admin_users_list as $admin_user_row): ?>
            <tr style="border-bottom:1px solid var(--gray-50);">
                <td style="padding:12px 16px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <!-- Avatar navy (admins nommés = même niveau que les admins classiques) -->
                        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.9rem;flex-shrink:0;">
                            <?= strtoupper(mb_substr($admin_user_row['prenom'],0,1)) ?>
                        </div>
                        <p style="margin:0;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($admin_user_row['prenom'].' '.$admin_user_row['nom']) ?></p>
                    </div>
                </td>
                <td style="padding:12px 16px;color:var(--gray-500);font-size:0.85rem;"><?= htmlspecialchars($admin_user_row['email']) ?></td>
                <td style="padding:12px 16px;">
                    <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;">
                        <!-- Révoquer le rôle admin → rétrograde en membre (utilisateur) -->
                        <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="type" value="user">
                            <input type="hidden" name="tab" value="admins">
                            <input type="hidden" name="user_id" value="<?= (int)$admin_user_row['idusers'] ?>">
                            <input type="hidden" name="action" value="set_role">
                            <input type="hidden" name="role" value="utilisateur">
                            <!-- addslashes dans le confirm() pour échapper les apostrophes dans les prénoms -->
                            <button type="submit"
                                style="padding:5px 12px;border-radius:6px;border:1.5px solid #DC2626;background:white;color:#DC2626;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                onclick="return confirm('Révoquer le rôle admin de <?= htmlspecialchars(addslashes($admin_user_row['prenom'].' '.$admin_user_row['nom'])) ?> ?')">
                                ⊘ Révoquer
                            </button>
                        </form>
                        <!-- Transférer la propriété : action irréversible → double confirmation JS -->
                        <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="type" value="user">
                            <input type="hidden" name="tab" value="admins">
                            <input type="hidden" name="user_id" value="<?= (int)$admin_user_row['idusers'] ?>">
                            <input type="hidden" name="action" value="transfer_ownership">
                            <button type="submit"
                                style="padding:5px 12px;border-radius:6px;border:1.5px solid var(--orange);background:white;color:var(--orange);font-size:0.78rem;font-weight:600;cursor:pointer;"
                                onclick="return confirm('Transférer le rôle Super-Admin à <?= htmlspecialchars(addslashes($admin_user_row['prenom'].' '.$admin_user_row['nom'])) ?> ?\n\nVous deviendrez administrateur. Action irréversible.')">
                                👑 Transférer
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ── Section 2 : Nommer un administrateur ──────────────────────────────── -->
    <!-- Liste filtrée : membres actifs (role='utilisateur') non bannis uniquement -->
    <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
        <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);">
            <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                Nommer un administrateur
                <span style="margin-left:8px;background:#F3F4F6;color:#6B7280;font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= count($eligible_member_list) ?> membres</span>
            </h2>
        </div>
        <?php if (empty($eligible_member_list)): ?>
        <p style="padding:24px 20px;color:var(--gray-500);margin:0;">Aucun membre disponible.</p>
        <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
            <thead>
                <tr style="background:#F9FAFB;border-bottom:1px solid var(--gray-200);">
                    <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Membre</th>
                    <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Email</th>
                    <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Activités</th>
                    <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($eligible_member_list as $member_row): ?>
            <tr style="border-bottom:1px solid var(--gray-50);">
                <td style="padding:12px 16px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <!-- Avatar gris (membres non encore nommés, à distinguer des admins en navy) -->
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--gray-200);display:flex;align-items:center;justify-content:center;color:var(--gray-600);font-weight:700;font-size:0.9rem;flex-shrink:0;">
                            <?= strtoupper(mb_substr($member_row['prenom'],0,1)) ?>
                        </div>
                        <p style="margin:0;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($member_row['prenom'].' '.$member_row['nom']) ?></p>
                    </div>
                </td>
                <td style="padding:12px 16px;color:var(--gray-500);font-size:0.85rem;"><?= htmlspecialchars($member_row['email']) ?></td>
                <!-- Nombre d'activités créées par ce membre (signe de son implication) -->
                <td style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-700);"><?= (int)$member_row['nb_activities'] ?></td>
                <td style="padding:12px 16px;text-align:right;">
                    <!-- Nommer admin : set_role avec role=admin + tab=admins pour redirection correcte -->
                    <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="type" value="user">
                        <input type="hidden" name="tab" value="admins">
                        <input type="hidden" name="user_id" value="<?= (int)$member_row['idusers'] ?>">
                        <input type="hidden" name="action" value="set_role">
                        <input type="hidden" name="role" value="admin">
                        <button type="submit"
                            style="padding:5px 12px;border-radius:6px;border:1.5px solid #059669;background:white;color:#059669;font-size:0.78rem;font-weight:600;cursor:pointer;"
                            onclick="return confirm('Nommer <?= htmlspecialchars(addslashes($member_row['prenom'].' '.$member_row['nom'])) ?> administrateur ?')">
                            ✓ Nommer admin
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

<?php /* ══════════════════════════════════════════════════════════════
   ONGLET CONTACT : messages reçus via le formulaire de contact.
   Même données que admin_contact.php mais intégrées dans le panel owner.
   Actions : mark_read, mark_unread, mark_all_read, delete.
══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($active_tab === 'contact'): ?>

    <!-- En-tête : titre + compteur + bouton "Tout marquer lu" si messages non lus -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
        <div>
            <h2 style="color:var(--navy); margin:0 0 4px;">Messages de contact</h2>
            <p style="color:var(--gray-500); font-size:0.9rem; margin:0;">
                <?= count($contact_messages ?? []) ?> message<?= count($contact_messages ?? []) > 1 ? 's' : '' ?>
                <?php if (($contact_unread ?? 0) > 0): ?>
                    — <span style="color:var(--orange); font-weight:600;"><?= $contact_unread ?> non lu<?= $contact_unread > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </p>
        </div>
        <!-- "Tout marquer lu" : visible uniquement s'il reste des messages non lus -->
        <?php if (($contact_unread ?? 0) > 0): ?>
        <form method="POST" action="/sharetime/public/?page=owner">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="contact_action" value="mark_all_read">
            <input type="hidden" name="msg_id" value="0">
            <input type="hidden" name="from" value="owner">
            <button type="submit" class="btn btn-outline-navy btn-sm">✓ Tout marquer comme lu</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (empty($contact_messages)): ?>
        <!-- État vide : aucun message de contact reçu -->
        <div style="text-align:center; padding:64px 0; color:var(--gray-400);">
            <div style="font-size:3rem; margin-bottom:16px;">📭</div>
            <p style="font-size:1rem; font-weight:600; color:var(--gray-500);">Aucun message reçu</p>
            <p style="font-size:0.85rem;">Les messages du formulaire de contact apparaîtront ici.</p>
        </div>
    <?php else: ?>
    <div style="display:flex; flex-direction:column; gap:12px;">
        <?php foreach ($contact_messages as $contact_message_item):
            // Booléen de lecture : bordure orange + badge "Nouveau" si non lu
            $is_already_read = (bool)$contact_message_item['is_read'];
            // Objet DateTime pour formater l'horodatage d'envoi
            $message_datetime = new DateTime($contact_message_item['sent_at']);
        ?>
        <!-- Bordure orange + légère ombre si non lu, gris standard si lu -->
        <div style="background:white; border:1.5px solid <?= $is_already_read ? 'var(--gray-200)' : 'var(--orange)' ?>;
                    border-radius:12px; padding:20px 24px;
                    <?= $is_already_read ? '' : 'box-shadow:0 2px 8px rgba(232,129,26,0.1);' ?>">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                <div style="flex:1; min-width:0;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; flex-wrap:wrap;">
                        <!-- Badge "Nouveau" orange uniquement pour les messages non lus -->
                        <?php if (!$is_already_read): ?>
                            <span style="background:var(--orange);color:white;font-size:0.65rem;font-weight:700;padding:2px 8px;border-radius:99px;text-transform:uppercase;letter-spacing:0.5px;">Nouveau</span>
                        <?php endif; ?>
                        <strong style="color:var(--navy); font-size:0.95rem;"><?= htmlspecialchars($contact_message_item['name']) ?></strong>
                        <!-- Email cliquable (mailto: pour réponse directe depuis un client mail) -->
                        <a href="mailto:<?= htmlspecialchars($contact_message_item['email']) ?>" style="color:var(--orange);font-size:0.85rem;text-decoration:none;"><?= htmlspecialchars($contact_message_item['email']) ?></a>
                        <span style="color:var(--gray-400);font-size:0.8rem;margin-left:auto;"><?= $message_datetime->format('d/m/Y à H\hi') ?></span>
                    </div>
                    <!-- Sujet du message si défini (champ optionnel dans le formulaire de contact) -->
                    <?php if (!empty($contact_message_item['subject'])): ?>
                    <p style="font-weight:600;color:var(--gray-700);margin:0 0 8px;font-size:0.9rem;"><?= htmlspecialchars($contact_message_item['subject']) ?></p>
                    <?php endif; ?>
                    <!-- Corps du message : pre-wrap pour préserver les retours à la ligne -->
                    <p style="color:var(--gray-600);font-size:0.88rem;margin:0;line-height:1.6;white-space:pre-wrap;"><?= htmlspecialchars($contact_message_item['message']) ?></p>
                </div>
                <!-- Colonne d'actions : marquer lu/non-lu, répondre par email, supprimer -->
                <div style="display:flex; flex-direction:column; gap:6px; flex-shrink:0;">
                    <!-- Basculer l'état lu/non-lu : contact_action dynamique selon l'état actuel -->
                    <form method="POST" action="/sharetime/public/?page=owner">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="msg_id" value="<?= (int)$contact_message_item['id'] ?>">
                        <input type="hidden" name="from" value="owner">
                        <input type="hidden" name="contact_action" value="<?= $is_already_read ? 'mark_unread' : 'mark_read' ?>">
                        <button type="submit" style="width:100%;padding:6px 14px;font-size:0.78rem;font-weight:600;background:<?= $is_already_read ? 'var(--gray-100)' : 'var(--navy)' ?>;color:<?= $is_already_read ? 'var(--gray-600)' : 'white' ?>;border:1.5px solid <?= $is_already_read ? 'var(--gray-300)' : 'var(--navy)' ?>;border-radius:8px;cursor:pointer;white-space:nowrap;">
                            <?= $is_already_read ? '↩ Marquer non lu' : '✓ Marquer lu' ?>
                        </button>
                    </form>
                    <!-- Lien "Répondre" : ouvre le client mail avec sujet pré-rempli en "Re: …" -->
                    <a href="mailto:<?= htmlspecialchars($contact_message_item['email']) ?>?subject=Re: <?= htmlspecialchars(urlencode($contact_message_item['subject'] ?: 'Votre message')) ?>"
                       style="display:block;text-align:center;padding:6px 14px;font-size:0.78rem;font-weight:600;background:var(--orange);color:white;border-radius:8px;text-decoration:none;white-space:nowrap;">
                        ✉ Répondre
                    </a>
                    <!-- Supprimer définitivement le message (confirmation JS) -->
                    <form method="POST" action="/sharetime/public/?page=owner" onsubmit="return confirm('Supprimer ce message ?')">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="msg_id" value="<?= (int)$contact_message_item['id'] ?>">
                        <input type="hidden" name="from" value="owner">
                        <input type="hidden" name="contact_action" value="delete">
                        <button type="submit" style="width:100%;padding:6px 14px;font-size:0.78rem;font-weight:600;background:white;color:#DC2626;border:1.5px solid #FECACA;border-radius:8px;cursor:pointer;white-space:nowrap;">
                            🗑 Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php /* ══════════════════════════════════════════════════════════════
   ONGLET CONTENU DU SITE : édition de la FAQ, des CGU et des mentions légales.
   L'owner peut ajouter/modifier/supprimer des questions FAQ, éditer le texte
   des CGU (avec versionnage) et celui des mentions légales.
   Le bouton "Éditer" d'une FAQ pré-remplit le formulaire via JS (openEditFaq).
══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($active_tab === 'contenu'): ?>

    <!-- ── FAQ ──────────────────────────────────────────────────────────────── -->
    <div style="margin-bottom:36px;">
        <h2 style="color:var(--navy);margin-bottom:16px;font-size:1.1rem;">📋 Foire aux questions</h2>

        <!-- Liste des questions FAQ existantes -->
        <?php if (empty($faq_items_owner)): ?>
            <p style="color:var(--gray-500);margin-bottom:16px;">Aucune question pour le moment.</p>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">
            <?php foreach ($faq_items_owner as $faq_item): ?>
            <div style="background:white;border:1.5px solid var(--gray-200);border-radius:10px;padding:14px 16px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                    <div style="flex:1;">
                        <!-- Question et aperçu de la réponse tronqué à 120 caractères -->
                        <p style="font-weight:600;color:var(--gray-900);margin:0 0 4px;"><?= htmlspecialchars($faq_item['question']) ?></p>
                        <p style="color:var(--gray-500);font-size:0.88rem;margin:0;"><?= htmlspecialchars(mb_substr($faq_item['reponse'], 0, 120)) ?>...</p>
                    </div>
                    <div style="display:flex;gap:6px;flex-shrink:0;">
                        <!-- Bouton Éditer : pré-remplit le formulaire d'ajout/édition via JS -->
                        <button type="button"
                            onclick="openEditFaq(<?= $faq_item['idfaq'] ?>, <?= htmlspecialchars(json_encode($faq_item['question'])) ?>, <?= htmlspecialchars(json_encode($faq_item['reponse'])) ?>)"
                            style="padding:5px 12px;border-radius:6px;border:1.5px solid var(--navy);background:white;color:var(--navy);font-size:0.78rem;font-weight:600;cursor:pointer;">
                            ✏️ Éditer
                        </button>
                        <!-- Supprimer une question FAQ (confirmation JS) -->
                        <form method="POST" action="/sharetime/public/?page=owner" onsubmit="return confirm('Supprimer cette question ?')">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="type" value="content">
                            <input type="hidden" name="action" value="delete_faq">
                            <input type="hidden" name="faq_id" value="<?= $faq_item['idfaq'] ?>">
                            <button type="submit" style="padding:5px 12px;border-radius:6px;border:1.5px solid #FECACA;background:white;color:#DC2626;font-size:0.78rem;font-weight:600;cursor:pointer;">
                                🗑
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire ajout/édition FAQ : bascule entre les deux modes via JS -->
        <div style="background:var(--gray-50);border:1.5px solid var(--gray-200);border-radius:12px;padding:20px;">
            <h3 id="faq-form-title" style="color:var(--navy);margin:0 0 14px;font-size:0.95rem;">+ Ajouter une question</h3>
            <form id="faq-form" method="POST" action="/sharetime/public/?page=owner" style="display:flex;flex-direction:column;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="type" value="content">
                <!-- faq-action et faq-id changent entre 'add_faq' et 'edit_faq' selon le mode -->
                <input type="hidden" id="faq-action" name="action" value="add_faq">
                <input type="hidden" id="faq-id" name="faq_id" value="">
                <div>
                    <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:6px;font-size:0.88rem;">Question *</label>
                    <input type="text" id="faq-q" name="question" required placeholder="Ex : Comment créer une activité ?"
                        style="width:100%;padding:10px 14px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:0.9rem;font-family:inherit;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:6px;font-size:0.88rem;">Réponse *</label>
                    <textarea id="faq-r" name="reponse" required rows="3" placeholder="Réponse complète..."
                        style="width:100%;padding:10px 14px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:0.9rem;font-family:inherit;resize:vertical;box-sizing:border-box;"></textarea>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn btn-navy">Enregistrer</button>
                    <!-- Bouton Annuler masqué par défaut, révélé en mode édition -->
                    <button type="button" id="faq-cancel" onclick="resetFaqForm()" style="display:none;padding:10px 18px;border:1.5px solid var(--gray-300);border-radius:8px;background:white;font-size:0.9rem;cursor:pointer;">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── CGU (Conditions Générales d'Utilisation) ───────────────────────────── -->
    <div style="margin-bottom:36px;">
        <h2 style="color:var(--navy);margin-bottom:16px;font-size:1.1rem;">📄 Conditions Générales d'Utilisation</h2>
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:12px;padding:20px;">
            <form method="POST" action="/sharetime/public/?page=owner" style="display:flex;flex-direction:column;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="type" value="content">
                <input type="hidden" name="action" value="update_cgu">
                <div>
                    <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:6px;font-size:0.88rem;">Version (ex : v1.1)</label>
                    <input type="text" name="version" value="<?= htmlspecialchars($cgu_version_owner) ?>" placeholder="v1.0"
                        style="width:200px;padding:10px 14px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:0.9rem;font-family:inherit;">
                </div>
                <div>
                    <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:6px;font-size:0.88rem;">Contenu *</label>
                    <p style="font-size:0.8rem;color:var(--gray-500);margin:0 0 8px;">Le texte sera affiché tel quel sur la page CGU. Utilisez des sauts de ligne pour structurer.</p>
                    <!-- Pré-rempli avec le texte des CGU actuelles -->
                    <textarea name="contenu" required rows="12"
                        style="width:100%;padding:12px 14px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:0.88rem;font-family:inherit;resize:vertical;box-sizing:border-box;line-height:1.6;"><?= htmlspecialchars($cgu_owner) ?></textarea>
                </div>
                <button type="submit" class="btn btn-navy" style="width:fit-content;">Enregistrer les CGU</button>
            </form>
        </div>
    </div>

    <!-- ── MENTIONS LÉGALES ──────────────────────────────────────────────────── -->
    <div>
        <h2 style="color:var(--navy);margin-bottom:16px;font-size:1.1rem;">⚖️ Mentions légales</h2>
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:12px;padding:20px;">
            <form method="POST" action="/sharetime/public/?page=owner" style="display:flex;flex-direction:column;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="type" value="content">
                <input type="hidden" name="action" value="update_mentions">
                <div>
                    <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:6px;font-size:0.88rem;">Contenu *</label>
                    <p style="font-size:0.8rem;color:var(--gray-500);margin:0 0 8px;">Affiché sur la page Mentions légales. Sauts de ligne préservés.</p>
                    <textarea name="contenu" required rows="12"
                        style="width:100%;padding:12px 14px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:0.88rem;font-family:inherit;resize:vertical;box-sizing:border-box;line-height:1.6;"><?= htmlspecialchars($mentions_owner) ?></textarea>
                </div>
                <button type="submit" class="btn btn-navy" style="width:fit-content;">Enregistrer les mentions</button>
            </form>
        </div>
    </div>

<?php /* ══════════════════════════════════════════════════════════════
   ONGLET SIGNALEMENTS : modération des signalements d'utilisateurs.
   Deux actions disponibles pour les signalements "en_attente" :
   - Marquer "traité" : signalement pris en compte
   - Rejeter : signalement non fondé
   Les signalements sont triés par FIELD(status,...) : en_attente d'abord.
══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($active_tab === 'signalements'): ?>

    <!-- En-tête : titre + compteur + nombre en attente -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="color:var(--navy);margin:0 0 4px;">Signalements utilisateurs</h2>
            <p style="color:var(--gray-500);font-size:0.9rem;margin:0;">
                <?= count($reports_list) ?> signalement<?= count($reports_list) > 1 ? 's' : '' ?>
                <?php if ($reports_pending_count > 0): ?>
                    — <span style="color:#DC2626;font-weight:600;"><?= $reports_pending_count ?> en attente</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (empty($reports_list)): ?>
        <!-- État vide : aucun signalement reçu sur la plateforme -->
        <div style="text-align:center;padding:60px 0;color:var(--gray-400);">
            <p style="font-size:2rem;margin-bottom:12px;">✅</p>
            <p>Aucun signalement pour le moment.</p>
        </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px;">
    <?php foreach ($reports_list as $report_item):
        // true si le signalement n'a pas encore été traité (action requise)
        $is_pending_report = $report_item['status'] === 'en_attente';

        // Style inline du badge statut : rouge pour en_attente, vert pour traité, gris pour rejeté
        $report_status_style = match($report_item['status']) {
            'en_attente' => 'background:#FEE2E2;color:#DC2626;',
            'traite'     => 'background:#D1FAE5;color:#065F46;',
            'rejete'     => 'background:#F3F4F6;color:var(--gray-500);',
            default      => ''
        };

        // Libellé du badge statut avec icône
        $report_status_label = match($report_item['status']) {
            'en_attente' => '⏳ En attente',
            'traite'     => '✓ Traité',
            'rejete'     => '✗ Rejeté',
            default      => $report_item['status']
        };

        // Nom d'affichage du signaleur : pseudo si disponible, prénom + nom sinon
        $reporter_display_name = htmlspecialchars(($report_item['sg_pseudo'] ?: $report_item['sg_prenom']) . ' ' . $report_item['sg_nom']);

        // Nom d'affichage de l'utilisateur signalé
        $reported_display_name = htmlspecialchars(($report_item['sd_pseudo'] ?: $report_item['sd_prenom']) . ' ' . $report_item['sd_nom']);
    ?>
    <!-- Bordure rouge pour les signalements en attente, gris pour les traités -->
    <div style="background:white;border:1.5px solid <?= $is_pending_report ? '#FECACA' : 'var(--gray-200)' ?>;border-radius:12px;padding:18px 20px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
                    <!-- Badge de statut coloré selon la sévérité -->
                    <span style="font-size:0.72rem;font-weight:700;padding:3px 10px;border-radius:99px;<?= $report_status_style ?>"><?= $report_status_label ?></span>
                    <!-- Horodatage de la création du signalement -->
                    <span style="color:var(--gray-400);font-size:0.8rem;"><?= date('d/m/Y à H:i', strtotime($report_item['created_at'])) ?></span>
                </div>
                <!-- Signaleur : lien cliquable vers son profil public -->
                <p style="margin:0 0 6px;font-size:0.9rem;color:var(--gray-700);">
                    <strong>Signaleur :</strong>
                    <a href="/sharetime/public/?page=profil&id=<?= $report_item['signaleur_id'] ?>" style="color:var(--navy);font-weight:600;"><?= $reporter_display_name ?></a>
                </p>
                <!-- Signalé : en rouge pour attirer l'attention sur la personne visée -->
                <p style="margin:0 0 8px;font-size:0.9rem;color:var(--gray-700);">
                    <strong>Signalé :</strong>
                    <a href="/sharetime/public/?page=profil&id=<?= $report_item['signale_id'] ?>" style="color:#DC2626;font-weight:600;"><?= $reported_display_name ?></a>
                </p>
                <!-- Motif du signalement dans un encadré gris pour le distinguer -->
                <p style="margin:0;font-size:0.88rem;color:var(--gray-600);background:var(--gray-50);padding:8px 12px;border-radius:8px;">
                    <?= htmlspecialchars($report_item['motif']) ?>
                </p>
            </div>
            <!-- Boutons d'action uniquement pour les signalements en attente -->
            <?php if ($is_pending_report): ?>
            <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
                <!-- Marquer comme traité : signalement examiné et pris en compte -->
                <form method="POST" action="/sharetime/public/?page=owner">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="type" value="report">
                    <input type="hidden" name="action" value="update_report">
                    <input type="hidden" name="report_id" value="<?= $report_item['idreports'] ?>">
                    <input type="hidden" name="status" value="traite">
                    <!-- tab=signalements pour rediriger vers le bon onglet après traitement -->
                    <input type="hidden" name="tab" value="signalements">
                    <button type="submit" style="width:100%;padding:6px 14px;border-radius:6px;border:1.5px solid #059669;background:white;color:#059669;font-size:0.78rem;font-weight:600;cursor:pointer;">
                        ✓ Traité
                    </button>
                </form>
                <!-- Rejeter : signalement examiné mais considéré non fondé -->
                <form method="POST" action="/sharetime/public/?page=owner">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="type" value="report">
                    <input type="hidden" name="action" value="update_report">
                    <input type="hidden" name="report_id" value="<?= $report_item['idreports'] ?>">
                    <input type="hidden" name="status" value="rejete">
                    <input type="hidden" name="tab" value="signalements">
                    <button type="submit" style="width:100%;padding:6px 14px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-500);font-size:0.78rem;font-weight:600;cursor:pointer;">
                        ✗ Rejeter
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

<script>
// ── Gestion du formulaire FAQ en mode ajout / édition ──────────────────────

// Bascule le formulaire en mode "édition" d'une question existante.
// Pré-remplit les champs et change le titre + l'action hidden.
function openEditFaq(faq_id, faq_question, faq_reponse) {
    document.getElementById('faq-form-title').textContent = '✏️ Modifier la question';
    document.getElementById('faq-action').value = 'edit_faq';
    document.getElementById('faq-id').value = faq_id;
    document.getElementById('faq-q').value = faq_question;
    document.getElementById('faq-r').value = faq_reponse;
    // Révèle le bouton "Annuler" pour sortir du mode édition sans modifier
    document.getElementById('faq-cancel').style.display = 'block';
    // Scroll vers le formulaire pour que l'utilisateur le voit directement
    document.getElementById('faq-form').scrollIntoView({behavior:'smooth', block:'center'});
}

// Réinitialise le formulaire FAQ en mode "ajout" (état initial).
// Réappelé par le bouton "Annuler" en mode édition.
function resetFaqForm() {
    document.getElementById('faq-form-title').textContent = '+ Ajouter une question';
    document.getElementById('faq-action').value = 'add_faq';
    document.getElementById('faq-id').value = '';
    document.getElementById('faq-q').value = '';
    document.getElementById('faq-r').value = '';
    document.getElementById('faq-cancel').style.display = 'none';
}
</script>

<?php endif; ?>

</div>
</main>

<!-- Responsive : grilles 2 colonnes → 1 colonne, tables réduites sur mobile -->
<style>
@media (max-width: 768px) {
    div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
    table { font-size: 0.8rem !important; }
    td, th { padding: 8px 10px !important; }
}
</style>
