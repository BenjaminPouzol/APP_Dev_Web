<?php
/**
 * public/pages/home.php — Page d'accueil
 *
 * Variables disponibles (préparées par index.php) :
 *   $activities   : tableau des 6 prochaines activités actives
 *   $CATEGORY_MAP : mapping catégorie → [emoji, classe CSS, libellé]
 *
 * Cette page fait une requête SQL supplémentaire (stats globales) car
 * ces chiffres ne sont utilisés que sur la home et n'ont pas leur place
 * dans le routing général de index.php.
 */

// ── Statistiques globales affichées dans la bande de chiffres ─────────────
// Une seule requête avec trois sous-sélections pour éviter trois allers-retours.
// Ces données sont purement informatives : pas de cache nécessaire.
$home_stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM users) AS nb_users,                                       -- nombre total de membres inscrits
        (SELECT COUNT(*) FROM activities WHERE status IN ('active','en_cours')) AS nb_activities,  -- activités à venir ou en cours
        (SELECT COUNT(DISTINCT city) FROM activities WHERE city != '') AS nb_cities     -- nombre de villes différentes représentées
")->fetch();
$nb_users      = $home_stats['nb_users'];
$nb_activities = $home_stats['nb_activities'];
$nb_cities     = $home_stats['nb_cities'];
?>

<main>

<!-- ── HERO ────────────────────────────────────────────────────────────────
     Section principale au-dessus de la ligne de flottaison.
     Contient : slogan, barre de recherche par ville, et chips de catégories
     pour accéder rapidement aux activités filtrées. -->
<section class="hero">
    <div class="container hero-content">
        <!-- Badge décoratif au-dessus du titre -->
        <div class="hero-badge">✨ Partageons l'instant</div>

        <!-- Titre principal : le <span> colore "près de chez toi" en orange via le CSS -->
        <h1>Découvre des activités <span>près de chez toi</span></h1>
        <p class="hero-subtitle">
            Rejoins des événements locaux, rencontre des personnes qui partagent tes passions
            et crée tes propres activités en quelques clics.
        </p>

        <!-- Barre de recherche rapide : soumet vers la page activites avec le filtre ville -->
        <form class="search-bar" action="/sharetime/public/" method="get">
            <input type="hidden" name="page" value="activites"><!-- paramètre de routing -->
            <div class="search-bar-icon">🔍</div>
            <input type="text" name="city" placeholder="Rechercher par ville...">
            <button type="submit" class="btn btn-orange">Rechercher</button>
        </form>

        <!-- Chips de catégories : chaque chip redirige vers la liste filtrée.
             On exclut 'autre' qui est une catégorie générique peu utile comme filtre. -->
        <div class="hero-chips">
            <?php foreach ($CATEGORY_MAP as $val => [$emoji, , $label]): if ($val === 'autre') continue; ?>
            <a href="/sharetime/public/?page=activites&category=<?= $val ?>" class="chip"><?= $emoji ?> <?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── BANDE DE STATISTIQUES ───────────────────────────────────────────────
     Trois chiffres clés pour rassurer les nouveaux visiteurs sur la vitalité
     de la communauté. Les valeurs sont fraîches à chaque chargement de page. -->
<div class="stats-band">
    <div class="container">
        <div class="stat-item">
            <span class="stat-number"><?= $nb_users ?></span>
            <span class="stat-label">Membres</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= $nb_activities ?></span>
            <span class="stat-label">Activités actives</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= $nb_cities ?></span>
            <span class="stat-label">Villes</span>
        </div>
    </div>
</div>

<!-- ── ACCÈS RAPIDE ADMIN / OWNER ──────────────────────────────────────────
     Cartes d'accès rapide visibles uniquement par les admin et l'owner.
     L'owner voit deux cartes (Propriétaire + Admin), l'admin n'en voit qu'une (Admin).
     La grille est 2 colonnes pour l'owner, 1 colonne pour l'admin. -->
<?php if (isset($_SESSION['user']) && is_admin()): ?>
<div class="container" style="margin:32px auto 0;">
    <!-- grid-template-columns dynamique : 2 colonnes si owner (deux cartes), 1 si admin -->
    <div style="display:grid; grid-template-columns:<?= is_owner() ? '1fr 1fr' : '1fr' ?>; gap:16px;">

        <?php if (is_owner()): ?>
        <!-- Carte Propriétaire (orange) — visible uniquement pour l'owner -->
        <a href="/sharetime/public/?page=owner" style="text-decoration:none;">
            <div style="background:linear-gradient(135deg,var(--orange) 0%,#c96a10 100%);
                        border-radius:16px; padding:24px 28px;
                        display:flex; align-items:center; justify-content:space-between; gap:16px;
                        box-shadow:0 4px 18px rgba(232,129,26,0.3); transition:transform 0.15s, box-shadow 0.15s;"
                 onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 28px rgba(232,129,26,0.4)'"
                 onmouseout="this.style.transform='';this.style.boxShadow='0 4px 18px rgba(232,129,26,0.3)'">
                <div>
                    <p style="color:rgba(255,255,255,0.75);font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Accès rapide</p>
                    <p style="color:white;font-size:1.15rem;font-weight:800;margin:0;">👑 Espace Super-Admin</p>
                    <p style="color:rgba(255,255,255,0.7);font-size:0.82rem;margin:4px 0 0;">Gérer les administrateurs · Transférer la propriété</p>
                </div>
                <span style="color:white;font-size:1.8rem;opacity:0.6;">→</span>
            </div>
        </a>
        <?php endif; ?>

        <!-- Carte Administration (navy) — visible pour admin ET owner -->
        <a href="/sharetime/public/?page=admin" style="text-decoration:none;">
            <div style="background:linear-gradient(135deg,var(--navy) 0%,var(--navy-light) 100%);
                        border-radius:16px; padding:24px 28px;
                        display:flex; align-items:center; justify-content:space-between; gap:16px;
                        box-shadow:0 4px 18px rgba(30,58,110,0.25); transition:transform 0.15s, box-shadow 0.15s;"
                 onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 28px rgba(30,58,110,0.35)'"
                 onmouseout="this.style.transform='';this.style.boxShadow='0 4px 18px rgba(30,58,110,0.25)'">
                <div>
                    <p style="color:rgba(255,255,255,0.65);font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Accès rapide</p>
                    <p style="color:white;font-size:1.15rem;font-weight:800;margin:0;">⚙️ Panel d'administration</p>
                    <p style="color:rgba(255,255,255,0.6);font-size:0.82rem;margin:4px 0 0;">Membres · Activités · Tableau de bord</p>
                </div>
                <span style="color:white;font-size:1.8rem;opacity:0.6;">→</span>
            </div>
        </a>

    </div>
