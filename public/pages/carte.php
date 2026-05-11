<?php
/**
 * public/pages/carte.php — Carte interactive des activités
 *
 * Affiche toutes les activités publiques actives ayant des coordonnées
 * sur une carte Leaflet.js (OpenStreetMap, sans clé API).
 * Les marqueurs sont colorés par catégorie et ouvrent une popup
 * avec un lien vers la page de détail de l'activité.
 *
 * Variables disponibles (index.php) : $activityModel, $CATEGORY_MAP
 */

$map_activities = $activityModel->getForMap();

// Couleurs par catégorie (correspondance avec $CATEGORY_MAP)
$CAT_COLORS = [
    'sport'      => '#EF4444',
    'creativite' => '#8B5CF6',
    'nature'     => '#10B981',
    'social'     => '#3B82F6',
    'culture'    => '#F59E0B',
    'autre'      => '#6B7280',
];
?>

<!-- Leaflet CSS (local) -->
<link rel="stylesheet" href="/sharetime/public/css/leaflet.css">

<style>
    .map-page { display: block; }
    .map-header {
        background: white;
        border-bottom: 1.5px solid var(--gray-200);
        padding: 14px 0;
    }
    .map-header-inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }
    .map-title { font-size: 1.1rem; font-weight: 700; color: var(--navy); margin: 0; }
    .map-subtitle { font-size: 0.82rem; color: var(--gray-500); margin: 2px 0 0; }
    .map-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }
    .map-legend-item {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.78rem;
        color: var(--gray-600);
        font-weight: 500;
    }
    .map-legend-dot {
        width: 11px;
        height: 11px;
        border-radius: 50%;
        border: 2px solid white;
        box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        flex-shrink: 0;
    }
    #map { height: 500px; z-index: 0; }

    /* Popup Leaflet personnalisée */
    .leaflet-popup-content-wrapper {
        border-radius: 12px !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important;
        padding: 0 !important;
    }
    .leaflet-popup-content { margin: 0 !important; width: 240px !important; }
    .map-popup {
        padding: 14px 16px;
        font-family: inherit;
    }
    .map-popup-cat {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-bottom: 4px;
    }
    .map-popup-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1E3A6E;
        margin: 0 0 4px;
        line-height: 1.3;
    }
    .map-popup-meta {
        font-size: 0.78rem;
        color: #6B7280;
        margin: 0 0 10px;
        line-height: 1.5;
    }
    .map-popup-link {
        display: inline-block;
        background: #E8811A;
        color: white;
        font-size: 0.78rem;
        font-weight: 600;
        padding: 6px 14px;
        border-radius: 8px;
        text-decoration: none;
    }
    .map-popup-link:hover { background: #d4721a; }

    /* Marqueur personnalisé */
    .map-marker {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 2.5px solid white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.35);
        transition: transform 0.15s;
    }
    .map-marker:hover { transform: scale(1.3); }

    /* Bandeau "aucune activité" */
    .map-empty {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        border-radius: 16px;
        padding: 32px 40px;
        text-align: center;
        box-shadow: 0 4px 24px rgba(0,0,0,0.12);
        z-index: 500;
        pointer-events: none;
    }

    @media (max-width: 640px) {
        .map-legend { display: none; }
        .map-header-inner { flex-direction: column; align-items: flex-start; }
    }
</style>

<main class="map-page">

    <!-- En-tête avec légende -->
    <div class="map-header">
        <div class="container map-header-inner">
            <div>
                <p class="map-title">Carte des activités</p>
                <p class="map-subtitle">
                    <?= count($map_activities) ?> activité<?= count($map_activities) !== 1 ? 's' : '' ?> localisée<?= count($map_activities) !== 1 ? 's' : '' ?>
                    — <a href="/sharetime/public/?page=activites" style="color:var(--orange); text-decoration:none; font-weight:600;">Voir la liste</a>
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

    <!-- Conteneur de la carte -->
    <div id="map" style="position:relative;">
        <?php if (empty($map_activities)): ?>
        <div class="map-empty">
            <div style="font-size:2.5rem; margin-bottom:12px;">🗺️</div>
            <p style="font-weight:700; color:var(--navy); margin:0 0 6px;">Aucune activité localisée</p>
            <p style="font-size:0.85rem; color:var(--gray-500); margin:0;">
                Les activités apparaissent ici une fois<br>géocodées lors de leur création.
            </p>
        </div>
        <?php endif; ?>
    </div>

</main>

<!-- Leaflet JS (local) -->
<script src="/sharetime/public/js/leaflet.js"></script>
<script>
(function() {
    // ── Hauteur dynamique ──────────────────────────────────────────────────────
    // Leaflet requiert une hauteur en pixels sur son conteneur.
    // On calcule l'espace disponible sous la navbar et l'en-tête de la carte.
    var mapEl  = document.getElementById('map');
    var navbar = document.querySelector('.navbar');
    var mapHdr = document.querySelector('.map-header');

    function applyHeight() {
        var navH = navbar ? navbar.offsetHeight : 64;
        var hdrH = mapHdr ? mapHdr.offsetHeight : 0;
        mapEl.style.height = (window.innerHeight - navH - hdrH) + 'px';
    }
    applyHeight();
    // Données des activités encodées depuis PHP
    var activities = <?= json_encode(array_map(function($a) use ($CATEGORY_MAP, $CAT_COLORS) {
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

    // Initialisation de la carte centrée sur la France
    var map = L.map('map', {
        center: [46.5, 2.5],
        zoom: 6,
        zoomControl: true,
    });

    // Fond de carte OpenStreetMap (gratuit, pas de clé API)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 18,
    }).addTo(map);

    // Recalcule la taille si la fenêtre est redimensionnée
    window.addEventListener('resize', function() { applyHeight(); map.invalidateSize(); });

    if (activities.length === 0) return;

    // Groupe de marqueurs pour auto-zoom sur l'ensemble des activités
    var bounds = L.latLngBounds();

    activities.forEach(function(a) {
        var icon = L.divIcon({
            className: '',
            html: '<div class="map-marker" style="background:' + a.color + ';"></div>',
            iconSize: [18, 18],
            iconAnchor: [9, 9],
            popupAnchor: [0, -12],
        });

        // Formatage de la date en français
        var d = new Date(a.date.replace(' ', 'T'));
        var dateStr = d.toLocaleDateString('fr-FR', { weekday:'short', day:'numeric', month:'long', year:'numeric' });

        var popup = '<div class="map-popup">'
            + '<p class="map-popup-cat" style="color:' + a.color + ';">' + a.emoji + ' ' + a.label + '</p>'
            + '<p class="map-popup-title">' + escHtml(a.title) + '</p>'
            + '<p class="map-popup-meta">'
            + '📅 ' + dateStr + '<br>'
            + '📍 ' + escHtml(a.location) + ', ' + escHtml(a.city)
            + '</p>'
            + '<a class="map-popup-link" href="/sharetime/public/?page=detail&id=' + a.id + '">Voir l\'activité</a>'
            + '</div>';

        L.marker([a.lat, a.lng], { icon: icon })
            .addTo(map)
            .bindPopup(popup, { maxWidth: 260 });

        bounds.extend([a.lat, a.lng]);
    });

    // Ajuste le zoom pour montrer tous les marqueurs (avec un peu de marge)
    if (activities.length > 1) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 13 });
    } else {
        map.setView([activities[0].lat, activities[0].lng], 13);
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
</script>
