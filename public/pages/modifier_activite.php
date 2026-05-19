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
<main class="container" style="padding:40px 0; max-width:700px; margin:auto;">
    <h1 style="margin-bottom:8px; color:var(--navy);">Modifier l'activité</h1>
    <p style="color:var(--gray-500); margin-bottom:32px;">
        Modifie les informations de ton activité. Les participants inscrits recevront une notification.
    </p>

    <!-- Erreur de validation retournée par le handler POST (titre vide, date passée, etc.) -->
    <?php if (!empty($error)): ?>
        <div style="background:#FEE2E2; color:#DC2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:500;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px;">
        <!-- enctype multipart/form-data obligatoire pour permettre l'upload d'une nouvelle photo -->
        <form method="POST" action="/sharetime/public/?page=modifier_activite" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:20px;">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <!-- ID de l'activité : permet au handler de cibler la bonne ligne en BDD -->
            <input type="hidden" name="activity_id" value="<?= (int)$activity['idactivities'] ?>">

            <!-- ── Titre ───────────────────────────────────────────────────────
                 Priorité à $_POST['title'] si soumission avec erreur (préserve la saisie),
                 sinon valeur actuelle en BDD. -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Titre *</label>
                <input type="text" name="title" required placeholder="Ex : Randonnée en forêt"
                       value="<?= htmlspecialchars($_POST['title'] ?? $activity['title']) ?>"
                       style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
            </div>

            <!-- ── Description ─────────────────────────────────────────────── -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Description *</label>
                <textarea name="description" required rows="4" placeholder="Décris ton activité, le programme, ce qu'il faut prévoir..."
                          style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; resize:vertical; box-sizing:border-box;"><?= htmlspecialchars($_POST['description'] ?? $activity['description']) ?></textarea>
            </div>

            <!-- ── Lieu + Ville en deux colonnes côte à côte ─────────────── -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Lieu *</label>
                    <input type="text" name="location" required
                           value="<?= htmlspecialchars($_POST['location'] ?? $activity['location']) ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Ville *</label>
                    <input type="text" name="city" required
                           value="<?= htmlspecialchars($_POST['city'] ?? $activity['city']) ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
            </div>

            <!-- ── Localisation sur la carte Leaflet ─────────────────────────
                 Coordonnées GPS : $_POST préempte les valeurs BDD en cas d'erreur précédente.
                 La mini-carte affiche un marqueur draggable à la position enregistrée,
                 ou laisse la carte vierge si aucune coordonnée n'existe encore. -->
            <link rel="stylesheet" href="/sharetime/public/css/leaflet.css">
            <?php
                // Priorité : valeur POST (re-soumission) > valeur BDD > chaîne vide (pas de coordonnées)
                $current_latitude  = $_POST['latitude']  ?? $activity['latitude']  ?? '';
                $current_longitude = $_POST['longitude'] ?? $activity['longitude'] ?? '';
            ?>
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Localisation sur la carte
                    <span style="font-weight:400; color:var(--gray-400); font-size:0.82rem;">(optionnel)</span>
                </label>
                <div style="display:flex; gap:8px; margin-bottom:8px;">
                    <!-- Bouton de géocodage : déclenche l'appel API Nominatim via JS -->
                    <button type="button" id="geocode-btn"
                            style="padding:8px 16px; background:var(--navy); color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                        📍 Géolocaliser l'adresse
                    </button>
                    <!-- Zone de statut : "Recherche…", message d'erreur, ou vide après succès -->
                    <span id="geocode-status" style="font-size:0.82rem; color:var(--gray-500); align-self:center;"></span>
                </div>
                <!-- Mini-carte Leaflet pour positionner le marqueur (clic ou géocodage) -->
                <div id="mini-map" style="height:220px; border-radius:10px; border:1.5px solid var(--gray-300); overflow:hidden;"></div>
                <!-- Champs hidden mis à jour par JS quand le marqueur est positionné ou déplacé -->
                <input type="hidden" name="latitude"  id="lat-input"  value="<?= htmlspecialchars($current_latitude) ?>">
                <input type="hidden" name="longitude" id="lng-input"  value="<?= htmlspecialchars($current_longitude) ?>">
                <!-- Texte de confirmation des coordonnées sélectionnées -->
                <p id="coords-display" style="font-size:0.78rem; color:var(--gray-400); margin:6px 0 0;">
                    <?php if ($current_latitude !== ''): ?>
                        Position : <?= htmlspecialchars($current_latitude) ?>, <?= htmlspecialchars($current_longitude) ?>
                    <?php else: ?>
                        Cliquez sur la carte ou utilisez le bouton pour placer le marqueur.
                    <?php endif; ?>
                </p>
            </div>

            <script src="/sharetime/public/js/leaflet.js"></script>
            <script>
            (function() {
                // Initialise la mini-carte centrée sur la France (vue nationale par défaut)
                var mini_map = L.map('mini-map').setView([46.5, 2.5], 5);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap', maxZoom: 18
                }).addTo(mini_map);

                var location_marker   = null;   // référence au marqueur courant (null si non positionné)
                var lat_input_el      = document.getElementById('lat-input');
                var lng_input_el      = document.getElementById('lng-input');
                var coords_display_el = document.getElementById('coords-display');

                // Place ou déplace le marqueur draggable aux coordonnées données,
                // met à jour les champs hidden et le texte d'affichage des coordonnées
                function placeMapMarker(lat, lng) {
                    if (location_marker) {
                        location_marker.setLatLng([lat, lng]);
                    } else {
                        location_marker = L.marker([lat, lng], { draggable: true }).addTo(mini_map);
                        // Quand l'utilisateur glisse-dépose le marqueur, met à jour les champs hidden
                        location_marker.on('dragend', function(drag_event) {
                            var drag_end_position = drag_event.target.getLatLng();
                            lat_input_el.value = drag_end_position.lat.toFixed(7);
                            lng_input_el.value = drag_end_position.lng.toFixed(7);
                            coords_display_el.textContent = 'Position : ' + drag_end_position.lat.toFixed(5) + ', ' + drag_end_position.lng.toFixed(5);
                        });
                    }
                    lat_input_el.value = lat.toFixed(7);
                    lng_input_el.value = lng.toFixed(7);
                    coords_display_el.textContent = 'Position : ' + lat.toFixed(5) + ', ' + lng.toFixed(5);
                }

                <?php if ($current_latitude !== '' && $current_longitude !== ''): ?>
                // Si des coordonnées existent déjà en BDD, initialise le marqueur sur la carte
                placeMapMarker(<?= (float)$current_latitude ?>, <?= (float)$current_longitude ?>);
                mini_map.setView([<?= (float)$current_latitude ?>, <?= (float)$current_longitude ?>], 13);
                <?php endif; ?>

                // Clic direct sur la carte : place le marqueur à la position cliquée
                mini_map.on('click', function(map_click_event) { placeMapMarker(map_click_event.latlng.lat, map_click_event.latlng.lng); });

                // Bouton "Géolocaliser" : appelle l'API Nominatim via geocode.php
                document.getElementById('geocode-btn').addEventListener('click', function() {
                    var location_value  = document.querySelector('[name="location"]').value.trim();
                    var city_value      = document.querySelector('[name="city"]').value.trim();
                    // Construit la query en combinant lieu + ville (Nominatim fonctionne mieux avec les deux)
                    var geocode_query   = [location_value, city_value].filter(Boolean).join(', ');
                    if (!geocode_query) {
                        document.getElementById('geocode-status').textContent = 'Remplis d\'abord les champs Lieu et Ville.';
                        return;
                    }
                    var geocode_status_el = document.getElementById('geocode-status');
                    geocode_status_el.textContent = 'Recherche…';
                    // geocode.php est un proxy PHP vers Nominatim (évite les problèmes CORS et User-Agent)
                    fetch('/sharetime/public/api/geocode.php?q=' + encodeURIComponent(geocode_query))
                    .then(function(geocode_response) { return geocode_response.json(); })
                    .then(function(geocode_results) {
                        if (!geocode_results.length) {
                            geocode_status_el.textContent = 'Introuvable — vérifiez l\'orthographe (surtout la ville), ou cliquez sur la carte.';
                            return;
                        }
                        // Prend le premier résultat (le plus pertinent selon Nominatim)
                        var geocoded_lat = parseFloat(geocode_results[0].lat);
                        var geocoded_lng = parseFloat(geocode_results[0].lon);
                        placeMapMarker(geocoded_lat, geocoded_lng);
                        mini_map.setView([geocoded_lat, geocoded_lng], 14);
                        geocode_status_el.textContent = '';  // efface le message "Recherche…"
                    })
                    .catch(function() { geocode_status_el.textContent = 'Erreur réseau, clique sur la carte.'; });
                });
            })();
            </script>

            <!-- ── Dates début + fin en deux colonnes ───────────────────────────
                 Le format MySQL (Y-m-d H:i:s) doit être converti en Y-m-d\TH:i
                 pour que l'input datetime-local affiche correctement la valeur pré-remplie.
                 Priorité : $_POST en cas d'erreur, sinon conversion de la date BDD. -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Date et heure de début *</label>
                    <?php
                        // strtotime + date() convertit "2025-06-15 14:00:00" en "2025-06-15T14:00"
                        $start_datetime_value = $_POST['start_time'] ?? date('Y-m-d\TH:i', strtotime($activity['start_time']));
                    ?>
                    <input type="datetime-local" name="start_time" required value="<?= htmlspecialchars($start_datetime_value) ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Date et heure de fin *</label>
                    <?php
                        $end_datetime_value = $_POST['end_time'] ?? date('Y-m-d\TH:i', strtotime($activity['end_time']));
                    ?>
                    <input type="datetime-local" name="end_time" required value="<?= htmlspecialchars($end_datetime_value) ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
            </div>

            <!-- ── Participants max + Visibilité en deux colonnes ──────────── -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                        Participants max *
                        <!-- Indication du minimum quand des personnes sont déjà inscrites :
                             empêche de réduire la capacité en-dessous des inscrits confirmés -->
                        <?php if ($activity['nb_inscrits'] > 0): ?>
                            <span style="font-size:0.78rem; font-weight:400; color:var(--gray-500);">(min. <?= $activity['nb_inscrits'] ?> inscrits)</span>
                        <?php endif; ?>
                    </label>
                    <!-- min = max(2, nb_inscrits) : impossible de descendre en-dessous des inscrits actuels -->
                    <input type="number" name="max_participants" required min="<?= max(2, (int)$activity['nb_inscrits']) ?>"
                           value="<?= htmlspecialchars($_POST['max_participants'] ?? $activity['max_participants']) ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Visibilité *</label>
                    <!-- Valeur courante de la visibilité : $_POST préempte la valeur BDD si re-soumission -->
                    <?php $current_visibility = $_POST['visibility'] ?? $activity['visibility']; ?>
                    <select name="visibility"
                            style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box; background:white;">
                        <option value="publique" <?= $current_visibility === 'publique' ? 'selected' : '' ?>>Publique</option>
                        <option value="privee"   <?= $current_visibility === 'privee'   ? 'selected' : '' ?>>Privée</option>
                    </select>
                </div>
            </div>

            <!-- ── Catégorie ─────────────────────────────────────────────────
                 Parcourt $CATEGORY_MAP pour générer les options.
                 $current_category : $_POST préempte la valeur BDD si re-soumission avec erreur. -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Catégorie *</label>
                <?php $current_category = $_POST['category'] ?? $activity['category']; ?>
                <select name="category"
                        style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box; background:white;">
                    <?php foreach ($CATEGORY_MAP as $category_slug => [$category_emoji, , $category_label]): ?>
                        <option value="<?= $category_slug ?>" <?= $current_category === $category_slug ? 'selected' : '' ?>>
                            <?= $category_emoji ?> <?= $category_label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ── Liste d'attente ────────────────────────────────────────────
                 État coché/décoché : priorité $_POST si soumission avec erreur,
                 sinon valeur en BDD ($activity['liste_attente_active']).
                 Cas particulier du checkbox : absent en POST si non coché, d'où isset(). -->
            <?php $waitlist_is_enabled = isset($_POST['liste_attente_active'])
                    ? !empty($_POST['liste_attente_active'])
                    : !empty($activity['liste_attente_active']); ?>
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:14px 16px;
                          border:1.5px solid var(--gray-200); border-radius:10px; background:var(--gray-50);">
                <input type="checkbox" name="liste_attente_active" value="1"
                       <?= $waitlist_is_enabled ? 'checked' : '' ?>
                       style="width:18px; height:18px; accent-color:var(--orange); cursor:pointer;">
                <span style="color:var(--gray-700); font-weight:500;">
                    Activer la liste d'attente
                    <span style="display:block; font-size:0.8rem; font-weight:400; color:var(--gray-400);">
                        Les participants peuvent rejoindre une file d'attente si l'activité est complète.
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
                    <span style="font-size:0.78rem; font-weight:400; color:var(--gray-400);">(JPG, PNG, WebP · max 2 Mo)</span>
                </label>
                <?php if (!empty($activity['photo'])): ?>
                <!-- Aperçu de la photo actuelle avec instruction pour la remplacer -->
                <div style="margin-bottom:10px; display:flex; align-items:center; gap:12px;">
                    <img src="/sharetime/public/uploads/activites/<?= htmlspecialchars($activity['photo']) ?>"
                         style="width:80px; height:56px; object-fit:cover; border-radius:8px; border:2px solid var(--gray-200);">
                    <span style="font-size:0.82rem; color:var(--gray-500);">Photo actuelle — importer une nouvelle pour la remplacer</span>
                </div>
                <?php else: ?>
                <p style="font-size:0.82rem; color:var(--gray-400); margin:0 0 10px;">Aucune photo pour l'instant. Vous pouvez en ajouter une ci-dessous.</p>
                <?php endif; ?>
                <!-- accept= filtre les types côté navigateur, doublé d'un contrôle magic bytes côté serveur -->
                <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp"
                       style="font-family:inherit; font-size:0.9rem; color:var(--gray-700);">
            </div>

            <!-- ── Boutons d'action ────────────────────────────────────────── -->
            <div style="display:flex; gap:12px; margin-top:8px;">
                <button type="submit" class="btn btn-orange btn-lg">Enregistrer les modifications</button>
                <!-- Annuler : retourne à la page de détail sans sauvegarder -->
                <a href="/sharetime/public/?page=detail&id=<?= (int)$activity['idactivities'] ?>" class="btn btn-outline-navy btn-lg">Annuler</a>
            </div>
        </form>
    </div>
</main>
