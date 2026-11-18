<?php

namespace App\Http\Controllers;

use App\Models\Airplane;
use App\Models\Route as FlightRoute;
use App\Models\Setting;
use App\Services\AircraftProximityService;
use App\Services\AirTrafficControlService;
use App\Services\GroqAvailabilityService;
use App\Services\FlightKinematicsService;
use App\Services\SimulationTimeService;
use App\Services\RoutePredictionService;
use App\Support\GeoCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AirplaneController extends Controller
{
    public function predictRoute(Request $request, RoutePredictionService $routePredictionService)
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $settings = Setting::query()
            ->whereIn('name', ['windDirection', 'purposeOfRunways'])
            ->pluck('value', 'name');

        $predictedRouteId = $routePredictionService->predictRouteId(
            (float) $data['latitude'],
            (float) $data['longitude'],
            (float) ($settings['windDirection'] ?? 0),
            (int) ($settings['purposeOfRunways'] ?? 0)
        );

        return response()->json([
            'route_id' => $predictedRouteId,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('airplanes', [
            'airplanes' => Airplane::with('route')->orderBy('id')->get(),
            'routes' => FlightRoute::orderBy('id')->get(),
            'settings' => Setting::whereIn('name', ['windDirection', 'purposeOfRunways', 'accelerationOfTime', 'intervalGeneratePlane'])->get()->keyBy('name'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort(404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, RoutePredictionService $routePredictionService)
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'altitude_feet' => 'required|numeric|min:0',
            'direction' => 'required|numeric|between:0,360',
            'speed' => 'required|numeric|min:0',
            'route_id' => 'nullable|exists:routes,id',
            'current_point_index' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'scenario' => 'nullable|in:arrival,departure',
        ]);

        if (empty($data['route_id'])) {
            $settings = Setting::query()
                ->whereIn('name', ['windDirection', 'purposeOfRunways'])
                ->pluck('value', 'name');

            $predictedRouteId = $routePredictionService->predictRouteId(
                (float) $data['latitude'],
                (float) $data['longitude'],
                (float) ($settings['windDirection'] ?? 0),
                (int) ($settings['purposeOfRunways'] ?? 0)
            );

            if ($predictedRouteId !== null) {
                $data['route_id'] = $predictedRouteId;
            }
        }

        $airplane = Airplane::create($data + [
            'current_point_index' => $data['current_point_index'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $request->expectsJson()
            ? response()->json($airplane->load('route'))
            : redirect()->route('airplanes.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $airplane = Airplane::with('route')->findOrFail($id);
        $routePoints = $airplane->route ? $airplane->route->orderedPoints()->values() : [];

        return response()->json([
            'airplane' => $airplane,
            'route_points' => $routePoints,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $airplane = Airplane::findOrFail($id);
        $data = $request->validate([
            'altitude_feet' => 'required|numeric|min:0',
            'direction' => 'required|numeric|between:0,360',
            'speed' => 'required|numeric|min:0',
            'route_id' => 'nullable|exists:routes,id',
            'current_point_index' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'scenario' => 'nullable|in:arrival,departure',
        ]);

        $airplane->update($data);

        return $request->expectsJson()
            ? response()->json($airplane->fresh()->load('route'))
            : redirect()->route('airplanes.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Airplane::findOrFail($id)->delete();

        return request()->expectsJson()
            ? response()->json(['status' => 'ok'])
            : redirect()->route('airplanes.index');
    }

    /**
     * Move airplane by 0.5 sec simulation step.
     */
    public function updatePosition(
        Request $request,
        string $id,
        FlightKinematicsService $flightKinematicsService
    ) {
        $airplane = Airplane::with('route')->findOrFail($id);

        if (! $airplane->is_active) {
            return response()->json($airplane);
        }

        $acceleration = (float) (Setting::where('name', 'accelerationOfTime')->value('value') ?? 1);
        $acceleration = max(0, min(10, $acceleration));

        if ($acceleration <= 0) {
            return response()->json($airplane->load('route'));
        }

        $flightKinematicsService->applyAltitudeAndSpeed($airplane, $acceleration);

        $distanceKm = ((float) $airplane->speed) * (0.5 / 3600) * $acceleration;
        $nextDirection = (float) $airplane->direction;

        if ($airplane->route_id && $airplane->route) {
            $routePoints = $airplane->route->orderedPoints()->values();
            $targetPoint = $routePoints->get($airplane->current_point_index);

            if ($targetPoint) {
                $nextDirection = GeoCalculator::bearing(
                    (float) $airplane->latitude,
                    (float) $airplane->longitude,
                    (float) $targetPoint->latitude,
                    (float) $targetPoint->longitude
                );
            }
        }

        $destination = GeoCalculator::destinationPoint(
            (float) $airplane->latitude,
            (float) $airplane->longitude,
            $nextDirection,
            $distanceKm
        );

        $airplane->latitude = $destination['latitude'];
        $airplane->longitude = $destination['longitude'];
        $airplane->direction = $nextDirection;

        if ($airplane->route_id && $airplane->route) {
            $routePoints = $airplane->route->orderedPoints()->values();
            $targetPoint = $routePoints->get($airplane->current_point_index);

            if ($targetPoint) {
                $distanceToTarget = GeoCalculator::distanceKm(
                    (float) $airplane->latitude,
                    (float) $airplane->longitude,
                    (float) $targetPoint->latitude,
                    (float) $targetPoint->longitude
                );

                if ($distanceToTarget <= 0.7) {
                    $airplane->current_point_index++;
                    $nextPoint = $routePoints->get($airplane->current_point_index);

                    if ($targetPoint->type === 'final' || ! $nextPoint) {
                        $airplane->is_active = false;
                    } else {
                        $airplane->direction = GeoCalculator::bearing(
                            (float) $airplane->latitude,
                            (float) $airplane->longitude,
                            (float) $nextPoint->latitude,
                            (float) $nextPoint->longitude
                        );
                    }
                }
            }
        }

        $distanceFromPulkovo = GeoCalculator::distanceKm(
            (float) $airplane->latitude,
            (float) $airplane->longitude,
            59.800278,
            30.2625
        );

        if ($distanceFromPulkovo > 150) {
            $airplane->is_active = false;
        }

        $airplane->save();

        return response()->json($airplane->fresh()->load('route'));
    }

    /**
     * Run AI traffic control to separate aircraft before collisions.
     */
    public function aiAvoidCollision(AirTrafficControlService $airTrafficControlService)
    {
        try {
            return response()->json($airTrafficControlService->avoidCollisions());
        } catch (\Throwable $exception) {
            Log::error('AI avoid collision endpoint failed', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'ran' => false,
                'reason' => 'server_error',
                'groq_available' => false,
                'conflicts_before' => 0,
                'conflicts_after' => 0,
                'commands' => [],
                'commands_count' => 0,
            ]);
        }
    }

    /**
     * Check whether Groq API is available for AI traffic control.
     */
    public function groqStatus(GroqAvailabilityService $groqAvailabilityService)
    {
        return response()->json($groqAvailabilityService->getStatusReport());
    }

    /**
     * Check proximity between active airplanes and freeze time on conflict.
     */
    public function checkProximity(
        AircraftProximityService $aircraftProximityService,
        SimulationTimeService $simulationTimeService,
    ) {
        $acceleration = $simulationTimeService->accelerationOfTime();

        if (! $simulationTimeService->isRunning()) {
            return response()->json([
                'conflicts' => [],
                'time_frozen' => true,
                'simulation_paused' => true,
                'acceleration_of_time' => $acceleration,
            ]);
        }

        $conflicts = $aircraftProximityService->detectConflicts();
        $aircraftProximityService->freezeTimeIfConflicts($conflicts);

        return response()->json([
            'conflicts' => $conflicts,
            'time_frozen' => $conflicts !== [],
            'simulation_paused' => false,
            'acceleration_of_time' => $acceleration,
        ]);
    }

    /**
     * Update setting value by keyup events from UI.
     */
    public function updateSetting(Request $request, string $name)
    {
        if ($name === 'windDirection') {
            $request->validate(['value' => 'required|numeric|between:0,360']);
        } elseif ($name === 'accelerationOfTime') {
            $request->validate(['value' => 'required|numeric|between:0,10']);
        } elseif ($name === 'intervalGeneratePlane') {
            $request->validate(['value' => 'required|numeric|between:60,86400']);
        } else {
            $request->validate(['value' => 'required|string|max:255']);
        }

        $setting = Setting::where('name', $name)->firstOrFail();
        $setting->value = $request->string('value')->toString();
        $setting->save();

        return response()->json($setting);
    }

    /**
     * Calculate azimuth to Pulkovo for click position.
     */
    public function bearingToPulkovo(Request $request)
    {
        $data = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $bearing = GeoCalculator::bearing(
            (float) $data['latitude'],
            (float) $data['longitude'],
            59.800278,
            30.2625
        );

        return response()->json(['direction' => $bearing]);
    }

    /**
     * Генерирует случайный самолет, если пришло время
     */
    public function generateRandom(
        \App\Services\RandomAircraftGeneratorService $generatorService
    ) {
        try {
            if (!$generatorService->shouldGenerateAircraft()) {
                return response()->json([
                    'generated' => false,
                    'message' => 'Не время для генерации',
                ]);
            }

            $airplane = $generatorService->generateRandomAircraft();
            
            if (!$airplane) {
                return response()->json([
                    'generated' => false,
                    'message' => 'Не удалось сгенерировать самолет',
                ]);
            }

            return response()->json([
                'generated' => true,
                'airplane' => $airplane->load('route'),
                'message' => 'Самолет успешно сгенерирован',
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка генерации случайного самолета: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            
            return response()->json([
                'generated' => false,
                'message' => 'Ошибка генерации: ' . $e->getMessage(),
            ], 500);
        }
    }
}
