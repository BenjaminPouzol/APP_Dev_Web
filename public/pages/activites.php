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
            <!-- Compteur de résultats : accord au pluriel + mention du filtre ville actif -->
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
        <!-- Préserve le filtre catégorie si actif (sinon absent de la querystring) -->
        <?php if (!empty($category_filter)): ?>
            <input type="hidden" name="category" value="<?= htmlspecialchars($category_filter) ?>">
        <?php endif; ?>
        <!-- Préserve le filtre statut si actif -->
        <?php if (!empty($status_filter)): ?>
            <input type="hidden" name="statut" value="<?= htmlspecialchars($status_filter) ?>">
        <?php endif; ?>
        <!-- Champ ville : recherche LIKE %...% côté SQL -->
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
         $base_querystring est construit avec array_filter pour exclure les valeurs vides. -->
    <?php
    // Querystring de base sans catégorie ni page : préserve ville, texte et statut pour les chips catégorie
    $base_querystring = http_build_query(array_filter([
        'page'   => 'activites',
        'city'   => $city_filter,
        'search' => $title_filter,
        'statut' => $status_filter,
    ]));
    ?>
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
        <!-- Chip "Toutes" : supprime le filtre catégorie (pas de paramètre category dans l'URL) -->
        <a href="/sharetime/public/?<?= $base_querystring ?>"
           style="padding:6px 16px; border-radius:99px; font-size:0.85rem; font-weight:600; text-decoration:none;
                  background:<?= empty($category_filter) ? 'var(--navy)' : 'var(--gray-100)' ?>;
                  color:<?= empty($category_filter) ? 'white' : 'var(--gray-600)' ?>;">
            Toutes
        </a>
        <!-- Une chip par catégorie (sauf 'autre', peu pertinente comme filtre) -->
        <?php foreach ($CATEGORY_MAP as $category_slug => [$category_emoji, , $category_label]): if ($category_slug === 'autre') continue; ?>
        <!-- Chip active = fond navy/blanc, inactive = fond gris clair -->
        <a href="/sharetime/public/?<?= $base_querystring ?>&category=<?= $category_slug ?>"
           style="padding:6px 16px; border-radius:99px; font-size:0.85rem; font-weight:600; text-decoration:none;
                  background:<?= $category_filter === $category_slug ? 'var(--navy)' : 'var(--gray-100)' ?>;
                  color:<?= $category_filter === $category_slug ? 'white' : 'var(--gray-600)' ?>;">
            <?= $category_emoji ?> <?= $category_label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── CHIPS DE STATUT ────────────────────────────────────────────────────
         Même principe que les chips catégorie mais pour le statut de l'activité.
         $base_querystring_no_status préserve ville, texte et catégorie mais pas le statut
         (car c'est ce paramètre-là qu'on remplace à chaque clic). -->
    <?php
    // Querystring sans statut : conserve ville, texte et catégorie
    $base_querystring_no_status = http_build_query(array_filter([
        'page'     => 'activites',
        'city'     => $city_filter,
        'search'   => $title_filter,
        'category' => $category_filter,
    ]));
    // Libellés des états possibles (clé vide = afficher tous les statuts)
    $status_filter_options = ['' => '🗂 Toutes', 'active' => '✅ À venir', 'en_cours' => '🔴 En cours', 'terminee' => '🏁 Terminées', 'annulee' => '❌ Annulées'];
    ?>
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:24px;">
        <?php foreach ($status_filter_options as $status_slug => $status_chip_label): ?>
        <!-- Chip orange si sélectionnée, grise sinon ; clé vide = pas de paramètre statut dans l'URL -->
        <a href="/sharetime/public/?<?= $base_querystring_no_status ?><?= $status_slug ? '&statut='.$status_slug : '' ?>"
           style="padding:5px 14px; border-radius:99px; font-size:0.82rem; font-weight:600; text-decoration:none;
                  background:<?= $status_filter === $status_slug ? 'var(--orange)' : 'var(--gray-100)' ?>;
                  color:<?= $status_filter === $status_slug ? 'white' : 'var(--gray-600)' ?>;">
            <?= $status_chip_label ?>
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
            <?php foreach ($activities as $activity_item):
                // Récupère les infos de catégorie avec fallback sur 'autre' si catégorie inconnue
                $category_info      = $CATEGORY_MAP[$activity_item['category']] ?? $CATEGORY_MAP['autre'];
                // Places disponibles = max inscrits - inscrits confirmés
                $available_places   = $activity_item['max_participants'] - $activity_item['nb_inscrits'];
                // Objet DateTime pour formater la date de début
                $start_datetime     = new DateTime($activity_item['start_time']);
                // Préfère le pseudo, fallback sur le prénom pour les comptes sans pseudo
                $organizer_display_name = $activity_item['pseudo'] ?: $activity_item['prenom'];
            ?>
            <a href="/sharetime/public/?page=detail&id=<?= $activity_item['idactivities'] ?>" class="activity-card">
                <!-- Image de couverture ou fond coloré par catégorie si aucune photo -->
                <?php if (!empty($activity_item['photo'])): ?>
                <div class="card-image" style="background-image:url('/sharetime/public/uploads/activites/<?= htmlspecialchars($activity_item['photo']) ?>');background-size:cover;background-position:center;">
                <?php else: ?>
                <div class="card-image <?= $category_info[1] ?>">
                    <?= $category_info[0] ?>
                <?php endif; ?>
                    <span class="card-badge"><?= htmlspecialchars($activity_item['city']) ?></span>
                    <span class="card-badge-vis"><?= $activity_item['visibility'] === 'publique' ? 'Public' : 'Privé' ?></span>
                </div>
                <div class="card-body">
                    <!-- Bandeau de statut — masqué si active (état normal, pas besoin de le signaler) -->
                    <?php if ($activity_item['status'] !== 'active'): ?>
                        <?php
                            // Couleurs et libellés des statuts non actifs
                            $non_active_status_colors = ['annulee' => '#DC2626', 'en_cours' => '#C05621', 'terminee' => 'var(--gray-500)'];
                            $non_active_status_labels = ['annulee' => '❌ Annulée', 'en_cours' => '🔴 En cours', 'terminee' => '🏁 Terminée'];
                        ?>
                        <span style="font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px;
                                     color:<?= $non_active_status_colors[$activity_item['status']] ?? 'var(--gray-500)' ?>;
                                     margin-bottom:4px; display:block;">
                            <?= $non_active_status_labels[$activity_item['status']] ?? ucfirst($activity_item['status']) ?>
                        </span>
                    <?php endif; ?>
                    <div class="card-title"><?= htmlspecialchars($activity_item['title']) ?></div>
                    <div class="card-meta">
                        <span>📅 <?= $start_datetime->format('d/m/Y à H:i') ?></span>
                        <span>📍 <?= htmlspecialchars($activity_item['location']) ?>, <?= htmlspecialchars($activity_item['city']) ?></span>
                        <span>👤 Par <?= htmlspecialchars($organizer_display_name) ?></span>
                    </div>
                    <div class="card-footer">
                        <!-- Indicateur de places : masqué (—) si activité non active ou en cours -->
                        <?php if ($activity_item['status'] !== 'active'): ?>
                            <span style="color:var(--gray-400); font-size:0.85rem;">—</span>
                        <?php elseif ($available_places <= 0): ?>
                            <span class="places-full">Complet</span>
                        <?php elseif ($available_places <= 2): ?>
                            <span class="places-few"><?= $available_places ?> place(s)</span>
                        <?php else: ?>
                            <span class="places-ok"><?= $available_places ?> places libres</span>
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
            // Querystring complète pour les liens de pagination : tous les filtres actifs + paramètre p
            $pagination_querystring = http_build_query(array_filter([
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
                    <a href="/sharetime/public/?<?= $pagination_querystring ?>&p=<?= $current_page - 1 ?>"
                       class="btn btn-outline-navy btn-sm">← Précédent</a>
                <?php endif; ?>

                <!-- Numéros de pages : fenêtre [current-2 … current+2] clampée aux bornes -->
                <?php for ($page_number = max(1, $current_page - 2); $page_number <= min($total_pages, $current_page + 2); $page_number++): ?>
                    <!-- Page courante : fond navy ; autres pages : fond gris clair -->
                    <a href="/sharetime/public/?<?= $pagination_querystring ?>&p=<?= $page_number ?>"
                       style="display:inline-flex; align-items:center; justify-content:center;
                              width:36px; height:36px; border-radius:8px; font-size:0.9rem; font-weight:600;
                              text-decoration:none;
                              background:<?= $page_number === $current_page ? 'var(--navy)' : 'var(--gray-100)' ?>;
                              color:<?= $page_number === $current_page ? 'white' : 'var(--gray-600)' ?>;">
                        <?= $page_number ?>
                    </a>
                <?php endfor; ?>

                <!-- Bouton "Suivant" : masqué sur la dernière page -->
                <?php if ($current_page < $total_pages): ?>
                    <a href="/sharetime/public/?<?= $pagination_querystring ?>&p=<?= $current_page + 1 ?>"
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
