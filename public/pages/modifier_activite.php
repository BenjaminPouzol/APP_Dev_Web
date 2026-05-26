<?php
/**
 * public/pages/modifier_activite.php — Formulaire d'édition d'une activité
 *
 * Variables disponibles (préparées par index.php routing) :
 *   $activity    : tableau de l'activité à modifier (chargée par Activity::getById)
 *   $error       : message d'erreur de validation (optionnel, défini par le handler POST)
 *   $CATEGORY_MAP: mapping catégorie → [emoji, classe CSS, libellé]
 *
 * Différences clés par rapport à creer.php :
 *   - Les champs sont pré-remplis avec les valeurs actuelles de $activity,
 *     mais $_POST prend priorité si le formulaire a déjà été soumis avec erreur
 *     (évite de perdre les saisies de l'utilisateur après une validation échouée).
 *   - Le min du champ participants est contraint par le nombre déjà inscrits
 *     (on ne peut pas descendre en-dessous du nombre actuel d'inscrits confirmés).
 *   - Les dates datetime-local sont reformatées depuis le format MySQL (Y-m-d H:i:s)
 *     vers le format attendu par l'input HTML (Y-m-d\TH:i).
 *   - L'ID de l'activité est transmis en champ hidden pour que le handler sache
 *     quelle entrée de la table activities mettre à jour.
 */
