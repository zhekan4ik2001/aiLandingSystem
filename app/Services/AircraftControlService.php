<?php

namespace App\Services;

use App\Models\Airplane;

class AircraftControlService
{
    public function __construct(
        private readonly AircraftCommandJournal $commandJournal,
    ) {}

    private const MIN_SPEED_KMH = 50.0;

    private const MAX_SPEED_KMH = 900.0;

    private const MIN_ALTITUDE_FEET = 79.0;

    private const MAX_ALTITUDE_FEET = 40000.0;

    /**
     * Increase airplane speed.
     */
    public function increaseSpeed(int $airplaneId, float $deltaKmh = 25.0): array
    {
        $delta = abs($deltaKmh);
        $result = $this->adjustSpeed($airplaneId, $delta);
        $this->commandJournal->record('increase_speed', $airplaneId, $delta, 'км/ч', $result);

        return $result;
    }

    /**
     * Decrease airplane speed.
     */
    public function decreaseSpeed(int $airplaneId, float $deltaKmh = 25.0): array
    {
        $delta = abs($deltaKmh);
        $result = $this->adjustSpeed($airplaneId, -$delta);
        $this->commandJournal->record('decrease_speed', $airplaneId, $delta, 'км/ч', $result);

        return $result;
    }

    /**
     * Increase airplane altitude.
     */
    public function increaseAltitude(int $airplaneId, float $deltaFeet = 1000.0): array
    {
        $delta = abs($deltaFeet);
        $result = $this->adjustAltitude($airplaneId, $delta);
        $this->commandJournal->record('increase_altitude', $airplaneId, $delta, 'ft', $result);

        return $result;
    }

    /**
     * Decrease airplane altitude.
     */
    public function decreaseAltitude(int $airplaneId, float $deltaFeet = 1000.0): array
    {
        $delta = abs($deltaFeet);
        $result = $this->adjustAltitude($airplaneId, -$delta);
        $this->commandJournal->record('decrease_altitude', $airplaneId, $delta, 'ft', $result);

        return $result;
    }

    /**
     * @return array{status: string, airplane_id: int, speed?: float, altitude_feet?: float}
     */
    private function adjustSpeed(int $airplaneId, float $deltaKmh): array
    {
        $airplane = $this->findActiveAirplane($airplaneId);
        if ($airplane === null) {
            return ['status' => 'error', 'airplane_id' => $airplaneId, 'message' => 'Airplane not found or inactive'];
        }

        $newSpeed = max(self::MIN_SPEED_KMH, min(self::MAX_SPEED_KMH, (float) $airplane->speed + $deltaKmh));
        $airplane->speed = round($newSpeed, 2);
        $airplane->save();

        return [
            'status' => 'ok',
            'airplane_id' => $airplaneId,
            'speed' => (float) $airplane->speed,
        ];
    }

    /**
     * @return array{status: string, airplane_id: int, speed?: float, altitude_feet?: float, message?: string}
     */
    private function adjustAltitude(int $airplaneId, float $deltaFeet): array
    {
        $airplane = $this->findActiveAirplane($airplaneId);
        if ($airplane === null) {
            return ['status' => 'error', 'airplane_id' => $airplaneId, 'message' => 'Airplane not found or inactive'];
        }

        $newAltitude = max(
            self::MIN_ALTITUDE_FEET,
            min(self::MAX_ALTITUDE_FEET, (float) $airplane->altitude_feet + $deltaFeet)
        );
        $airplane->altitude_feet = round($newAltitude, 2);
        $airplane->save();

        return [
            'status' => 'ok',
            'airplane_id' => $airplaneId,
            'altitude_feet' => (float) $airplane->altitude_feet,
        ];
    }

    private function findActiveAirplane(int $airplaneId): ?Airplane
    {
        return Airplane::query()
            ->where('id', $airplaneId)
            ->where('is_active', true)
            ->first();
    }
}
