<?php
/**
 * public/pages/admin.php — Tableau de bord d'administration
 *
 * Variables disponibles (préparées par index.php routing) :
 *   $admin_stats            : statistiques globales (membres, admins, activités, inscriptions, suspendus)
 *   $admin_recent_users     : 5 derniers utilisateurs inscrits
 *   $admin_recent_activities: 5 dernières activités créées
 *
 * Accessible par les admins ET l'owner.
 * L'owner est automatiquement redirigé vers ?page=owner s'il tente d'accéder
 * à ?page=admin (redirection dans index.php routing).
 */
?>
<!-- ── EN-TÊTE ADMIN ──────────────────────────────────────────────────────────
     Bandeau navy commun à toutes les pages admin.
     Affiche le titre de la section + badge de rôle + prénom/nom de l'admin connecté. -->
<div style="background:var(--navy);padding:28px 0;">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <!-- Libellé de section affiché au-dessus du titre principal -->
            <p style="color:rgba(255,255,255,0.55);font-size:0.8rem;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;">Administration</p>
            <!-- Titre principal de la page du tableau de bord -->
            <h1 style="color:white;margin:0;font-size:1.6rem;">Tableau de bord</h1>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <!-- role_badge() génère un badge HTML coloré selon le rôle (admin / owner) -->
            <?= role_badge($_SESSION['user']['role']) ?>
            <!-- Affichage sécurisé du prénom et nom via htmlspecialchars pour éviter les injections XSS -->
            <span style="color:rgba(255,255,255,0.6);font-size:0.9rem;"><?= htmlspecialchars($_SESSION['user']['prenom'].' '.$_SESSION['user']['nom']) ?></span>
        </div>
    </div>
</div>