?>
<!-- ── CONTENEUR PRINCIPAL ─────────────────────────────────────────────────── -->
<main class="container" style="padding:40px 0; max-width:700px; margin:auto;"> <!-- Conteneur centré avec largeur limitée à 700px -->
    <h1 style="margin-bottom:8px; color:var(--navy);">Modifier l'activité</h1> <!-- Titre principal de la page en couleur navy -->
    <p style="color:var(--gray-500); margin-bottom:32px;">
        Modifie les informations de ton activité. Les participants inscrits recevront une notification. <!-- Sous-titre informatif pour l'utilisateur -->
    </p>

    <!-- Erreur de validation retournée par le handler POST (titre vide, date passée, etc.) -->
    <?php if (!empty($error)): ?> <!-- Affiche le bloc d'erreur uniquement si une erreur existe -->
        <div style="background:#FEE2E2; color:#DC2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:500;">
            <?= htmlspecialchars($error) ?> <!-- Affiche le message d'erreur en échappant les caractères spéciaux HTML -->
        </div>
    <?php endif; ?>

    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px;"> <!-- Carte blanche avec bordure arrondie pour le formulaire -->
        <!-- enctype multipart/form-data obligatoire pour permettre l'upload d'une nouvelle photo -->
        <form method="POST" action="/sharetime/public/?page=modifier_activite" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:20px;"> <!-- Formulaire POST avec colonne et espacement vertical de 20px -->
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"> <!-- Jeton CSRF caché pour protéger contre les attaques cross-site -->
            <!-- ID de l'activité : permet au handler de cibler la bonne ligne en BDD -->
            <input type="hidden" name="activity_id" value="<?= (int)$activity['idactivities'] ?>"> <!-- ID de l'activité castée en entier pour éviter les injections -->

            <!-- ── Titre ───────────────────────────────────────────────────────
                 Priorité à $_POST['title'] si soumission avec erreur (préserve la saisie),
                 sinon valeur actuelle en BDD. -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Titre *</label> <!-- Label en gras, affiché en bloc au-dessus du champ -->
                <input type="text" name="title" required placeholder="Ex : Randonnée en forêt"
                       value="<?= htmlspecialchars($_POST['title'] ?? $activity['title']) ?>" <!-- Préremplit avec $_POST si erreur précédente, sinon valeur BDD -->
                       style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
            </div>

            <!-- ── Description ─────────────────────────────────────────────── -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Description *</label> <!-- Label obligatoire pour la description -->
                <textarea name="description" required rows="4" placeholder="Décris ton activité, le programme, ce qu'il faut prévoir..."
                          style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; resize:vertical; box-sizing:border-box;"><?= htmlspecialchars($_POST['description'] ?? $activity['description']) ?></textarea> <!-- Préremplit le textarea avec $_POST ou la valeur BDD, en échappant le HTML -->
            </div>

            <!-- ── Lieu + Ville en deux colonnes côte à côte ─────────────── -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;"> <!-- Grille CSS à deux colonnes égales avec écart de 16px -->
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Lieu *</label> <!-- Label du champ "lieu précis" (ex : nom du parc, de la salle) -->
                    <input type="text" name="location" required
                           value="<?= htmlspecialchars($_POST['location'] ?? $activity['location']) ?>" <!-- Priorité $_POST puis valeur BDD pour le champ lieu -->
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Ville *</label> <!-- Label du champ ville (utilisé aussi pour le géocodage Nominatim) -->
                    <input type="text" name="city" required
                           value="<?= htmlspecialchars($_POST['city'] ?? $activity['city']) ?>" <!-- Priorité $_POST puis valeur BDD pour le champ ville -->
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
            </div>

            <!-- ── Localisation sur la carte Leaflet ─────────────────────────
                 Coordonnées GPS : $_POST préempte les valeurs BDD en cas d'erreur précédente.
                 La mini-carte affiche un marqueur draggable à la position enregistrée,
                 ou laisse la carte vierge si aucune coordonnée n'existe encore. -->
            <link rel="stylesheet" href="/sharetime/public/css/leaflet.css"> <!-- Charge le CSS de la bibliothèque Leaflet pour afficher la carte -->
            <?php
                // Priorité : valeur POST (re-soumission) > valeur BDD > chaîne vide (pas de coordonnées)
                $current_latitude  = $_POST['latitude']  ?? $activity['latitude']  ?? ''; // Latitude : POST > BDD > vide
                $current_longitude = $_POST['longitude'] ?? $activity['longitude'] ?? ''; // Longitude : POST > BDD > vide
            ?>
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Localisation sur la carte
                    <span style="font-weight:400; color:var(--gray-400); font-size:0.82rem;">(optionnel)</span> <!-- Mention "optionnel" en petite police grise -->
                </label>
                <div style="display:flex; gap:8px; margin-bottom:8px;">
                    <!-- Bouton de géocodage : déclenche l'appel API Nominatim via JS -->
                    <button type="button" id="geocode-btn"
                            style="padding:8px 16px; background:var(--navy); color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                        📍 Géolocaliser l'adresse <!-- Texte du bouton avec emoji de localisation -->
                    </button>
                    <!-- Zone de statut : "Recherche…", message d'erreur, ou vide après succès -->
                    <span id="geocode-status" style="font-size:0.82rem; color:var(--gray-500); align-self:center;"></span> <!-- Span mis à jour en JS pour indiquer l'état du géocodage -->
                </div>
                <!-- Mini-carte Leaflet pour positionner le marqueur (clic ou géocodage) -->
                <div id="mini-map" style="height:220px; border-radius:10px; border:1.5px solid var(--gray-300); overflow:hidden;"></div> <!-- Div conteneur de la carte Leaflet, hauteur fixe 220px -->
                <!-- Champs hidden mis à jour par JS quand le marqueur est positionné ou déplacé -->
                <input type="hidden" name="latitude"  id="lat-input"  value="<?= htmlspecialchars($current_latitude) ?>"> <!-- Champ caché portant la latitude, mis à jour par JS -->
                <input type="hidden" name="longitude" id="lng-input"  value="<?= htmlspecialchars($current_longitude) ?>"> <!-- Champ caché portant la longitude, mis à jour par JS -->
                <!-- Texte de confirmation des coordonnées sélectionnées -->
                <p id="coords-display" style="font-size:0.78rem; color:var(--gray-400); margin:6px 0 0;">
                    <?php if ($current_latitude !== ''): ?> <!-- Vérifie si des coordonnées existent déjà -->
                        Position : <?= htmlspecialchars($current_latitude) ?>, <?= htmlspecialchars($current_longitude) ?> <!-- Affiche les coordonnées actuelles de manière lisible -->
                    <?php else: ?>
                        Cliquez sur la carte ou utilisez le bouton pour placer le marqueur. <!-- Message d'invitation si aucune coordonnée n'est encore définie -->
                    <?php endif; ?>
                </p>
            </div>

            <script src="/sharetime/public/js/leaflet.js"></script> <!-- Charge la bibliothèque JavaScript Leaflet pour la carte interactive -->
            <script>
            (function() { // Fonction auto-invoquée pour isoler les variables du scope global
                // Initialise la mini-carte centrée sur la France (vue nationale par défaut)
                var mini_map = L.map('mini-map').setView([46.5, 2.5], 5); // Crée la carte Leaflet centrée sur la France au niveau de zoom 5
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap', maxZoom: 18 // Définit la source des tuiles OSM avec une attribution et un zoom max de 18
                }).addTo(mini_map); // Ajoute la couche de tuiles à la carte

                var location_marker   = null;   // référence au marqueur courant (null si non positionné)
                var lat_input_el      = document.getElementById('lat-input'); // Référence au champ caché latitude pour le mettre à jour
                var lng_input_el      = document.getElementById('lng-input'); // Référence au champ caché longitude pour le mettre à jour
                var coords_display_el = document.getElementById('coords-display'); // Référence au paragraphe affichant les coordonnées à l'utilisateur

                // Place ou déplace le marqueur draggable aux coordonnées données,
                // met à jour les champs hidden et le texte d'affichage des coordonnées
                function placeMapMarker(lat, lng) {
                    if (location_marker) { // Si un marqueur existe déjà, on le déplace sans en créer un nouveau
                        location_marker.setLatLng([lat, lng]); // Déplace le marqueur existant vers les nouvelles coordonnées
                    } else {
                        location_marker = L.marker([lat, lng], { draggable: true }).addTo(mini_map); // Crée un marqueur draggable et l'ajoute à la carte
                        // Quand l'utilisateur glisse-dépose le marqueur, met à jour les champs hidden
                        location_marker.on('dragend', function(drag_event) { // Écoute la fin du glisser-déposer du marqueur
                            var drag_end_position = drag_event.target.getLatLng(); // Récupère la nouvelle position GPS après le drag
                            lat_input_el.value = drag_end_position.lat.toFixed(7); // Enregistre la latitude avec 7 décimales dans le champ caché
                            lng_input_el.value = drag_end_position.lng.toFixed(7); // Enregistre la longitude avec 7 décimales dans le champ caché
                            coords_display_el.textContent = 'Position : ' + drag_end_position.lat.toFixed(5) + ', ' + drag_end_position.lng.toFixed(5); // Met à jour l'affichage des coordonnées (5 décimales pour l'utilisateur)
                        });
                    }
                    lat_input_el.value = lat.toFixed(7); // Met à jour le champ caché latitude avec 7 décimales de précision
                    lng_input_el.value = lng.toFixed(7); // Met à jour le champ caché longitude avec 7 décimales de précision
                    coords_display_el.textContent = 'Position : ' + lat.toFixed(5) + ', ' + lng.toFixed(5); // Affiche les coordonnées arrondies à 5 décimales pour la lisibilité
                }

                <?php if ($current_latitude !== '' && $current_longitude !== ''): ?> // Vérifie côté PHP si des coordonnées sont déjà enregistrées
                // Si des coordonnées existent déjà en BDD, initialise le marqueur sur la carte
                placeMapMarker(<?= (float)$current_latitude ?>, <?= (float)$current_longitude ?>); // Injecte les coordonnées PHP en JS pour positionner le marqueur au chargement
                mini_map.setView([<?= (float)$current_latitude ?>, <?= (float)$current_longitude ?>], 13); // Centre la carte sur les coordonnées existantes avec un zoom de 13 (niveau ville)
                <?php endif; ?>

                // Clic direct sur la carte : place le marqueur à la position cliquée
                mini_map.on('click', function(map_click_event) { placeMapMarker(map_click_event.latlng.lat, map_click_event.latlng.lng); }); // Écoute les clics sur la carte et positionne le marqueur aux coordonnées cliquées

                // Bouton "Géolocaliser" : appelle l'API Nominatim via geocode.php
                document.getElementById('geocode-btn').addEventListener('click', function() { // Attache le gestionnaire de clic au bouton de géocodage
                    var location_value  = document.querySelector('[name="location"]').value.trim(); // Récupère et nettoie la valeur du champ "lieu"
                    var city_value      = document.querySelector('[name="city"]').value.trim(); // Récupère et nettoie la valeur du champ "ville"
                    // Construit la query en combinant lieu + ville (Nominatim fonctionne mieux avec les deux)
                    var geocode_query   = [location_value, city_value].filter(Boolean).join(', '); // Filtre les valeurs vides et joint lieu + ville par une virgule
                    if (!geocode_query) { // Si les deux champs sont vides, on ne peut pas géocoder
                        document.getElementById('geocode-status').textContent = 'Remplis d\'abord les champs Lieu et Ville.'; // Informe l'utilisateur que les champs sont requis
                        return; // Stoppe l'exécution sans faire de requête
                    }
                    var geocode_status_el = document.getElementById('geocode-status'); // Référence au span de statut pour les mises à jour
                    geocode_status_el.textContent = 'Recherche…'; // Affiche un indicateur de chargement pendant la requête
                    // geocode.php est un proxy PHP vers Nominatim (évite les problèmes CORS et User-Agent)
                    fetch('/sharetime/public/api/geocode.php?q=' + encodeURIComponent(geocode_query)) // Envoie la requête GET au proxy PHP avec l'adresse encodée
                    .then(function(geocode_response) { return geocode_response.json(); }) // Parse la réponse JSON retournée par Nominatim
                    .then(function(geocode_results) {
                        if (!geocode_results.length) { // Aucun résultat trouvé pour l'adresse saisie
                            geocode_status_el.textContent = 'Introuvable — vérifiez l\'orthographe (surtout la ville), ou cliquez sur la carte.'; // Indique à l'utilisateur que l'adresse n'a pas été trouvée
                            return; // Arrête le traitement sans modifier la carte
                        }
                        // Prend le premier résultat (le plus pertinent selon Nominatim)
                        var geocoded_lat = parseFloat(geocode_results[0].lat); // Convertit la latitude du premier résultat en nombre flottant
                        var geocoded_lng = parseFloat(geocode_results[0].lon); // Convertit la longitude du premier résultat en nombre flottant
                        placeMapMarker(geocoded_lat, geocoded_lng); // Positionne le marqueur aux coordonnées géocodées
                        mini_map.setView([geocoded_lat, geocoded_lng], 14); // Recentre la carte sur le résultat avec un zoom de 14 (niveau quartier)
                        geocode_status_el.textContent = '';  // efface le message "Recherche…"
                    })
                    .catch(function() { geocode_status_el.textContent = 'Erreur réseau, clique sur la carte.'; }); // Gère les erreurs réseau en affichant un message de fallback
                });
            })();
            </script>

            <!-- ── Dates début + fin en deux colonnes ───────────────────────────
                 Le format MySQL (Y-m-d H:i:s) doit être converti en Y-m-d\TH:i
                 pour que l'input datetime-local affiche correctement la valeur pré-remplie.
                 Priorité : $_POST en cas d'erreur, sinon conversion de la date BDD. -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;"> <!-- Grille deux colonnes pour les dates de début et de fin côte à côte -->
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Date et heure de début *</label> <!-- Label obligatoire pour la date/heure de début -->
                    <?php
                        // strtotime + date() convertit "2025-06-15 14:00:00" en "2025-06-15T14:00"
                        $start_datetime_value = $_POST['start_time'] ?? date('Y-m-d\TH:i', strtotime($activity['start_time'])); // Reformate la date MySQL en format HTML datetime-local, ou récupère la valeur POST
                    ?>
                    <input type="datetime-local" name="start_time" required value="<?= htmlspecialchars($start_datetime_value) ?>" <!-- Champ date-heure de début prérempli avec la valeur formatée -->
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Date et heure de fin *</label> <!-- Label obligatoire pour la date/heure de fin -->
                    <?php
                        $end_datetime_value = $_POST['end_time'] ?? date('Y-m-d\TH:i', strtotime($activity['end_time'])); // Même reformatage pour la date de fin : POST en priorité, sinon conversion BDD
                    ?>
                    <input type="datetime-local" name="end_time" required value="<?= htmlspecialchars($end_datetime_value) ?>" <!-- Champ date-heure de fin prérempli avec la valeur formatée -->
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
            </div>

            <!-- ── Participants max + Visibilité en deux colonnes ──────────── -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;"> <!-- Grille deux colonnes pour le nombre de participants et la visibilité -->
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                        Participants max *
                        <!-- Indication du minimum quand des personnes sont déjà inscrites :
                             empêche de réduire la capacité en-dessous des inscrits confirmés -->
                        <?php if ($activity['nb_inscrits'] > 0): ?> <!-- Affiche la contrainte min uniquement si des inscrits existent -->
                            <span style="font-size:0.78rem; font-weight:400; color:var(--gray-500);">(min. <?= $activity['nb_inscrits'] ?> inscrits)</span> <!-- Indique à l'organisateur le nombre minimal dû aux inscrits actuels -->
                        <?php endif; ?>
                    </label>
                    <!-- min = max(2, nb_inscrits) : impossible de descendre en-dessous des inscrits actuels -->
                    <input type="number" name="max_participants" required min="<?= max(2, (int)$activity['nb_inscrits']) ?>" <!-- L'attribut min est le plus grand entre 2 et le nombre d'inscrits actuels -->
                           value="<?= htmlspecialchars($_POST['max_participants'] ?? $activity['max_participants']) ?>" <!-- Priorité $_POST pour conserver la saisie, sinon valeur BDD -->
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Visibilité *</label> <!-- Label du sélecteur public/privé -->
                    <!-- Valeur courante de la visibilité : $_POST préempte la valeur BDD si re-soumission -->
                    <?php $current_visibility = $_POST['visibility'] ?? $activity['visibility']; ?> <!-- Détermine la visibilité à pré-sélectionner : POST > BDD -->
                    <select name="visibility"
                            style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box; background:white;">
                        <option value="publique" <?= $current_visibility === 'publique' ? 'selected' : '' ?>>Publique</option> <!-- Option "Publique" : selected si c'est la valeur courante -->
                        <option value="privee"   <?= $current_visibility === 'privee'   ? 'selected' : '' ?>>Privée</option> <!-- Option "Privée" : selected si c'est la valeur courante -->
                    </select>
                </div>
            </div>

            <!-- ── Catégorie ─────────────────────────────────────────────────
                 Parcourt $CATEGORY_MAP pour générer les options.
                 $current_category : $_POST préempte la valeur BDD si re-soumission avec erreur. -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Catégorie *</label> <!-- Label obligatoire du sélecteur de catégorie -->
                <?php $current_category = $_POST['category'] ?? $activity['category']; ?> <!-- Catégorie à pré-sélectionner : POST > BDD -->
                <select name="category"
                        style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box; background:white;">
                    <?php foreach ($CATEGORY_MAP as $category_slug => [$category_emoji, , $category_label]): ?> <!-- Parcourt chaque entrée du mapping catégorie : slug → [emoji, classe CSS, libellé] -->
                        <option value="<?= $category_slug ?>" <?= $current_category === $category_slug ? 'selected' : '' ?>> <!-- Génère chaque option avec son slug et la marque "selected" si c'est la catégorie courante -->
                            <?= $category_emoji ?> <?= $category_label ?> <!-- Affiche l'emoji suivi du libellé de la catégorie -->
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ── Liste d'attente ────────────────────────────────────────────
                 État coché/décoché : priorité $_POST si soumission avec erreur,
                 sinon valeur en BDD ($activity['liste_attente_active']).
                 Cas particulier du checkbox : absent en POST si non coché, d'où isset(). -->
            <?php $waitlist_is_enabled = isset($_POST['liste_attente_active']) // isset() car une checkbox absente de $_POST signifie qu'elle est décochée
                    ? !empty($_POST['liste_attente_active']) // Si le champ est en POST, on vérifie qu'il n'est pas vide (valeur "1")
                    : !empty($activity['liste_attente_active']); ?> <!-- Sinon on lit la valeur en BDD pour déterminer l'état initial -->
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:14px 16px;
                          border:1.5px solid var(--gray-200); border-radius:10px; background:var(--gray-50);"> <!-- Label cliquable englobant la checkbox et son texte -->
                <input type="checkbox" name="liste_attente_active" value="1"
                       <?= $waitlist_is_enabled ? 'checked' : '' ?> <!-- Ajoute l'attribut "checked" si la liste d'attente était activée -->
                       style="width:18px; height:18px; accent-color:var(--orange); cursor:pointer;"> <!-- Checkbox en orange avec taille augmentée pour faciliter le clic -->
                <span style="color:var(--gray-700); font-weight:500;">
                    Activer la liste d'attente <!-- Texte principal de l'option liste d'attente -->
                    <span style="display:block; font-size:0.8rem; font-weight:400; color:var(--gray-400);">
                        Les participants peuvent rejoindre une file d'attente si l'activité est complète. <!-- Description courte expliquant le fonctionnement de la liste d'attente -->
                    </span>
                </span>
            </label>

            <!-- ── Photo de l'activité ────────────────────────────────────────
                 Si une photo existe déjà, on affiche sa miniature + un message explicatif.
                 L'upload d'une nouvelle photo remplace l'ancienne (géré dans le handler activity.php).
                 Si aucune photo n'est choisie, la photo actuelle est conservée. -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Photo de l'activité
                    <span style="font-size:0.78rem; font-weight:400; color:var(--gray-400);">(JPG, PNG, WebP · max 2 Mo)</span> <!-- Indique les formats acceptés et la taille maximale -->
                </label>
                <?php if (!empty($activity['photo'])): ?> <!-- Vérifie si une photo est déjà enregistrée pour cette activité -->
                <!-- Aperçu de la photo actuelle avec instruction pour la remplacer -->
                <div style="margin-bottom:10px; display:flex; align-items:center; gap:12px;">
                    <img src="/sharetime/public/uploads/activites/<?= htmlspecialchars($activity['photo']) ?>" <!-- Affiche la miniature de la photo actuelle depuis le dossier uploads -->
                         style="width:80px; height:56px; object-fit:cover; border-radius:8px; border:2px solid var(--gray-200);"> <!-- Miniature recadrée en 80×56px avec coins arrondis -->
                    <span style="font-size:0.82rem; color:var(--gray-500);">Photo actuelle — importer une nouvelle pour la remplacer</span> <!-- Message explicatif : la nouvelle photo remplacera l'ancienne -->
                </div>
                <?php else: ?>
                <p style="font-size:0.82rem; color:var(--gray-400); margin:0 0 10px;">Aucune photo pour l'instant. Vous pouvez en ajouter une ci-dessous.</p> <!-- Message affiché quand aucune photo n'est encore associée à l'activité -->
                <?php endif; ?>
                <!-- accept= filtre les types côté navigateur, doublé d'un contrôle magic bytes côté serveur -->
                <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp" <!-- Filtre les formats acceptés côté client (le serveur re-vérifie côté handler) -->
                       style="font-family:inherit; font-size:0.9rem; color:var(--gray-700);">
            </div>

            <!-- ── Boutons d'action ────────────────────────────────────────── -->
            <div style="display:flex; gap:12px; margin-top:8px;"> <!-- Conteneur flex pour aligner les boutons côte à côte -->
                <button type="submit" class="btn btn-orange btn-lg">Enregistrer les modifications</button> <!-- Bouton de soumission principal en orange -->
                <!-- Annuler : retourne à la page de détail sans sauvegarder -->
                <a href="/sharetime/public/?page=detail&id=<?= (int)$activity['idactivities'] ?>" class="btn btn-outline-navy btn-lg">Annuler</a> <!-- Lien "Annuler" qui redirige vers la page de détail de l'activité sans rien enregistrer -->
            </div>
        </form>
    </div>
</main>
