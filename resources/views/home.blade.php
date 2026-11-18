<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.head-meta')
    <title>AI Landing System</title>
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ai-landing.css') }}">
</head>
<body data-page="home">
@include('partials.header')
<main class="app-main">
    @include('partials.menu')
    <section class="app-map-wrap">
        <div id="map" class="map-root"></div>
    </section>
</main>
@include('partials.footer')
<script src="https://api-maps.yandex.ru/2.1/?apikey=140cdb9d-acff-4e69-a03e-e65c2d65a01e&lang=ru_RU"></script>
<script src="{{ asset('js/landing.js') }}"></script>
</body>
</html>
