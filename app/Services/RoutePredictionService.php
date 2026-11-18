<?php

namespace App\Services;

use App\Models\Route as FlightRoute;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class RoutePredictionService
{
    public function predictRouteId(
        float $latitude,
        float $longitude,
        float $windDirection,
        int $purposeOfRunways
    ): ?int {
        $modelPath = storage_path('ml-models/final_model.keras');
        $scriptPath = storage_path('ml-models/predict_route.py');

        if (! is_file($modelPath) || ! is_file($scriptPath)) {
            return null;
        }

        $pythonBinary = (string) config('services.ml.python_binary', 'python');

        $result = Process::timeout((int) config('services.ml.timeout_seconds', 15))
            ->run([
                $pythonBinary,
                $scriptPath,
                $modelPath,
                (string) $latitude,
                (string) $longitude,
                (string) $windDirection,
                (string) $purposeOfRunways,
            ]);

        if (! $result->successful()) {
            Log::warning('ML route prediction process failed.', [
                'exit_code' => $result->exitCode(),
                'error_output' => $result->errorOutput(),
            ]);

            return null;
        }

        $predictedRaw = trim($result->output());
        if ($predictedRaw === '') {
            return null;
        }

        $decoded = json_decode($predictedRaw, true);
        $predictedValue = null;

        if (is_array($decoded)) {
            if (! empty($decoded['error'])) {
                Log::warning('ML route prediction returned error JSON.', [
                    'error' => $decoded['error'],
                ]);

                return null;
            }
            $predictedValue = $decoded['route_id'] ?? null;
        } else {
            // Backward compatible: allow plain numeric stdout
            $predictedValue = $predictedRaw;
        }

        if ($predictedValue === null || $predictedValue === '') {
            return null;
        }

        $predictedRouteId = (int) round((float) $predictedValue);
        if ($predictedRouteId <= 0) {
            return null;
        }

        return FlightRoute::query()->whereKey($predictedRouteId)->exists()
            ? $predictedRouteId
            : null;
    }
}
