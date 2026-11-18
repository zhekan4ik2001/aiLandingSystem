<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.head-meta')
    <title>Маршруты - AI Landing System</title>
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ai-landing.css') }}">
</head>
<body data-page="routes" class="has-right-menu">
@include('partials.header')
<main class="app-main">
    @include('partials.menu')
    <section class="app-map-wrap">
        <div id="map" class="map-root"></div>
    </section>
    <aside class="app-right-menu bg-light border-start p-3">
        <h2 class="h5">Маршруты</h2>
        <select id="route-selector" class="form-select mb-3">
            <option value="">Добавить маршрут</option>
            @foreach($routes as $route)
                <option value="{{ $route->id }}">{{ $route->name }}</option>
            @endforeach
        </select>

        <form id="route-create-form" class="vstack gap-2" method="POST" action="{{ route('routes.store') }}">
            @csrf
            <input type="text" name="name" class="form-control" placeholder="Название маршрута" required>
            <input type="text" name="point_ids" class="form-control" placeholder="ID точек через запятую" required>
            <button class="btn btn-success" type="submit">Добавить</button>
        </form>

        <form id="route-edit-form" class="vstack gap-2 d-none" method="POST" action="{{ route('routes.update', ['route' => 0]) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="id">
            <input type="text" name="name" class="form-control" placeholder="Название маршрута" required>
            <input type="text" name="point_ids" class="form-control" placeholder="ID точек через запятую" required>
            <button class="btn btn-primary" type="submit">Сохранить</button>
        </form>
        <form id="route-delete-form" class="d-none mt-2" method="POST" action="{{ route('routes.destroy', ['route' => 0]) }}">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger w-100" type="submit">Удалить</button>
        </form>
    </aside>
</main>
@include('partials.footer')
<script id="routes-data" type="application/json">@json($routes)</script>
<script id="points-data" type="application/json">@json($points)</script>
<script src="https://api-maps.yandex.ru/2.1/?apikey=140cdb9d-acff-4e69-a03e-e65c2d65a01e&lang=ru_RU"></script>
<script src="{{ asset('js/landing.js') }}"></script>
</body>
</html>
