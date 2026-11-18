<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.head-meta')
    <title>Точки - AI Landing System</title>
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ai-landing.css') }}">
</head>
<body data-page="points" class="has-right-menu">
@include('partials.header')
<main class="app-main">
    @include('partials.menu')
    <section class="app-map-wrap">
        <div id="map" class="map-root"></div>
    </section>
    <aside class="app-right-menu bg-light border-start p-3">
        <h2 class="h5">Точки</h2>
        <form id="point-create-form" class="vstack gap-2" method="POST" action="{{ route('points.store') }}">
            @csrf
            <input type="text" name="name" class="form-control" placeholder="Название точки" required>
            <input type="number" step="0.00000001" name="latitude" class="form-control" placeholder="Широта" required>
            <input type="number" step="0.00000001" name="longitude" class="form-control" placeholder="Долгота" required>
            <input type="number" step="0.01" name="altitude_feet" class="form-control" placeholder="Высота (feet)" required>
            <select name="type" class="form-select" required>
                <option value="input">input</option>
                <option value="intermediate">intermediate</option>
                <option value="final">final</option>
            </select>
            <button class="btn btn-success" type="submit">Добавить</button>
        </form>
        <form id="point-edit-form" class="vstack gap-2 d-none mt-3" method="POST" action="{{ route('points.update', ['point' => 0]) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="id">
            <input type="text" name="name" class="form-control" placeholder="Название точки" required>
            <input type="number" step="0.00000001" name="latitude" class="form-control" placeholder="Широта" required>
            <input type="number" step="0.00000001" name="longitude" class="form-control" placeholder="Долгота" required>
            <input type="number" step="0.01" name="altitude_feet" class="form-control" placeholder="Высота (feet)" required>
            <select name="type" class="form-select" required>
                <option value="input">input</option>
                <option value="intermediate">intermediate</option>
                <option value="final">final</option>
            </select>
            <button class="btn btn-primary" type="submit">Сохранить</button>
        </form>
        <form id="point-delete-form" class="mt-2 d-none" method="POST" action="{{ route('points.destroy', ['point' => 0]) }}">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger w-100" type="submit">Удалить</button>
        </form>
    </aside>
</main>
@include('partials.footer')
<script id="points-data" type="application/json">@json($points)</script>
<script src="https://api-maps.yandex.ru/2.1/?apikey=140cdb9d-acff-4e69-a03e-e65c2d65a01e&lang=ru_RU"></script>
<script src="{{ asset('js/landing.js') }}"></script>
</body>
</html>
