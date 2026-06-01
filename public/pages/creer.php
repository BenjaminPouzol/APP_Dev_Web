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
    <?php if (!empty($error)): // Vérifie si une erreur de validation existe avant d'afficher le bandeau ?>
        <div style="background:#FEE2E2; color:#DC2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:500;">
            <?= htmlspecialchars($error) ?> <!-- Affiche le message d'erreur en sécurisé pour éviter les injections XSS -->
        </div>
    <?php endif; ?>

    <!-- Card blanche qui contient le formulaire -->
    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px;">
        <!-- enctype multipart/form-data obligatoire pour l'upload de photo -->
        <form method="POST" action="/sharetime/public/?page=creer" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:20px;"> <!-- Le formulaire envoie les données en POST, enctype multipart permet l'envoi de fichier -->
            <!-- Token CSRF : requis par csrf_check() dans le handler -->
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"> <!-- Jeton de sécurité anti-CSRF généré côté serveur -->

            <!-- ── Titre ──────────────────────────────────────────────────── -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Titre *</label>
                <!-- value depuis $_POST pour conserver la saisie si erreur de validation -->
                <!-- Pré-remplit le titre depuis $_POST (vide par défaut) en sécurisant la valeur -->
                <input type="text" name="title" required placeholder="Ex : Randonnée en forêt"
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                       style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
            </div>

            <!-- ── Description ────────────────────────────────────────────── -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Description *</label>
                <!-- textarea : pas de value, contenu entre les balises (htmlspecialchars obligatoire) -->
                <textarea name="description" required rows="4" placeholder="Décris ton activité, le programme, ce qu'il faut prévoir..."
                          style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; resize:vertical; box-sizing:border-box;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea> <!-- Pré-remplit le contenu du textarea depuis $_POST en sécurisant la valeur -->
            </div>

            <!-- ── Lieu + Ville (2 colonnes) ──────────────────────────────── -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;"> <!-- Grille CSS à 2 colonnes égales pour aligner les champs Lieu et Ville -->
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Lieu *</label>
                    <!-- Pré-remplit le lieu depuis $_POST en sécurisant la valeur -->
                    <input type="text" name="location" required placeholder="Ex : Forêt de Fontainebleau"
                           value="<?= htmlspecialchars($_POST['location'] ?? '') ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Ville *</label>
                    <!-- Pré-remplit la ville depuis $_POST en sécurisant la valeur -->
                    <input type="text" name="city" required placeholder="Ex : Paris"
                           value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
            </div>

            <!-- ── Localisation sur la carte ──────────────────────────────── -->
            <link rel="stylesheet" href="/sharetime/public/css/leaflet.css"> <!-- Charge la feuille de style de la librairie Leaflet pour la carte interactive -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Localisation sur la carte
                    <span style="font-weight:400; color:var(--gray-400); font-size:0.82rem;">(optionnel — permet d'afficher l'activité sur la carte)</span>
                </label>
                <div style="display:flex; gap:8px; margin-bottom:8px;">
                    <!-- Bouton de géocodage : déclenche la recherche de coordonnées via l'API geocode.php -->
                    <button type="button" id="geocode-btn"
                            style="padding:8px 16px; background:var(--navy); color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                        📍 Géolocaliser l'adresse
                    </button>
                    <span id="geocode-status" style="font-size:0.82rem; color:var(--gray-500); align-self:center;"></span> <!-- Zone d'affichage du statut de la géolocalisation (chargement, erreur, succès) -->
                </div>
                <div id="mini-map" style="height:220px; border-radius:10px; border:1.5px solid var(--gray-300); overflow:hidden;"></div> <!-- Conteneur dans lequel Leaflet affichera la carte interactive -->
                <input type="hidden" name="latitude"  id="lat-input"  value="<?= htmlspecialchars($_POST['latitude']  ?? '') ?>"> <!-- Champ caché qui stocke la latitude choisie pour l'envoi au serveur -->
                <input type="hidden" name="longitude" id="lng-input"  value="<?= htmlspecialchars($_POST['longitude'] ?? '') ?>"> <!-- Champ caché qui stocke la longitude choisie pour l'envoi au serveur -->
                <p id="coords-display" style="font-size:0.78rem; color:var(--gray-400); margin:6px 0 0;">
                    <?php if (!empty($_POST['latitude'])): // Affiche les coordonnées déjà saisies en cas de retour d'erreur de formulaire ?>
                        Position : <?= htmlspecialchars($_POST['latitude']) ?>, <?= htmlspecialchars($_POST['longitude']) ?> <!-- Réaffiche les coordonnées précédemment choisies -->
                    <?php else: ?>
                        Cliquez sur la carte ou utilisez le bouton pour placer le marqueur. <!-- Message d'aide affiché par défaut si aucune coordonnée n'est encore saisie -->
                    <?php endif; ?>
                </p>
            </div>
            <script src="/sharetime/public/js/leaflet.js"></script> <!-- Charge la librairie JavaScript Leaflet pour la carte interactive -->
            <script>
            (function() {
                var map = L.map('mini-map').setView([46.5, 2.5], 5); // Initialise la carte Leaflet centrée sur la France avec un zoom national
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { // Configure les tuiles OpenStreetMap comme fond de carte
                    attribution: '© OpenStreetMap', maxZoom: 18 // Crédite OpenStreetMap et limite le zoom maximum à 18
                }).addTo(map); // Ajoute la couche de tuiles à la carte

                var marker = null; // Variable pour stocker le marqueur placé sur la carte (null = pas encore placé)
                var latIn  = document.getElementById('lat-input'); // Référence au champ caché latitude
                var lngIn  = document.getElementById('lng-input'); // Référence au champ caché longitude
                var disp   = document.getElementById('coords-display'); // Référence au paragraphe affichant les coordonnées lisibles

                function setMarker(lat, lng) { // Fonction qui place ou déplace le marqueur à une position donnée
                    if (marker) marker.setLatLng([lat, lng]); // Si un marqueur existe déjà, déplace-le vers les nouvelles coordonnées
                    else marker = L.marker([lat, lng], { draggable: true }).addTo(map); // Sinon crée un nouveau marqueur déplaçable et l'ajoute à la carte
                    marker.on('dragend', function(e) { // Écoute la fin du glisser-déposer du marqueur
                        var p = e.target.getLatLng(); // Récupère la position finale du marqueur après déplacement
                        latIn.value = p.lat.toFixed(7); // Met à jour le champ caché latitude avec 7 décimales de précision
                        lngIn.value = p.lng.toFixed(7); // Met à jour le champ caché longitude avec 7 décimales de précision
                        disp.textContent = 'Position : ' + p.lat.toFixed(5) + ', ' + p.lng.toFixed(5); // Affiche la nouvelle position dans le paragraphe (5 décimales pour la lisibilité)
                    });
                    latIn.value = lat.toFixed(7); // Enregistre la latitude dans le champ caché avec 7 décimales
                    lngIn.value = lng.toFixed(7); // Enregistre la longitude dans le champ caché avec 7 décimales
                    disp.textContent = 'Position : ' + lat.toFixed(5) + ', ' + lng.toFixed(5); // Affiche les coordonnées dans le paragraphe lisible
                }

                <?php if (!empty($_POST['latitude']) && !empty($_POST['longitude'])): // Si des coordonnées existent en $_POST, les restaure sur la carte ?>
                setMarker(<?= (float)$_POST['latitude'] ?>, <?= (float)$_POST['longitude'] ?>); // Place le marqueur aux coordonnées précédemment saisies (cast float pour sécurité)
                map.setView([<?= (float)$_POST['latitude'] ?>, <?= (float)$_POST['longitude'] ?>], 13); // Centre la carte sur ces coordonnées avec un zoom de quartier
                <?php endif; ?>

                map.on('click', function(e) { setMarker(e.latlng.lat, e.latlng.lng); }); // Au clic sur la carte, place le marqueur à l'endroit cliqué

                document.getElementById('geocode-btn').addEventListener('click', function() { // Écoute le clic sur le bouton "Géolocaliser"
                    var loc  = document.querySelector('[name="location"]').value.trim(); // Lit la valeur du champ Lieu et supprime les espaces inutiles
                    var city = document.querySelector('[name="city"]').value.trim(); // Lit la valeur du champ Ville et supprime les espaces inutiles
                    var q    = [loc, city].filter(Boolean).join(', '); // Assemble la requête de géocodage en ignorant les valeurs vides
                    if (!q) { document.getElementById('geocode-status').textContent = 'Remplis d\'abord les champs Lieu et Ville.'; return; } // Interrompt si aucun champ n'est rempli
                    var st = document.getElementById('geocode-status'); // Référence au span de statut pour afficher les messages de progression
                    st.textContent = 'Recherche…'; // Affiche un message de chargement pendant la requête
                    fetch('/sharetime/public/api/geocode.php?q=' + encodeURIComponent(q)) // Envoie la requête à l'API de géocodage interne avec la query encodée
                    .then(function(r) { return r.json(); }) // Convertit la réponse HTTP en objet JSON
                    .then(function(data) {
                        if (!data.length) { st.textContent = 'Introuvable — vérifiez l\'orthographe (surtout la ville), ou cliquez sur la carte.'; return; } // Aucun résultat : affiche un message d'aide et arrête le traitement
                        var lat = parseFloat(data[0].lat), lng = parseFloat(data[0].lon); // Extrait la latitude et la longitude du premier résultat de géocodage
                        setMarker(lat, lng); // Place le marqueur aux coordonnées trouvées
                        map.setView([lat, lng], 14); // Centre et zoome la carte sur le lieu trouvé
                        st.textContent = ''; // Efface le message de statut après succès
                    })
                    .catch(function() { st.textContent = 'Erreur réseau, clique sur la carte.'; }); // Affiche un message d'erreur si la requête réseau a échoué
                });
            })();
            </script>

            <!-- ── Dates début + fin (2 colonnes) ────────────────────────── -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;"> <!-- Grille CSS à 2 colonnes pour aligner les champs de date côte à côte -->
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Date et heure de début *</label>
                    <!-- Champ de sélection de date et heure (format HTML5 natif) -->
                    <!-- Pré-remplit la date de début depuis $_POST en cas d'erreur de validation -->
                    <input type="datetime-local" name="start_time" required
                           value="<?= htmlspecialchars($_POST['start_time'] ?? '') ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Date et heure de fin *</label>
                    <!-- Champ de sélection de date et heure de fin (validé côté serveur pour être > start_time) -->
                    <!-- Pré-remplit la date de fin depuis $_POST en cas d'erreur de validation -->
                    <input type="datetime-local" name="end_time" required
                           value="<?= htmlspecialchars($_POST['end_time'] ?? '') ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
            </div>

            <!-- ── Participants max + Visibilité (2 colonnes) ─────────────── -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;"> <!-- Grille CSS à 2 colonnes pour aligner les champs côte à côte -->
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Participants max *</label>
                    <!-- min="2" : une activité nécessite au moins 2 personnes (organisateur + 1) -->
                    <!-- min="2" empêche la création d'une activité solo côté navigateur -->
                    <!-- Pré-remplit le nombre de participants depuis $_POST en cas d'erreur -->
                    <input type="number" name="max_participants" required min="2" placeholder="Ex : 10"
                           value="<?= htmlspecialchars($_POST['max_participants'] ?? '') ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Visibilité *</label>
                    <select name="visibility"
                            style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box; background:white;">
                        <!-- Pré-sélection depuis $_POST en cas de retour d'erreur -->
                        <option value="publique" <?= ($_POST['visibility'] ?? '') === 'publique' ? 'selected' : '' ?>>Publique</option> <!-- Pré-sélectionne "Publique" si c'était la valeur choisie avant l'erreur -->
                        <option value="privee"   <?= ($_POST['visibility'] ?? '') === 'privee'   ? 'selected' : '' ?>>Privée</option> <!-- Pré-sélectionne "Privée" si c'était la valeur choisie avant l'erreur -->
                    </select>
                </div>
            </div>

            <!-- ── Catégorie ──────────────────────────────────────────────── -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Catégorie *</label>
                <select name="category"
                        style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box; background:white;">
                    <?php foreach ($CATEGORY_MAP as $val => [$emoji, , $label]): // Itère sur toutes les catégories disponibles (destructuring : ignore la classe CSS) ?>
                        <!-- Pré-sélection : 'autre' par défaut si aucune saisie précédente -->
                        <option value="<?= $val ?>" <?= ($_POST['category'] ?? 'autre') === $val ? 'selected' : '' ?>> <!-- Pré-sélectionne la catégorie choisie avant l'erreur, 'autre' par défaut -->
                            <?= $emoji ?> <?= $label ?> <!-- Affiche l'emoji et le libellé de chaque catégorie dans la liste déroulante -->
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ── Liste d'attente ────────────────────────────────────────── -->
            <!-- Case à cocher : quand cochée, les participants peuvent rejoindre une
                 file d'attente si l'activité est complète (géré par Activity::registerWaitlist). -->
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:14px 16px;
                          border:1.5px solid var(--gray-200); border-radius:10px; background:var(--gray-50);">
                <!-- La valeur "1" est envoyée si la case est cochée, rien sinon -->
                <!-- Restitue l'état coché si la case l'était avant l'erreur de validation -->
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
                <!-- Filtre les types de fichiers dans le sélecteur navigateur (non contraignant côté client) -->
                <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp"
                       style="font-family:inherit; font-size:0.9rem; color:var(--gray-700);">
            </div>

            <!-- ── Boutons d'action ──────────────────────────────────────── -->
            <div style="display:flex; gap:12px; margin-top:8px;">
                <button type="submit" class="btn btn-orange btn-lg">Créer l'activité</button> <!-- Bouton de soumission principal : envoie tout le formulaire -->
                <!-- Lien Annuler : retourne à la liste des activités sans soumettre -->
                <a href="/sharetime/public/?page=activites" class="btn btn-outline-navy btn-lg">Annuler</a> <!-- Lien de retour qui abandonne la création sans rien enregistrer -->
            </div>
        </form>
    </div>
</main>
