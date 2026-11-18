<?php

namespace App\Services;

class AtcTextCommandExecutor
{
    public function __construct(
        private readonly AircraftControlService $aircraftControlService,
    ) {}

    /**
     * @param  list<array{airplane_id: int, command: string, value: float}>  $commands
     */
    public function execute(array $commands): void
    {
        foreach ($commands as $command) {
            $airplaneId = (int) $command['airplane_id'];
            $value = (float) $command['value'];

            match ($command['command']) {
                'increase_speed' => $this->aircraftControlService->increaseSpeed($airplaneId, $value),
                'decrease_speed' => $this->aircraftControlService->decreaseSpeed($airplaneId, $value),
                'increase_altitude' => $this->aircraftControlService->increaseAltitude($airplaneId, $value),
                'decrease_altitude' => $this->aircraftControlService->decreaseAltitude($airplaneId, $value),
                default => null,
            };
        }
    }
}
