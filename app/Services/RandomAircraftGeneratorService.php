<?php

namespace App\Services;

use App\Models\Airplane;
use App\Models\Point;
use App\Models\Route;
use App\Models\Setting;
use App\Support\GeoCalculator;
use Illuminate\Support\Facades\Log;

class RandomAircraftGeneratorService
{
    private RoutePredictionService $routePredictionService;
    private SimulationTimeService $simulationTimeService;

    public function __construct(
        RoutePredictionService $routePredictionService,
        SimulationTimeService $simulationTimeService
    ) {
        $this->routePredictionService = $routePredictionService;
        $this->simulationTimeService = $simulationTimeService;
    }

    /**
     * Генерирует случайный самолет в соответствии с настройками
     */
    public function generateRandomAircraft(): ?Airplane
    {
        try {
            // Получаем настройки
            $settings = Setting::whereIn('name', [
                'intervalGeneratePlane', 
                'windDirection', 
                'purposeOfRunways',
                'accelerationOfTime'
            ])->pluck('value', 'name');

            $windDirection = (float) ($settings['windDirection'] ?? 270);
            $purposeOfRunways = (int) ($settings['purposeOfRunways'] ?? 0);
            $accelerationOfTime = (float) ($settings['accelerationOfTime'] ?? 1);

            // Определяем сценарий: 50% на взлет, 50% на посадку
            $scenario = rand(0, 1) === 0 ? 'departure' : 'arrival';
            
            if ($scenario === 'departure') {
                return $this->generateDepartureAircraft($windDirection, $purposeOfRunways);
            } else {
                return $this->generateArrivalAircraft($windDirection, $purposeOfRunways);
            }
        } catch (\Exception $e) {
            Log::error('Ошибка генерации случайного самолета: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return null;
        }
    }

    /**
     * Генерирует самолет для взлета
     */
    public function generateDepartureAircraft(float $windDirection, int $purposeOfRunways): ?Airplane
    {
        // Определяем ВПП для взлета (противоположная той, что для посадки)
        $runwayPoints = Point::where('type', 'runway')->get();
        
        // Точки ВПП: 14 - ВПП 10R/28L, 15 - ВПП 10L/28R
        // SID точки: LI719 (16), LI785 (17), LI783 (18), LI784 (19)
        
        // Если для посадки выбрана 10L/28R (purposeOfRunways = 0), то взлет с 10R/28L
        // Если для посадки выбрана 10R/28L (purposeOfRunways = 1), то взлет с 10L/28R
        $departureRunwayId = $purposeOfRunways === 0 ? 14 : 15; // ID точки ВПП для взлета
        
        $departureRunway = Point::find($departureRunwayId);
        if (!$departureRunway) {
            return null;
        }

        // Выбираем SID точку в зависимости от направления ветра
        // Если направление ветра от 0 до 180, то через LI783 или LI784
        // Если направление ветра от 180 до 360, то через LI719 или LI785
        $sidPoint = null;
        if ($windDirection >= 0 && $windDirection < 180) {
            // Выбираем случайно LI783 (18) или LI784 (19)
            $sidPointId = rand(0, 1) === 0 ? 18 : 19; // LI783 или LI784
        } else {
            // Выбираем случайно LI719 (16) или LI785 (17)  
            $sidPointId = rand(0, 1) === 0 ? 16 : 17; // LI719 или LI785
        }
        
        $sidPoint = Point::find($sidPointId);
        if (!$sidPoint) {
            return null;
        }

        // Выбираем случайную выходную точку (тип output)
        $outputPoints = Point::where('type', 'output')->get();
        if ($outputPoints->isEmpty()) {
            return null;
        }
        
        $outputPoint = $outputPoints->random();
        
        // Создаем маршрут или используем существующий
        // Ищем маршрут, который начинается с нужной SID точки и заканчивается выходной точкой
        $route = $this->findDepartureRoute($sidPointId, $outputPoint->id);
        
        if (!$route) {
            return null;
        }

        // Генерируем случайные параметры для взлета
        $speed = rand(250, 300); // км/ч для взлета
        $altitude = rand(1000, 5000); // высота для взлета
        
        // Направление от ВПП к первой точке маршрута
        $direction = GeoCalculator::bearing(
            (float) $departureRunway->latitude,
            (float) $departureRunway->longitude,
            (float) $sidPoint->latitude,
            (float) $sidPoint->longitude
        );

        return Airplane::create([
            'latitude' => $departureRunway->latitude,
            'longitude' => $departureRunway->longitude,
            'altitude_feet' => $altitude,
            'direction' => $direction,
            'speed' => $speed,
            'route_id' => $route->id,
            'current_point_index' => 0,
            'is_active' => true,
            'scenario' => 'departure',
        ]);
    }

    /**
     * Генерирует самолет для посадки
     */
    public function generateArrivalAircraft(float $windDirection, int $purposeOfRunways): ?Airplane
    {
        // Генерируем позицию на расстоянии 100-150 км от аэродрома Пулково
        $pulkovoLat = 59.800278;
        $pulkovoLon = 30.2625;
        
        // Случайное расстояние от 100 до 150 км
        $distanceKm = rand(100, 150);
        // Случайное направление 0-360 градусов
        $bearing = rand(0, 360);
        
        // Вычисляем позицию
        $position = GeoCalculator::destinationPoint($pulkovoLat, $pulkovoLon, $bearing, $distanceKm);
        
        // Высота 30000 футов для захода на посадку
        $altitude = 30000;
        $speed = rand(400, 500); // км/ч для захода
        
        // Направление на аэродром Пулково
        $direction = GeoCalculator::bearing(
            $position['latitude'],
            $position['longitude'],
            $pulkovoLat,
            $pulkovoLon
        );

        // Используем RoutePredictionService для определения маршрута
        $routeId = $this->routePredictionService->predictRouteId(
            $position['latitude'],
            $position['longitude'],
            $windDirection,
            $purposeOfRunways
        );

        if (!$routeId) {
            return null;
        }

        return Airplane::create([
            'latitude' => $position['latitude'],
            'longitude' => $position['longitude'],
            'altitude_feet' => $altitude,
            'direction' => $direction,
            'speed' => $speed,
            'route_id' => $routeId,
            'current_point_index' => 0,
            'is_active' => true,
            'scenario' => 'arrival',
        ]);
    }

    /**
     * Находит маршрут для взлета по начальной и конечной точкам
     */
    private function findDepartureRoute(int $startPointId, int $endPointId): ?Route
    {
        // Ищем существующие маршруты SID
        $routes = Route::all();
        
        foreach ($routes as $route) {
            $pointIds = $route->point_ids;
            if (is_array($pointIds) && count($pointIds) >= 2) {
                // Проверяем, начинается ли маршрут с нужной SID точки
                // и содержит ли выходную точку
                if (in_array($startPointId, $pointIds) && in_array($endPointId, $pointIds)) {
                    // Убеждаемся, что SID точка идет раньше выходной
                    $startIndex = array_search($startPointId, $pointIds);
                    $endIndex = array_search($endPointId, $pointIds);
                    
                    if ($startIndex !== false && $endIndex !== false && $startIndex < $endIndex) {
                        return $route;
                    }
                }
            }
        }
        
        // Если не нашли подходящий маршрут, создаем простой
        return $this->createSimpleDepartureRoute($startPointId, $endPointId);
    }

    /**
     * Создает простой маршрут для взлета
     */
    private function createSimpleDepartureRoute(int $startPointId, int $endPointId): ?Route
    {
        $startPoint = Point::find($startPointId);
        $endPoint = Point::find($endPointId);
        
        if (!$startPoint || !$endPoint) {
            return null;
        }
        
        // Создаем имя маршрута
        $routeName = "SID " . $startPoint->name . " -> " . $endPoint->name;
        
        // Проверяем, существует ли уже такой маршрут
        $existingRoute = Route::where('name', $routeName)->first();
        if ($existingRoute) {
            return $existingRoute;
        }
        
        // Создаем новый маршрут
        return Route::create([
            'name' => $routeName,
            'point_ids' => [$startPointId, $endPointId],
        ]);
    }

    /**
     * Проверяет, нужно ли генерировать новый самолет
     * на основе интервала и ускорения времени
     */
    public function shouldGenerateAircraft(): bool
    {
        try {
            $settings = Setting::whereIn('name', [
                'intervalGeneratePlane',
                'accelerationOfTime'
            ])->pluck('value', 'name');
            
            $interval = (int) ($settings['intervalGeneratePlane'] ?? 3600);
            $acceleration = (float) ($settings['accelerationOfTime'] ?? 1);
            
            if ($interval <= 0 || $acceleration <= 0) {
                return false;
            }
            
            // Корректируем интервал с учетом ускорения времени
            $adjustedInterval = $interval / $acceleration;
            
            // Упрощенная логика: с вероятностью 1/(adjustedInterval/0.5) на каждом шаге 0.5 сек
            // Поскольку шаг симуляции 0.5 секунды
            $probability = 0.5 / $adjustedInterval;
            
            return mt_rand() / mt_getrandmax() < $probability;
            
        } catch (\Exception $e) {
            Log::error('Ошибка проверки необходимости генерации самолета: ' . $e->getMessage());
            return false;
        }
    }
}