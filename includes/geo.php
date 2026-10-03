<?php
function geocode(string $place): ?array {
    $url = 'https://nominatim.openstreetmap.org/search?'
         . http_build_query([
               'q'              => $place,
               'format'         => 'json',
               'limit'          => 1,
               'addressdetails' => 0,
           ]);

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: CrowdDropApp/1.0 (contact@CrowdDrop.local)\r\n",
            'timeout' => 5,
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return null;

    $data = json_decode($raw, true);
    if (empty($data[0])) return null;

    return [
        'lat'     => (float)$data[0]['lat'],
        'lng'     => (float)$data[0]['lon'],
        'display' => $data[0]['display_name'],
    ];
}

function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R  = 6371;
    $dL = deg2rad($lat2 - $lat1);
    $dG = deg2rad($lng2 - $lng1);
    $a  = sin($dL/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dG/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function withinRadius(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2, float $km = 10): bool {
    if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) return true;
    return haversine($lat1, $lng1, $lat2, $lng2) <= $km;
}
