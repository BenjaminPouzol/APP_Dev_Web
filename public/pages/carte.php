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
$map_activities = $activityModel->getForMap(); // Récupère les activités géolocalisées depuis le modèle (filtrées sur latitude/longitude non nulles)

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
<link rel="stylesheet" href="/sharetime/public/css/leaflet.css"> <!-- Feuille de style de la bibliothèque Leaflet nécessaire au rendu de la carte -->
<style>
/* Conteneur principal : sidebar + carte côte à côte, hauteur = fenêtre moins la navbar */
.carte-layout {
    display: flex;
    height: calc(100vh - 65px); /* Occupe toute la hauteur de fenêtre disponible sous la navbar */
}
/* Sidebar gauche : largeur fixe, scrollable, séparée de la carte par une bordure */
.carte-sidebar {
    width: 320px; /* Largeur fixe de 320px pour la colonne de liste des activités */
    flex-shrink: 0; /* Empêche la sidebar de rétrécir quand la carte manque de place */
    background: white;
    border-right: 1.5px solid var(--gray-200);
    display: flex;
    flex-direction: column;
    overflow: hidden; /* Masque le débordement vertical, géré par .carte-sidebar-list */
}
/* En-tête de la sidebar : titre + compteur, non scrollable */
.carte-sidebar-head {
    padding: 16px;
    border-bottom: 1.5px solid var(--gray-200);
    flex-shrink: 0; /* L'en-tête reste visible et ne participe pas au scroll de la liste */
}
.carte-sidebar-head h1 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--navy);
    margin: 0 0 4px; /* Marge inférieure de 4px entre le titre et le compteur */
}
.carte-sidebar-head p {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin: 0;
}
/* Zone scrollable de la liste des activités */
.carte-sidebar-list {
    overflow-y: auto; /* Active le défilement vertical quand les cards dépassent la hauteur disponible */
    flex: 1; /* Prend tout l'espace restant après l'en-tête */
    padding: 8px;
}
/* Card activité dans la sidebar : cliquable via JS pour centrer la carte + ouvrir popup */
.carte-card {
    padding: 12px;
    border-radius: 10px;
    border: 1.5px solid var(--gray-200);
    margin-bottom: 8px;
    cursor: pointer; /* Curseur pointer pour indiquer que la card est cliquable */
    transition: border-color 0.15s, box-shadow 0.15s; /* Animation douce de la bordure et de l'ombre au survol */
    text-decoration: none; /* Supprime le soulignement du lien <a> */
    display: block; /* Rend le lien <a> de type bloc pour occuper toute la largeur */
    background: white;
}
.carte-card:hover {
    border-color: var(--orange); /* Bordure orange au survol pour indiquer l'interactivité */
    box-shadow: 0 2px 8px rgba(232,129,26,0.15); /* Légère ombre orange au survol */
}
/* Libellé de catégorie en haut de la card : très petit, tout majuscule */
.carte-card-cat   { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 4px; } /* Étiquette de catégorie en petites majuscules avec espacement des lettres */
.carte-card-title { font-size: 0.9rem; font-weight: 700; color: var(--navy); margin: 0 0 4px; } /* Titre de l'activité en gras bleu marine */
.carte-card-meta  { font-size: 0.76rem; color: var(--gray-500); margin: 0; line-height: 1.5; } /* Méta-informations (date, lieu) en gris, interligne confortable */
/* Zone carte Leaflet : prend tout l'espace restant */
#map {
    flex: 1; /* La carte occupe tout l'espace horizontal restant après la sidebar */
    min-width: 0;   /* évite le débordement dans le flex container */
}
/* État vide : aucune activité localisée */
.carte-empty { padding: 32px 16px; text-align: center; color: var(--gray-400); } /* Bloc centré affiché dans la sidebar quand aucune activité n'est géolocalisée */
.carte-empty-icon { font-size: 2.5rem; margin-bottom: 12px; } /* Icône de l'état vide, grande pour attirer l'attention */
.carte-empty p { font-size: 0.85rem; margin: 4px 0; } /* Textes d'explication de l'état vide */
/* Styles de la popup Leaflet : arrondie, sans padding interne par défaut */
.leaflet-popup-content-wrapper { border-radius: 12px !important; padding: 0 !important; box-shadow: 0 4px 20px rgba(0,0,0,.15) !important; } /* Surcharge du style Leaflet pour des popups arrondies avec ombre personnalisée */
.leaflet-popup-content { margin: 0 !important; width: 220px !important; } /* Largeur fixe de la popup et suppression des marges internes par défaut */
/* Classes CSS pour le contenu HTML de la popup (généré en JS) */
.mp       { padding: 14px 16px; font-family: inherit; } /* Conteneur principal de la popup avec paddings et police héritée */
.mp-cat   { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px; } /* Libellé de catégorie en petites majuscules colorées dans la popup */
.mp-title { font-size: .9rem; font-weight: 700; color: var(--navy); margin: 0 0 4px; line-height: 1.3; } /* Titre de l'activité en gras bleu marine dans la popup */
.mp-meta  { font-size: .75rem; color: var(--gray-500); margin: 0 0 10px; line-height: 1.5; } /* Méta-informations (date, lieu) en gris dans la popup */
.mp-btn   { display: inline-block; background: var(--orange); color: white; font-size: .75rem; font-weight: 600; padding: 6px 14px; border-radius: 8px; text-decoration: none; } /* Bouton orange "Voir l'activité" dans la popup */
.mp-btn:hover { background: #d4721a; } /* Assombrit le bouton orange au survol */
/* Responsive mobile : sidebar au-dessus de la carte, hauteur réduite */
@media (max-width: 640px) {
    .carte-layout { flex-direction: column; } /* Sur mobile, la sidebar et la carte s'empilent verticalement */
    .carte-sidebar { width: 100%; height: 200px; border-right: none; border-bottom: 1.5px solid var(--gray-200); } /* La sidebar occupe toute la largeur et une hauteur fixe de 200px sur mobile */
    #map { flex: 1; min-height: 300px; } /* La carte occupe le reste de l'écran avec au moins 300px de hauteur */
}
</style>

<div class="carte-layout">

    <!-- ── Sidebar : liste des activités localisées ─────────────────────── -->
    <aside class="carte-sidebar">
        <div class="carte-sidebar-head">
            <h1>Activités à proximité</h1>
            <!-- Compteur avec accord au pluriel -->
            <p><?= count($map_activities) ?> activité<?= count($map_activities) !== 1 ? 's' : '' ?> localisée<?= count($map_activities) !== 1 ? 's' : '' ?></p> <!-- Affiche le nombre d'activités localisées avec accord automatique au pluriel -->
        </div>
        <div class="carte-sidebar-list">
            <?php if (empty($map_activities)): // Aucune activité géolocalisée dans la base de données ?>
                <!-- État vide : aucune activité n'a encore été géolocalisée lors de la création -->
                <div class="carte-empty">
                    <div class="carte-empty-icon">🗺️</div>
                    <p style="font-weight:600; color:var(--navy);">Aucune activité localisée</p>
                    <p>Les activités apparaissent ici une fois géolocalisées lors de leur création.</p>
                    <?php if (isset($_SESSION['user'])): // Propose la création uniquement aux utilisateurs connectés ?>
                    <a href="/sharetime/public/?page=creer" class="btn btn-orange btn-sm" style="margin-top:12px; display:inline-block;">
                        + Créer une activité <!-- Bouton d'appel à l'action pour créer une activité géolocalisée -->
                    </a>
                    <?php endif; ?>
                </div>
            <?php else: // Des activités géolocalisées existent : on les affiche dans la sidebar ?>
                <?php foreach ($map_activities as $map_activity_item): // Parcourt chaque activité géolocalisée pour créer une card dans la sidebar
                    // Récupère les infos de catégorie (emoji, classe CSS, libellé) pour la card
                    $category_info   = $CATEGORY_MAP[$map_activity_item['category']] ?? $CATEGORY_MAP['autre'];
                    // Couleur hexadécimale du marqueur SVG correspondant à la catégorie
                    $marker_color    = $category_marker_colors[$map_activity_item['category']] ?? '#6B7280';
                    // Objet DateTime pour formater la date de début
                    $start_datetime  = new DateTime($map_activity_item['start_time']);
                ?>
                <!-- data-lat/lng/id transmis au JS pour centrer la carte au clic -->
                <!-- Lien vers le détail intercepté par le JS (data-lat/lng/id transmis au script pour centrer la carte) -->
                <a class="carte-card" href="/sharetime/public/?page=detail&id=<?= (int)$map_activity_item['idactivities'] ?>"
                   data-lat="<?= (float)$map_activity_item['latitude'] ?>"
                   data-lng="<?= (float)$map_activity_item['longitude'] ?>"
                   data-id="<?= (int)$map_activity_item['idactivities'] ?>">
                    <!-- Libellé de catégorie coloré selon la catégorie -->
                    <p class="carte-card-cat" style="color:<?= $marker_color ?>"> <!-- Couleur du texte de catégorie correspond à la couleur du marqueur sur la carte -->
                        <?= $category_info[0] ?> <?= htmlspecialchars($category_info[2]) ?> <!-- Affiche l'emoji suivi du libellé de la catégorie -->
                    </p>
                    <p class="carte-card-title"><?= htmlspecialchars($map_activity_item['title']) ?></p> <!-- Titre de l'activité, échappé contre les injections XSS -->
                    <p class="carte-card-meta">
                        📅 <?= $start_datetime->format('d/m/Y à H\hi') ?><br> <!-- Date et heure de début de l'activité au format français -->
                        📍 <?= htmlspecialchars($map_activity_item['location']) ?>, <?= htmlspecialchars($map_activity_item['city']) ?> <!-- Adresse et ville de l'activité -->
                    </p>
                </a>
                <?php endforeach; // Fin de la boucle sur les activités de la sidebar ?>
            <?php endif; ?>
        </div>
    </aside>

    <!-- ── Zone carte Leaflet ─────────────────────────────────────────── -->
    <div id="map"></div> <!-- Conteneur dans lequel Leaflet initialise et rend la carte interactive -->

</div>

<script src="/sharetime/public/js/leaflet.js"></script> <!-- Charge la bibliothèque Leaflet avant le script de la carte -->
<script>
(function () { // IIFE : encapsule le code pour éviter de polluer le scope global de la page
    // Sérialise les données d'activités PHP → JSON pour le JS de la carte
    // Chaque entrée contient toutes les infos nécessaires aux marqueurs et popups
    var mapActivities = <?= json_encode(array_map(function ($map_activity_item) use ($CATEGORY_MAP, $category_marker_colors) { // Transforme le tableau PHP en JSON lisible par le JS
        return [
            'id'       => (int)$map_activity_item['idactivities'],    // Identifiant entier de l'activité
            'title'    => $map_activity_item['title'],                 // Titre de l'activité
            'city'     => $map_activity_item['city'],                  // Ville de l'activité
            'location' => $map_activity_item['location'],              // Adresse précise de l'activité
            'category' => $map_activity_item['category'],              // Slug de catégorie
            'emoji'    => $CATEGORY_MAP[$map_activity_item['category']][0] ?? '⭐',  // Emoji de catégorie, étoile par défaut
            'label'    => $CATEGORY_MAP[$map_activity_item['category']][2] ?? 'Autre', // Libellé de catégorie, "Autre" par défaut
            'color'    => $category_marker_colors[$map_activity_item['category']] ?? '#6B7280', // Couleur du marqueur, gris par défaut
            'date'     => $map_activity_item['start_time'],            // Date et heure de début au format MySQL
            'creator'  => $map_activity_item['pseudo'] ?: ($map_activity_item['prenom'] . ' ' . $map_activity_item['nom']), // Pseudo ou nom complet de l'organisateur
            'lat'      => (float)$map_activity_item['latitude'],       // Latitude GPS en nombre flottant
            'lng'      => (float)$map_activity_item['longitude'],      // Longitude GPS en nombre flottant
        ];
    }, $map_activities), JSON_UNESCAPED_UNICODE) ?>; // JSON_UNESCAPED_UNICODE préserve les caractères accentués et emojis

    /* Initialise la carte Leaflet centrée sur la France (zoom national) */
    var map = L.map('map', { center: [46.5, 2.5], zoom: 6 }); // Centre la vue initiale sur la France métropolitaine au zoom 6

    /* Fond de carte OpenStreetMap */
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>', // Mention légale obligatoire pour l'utilisation d'OpenStreetMap
        maxZoom: 19 // Niveau de zoom maximal autorisé pour les tuiles OpenStreetMap
    }).addTo(map); // Ajoute la couche de tuiles à la carte

    /* Force le recalcul de la taille après rendu complet (évite la carte grise) */
    setTimeout(function () { map.invalidateSize(); }, 200); // Délai de 200ms pour laisser le DOM se stabiliser avant le recalcul

    if (!mapActivities.length) return; // Arrête l'exécution si aucune activité n'est à afficher sur la carte

    /* Échappe les caractères HTML dans les chaînes insérées dans les popups */
    function escapeHtml(str) { // Protège le contenu des popups contre les injections HTML
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* markers : dictionnaire id → marker Leaflet, pour retrouver un marker par ID au clic sidebar */
    var markers = {}; // Objet vide qui servira de dictionnaire : clé = id activité, valeur = instance L.marker
    /* bounds : rectangle englobant tous les markers, pour le zoom automatique */
    var allMarkersBounds  = L.latLngBounds(); // Rectangle de délimitation initialement vide, étendu à chaque marqueur ajouté

    mapActivities.forEach(function (activityData) { // Parcourt chaque activité pour créer son marqueur sur la carte
        /* Marqueur SVG coloré : pin pointant vers le bas, cercle blanc en son centre */
        var markerIcon = L.divIcon({
            className: '', // Classe CSS vide pour ne pas appliquer les styles Leaflet par défaut
            html: '<svg width="28" height="40" viewBox="0 0 28 40" xmlns="http://www.w3.org/2000/svg">'
                + '<path d="M14 0C6.268 0 0 6.268 0 14c0 9.333 14 26 14 26S28 23.333 28 14C28 6.268 21.732 0 14 0z" fill="' + activityData.color + '" stroke="white" stroke-width="2"/>' // Forme de pin colorée selon la catégorie
                + '<circle cx="14" cy="14" r="6" fill="white"/>' // Cercle blanc au centre du pin
                + '</svg>',
            iconSize:    [28, 40],   // Dimensions du SVG du marqueur
            iconAnchor:  [14, 40],   // point d'ancrage = bas du pin
            popupAnchor: [0, -42]    // popup s'ouvre au-dessus du pin
        });

        /* Formate la date de début pour l'affichage dans la popup */
        var startDate       = new Date(activityData.date.replace(' ', 'T')); // Remplace l'espace MySQL par 'T' pour un parsing ISO 8601 correct
        var startDateLabel  = startDate.toLocaleDateString('fr-FR', { day:'numeric', month:'long', year:'numeric' }); // Formate la date en français (ex : "15 juin 2025")

        /* HTML de la popup : catégorie + titre + lieu/date + lien vers la page de détail */
        var popupHtml = '<div class="mp">'
            + '<p class="mp-cat" style="color:' + activityData.color + '">' + activityData.emoji + ' ' + activityData.label + '</p>' // Libellé de catégorie coloré selon la catégorie
            + '<p class="mp-title">' + escapeHtml(activityData.title) + '</p>' // Titre de l'activité protégé contre les injections HTML
            + '<p class="mp-meta">📅 ' + startDateLabel + '<br>📍 ' + escapeHtml(activityData.location) + ', ' + escapeHtml(activityData.city) + '</p>' // Méta-informations : date et lieu
            + '<a class="mp-btn" href="/sharetime/public/?page=detail&id=' + activityData.id + '">Voir l\'activité →</a>' // Bouton de navigation vers la page de détail
            + '</div>';

        /* Crée le marker Leaflet et l'ajoute à la carte */
        var leafletMarker = L.marker([activityData.lat, activityData.lng], { icon: markerIcon })
                             .addTo(map) // Ajoute le marqueur à la carte Leaflet
                             .bindPopup(popupHtml, { maxWidth: 250 }); // Associe la popup HTML au marqueur avec largeur max

        markers[activityData.id] = leafletMarker; // Enregistre le marker dans le dictionnaire pour le retrouver depuis la sidebar
        allMarkersBounds.extend([activityData.lat, activityData.lng]); // Étend le rectangle englobant pour inclure ce nouveau marqueur
    });

    /* Zoom automatique : sur le seul marqueur si 1 activité, sur tous sinon */
    if (mapActivities.length === 1) {
        map.setView([mapActivities[0].lat, mapActivities[0].lng], 14); // Centre la carte sur l'unique marqueur avec un zoom de quartier
        markers[mapActivities[0].id].openPopup(); // Ouvre automatiquement la popup du seul marqueur
    } else {
        map.fitBounds(allMarkersBounds, { padding: [60, 60], maxZoom: 13 }); // Ajuste le zoom pour englober tous les marqueurs avec une marge de 60px
    }

    /* Clic sur une card de la sidebar → centrer la carte sur ce marqueur + ouvrir sa popup */
    document.querySelectorAll('.carte-card[data-id]').forEach(function (sidebarCard) { // Sélectionne toutes les cards de la sidebar ayant un attribut data-id
        sidebarCard.addEventListener('click', function (clickEvent) {
            clickEvent.preventDefault();  // évite la navigation vers la page détail
            var activityId = parseInt(sidebarCard.dataset.id);   // Récupère l'ID de l'activité depuis l'attribut data-id
            var cardLat    = parseFloat(sidebarCard.dataset.lat); // Récupère la latitude depuis l'attribut data-lat
            var cardLng    = parseFloat(sidebarCard.dataset.lng); // Récupère la longitude depuis l'attribut data-lng
            map.setView([cardLat, cardLng], 15); // Centre la carte sur le marqueur avec un zoom de rue
            if (markers[activityId]) markers[activityId].openPopup(); // Ouvre la popup du marqueur si celui-ci existe dans le dictionnaire
        });
    });
})();
</script>
