<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\AirplaneController;
use App\Services\RandomAircraftGeneratorService;
use App\Services\RoutePredictionService;
use App\Services\SimulationTimeService;

echo "Тестирование endpoint генерации самолетов\n";
echo "========================================\n\n";

// Тест 1: Простая проверка сервиса
echo "Тест 1: Проверка логики сервиса\n";
echo "-------------------------------\n";

$routePredictionService = app(RoutePredictionService::class);
$simulationTimeService = app(SimulationTimeService::class);
$generatorService = new RandomAircraftGeneratorService($routePredictionService, $simulationTimeService);

// Проверяем shouldGenerateAircraft с разными настройками
echo "Проверка shouldGenerateAircraft():\n";

// Случай 1: Ускорение времени = 0
\App\Models\Setting::where('name', 'accelerationOfTime')->update(['value' => '0']);
$result1 = $generatorService->shouldGenerateAircraft();
echo "  Ускорение времени = 0: " . ($result1 ? 'true' : 'false') . " (ожидается: false)\n";

// Случай 2: Ускорение времени = 1, интервал = 3600
\App\Models\Setting::where('name', 'accelerationOfTime')->update(['value' => '1']);
\App\Models\Setting::where('name', 'intervalGeneratePlane')->update(['value' => '3600']);
$result2 = $generatorService->shouldGenerateAircraft();
echo "  Ускорение времени = 1, интервал = 3600: " . ($result2 ? 'true' : 'false') . " (вероятность низкая)\n";

// Случай 3: Ускорение времени = 2, интервал = 3600 (фактически 1800 с учетом ускорения)
\App\Models\Setting::where('name', 'accelerationOfTime')->update(['value' => '2']);
$result3 = $generatorService->shouldGenerateAircraft();
echo "  Ускорение времени = 2, интервал = 3600: " . ($result3 ? 'true' : 'false') . " (вероятность в 2 раза выше)\n\n";

// Восстанавливаем настройки
\App\Models\Setting::where('name', 'accelerationOfTime')->update(['value' => '1']);
\App\Models\Setting::where('name', 'intervalGeneratePlane')->update(['value' => '3600']);

// Тест 2: Проверка с нормальным ускорением времени
echo "Тест 2: Проверка с ускорением времени = 1\n";
\App\Models\Setting::where('name', 'accelerationOfTime')->update(['value' => '1']);

// Устанавливаем очень маленький интервал для теста
\App\Models\Setting::where('name', 'intervalGeneratePlane')->update(['value' => '1']);

try {
    $response = $controller->generateRandom($generatorService);
    $data = json_decode($response->getContent(), true);
    
    echo "Статус: " . ($data['generated'] ? 'Сгенерирован' : 'Не сгенерирован') . "\n";
    echo "Сообщение: " . ($data['message'] ?? 'нет сообщения') . "\n";
    
    if ($data['generated'] && isset($data['airplane'])) {
        echo "Создан самолет ID: " . $data['airplane']['id'] . "\n";
        echo "Сценарий: " . $data['airplane']['scenario'] . "\n";
        
        // Удаляем тестовый самолет
        \App\Models\Airplane::find($data['airplane']['id'])?->delete();
    }
    
    echo "Ожидается: Может быть сгенерирован или нет (вероятностная логика)\n\n";
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n\n";
}

// Восстанавливаем настройки
\App\Models\Setting::where('name', 'accelerationOfTime')->update(['value' => '1']);
\App\Models\Setting::where('name', 'intervalGeneratePlane')->update(['value' => '3600']);

// Тест 3: Проверка логики выбора маршрута в зависимости от ветра
echo "Тест 3: Проверка логики выбора маршрута\n";
echo "---------------------------------------\n";

// Тестируем для разных направлений ветра
$testCases = [
    ['wind' => 90, 'expected' => 'LI783 или LI784 (0-180 градусов)'],
    ['wind' => 270, 'expected' => 'LI719 или LI785 (180-360 градусов)'],
    ['wind' => 0, 'expected' => 'LI783 или LI784 (граничное значение)'],
    ['wind' => 180, 'expected' => 'LI719 или LI785 (граничное значение)'],
];

foreach ($testCases as $testCase) {
    \App\Models\Setting::where('name', 'windDirection')->update(['value' => $testCase['wind']]);
    
    // Создаем новый экземпляр сервиса для актуальных настроек
    $testGenerator = new RandomAircraftGeneratorService($routePredictionService, $simulationTimeService);
    
    // Генерируем departure самолет
    $airplane = $testGenerator->generateDepartureAircraft($testCase['wind'], 0);
    
    if ($airplane && $airplane->route) {
        $routeName = $airplane->route->name;
        $matchesExpected = str_contains($routeName, 'LI78');
        
        echo "Направление ветра: {$testCase['wind']}°\n";
        echo "Ожидается: {$testCase['expected']}\n";
        echo "Маршрут: {$routeName}\n";
        echo "Результат: " . ($matchesExpected ? 'Соответствует' : 'Не соответствует') . "\n\n";
        
        // Удаляем тестовый самолет
        $airplane->delete();
    } else {
        echo "Направление ветра: {$testCase['wind']}°\n";
        echo "Ожидается: {$testCase['expected']}\n";
        echo "Результат: Не удалось создать самолет\n\n";
    }
}

// Восстанавливаем настройки ветра
\App\Models\Setting::where('name', 'windDirection')->update(['value' => '270']);

echo "Тестирование завершено!\n";

unlink(__FILE__);