<?php
/**
 * public/api/geocode.php — API de géocodage (adresse → coordonnées GPS)
 *
 * Reçoit un paramètre GET ?q= (adresse ou lieu + ville) et retourne
 * le tableau JSON des résultats Nominatim (OpenStreetMap).
 *
 * Utilisé par les formulaires Leaflet de creer.php et modifier_activite.php
 * pour placer automatiquement un marqueur sur la carte quand l'utilisateur
 * clique sur "Géolocaliser l'adresse".
 *
 * Stratégie en cascade :
 *   1. Recherche exacte en France (countrycodes=fr)
 *   2. Recherche structurée rue/ville si la requête contient une virgule
 *   3. Recherche mondiale sans restriction pays (dernier recours)
 *
 * cURL est utilisé (plutôt que file_get_contents) pour pouvoir définir
 * le User-Agent requis par la politique d'utilisation de Nominatim.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Récupère et nettoie la requête de géocodage transmise en GET (?q=...)
$search_query = trim($_GET['q'] ?? '');

// Retourne un tableau vide si la requête est absente (évite un appel inutile à Nominatim)
if (!$search_query) { echo '[]'; exit; }

/**
 * nominatim_curl() — Effectue une requête GET vers Nominatim via cURL.
 *
 * @param string $api_url  URL complète de l'endpoint Nominatim avec ses paramètres
 * @return array           Tableau de résultats décodés, vide si erreur réseau ou parse
 */
function nominatim_curl($api_url) {
    $curl_handle = curl_init($api_url);
    curl_setopt_array($curl_handle, [
        CURLOPT_RETURNTRANSFER => true,   // retourne la réponse comme chaîne plutôt que de l'afficher
        CURLOPT_TIMEOUT        => 8,      // abandon si Nominatim ne répond pas en 8 secondes
        CURLOPT_HTTPHEADER     => [
            'User-Agent: ShareTime/1.0 (pouzol.benji@gmail.com)',  // requis par Nominatim (politique d'usage)
            'Accept-Language: fr',                                    // préfère les libellés en français
        ],
        CURLOPT_SSL_VERIFYPEER => false,  // évite les erreurs SSL sur certaines configs XAMPP locales
    ]);
    $raw_curl_result = curl_exec($curl_handle);
    curl_close($curl_handle);
    // Retourne [] si cURL a échoué (réseau, timeout) ou si la réponse n'est pas du JSON valide
    if (!$raw_curl_result) return [];
    $decoded_json_results = json_decode($raw_curl_result, true);
    return is_array($decoded_json_results) ? $decoded_json_results : [];
}

// ── Stratégie 1 : recherche exacte en France ──────────────────────────────
// Restreint aux résultats français (countrycodes=fr) pour favoriser la pertinence
// dans le contexte d'une plateforme d'activités locales.
$geocoding_results = nominatim_curl(
    'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=fr&q=' . urlencode($search_query)
);

// ── Stratégie 2 : recherche structurée rue/ville (si la requête a une virgule) ─
// Plus tolérante sur les noms de rue flous ; sépare adresse et ville en paramètres
// distincts pour aider Nominatim à désambiguïser.
if (empty($geocoding_results) && str_contains($search_query, ',')) {
    [$street_part, $city_part] = array_map('trim', explode(',', $search_query, 2));
    $geocoding_results = nominatim_curl(
        'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=fr'
        . '&street=' . urlencode($street_part)
        . '&city='   . urlencode($city_part)
    );
}

// ── Stratégie 3 : recherche mondiale sans restriction pays (dernier recours) ─
// Utilisé si les deux premières stratégies ont échoué (ex : lieu à l'étranger,
// adresse très particulière, orthographe approximative).
if (empty($geocoding_results)) {
    $geocoding_results = nominatim_curl(
        'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($search_query)
    );
}

// Retourne les résultats au format JSON au client JavaScript appelant
echo json_encode($geocoding_results);