<main>
    <!-- Barre de navigation admin : liens vers dashboard, users, activities, logs -->
    <?php admin_nav('admin'); // Affiche la nav avec l'onglet 'admin' actif ?>

    <div class="container" style="padding-bottom:48px;">

        <!-- ── CARDS DE STATISTIQUES ──────────────────────────────────────────
             5 indicateurs clés en grille auto-fit : s'adaptent au nombre de colonnes
             disponibles. Chaque card a un fond coloré et un emoji associé. -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:36px;">
            <?php
            // Définition des cards : [libellé affiché, valeur numérique, emoji, couleur de fond, couleur du chiffre]
            $stats_card_definitions = [
                ['Membres',      $admin_stats['membres'],      '👤', '#EBF0F8', 'var(--navy)'],   // Nombre total de membres inscrits
                ['Admins',       $admin_stats['admins'],       '🛡️', '#FEF3E2', 'var(--orange)'], // Nombre d'administrateurs actifs
                ['Activités',    $admin_stats['activites'],    '🎯', '#D1FAE5', '#065F46'],        // Nombre total d'activités créées
                ['Inscriptions', $admin_stats['inscriptions'], '✅', '#EDE9FE', '#7C3AED'],        // Nombre total d'inscriptions confirmées
                ['Suspendus',    $admin_stats['suspendus'],    '🚫', '#FEE2E2', '#DC2626'],        // Nombre de comptes actuellement suspendus
            ];
            // Boucle sur chaque définition de card pour générer le HTML de chaque indicateur
            foreach ($stats_card_definitions as [$card_label, $card_value, $card_icon, $card_bg_color, $card_text_color]):
            ?>
            <!-- Card individuelle : fond blanc, bordure subtile, coins arrondis -->
            <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;padding:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <!-- Libellé de l'indicateur en petites majuscules espacées -->
                    <span style="font-size:0.75rem;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;"><?= $card_label ?></span>
                    <!-- Icône dans un fond coloré spécifique à chaque indicateur -->
                    <span style="font-size:1.3rem;background:<?= $card_bg_color ?>;padding:6px;border-radius:8px;"><?= $card_icon ?></span>
                </div>
                <!-- Valeur numérique en grand, colorée selon l'indicateur -->
                <p style="font-size:2rem;font-weight:800;color:<?= $card_text_color ?>;margin:0;"><?= $card_value ?></p>
            </div>
            <?php endforeach; // Fin du foreach sur les cards de statistiques ?>
        </div>

        <!-- ── DEUX COLONNES : DERNIERS MEMBRES + DERNIÈRES ACTIVITÉS ─────────
             Aperçu des 5 dernières entrées de chaque type avec lien vers la vue complète. -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

            <!-- ── DERNIERS MEMBRES INSCRITS ─────────────────────────────── -->
            <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
                <!-- En-tête de la section avec lien "Tout voir" -->
                <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;">
                    <h2 style="margin:0;font-size:1rem;color:var(--navy);">Derniers membres</h2>
                    <!-- Lien vers la page complète de gestion des utilisateurs -->
                    <a href="/sharetime/public/?page=admin_users" style="font-size:0.82rem;color:var(--orange);font-weight:600;text-decoration:none;">Tout voir →</a>
                </div>
                <!-- Itération sur les 5 derniers utilisateurs inscrits -->
                <?php foreach ($admin_recent_users as $recent_user): ?>
                <div style="padding:12px 20px;border-bottom:1px solid var(--gray-50);display:flex;align-items:center;justify-content:space-between;gap:10px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <!-- Avatar : initiale du prénom sur fond gradient navy -->
                        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.9rem;flex-shrink:0;">
                            <!-- strtoupper + mb_substr extrait la première lettre du prénom en majuscule (compatible accents) -->
                            <?= strtoupper(mb_substr($recent_user['prenom'],0,1)) ?>
                        </div>
                        <div>
                            <!-- Nom complet de l'utilisateur protégé contre les XSS -->
                            <p style="margin:0;font-size:0.88rem;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($recent_user['prenom'].' '.$recent_user['nom']) ?></p>
                            <!-- Email de l'utilisateur en texte secondaire plus clair -->
                            <p style="margin:0;font-size:0.78rem;color:var(--gray-500);"><?= htmlspecialchars($recent_user['email']) ?></p>
                        </div>
                    </div>
                    <!-- role_badge avec is_banned : affiche badge rouge "Banni" si suspendu -->
                    <?= role_badge($recent_user['role'], !empty($recent_user['is_banned'])) ?>
                </div>
                <?php endforeach; // Fin du foreach sur les derniers utilisateurs ?>
            </div>

            <!-- ── DERNIÈRES ACTIVITÉS CRÉÉES ────────────────────────────── -->
            <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
                <!-- En-tête avec lien "Tout voir" vers la gestion des activités -->
                <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;">
                    <h2 style="margin:0;font-size:1rem;color:var(--navy);">Dernières activités</h2>
                    <!-- Lien vers la page complète de gestion des activités -->
                    <a href="/sharetime/public/?page=admin_activities" style="font-size:0.82rem;color:var(--orange);font-weight:600;text-decoration:none;">Tout voir →</a>
                </div>
                <?php foreach ($admin_recent_activities as $recent_activity):
                    // Objet DateTime pour formater la date de début de l'activité
                    $start_datetime = new DateTime($recent_activity['start_time']);
                    // Couleurs des badges statut : vert=active, orange=en cours, rouge=annulée, gris=terminée
                    $status_badge_colors = ['active'=>['#D1FAE5','#065F46'],'en_cours'=>['#FEF3C7','#92400E'],'annulee'=>['#FEE2E2','#DC2626'],'terminee'=>['#F3F4F6','#6B7280']];
                    // Libellés lisibles associés à chaque valeur de statut en base de données
                    $status_badge_labels = ['active'=>'À venir','en_cours'=>'En cours','annulee'=>'Annulée','terminee'=>'Terminée'];
                    // Déstructuration des deux couleurs (fond + texte) du badge statut avec fallback gris si statut inconnu
                    [$status_badge_bg, $status_badge_color] = $status_badge_colors[$recent_activity['status']] ?? ['#F3F4F6','#6B7280'];
                ?>
                <div style="padding:12px 20px;border-bottom:1px solid var(--gray-50);display:flex;align-items:center;justify-content:space-between;gap:10px;">
                    <div>
                        <!-- Titre de l'activité protégé contre les injections XSS -->
                        <p style="margin:0;font-size:0.88rem;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($recent_activity['title']) ?></p>
                        <!-- Ville et date de début séparées par un point médian -->
                        <p style="margin:0;font-size:0.78rem;color:var(--gray-500);"><?= htmlspecialchars($recent_activity['city']) ?> · <?= $start_datetime->format('d/m/Y') ?></p>
                    </div>
                    <!-- Badge statut coloré avec fond et texte selon l'état actuel -->
                    <span style="background:<?= $status_badge_bg ?>;color:<?= $status_badge_color ?>;padding:3px 10px;border-radius:99px;font-size:0.75rem;font-weight:600;white-space:nowrap;"><?= $status_badge_labels[$recent_activity['status']] ?? ucfirst($recent_activity['status']) ?></span>
                </div>
                <?php endforeach; // Fin du foreach sur les dernières activités ?>
            </div>
        </div>

    </div>
</main>

<!-- Responsive : grilles 1fr 1fr → 1 colonne sur mobile, stats → 2 colonnes sur petit écran -->
<style>
@media (max-width: 768px) {
    /* Sur mobile : les deux colonnes côte à côte passent en colonne unique */
    div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
    /* Les cards de stats passent en grille 2 colonnes sur mobile au lieu de 5 */
    div[style*="repeat(auto-fit"] { grid-template-columns: repeat(2,1fr) !important; }
}
</style>
