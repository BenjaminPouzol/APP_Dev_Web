<?php
$emojis        = ['🏃', '🎨', '🌲', '🤝', '🖼️'];
$color_classes = ['sport', 'atelier', 'sortie', 'club', 'art'];
?>

<main class="container" style="padding:40px 0;">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:32px; flex-wrap:wrap; gap:16px;">
        <div>
            <h1 style="color:var(--navy); margin-bottom:4px;">Activités</h1>
            <p style="color:var(--gray-500); font-size:0.9rem;">
                <?= count($activities) ?> activité<?= count($activities) > 1 ? 's' : '' ?> disponible<?= count($activities) > 1 ? 's' : '' ?>
                <?= !empty($city_filter) ? ' pour "' . htmlspecialchars($city_filter) . '"' : '' ?>
            </p>
        </div>
        <?php if (isset($_SESSION['user'])): ?>
            <a href="/sharetime/public/?page=creer" class="btn btn-orange btn-lg">+ Créer une activité</a>
        <?php endif; ?>
    </div>

    <!-- Filtres -->
    <form method="get" action="/sharetime/public/" style="margin-bottom:28px; display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        <input type="hidden" name="page" value="activites">
        <input type="text" name="city"
               value="<?= htmlspecialchars($city_filter) ?>"
               placeholder="Filtrer par ville..."
               style="padding:10px 16px; border:1.5px solid var(--gray-300); border-radius:8px; font-size:0.9rem; min-width:220px; font-family:inherit;">
        <button type="submit" class="btn btn-navy">Filtrer</button>
        <?php if (!empty($city_filter)): ?>
            <a href="/sharetime/public/?page=activites" class="btn btn-outline-navy">✕ Réinitialiser</a>
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
                $idx    = $a['idactivities'] % 5;
                $places = $a['max_participants'] - $a['nb_inscrits'];
                $start  = new DateTime($a['start_time']);
                $auteur = $a['pseudo'] ?: $a['prenom'];
            ?>
            <a href="/sharetime/public/?page=detail&id=<?= $a['idactivities'] ?>" class="activity-card">
                <div class="card-image <?= $color_classes[$idx] ?>">
                    <?= $emojis[$idx] ?>
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
