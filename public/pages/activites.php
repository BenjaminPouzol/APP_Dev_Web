<main class="container" style="padding:40px 0;">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:16px;">
        <div>
            <h1 style="color:var(--navy); margin-bottom:4px;">Activités</h1>
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

    <!-- Filtre ville + titre -->
    <form method="get" action="/sharetime/public/" style="margin-bottom:16px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <input type="hidden" name="page" value="activites">
        <?php if (!empty($category_filter)): ?>
            <input type="hidden" name="category" value="<?= htmlspecialchars($category_filter) ?>">
        <?php endif; ?>
        <?php if (!empty($status_filter)): ?>
            <input type="hidden" name="statut" value="<?= htmlspecialchars($status_filter) ?>">
        <?php endif; ?>
        <input type="text" name="city" value="<?= htmlspecialchars($city_filter) ?>"
               placeholder="Ville..."
               style="padding:10px 16px; border:1.5px solid var(--gray-300); border-radius:8px; font-size:0.9rem; font-family:inherit; min-width:160px;">
        <input type="text" name="search" value="<?= htmlspecialchars($title_filter) ?>"
               placeholder="Recherche par titre ou description..."
               style="padding:10px 16px; border:1.5px solid var(--gray-300); border-radius:8px; font-size:0.9rem; font-family:inherit; flex:1; min-width:180px;">
        <button type="submit" class="btn btn-navy">Rechercher</button>
        <?php if (!empty($city_filter) || !empty($category_filter) || !empty($status_filter) || !empty($title_filter)): ?>
            <a href="/sharetime/public/?page=activites" class="btn btn-outline-navy">✕ Réinitialiser</a>
        <?php endif; ?>
    </form>

    <!-- Chips catégories -->
    <?php
    $base_qs = http_build_query(array_filter([
        'page'     => 'activites',
        'city'     => $city_filter,
        'search'   => $title_filter,
        'statut'   => $status_filter,
    ]));
    ?>
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
        <a href="/sharetime/public/?<?= $base_qs ?>"
           style="padding:6px 16px; border-radius:99px; font-size:0.85rem; font-weight:600; text-decoration:none;
                  background:<?= empty($category_filter) ? 'var(--navy)' : 'var(--gray-100)' ?>;
                  color:<?= empty($category_filter) ? 'white' : 'var(--gray-600)' ?>;">
            Toutes
        </a>
        <?php foreach ($CATEGORY_MAP as $val => [$emoji, , $label]): if ($val === 'autre') continue; ?>
        <a href="/sharetime/public/?<?= $base_qs ?>&category=<?= $val ?>"
           style="padding:6px 16px; border-radius:99px; font-size:0.85rem; font-weight:600; text-decoration:none;
                  background:<?= $category_filter === $val ? 'var(--navy)' : 'var(--gray-100)' ?>;
                  color:<?= $category_filter === $val ? 'white' : 'var(--gray-600)' ?>;">
            <?= $emoji ?> <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Chips statut -->
    <?php
    $base_qs_cat = http_build_query(array_filter([
        'page'     => 'activites',
        'city'     => $city_filter,
        'search'   => $title_filter,
        'category' => $category_filter,
    ]));
    $statuts = ['' => '🗂 Toutes', 'active' => '✅ Actives', 'terminee' => '🏁 Terminées', 'annulee' => '❌ Annulées'];
    ?>
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:24px;">
        <?php foreach ($statuts as $val => $label): ?>
        <a href="/sharetime/public/?<?= $base_qs_cat ?><?= $val ? '&statut='.$val : '' ?>"
           style="padding:5px 14px; border-radius:99px; font-size:0.82rem; font-weight:600; text-decoration:none;
                  background:<?= $status_filter === $val ? 'var(--orange)' : 'var(--gray-100)' ?>;
                  color:<?= $status_filter === $val ? 'white' : 'var(--gray-600)' ?>;">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

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
        <div class="cards-grid">
            <?php foreach ($activities as $a):
                $cat    = $CATEGORY_MAP[$a['category']] ?? $CATEGORY_MAP['autre'];
                $places = $a['max_participants'] - $a['nb_inscrits'];
                $start  = new DateTime($a['start_time']);
                $auteur = $a['pseudo'] ?: $a['prenom'];
            ?>
            <a href="/sharetime/public/?page=detail&id=<?= $a['idactivities'] ?>" class="activity-card">
                <div class="card-image <?= $cat[1] ?>">
                    <?= $cat[0] ?>
                    <span class="card-badge"><?= htmlspecialchars($a['city']) ?></span>
                    <span class="card-badge-vis"><?= $a['visibility'] === 'publique' ? 'Public' : 'Privé' ?></span>
                </div>
                <div class="card-body">
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

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <?php
            $page_qs = http_build_query(array_filter([
                'page'     => 'activites',
                'city'     => $city_filter,
                'search'   => $title_filter,
                'category' => $category_filter,
                'statut'   => $status_filter,
            ]));
            ?>
            <div style="display:flex; justify-content:center; align-items:center; gap:8px; margin-top:40px; flex-wrap:wrap;">
                <?php if ($current_page > 1): ?>
                    <a href="/sharetime/public/?<?= $page_qs ?>&p=<?= $current_page - 1 ?>"
                       class="btn btn-outline-navy btn-sm">← Précédent</a>
                <?php endif; ?>

                <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                    <a href="/sharetime/public/?<?= $page_qs ?>&p=<?= $i ?>"
                       style="display:inline-flex; align-items:center; justify-content:center;
                              width:36px; height:36px; border-radius:8px; font-size:0.9rem; font-weight:600;
                              text-decoration:none;
                              background:<?= $i === $current_page ? 'var(--navy)' : 'var(--gray-100)' ?>;
                              color:<?= $i === $current_page ? 'white' : 'var(--gray-600)' ?>;">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="/sharetime/public/?<?= $page_qs ?>&p=<?= $current_page + 1 ?>"
                       class="btn btn-outline-navy btn-sm">Suivant →</a>
                <?php endif; ?>
            </div>
            <p style="text-align:center; color:var(--gray-400); font-size:0.82rem; margin-top:12px;">
                Page <?= $current_page ?> / <?= $total_pages ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>

</main>
