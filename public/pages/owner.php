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
// Si l'onglet reçu en GET n'est pas dans la liste autorisée, on utilise 'dashboard' par défaut
$active_tab = in_array($owner_tab ?? '', $valid_tabs) ? $owner_tab : 'dashboard';

// ID de l'owner connecté : utilisé pour protéger sa propre ligne contre les auto-actions
$owner_user_id = (int)$_SESSION['user']['id']; // cast en int pour les comparaisons strictes

// Définition des onglets : slug → [emoji, libellé] pour la barre de navigation
$tab_definitions = [
    'dashboard'    => ['📊', 'Tableau de bord'],  // vue générale avec les chiffres clés
    'users'        => ['👥', 'Utilisateurs'],       // gestion de tous les comptes membres
    'activities'   => ['🎯', 'Activités'],          // gestion de toutes les activités
    'admins'       => ['👑', 'Administrateurs'],    // nommer/révoquer des admins, transférer la propriété
    'contact'      => ['✉️', 'Messages contact'],   // boite de réception du formulaire de contact
    'contenu'      => ['📝', 'Contenu du site'],    // édition FAQ, CGU, mentions légales
    'signalements' => ['🚩', 'Signalements'],       // modération des signalements d'utilisateurs
];

// ── Données chargées à la demande selon l'onglet actif ─────────────────────
// Charger ces données uniquement si l'onglet correspondant est ouvert
// évite des requêtes SQL inutiles pour les onglets non consultés.

// Données de l'onglet "signalements" : liste vide et compteur à zéro par défaut
$reports_list          = [];
$reports_pending_count = 0;
if ($active_tab === 'signalements') { // charge uniquement quand l'onglet signalements est ouvert
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
    ")->fetchAll(); // fetchAll retourne toutes les lignes du résultat sous forme de tableau
    // Compteur de signalements en attente affiché dans le titre de l'onglet
    $reports_pending_count = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'en_attente'")->fetchColumn();
} // fin du chargement conditionnel des signalements

// Données de l'onglet "contenu" : FAQ, CGU, Mentions légales initialisées à vide
$faq_items_owner   = [];
$cgu_owner         = '';   // texte des CGU actuelles
$cgu_version_owner = '';   // numéro de version des CGU (ex : "v1.1")
$mentions_owner    = '';   // texte des mentions légales actuelles
if ($active_tab === 'contenu') { // charge uniquement quand l'onglet contenu est ouvert
    // Toutes les questions FAQ, triées par ID (ordre de création)
    $faq_items_owner   = $pdo->query("SELECT * FROM faq ORDER BY idfaq ASC")->fetchAll();
    // La version la plus récente des CGU (ORDER BY DESC LIMIT 1)
    $cgu_row           = $pdo->query("SELECT contenu, version FROM cgu ORDER BY idcgu DESC LIMIT 1")->fetch();
    $cgu_owner         = $cgu_row['contenu'] ?? '';  // texte des CGU ou chaîne vide si table vide
    $cgu_version_owner = $cgu_row['version'] ?? '';  // numéro de version ou chaîne vide si table vide
    // La version la plus récente des mentions légales
    $mentions_row      = $pdo->query("SELECT contenu FROM mentions ORDER BY idmentions DESC LIMIT 1")->fetch();
    $mentions_owner    = $mentions_row['contenu'] ?? ''; // texte des mentions ou chaîne vide si table vide
} // fin du chargement conditionnel du contenu
?>

<!-- ── EN-TÊTE OWNER ──────────────────────────────────────────────────────────
     Gradient orange (vs navy pour l'admin) pour signifier le niveau hiérarchique supérieur.
     Affiche le badge de rôle owner + prénom/nom de l'owner connecté. -->
<div style="background:linear-gradient(135deg,var(--orange) 0%,#c96a10 100%);padding:28px 0;"> <!-- bandeau d'en-tête orange dégradé, spécifique au super-admin -->
    <!-- Conteneur flex : titre à gauche, badge + nom à droite, passe en colonne sur mobile -->
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <!-- Label "Super-Admin" en petites majuscules au-dessus du titre principal -->
            <p style="color:rgba(255,255,255,0.65);font-size:0.8rem;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;">Super-Admin</p>
            <h1 style="color:white;margin:0;font-size:1.6rem;">Panel Super-Admin</h1>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <!-- role_badge() génère le badge HTML coloré selon le rôle -->
            <?= role_badge($_SESSION['user']['role']) ?>
            <!-- Prénom et nom de l'owner connecté, protégés contre les injections XSS -->
            <span style="color:rgba(255,255,255,0.7);font-size:0.9rem;"><?= htmlspecialchars($_SESSION['user']['prenom'].' '.$_SESSION['user']['nom']) ?></span>
        </div>
    </div>
</div>

<!-- ── NAVIGATION PAR ONGLETS ────────────────────────────────────────────────
     Barre d'onglets sous le header : l'onglet actif a une bordure orange et couleur navy,
     les autres sont gris. overflow-x:auto permet de scroller sur mobile. -->
<div style="background:white;border-bottom:2px solid var(--gray-200);margin-bottom:32px;">
    <!-- Flex sans retour à la ligne : défilement horizontal sur petits écrans -->
    <div class="container" style="display:flex;gap:0;overflow-x:auto;">
        <?php foreach ($tab_definitions as $tab_slug => [$tab_icon, $tab_label]): // boucle sur les 7 onglets ?>
        <!-- Lien d'onglet : bordure inférieure orange + couleur navy si actif, gris sinon -->
        <a href="/sharetime/public/?page=owner&tab=<?= $tab_slug ?>"
           style="padding:14px 20px;font-weight:600;font-size:0.9rem;text-decoration:none;white-space:nowrap;
                  display:inline-flex;align-items:center;gap:6px;transition:all 0.15s;
                  border-bottom:3px solid <?= $active_tab === $tab_slug ? 'var(--orange)' : 'transparent' ?>;
                  color:<?= $active_tab === $tab_slug ? 'var(--navy)' : 'var(--gray-500)' ?>;">
            <?= $tab_icon ?> <?= $tab_label ?> <!-- icône et libellé de l'onglet -->
        </a>
        <?php endforeach; // fin de la boucle sur les onglets ?>
    </div>
</div>

<main> <!-- zone de contenu principal de la page -->
<div class="container" style="padding-bottom:48px;"> <!-- conteneur centré avec espacement bas avant le footer -->

<!-- Message flash de succès après une action (ban, set_role, transfer, delete…) -->
<?php if ($flash): // $flash est défini uniquement après une action POST réussie ?>
<div style="background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0;border-radius:10px;padding:12px 18px;margin-bottom:24px;font-weight:600;">
    <?= htmlspecialchars($flash) ?> <!-- affiche le message de retour sans risque XSS -->
</div>
<?php endif; ?>

<?php /* ══════════════════════════════════════════════════════════════
   ONGLET DASHBOARD : statistiques globales + aperçu des derniers membres/activités.
   Même données que admin.php, mais les liens "Tout voir" pointent vers
   les onglets du panel owner (?page=owner&tab=…) et non vers les pages admin séparées.
══════════════════════════════════════════════════════════════════ */ ?>
<?php if ($active_tab === 'dashboard'): // premier onglet : tableau de bord ?>

    <!-- Grille de cards de statistiques globales (auto-fit pour s'adapter à l'écran) -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:36px;">
        <?php
        // Définition des 5 indicateurs : [libellé, valeur numérique, emoji, fond coloré, couleur texte]
        $stats_card_definitions = [
            ['Membres',      $admin_stats['membres'],      '👤', '#EBF0F8', 'var(--navy)'],    // total des membres inscrits
            ['Admins',       $admin_stats['admins'],       '🛡️', '#FEF3E2', 'var(--orange)'], // total des administrateurs
            ['Activités',    $admin_stats['activites'],    '🎯', '#D1FAE5', '#065F46'],        // total des activités créées
            ['Inscriptions', $admin_stats['inscriptions'], '✅', '#EDE9FE', '#7C3AED'],        // total des participations
            ['Suspendus',    $admin_stats['suspendus'],    '🚫', '#FEE2E2', '#DC2626'],        // total des comptes bannis
        ];
        foreach ($stats_card_definitions as [$card_label, $card_value, $card_icon, $card_bg_color, $card_text_color]): // boucle sur les 5 indicateurs
        ?>
        <!-- Card individuelle : bordure grise, coins arrondis, padding interne -->
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;padding:20px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <!-- Libellé de l'indicateur en majuscules + espacées (style "kpi label") -->
                <span style="font-size:0.75rem;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;"><?= $card_label ?></span>
                <!-- Icône dans un fond coloré spécifique à chaque indicateur -->
                <span style="font-size:1.3rem;background:<?= $card_bg_color ?>;padding:6px;border-radius:8px;"><?= $card_icon ?></span>
            </div>
            <!-- Valeur numérique en grand, colorée selon l'indicateur -->
            <p style="font-size:2rem;font-weight:800;color:<?= $card_text_color ?>;margin:0;"><?= $card_value ?></p>
        </div>
        <?php endforeach; // fin boucle des indicateurs ?>
    </div>

    <!-- Deux colonnes côte à côte : derniers membres inscrits + dernières activités créées -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

        <!-- Derniers membres inscrits (5 derniers récupérés par le routing) -->
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
            <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;">
                <h2 style="margin:0;font-size:1rem;color:var(--navy);">Derniers membres</h2>
                <!-- "Tout voir" pointe vers l'onglet users du panel owner -->
                <a href="/sharetime/public/?page=owner&tab=users" style="font-size:0.82rem;color:var(--orange);font-weight:600;text-decoration:none;">Tout voir →</a>
            </div>
            <?php foreach ($admin_recent_users as $recent_user): // itère sur les 5 derniers membres ?>
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-50);display:flex;align-items:center;justify-content:space-between;gap:10px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <!-- Avatar initiale sur fond gradient navy -->
                    <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.9rem;flex-shrink:0;">
                        <?= strtoupper(mb_substr($recent_user['prenom'],0,1)) ?> <!-- première lettre du prénom en majuscule -->
                    </div>
                    <div>
                        <!-- Prénom + nom du membre protégés contre les injections XSS -->
                        <p style="margin:0;font-size:0.88rem;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($recent_user['prenom'].' '.$recent_user['nom']) ?></p>
                        <!-- Email du membre en gris, plus petit -->
                        <p style="margin:0;font-size:0.78rem;color:var(--gray-500);"><?= htmlspecialchars($recent_user['email']) ?></p>
                    </div>
                </div>
                <!-- Badge rôle + état banni si applicable -->
                <?= role_badge($recent_user['role'], !empty($recent_user['is_banned'])) ?>
            </div>
            <?php endforeach; // fin boucle des derniers membres ?>
        </div>

        <!-- Dernières activités créées (5 dernières récupérées par le routing) -->
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
            <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;">
                <h2 style="margin:0;font-size:1rem;color:var(--navy);">Dernières activités</h2>
                <!-- "Tout voir" pointe vers l'onglet activities du panel owner -->
                <a href="/sharetime/public/?page=owner&tab=activities" style="font-size:0.82rem;color:var(--orange);font-weight:600;text-decoration:none;">Tout voir →</a>
            </div>
            <?php foreach ($admin_recent_activities as $recent_activity): // itère sur les 5 dernières activités
                // Formatage de la date de début pour affichage compact dans la liste
                $start_datetime = new DateTime($recent_activity['start_time']); // conversion en objet DateTime
                // Tables de correspondance statut → couleurs du badge (fond et texte)
                $status_badge_colors = ['active'=>['#D1FAE5','#065F46'],'en_cours'=>['#FEF3C7','#92400E'],'annulee'=>['#FEE2E2','#DC2626'],'terminee'=>['#F3F4F6','#6B7280']];
                // Libellés lisibles des statuts pour l'affichage utilisateur
                $status_badge_labels = ['active'=>'À venir','en_cours'=>'En cours','annulee'=>'Annulée','terminee'=>'Terminée'];
                // Déstructuration du tableau de couleurs (fond + texte) avec fallback gris si statut inconnu
                [$status_badge_bg, $status_badge_color] = $status_badge_colors[$recent_activity['status']] ?? ['#F3F4F6','#6B7280'];
            ?>
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-50);display:flex;align-items:center;justify-content:space-between;gap:10px;">
                <div>
                    <!-- Titre de l'activité protégé contre XSS -->
                    <p style="margin:0;font-size:0.88rem;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($recent_activity['title']) ?></p>
                    <!-- Ville et date de début formatée -->
                    <p style="margin:0;font-size:0.78rem;color:var(--gray-500);"><?= htmlspecialchars($recent_activity['city']) ?> · <?= $start_datetime->format('d/m/Y') ?></p>
                </div>
                <!-- Badge statut coloré selon l'état actuel de l'activité -->
                <span style="background:<?= $status_badge_bg ?>;color:<?= $status_badge_color ?>;padding:3px 10px;border-radius:99px;font-size:0.75rem;font-weight:600;white-space:nowrap;"><?= $status_badge_labels[$recent_activity['status']] ?? ucfirst($recent_activity['status']) ?></span>
            </div>
            <?php endforeach; // fin boucle des dernières activités ?>
        </div>
    </div>

