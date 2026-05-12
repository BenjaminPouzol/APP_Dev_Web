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
<style>
    /* Hauteur directe en px — pas de flex, pas de calc dépendant du DOM */
    #carte-header { background:white; border-bottom:1.5px solid var(--gray-200); padding:12px 0; }
    #carte-header h1 { font-size:1rem; font-weight:700; color:var(--navy); margin:0 0 2px; }
    #carte-header .sub { font-size:0.8rem; color:var(--gray-500); }
    #carte-header .legend { display:flex; flex-wrap:wrap; gap:10px; margin-top:8px; }
    #carte-header .legend-item { display:flex; align-items:center; gap:5px; font-size:0.75rem; color:var(--gray-600); font-weight:500; }
    #carte-header .legend-dot { width:10px; height:10px; border-radius:50%; border:2px solid white; box-shadow:0 1px 3px rgba(0,0,0,.3); }

    #map {
        width: 100%;
        height: 78vh;
        min-height: 450px;
        display: block;
    }

    /* Popup personnalisée */
    .leaflet-popup-content-wrapper { border-radius:12px !important; padding:0 !important; box-shadow:0 4px 20px rgba(0,0,0,.15) !important; }
    .leaflet-popup-content { margin:0 !important; width:230px !important; }
    .mp { padding:14px 16px; font-family:inherit; }
    .mp-cat { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; margin-bottom:4px; }
    .mp-title { font-size:.92rem; font-weight:700; color:var(--navy); margin:0 0 4px; line-height:1.3; }
    .mp-meta { font-size:.76rem; color:var(--gray-500); margin:0 0 10px; line-height:1.5; }
    .mp-btn { display:inline-block; background:var(--orange); color:white; font-size:.76rem; font-weight:600; padding:6px 14px; border-radius:8px; text-decoration:none; }
    .mp-btn:hover { background:#d4721a; }
    .map-dot { width:14px; height:14px; border-radius:50%; border:2.5px solid white; box-shadow:0 2px 6px rgba(0,0,0,.4); cursor:pointer; }
</style>

<div id="carte-header">
    <div class="container">
        <h1>Carte des activités</h1>
        <p class="sub">
            <?= count($map_activities) ?> activité<?= count($map_activities) !== 1 ? 's' : '' ?> localisée<?= count($map_activities) !== 1 ? 's' : '' ?>
            &nbsp;·&nbsp;
            <a href="/sharetime/public/?page=activites" style="color:var(--orange);text-decoration:none;font-weight:600;">Voir la liste</a>
        </p>
        <div class="legend">
            <?php foreach ($CATEGORY_MAP as $key => [$emoji, , $label]): ?>
            <div class="legend-item">
                <div class="legend-dot" style="background:<?= $CAT_COLORS[$key] ?? '#6B7280' ?>"></div>
                <?= $emoji ?> <?= htmlspecialchars($label) ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Le CSS Leaflet doit être chargé avant que le div#map soit dimensionné -->
<link rel="stylesheet" href="/sharetime/public/css/leaflet.css">
<div id="map"></div>
<script src="/sharetime/public/js/leaflet.js"></script>
<script>
(function () {
    var el = document.getElementById('map');

    var map = L.map(el, { center: [46.5, 2.5], zoom: 6 });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);

    /* Force Leaflet à recalculer la taille du conteneur après le rendu */
    setTimeout(function () { map.invalidateSize(); }, 100);

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

    if (!activities.length) return;

    function esc(s) {
        return String(s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    var bounds = L.latLngBounds();

    activities.forEach(function (a) {
        var icon = L.divIcon({
            className: '',
            html: '<div class="map-dot" style="background:' + a.color + '"></div>',
            iconSize: [14, 14], iconAnchor: [7, 7], popupAnchor: [0, -10]
        });

        var d = new Date(a.date.replace(' ', 'T'));
        var dateStr = d.toLocaleDateString('fr-FR', { weekday:'short', day:'numeric', month:'long' });

        var html = '<div class="mp">'
            + '<p class="mp-cat" style="color:' + a.color + '">' + a.emoji + ' ' + a.label + '</p>'
            + '<p class="mp-title">' + esc(a.title) + '</p>'
            + '<p class="mp-meta">📅 ' + dateStr + '<br>📍 ' + esc(a.location) + ', ' + esc(a.city) + '</p>'
            + '<a class="mp-btn" href="/sharetime/public/?page=detail&id=' + a.id + '">Voir →</a>'
            + '</div>';

        L.marker([a.lat, a.lng], { icon: icon })
         .addTo(map)
         .bindPopup(html, { maxWidth: 260 });

        bounds.extend([a.lat, a.lng]);
    });

    activities.length === 1
        ? map.setView([activities[0].lat, activities[0].lng], 13)
        : map.fitBounds(bounds, { padding: [60, 60], maxZoom: 13 });
})();
</script>
