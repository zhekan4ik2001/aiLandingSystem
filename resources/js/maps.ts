const PULKOVO: [number, number] = [59.800278, 30.2625];
const MAP_BOUNDS: [[number, number], [number, number]] = [[58.9, 28.7], [60.7, 31.8]];

type AnyObject = Record<string, any>;

/**
 * Read JSON from script tag.
 */
function readJson<T>(id: string, fallback: T): T {
    const node = document.getElementById(id);
    if (!node?.textContent) {
        return fallback;
    }

    return JSON.parse(node.textContent) as T;
}

/**
 * Perform fetch request with csrf token.
 */
function resolveUrl(url: string): string {
    if (!url.startsWith('/') || url.startsWith('//')) {
        return url;
    }

    const baseNode = document.querySelector('meta[name="app-base-url"]') as HTMLMetaElement | null;
    const base = baseNode?.content?.replace(/\/$/, '') ?? '';

    return base + url;
}

async function request(url: string, method: string, body?: AnyObject): Promise<any> {
    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
    const response = await fetch(resolveUrl(url), {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json',
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (!response.ok) {
        throw new Error(`${method} ${url} failed`);
    }

    return response.json();
}

/**
 * Create common map centered at Pulkovo.
 */
function createMap(): any {
    const map = new (window as any).ymaps.Map('map', {
        center: PULKOVO,
        zoom: 9,
        controls: ['zoomControl'],
    });

    map.setBounds(MAP_BOUNDS, { checkZoomRange: true, zoomMargin: 10 });
    return map;
}

/**
 * Render point placemark with color by type.
 */
function createPointPlacemark(point: AnyObject): any {
    const color = point.type === 'input' ? 'green' : point.type === 'intermediate' ? 'blue' : 'red';
    const iconSvg = `<svg width="30" height="30" viewBox="0 0 30 30" xmlns="http://www.w3.org/2000/svg"><path fill="${color}" d="M15 0 L26 4 L30 15 L26 26 L15 30 L4 26 L0 15 L4 4 Z"></path></svg>`;

    return new (window as any).ymaps.Placemark(
        [Number(point.latitude), Number(point.longitude)],
        { pointId: point.id, hintContent: point.name },
        {
            iconLayout: 'default#imageWithContent',
            iconImageHref: `data:image/svg+xml;utf8,${encodeURIComponent(iconSvg)}`,
            iconImageSize: [30, 30],
            iconImageOffset: [-15, -15],
        }
    );
}

/**
 * Initialize points page.
 */
function initPointsPage(): void {
    const map = createMap();
    const points = readJson<AnyObject[]>('points-data', []);
    const createForm = document.getElementById('point-create-form') as HTMLFormElement;
    const editForm = document.getElementById('point-edit-form') as HTMLFormElement;
    const deleteBtn = document.getElementById('point-delete-btn') as HTMLButtonElement;
    let selectedId = 0;

    const redraw = (items: AnyObject[]) => {
        map.geoObjects.removeAll();
        items.forEach((item) => map.geoObjects.add(createPointPlacemark(item)));
    };
    redraw(points);

    map.events.add('click', async (event: any) => {
        const target = event.get('target');
        if (target?.properties?.get('pointId')) {
            selectedId = target.properties.get('pointId');
            const point = await request(`/points/${selectedId}`, 'GET');
            (editForm.elements.namedItem('id') as HTMLInputElement).value = point.id;
            (editForm.elements.namedItem('name') as HTMLInputElement).value = point.name;
            (editForm.elements.namedItem('latitude') as HTMLInputElement).value = point.latitude;
            (editForm.elements.namedItem('longitude') as HTMLInputElement).value = point.longitude;
            (editForm.elements.namedItem('altitude_feet') as HTMLInputElement).value = point.altitude_feet;
            (editForm.elements.namedItem('type') as HTMLSelectElement).value = point.type;
            editForm.classList.remove('d-none');
            createForm.classList.add('d-none');
            return;
        }

        const coords = event.get('coords');
        (createForm.elements.namedItem('latitude') as HTMLInputElement).value = coords[0].toFixed(8);
        (createForm.elements.namedItem('longitude') as HTMLInputElement).value = coords[1].toFixed(8);
        createForm.classList.remove('d-none');
        editForm.classList.add('d-none');
    });

    createForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = Object.fromEntries(new FormData(createForm).entries());
        const point = await request('/points', 'POST', formData);
        points.push(point);
        redraw(points);
        createForm.reset();
    });

    editForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = Object.fromEntries(new FormData(editForm).entries());
        await request(`/points/${selectedId}`, 'PUT', formData);
        location.reload();
    });

    deleteBtn.addEventListener('click', async () => {
        await request(`/points/${selectedId}`, 'DELETE');
        location.reload();
    });
}

/**
 * Initialize routes page with route visualization.
 */
