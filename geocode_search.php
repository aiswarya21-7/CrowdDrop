<?php

require_once 'includes/config.php';
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
    'q'              => $q,
    'format'         => 'json',
    'limit'          => 6,
    'addressdetails' => 1,
]);

$ctx = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'header'  => "User-Agent: CrowdDropApp/1.0 (contact@CrowdDrop.local)\r\n",
        'timeout' => 5,
    ],
]);

$raw = @file_get_contents($url, false, $ctx);
if (!$raw) {
    echo json_encode([]);
    exit;
}

$results = json_decode($raw, true);
$out = [];
foreach (($results ?: []) as $r) {
    $addr  = $r['address'] ?? [];
    $parts = array_filter([
        $addr['city']     ?? $addr['town']    ?? $addr['village'] ?? $addr['county'] ?? null,
        $addr['state']    ?? null,
        $addr['country']  ?? null,
    ]);
    $label = implode(', ', $parts) ?: $r['display_name'];

    $out[] = [
        'label' => $label,
        'full'  => $r['display_name'],
        'lat'   => (float)$r['lat'],
        'lng'   => (float)$r['lon'],
    ];
}

echo json_encode($out);
