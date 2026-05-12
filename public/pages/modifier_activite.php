<?php
/**
 * public/pages/modifier_activite.php — Formulaire d'édition d'une activité
 *
 * Variables disponibles (préparées par index.php routing) :
 *   $activity    : tableau de l'activité à modifier (chargée par Activity::getById)
 *   $error       : message d'erreur de validation (optionnel)
 *   $CATEGORY_MAP: mapping catégorie → [emoji, classe CSS, libellé]
 *
 * Différences clés par rapport à creer.php :
 *   - Les champs sont pré-remplis avec les valeurs actuelles de $activity,
 *     mais $_POST prend priorité si le formulaire a déjà été soumis avec erreur.
 *   - Le min des participants est contraint par le nombre déjà inscrits (on ne peut
 *     pas descendre en-dessous du nombre actuel d'inscrits).
 *   - Les dates datetime-local sont reformatées depuis le format MySQL (Y-m-d H:i:s)
 *     vers le format attendu par l'input (Y-m-d\TH:i).
 *   - L'ID de l'activité est transmis en champ hidden pour que le handler sache
 *     quelle activité mettre à jour.
 */
?>
<!-- ── CONTENEUR PRINCIPAL ─────────────────────────────────────────────────── -->
<main class="container" style="padding:40px 0; max-width:700px; margin:auto;">
    <h1 style="margin-bottom:8px; color:var(--navy);">Modifier l'activité</h1>
    <p style="color:var(--gray-500); margin-bottom:32px;">
        Modifie les informations de ton activité. Les participants inscrits recevront une notification.
    </p>

    <!-- Erreur de validation côté serveur -->
    <?php if (!empty($error)): ?>
        <div style="background:#FEE2E2; color:#DC2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:500;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px;">
        <!-- enctype multipart/form-data obligatoire pour le remplacement de photo -->
        <form method="POST" action="/sharetime/public/?page=modifier_activite" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:20px;">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <!-- ID de l'activité transmis en hidden pour que le handler sache quoi modifier -->
            <input type="hidden" name="activity_id" value="<?= (int)$activity['idactivities'] ?>">

            <!-- ── Titre : $_POST préempte la valeur en BDD si erreur précédente ── -->
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

            <!-- ── Lieu + Ville (2 colonnes) ─────────────────────────────── -->
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

            <!-- ── Localisation sur la carte ──────────────────────────────── -->
            <link rel="stylesheet" href="/sharetime/public/css/leaflet.css">
            <?php
                $mod_lat = $_POST['latitude']  ?? $activity['latitude']  ?? '';
                $mod_lng = $_POST['longitude'] ?? $activity['longitude'] ?? '';
            ?>
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Localisation sur la carte
                    <span style="font-weight:400; color:var(--gray-400); font-size:0.82rem;">(optionnel)</span>
                </label>
                <div style="display:flex; gap:8px; margin-bottom:8px;">
                    <button type="button" id="geocode-btn"
                            style="padding:8px 16px; background:var(--navy); color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                        📍 Géolocaliser l'adresse
                    </button>
                    <span id="geocode-status" style="font-size:0.82rem; color:var(--gray-500); align-self:center;"></span>
                </div>
                <div id="mini-map" style="height:220px; border-radius:10px; border:1.5px solid var(--gray-300); overflow:hidden;"></div>
                <input type="hidden" name="latitude"  id="lat-input"  value="<?= htmlspecialchars($mod_lat) ?>">
                <input type="hidden" name="longitude" id="lng-input"  value="<?= htmlspecialchars($mod_lng) ?>">
                <p id="coords-display" style="font-size:0.78rem; color:var(--gray-400); margin:6px 0 0;">
                    <?php if ($mod_lat !== ''): ?>
                        Position : <?= htmlspecialchars($mod_lat) ?>, <?= htmlspecialchars($mod_lng) ?>
                    <?php else: ?>
                        Cliquez sur la carte ou utilisez le bouton pour placer le marqueur.
                    <?php endif; ?>
                </p>
            </div>
            <script src="/sharetime/public/js/leaflet.js"></script>
            <script>
            (function() {
                var map = L.map('mini-map').setView([46.5, 2.5], 5);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap', maxZoom: 18
                }).addTo(map);

                var marker = null;
                var latIn  = document.getElementById('lat-input');
                var lngIn  = document.getElementById('lng-input');
                var disp   = document.getElementById('coords-display');

                function setMarker(lat, lng) {
                    if (marker) marker.setLatLng([lat, lng]);
                    else marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                    marker.on('dragend', function(e) {
                        var p = e.target.getLatLng();
                        latIn.value = p.lat.toFixed(7);
                        lngIn.value = p.lng.toFixed(7);
                        disp.textContent = 'Position : ' + p.lat.toFixed(5) + ', ' + p.lng.toFixed(5);
                    });
                    latIn.value = lat.toFixed(7);
                    lngIn.value = lng.toFixed(7);
                    disp.textContent = 'Position : ' + lat.toFixed(5) + ', ' + lng.toFixed(5);
                }

                <?php if ($mod_lat !== '' && $mod_lng !== ''): ?>
                setMarker(<?= (float)$mod_lat ?>, <?= (float)$mod_lng ?>);
                map.setView([<?= (float)$mod_lat ?>, <?= (float)$mod_lng ?>], 13);
                <?php endif; ?>

                map.on('click', function(e) { setMarker(e.latlng.lat, e.latlng.lng); });

                document.getElementById('geocode-btn').addEventListener('click', function() {
                    var loc  = document.querySelector('[name="location"]').value.trim();
                    var city = document.querySelector('[name="city"]').value.trim();
                    var q    = [loc, city].filter(Boolean).join(', ');
                    if (!q) { document.getElementById('geocode-status').textContent = 'Remplis d\'abord les champs Lieu et Ville.'; return; }
                    var st = document.getElementById('geocode-status');
                    st.textContent = 'Recherche…';
                    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q), {
                        headers: { 'Accept-Language': 'fr' }
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.length) { st.textContent = 'Adresse introuvable, clique sur la carte.'; return; }
                        var lat = parseFloat(data[0].lat), lng = parseFloat(data[0].lon);
                        setMarker(lat, lng);
                        map.setView([lat, lng], 14);
                        st.textContent = '';
                    })
                    .catch(function() { st.textContent = 'Erreur réseau, clique sur la carte.'; });
                });
            })();
            </script>

            <!-- ── Dates début + fin (2 colonnes) ──────────────────────────
                 Le format MySQL (Y-m-d H:i:s) doit être converti en Y-m-d\TH:i
                 pour que l'input datetime-local affiche correctement la valeur. -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Date et heure de début *</label>
                    <?php
                        // Priorité : valeur POST en cas d'erreur, sinon conversion de la date BDD
                        $start_val = $_POST['start_time'] ?? date('Y-m-d\TH:i', strtotime($activity['start_time']));
                    ?>
                    <input type="datetime-local" name="start_time" required value="<?= htmlspecialchars($start_val) ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Date et heure de fin *</label>
                    <?php
                        $end_val = $_POST['end_time'] ?? date('Y-m-d\TH:i', strtotime($activity['end_time']));
                    ?>
                    <input type="datetime-local" name="end_time" required value="<?= htmlspecialchars($end_val) ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
            </div>

            <!-- ── Participants max + Visibilité (2 colonnes) ──────────────── -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                        Participants max *
                        <!-- Indication du minimum en sous-texte si des personnes sont déjà inscrites -->
                        <?php if ($activity['nb_inscrits'] > 0): ?>
                            <span style="font-size:0.78rem; font-weight:400; color:var(--gray-500);">(min. <?= $activity['nb_inscrits'] ?> inscrits)</span>
                        <?php endif; ?>
                    </label>
                    <!-- min = max(2, nb_inscrits) : on ne peut pas réduire en-dessous des inscrits actuels -->
                    <input type="number" name="max_participants" required min="<?= max(2, (int)$activity['nb_inscrits']) ?>"
                           value="<?= htmlspecialchars($_POST['max_participants'] ?? $activity['max_participants']) ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Visibilité *</label>
                    <!-- $vis : valeur POST ou valeur actuelle en BDD -->
                    <?php $vis = $_POST['visibility'] ?? $activity['visibility']; ?>
                    <select name="visibility"
                            style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box; background:white;">
                        <option value="publique" <?= $vis === 'publique' ? 'selected' : '' ?>>Publique</option>
                        <option value="privee"   <?= $vis === 'privee'   ? 'selected' : '' ?>>Privée</option>
                    </select>
                </div>
            </div>

            <!-- ── Catégorie ─────────────────────────────────────────────── -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Catégorie *</label>
                <!-- $cur_cat : valeur POST ou valeur actuelle en BDD -->
                <?php $cur_cat = $_POST['category'] ?? $activity['category']; ?>
                <select name="category"
                        style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box; background:white;">
                    <?php foreach ($CATEGORY_MAP as $val => [$emoji, , $label]): ?>
                        <option value="<?= $val ?>" <?= $cur_cat === $val ? 'selected' : '' ?>>
                            <?= $emoji ?> <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ── Liste d'attente ────────────────────────────────────────
                 État coché/décoché : priorité $_POST si soumission avec erreur,
                 sinon valeur en BDD ($activity['liste_attente_active']). -->
            <?php $wl_checked = isset($_POST['liste_attente_active']) ? !empty($_POST['liste_attente_active']) : !empty($activity['liste_attente_active']); ?>
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:14px 16px;
                          border:1.5px solid var(--gray-200); border-radius:10px; background:var(--gray-50);">
                <input type="checkbox" name="liste_attente_active" value="1"
                       <?= $wl_checked ? 'checked' : '' ?>
                       style="width:18px; height:18px; accent-color:var(--orange); cursor:pointer;">
                <span style="color:var(--gray-700); font-weight:500;">
                    Activer la liste d'attente
                    <span style="display:block; font-size:0.8rem; font-weight:400; color:var(--gray-400);">
                        Les participants peuvent rejoindre une file d'attente si l'activité est complète.
                    </span>
                </span>
            </label>

            <!-- ── Photo de l'activité ────────────────────────────────────
                 Si une photo existe déjà, affiche une miniature + message explicatif.
                 L'import d'une nouvelle photo remplace l'ancienne (géré par le handler). -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Photo de l'activité
                    <span style="font-size:0.78rem; font-weight:400; color:var(--gray-400);">(JPG, PNG, WebP · max 2 Mo)</span>
                </label>
                <?php if (!empty($activity['photo'])): ?>
                <!-- Aperçu de la photo actuelle avec indication pour la remplacer -->
                <div style="margin-bottom:10px; display:flex; align-items:center; gap:12px;">
                    <img src="/sharetime/public/uploads/activites/<?= htmlspecialchars($activity['photo']) ?>"
                         style="width:80px; height:56px; object-fit:cover; border-radius:8px; border:2px solid var(--gray-200);">
                    <span style="font-size:0.82rem; color:var(--gray-500);">Photo actuelle — importer une nouvelle pour la remplacer</span>
                </div>
                <?php else: ?>
                <p style="font-size:0.82rem; color:var(--gray-400); margin:0 0 10px;">Aucune photo pour l'instant. Vous pouvez en ajouter une ci-dessous.</p>
                <?php endif; ?>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp"
                       style="font-family:inherit; font-size:0.9rem; color:var(--gray-700);">
            </div>

            <!-- ── Boutons d'action ────────────────────────────────────── -->
            <div style="display:flex; gap:12px; margin-top:8px;">
                <button type="submit" class="btn btn-orange btn-lg">Enregistrer les modifications</button>
                <!-- Annuler : retourne à la page de détail de l'activité -->
                <a href="/sharetime/public/?page=detail&id=<?= (int)$activity['idactivities'] ?>" class="btn btn-outline-navy btn-lg">Annuler</a>
            </div>
        </form>
    </div>
</main>
