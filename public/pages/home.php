<?php
$nb_users      = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$nb_activities = $pdo->query("SELECT COUNT(*) FROM activities WHERE status = 'active'")->fetchColumn();
$nb_cities     = $pdo->query("SELECT COUNT(DISTINCT city) FROM activities WHERE city != ''")->fetchColumn();

$emojis        = ['🏃', '🎨', '🌲', '🤝', '🖼️'];
$color_classes = ['sport', 'atelier', 'sortie', 'club', 'art'];
?>

<main>

<!-- ── HERO ── -->
<section class="hero">
    <div class="container hero-content">
        <div class="hero-badge">✨ Partageons l'instant</div>
        <h1>Découvre des activités <span>près de chez toi</span></h1>
        <p class="hero-subtitle">
            Rejoins des événements locaux, rencontre des personnes qui partagent tes passions
            et crée tes propres activités en quelques clics.
        </p>

        <form class="search-bar" action="/sharetime/public/" method="get">
            <input type="hidden" name="page" value="activites">
            <div class="search-bar-icon">🔍</div>
            <input type="text" name="city" placeholder="Rechercher par ville...">
            <button type="submit" class="btn btn-orange">Rechercher</button>
        </form>

        <div class="hero-chips">
            <a href="/sharetime/public/?page=activites" class="chip">🏃 Sport</a>
            <a href="/sharetime/public/?page=activites" class="chip">🎨 Créativité</a>
            <a href="/sharetime/public/?page=activites" class="chip">🌲 Nature</a>
            <a href="/sharetime/public/?page=activites" class="chip">🤝 Social</a>
            <a href="/sharetime/public/?page=activites" class="chip">🖼️ Culture</a>
        </div>
    </div>
</section>

<!-- ── STATS ── -->
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

<!-- ── ACTIVITÉS RÉCENTES ── -->
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
                    if ($shown >= 6) break;
                    $shown++;
                    $idx   = $a['idactivities'] % 5;
                    $places = $a['max_participants'] - $a['nb_inscrits'];
                    $start  = new DateTime($a['start_time']);
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
                            <span>📍 <?= htmlspecialchars($a['location']) ?></span>
                            <span>👤 <?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?></span>
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
    </div>
</section>

<!-- ── CTA ── -->
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
