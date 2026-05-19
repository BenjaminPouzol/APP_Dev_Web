<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$q = trim($_GET['q'] ?? '');
if (!$q) { echo '[]'; exit; }

function nominatim_curl($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: ShareTime/1.0 (pouzol.benji@gmail.com)',
            'Accept-Language: fr',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    if (!$result) return [];
    $data = json_decode($result, true);
    return is_array($data) ? $data : [];
}

// Stratégie 1 : recherche exacte France
$results = nominatim_curl(
    'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=fr&q=' . urlencode($q)
);

// Stratégie 2 : recherche structurée (plus tolérante sur la ville)
if (empty($results) && str_contains($q, ',')) {
    [$street, $city] = array_map('trim', explode(',', $q, 2));
    $results = nominatim_curl(
        'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=fr'
        . '&street=' . urlencode($street)
        . '&city='   . urlencode($city)
    );
}

// Stratégie 3 : sans restriction pays
if (empty($results)) {
    $results = nominatim_curl(
        'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($q)
    );
}

echo json_encode($results);
