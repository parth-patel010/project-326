<?php

declare(strict_types=1);

/**
 * OSRM routing client (public demo server by default).
 */
final class Osrm
{
    private string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim(
            $baseUrl ?: (Env::get('OSRM_BASE_URL', 'https://router.project-osrm.org') ?? 'https://router.project-osrm.org'),
            '/'
        );
    }

    /**
     * @return array{ok:bool,distance_km?:float,duration_sec?:int,duration_min?:int,geometry?:array,error?:string}
     */
    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        $url = sprintf(
            '%s/route/v1/driving/%s,%s;%s,%s?overview=full&geometries=geojson',
            $this->baseUrl,
            $fromLng,
            $fromLat,
            $toLng,
            $toLat
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($errno) {
            return $this->fallback($fromLat, $fromLng, $toLat, $toLng, 'OSRM curl: ' . $err);
        }

        $data = json_decode((string) $body, true);
        if (!is_array($data) || ($data['code'] ?? '') !== 'Ok' || empty($data['routes'][0])) {
            return $this->fallback($fromLat, $fromLng, $toLat, $toLng, 'OSRM route failed');
        }

        $route = $data['routes'][0];
        $meters = (float) ($route['distance'] ?? 0);
        $seconds = (int) round((float) ($route['duration'] ?? 0));
        $coords = $route['geometry']['coordinates'] ?? [];
        // GeoJSON is [lng,lat] — convert to [lat,lng] for maps
        $polyline = [];
        if (is_array($coords)) {
            foreach ($coords as $c) {
                if (is_array($c) && count($c) >= 2) {
                    $polyline[] = [(float) $c[1], (float) $c[0]];
                }
            }
        }

        return [
            'ok' => true,
            'distance_km' => round($meters / 1000, 3),
            'duration_sec' => $seconds,
            'duration_min' => max(1, (int) ceil($seconds / 60)),
            'geometry' => $polyline,
        ];
    }

    private function fallback(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng,
        string $reason
    ): array {
        $km = H3::haversineKm($fromLat, $fromLng, $toLat, $toLng);
        $min = H3::approxMinutesFromKm($km);
        return [
            'ok' => true,
            'distance_km' => round($km, 3),
            'duration_sec' => $min * 60,
            'duration_min' => $min,
            'geometry' => [[$fromLat, $fromLng], [$toLat, $toLng]],
            'fallback' => true,
            'error' => $reason,
        ];
    }
}