function initRoutesPage(): void {
    const map = createMap();
    const routes = readJson<AnyObject[]>('routes-data', []);
    const points = readJson<AnyObject[]>('points-data', []);
    const selector = document.getElementById('route-selector') as HTMLSelectElement;
    const createForm = document.getElementById('route-create-form') as HTMLFormElement;
    const editForm = document.getElementById('route-edit-form') as HTMLFormElement;
    const deleteBtn = document.getElementById('route-delete-btn') as HTMLButtonElement;
    let selectedId = 0;

    const pointById = new Map(points.map((point) => [point.id, point]));

    const drawAllPoints = () => {
        map.geoObjects.removeAll();
        points.forEach((point) => map.geoObjects.add(createPointPlacemark(point)));
    };

    const drawRoute = (route: AnyObject) => {
        map.geoObjects.removeAll();
        const routePoints = (route.point_ids || []).map((id: number) => pointById.get(id)).filter(Boolean);
        routePoints.forEach((point: AnyObject) => map.geoObjects.add(createPointPlacemark(point)));
        map.geoObjects.add(new (window as any).ymaps.Polyline(
            routePoints.map((point: AnyObject) => [Number(point.latitude), Number(point.longitude)]),
            {},
            { strokeColor: '#000000', strokeWidth: 3 }
        ));
    };

    drawAllPoints();

    map.events.add('click', (event: any) => {
        if (selector.value) {
            return;
        }
        const target = event.get('target');
        const pointId = target?.properties?.get('pointId');
        if (!pointId) {
            return;
        }
        const input = createForm.elements.namedItem('point_ids') as HTMLInputElement;
        const ids = input.value.split(',').map((value) => value.trim()).filter(Boolean);
        ids.push(String(pointId));
        input.value = Array.from(new Set(ids)).join(',');
    });

    selector.addEventListener('change', async () => {
        const routeId = selector.value;
        if (!routeId) {
            createForm.classList.remove('d-none');
            editForm.classList.add('d-none');
            drawAllPoints();
            return;
        }

        selectedId = Number(routeId);
        const payload = await request(`/routes/${routeId}`, 'GET');
        const route = payload.route;
        (editForm.elements.namedItem('id') as HTMLInputElement).value = route.id;
        (editForm.elements.namedItem('name') as HTMLInputElement).value = route.name;
        (editForm.elements.namedItem('point_ids') as HTMLInputElement).value = (route.point_ids || []).join(',');
        editForm.classList.remove('d-none');
        createForm.classList.add('d-none');
        drawRoute(route);
    });

    createForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const raw = Object.fromEntries(new FormData(createForm).entries()) as AnyObject;
        raw.point_ids = String(raw.point_ids).split(',').map((value) => Number(value.trim())).filter(Boolean);
        await request('/routes', 'POST', raw);
        location.reload();
    });

    editForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const raw = Object.fromEntries(new FormData(editForm).entries()) as AnyObject;
        raw.point_ids = String(raw.point_ids).split(',').map((value) => Number(value.trim())).filter(Boolean);
        await request(`/routes/${selectedId}`, 'PUT', raw);
        location.reload();
    });

    deleteBtn.addEventListener('click', async () => {
        await request(`/routes/${selectedId}`, 'DELETE');
        location.reload();
    });
}

/**
 * Create airplane placemark with rotation by direction.
 */
function createAirplanePlacemark(airplane: AnyObject): any {
    const iconSvg = `<svg width="30" height="30" viewBox="0 0 30 30" xmlns="http://www.w3.org/2000/svg"><path d="M15 0 L27 25 L15 20 L3 25 Z" fill="black"></path></svg>`;
    return new (window as any).ymaps.Placemark(
        [Number(airplane.latitude), Number(airplane.longitude)],
        { airplaneId: airplane.id, hintContent: `Aircraft ${airplane.id}` },
        {
            iconLayout: 'default#imageWithContent',
            iconImageHref: `data:image/svg+xml;utf8,${encodeURIComponent(iconSvg)}`,
            iconImageSize: [30, 30],
            iconImageOffset: [-15, -15],
            iconContentLayout: (window as any).ymaps.templateLayoutFactory.createClass(
                `<div style="transform: rotate(${airplane.direction}deg); width:30px; height:30px;"></div>`
            ),
        }
    );
}

/**
 * Initialize airplanes page and movement loop.
 */