<?php /* ══════════════════════════════════════════════════════════════
   ONGLET USERS : tableau complet de tous les utilisateurs.
   L'owner peut : ban/unban, set_role, supprimer, transférer la propriété.
   Toutes les actions POST vers page=owner avec type=user + tab=users
   pour que le handler redirige vers le bon onglet après traitement.
══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($active_tab === 'users'): // onglet gestion des utilisateurs ?>

    <!-- Card conteneur du tableau des utilisateurs -->
    <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
        <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);">
            <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                Tous les utilisateurs
                <!-- Compteur total en badge gris pill -->
                <span style="margin-left:8px;background:#F3F4F6;color:#6B7280;font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= count($owner_users) ?></span>
            </h2>
        </div>
        <!-- overflow-x:auto pour scroller horizontalement sur mobile -->
        <div style="overflow-x:auto;"> <!-- défilement horizontal sur mobile pour les tableaux larges -->
            <table style="width:100%;border-collapse:collapse;font-size:0.875rem;"> <!-- tableau pleine largeur sans bordures doublées -->
                <thead> <!-- en-tête fixe du tableau utilisateurs -->
                    <!-- En-têtes des colonnes du tableau utilisateurs -->
                    <tr style="background:#F9FAFB;border-bottom:1px solid var(--gray-200);">
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Utilisateur</th>
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Rôle</th>
                        <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Activités</th>
                        <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Inscriptions</th>
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Depuis</th>
                        <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Actions</th>
                    </tr>
                </thead>
                <tbody> <!-- corps du tableau, une ligne par utilisateur -->
                <?php foreach ($owner_users as $owner_user_row): // itère sur tous les utilisateurs
                    // ID de la ligne courante, casté en int pour les comparaisons strictes
                    $owner_user_row_id = (int)$owner_user_row['idusers'];

                    // true si cette ligne correspond à l'owner connecté (auto-protection)
                    $is_connected_owner = $owner_user_row_id === $owner_user_id;

                    // true si le rôle de cette ligne est 'owner' (il n'y en a qu'un = $owner_user_id)
                    $is_owner_account = $owner_user_row['role'] === 'superadmin';

                    // true si le compte est actuellement suspendu (champ is_banned = 1)
                    $is_user_banned = !empty($owner_user_row['is_banned']);

                    // can_perform_actions = false sur la propre ligne + sur la ligne owner (protection)
                    $can_perform_actions = !$is_connected_owner && !$is_owner_account;
                ?>
                <tr style="border-bottom:1px solid var(--gray-50);">
                    <!-- Utilisateur : avatar initiale + nom complet + email -->
                    <td style="padding:12px 16px;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <!-- Cercle avatar avec la première lettre du prénom en majuscule -->
                            <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.9rem;flex-shrink:0;">
                                <?= strtoupper(mb_substr($owner_user_row['prenom'],0,1)) ?> <!-- initiale du prénom -->
                            </div>
                            <div>
                                <p style="margin:0;font-weight:600;color:var(--gray-900);">
                                    <?= htmlspecialchars($owner_user_row['prenom'].' '.$owner_user_row['nom']) ?>
                                    <!-- Indication discrète "(vous)" sur la propre ligne de l'owner connecté -->
                                    <?php if ($is_connected_owner): ?><span style="font-size:0.72rem;color:var(--gray-400);"> (vous)</span><?php endif; ?>
                                </p>
                                <!-- Email en gris clair, taille réduite -->
                                <p style="margin:0;color:var(--gray-500);font-size:0.78rem;"><?= htmlspecialchars($owner_user_row['email']) ?></p>
                            </div>
                        </div>
                    </td>
                    <!-- Badge rôle coloré + affichage "Banni" si is_banned = true -->
                    <td style="padding:12px 16px;"><?= role_badge($owner_user_row['role'], $is_user_banned) ?></td>
                    <!-- Compteurs issus du JOIN dans User::getAllForAdmin (activités créées) -->
                    <td style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-700);"><?= $owner_user_row['nb_activities'] ?></td>
                    <!-- Compteurs issus du JOIN (inscriptions à des activités d'autres) -->
                    <td style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-700);"><?= $owner_user_row['nb_registrations'] ?></td>
                    <!-- Date d'inscription formatée en jour/mois/année -->
                    <td style="padding:12px 16px;color:var(--gray-500);font-size:0.82rem;"><?= (new DateTime($owner_user_row['date_creation']))->format('d/m/Y') ?></td>
                    <td style="padding:12px 16px;">
                        <?php if ($can_perform_actions): // affiche les boutons seulement si l'action est permise ?>
                        <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;">
                            <!-- Suspendre / Réactiver : type=user + tab=users pour retour correct après POST -->
                            <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"> <!-- jeton CSRF anti-forgerie -->
                                <input type="hidden" name="type" value="user"> <!-- indique au handler que la cible est un utilisateur -->
                                <input type="hidden" name="tab" value="users"> <!-- pour rediriger vers cet onglet après l'action -->
                                <input type="hidden" name="user_id" value="<?= $owner_user_row_id ?>"> <!-- ID de l'utilisateur ciblé -->
                                <!-- action dynamique : 'unban' si déjà banni, 'ban' sinon -->
                                <input type="hidden" name="action" value="<?= $is_user_banned ? 'unban' : 'ban' ?>">
                                <!-- Bouton rouge "Suspendre" ou vert "Réactiver" selon l'état du compte -->
                                <button type="submit"
                                    style="padding:5px 12px;border-radius:6px;border:1.5px solid <?= $is_user_banned ? '#059669' : '#DC2626' ?>;background:white;color:<?= $is_user_banned ? '#059669' : '#DC2626' ?>;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                    onclick="return confirm('<?= $is_user_banned ? 'Réactiver ce compte ?' : 'Suspendre ce compte ?' ?>')"> <!-- confirmation JS avant action irréversible -->
                                    <?= $is_user_banned ? '✓ Réactiver' : '⊘ Suspendre' ?>
                                </button>
                            </form>
                            <!-- Changer le rôle (membre ↔ admin) : masqué si banni pour cohérence -->
                            <?php if (!$is_user_banned): // masque le sélecteur de rôle pour les comptes bannis ?>
                            <form method="POST" action="/sharetime/public/?page=owner" style="display:flex;gap:4px;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="type" value="user">
                                <input type="hidden" name="tab" value="users">
                                <input type="hidden" name="user_id" value="<?= $owner_user_row_id ?>">
                                <input type="hidden" name="action" value="set_role"> <!-- action de changement de rôle -->
                                <!-- Menu déroulant avec le rôle actuel pré-sélectionné -->
                                <select name="role" style="padding:5px 8px;border-radius:6px;border:1.5px solid var(--gray-200);font-size:0.78rem;color:var(--gray-700);">
                                    <option value="utilisateur" <?= $owner_user_row['role']==='utilisateur'?'selected':'' ?>>Membre</option>
                                    <option value="admin"       <?= $owner_user_row['role']==='admin'?'selected':'' ?>>Admin</option>
                                </select>
                                <button type="submit" style="padding:5px 10px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-700);font-size:0.78rem;font-weight:600;cursor:pointer;">OK</button>
                            </form>
                            <?php endif; // fin condition compte non banni ?>
                            <!-- Suppression définitive du compte (supprime aussi ses activités, inscriptions…) -->
                            <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="type" value="user">
                                <input type="hidden" name="tab" value="users">
                                <input type="hidden" name="user_id" value="<?= $owner_user_row_id ?>">
                                <input type="hidden" name="action" value="delete"> <!-- action de suppression définitive -->
                                <button type="submit"
                                    style="padding:5px 10px;border-radius:6px;border:1.5px solid #DC2626;background:#DC2626;color:white;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                    onclick="return confirm('Supprimer définitivement ce compte ?')"> <!-- confirmation obligatoire car action irréversible -->
                                    🗑
                                </button>
                            </form>
                        </div>
                        <?php else: // aucune action possible sur la ligne protégée ?>
                        <!-- Aucune action sur la propre ligne de l'owner ni sur son compte protégé -->
                        <span style="color:var(--gray-300);font-size:0.8rem;font-style:italic;"><?= $is_connected_owner ? 'Vous' : 'Protégé' ?></span>
                        <?php endif; // fin condition can_perform_actions ?>
                    </td>
                </tr>
                <?php endforeach; // fin boucle des utilisateurs ?>
                </tbody> <!-- fin du corps du tableau utilisateurs -->
            </table>
        </div> <!-- fin du conteneur défilable horizontalement -->
    </div> <!-- fin de la card tableau utilisateurs -->

<?php /* ══════════════════════════════════════════════════════════════
   ONGLET ACTIVITIES : tableau complet de toutes les activités.
   L'owner peut changer le statut et supprimer. Même structure que admin_activities.php
   mais les actions POST sont envoyées vers page=owner avec type=activity + tab=activities.
══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($active_tab === 'activities'): // onglet gestion des activités ?>

    <!-- Card conteneur du tableau des activités -->
    <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
        <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);">
            <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                Toutes les activités
                <!-- Compteur total des activités en badge gris pill -->
                <span style="margin-left:8px;background:#F3F4F6;color:#6B7280;font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= count($admin_activities_list) ?></span>
            </h2>
        </div>
        <?php if (empty($admin_activities_list)): // état vide : aucune activité créée ?>
        <!-- État vide : aucune activité créée sur la plateforme -->
        <div style="padding:48px;text-align:center;color:var(--gray-400);">
            <p style="font-size:2rem;margin-bottom:8px;">🎯</p><p>Aucune activité.</p>
        </div>
        <?php else: // il y a des activités à afficher ?>
        <div style="overflow-x:auto;"> <!-- défilement horizontal sur mobile -->
            <table style="width:100%;border-collapse:collapse;font-size:0.875rem;"> <!-- tableau pleine largeur sans espacement entre cellules -->
                <thead> <!-- en-tête fixe du tableau des activités -->
                    <!-- En-têtes des colonnes du tableau des activités -->
                    <tr style="background:#F9FAFB;border-bottom:1px solid var(--gray-200);">
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Activité</th>
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Créateur</th>
                        <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Participants</th>
                        <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Date</th>
                        <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Statut</th>
                        <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Actions</th>
                    </tr>
                </thead>
                <tbody> <!-- corps du tableau, une ligne par activité -->
                <?php
                // Tables de correspondance statut → couleurs des badges, définies hors de la boucle pour la performance
                $status_badge_colors = ['active'=>['#D1FAE5','#065F46'],'en_cours'=>['#FEF3C7','#92400E'],'annulee'=>['#FEE2E2','#DC2626'],'terminee'=>['#F3F4F6','#6B7280']];
                // Libellés lisibles en français pour chaque statut d'activité
                $status_badge_labels = ['active'=>'À venir','en_cours'=>'En cours','annulee'=>'Annulée','terminee'=>'Terminée'];
                foreach ($admin_activities_list as $activity_row): // itère sur toutes les activités
                    // Formatage de la date de début pour la colonne "Date"
                    $start_datetime = new DateTime($activity_row['start_time']); // conversion en objet DateTime
                    // Couleurs du badge statut avec fallback gris pour les statuts inconnus
                    [$status_badge_bg, $status_badge_color] = $status_badge_colors[$activity_row['status']] ?? ['#F3F4F6','#6B7280'];
                ?>
                <tr style="border-bottom:1px solid var(--gray-50);">
                    <!-- Titre tronqué par ellipsis si trop long + ville en gris dessous -->
                    <td style="padding:12px 16px;max-width:200px;">
                        <p style="margin:0;font-weight:600;color:var(--gray-900);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($activity_row['title']) ?></p>
                        <p style="margin:0;color:var(--gray-500);font-size:0.78rem;"><?= htmlspecialchars($activity_row['city']) ?></p>
                    </td>
                    <!-- Prénom + Nom du créateur de l'activité (issu du JOIN avec users) -->
                    <td style="padding:12px 16px;color:var(--gray-700);"><?= htmlspecialchars($activity_row['prenom'].' '.$activity_row['nom']) ?></td>
                    <!-- Inscrits actuels / maximum de participants autorisés -->
                    <td style="padding:12px 16px;text-align:center;">
                        <span style="font-weight:600;"><?= (int)$activity_row['nb_inscrits'] ?></span><span style="color:var(--gray-400);">/</span><span style="color:var(--gray-500);"><?= (int)$activity_row['max_participants'] ?></span>
                    </td>
                    <!-- Date de début formatée en jour/mois/année -->
                    <td style="padding:12px 16px;color:var(--gray-600);font-size:0.82rem;white-space:nowrap;"><?= $start_datetime->format('d/m/Y') ?></td>
                    <!-- Badge statut coloré selon l'état de l'activité -->
                    <td style="padding:12px 16px;text-align:center;">
                        <span style="background:<?= $status_badge_bg ?>;color:<?= $status_badge_color ?>;padding:3px 10px;border-radius:99px;font-size:0.75rem;font-weight:600;"><?= $status_badge_labels[$activity_row['status']] ?? ucfirst($activity_row['status']) ?></span>
                    </td>
                    <td style="padding:12px 16px;">
                        <div style="display:flex;gap:6px;justify-content:flex-end;align-items:center;flex-wrap:wrap;">
                            <!-- Changer le statut : type=activity + tab=activities pour retour correct après POST -->
                            <form method="POST" action="/sharetime/public/?page=owner" style="display:flex;gap:4px;align-items:center;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="type" value="activity"> <!-- cible : une activité -->
                                <input type="hidden" name="tab" value="activities"> <!-- onglet de retour après action -->
                                <input type="hidden" name="activity_id" value="<?= (int)$activity_row['idactivities'] ?>"> <!-- ID de l'activité ciblée -->
                                <input type="hidden" name="action" value="set_status"> <!-- action : changer le statut -->
                                <!-- Menu déroulant des statuts avec le statut actuel pré-sélectionné -->
                                <select name="status" style="padding:5px 8px;border-radius:6px;border:1.5px solid var(--gray-200);font-size:0.78rem;color:var(--gray-700);">
                                    <option value="active"   <?= $activity_row['status']==='active'   ?'selected':'' ?>>À venir</option>
                                    <option value="en_cours" <?= $activity_row['status']==='en_cours' ?'selected':'' ?>>En cours</option>
                                    <option value="annulee"  <?= $activity_row['status']==='annulee'  ?'selected':'' ?>>Annulée</option>
                                    <option value="terminee" <?= $activity_row['status']==='terminee' ?'selected':'' ?>>Terminée</option>
                                </select>
                                <button type="submit" style="padding:5px 10px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-700);font-size:0.78rem;font-weight:600;cursor:pointer;">OK</button>
                            </form>
                            <!-- Lien "Voir" : ouvre la page de détail publique de l'activité dans le même onglet -->
                            <a href="/sharetime/public/?page=detail&id=<?= (int)$activity_row['idactivities'] ?>"
                               style="padding:5px 10px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-700);font-size:0.78rem;font-weight:600;text-decoration:none;">👁</a>
                            <!-- Suppression définitive de l'activité et de ses inscriptions (confirmation JS requise) -->
                            <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="type" value="activity">
                                <input type="hidden" name="tab" value="activities">
                                <input type="hidden" name="activity_id" value="<?= (int)$activity_row['idactivities'] ?>">
                                <input type="hidden" name="action" value="delete"> <!-- suppression définitive avec cascade -->
                                <button type="submit"
                                    style="padding:5px 10px;border-radius:6px;border:1.5px solid #DC2626;background:#DC2626;color:white;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                    onclick="return confirm('Supprimer cette activité et ses inscriptions ?')">🗑</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; // fin boucle des activités ?>
                </tbody> <!-- fin du corps du tableau des activités -->
            </table>
        </div> <!-- fin du conteneur défilable horizontalement -->
        <?php endif; // fin condition liste vide/non vide ?>
    </div> <!-- fin de la card tableau des activités -->

<?php /* ══════════════════════════════════════════════════════════════
   ONGLET ADMINS : gestion des administrateurs.
   Deux sections distinctes :
   1. Administrateurs actuels → révoquer ou transférer la propriété
   2. Nommer un administrateur → membres actifs et non bannis disponibles
══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($active_tab === 'admins'): // onglet gestion des administrateurs ?>

    <?php
    // Filtre la liste $owner_users en deux sous-listes distinctes :
    // admins actuels (role='admin') et membres éligibles (role='utilisateur', non bannis)
    $admin_users_list    = array_values(array_filter($owner_users, fn($u) => $u['role'] === 'admin')); // extrait les admins
    $eligible_member_list = array_values(array_filter($owner_users, fn($u) => $u['role'] === 'utilisateur' && empty($u['is_banned']))); // membres actifs non bannis
    ?>

    <!-- Rappel des règles importantes pour éviter les mauvaises manipulations -->
    <div style="background:#FEF3E2;border:1.5px solid rgba(232,129,26,0.3);border-radius:12px;padding:16px 20px;margin-bottom:28px;display:flex;align-items:center;gap:14px;">
        <span style="font-size:1.5rem;">👑</span>
        <p style="margin:0;font-size:0.88rem;color:var(--gray-700);">
            <strong>Seul le super-admin</strong> peut nommer ou révoquer des administrateurs et transférer ses prérogatives.
            Le transfert est <strong>irréversible</strong> sans intervention du nouveau super-admin.
        </p>
    </div>

    <!-- ── Section 1 : Administrateurs actuels ──────────────────────────────── -->
    <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;margin-bottom:24px;">
        <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);">
            <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                Administrateurs actuels
                <!-- Compteur des admins en badge bleu navy -->
                <span style="margin-left:8px;background:#EBF0F8;color:var(--navy);font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= count($admin_users_list) ?></span>
            </h2>
        </div>
        <?php if (empty($admin_users_list)): // aucun admin nommé pour l'instant ?>
        <p style="padding:24px 20px;color:var(--gray-500);margin:0;">Aucun administrateur pour le moment.</p>
        <?php else: // affiche le tableau des admins existants ?>
        <table style="width:100%;border-collapse:collapse;font-size:0.875rem;"> <!-- tableau des admins existants, pleine largeur -->
            <thead> <!-- en-tête fixe du tableau des administrateurs -->
                <!-- En-têtes du tableau des administrateurs -->
                <tr style="background:#F9FAFB;border-bottom:1px solid var(--gray-200);">
                    <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Administrateur</th>
                    <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Email</th>
                    <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Actions</th>
                </tr>
            </thead>
            <tbody> <!-- corps du tableau, une ligne par administrateur -->
            <?php foreach ($admin_users_list as $admin_user_row): // itère sur les admins existants ?>
            <tr style="border-bottom:1px solid var(--gray-50);">
                <td style="padding:12px 16px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <!-- Avatar navy (admins nommés = même niveau que les admins classiques) -->
                        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.9rem;flex-shrink:0;">
                            <?= strtoupper(mb_substr($admin_user_row['prenom'],0,1)) ?> <!-- initiale du prénom en majuscule -->
                        </div>
                        <!-- Prénom + nom de l'admin protégé contre XSS -->
                        <p style="margin:0;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($admin_user_row['prenom'].' '.$admin_user_row['nom']) ?></p>
                    </div>
                </td>
                <!-- Email de l'admin en gris -->
                <td style="padding:12px 16px;color:var(--gray-500);font-size:0.85rem;"><?= htmlspecialchars($admin_user_row['email']) ?></td>
                <td style="padding:12px 16px;">
                    <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;">
                        <!-- Révoquer le rôle admin → rétrograde en membre (role=utilisateur) -->
                        <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="type" value="user">
                            <input type="hidden" name="tab" value="admins"> <!-- retour vers l'onglet admins -->
                            <input type="hidden" name="user_id" value="<?= (int)$admin_user_row['idusers'] ?>">
                            <input type="hidden" name="action" value="set_role"> <!-- changement de rôle -->
                            <input type="hidden" name="role" value="utilisateur"> <!-- rétrogradation en simple membre -->
                            <!-- addslashes dans le confirm() pour échapper les apostrophes dans les prénoms -->
                            <button type="submit"
                                style="padding:5px 12px;border-radius:6px;border:1.5px solid #DC2626;background:white;color:#DC2626;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                onclick="return confirm('Révoquer le rôle admin de <?= htmlspecialchars(addslashes($admin_user_row['prenom'].' '.$admin_user_row['nom'])) ?> ?')"> <!-- addslashes évite de casser le confirm() JS -->
                                ⊘ Révoquer
                            </button>
                        </form>
                        <!-- Transférer la propriété : action irréversible → double confirmation JS -->
                        <form method="POST" action="/sharetime/public/?page=owner" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="type" value="user">
                            <input type="hidden" name="tab" value="admins">
                            <input type="hidden" name="user_id" value="<?= (int)$admin_user_row['idusers'] ?>">
                            <input type="hidden" name="action" value="transfer_ownership"> <!-- action spéciale owner uniquement -->
                            <button type="submit"
                                style="padding:5px 12px;border-radius:6px;border:1.5px solid var(--orange);background:white;color:var(--orange);font-size:0.78rem;font-weight:600;cursor:pointer;"
                                onclick="return confirm('Transférer les prérogatives Super-Admin à <?= htmlspecialchars(addslashes($admin_user_row['prenom'].' '.$admin_user_row['nom'])) ?> ?\n\nVous deviendrez administrateur. Action irréversible.')"> <!-- \n crée un saut de ligne dans la popup JS -->
                                👑 Transférer
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; // fin boucle des admins ?>
            </tbody> <!-- fin corps du tableau des admins -->
        </table>
        <?php endif; // fin condition liste admins vide/non vide ?>
    </div> <!-- fin de la card "Administrateurs actuels" -->

    <!-- ── Section 2 : Nommer un administrateur ──────────────────────────────── -->
    <!-- Liste filtrée : membres actifs (role='utilisateur') non bannis uniquement -->
    <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
        <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);">
            <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                Nommer un administrateur
                <!-- Compteur des membres éligibles en badge gris -->
                <span style="margin-left:8px;background:#F3F4F6;color:#6B7280;font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= count($eligible_member_list) ?> membres</span>
            </h2>
        </div>
        <?php if (empty($eligible_member_list)): // aucun membre disponible pour être nommé ?>
        <p style="padding:24px 20px;color:var(--gray-500);margin:0;">Aucun membre disponible.</p>
        <?php else: // affiche la liste des membres éligibles ?>
        <table style="width:100%;border-collapse:collapse;font-size:0.875rem;"> <!-- tableau des membres pouvant être nommés admins -->
            <thead> <!-- en-tête du tableau de nomination -->
                <!-- En-têtes du tableau de nomination -->
                <tr style="background:#F9FAFB;border-bottom:1px solid var(--gray-200);">
                    <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Membre</th>
                    <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Email</th>
                    <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Activités</th>
                    <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;">Actions</th>
                </tr>
            </thead>
            <tbody> <!-- corps du tableau, une ligne par membre éligible -->
            <?php foreach ($eligible_member_list as $member_row): // itère sur les membres éligibles ?>
            <tr style="border-bottom:1px solid var(--gray-50);">
                <td style="padding:12px 16px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <!-- Avatar gris (membres non encore nommés, à distinguer des admins en navy) -->
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--gray-200);display:flex;align-items:center;justify-content:center;color:var(--gray-600);font-weight:700;font-size:0.9rem;flex-shrink:0;">
                            <?= strtoupper(mb_substr($member_row['prenom'],0,1)) ?> <!-- initiale du prénom -->
                        </div>
                        <!-- Prénom + nom du membre protégé contre XSS -->
                        <p style="margin:0;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($member_row['prenom'].' '.$member_row['nom']) ?></p>
                    </div>
                </td>
                <!-- Email du membre en gris -->
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
                        <input type="hidden" name="role" value="admin"> <!-- promotion en administrateur -->
                        <button type="submit"
                            style="padding:5px 12px;border-radius:6px;border:1.5px solid #059669;background:white;color:#059669;font-size:0.78rem;font-weight:600;cursor:pointer;"
                            onclick="return confirm('Nommer <?= htmlspecialchars(addslashes($member_row['prenom'].' '.$member_row['nom'])) ?> administrateur ?')"> <!-- addslashes protège les apostrophes dans le confirm() -->
                            ✓ Nommer admin
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; // fin boucle des membres éligibles ?>
            </tbody> <!-- fin corps du tableau des membres éligibles -->
        </table>
        <?php endif; // fin condition liste membres vide/non vide ?>
    </div> <!-- fin de la card "Nommer un administrateur" -->

    <!-- ── Section 3 : Transférer la propriété ──────────────────────────────── -->
    <?php
    // Tous les utilisateurs non-owner et non-bannis sont éligibles au transfert
    $transfer_eligible_list = array_values(array_filter($owner_users, fn($u) =>
        $u['role'] !== 'superadmin' && empty($u['is_banned'])
    ));
    ?>
    <div style="background:white;border:2px solid #DC2626;border-radius:14px;overflow:hidden;margin-top:24px;">
        <div style="padding:18px 20px;border-bottom:1px solid #FEE2E2;background:#FFF5F5;display:flex;align-items:center;gap:12px;">
            <span style="font-size:1.4rem;">⚠️</span>
            <div>
                <h2 style="margin:0 0 2px;font-size:1rem;color:#DC2626;">Transférer les prérogatives Super-Admin</h2>
                <p style="margin:0;font-size:0.82rem;color:#991B1B;">Action irréversible — vous perdrez immédiatement vos prérogatives Super-Admin.</p>
            </div>
        </div>
        <div style="padding:20px;">
            <?php if (empty($transfer_eligible_list)): ?>
            <p style="color:var(--gray-500);margin:0;">Aucun utilisateur disponible pour le transfert.</p>
            <?php else: ?>
            <form method="POST" action="/sharetime/public/?page=owner" id="transfer-ownership-form">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="type" value="user">
                <input type="hidden" name="tab" value="admins">
                <input type="hidden" name="action" value="transfer_ownership">
                <input type="hidden" name="user_id" id="transfer-user-id" value="">
                <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                    <div style="flex:1;min-width:200px;">
                        <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:6px;font-size:0.88rem;">Choisir le nouveau Super-Admin</label>
                        <select id="transfer-select" style="width:100%;padding:10px 14px;border:1.5px solid #FECACA;border-radius:8px;font-size:0.9rem;font-family:inherit;color:var(--gray-700);">
                            <option value="">-- Sélectionner un utilisateur --</option>
                            <?php foreach ($transfer_eligible_list as $t_user): ?>
                            <option value="<?= (int)$t_user['idusers'] ?>"
                                data-name="<?= htmlspecialchars($t_user['prenom'].' '.$t_user['nom']) ?>"
                                data-role="<?= htmlspecialchars($t_user['role']) ?>">
                                <?= htmlspecialchars($t_user['prenom'].' '.$t_user['nom']) ?>
                                (<?= $t_user['role'] === 'admin' ? 'Admin' : 'Membre' ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" onclick="openTransferModal()"
                        style="padding:10px 20px;border-radius:8px;border:2px solid #DC2626;background:#DC2626;color:white;font-size:0.9rem;font-weight:700;cursor:pointer;white-space:nowrap;">
                        👑 Transférer
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de confirmation du transfert de propriété -->
    <div id="transfer-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:white;border-radius:16px;padding:32px;max-width:460px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="text-align:center;margin-bottom:20px;">
                <div style="font-size:3rem;margin-bottom:12px;">⚠️</div>
                <h2 style="color:#DC2626;margin:0 0 8px;font-size:1.3rem;">Confirmer le transfert</h2>
                <p style="color:var(--gray-600);margin:0;font-size:0.95rem;">Vous êtes sur le point de transférer vos prérogatives <strong>Super-Admin</strong> à :</p>
            </div>
            <div style="background:#FFF5F5;border:1.5px solid #FECACA;border-radius:10px;padding:14px 18px;margin-bottom:20px;text-align:center;">
                <strong id="transfer-target-name" style="font-size:1.1rem;color:var(--gray-900);"></strong>
            </div>
            <div style="background:#FEF3E2;border-left:4px solid var(--orange);border-radius:6px;padding:12px 16px;margin-bottom:24px;">
                <p style="margin:0;font-size:0.88rem;color:#92400E;line-height:1.5;">
                    <strong>Conséquences immédiates :</strong><br>
                    • Vous perdrez vos prérogatives Super-Admin<br>
                    • Vous serez rétrogradé au rang d'Administrateur<br>
                    • Cette action est <strong>irréversible</strong> sans l'accord du nouveau Super-Admin
                </p>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="button" onclick="closeTransferModal()"
                    style="flex:1;padding:12px;border-radius:8px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-700);font-size:0.9rem;font-weight:600;cursor:pointer;">
                    Annuler
                </button>
                <button type="button" onclick="confirmTransfer()"
                    style="flex:1;padding:12px;border-radius:8px;border:none;background:#DC2626;color:white;font-size:0.9rem;font-weight:700;cursor:pointer;">
                    Oui, transférer maintenant
                </button>
            </div>
        </div>
    </div>

    <script>
    function openTransferModal() {
        var select = document.getElementById('transfer-select');
        var selectedOption = select.options[select.selectedIndex];
        if (!select.value) {
            alert('Veuillez sélectionner un utilisateur avant de transférer.');
            return;
        }
        document.getElementById('transfer-target-name').textContent = selectedOption.dataset.name;
        document.getElementById('transfer-user-id').value = select.value;
        var modal = document.getElementById('transfer-modal');
        modal.style.display = 'flex';
    }
    function closeTransferModal() {
        document.getElementById('transfer-modal').style.display = 'none';
        document.getElementById('transfer-user-id').value = '';
    }
    function confirmTransfer() {
        document.getElementById('transfer-ownership-form').submit();
    }
    // Fermer le modal en cliquant sur l'arrière-plan
    document.getElementById('transfer-modal').addEventListener('click', function(e) {
        if (e.target === this) closeTransferModal();
    });
    </script>

<?php /* ══════════════════════════════════════════════════════════════
   ONGLET CONTACT : messages reçus via le formulaire de contact.
   Même données que admin_contact.php mais intégrées dans le panel owner.
   Actions : mark_read, mark_unread, mark_all_read, delete.
══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($active_tab === 'contact'): // onglet boite de réception du formulaire de contact ?>

    <!-- En-tête : titre + compteur + bouton "Tout marquer lu" si messages non lus -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
        <div>
            <h2 style="color:var(--navy); margin:0 0 4px;">Messages de contact</h2>
            <p style="color:var(--gray-500); font-size:0.9rem; margin:0;">
                <!-- Compteur avec pluralisation : "message" ou "messages" -->
                <?= count($contact_messages ?? []) ?> message<?= count($contact_messages ?? []) > 1 ? 's' : '' ?>
                <?php if (($contact_unread ?? 0) > 0): // affiche le nombre de non lus seulement s'il y en a ?>
                    — <span style="color:var(--orange); font-weight:600;"><?= $contact_unread ?> non lu<?= $contact_unread > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </p>
        </div>
        <!-- "Tout marquer lu" : visible uniquement s'il reste des messages non lus -->
        <?php if (($contact_unread ?? 0) > 0): // bouton masqué si tout est déjà lu ?>
        <form method="POST" action="/sharetime/public/?page=owner">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="contact_action" value="mark_all_read"> <!-- action : marquer tous les messages comme lus -->
            <input type="hidden" name="msg_id" value="0"> <!-- valeur placeholder, non utilisée pour cette action globale -->
            <input type="hidden" name="from" value="owner"> <!-- indique au handler que l'origine est le panel owner -->
            <button type="submit" class="btn btn-outline-navy btn-sm">✓ Tout marquer comme lu</button>
        </form>
        <?php endif; // fin condition messages non lus ?>
    </div>

    <?php if (empty($contact_messages)): // aucun message reçu ?>
        <!-- État vide : aucun message de contact reçu -->
        <div style="text-align:center; padding:64px 0; color:var(--gray-400);">
            <div style="font-size:3rem; margin-bottom:16px;">📭</div>
            <p style="font-size:1rem; font-weight:600; color:var(--gray-500);">Aucun message reçu</p>
            <p style="font-size:0.85rem;">Les messages du formulaire de contact apparaîtront ici.</p>
        </div>
    <?php else: // il y a des messages à afficher ?>
    <div style="display:flex; flex-direction:column; gap:12px;"> <!-- liste verticale des messages de contact -->
        <?php foreach ($contact_messages as $contact_message_item): // itère sur chaque message de contact
            // Booléen de lecture : bordure orange + badge "Nouveau" si non lu
            $is_already_read = (bool)$contact_message_item['is_read']; // conversion en booléen explicite
            // Objet DateTime pour formater l'horodatage d'envoi du message
            $message_datetime = new DateTime($contact_message_item['sent_at']);
        ?>
        <!-- Bordure orange + légère ombre si non lu, gris standard si lu -->
        <div style="background:white; border:1.5px solid <?= $is_already_read ? 'var(--gray-200)' : 'var(--orange)' ?>;
                    border-radius:12px; padding:20px 24px;
                    <?= $is_already_read ? '' : 'box-shadow:0 2px 8px rgba(232,129,26,0.1);' ?>"> <!-- ombre subtile pour attirer l'attention sur les non lus -->
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                <div style="flex:1; min-width:0;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; flex-wrap:wrap;">
                        <!-- Badge "Nouveau" orange uniquement pour les messages non lus -->
                        <?php if (!$is_already_read): // affiche le badge uniquement si le message n'a pas encore été lu ?>
                            <span style="background:var(--orange);color:white;font-size:0.65rem;font-weight:700;padding:2px 8px;border-radius:99px;text-transform:uppercase;letter-spacing:0.5px;">Nouveau</span>
                        <?php endif; ?>
                        <!-- Nom de l'expéditeur en gras navy -->
                        <strong style="color:var(--navy); font-size:0.95rem;"><?= htmlspecialchars($contact_message_item['name']) ?></strong>
                        <!-- Email cliquable (mailto: pour réponse directe depuis un client mail) -->
                        <a href="mailto:<?= htmlspecialchars($contact_message_item['email']) ?>" style="color:var(--orange);font-size:0.85rem;text-decoration:none;"><?= htmlspecialchars($contact_message_item['email']) ?></a>
                        <!-- Date et heure d'envoi formatées (ex : 12/05/2026 à 14h37) -->
                        <span style="color:var(--gray-400);font-size:0.8rem;margin-left:auto;"><?= $message_datetime->format('d/m/Y à H\hi') ?></span>
                    </div>
                    <!-- Sujet du message si défini (champ optionnel dans le formulaire de contact) -->
                    <?php if (!empty($contact_message_item['subject'])): ?>
                    <p style="font-weight:600;color:var(--gray-700);margin:0 0 8px;font-size:0.9rem;"><?= htmlspecialchars($contact_message_item['subject']) ?></p>
                    <?php endif; ?>
                    <!-- Corps du message : pre-wrap pour préserver les retours à la ligne tapés par l'utilisateur -->
                    <p style="color:var(--gray-600);font-size:0.88rem;margin:0;line-height:1.6;white-space:pre-wrap;"><?= htmlspecialchars($contact_message_item['message']) ?></p>
                </div>
                <!-- Colonne d'actions : marquer lu/non-lu, répondre par email, supprimer -->
                <div style="display:flex; flex-direction:column; gap:6px; flex-shrink:0;">
                    <!-- Basculer l'état lu/non-lu : contact_action dynamique selon l'état actuel -->
                    <form method="POST" action="/sharetime/public/?page=owner">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="msg_id" value="<?= (int)$contact_message_item['id'] ?>"> <!-- ID du message ciblé -->
                        <input type="hidden" name="from" value="owner"> <!-- indique l'origine panel owner -->
                        <!-- action inverse de l'état actuel : si lu → mark_unread, si non lu → mark_read -->
                        <input type="hidden" name="contact_action" value="<?= $is_already_read ? 'mark_unread' : 'mark_read' ?>">
                        <!-- Bouton gris si lu (pour repasser en non lu), navy si non lu (pour marquer lu) -->
                        <button type="submit" style="width:100%;padding:6px 14px;font-size:0.78rem;font-weight:600;background:<?= $is_already_read ? 'var(--gray-100)' : 'var(--navy)' ?>;color:<?= $is_already_read ? 'var(--gray-600)' : 'white' ?>;border:1.5px solid <?= $is_already_read ? 'var(--gray-300)' : 'var(--navy)' ?>;border-radius:8px;cursor:pointer;white-space:nowrap;">
                            <?= $is_already_read ? '↩ Marquer non lu' : '✓ Marquer lu' ?>
                        </button>
                    </form>
                    <!-- Lien "Répondre" : ouvre le client mail avec sujet pré-rempli en "Re: …" -->
                    <a href="mailto:<?= htmlspecialchars($contact_message_item['email']) ?>?subject=Re: <?= htmlspecialchars(urlencode($contact_message_item['subject'] ?: 'Votre message')) ?>"
                       style="display:block;text-align:center;padding:6px 14px;font-size:0.78rem;font-weight:600;background:var(--orange);color:white;border-radius:8px;text-decoration:none;white-space:nowrap;">
                        ✉ Répondre
                    </a>
                    <!-- Supprimer définitivement le message (confirmation JS via onsubmit) -->
                    <form method="POST" action="/sharetime/public/?page=owner" onsubmit="return confirm('Supprimer ce message ?')">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="msg_id" value="<?= (int)$contact_message_item['id'] ?>">
                        <input type="hidden" name="from" value="owner">
                        <input type="hidden" name="contact_action" value="delete"> <!-- suppression définitive du message -->
                        <button type="submit" style="width:100%;padding:6px 14px;font-size:0.78rem;font-weight:600;background:white;color:#DC2626;border:1.5px solid #FECACA;border-radius:8px;cursor:pointer;white-space:nowrap;">
                            🗑 Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; // fin boucle des messages de contact ?>
    </div> <!-- fin de la liste des messages de contact -->
    <?php endif; // fin condition liste messages vide/non vide ?>

<?php /* ══════════════════════════════════════════════════════════════
   ONGLET CONTENU DU SITE : édition de la FAQ, des CGU et des mentions légales.
   L'owner peut ajouter/modifier/supprimer des questions FAQ, éditer le texte
   des CGU (avec versionnage) et celui des mentions légales.
   Le bouton "Éditer" d'une FAQ pré-remplit le formulaire via JS (openEditFaq).
══════════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($active_tab === 'contenu'): // onglet édition du contenu du site ?>

    <!-- ── FAQ ──────────────────────────────────────────────────────────────── -->
    <div style="margin-bottom:36px;">
        <h2 style="color:var(--navy);margin-bottom:16px;font-size:1.1rem;">📋 Foire aux questions</h2>

        <!-- Liste des questions FAQ existantes -->
        <?php if (empty($faq_items_owner)): // aucune question FAQ enregistrée ?>
            <p style="color:var(--gray-500);margin-bottom:16px;">Aucune question pour le moment.</p>
        <?php else: // affiche la liste des questions existantes ?>
            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;"> <!-- liste verticale des questions FAQ -->
            <?php foreach ($faq_items_owner as $faq_item): // itère sur chaque question FAQ ?>
            <div style="background:white;border:1.5px solid var(--gray-200);border-radius:10px;padding:14px 16px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                    <div style="flex:1;">
                        <!-- Question en gras + aperçu de la réponse tronqué à 120 caractères -->
                        <p style="font-weight:600;color:var(--gray-900);margin:0 0 4px;"><?= htmlspecialchars($faq_item['question']) ?></p>
                        <!-- Aperçu tronqué de la réponse pour ne pas surcharger l'interface -->
                        <p style="color:var(--gray-500);font-size:0.88rem;margin:0;"><?= htmlspecialchars(mb_substr($faq_item['reponse'], 0, 120)) ?>...</p>
                    </div>
                    <div style="display:flex;gap:6px;flex-shrink:0;">
                        <!-- Bouton Éditer : appelle openEditFaq() en JS pour pré-remplir le formulaire -->
                        <!-- json_encode + htmlspecialchars sécurise les guillemets et caractères spéciaux -->
                        <button type="button"
                            onclick="openEditFaq(<?= $faq_item['idfaq'] ?>, <?= htmlspecialchars(json_encode($faq_item['question'])) ?>, <?= htmlspecialchars(json_encode($faq_item['reponse'])) ?>)"
                            style="padding:5px 12px;border-radius:6px;border:1.5px solid var(--navy);background:white;color:var(--navy);font-size:0.78rem;font-weight:600;cursor:pointer;">
                            ✏️ Éditer
                        </button>
                        <!-- Supprimer une question FAQ (confirmation JS via onsubmit) -->
                        <form method="POST" action="/sharetime/public/?page=owner" onsubmit="return confirm('Supprimer cette question ?')">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="type" value="content"> <!-- cible : contenu du site -->
                            <input type="hidden" name="action" value="delete_faq"> <!-- action : supprimer une question FAQ -->
                            <input type="hidden" name="faq_id" value="<?= $faq_item['idfaq'] ?>"> <!-- ID de la question à supprimer -->
                            <button type="submit" style="padding:5px 12px;border-radius:6px;border:1.5px solid #FECACA;background:white;color:#DC2626;font-size:0.78rem;font-weight:600;cursor:pointer;">
                                🗑
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; // fin boucle des questions FAQ ?>
            </div> <!-- fin de la liste des questions FAQ -->
        <?php endif; // fin condition liste FAQ vide/non vide ?>

        <!-- Formulaire ajout/édition FAQ : bascule entre les deux modes via JS (openEditFaq / resetFaqForm) -->
        <div style="background:var(--gray-50);border:1.5px solid var(--gray-200);border-radius:12px;padding:20px;">
            <!-- Titre du formulaire : modifié par JS entre "Ajouter" et "Modifier" -->
            <h3 id="faq-form-title" style="color:var(--navy);margin:0 0 14px;font-size:0.95rem;">+ Ajouter une question</h3>
            <form id="faq-form" method="POST" action="/sharetime/public/?page=owner" style="display:flex;flex-direction:column;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="type" value="content">
                <!-- faq-action et faq-id changent entre 'add_faq' et 'edit_faq' selon le mode (ajout ou édition) -->
                <input type="hidden" id="faq-action" name="action" value="add_faq"> <!-- action par défaut : ajout -->
                <input type="hidden" id="faq-id" name="faq_id" value=""> <!-- vide en mode ajout, rempli par JS en mode édition -->
                <div>
                    <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:6px;font-size:0.88rem;">Question *</label>
                    <!-- Champ question : required, pré-rempli par JS en mode édition -->
                    <input type="text" id="faq-q" name="question" required placeholder="Ex : Comment créer une activité ?"
                        style="width:100%;padding:10px 14px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:0.9rem;font-family:inherit;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:6px;font-size:0.88rem;">Réponse *</label>
                    <!-- Textarea réponse : resize:vertical permet à l'utilisateur d'agrandir verticalement -->
                    <textarea id="faq-r" name="reponse" required rows="3" placeholder="Réponse complète..."
                        style="width:100%;padding:10px 14px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:0.9rem;font-family:inherit;resize:vertical;box-sizing:border-box;"></textarea>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn btn-navy">Enregistrer</button>
                    <!-- Bouton Annuler masqué par défaut (display:none), révélé en mode édition par JS -->
                    <button type="button" id="faq-cancel" onclick="resetFaqForm()" style="display:none;padding:10px 18px;border:1.5px solid var(--gray-300);border-radius:8px;background:white;font-size:0.9rem;cursor:pointer;">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── CGU (Conditions Générales d'Utilisation) ───────────────────────────── -->
    <div style="margin-bottom:36px;"> <!-- section CGU avec marge basse avant les mentions légales -->
        <h2 style="color:var(--navy);margin-bottom:16px;font-size:1.1rem;">📄 Conditions Générales d'Utilisation</h2>
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:12px;padding:20px;"> <!-- card blanche du formulaire CGU -->
            <form method="POST" action="/sharetime/public/?page=owner" style="display:flex;flex-direction:column;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="type" value="content">
                <input type="hidden" name="action" value="update_cgu"> <!-- action : mise à jour des CGU -->
                <div>
                    <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:6px;font-size:0.88rem;">Version (ex : v1.1)</label>
                    <!-- Champ version pré-rempli avec la version actuelle des CGU -->
                    <input type="text" name="version" value="<?= htmlspecialchars($cgu_version_owner) ?>" placeholder="v1.0"
                        style="width:200px;padding:10px 14px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:0.9rem;font-family:inherit;">
                </div>
                <div>
                    <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:6px;font-size:0.88rem;">Contenu *</label>
                    <p style="font-size:0.8rem;color:var(--gray-500);margin:0 0 8px;">Le texte sera affiché tel quel sur la page CGU. Utilisez des sauts de ligne pour structurer.</p>
                    <!-- Textarea pré-remplie avec le texte des CGU actuelles récupérées en base -->
                    <textarea name="contenu" required rows="12"
                        style="width:100%;padding:12px 14px;border:1.5px solid var(--gray-300);border-radius:8px;font-size:0.88rem;font-family:inherit;resize:vertical;box-sizing:border-box;line-height:1.6;"><?= htmlspecialchars($cgu_owner) ?></textarea>
                </div>
                <!-- Bouton de sauvegarde : width:fit-content pour ne pas prendre toute la largeur -->
                <button type="submit" class="btn btn-navy" style="width:fit-content;">Enregistrer les CGU</button>
            </form>
        </div>
    </div>

    <!-- ── MENTIONS LÉGALES ──────────────────────────────────────────────────── -->
    <div> <!-- section mentions légales, dernière de l'onglet contenu -->
        <h2 style="color:var(--navy);margin-bottom:16px;font-size:1.1rem;">⚖️ Mentions légales</h2>
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:12px;padding:20px;"> <!-- card blanche du formulaire mentions légales -->
            <form method="POST" action="/sharetime/public/?page=owner" style="display:flex;flex-direction:column;gap:12px;"> <!-- formulaire de mise à jour des mentions légales -->
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="type" value="content">
                <input type="hidden" name="action" value="update_mentions"> <!-- action : mise à jour des mentions légales -->
                <div>
                    <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:6px;font-size:0.88rem;">Contenu *</label>
                    <p style="font-size:0.8rem;color:var(--gray-500);margin:0 0 8px;">Affiché sur la page Mentions légales. Sauts de ligne préservés.</p>
                    <!-- Textarea pré-remplie avec le texte des mentions légales actuelles -->
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
<?php elseif ($active_tab === 'signalements'): // onglet modération des signalements ?>

    <!-- En-tête : titre + compteur total + nombre en attente -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="color:var(--navy);margin:0 0 4px;">Signalements utilisateurs</h2>
            <p style="color:var(--gray-500);font-size:0.9rem;margin:0;">
                <!-- Compteur total avec pluralisation automatique -->
                <?= count($reports_list) ?> signalement<?= count($reports_list) > 1 ? 's' : '' ?>
                <?php if ($reports_pending_count > 0): // affiche le nombre en attente seulement s'il y en a ?>
                    — <span style="color:#DC2626;font-weight:600;"><?= $reports_pending_count ?> en attente</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (empty($reports_list)): // aucun signalement reçu ?>
        <!-- État vide : aucun signalement reçu sur la plateforme -->
        <div style="text-align:center;padding:60px 0;color:var(--gray-400);">
            <p style="font-size:2rem;margin-bottom:12px;">✅</p>
            <p>Aucun signalement pour le moment.</p>
        </div>
    <?php else: // il y a des signalements à modérer ?>
    <div style="display:flex;flex-direction:column;gap:12px;"> <!-- liste verticale des signalements -->
    <?php foreach ($reports_list as $report_item): // itère sur chaque signalement
        // true si le signalement n'a pas encore été traité (action de modération requise)
        $is_pending_report = $report_item['status'] === 'en_attente';

        // Style inline du badge statut : rouge pour en_attente, vert pour traité, gris pour rejeté
        $report_status_style = match($report_item['status']) {
            'en_attente' => 'background:#FEE2E2;color:#DC2626;', // rouge : action requise
            'traite'     => 'background:#D1FAE5;color:#065F46;', // vert : résolu
            'rejete'     => 'background:#F3F4F6;color:var(--gray-500);', // gris : classé sans suite
            default      => '' // pas de style pour les statuts inconnus
        };

        // Libellé du badge statut avec icône correspondante
        $report_status_label = match($report_item['status']) {
            'en_attente' => '⏳ En attente', // en cours de traitement
            'traite'     => '✓ Traité',      // modéré et pris en compte
            'rejete'     => '✗ Rejeté',      // examiné et classé sans suite
            default      => $report_item['status'] // affiche la valeur brute si statut inconnu
        };

        // Nom d'affichage du signaleur : pseudo si disponible, prénom + nom sinon
        $reporter_display_name = htmlspecialchars(($report_item['sg_pseudo'] ?: $report_item['sg_prenom']) . ' ' . $report_item['sg_nom']);

        // Nom d'affichage de l'utilisateur signalé (sg_pseudo = signaleur, sd_pseudo = signalé)
        $reported_display_name = htmlspecialchars(($report_item['sd_pseudo'] ?: $report_item['sd_prenom']) . ' ' . $report_item['sd_nom']);
    ?>
    <!-- Bordure rouge pour les signalements en attente, gris pour les traités/rejetés -->
    <div style="background:white;border:1.5px solid <?= $is_pending_report ? '#FECACA' : 'var(--gray-200)' ?>;border-radius:12px;padding:18px 20px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
                    <!-- Badge de statut coloré selon la sévérité du signalement -->
                    <span style="font-size:0.72rem;font-weight:700;padding:3px 10px;border-radius:99px;<?= $report_status_style ?>"><?= $report_status_label ?></span>
                    <!-- Horodatage de la création du signalement (date et heure) -->
                    <span style="color:var(--gray-400);font-size:0.8rem;"><?= date('d/m/Y à H:i', strtotime($report_item['created_at'])) ?></span>
                </div>
                <!-- Signaleur : lien cliquable vers son profil public pour consultation -->
                <p style="margin:0 0 6px;font-size:0.9rem;color:var(--gray-700);">
                    <strong>Signaleur :</strong>
                    <a href="/sharetime/public/?page=profil&id=<?= $report_item['signaleur_id'] ?>" style="color:var(--navy);font-weight:600;"><?= $reporter_display_name ?></a>
                </p>
                <!-- Signalé : en rouge pour attirer l'attention sur la personne visée par la plainte -->
                <p style="margin:0 0 8px;font-size:0.9rem;color:var(--gray-700);">
                    <strong>Signalé :</strong>
                    <a href="/sharetime/public/?page=profil&id=<?= $report_item['signale_id'] ?>" style="color:#DC2626;font-weight:600;"><?= $reported_display_name ?></a>
                </p>
                <!-- Motif du signalement dans un encadré gris pour le distinguer du reste -->
                <p style="margin:0;font-size:0.88rem;color:var(--gray-600);background:var(--gray-50);padding:8px 12px;border-radius:8px;">
                    <?= htmlspecialchars($report_item['motif']) ?> <!-- motif protégé contre XSS -->
                </p>
            </div>
            <!-- Boutons d'action uniquement pour les signalements encore en attente -->
            <?php if ($is_pending_report): // masque les boutons pour les signalements déjà traités/rejetés ?>
            <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
                <!-- Marquer comme traité : signalement examiné et pris en compte par le modérateur -->
                <form method="POST" action="/sharetime/public/?page=owner">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="type" value="report"> <!-- cible : un signalement -->
                    <input type="hidden" name="action" value="update_report"> <!-- mise à jour du statut -->
                    <input type="hidden" name="report_id" value="<?= $report_item['idreports'] ?>"> <!-- ID du signalement -->
                    <input type="hidden" name="status" value="traite"> <!-- nouveau statut : traité -->
                    <!-- tab=signalements pour rediriger vers le bon onglet après traitement -->
                    <input type="hidden" name="tab" value="signalements">
                    <button type="submit" style="width:100%;padding:6px 14px;border-radius:6px;border:1.5px solid #059669;background:white;color:#059669;font-size:0.78rem;font-weight:600;cursor:pointer;">
                        ✓ Traité
                    </button>
                </form>
                <!-- Rejeter : signalement examiné mais considéré non fondé par le modérateur -->
                <form method="POST" action="/sharetime/public/?page=owner">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="type" value="report">
                    <input type="hidden" name="action" value="update_report">
                    <input type="hidden" name="report_id" value="<?= $report_item['idreports'] ?>">
                    <input type="hidden" name="status" value="rejete"> <!-- nouveau statut : rejeté (non fondé) -->
                    <input type="hidden" name="tab" value="signalements">
                    <button type="submit" style="width:100%;padding:6px 14px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-500);font-size:0.78rem;font-weight:600;cursor:pointer;">
                        ✗ Rejeter
                    </button>
                </form>
            </div>
            <?php endif; // fin condition signalement en attente ?>
        </div>
    </div>
    <?php endforeach; // fin boucle des signalements ?>
    </div> <!-- fin de la liste des signalements -->
    <?php endif; // fin condition liste signalements vide/non vide ?>

<script>
// ── Gestion du formulaire FAQ en mode ajout / édition ──────────────────────

// Bascule le formulaire en mode "édition" d'une question existante.
// Pré-remplit les champs et change le titre + l'action hidden.
function openEditFaq(faq_id, faq_question, faq_reponse) {
    document.getElementById('faq-form-title').textContent = '✏️ Modifier la question'; // change le titre du formulaire
    document.getElementById('faq-action').value = 'edit_faq'; // bascule l'action sur "édition"
    document.getElementById('faq-id').value = faq_id; // injecte l'ID de la question à modifier
    document.getElementById('faq-q').value = faq_question; // pré-remplit le champ question
    document.getElementById('faq-r').value = faq_reponse; // pré-remplit le champ réponse
    // Révèle le bouton "Annuler" pour sortir du mode édition sans sauvegarder
    document.getElementById('faq-cancel').style.display = 'block';
    // Scroll vers le formulaire pour que l'utilisateur le voit directement après le clic
    document.getElementById('faq-form').scrollIntoView({behavior:'smooth', block:'center'});
}

// Réinitialise le formulaire FAQ en mode "ajout" (état initial par défaut).
// Réappelé par le bouton "Annuler" en mode édition pour revenir à l'état neutre.
function resetFaqForm() {
    document.getElementById('faq-form-title').textContent = '+ Ajouter une question'; // remet le titre par défaut
    document.getElementById('faq-action').value = 'add_faq'; // rebascule sur l'action "ajout"
    document.getElementById('faq-id').value = ''; // vide l'ID (pas de question ciblée)
    document.getElementById('faq-q').value = ''; // vide le champ question
    document.getElementById('faq-r').value = ''; // vide le champ réponse
    document.getElementById('faq-cancel').style.display = 'none'; // cache à nouveau le bouton Annuler
}
</script>

<?php endif; // fin du bloc if/elseif sur $active_tab ?>

</div> <!-- fin du conteneur principal -->
</main> <!-- fin de la zone de contenu principal -->

<!-- Responsive : grilles 2 colonnes → 1 colonne, tables réduites sur mobile -->
<style> <!-- surcharges CSS spécifiques à la page owner pour l'affichage mobile -->
@media (max-width: 768px) {
    div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; } /* empile les colonnes en une seule sur petit écran */
    table { font-size: 0.8rem !important; } /* réduit la taille de police dans les tableaux */
    td, th { padding: 8px 10px !important; } /* réduit le padding des cellules pour gagner de la place */
}
</style>
