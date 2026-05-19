<?php
/**
 * public/pages/carte.php — Carte interactive des activités géolocalisées
 *
 * Variables disponibles (préparées par index.php) :
 *   $activityModel : instance du modèle Activity (injectée par index.php)
 *   $CATEGORY_MAP  : mapping catégorie → [emoji, classe CSS, libellé]
 *
 * Affiche une carte Leaflet (OpenStreetMap) avec :
 *   - Sidebar gauche : liste des activités localisées, cliquable pour centrer la carte
 *   - Carte droite : marqueurs colorés par catégorie avec popup de détail
 *
 * Seules les activités ayant des coordonnées GPS (latitude + longitude non nulles)
 * sont affichées (getForMap() filtre déjà sur is NOT NULL).
 *
 * Responsive : sur mobile (≤640px), la sidebar passe au-dessus de la carte.
 */

// Charge toutes les activités actives ayant des coordonnées GPS
$map_activities = $activityModel->getForMap();

// Couleurs hexadécimales des marqueurs SVG sur la carte, par catégorie
$category_marker_colors = [
    'sport'      => '#EF4444',   // rouge vif
    'creativite' => '#8B5CF6',   // violet
    'nature'     => '#10B981',   // vert
    'social'     => '#3B82F6',   // bleu
    'culture'    => '#F59E0B',   // ambre
    'autre'      => '#6B7280',   // gris neutre
];
?>
<link rel="stylesheet" href="/sharetime/public/css/leaflet.css">
<style>
/* Conteneur principal : sidebar + carte côte à côte, hauteur = fenêtre moins la navbar */
.carte-layout {
    display: flex;
    height: calc(100vh - 65px);
}
/* Sidebar gauche : largeur fixe, scrollable, séparée de la carte par une bordure */
.carte-sidebar {
    width: 320px;
    flex-shrink: 0;
    background: white;
    border-right: 1.5px solid var(--gray-200);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
/* En-tête de la sidebar : titre + compteur, non scrollable */
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
/* Zone scrollable de la liste des activités */
.carte-sidebar-list {
    overflow-y: auto;
    flex: 1;
    padding: 8px;
}
/* Card activité dans la sidebar : cliquable via JS pour centrer la carte + ouvrir popup */
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
/* Libellé de catégorie en haut de la card : très petit, tout majuscule */
.carte-card-cat   { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 4px; }
.carte-card-title { font-size: 0.9rem; font-weight: 700; color: var(--navy); margin: 0 0 4px; }
.carte-card-meta  { font-size: 0.76rem; color: var(--gray-500); margin: 0; line-height: 1.5; }
/* Zone carte Leaflet : prend tout l'espace restant */
#map {
    flex: 1;
    min-width: 0;   /* évite le débordement dans le flex container */
}
/* État vide : aucune activité localisée */
.carte-empty { padding: 32px 16px; text-align: center; color: var(--gray-400); }
.carte-empty-icon { font-size: 2.5rem; margin-bottom: 12px; }
.carte-empty p { font-size: 0.85rem; margin: 4px 0; }
/* Styles de la popup Leaflet : arrondie, sans padding interne par défaut */
.leaflet-popup-content-wrapper { border-radius: 12px !important; padding: 0 !important; box-shadow: 0 4px 20px rgba(0,0,0,.15) !important; }
.leaflet-popup-content { margin: 0 !important; width: 220px !important; }
/* Classes CSS pour le contenu HTML de la popup (généré en JS) */
.mp       { padding: 14px 16px; font-family: inherit; }
.mp-cat   { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px; }
.mp-title { font-size: .9rem; font-weight: 700; color: var(--navy); margin: 0 0 4px; line-height: 1.3; }
.mp-meta  { font-size: .75rem; color: var(--gray-500); margin: 0 0 10px; line-height: 1.5; }
.mp-btn   { display: inline-block; background: var(--orange); color: white; font-size: .75rem; font-weight: 600; padding: 6px 14px; border-radius: 8px; text-decoration: none; }
.mp-btn:hover { background: #d4721a; }
/* Responsive mobile : sidebar au-dessus de la carte, hauteur réduite */
@media (max-width: 640px) {
    .carte-layout { flex-direction: column; }
    .carte-sidebar { width: 100%; height: 200px; border-right: none; border-bottom: 1.5px solid var(--gray-200); }
    #map { flex: 1; min-height: 300px; }
}
</style>

<div class="carte-layout">

    <!-- ── Sidebar : liste des activités localisées ─────────────────────── -->
    <aside class="carte-sidebar">
        <div class="carte-sidebar-head">
            <h1>Activités à proximité</h1>
            <!-- Compteur avec accord au pluriel -->
            <p><?= count($map_activities) ?> activité<?= count($map_activities) !== 1 ? 's' : '' ?> localisée<?= count($map_activities) !== 1 ? 's' : '' ?></p>
        </div>
        <div class="carte-sidebar-list">
            <?php if (empty($map_activities)): ?>
                <!-- État vide : aucune activité n'a encore été géolocalisée lors de la création -->
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
                <?php foreach ($map_activities as $map_activity_item):
                    // Récupère les infos de catégorie (emoji, classe CSS, libellé) pour la card
                    $category_info   = $CATEGORY_MAP[$map_activity_item['category']] ?? $CATEGORY_MAP['autre'];
                    // Couleur hexadécimale du marqueur SVG correspondant à la catégorie
                    $marker_color    = $category_marker_colors[$map_activity_item['category']] ?? '#6B7280';
                    // Objet DateTime pour formater la date de début
                    $start_datetime  = new DateTime($map_activity_item['start_time']);
                ?>
                <!-- data-lat/lng/id transmis au JS pour centrer la carte au clic -->
                <a class="carte-card" href="/sharetime/public/?page=detail&id=<?= (int)$map_activity_item['idactivities'] ?>"
                   data-lat="<?= (float)$map_activity_item['latitude'] ?>"
                   data-lng="<?= (float)$map_activity_item['longitude'] ?>"
                   data-id="<?= (int)$map_activity_item['idactivities'] ?>">
                    <!-- Libellé de catégorie coloré selon la catégorie -->
                    <p class="carte-card-cat" style="color:<?= $marker_color ?>">
                        <?= $category_info[0] ?> <?= htmlspecialchars($category_info[2]) ?>
                    </p>
                    <p class="carte-card-title"><?= htmlspecialchars($map_activity_item['title']) ?></p>
                    <p class="carte-card-meta">
                        📅 <?= $start_datetime->format('d/m/Y à H\hi') ?><br>
                        📍 <?= htmlspecialchars($map_activity_item['location']) ?>, <?= htmlspecialchars($map_activity_item['city']) ?>
                    </p>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <!-- ── Zone carte Leaflet ─────────────────────────────────────────── -->
    <div id="map"></div>

</div>

<script src="/sharetime/public/js/leaflet.js"></script>
<script>
(function () {
    // Sérialise les données d'activités PHP → JSON pour le JS de la carte
    // Chaque entrée contient toutes les infos nécessaires aux marqueurs et popups
    var mapActivities = <?= json_encode(array_map(function ($map_activity_item) use ($CATEGORY_MAP, $category_marker_colors) {
        return [
            'id'       => (int)$map_activity_item['idactivities'],
            'title'    => $map_activity_item['title'],
            'city'     => $map_activity_item['city'],
            'location' => $map_activity_item['location'],
            'category' => $map_activity_item['category'],
            'emoji'    => $CATEGORY_MAP[$map_activity_item['category']][0] ?? '⭐',
            'label'    => $CATEGORY_MAP[$map_activity_item['category']][2] ?? 'Autre',
            'color'    => $category_marker_colors[$map_activity_item['category']] ?? '#6B7280',
            'date'     => $map_activity_item['start_time'],
            'creator'  => $map_activity_item['pseudo'] ?: ($map_activity_item['prenom'] . ' ' . $map_activity_item['nom']),
            'lat'      => (float)$map_activity_item['latitude'],
            'lng'      => (float)$map_activity_item['longitude'],
        ];
    }, $map_activities), JSON_UNESCAPED_UNICODE) ?>;

    /* Initialise la carte Leaflet centrée sur la France (zoom national) */
    var map = L.map('map', { center: [46.5, 2.5], zoom: 6 });

    /* Fond de carte OpenStreetMap */
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);

    /* Force le recalcul de la taille après rendu complet (évite la carte grise) */
    setTimeout(function () { map.invalidateSize(); }, 200);

    if (!mapActivities.length) return;

    /* Échappe les caractères HTML dans les chaînes insérées dans les popups */
    function escapeHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* markers : dictionnaire id → marker Leaflet, pour retrouver un marker par ID au clic sidebar */
    var markers = {};
    /* bounds : rectangle englobant tous les markers, pour le zoom automatique */
    var allMarkersBounds  = L.latLngBounds();

    mapActivities.forEach(function (activityData) {
        /* Marqueur SVG coloré : pin pointant vers le bas, cercle blanc en son centre */
        var markerIcon = L.divIcon({
            className: '',
            html: '<svg width="28" height="40" viewBox="0 0 28 40" xmlns="http://www.w3.org/2000/svg">'
                + '<path d="M14 0C6.268 0 0 6.268 0 14c0 9.333 14 26 14 26S28 23.333 28 14C28 6.268 21.732 0 14 0z" fill="' + activityData.color + '" stroke="white" stroke-width="2"/>'
                + '<circle cx="14" cy="14" r="6" fill="white"/>'
                + '</svg>',
            iconSize:    [28, 40],
            iconAnchor:  [14, 40],   // point d'ancrage = bas du pin
            popupAnchor: [0, -42]    // popup s'ouvre au-dessus du pin
        });

        /* Formate la date de début pour l'affichage dans la popup */
        var startDate       = new Date(activityData.date.replace(' ', 'T'));
        var startDateLabel  = startDate.toLocaleDateString('fr-FR', { day:'numeric', month:'long', year:'numeric' });

        /* HTML de la popup : catégorie + titre + lieu/date + lien vers la page de détail */
        var popupHtml = '<div class="mp">'
            + '<p class="mp-cat" style="color:' + activityData.color + '">' + activityData.emoji + ' ' + activityData.label + '</p>'
            + '<p class="mp-title">' + escapeHtml(activityData.title) + '</p>'
            + '<p class="mp-meta">📅 ' + startDateLabel + '<br>📍 ' + escapeHtml(activityData.location) + ', ' + escapeHtml(activityData.city) + '</p>'
            + '<a class="mp-btn" href="/sharetime/public/?page=detail&id=' + activityData.id + '">Voir l\'activité →</a>'
            + '</div>';

        /* Crée le marker Leaflet et l'ajoute à la carte */
        var leafletMarker = L.marker([activityData.lat, activityData.lng], { icon: markerIcon })
                             .addTo(map)
                             .bindPopup(popupHtml, { maxWidth: 250 });

        markers[activityData.id] = leafletMarker;
        allMarkersBounds.extend([activityData.lat, activityData.lng]);
    });

    /* Zoom automatique : sur le seul marqueur si 1 activité, sur tous sinon */
    if (mapActivities.length === 1) {
        map.setView([mapActivities[0].lat, mapActivities[0].lng], 14);
        markers[mapActivities[0].id].openPopup();
    } else {
        map.fitBounds(allMarkersBounds, { padding: [60, 60], maxZoom: 13 });
    }

    /* Clic sur une card de la sidebar → centrer la carte sur ce marqueur + ouvrir sa popup */
    document.querySelectorAll('.carte-card[data-id]').forEach(function (sidebarCard) {
        sidebarCard.addEventListener('click', function (clickEvent) {
            clickEvent.preventDefault();  // évite la navigation vers la page détail
            var activityId = parseInt(sidebarCard.dataset.id);
            var cardLat    = parseFloat(sidebarCard.dataset.lat);
            var cardLng    = parseFloat(sidebarCard.dataset.lng);
            map.setView([cardLat, cardLng], 15);
            if (markers[activityId]) markers[activityId].openPopup();
        });
    });
})();
</script>