function initAirplanesPage(): void {
    const map = createMap();
    const airplanes = readJson<AnyObject[]>('airplanes-data', []);
    const createForm = document.getElementById('airplane-create-form') as HTMLFormElement;
    const editForm = document.getElementById('airplane-edit-form') as HTMLFormElement;
    const deleteBtn = document.getElementById('airplane-delete-btn') as HTMLButtonElement;
    const windField = document.getElementById('setting-windDirection') as HTMLInputElement;
    const runwaysField = document.getElementById('setting-purposeOfRunways') as HTMLInputElement;
    const accelerationField = document.getElementById('setting-accelerationOfTime') as HTMLInputElement;
    let selectedId = 0;
    let routeLine: any = null;
    let proximityConflicts: AnyObject[] = [];

    const drawProximityConflicts = () => {
        proximityConflicts.forEach((conflict) => {
            map.geoObjects.add(new (window as any).ymaps.Circle(
                [[Number(conflict.latitude), Number(conflict.longitude)], Number(conflict.radius_km) * 1000],
                {},
                {
                    fillColor: 'rgba(255, 0, 0, 0.25)',
                    strokeColor: '#ff0000',
                    strokeWidth: 2,
                    strokeOpacity: 0.9,
                }
            ));
        });
    };

    const redraw = (items: AnyObject[]) => {
        map.geoObjects.removeAll();
        drawProximityConflicts();
        items.filter((item) => item.is_active).forEach((item) => map.geoObjects.add(createAirplanePlacemark(item)));
        if (routeLine) {
            map.geoObjects.add(routeLine);
        }
    };

    redraw(airplanes);

    map.events.add('click', async (event: any) => {
        const target = event.get('target');
        if (target?.properties?.get('airplaneId')) {
            selectedId = target.properties.get('airplaneId');
            const payload = await request(`/airplanes/${selectedId}`, 'GET');
            const airplane = payload.airplane;
            (editForm.elements.namedItem('id') as HTMLInputElement).value = airplane.id;
            (editForm.elements.namedItem('latitude') as HTMLInputElement).value = airplane.latitude;
            (editForm.elements.namedItem('longitude') as HTMLInputElement).value = airplane.longitude;
            (editForm.elements.namedItem('altitude_feet') as HTMLInputElement).value = airplane.altitude_feet;
            (editForm.elements.namedItem('direction') as HTMLInputElement).value = airplane.direction;
            (editForm.elements.namedItem('speed') as HTMLInputElement).value = airplane.speed;
            (editForm.elements.namedItem('route_id') as HTMLSelectElement).value = airplane.route_id ?? '';
            createForm.classList.add('d-none');
            editForm.classList.remove('d-none');

            const points = payload.route_points || [];
            routeLine = null;
            if (points.length > 1) {
                routeLine = new (window as any).ymaps.Polyline(
                    points.map((point: AnyObject) => [Number(point.latitude), Number(point.longitude)]),
                    {},
                    { strokeColor: '#000000', strokeWidth: 3 }
                );
            }
            redraw(airplanes);
            return;
        }

        const coords = event.get('coords');
        const bearingResponse = await request('/airplanes/bearing-to-pulkovo', 'POST', {
            latitude: coords[0],
            longitude: coords[1],
        });
        (createForm.elements.namedItem('latitude') as HTMLInputElement).value = coords[0].toFixed(8);
        (createForm.elements.namedItem('longitude') as HTMLInputElement).value = coords[1].toFixed(8);
        (createForm.elements.namedItem('direction') as HTMLInputElement).value = Number(bearingResponse.direction).toFixed(2);
        createForm.classList.remove('d-none');
        editForm.classList.add('d-none');
        routeLine = null;
        redraw(airplanes);
    });

    createForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const raw = Object.fromEntries(new FormData(createForm).entries()) as AnyObject;
        raw.route_id = raw.route_id || null;
        const airplane = await request('/airplanes', 'POST', raw);
        airplanes.push(airplane);
        redraw(airplanes);
        createForm.reset();
    });

    editForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const raw = Object.fromEntries(new FormData(editForm).entries()) as AnyObject;
        raw.route_id = raw.route_id || null;
        await request(`/airplanes/${selectedId}`, 'PUT', raw);
        location.reload();
    });

    deleteBtn.addEventListener('click', async () => {
        await request(`/airplanes/${selectedId}`, 'DELETE');
        location.reload();
    });

    [windField, runwaysField, accelerationField].forEach((field) => {
        field?.addEventListener('keyup', async () => {
            const key = field.id.replace('setting-', '');
            await request(`/settings/${key}`, 'POST', { value: field.value });
        });
    });

    setInterval(async () => {
        const activeCount = airplanes.filter((plane) => plane.is_active).length;
        if (activeCount >= 2) {
            try {
                await request('/airplanes/ai-avoid-collision', 'POST');
            } catch {
                // AI control is optional
            }
        }

        for (const airplane of airplanes) {
            if (!airplane.is_active) {
                continue;
            }
            const updated = await request(`/airplanes/${airplane.id}/update-position`, 'POST');
            Object.assign(airplane, updated);
        }

        const proximity = await request('/airplanes/check-proximity', 'POST');
        proximityConflicts = proximity.conflicts || [];
        if (proximity.time_frozen && accelerationField) {
            accelerationField.value = String(proximity.acceleration_of_time ?? 0);
        }

        redraw(airplanes);
    }, 500);
}

/**
 * Initialize map by current page.
 */
export function initPageMap(): void {
    const page = document.body.dataset.page;
    if (!page || !(window as any).ymaps) {
        return;
    }
    (window as any).ymaps.ready(() => {
        if (page === 'home') {
            createMap();
        } else if (page === 'points') {
            initPointsPage();
        } else if (page === 'routes') {
            initRoutesPage();
        } else if (page === 'airplanes') {
            initAirplanesPage();
        }
    });
}
