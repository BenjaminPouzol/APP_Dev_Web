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
<!-- Leaflet CDN — plus fiable que les fichiers locaux pour l'initialisation -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

<style>
    .map-wrap {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 65px); /* 65px = hauteur navbar */
    }
    .map-bar {
        background: white;
        border-bottom: 1.5px solid var(--gray-200);
        padding: 10px 0;
        flex-shrink: 0;
    }
    .map-bar-inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }
    .map-bar h1 { font-size: 1rem; font-weight: 700; color: var(--navy); margin: 0; }
    .map-bar-sub { font-size: 0.8rem; color: var(--gray-500); margin: 2px 0 0; }
    .map-legend { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .map-legend-item { display: flex; align-items: center; gap: 5px; font-size: 0.75rem; color: var(--gray-600); font-weight: 500; }
    .map-legend-dot { width: 10px; height: 10px; border-radius: 50%; border: 2px solid white; box-shadow: 0 1px 3px rgba(0,0,0,.3); flex-shrink: 0; }

    #map { flex: 1; min-height: 0; z-index: 0; }

    .leaflet-popup-content-wrapper { border-radius: 12px !important; box-shadow: 0 4px 20px rgba(0,0,0,.15) !important; padding: 0 !important; }
    .leaflet-popup-content { margin: 0 !important; width: 230px !important; }
    .map-popup { padding: 14px 16px; font-family: inherit; }
    .map-popup-cat { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px; }
    .map-popup-title { font-size: 0.92rem; font-weight: 700; color: var(--navy); margin: 0 0 4px; line-height: 1.3; }
    .map-popup-meta { font-size: 0.77rem; color: var(--gray-500); margin: 0 0 10px; line-height: 1.5; }
    .map-popup-btn { display: inline-block; background: var(--orange); color: white; font-size: 0.77rem; font-weight: 600; padding: 6px 14px; border-radius: 8px; text-decoration: none; }
    .map-popup-btn:hover { background: #d4721a; }

    .map-marker-icon { width: 16px; height: 16px; border-radius: 50%; border: 2.5px solid white; box-shadow: 0 2px 6px rgba(0,0,0,.4); cursor: pointer; transition: transform .15s; }
    .map-marker-icon:hover { transform: scale(1.4); }

    @media (max-width: 640px) { .map-legend { display: none; } }
</style>

<div class="map-wrap">

    <div class="map-bar">
        <div class="container map-bar-inner">
            <div>
                <h1>Carte des activités</h1>
                <p class="map-bar-sub">
                    <?= count($map_activities) ?> activité<?= count($map_activities) !== 1 ? 's' : '' ?> localisée<?= count($map_activities) !== 1 ? 's' : '' ?>
                    &nbsp;·&nbsp; <a href="/sharetime/public/?page=activites" style="color:var(--orange);text-decoration:none;font-weight:600;">Voir la liste</a>
                </p>
            </div>
            <div class="map-legend">
                <?php foreach ($CATEGORY_MAP as $key => [$emoji, , $label]): ?>
                <div class="map-legend-item">
                    <div class="map-legend-dot" style="background:<?= $CAT_COLORS[$key] ?? '#6B7280' ?>;"></div>
                    <?= $emoji ?> <?= htmlspecialchars($label) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="map"></div>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WLs=" crossorigin=""></script>
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

    var map = L.map('map', { center: [46.5, 2.5], zoom: 6 });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);

    if (!activities.length) {
        var info = L.control({ position: 'topright' });
        info.onAdd = function () {
            var d = L.DomUtil.create('div');
            d.style.cssText = 'background:white;padding:14px 20px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.12);text-align:center;font-family:inherit;';
            d.innerHTML = '<div style="font-size:1.8rem;margin-bottom:6px;">🗺️</div>'
                        + '<p style="font-weight:700;color:#1E3A6E;margin:0 0 4px;">Aucune activité localisée</p>'
                        + '<p style="font-size:0.8rem;color:#6B7280;margin:0;">Ajoutez une position lors de la création.</p>';
            return d;
        };
        info.addTo(map);
        return;
    }

    var bounds = L.latLngBounds();

    activities.forEach(function (a) {
        var icon = L.divIcon({
            className: '',
            html: '<div class="map-marker-icon" style="background:' + a.color + ';"></div>',
            iconSize: [16, 16],
            iconAnchor: [8, 8],
            popupAnchor: [0, -12]
        });

        var d    = new Date(a.date.replace(' ', 'T'));
        var date = d.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' });

        function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

        var popup = '<div class="map-popup">'
            + '<p class="map-popup-cat" style="color:' + a.color + '">' + a.emoji + ' ' + a.label + '</p>'
            + '<p class="map-popup-title">' + esc(a.title) + '</p>'
            + '<p class="map-popup-meta">📅 ' + date + '<br>📍 ' + esc(a.location) + ', ' + esc(a.city) + '<br>👤 ' + esc(a.creator) + '</p>'
            + '<a class="map-popup-btn" href="/sharetime/public/?page=detail&id=' + a.id + '">Voir l\'activité →</a>'
            + '</div>';

        L.marker([a.lat, a.lng], { icon: icon }).addTo(map).bindPopup(popup, { maxWidth: 260 });
        bounds.extend([a.lat, a.lng]);
    });

    activities.length === 1
        ? map.setView([activities[0].lat, activities[0].lng], 13)
        : map.fitBounds(bounds, { padding: [60, 60], maxZoom: 13 });
})();
</script>
