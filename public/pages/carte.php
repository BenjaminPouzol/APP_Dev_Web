<?php
$map_activities = $activityModel->getForMap();

$CAT_COLORS = [
    'sport'      => '#EF4444',
    'creativite' => '#8B5CF6',
    'nature'     => '#10B981',
    'social'     => '#3B82F6',
    'culture'    => '#F59E0B',
    'autre'      => '#6B7280',
];
?>
<link rel="stylesheet" href="/sharetime/public/css/leaflet.css">
<style>
.carte-layout {
    display: flex;
    height: calc(100vh - 65px);
}
.carte-sidebar {
    width: 320px;
    flex-shrink: 0;
    background: white;
    border-right: 1.5px solid var(--gray-200);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.carte-sidebar-head {
    padding: 16px;
    border-bottom: 1.5px solid var(--gray-200);
    flex-shrink: 0;
}
.carte-sidebar-head h1 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--navy);
    margin: 0 0 4px;
}
.carte-sidebar-head p {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin: 0;
}
.carte-sidebar-list {
    overflow-y: auto;
    flex: 1;
    padding: 8px;
}
.carte-card {
    padding: 12px;
    border-radius: 10px;
    border: 1.5px solid var(--gray-200);
    margin-bottom: 8px;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s;
    text-decoration: none;
    display: block;
    background: white;
}
.carte-card:hover {
    border-color: var(--orange);
    box-shadow: 0 2px 8px rgba(232,129,26,0.15);
}
.carte-card-cat {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 4px;
}
.carte-card-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--navy);
    margin: 0 0 4px;
}
.carte-card-meta {
    font-size: 0.76rem;
    color: var(--gray-500);
    margin: 0;
    line-height: 1.5;
}
#map {
    flex: 1;
    min-width: 0;
}
.carte-empty {
    padding: 32px 16px;
    text-align: center;
    color: var(--gray-400);
}
.carte-empty-icon { font-size: 2.5rem; margin-bottom: 12px; }
.carte-empty p { font-size: 0.85rem; margin: 4px 0; }
/* popup */
.leaflet-popup-content-wrapper { border-radius: 12px !important; padding: 0 !important; box-shadow: 0 4px 20px rgba(0,0,0,.15) !important; }
.leaflet-popup-content { margin: 0 !important; width: 220px !important; }
.mp { padding: 14px 16px; font-family: inherit; }
.mp-cat { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px; }
.mp-title { font-size: .9rem; font-weight: 700; color: var(--navy); margin: 0 0 4px; line-height: 1.3; }
.mp-meta { font-size: .75rem; color: var(--gray-500); margin: 0 0 10px; line-height: 1.5; }
.mp-btn { display: inline-block; background: var(--orange); color: white; font-size: .75rem; font-weight: 600; padding: 6px 14px; border-radius: 8px; text-decoration: none; }
.mp-btn:hover { background: #d4721a; }
@media (max-width: 640px) {
    .carte-layout { flex-direction: column; }
    .carte-sidebar { width: 100%; height: 200px; border-right: none; border-bottom: 1.5px solid var(--gray-200); }
    #map { flex: 1; min-height: 300px; }
}
</style>

<div class="carte-layout">

    <!-- ── Sidebar liste des activités ── -->
    <aside class="carte-sidebar">
        <div class="carte-sidebar-head">
            <h1>Activités à proximité</h1>
            <p><?= count($map_activities) ?> activité<?= count($map_activities) !== 1 ? 's' : '' ?> localisée<?= count($map_activities) !== 1 ? 's' : '' ?></p>
        </div>
        <div class="carte-sidebar-list">
            <?php if (empty($map_activities)): ?>
                <div class="carte-empty">
                    <div class="carte-empty-icon">🗺️</div>
                    <p style="font-weight:600; color:var(--navy);">Aucune activité localisée</p>
                    <p>Les activités apparaissent ici une fois géolocalisées lors de leur création.</p>
                    <?php if (isset($_SESSION['user'])): ?>
                    <a href="/sharetime/public/?page=creer" class="btn btn-orange btn-sm" style="margin-top:12px; display:inline-block;">
                        + Créer une activité
                    </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($map_activities as $a):
                    $cat   = $CATEGORY_MAP[$a['category']] ?? $CATEGORY_MAP['autre'];
                    $color = $CAT_COLORS[$a['category']]   ?? '#6B7280';
                    $dt    = new DateTime($a['start_time']);
                ?>
                <a class="carte-card" href="/sharetime/public/?page=detail&id=<?= (int)$a['idactivities'] ?>"
                   data-lat="<?= (float)$a['latitude'] ?>" data-lng="<?= (float)$a['longitude'] ?>"
                   data-id="<?= (int)$a['idactivities'] ?>">
                    <p class="carte-card-cat" style="color:<?= $color ?>">
                        <?= $cat[0] ?> <?= htmlspecialchars($cat[2]) ?>
                    </p>
                    <p class="carte-card-title"><?= htmlspecialchars($a['title']) ?></p>
                    <p class="carte-card-meta">
                        📅 <?= $dt->format('d/m/Y à H\hi') ?><br>
                        📍 <?= htmlspecialchars($a['location']) ?>, <?= htmlspecialchars($a['city']) ?>
                    </p>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <!-- ── Carte Leaflet ── -->
    <div id="map"></div>

</div>

<script src="/sharetime/public/js/leaflet.js"></script>
<script>
(function () {
    var activities = <?= json_encode(array_map(function ($a) use ($CATEGORY_MAP, $CAT_COLORS) {
        return [
            'id'       => (int)$a['idactivities'],
            'title'    => $a['title'],
            'city'     => $a['city'],
            'location' => $a['location'],
            'category' => $a['category'],
            'emoji'    => $CATEGORY_MAP[$a['category']][0] ?? '⭐',
            'label'    => $CATEGORY_MAP[$a['category']][2] ?? 'Autre',
            'color'    => $CAT_COLORS[$a['category']] ?? '#6B7280',
            'date'     => $a['start_time'],
            'creator'  => $a['pseudo'] ?: ($a['prenom'] . ' ' . $a['nom']),
            'lat'      => (float)$a['latitude'],
            'lng'      => (float)$a['longitude'],
        ];
    }, $map_activities), JSON_UNESCAPED_UNICODE) ?>;

    /* Initialisation de la carte */
    var map = L.map('map', { center: [46.5, 2.5], zoom: 6 });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);

    /* Recalcul de taille après rendu complet */
    setTimeout(function () { map.invalidateSize(); }, 200);

    if (!activities.length) return;

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    var markers = {};
    var bounds  = L.latLngBounds();

    activities.forEach(function (a) {
        /* Marqueur coloré avec pin SVG natif de Leaflet */
        var icon = L.divIcon({
            className: '',
            html: '<svg width="28" height="40" viewBox="0 0 28 40" xmlns="http://www.w3.org/2000/svg">'
                + '<path d="M14 0C6.268 0 0 6.268 0 14c0 9.333 14 26 14 26S28 23.333 28 14C28 6.268 21.732 0 14 0z" fill="' + a.color + '" stroke="white" stroke-width="2"/>'
                + '<circle cx="14" cy="14" r="6" fill="white"/>'
                + '</svg>',
            iconSize:   [28, 40],
            iconAnchor: [14, 40],
            popupAnchor:[0, -42]
        });

        var d    = new Date(a.date.replace(' ', 'T'));
        var date = d.toLocaleDateString('fr-FR', { day:'numeric', month:'long', year:'numeric' });

        var popup = '<div class="mp">'
            + '<p class="mp-cat" style="color:' + a.color + '">' + a.emoji + ' ' + a.label + '</p>'
            + '<p class="mp-title">' + esc(a.title) + '</p>'
            + '<p class="mp-meta">📅 ' + date + '<br>📍 ' + esc(a.location) + ', ' + esc(a.city) + '</p>'
            + '<a class="mp-btn" href="/sharetime/public/?page=detail&id=' + a.id + '">Voir l\'activité →</a>'
            + '</div>';

        var m = L.marker([a.lat, a.lng], { icon: icon })
                 .addTo(map)
                 .bindPopup(popup, { maxWidth: 250 });

        markers[a.id] = m;
        bounds.extend([a.lat, a.lng]);
    });

    /* Zoom automatique sur les marqueurs */
    if (activities.length === 1) {
        map.setView([activities[0].lat, activities[0].lng], 14);
        markers[activities[0].id].openPopup();
    } else {
        map.fitBounds(bounds, { padding: [60, 60], maxZoom: 13 });
    }

    /* Clic sur une carte de la sidebar → centrer + ouvrir popup */
    document.querySelectorAll('.carte-card[data-id]').forEach(function (card) {
        card.addEventListener('click', function (e) {
            e.preventDefault();
            var id  = parseInt(card.dataset.id);
            var lat = parseFloat(card.dataset.lat);
            var lng = parseFloat(card.dataset.lng);
            map.setView([lat, lng], 15);
            if (markers[id]) markers[id].openPopup();
        });
    });
})();
</script>
