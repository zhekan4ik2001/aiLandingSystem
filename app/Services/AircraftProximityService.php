<?php

namespace App\Services;

use App\Models\Airplane;
use App\Models\Setting;
use App\Support\GeoCalculator;

class AircraftProximityService
{
    private const MIN_SAFE_DISTANCE_KM = 1.0;

    private const CONFLICT_CIRCLE_RADIUS_KM = 1.0;

    /**
     * Find active airplane pairs closer than 1 km horizontally and vertically.
     *
     * @return list<array{airplane_ids: array{int, int}, latitude: float, longitude: float, radius_km: float}>
     */
    public function detectConflicts(): array
    {
        $airplanes = Airplane::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $minVerticalSeparationFeet = (float) config(
            'air-traffic-control.min_vertical_separation_feet',
            1000
        );

        $conflicts = [];

        for ($i = 0; $i < $airplanes->count(); $i++) {
            for ($j = $i + 1; $j < $airplanes->count(); $j++) {
                $first = $airplanes[$i];
                $second = $airplanes[$j];

                $horizontalKm = GeoCalculator::distanceKm(
                    (float) $first->latitude,
                    (float) $first->longitude,
                    (float) $second->latitude,
                    (float) $second->longitude
                );

                if ($horizontalKm >= self::MIN_SAFE_DISTANCE_KM) {
                    continue;
                }

                $verticalSeparationFeet = abs((float) $first->altitude_feet - (float) $second->altitude_feet);
                if ($verticalSeparationFeet >= $minVerticalSeparationFeet) {
                    continue;
                }

                $conflicts[] = [
                    'airplane_ids' => [(int) $first->id, (int) $second->id],
                    'latitude' => round(((float) $first->latitude + (float) $second->latitude) / 2, 8),
                    'longitude' => round(((float) $first->longitude + (float) $second->longitude) / 2, 8),
                    'radius_km' => self::CONFLICT_CIRCLE_RADIUS_KM,
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Freeze simulation time when conflicts exist.
     */
    public function freezeTimeIfConflicts(array $conflicts): void
    {
        if ($conflicts === []) {
            return;
        }

        Setting::query()
            ->where('name', 'accelerationOfTime')
            ->update(['value' => '0']);
    }
}
