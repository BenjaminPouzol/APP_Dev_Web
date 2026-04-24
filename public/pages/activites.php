<main class="container" style="padding:40px 0;">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:32px; flex-wrap:wrap; gap:16px;">
        <div>
            <h1 style="color:var(--navy); margin-bottom:4px;">Activités</h1>
            <p style="color:var(--gray-500); font-size:0.9rem;">
                <?= count($activities) ?> activité<?= count($activities) > 1 ? 's' : '' ?> disponible<?= count($activities) > 1 ? 's' : '' ?>
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

    <!-- Chips catégories -->
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px;">
        <a href="/sharetime/public/?page=activites<?= !empty($city_filter) ? '&city='.urlencode($city_filter) : '' ?>"
           style="padding:6px 16px; border-radius:99px; font-size:0.85rem; font-weight:600; text-decoration:none;
                  background:<?= empty($category_filter) ? 'var(--navy)' : 'var(--gray-100)' ?>;
                  color:<?= empty($category_filter) ? 'white' : 'var(--gray-600)' ?>;">
            Toutes
        </a>
        <?php foreach ($CATEGORY_MAP as $val => [$emoji, , $label]): if ($val === 'autre') continue; ?>
        <a href="/sharetime/public/?page=activites&category=<?= $val ?><?= !empty($city_filter) ? '&city='.urlencode($city_filter) : '' ?>"
           style="padding:6px 16px; border-radius:99px; font-size:0.85rem; font-weight:600; text-decoration:none;
                  background:<?= $category_filter === $val ? 'var(--navy)' : 'var(--gray-100)' ?>;
                  color:<?= $category_filter === $val ? 'white' : 'var(--gray-600)' ?>;">
            <?= $emoji ?> <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Filtre ville -->
    <form method="get" action="/sharetime/public/" style="margin-bottom:28px; display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        <input type="hidden" name="page" value="activites">
        <?php if (!empty($category_filter)): ?>
            <input type="hidden" name="category" value="<?= htmlspecialchars($category_filter) ?>">
        <?php endif; ?>
        <input type="text" name="city"
               value="<?= htmlspecialchars($city_filter) ?>"
               placeholder="Filtrer par ville..."
               style="padding:10px 16px; border:1.5px solid var(--gray-300); border-radius:8px; font-size:0.9rem; min-width:220px; font-family:inherit;">
        <button type="submit" class="btn btn-navy">Filtrer</button>
        <?php if (!empty($city_filter) || !empty($category_filter)): ?>
            <a href="/sharetime/public/?page=activites" class="btn btn-outline-navy">✕ Tout réinitialiser</a>
        <?php endif; ?>
    </form>

    <?php if (empty($activities)): ?>
        <div style="text-align:center; padding:80px 0; color:var(--gray-500);">
            <p style="font-size:2.5rem; margin-bottom:16px;">🔍</p>
            <p style="font-size:1.1rem; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Aucune activité trouvée.</p>
            <?php if (!empty($city_filter)): ?>
                <p>Essayez avec une autre ville ou
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
                    <div class="card-title"><?= htmlspecialchars($a['title']) ?></div>
                    <div class="card-meta">
                        <span>📅 <?= $start->format('d/m/Y à H:i') ?></span>
                        <span>📍 <?= htmlspecialchars($a['location']) ?>, <?= htmlspecialchars($a['city']) ?></span>
                        <span>👤 Par <?= htmlspecialchars($auteur) ?></span>
                    </div>
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

</main>
