<aside class="app-left-menu bg-light border-end p-3">
    <nav class="nav flex-column gap-2">
        <a class="btn btn-outline-secondary text-start {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Главная страница</a>
        <a class="btn btn-outline-secondary text-start {{ request()->routeIs('points.*') ? 'active' : '' }}" href="{{ route('points.index') }}">Работа с точками</a>
        <a class="btn btn-outline-secondary text-start {{ request()->routeIs('routes.*') ? 'active' : '' }}" href="{{ route('routes.index') }}">Работа с маршрутами</a>
        <a class="btn btn-outline-secondary text-start {{ request()->routeIs('airplanes.*') ? 'active' : '' }}" href="{{ route('airplanes.index') }}">Работа с самолетами</a>
    </nav>
</aside>
