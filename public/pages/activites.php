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
                <?= $total_count ?> activité<?= $total_count > 1 ? 's' : '' ?> trouvée<?= $total_count > 1 ? 's' : '' ?> <!-- Accord automatique du mot "activité" et "trouvée" au pluriel si nécessaire -->
                <?= !empty($city_filter) ? ' à "' . htmlspecialchars($city_filter) . '"' : '' ?> <!-- Affiche la ville filtrée si un filtre de ville est actif -->
                <?php if (!empty($category_filter) && isset($CATEGORY_MAP[$category_filter])): // Vérifie qu'un filtre de catégorie est actif et connu ?>
                    · <?= $CATEGORY_MAP[$category_filter][0] ?> <?= $CATEGORY_MAP[$category_filter][2] ?> <!-- Affiche l'emoji et le libellé de la catégorie filtrée -->
                <?php endif; ?>
            </p>
        </div>
        <?php if (isset($_SESSION['user'])): // Affiche le bouton "Créer" uniquement aux utilisateurs connectés ?>
            <a href="/sharetime/public/?page=creer" class="btn btn-orange btn-lg">+ Créer une activité</a> <!-- Bouton de création d'une nouvelle activité -->
        <?php endif; ?>
    </div>

    <!-- ── BARRE DE FILTRES TEXTE ─────────────────────────────────────────────
         Formulaire GET : les champs hidden préservent les filtres actifs (catégorie,
         statut) quand l'utilisateur ne veut changer que la ville ou le titre.
         Sans eux, les chips actives seraient perdues à la soumission. -->
    <form method="get" action="/sharetime/public/" style="margin-bottom:16px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;"> <!-- Formulaire de filtrage soumis par méthode GET -->
        <input type="hidden" name="page" value="activites"> <!-- Paramètre de routing pour indiquer la page courante à index.php -->
        <!-- Préserve le filtre catégorie si actif (sinon absent de la querystring) -->
        <?php if (!empty($category_filter)): // Inclut le filtre catégorie dans le formulaire s'il est actif ?>
            <input type="hidden" name="category" value="<?= htmlspecialchars($category_filter) ?>"> <!-- Champ caché qui conserve la catégorie sélectionnée lors de la soumission -->
        <?php endif; ?>
        <!-- Préserve le filtre statut si actif -->
        <?php if (!empty($status_filter)): // Inclut le filtre statut dans le formulaire s'il est actif ?>
            <input type="hidden" name="statut" value="<?= htmlspecialchars($status_filter) ?>"> <!-- Champ caché qui conserve le statut sélectionné lors de la soumission -->
        <?php endif; ?>
        <!-- Champ ville : recherche LIKE %...% côté SQL -->
        <input type="text" name="city" value="<?= htmlspecialchars($city_filter) ?>"
               placeholder="Ville..."
               style="padding:10px 16px; border:1.5px solid var(--gray-300); border-radius:8px; font-size:0.9rem; font-family:inherit; min-width:160px;"> <!-- Champ texte prérempli avec la ville déjà filtrée -->
        <!-- Champ recherche plein texte : cherche dans title ET description -->
        <input type="text" name="search" value="<?= htmlspecialchars($title_filter) ?>"
               placeholder="Recherche par titre ou description..."
               style="padding:10px 16px; border:1.5px solid var(--gray-300); border-radius:8px; font-size:0.9rem; font-family:inherit; flex:1; min-width:180px;"> <!-- Champ texte prérempli avec le terme de recherche déjà saisi -->
        <button type="submit" class="btn btn-navy">Rechercher</button> <!-- Bouton qui soumet le formulaire de filtrage -->
        <!-- Bouton reset : affiché uniquement si au moins un filtre est actif -->
        <?php if (!empty($city_filter) || !empty($category_filter) || !empty($status_filter) || !empty($title_filter)): // Affiche le bouton reset seulement si un filtre est actif ?>
            <a href="/sharetime/public/?page=activites" class="btn btn-outline-navy">✕ Réinitialiser</a> <!-- Lien qui supprime tous les filtres en revenant à l'URL de base -->
        <?php endif; ?>
    </form>

    <!-- ── CHIPS DE CATÉGORIES ────────────────────────────────────────────────
         Chaque chip est un lien GET qui ajoute/remplace le paramètre category
         tout en préservant les autres filtres actifs (ville, texte, statut).
         $base_querystring est construit avec array_filter pour exclure les valeurs vides. -->
    <?php
    // Querystring de base sans catégorie ni page : préserve ville, texte et statut pour les chips catégorie
    $base_querystring = http_build_query(array_filter([ // Construit une chaîne d'URL en supprimant les valeurs vides avec array_filter
        'page'   => 'activites',  // Paramètre obligatoire pour le routing
        'city'   => $city_filter, // Conserve le filtre ville actif s'il existe
        'search' => $title_filter, // Conserve le filtre texte actif s'il existe
        'statut' => $status_filter, // Conserve le filtre statut actif s'il existe
    ]));
    ?>
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
        <!-- Chip "Toutes" : supprime le filtre catégorie (pas de paramètre category dans l'URL) -->
        <a href="/sharetime/public/?<?= $base_querystring ?>"
           style="padding:6px 16px; border-radius:99px; font-size:0.85rem; font-weight:600; text-decoration:none;
                  background:<?= empty($category_filter) ? 'var(--navy)' : 'var(--gray-100)' ?>;
                  color:<?= empty($category_filter) ? 'white' : 'var(--gray-600)' ?>;"> <!-- La chip "Toutes" est mise en évidence (fond navy) quand aucune catégorie n'est sélectionnée -->
            Toutes
        </a>
        <!-- Une chip par catégorie (sauf 'autre', peu pertinente comme filtre) -->
        <?php foreach ($CATEGORY_MAP as $category_slug => [$category_emoji, , $category_label]): if ($category_slug === 'autre') continue; // Ignore la catégorie générique 'autre' ?>
        <!-- Chip active = fond navy/blanc, inactive = fond gris clair -->
        <!-- Lien qui ajoute le filtre catégorie à la querystring existante -->
        <a href="/sharetime/public/?<?= $base_querystring ?>&category=<?= $category_slug ?>"
           style="padding:6px 16px; border-radius:99px; font-size:0.85rem; font-weight:600; text-decoration:none;
                  background:<?= $category_filter === $category_slug ? 'var(--navy)' : 'var(--gray-100)' ?>;
                  color:<?= $category_filter === $category_slug ? 'white' : 'var(--gray-600)' ?>;"> <!-- Fond navy si catégorie active, gris sinon -->
            <?= $category_emoji ?> <?= $category_label ?> <!-- Affiche l'emoji suivi du nom de la catégorie -->
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── CHIPS DE STATUT ────────────────────────────────────────────────────
         Même principe que les chips catégorie mais pour le statut de l'activité.
         $base_querystring_no_status préserve ville, texte et catégorie mais pas le statut
         (car c'est ce paramètre-là qu'on remplace à chaque clic). -->
    <?php
    // Querystring sans statut : conserve ville, texte et catégorie
    $base_querystring_no_status = http_build_query(array_filter([ // Construit la querystring en excluant le statut pour que chaque chip de statut le remplace
        'page'     => 'activites',       // Paramètre de routing obligatoire
        'city'     => $city_filter,      // Conserve le filtre ville
        'search'   => $title_filter,     // Conserve le filtre texte
        'category' => $category_filter,  // Conserve le filtre catégorie
    ]));
    // Libellés des états possibles (clé vide = afficher tous les statuts)
    $status_filter_options = ['' => '🗂 Toutes', 'active' => '✅ À venir', 'en_cours' => '🔴 En cours', 'terminee' => '🏁 Terminées', 'annulee' => '❌ Annulées']; // Tableau associatif : slug de statut → libellé affiché sur la chip
    ?>
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:24px;">
        <?php foreach ($status_filter_options as $status_slug => $status_chip_label): // Parcourt chaque option de statut pour créer une chip ?>
        <!-- Chip orange si sélectionnée, grise sinon ; clé vide = pas de paramètre statut dans l'URL -->
        <!-- Clé vide = pas de paramètre statut (= afficher tous les statuts) ; clé non vide = filtre sur ce statut -->
        <a href="/sharetime/public/?<?= $base_querystring_no_status ?><?= $status_slug ? '&statut='.$status_slug : '' ?>"
           style="padding:5px 14px; border-radius:99px; font-size:0.82rem; font-weight:600; text-decoration:none;
                  background:<?= $status_filter === $status_slug ? 'var(--orange)' : 'var(--gray-100)' ?>;
                  color:<?= $status_filter === $status_slug ? 'white' : 'var(--gray-600)' ?>;"> <!-- Fond orange si statut actif, gris sinon -->
            <?= $status_chip_label ?> <!-- Libellé de la chip de statut avec emoji -->
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── ÉTAT VIDE ─────────────────────────────────────────────────────────
         Deux messages différents selon que des filtres sont actifs ou non :
         - Avec filtres → invite à élargir la recherche
         - Sans filtres → invite à créer la première activité -->
    <?php if (empty($activities)): // Aucune activité ne correspond aux filtres appliqués ?>
        <div style="text-align:center; padding:80px 0; color:var(--gray-500);">
            <p style="font-size:2.5rem; margin-bottom:16px;">🔍</p>
            <p style="font-size:1.1rem; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Aucune activité trouvée.</p>
            <?php if (!empty($city_filter) || !empty($title_filter)): // Des filtres texte sont actifs : propose de les effacer ?>
                <p>Essayez avec d'autres critères ou
                   <a href="/sharetime/public/?page=activites" style="color:var(--orange); font-weight:600;">voir toutes les activités</a>.
                </p>
            <?php else: // Aucun filtre actif : la base est vraiment vide, invite à créer ?>
                <p>Soyez le premier à
                   <a href="/sharetime/public/?page=creer" style="color:var(--orange); font-weight:600;">créer une activité</a> !
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- ── GRILLE DE CARTES ───────────────────────────────────────────────
             Même classe .cards-grid que la home. Chaque carte est un <a> pour
             que le clic entier soit cliquable (accessibilité). -->
        <div class="cards-grid"> <!-- Conteneur CSS en grille pour aligner les cartes sur plusieurs colonnes -->
            <?php foreach ($activities as $activity_item): // Parcourt chaque activité de la page courante
                // Récupère les infos de catégorie avec fallback sur 'autre' si catégorie inconnue
                $category_info      = $CATEGORY_MAP[$activity_item['category']] ?? $CATEGORY_MAP['autre'];
                // Places disponibles = max inscrits - inscrits confirmés
                $available_places   = $activity_item['max_participants'] - $activity_item['nb_inscrits'];
                // Objet DateTime pour formater la date de début
                $start_datetime     = new DateTime($activity_item['start_time']);
                // Préfère le pseudo, fallback sur le prénom pour les comptes sans pseudo
                $organizer_display_name = $activity_item['pseudo'] ?: $activity_item['prenom'];
            ?>
            <a href="/sharetime/public/?page=detail&id=<?= $activity_item['idactivities'] ?>" class="activity-card"> <!-- Carte cliquable vers la page de détail de l'activité -->
                <!-- Image de couverture ou fond coloré par catégorie si aucune photo -->
                <?php if (!empty($activity_item['photo'])): // Affiche la photo uploadée si elle existe ?>
                <div class="card-image" style="background-image:url('/sharetime/public/uploads/activites/<?= htmlspecialchars($activity_item['photo']) ?>');background-size:cover;background-position:center;"> <!-- Affiche la photo comme image de fond, couvrant tout le bloc -->
                <?php else: ?>
                <div class="card-image <?= $category_info[1] ?>"> <!-- Fond coloré selon la catégorie quand il n'y a pas de photo -->
                    <?= $category_info[0] ?> <!-- Emoji de la catégorie affiché à la place de la photo -->
                <?php endif; ?>
                    <span class="card-badge"><?= htmlspecialchars($activity_item['city']) ?></span> <!-- Badge de la ville affiché sur l'image -->
                    <span class="card-badge-vis"><?= $activity_item['visibility'] === 'publique' ? 'Public' : 'Privé' ?></span> <!-- Badge de visibilité : "Public" ou "Privé" selon le réglage de l'activité -->
                </div>
                <div class="card-body">
                    <!-- Bandeau de statut — masqué si active (état normal, pas besoin de le signaler) -->
                    <?php if ($activity_item['status'] !== 'active'): // N'affiche le bandeau de statut que pour les activités non actives ?>
                        <?php
                            // Couleurs et libellés des statuts non actifs
                            $non_active_status_colors = ['annulee' => '#DC2626', 'en_cours' => '#C05621', 'terminee' => 'var(--gray-500)']; // Rouge pour annulée, orange foncé pour en cours, gris pour terminée
                            $non_active_status_labels = ['annulee' => '❌ Annulée', 'en_cours' => '🔴 En cours', 'terminee' => '🏁 Terminée']; // Libellés avec emojis pour chaque statut non actif
                        ?>
                        <span style="font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px;
                                     color:<?= $non_active_status_colors[$activity_item['status']] ?? 'var(--gray-500)' ?>;
                                     margin-bottom:4px; display:block;">
                            <?= $non_active_status_labels[$activity_item['status']] ?? ucfirst($activity_item['status']) ?> <!-- Libellé du statut avec fallback sur le statut brut mis en majuscule -->
                        </span>
                    <?php endif; ?>
                    <div class="card-title"><?= htmlspecialchars($activity_item['title']) ?></div> <!-- Titre de l'activité, échappé pour éviter les failles XSS -->
                    <div class="card-meta">
                        <span>📅 <?= $start_datetime->format('d/m/Y à H:i') ?></span> <!-- Date et heure de début au format français -->
                        <span>📍 <?= htmlspecialchars($activity_item['location']) ?>, <?= htmlspecialchars($activity_item['city']) ?></span> <!-- Lieu complet : adresse et ville -->
                        <span>👤 Par <?= htmlspecialchars($organizer_display_name) ?></span> <!-- Nom ou pseudo de l'organisateur de l'activité -->
                    </div>
                    <div class="card-footer">
                        <!-- Indicateur de places : masqué (—) si activité non active ou en cours -->
                        <?php if ($activity_item['status'] !== 'active'): // Pour les activités non actives, on n'affiche pas les places disponibles ?>
                            <span style="color:var(--gray-400); font-size:0.85rem;">—</span> <!-- Tiret neutre pour les activités terminées, annulées ou en cours -->
                        <?php elseif ($available_places <= 0): // Toutes les places ont été prises ?>
                            <span class="places-full">Complet</span>
                        <?php elseif ($available_places <= 2): // Moins de 3 places restantes, indication d'urgence ?>
                            <span class="places-few"><?= $available_places ?> place(s)</span>
                        <?php else: // Plusieurs places encore disponibles ?>
                            <span class="places-ok"><?= $available_places ?> places libres</span>
                        <?php endif; ?>
                        <span class="btn btn-sm btn-orange">Voir →</span> <!-- Bouton visuel invitant à consulter le détail de l'activité -->
                    </div>
                </div>
            </a>
            <?php endforeach; // Fin de la boucle sur les activités de la page courante ?>
        </div>

        <!-- ── PAGINATION ─────────────────────────────────────────────────────
             Affiche uniquement s'il y a plus d'une page. Fenêtre glissante de
             5 pages (±2 autour de la page courante) pour ne pas surcharger. -->
        <?php if ($total_pages > 1): // Affiche la pagination seulement s'il y a plusieurs pages ?>
            <?php
            // Querystring complète pour les liens de pagination : tous les filtres actifs + paramètre p
            $pagination_querystring = http_build_query(array_filter([ // Construit l'URL de pagination en conservant tous les filtres actifs
                'page'     => 'activites',       // Paramètre de routing
                'city'     => $city_filter,      // Filtre ville actif
                'search'   => $title_filter,     // Filtre texte actif
                'category' => $category_filter,  // Filtre catégorie actif
                'statut'   => $status_filter,    // Filtre statut actif
            ]));
            ?>
            <div style="display:flex; justify-content:center; align-items:center; gap:8px; margin-top:40px; flex-wrap:wrap;">
                <!-- Bouton "Précédent" : masqué sur la première page -->
                <?php if ($current_page > 1): // Affiche le bouton "Précédent" seulement si on n'est pas sur la première page ?>
                    <a href="/sharetime/public/?<?= $pagination_querystring ?>&p=<?= $current_page - 1 ?>"
                       class="btn btn-outline-navy btn-sm">← Précédent</a> <!-- Lien vers la page précédente en décrémentant le numéro de page -->
                <?php endif; ?>

                <!-- Numéros de pages : fenêtre [current-2 … current+2] clampée aux bornes -->
                <?php for ($page_number = max(1, $current_page - 2); $page_number <= min($total_pages, $current_page + 2); $page_number++): // Boucle sur les 5 pages autour de la page courante (±2), sans dépasser les bornes ?>
                    <!-- Page courante : fond navy ; autres pages : fond gris clair -->
                    <a href="/sharetime/public/?<?= $pagination_querystring ?>&p=<?= $page_number ?>"
                       style="display:inline-flex; align-items:center; justify-content:center;
                              width:36px; height:36px; border-radius:8px; font-size:0.9rem; font-weight:600;
                              text-decoration:none;
                              background:<?= $page_number === $current_page ? 'var(--navy)' : 'var(--gray-100)' ?>;
                              color:<?= $page_number === $current_page ? 'white' : 'var(--gray-600)' ?>;"> <!-- La page courante est mise en évidence avec un fond navy et du texte blanc -->
                        <?= $page_number ?> <!-- Affiche le numéro de la page -->
                    </a>
                <?php endfor; ?>

                <!-- Bouton "Suivant" : masqué sur la dernière page -->
                <?php if ($current_page < $total_pages): // Affiche le bouton "Suivant" seulement si on n'est pas sur la dernière page ?>
                    <a href="/sharetime/public/?<?= $pagination_querystring ?>&p=<?= $current_page + 1 ?>"
                       class="btn btn-outline-navy btn-sm">Suivant →</a> <!-- Lien vers la page suivante en incrémentant le numéro de page -->
                <?php endif; ?>
            </div>
            <!-- Indicateur "Page X / Y" sous les boutons de pagination -->
            <p style="text-align:center; color:var(--gray-400); font-size:0.82rem; margin-top:12px;">
                Page <?= $current_page ?> / <?= $total_pages ?> <!-- Affiche la page actuelle et le nombre total de pages -->
            </p>
        <?php endif; ?>
    <?php endif; ?>

</main>
