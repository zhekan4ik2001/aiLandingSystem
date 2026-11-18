<?php

namespace App\Services;

use App\Models\Setting;

class SimulationTimeService
{
    public function accelerationOfTime(): float
    {
        $value = Setting::query()->where('name', 'accelerationOfTime')->value('value');

        return max(0.0, (float) ($value ?? 0));
    }

    public function isRunning(): bool
    {
        return $this->accelerationOfTime() > 0;
    }
}
