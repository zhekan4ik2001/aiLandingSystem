<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.head-meta')
    <title>Самолеты - AI Landing System</title>
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ai-landing.css') }}">
</head>
<body data-page="airplanes" class="has-right-menu">
@include('partials.header')
<main class="app-main">
    @include('partials.menu')
    <section class="app-map-wrap">
        <div class="settings-overlay card p-2">
            <div class="settings-row">
                <div class="settings-item settings-item-narrow">
                    <label class="form-label mb-1">Направление ветра</label>
                    <input id="setting-windDirection" type="number" min="0" max="360" step="10" class="form-control form-control-sm" value="{{ $settings['windDirection']->value ?? '270' }}">
                </div>
                <div class="settings-item settings-item-narrow">
                    <label class="form-label mb-1">Ускорение времени</label>
                    <input id="setting-accelerationOfTime" type="number" min="0" max="50" step="0.5" class="form-control form-control-sm" value="{{ $settings['accelerationOfTime']->value ?? '1' }}">
                </div>
                <div class="settings-item settings-item-narrow">
                    <label class="form-label mb-1">Интервал появления, сек</label>
                    <input id="setting-intervalGeneratePlane" type="number" min="60" max="86400" step="60" class="form-control form-control-sm" value="{{ $settings['intervalGeneratePlane']->value ?? '3600' }}">
                </div>
                <div class="settings-item settings-item-wide">
                    <label class="form-label mb-1">Назначение полос</label>
                    <select id="setting-purposeOfRunways" class="form-select form-select-sm">
                        <option value="0" {{ ($settings['purposeOfRunways']->value ?? '0') === '0' ? 'selected' : '' }}>10L/28R для посадки</option>
                        <option value="1" {{ ($settings['purposeOfRunways']->value ?? '0') === '1' ? 'selected' : '' }}>10R/28L для посадки</option>
                    </select>
                </div>
            </div>
        </div>
        <div id="map" class="map-root"></div>
    </section>
    <aside class="app-right-menu bg-light border-start p-3">
        <h2 class="h5">Самолеты</h2>
        <form id="airplane-create-form" class="vstack gap-2" method="POST" action="{{ route('airplanes.store') }}">
            @csrf
            <input type="number" step="0.00000001" name="latitude" class="form-control" placeholder="Широта" required readonly>
            <input type="number" step="0.00000001" name="longitude" class="form-control" placeholder="Долгота" required readonly>
            <input type="number" step="0.01" name="altitude_feet" class="form-control" placeholder="Высота (feet)" required>
            <input type="number" step="0.01" name="direction" class="form-control" placeholder="Направление (градусы)" required>
            <input type="number" step="0.01" name="speed" class="form-control" placeholder="Скорость (км/ч)" required>
            <select name="route_id" class="form-select">
                <option value="">Без маршрута</option>
                @foreach($routes as $route)
                    <option value="{{ $route->id }}">{{ $route->name }}</option>
                @endforeach
            </select>
            <input type="hidden" name="scenario" value="arrival">
            <button class="btn btn-success" type="submit">Добавить</button>
        </form>
        <form id="airplane-edit-form" class="vstack gap-2 d-none mt-3" method="POST" action="{{ route('airplanes.update', ['airplane' => 0]) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="id">
            <input type="number" step="0.00000001" name="latitude" class="form-control" placeholder="Широта" required readonly>
            <input type="number" step="0.00000001" name="longitude" class="form-control" placeholder="Долгота" required readonly>
            <input type="number" step="0.01" name="altitude_feet" class="form-control" placeholder="Высота (feet)" required>
            <input type="number" step="0.01" name="direction" class="form-control" placeholder="Направление (градусы)" required>
            <input type="number" step="0.01" name="speed" class="form-control" placeholder="Скорость (км/ч)" required>
            <select name="route_id" class="form-select">
                <option value="">Без маршрута</option>
                @foreach($routes as $route)
                    <option value="{{ $route->id }}">{{ $route->name }}</option>
                @endforeach
            </select>
            <input type="hidden" name="scenario" value="arrival">
            <button class="btn btn-primary" type="submit">Сохранить</button>
        </form>
        <form id="airplane-delete-form" class="d-none mt-2" method="POST" action="{{ route('airplanes.destroy', ['airplane' => 0]) }}">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger w-100" type="submit">Удалить</button>
        </form>
    </aside>
</main>
@include('partials.footer')
<script id="airplanes-data" type="application/json">@json($airplanes)</script>
<script src="https://api-maps.yandex.ru/2.1/?apikey=140cdb9d-acff-4e69-a03e-e65c2d65a01e&lang=ru_RU"></script>
<script src="{{ asset('js/landing.js') }}"></script>
</body>
</html>
