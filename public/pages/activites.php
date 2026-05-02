<?php
/**
 * public/pages/activites.php — Liste paginée des activités avec filtres
 *
 * Variables disponibles (préparées par index.php routing) :
 *   $activities      : tableau des activités de la page courante
 *   $total_count     : nombre total d'activités correspondant aux filtres
 *   $total_pages     : nombre de pages de pagination
 *   $current_page    : numéro de la page affichée (≥ 1)
 *   $city_filter     : filtre ville saisi par l'utilisateur (peut être vide)
 *   $title_filter    : filtre titre/description (peut être vide)
 *   $category_filter : slug de catégorie sélectionné (peut être vide)
 *   $status_filter   : filtre de statut : '', 'active', 'terminee', 'annulee'
 *   $CATEGORY_MAP    : mapping catégorie → [emoji, classe CSS, libellé]
 */
?>
<main class="container" style="padding:40px 0;">

    <!-- ── EN-TÊTE DE PAGE ────────────────────────────────────────────────────
         Titre + compteur de résultats avec accord grammatical automatique.
         Le bouton "Créer" n'est visible que si l'utilisateur est connecté. -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:16px;">
        <div>
            <h1 style="color:var(--navy); margin-bottom:4px;">Activités</h1>
            <!-- Compteur de résultats : accord au pluriel + mention du filtre actif -->
            <p style="color:var(--gray-500); font-size:0.9rem;">
                <?= $total_count ?> activité<?= $total_count > 1 ? 's' : '' ?> trouvée<?= $total_count > 1 ? 's' : '' ?>
                <?= !empty($city_filter) ? ' à "' . htmlspecialchars($city_filter) . '"' : '' ?>
                <?php if (!empty($category_filter) && isset($CATEGORY_MAP[$category_filter])): ?>
                    · <?= $CATEGORY_MAP[$category_filter][0] ?> <?= $CATEGORY_MAP[$category_filter][2] ?>
                <?php endif; ?>
            </p>
        </div>
        <?php if (isset($_SESSION['user'])): ?>
            <a href="/sharetime/public/?page=creer" class="btn btn-orange btn-lg">+ Créer une activité</a>
        <?php endif; ?>
    </div>

    <!-- ── BARRE DE FILTRES TEXTE ─────────────────────────────────────────────
         Formulaire GET : les champs hidden préservent les filtres actifs (catégorie,
         statut) quand l'utilisateur ne veut changer que la ville ou le titre.
         Sans eux, les chips actives seraient perdues à la soumission. -->
    <form method="get" action="/sharetime/public/" style="margin-bottom:16px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <input type="hidden" name="page" value="activites">
        <!-- Préserve le filtre catégorie si actif (sinon non présent dans la querystring) -->
        <?php if (!empty($category_filter)): ?>
            <input type="hidden" name="category" value="<?= htmlspecialchars($category_filter) ?>">
        <?php endif; ?>
        <!-- Préserve le filtre statut si actif -->
        <?php if (!empty($status_filter)): ?>
            <input type="hidden" name="statut" value="<?= htmlspecialchars($status_filter) ?>">
        <?php endif; ?>
        <!-- Champ ville : recherche exacte (LIKE %...% côté SQL) -->
        <input type="text" name="city" value="<?= htmlspecialchars($city_filter) ?>"
               placeholder="Ville..."
               style="padding:10px 16px; border:1.5px solid var(--gray-300); border-radius:8px; font-size:0.9rem; font-family:inherit; min-width:160px;">
        <!-- Champ recherche plein texte : cherche dans title ET description -->
        <input type="text" name="search" value="<?= htmlspecialchars($title_filter) ?>"
               placeholder="Recherche par titre ou description..."
               style="padding:10px 16px; border:1.5px solid var(--gray-300); border-radius:8px; font-size:0.9rem; font-family:inherit; flex:1; min-width:180px;">
        <button type="submit" class="btn btn-navy">Rechercher</button>
        <!-- Bouton reset : affiché uniquement si au moins un filtre est actif -->
        <?php if (!empty($city_filter) || !empty($category_filter) || !empty($status_filter) || !empty($title_filter)): ?>
            <a href="/sharetime/public/?page=activites" class="btn btn-outline-navy">✕ Réinitialiser</a>
        <?php endif; ?>
    </form>

    <!-- ── CHIPS DE CATÉGORIES ────────────────────────────────────────────────
         Chaque chip est un lien GET qui ajoute/remplace le paramètre category
         tout en préservant les autres filtres actifs (ville, texte, statut).
         $base_qs est construit avec array_filter pour exclure les valeurs vides. -->
    <?php
    // Base de la querystring sans catégorie ni numéro de page : utilisée pour construire
    // les liens des chips en conservant ville, texte et statut.
    $base_qs = http_build_query(array_filter([
        'page'     => 'activites',
        'city'     => $city_filter,
        'search'   => $title_filter,
        'statut'   => $status_filter,
    ]));
    ?>
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
        <!-- Chip "Toutes" : remet category à vide (pas de paramètre category dans l'URL) -->
        <a href="/sharetime/public/?<?= $base_qs ?>"
           style="padding:6px 16px; border-radius:99px; font-size:0.85rem; font-weight:600; text-decoration:none;
                  background:<?= empty($category_filter) ? 'var(--navy)' : 'var(--gray-100)' ?>;
                  color:<?= empty($category_filter) ? 'white' : 'var(--gray-600)' ?>;">
            Toutes
        </a>
        <!-- Une chip par catégorie (sauf 'autre' peu utile comme filtre) -->
        <?php foreach ($CATEGORY_MAP as $val => [$emoji, , $label]): if ($val === 'autre') continue; ?>
        <!-- La chip active est navy/blanc, les autres sont grises -->
        <a href="/sharetime/public/?<?= $base_qs ?>&category=<?= $val ?>"
           style="padding:6px 16px; border-radius:99px; font-size:0.85rem; font-weight:600; text-decoration:none;
                  background:<?= $category_filter === $val ? 'var(--navy)' : 'var(--gray-100)' ?>;
                  color:<?= $category_filter === $val ? 'white' : 'var(--gray-600)' ?>;">
            <?= $emoji ?> <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── CHIPS DE STATUT ────────────────────────────────────────────────────
         Même principe que les chips catégorie mais pour le statut de l'activité.
         $base_qs_cat préserve ville, texte et catégorie mais pas le statut
         (car c'est ce paramètre-là qu'on remplace). -->
    <?php
    // Querystring sans statut : conserve ville, texte et catégorie
    $base_qs_cat = http_build_query(array_filter([
        'page'     => 'activites',
        'city'     => $city_filter,
        'search'   => $title_filter,
        'category' => $category_filter,
    ]));
    // Libellés des 4 états possibles (vide = toutes)
    $statuts = ['' => '🗂 Toutes', 'active' => '✅ Actives', 'terminee' => '🏁 Terminées', 'annulee' => '❌ Annulées'];
    ?>
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:24px;">
        <?php foreach ($statuts as $val => $label): ?>
        <!-- Chip orange si active, grise sinon ; $val vide = pas de paramètre statut dans l'URL -->
        <a href="/sharetime/public/?<?= $base_qs_cat ?><?= $val ? '&statut='.$val : '' ?>"
           style="padding:5px 14px; border-radius:99px; font-size:0.82rem; font-weight:600; text-decoration:none;
                  background:<?= $status_filter === $val ? 'var(--orange)' : 'var(--gray-100)' ?>;
                  color:<?= $status_filter === $val ? 'white' : 'var(--gray-600)' ?>;">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── ÉTAT VIDE ─────────────────────────────────────────────────────────
         Deux messages différents selon que des filtres sont actifs ou non :
         - Avec filtres → invite à élargir la recherche
         - Sans filtres → invite à créer la première activité -->
    <?php if (empty($activities)): ?>
        <div style="text-align:center; padding:80px 0; color:var(--gray-500);">
            <p style="font-size:2.5rem; margin-bottom:16px;">🔍</p>
            <p style="font-size:1.1rem; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Aucune activité trouvée.</p>
            <?php if (!empty($city_filter) || !empty($title_filter)): ?>
                <p>Essayez avec d'autres critères ou
                   <a href="/sharetime/public/?page=activites" style="color:var(--orange); font-weight:600;">voir toutes les activités</a>.
                </p>
            <?php else: ?>
                <p>Soyez le premier à
                   <a href="/sharetime/public/?page=creer" style="color:var(--orange); font-weight:600;">créer une activité</a> !
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- ── GRILLE DE CARTES ───────────────────────────────────────────────
             Même classe .cards-grid que la home. Chaque carte est un <a> pour
             que le clic entier soit cliquable (accessibilité). -->
        <div class="cards-grid">
            <?php foreach ($activities as $a):
                $cat    = $CATEGORY_MAP[$a['category']] ?? $CATEGORY_MAP['autre'];  // fallback si catégorie inconnue
                $places = $a['max_participants'] - $a['nb_inscrits'];                // places restantes calculées
                $start  = new DateTime($a['start_time']);
                $auteur = $a['pseudo'] ?: $a['prenom'];                             // pseudo préféré, prénom en fallback
            ?>
            <a href="/sharetime/public/?page=detail&id=<?= $a['idactivities'] ?>" class="activity-card">
                <!-- Image de couverture ou fond coloré par catégorie -->
                <?php if (!empty($a['photo'])): ?>
                <div class="card-image" style="background-image:url('/sharetime/public/uploads/activites/<?= htmlspecialchars($a['photo']) ?>');background-size:cover;background-position:center;">
                <?php else: ?>
                <div class="card-image <?= $cat[1] ?>">
                    <?= $cat[0] ?>
                <?php endif; ?>
                    <span class="card-badge"><?= htmlspecialchars($a['city']) ?></span>
                    <span class="card-badge-vis"><?= $a['visibility'] === 'publique' ? 'Public' : 'Privé' ?></span>
                </div>
                <div class="card-body">
                    <!-- Bandeau de statut (rouge annulée / gris terminée) — masqué si active -->
                    <?php if ($a['status'] !== 'active'): ?>
                        <span style="font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px;
                                     color:<?= $a['status'] === 'annulee' ? '#DC2626' : 'var(--gray-500)' ?>;
                                     margin-bottom:4px; display:block;">
                            <?= $a['status'] === 'annulee' ? '❌ Annulée' : '🏁 Terminée' ?>
                        </span>
                    <?php endif; ?>
                    <div class="card-title"><?= htmlspecialchars($a['title']) ?></div>
                    <div class="card-meta">
                        <span>📅 <?= $start->format('d/m/Y à H:i') ?></span>
                        <span>📍 <?= htmlspecialchars($a['location']) ?>, <?= htmlspecialchars($a['city']) ?></span>
                        <span>👤 Par <?= htmlspecialchars($auteur) ?></span>
                    </div>
                    <div class="card-footer">
                        <!-- Indicateur de places : masqué (—) si activité non active -->
                        <?php if ($a['status'] !== 'active'): ?>
                            <span style="color:var(--gray-400); font-size:0.85rem;">—</span>
                        <?php elseif ($places <= 0): ?>
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

        <!-- ── PAGINATION ─────────────────────────────────────────────────────
             Affiche uniquement s'il y a plus d'une page. Fenêtre glissante de
             5 pages (±2 autour de la page courante) pour ne pas surcharger. -->
        <?php if ($total_pages > 1): ?>
            <?php
            // Querystring complète pour les liens de pagination : tous les filtres + paramètre p
            $page_qs = http_build_query(array_filter([
                'page'     => 'activites',
                'city'     => $city_filter,
                'search'   => $title_filter,
                'category' => $category_filter,
                'statut'   => $status_filter,
            ]));
            ?>
            <div style="display:flex; justify-content:center; align-items:center; gap:8px; margin-top:40px; flex-wrap:wrap;">
                <!-- Bouton "Précédent" : masqué sur la première page -->
                <?php if ($current_page > 1): ?>
                    <a href="/sharetime/public/?<?= $page_qs ?>&p=<?= $current_page - 1 ?>"
                       class="btn btn-outline-navy btn-sm">← Précédent</a>
                <?php endif; ?>

                <!-- Numéros de pages : fenêtre [current-2 … current+2] clampée aux bornes -->
                <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                    <!-- Page courante : fond navy ; autres : fond gris clair -->
                    <a href="/sharetime/public/?<?= $page_qs ?>&p=<?= $i ?>"
                       style="display:inline-flex; align-items:center; justify-content:center;
                              width:36px; height:36px; border-radius:8px; font-size:0.9rem; font-weight:600;
                              text-decoration:none;
                              background:<?= $i === $current_page ? 'var(--navy)' : 'var(--gray-100)' ?>;
                              color:<?= $i === $current_page ? 'white' : 'var(--gray-600)' ?>;">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <!-- Bouton "Suivant" : masqué sur la dernière page -->
                <?php if ($current_page < $total_pages): ?>
                    <a href="/sharetime/public/?<?= $page_qs ?>&p=<?= $current_page + 1 ?>"
                       class="btn btn-outline-navy btn-sm">Suivant →</a>
                <?php endif; ?>
            </div>
            <!-- Indicateur "Page X / Y" sous les boutons de pagination -->
            <p style="text-align:center; color:var(--gray-400); font-size:0.82rem; margin-top:12px;">
                Page <?= $current_page ?> / <?= $total_pages ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>

</main>
