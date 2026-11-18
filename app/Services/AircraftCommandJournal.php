<?php

namespace App\Services;

class AircraftCommandJournal
{
    /** @var list<array{action: string, airplane_id: int, delta: float, unit: string, status: string, label: string, speed?: float, altitude_feet?: float, message?: string}> */
    private array $entries = [];

    /**
     * Clear command log before a new agent run.
     */
    public function begin(): void
    {
        $this->entries = [];
    }

    /**
     * @param  array{status: string, airplane_id: int, speed?: float, altitude_feet?: float, message?: string}  $result
     */
    public function record(string $action, int $airplaneId, float $delta, string $unit, array $result): void
    {
        $entry = [
            'action' => $action,
            'airplane_id' => $airplaneId,
            'delta' => $delta,
            'unit' => $unit,
            'status' => $result['status'] ?? 'unknown',
            'label' => $this->buildLabel($action, $airplaneId, $delta, $unit, $result),
        ];

        if (isset($result['speed'])) {
            $entry['speed'] = (float) $result['speed'];
        }

        if (isset($result['altitude_feet'])) {
            $entry['altitude_feet'] = (float) $result['altitude_feet'];
        }

        if (isset($result['message'])) {
            $entry['message'] = (string) $result['message'];
        }

        $this->entries[] = $entry;
    }

    /**
     * @return list<array{action: string, airplane_id: int, delta: float, unit: string, status: string, label: string, speed?: float, altitude_feet?: float, message?: string}>
     */
    public function pull(): array
    {
        $entries = $this->entries;
        $this->entries = [];

        return $entries;
    }

    /**
     * @param  array{status: string, airplane_id: int, speed?: float, altitude_feet?: float, message?: string}  $result
     */
    private function buildLabel(string $action, int $airplaneId, float $delta, string $unit, array $result): string
    {
        $actionLabels = [
            'increase_speed' => 'увеличить скорость',
            'decrease_speed' => 'уменьшить скорость',
            'increase_altitude' => 'набор высоты',
            'decrease_altitude' => 'снижение высоты',
        ];

        $verb = $actionLabels[$action] ?? $action;
        $base = sprintf('Самолёт #%d: %s на %s %s', $airplaneId, $verb, rtrim(rtrim(number_format($delta, 2, '.', ''), '0'), '.'), $unit);

        if (($result['status'] ?? '') !== 'ok') {
            return $base.' — ошибка: '.($result['message'] ?? 'неизвестная ошибка');
        }

        if (isset($result['speed'])) {
            return $base.sprintf(' → скорость %s км/ч', rtrim(rtrim(number_format((float) $result['speed'], 2, '.', ''), '0'), '.'));
        }

        if (isset($result['altitude_feet'])) {
            return $base.sprintf(' → высота %s ft', rtrim(rtrim(number_format((float) $result['altitude_feet'], 2, '.', ''), '0'), '.'));
        }

        return $base;
    }
}
