<?php

namespace App\Support;

class GeoCalculator
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Calculate distance in kilometers with haversine formula.
     */
    public static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1, sqrt($a)));
    }

    /**
     * Calculate destination point by distance and bearing.
     */
    public static function destinationPoint(float $lat, float $lon, float $bearingDeg, float $distanceKm): array
    {
        $angularDistance = $distanceKm / self::EARTH_RADIUS_KM;
        $bearingRad = deg2rad($bearingDeg);
        $latRad = deg2rad($lat);
        $lonRad = deg2rad($lon);

        $destLat = asin(
            sin($latRad) * cos($angularDistance)
            + cos($latRad) * sin($angularDistance) * cos($bearingRad)
        );

        $destLon = $lonRad + atan2(
            sin($bearingRad) * sin($angularDistance) * cos($latRad),
            cos($angularDistance) - sin($latRad) * sin($destLat)
        );

        return [
            'latitude' => rad2deg($destLat),
            'longitude' => rad2deg($destLon),
        ];
    }

    /**
     * Calculate azimuth in degrees from point A to point B.
     */
    public static function bearing(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $dLon = deg2rad($lon2 - $lon1);

        $y = sin($dLon) * cos($lat2Rad);
        $x = cos($lat1Rad) * sin($lat2Rad) - sin($lat1Rad) * cos($lat2Rad) * cos($dLon);

        return fmod((rad2deg(atan2($y, $x)) + 360), 360);
    }
}
