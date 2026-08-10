<?php

declare(strict_types=1);

/**
 * Uber H3 proximity helpers with haversine fallback (no FFI required).
 */
final class H3
{
    public const RES = 8;

    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * Approximate H3-like cell key (grid bucket) when native H3 unavailable.
     * Good enough for nearby shortlisting with radius filter.
     */
    public static function cellKey(float $lat, float $lng, float $cellKm = 0.5): string
    {
        $latStep = $cellKm / 111.0;
        $lngStep = $cellKm / max(0.01, 111.0 * cos(deg2rad($lat)));
        $i = (int) floor($lat / $latStep);
        $j = (int) floor($lng / $lngStep);
        return 'g' . $i . '_' . $j;
    }

    public static function latLngToCell(float $lat, float $lng, int $res = self::RES): string
    {
        if (self::ffiAvailable()) {
            try {
                if (class_exists('\\H3\\H3', false)) {
                    $h3 = new \H3\H3();
                    if (method_exists($h3, 'latLngToCell')) {
                        return (string) $h3->latLngToCell($lat, $lng, $res);
                    }
                }
                if (function_exists('geoToH3')) {
                    return (string) geoToH3($lat, $lng, $res);
                }
            } catch (Throwable $e) {
                // fall through
            }
        }
        return self::cellKey($lat, $lng);
    }

    public static function ffiAvailable(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $cached = false;
        try {
            if (extension_loaded('ffi') && class_exists('\\H3\\H3', false)) {
                $cached = true;
            } elseif (function_exists('geoToH3')) {
                $cached = true;
            }
        } catch (Throwable $e) {
            $cached = false;
        }
        return $cached;
    }

    /**
     * Approx travel minutes from straight-line km (urban fudge ~22 km/h avg).
     */
    public static function approxMinutesFromKm(float $km): int
    {
        return max(5, (int) ceil(($km / 22.0) * 60) + 5);
    }
}
