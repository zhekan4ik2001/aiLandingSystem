<?php

namespace App\Services;

use App\Models\Airplane;
use App\Models\Point;
use App\Models\Route;
use App\Support\GeoCalculator;
use Illuminate\Support\Collection;

class FlightKinematicsService
{
    private const SPECIAL_FIRST_POINT_ALTITUDE_FEET = 35000.0;

    private const VERTICAL_RATE_FEET_PER_SECOND = 30.0;

    private const SPEED_CHANGE_PER_FOOT_KMH = 0.015;

    private const MIN_CRUISE_SPEED_KMH = 80.0;

    /**
     * Routes where the first leg is flown at 35 000 ft.
     *
     * @var list<string>
     */
    private const SPECIAL_CRUISE_ROUTE_NAMES = [
        'BEPOL - NANEB - LEPTO - ВПП 10L/28R',
        'BEPOL - NANEB - GEBKA - ВПП 10R/28L',
        'AKARU - ARNAT - MADID - ВПП 10L/28R',
        'AKARU - ARNAT - BIPRI - ВПП 10R/28L',
    ];

    /**
     * Adjust altitude and speed for one simulation step.
     */
    public function applyAltitudeAndSpeed(Airplane $airplane, float $timeAcceleration): void
    {
        if (! $airplane->route_id || ! $airplane->route) {
            return;
        }

        $routePoints = $airplane->route->orderedPoints()->values();
        if ($routePoints->isEmpty()) {
            return;
        }

        $targetPoint = $routePoints->get($airplane->current_point_index);
        if (! $targetPoint) {
            return;
        }

        $targetAltitude = $this->resolveTargetAltitude($airplane->route, $routePoints, $targetPoint, $airplane->current_point_index);
        $altitudeDelta = $this->stepAltitude($airplane, $targetAltitude, $timeAcceleration);

        $this->applySpeedFromAltitudeChange($airplane, $altitudeDelta);
        $this->applyFinalSegmentDeceleration($airplane, $routePoints);
    }

    /**
     * Target altitude for the current route leg.
     */
    private function resolveTargetAltitude(Route $route, Collection $routePoints, Point $targetPoint, int $pointIndex): float
    {
        if (
            $pointIndex === 0
            && in_array($route->name, self::SPECIAL_CRUISE_ROUTE_NAMES, true)
        ) {
            return self::SPECIAL_FIRST_POINT_ALTITUDE_FEET;
        }

        return (float) $targetPoint->altitude_feet;
    }

    /**
     * Move current altitude toward target by one step.
     */
    private function stepAltitude(Airplane $airplane, float $targetAltitude, float $timeAcceleration): float
    {
        $currentAltitude = (float) $airplane->altitude_feet;
        $maxDelta = self::VERTICAL_RATE_FEET_PER_SECOND * 0.5 * max(0, $timeAcceleration);
        $delta = max(-$maxDelta, min($maxDelta, $targetAltitude - $currentAltitude));
        $airplane->altitude_feet = round($currentAltitude + $delta, 2);

        return $delta;
    }

    /**
     * Adjust horizontal speed when altitude changes (climb slows, descent speeds up).
     */
    private function applySpeedFromAltitudeChange(Airplane $airplane, float $altitudeDelta): void
    {
        if (abs($altitudeDelta) < 0.01) {
            return;
        }

        $speed = (float) $airplane->speed;

        if ($altitudeDelta > 0) {
            $speed -= $altitudeDelta * self::SPEED_CHANGE_PER_FOOT_KMH;
        } else {
            $speed += abs($altitudeDelta) * self::SPEED_CHANGE_PER_FOOT_KMH;
        }

        $airplane->speed = round(max(self::MIN_CRUISE_SPEED_KMH, $speed), 2);
    }

    /**
     * Between the 3rd and 4th route points, reduce speed toward zero on approach.
     */
    private function applyFinalSegmentDeceleration(Airplane $airplane, Collection $routePoints): void
    {
        if ($routePoints->count() < 4) {
            return;
        }

        $finalIndex = $routePoints->count() - 1;
        if ($airplane->current_point_index !== $finalIndex) {
            return;
        }

        $finalPoint = $routePoints->get($finalIndex);
        $previousPoint = $routePoints->get($finalIndex - 1);

        if (! $finalPoint || ! $previousPoint) {
            return;
        }

        $segmentKm = GeoCalculator::distanceKm(
            (float) $previousPoint->latitude,
            (float) $previousPoint->longitude,
            (float) $finalPoint->latitude,
            (float) $finalPoint->longitude
        );

        $distanceToFinalKm = GeoCalculator::distanceKm(
            (float) $airplane->latitude,
            (float) $airplane->longitude,
            (float) $finalPoint->latitude,
            (float) $finalPoint->longitude
        );

        if ($segmentKm <= 0) {
            $airplane->speed = 0;

            return;
        }

        $progress = min(1, max(0, $distanceToFinalKm / $segmentKm));
        $airplane->speed = round((float) $airplane->speed * $progress, 2);
    }
}