</div>
<!-- Responsive : les deux cartes passent en 1 colonne sur mobile -->
<style>
@media (max-width: 640px) {
    div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
}
</style>
<?php endif; ?>

<!-- ── ACTIVITÉS À VENIR ────────────────────────────────────────────────────
     Affiche jusqu'à 6 activités actives triées par date de début croissante
     (les plus proches en premier). $activities est chargé dans index.php
     avec LIMIT 6. La boucle stoppe à $shown >= 6 comme double sécurité. -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <div>
                <p class="section-eyebrow">À ne pas manquer</p>
                <h2 class="section-title">Activités à venir</h2>
            </div>
            <a href="/sharetime/public/?page=activites" class="btn btn-outline-navy">Tout voir →</a>
        </div>

        <?php if (empty($activities)): ?>
            <!-- État vide : invite à créer la première activité -->
            <div style="text-align:center; padding:60px 0; color:var(--gray-500);">
                <p style="font-size:2rem; margin-bottom:12px;">🌟</p>
                <p style="font-size:1.1rem; margin-bottom:8px;">Aucune activité pour le moment.</p>
                <a href="/sharetime/public/?page=creer" class="btn btn-orange" style="margin-top:12px;">
                    Créer la première activité
                </a>
            </div>
        <?php else: ?>
            <div class="cards-grid">
                <?php
                $shown = 0;
                foreach ($activities as $a):
                    if ($shown >= 6) break;  // limite à 6 cartes même si index.php en a retourné plus
                    $shown++;
                    $cat    = $CATEGORY_MAP[$a['category']] ?? $CATEGORY_MAP['autre'];  // fallback sur 'autre' si catégorie inconnue
                    $places = $a['max_participants'] - $a['nb_inscrits'];                // places restantes
                    $start  = new DateTime($a['start_time']);                            // objet DateTime pour le formatage
                    $auteur = $a['pseudo'] ?: $a['prenom'];                             // préfère le pseudo, fallback sur le prénom
                ?>
                <!-- Carte activité : lien vers la page de détail -->
                <a href="/sharetime/public/?page=detail&id=<?= $a['idactivities'] ?>" class="activity-card">
                    <!-- Image de couverture ou fond coloré par catégorie -->
                    <?php if (!empty($a['photo'])): ?>
                    <div class="card-image" style="background-image:url('/sharetime/public/uploads/activites/<?= htmlspecialchars($a['photo']) ?>');background-size:cover;background-position:center;">
                    <?php else: ?>
                    <div class="card-image <?= $cat[1] ?>"><!-- classe CSS de couleur selon la catégorie -->
                        <?= $cat[0] ?><!-- emoji de la catégorie -->
                    <?php endif; ?>
                        <span class="card-badge"><?= htmlspecialchars($a['city']) ?></span><!-- badge ville -->
                        <span class="card-badge-vis"><?= $a['visibility'] === 'publique' ? 'Public' : 'Privé' ?></span><!-- badge visibilité -->
                    </div>
                    <div class="card-body">
                        <div class="card-title"><?= htmlspecialchars($a['title']) ?></div>
                        <div class="card-meta">
                            <span>📅 <?= $start->format('d/m/Y à H:i') ?></span>
                            <span>📍 <?= htmlspecialchars($a['location']) ?></span>
                            <span>👤 <?= htmlspecialchars($auteur) ?></span>
                        </div>
                        <!-- Indicateur de places : vert si dispo, orange si < 3, rouge si complet -->
                        <div class="card-footer">
                            <?php if ($places <= 0): ?>
                                <span class="places-full">Complet</span>
                            <?php elseif ($places <= 2): ?>
                                <span class="places-few"><?= $places ?> place(s)</span>
                            <?php else: ?>
                                <span class="places-ok"><?= $places ?> places libres</span>
                            <?php endif; ?>
                            <span class="btn btn-sm btn-orange">Voir →</span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── CALL-TO-ACTION ──────────────────────────────────────────────────────
     Bande d'inscription visible uniquement pour les visiteurs non connectés.
     Disparaît une fois l'utilisateur connecté pour ne pas surcharger la page. -->
<?php if (!isset($_SESSION['user'])): ?>
<section class="cta-band">
    <div class="container">
        <h2>Prêt à rejoindre la communauté ?</h2>
        <p>Crée ton compte gratuitement et commence à partager des moments inoubliables.</p>
        <div class="cta-actions">
            <a href="/sharetime/public/?page=inscription" class="btn btn-white btn-lg">S'inscrire gratuitement</a>
            <a href="/sharetime/public/?page=connexion" class="btn btn-outline btn-lg">Se connecter</a>
        </div>
    </div>
</section>
<?php endif; ?>

</main>
