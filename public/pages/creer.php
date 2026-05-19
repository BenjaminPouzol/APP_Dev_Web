<?php
/**
 * public/pages/creer.php — Formulaire de création d'activité
 *
 * Variables disponibles (préparées par index.php) :
 *   $error       : message d'erreur de validation (via handlers/activity.php)
 *   $CATEGORY_MAP: mapping catégorie → [emoji, classe CSS, libellé]
 *
 * La logique POST (validation, upload photo, insertion BDD) est dans
 * app/handlers/activity.php (bloc $page === 'creer').
 *
 * Les valeurs des champs sont pré-remplies depuis $_POST pour conserver
 * la saisie en cas d'erreur côté serveur (sauf le file input, impossible
 * à pré-remplir pour des raisons de sécurité navigateur).
 */
?>
<!-- ── CONTENEUR PRINCIPAL ────────────────────────────────────────────────────
     max-width:700px + margin:auto pour centrer la card de formulaire. -->
<main class="container" style="padding:40px 0; max-width:700px; margin:auto;">
    <h1 style="margin-bottom:8px; color:var(--navy);">Créer une activité</h1>
    <p style="color:var(--gray-500); margin-bottom:32px;">Remplis le formulaire pour proposer ton activité à la communauté.</p>

    <!-- Erreur serveur : affichée en bandeau rouge au-dessus du formulaire -->
    <?php if (!empty($error)): ?>
        <div style="background:#FEE2E2; color:#DC2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:500;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Card blanche qui contient le formulaire -->
    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px;">
        <!-- enctype multipart/form-data obligatoire pour l'upload de photo -->
        <form method="POST" action="/sharetime/public/?page=creer" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:20px;">
            <!-- Token CSRF : requis par csrf_check() dans le handler -->
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- ── Titre ──────────────────────────────────────────────────── -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Titre *</label>
                <!-- value depuis $_POST pour conserver la saisie si erreur de validation -->
                <input type="text" name="title" required placeholder="Ex : Randonnée en forêt"
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                       style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
            </div>

            <!-- ── Description ────────────────────────────────────────────── -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Description *</label>
                <!-- textarea : pas de value, contenu entre les balises (htmlspecialchars obligatoire) -->
                <textarea name="description" required rows="4" placeholder="Décris ton activité, le programme, ce qu'il faut prévoir..."
                          style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; resize:vertical; box-sizing:border-box;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <!-- ── Lieu + Ville (2 colonnes) ──────────────────────────────── -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Lieu *</label>
                    <input type="text" name="location" required placeholder="Ex : Forêt de Fontainebleau"
                           value="<?= htmlspecialchars($_POST['location'] ?? '') ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Ville *</label>
                    <input type="text" name="city" required placeholder="Ex : Paris"
                           value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
            </div>

            <!-- ── Localisation sur la carte ──────────────────────────────── -->
            <link rel="stylesheet" href="/sharetime/public/css/leaflet.css">
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Localisation sur la carte
                    <span style="font-weight:400; color:var(--gray-400); font-size:0.82rem;">(optionnel — permet d'afficher l'activité sur la carte)</span>
                </label>
                <div style="display:flex; gap:8px; margin-bottom:8px;">
                    <button type="button" id="geocode-btn"
                            style="padding:8px 16px; background:var(--navy); color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                        📍 Géolocaliser l'adresse
                    </button>
                    <span id="geocode-status" style="font-size:0.82rem; color:var(--gray-500); align-self:center;"></span>
                </div>
                <div id="mini-map" style="height:220px; border-radius:10px; border:1.5px solid var(--gray-300); overflow:hidden;"></div>
                <input type="hidden" name="latitude"  id="lat-input"  value="<?= htmlspecialchars($_POST['latitude']  ?? '') ?>">
                <input type="hidden" name="longitude" id="lng-input"  value="<?= htmlspecialchars($_POST['longitude'] ?? '') ?>">
                <p id="coords-display" style="font-size:0.78rem; color:var(--gray-400); margin:6px 0 0;">
                    <?php if (!empty($_POST['latitude'])): ?>
                        Position : <?= htmlspecialchars($_POST['latitude']) ?>, <?= htmlspecialchars($_POST['longitude']) ?>
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

                <?php if (!empty($_POST['latitude']) && !empty($_POST['longitude'])): ?>
                setMarker(<?= (float)$_POST['latitude'] ?>, <?= (float)$_POST['longitude'] ?>);
                map.setView([<?= (float)$_POST['latitude'] ?>, <?= (float)$_POST['longitude'] ?>], 13);
                <?php endif; ?>

                map.on('click', function(e) { setMarker(e.latlng.lat, e.latlng.lng); });

                document.getElementById('geocode-btn').addEventListener('click', function() {
                    var loc  = document.querySelector('[name="location"]').value.trim();
                    var city = document.querySelector('[name="city"]').value.trim();
                    var q    = [loc, city].filter(Boolean).join(', ');
                    if (!q) { document.getElementById('geocode-status').textContent = 'Remplis d\'abord les champs Lieu et Ville.'; return; }
                    var st = document.getElementById('geocode-status');
                    st.textContent = 'Recherche…';
                    fetch('/sharetime/public/api/geocode.php?q=' + encodeURIComponent(q))
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.length) { st.textContent = 'Introuvable — vérifiez l\'orthographe (surtout la ville), ou cliquez sur la carte.'; return; }
                        var lat = parseFloat(data[0].lat), lng = parseFloat(data[0].lon);
                        setMarker(lat, lng);
                        map.setView([lat, lng], 14);
                        st.textContent = '';
                    })
                    .catch(function() { st.textContent = 'Erreur réseau, clique sur la carte.'; });
                });
            })();
            </script>

            <!-- ── Dates début + fin (2 colonnes) ────────────────────────── -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Date et heure de début *</label>
                    <input type="datetime-local" name="start_time" required
                           value="<?= htmlspecialchars($_POST['start_time'] ?? '') ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Date et heure de fin *</label>
                    <input type="datetime-local" name="end_time" required
                           value="<?= htmlspecialchars($_POST['end_time'] ?? '') ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
            </div>

            <!-- ── Participants max + Visibilité (2 colonnes) ─────────────── -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Participants max *</label>
                    <!-- min="2" : une activité nécessite au moins 2 personnes (organisateur + 1) -->
                    <input type="number" name="max_participants" required min="2" placeholder="Ex : 10"
                           value="<?= htmlspecialchars($_POST['max_participants'] ?? '') ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Visibilité *</label>
                    <select name="visibility"
                            style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box; background:white;">
                        <!-- Pré-sélection depuis $_POST en cas de retour d'erreur -->
                        <option value="publique" <?= ($_POST['visibility'] ?? '') === 'publique' ? 'selected' : '' ?>>Publique</option>
                        <option value="privee"   <?= ($_POST['visibility'] ?? '') === 'privee'   ? 'selected' : '' ?>>Privée</option>
                    </select>
                </div>
            </div>

            <!-- ── Catégorie ──────────────────────────────────────────────── -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Catégorie *</label>
                <select name="category"
                        style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box; background:white;">
                    <?php foreach ($CATEGORY_MAP as $val => [$emoji, , $label]): ?>
                        <!-- Pré-sélection : 'autre' par défaut si aucune saisie précédente -->
                        <option value="<?= $val ?>" <?= ($_POST['category'] ?? 'autre') === $val ? 'selected' : '' ?>>
                            <?= $emoji ?> <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ── Liste d'attente ────────────────────────────────────────── -->
            <!-- Case à cocher : quand cochée, les participants peuvent rejoindre une
                 file d'attente si l'activité est complète (géré par Activity::registerWaitlist). -->
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:14px 16px;
                          border:1.5px solid var(--gray-200); border-radius:10px; background:var(--gray-50);">
                <input type="checkbox" name="liste_attente_active" value="1"
                       <?= !empty($_POST['liste_attente_active']) ? 'checked' : '' ?>
                       style="width:18px; height:18px; accent-color:var(--orange); cursor:pointer;">
                <span style="color:var(--gray-700); font-weight:500;">
                    Activer la liste d'attente
                    <span style="display:block; font-size:0.8rem; font-weight:400; color:var(--gray-400);">
                        Les participants peuvent rejoindre une file d'attente si l'activité est complète.
                    </span>
                </span>
            </label>

            <!-- ── Photo (optionnelle) ─────────────────────────────────────
                 Traitement côté serveur via upload_image() dans helpers.php :
                 validation MIME réelle (finfo), max 2 Mo, stockage dans uploads/activites/. -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Photo de l'activité
                    <span style="font-size:0.78rem; font-weight:400; color:var(--gray-400);">(optionnelle · JPG, PNG, WebP · max 2 Mo)</span>
                </label>
                <!-- accept= filtre côté navigateur seulement, la validation réelle est serveur -->
                <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp"
                       style="font-family:inherit; font-size:0.9rem; color:var(--gray-700);">
            </div>

            <!-- ── Boutons d'action ──────────────────────────────────────── -->
            <div style="display:flex; gap:12px; margin-top:8px;">
                <button type="submit" class="btn btn-orange btn-lg">Créer l'activité</button>
                <!-- Lien Annuler : retourne à la liste des activités sans soumettre -->
                <a href="/sharetime/public/?page=activites" class="btn btn-outline-navy btn-lg">Annuler</a>
            </div>
        </form>
    </div>
</main>
