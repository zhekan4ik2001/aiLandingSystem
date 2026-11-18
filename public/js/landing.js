(function () {
    const PULKOVO = [59.800278, 30.2625];
    const MAP_BOUNDS = [[58.9, 28.7], [60.7, 31.8]];

    function readJson(id, fallback) {
        const node = document.getElementById(id);
        if (!node || !node.textContent) {
            return fallback;
        }
        return JSON.parse(node.textContent);
    }

    function resolveUrl(url) {
        if (!url.startsWith('/') || url.startsWith('//')) {
            return url;
        }

        const baseNode = document.querySelector('meta[name="app-base-url"]');
        const base = baseNode && baseNode.content ? baseNode.content.replace(/\/$/, '') : '';

        return base + url;
    }

    async function request(url, method, body, timeoutMs) {
        const tokenNode = document.querySelector('meta[name="csrf-token"]');
        const token = tokenNode ? tokenNode.content : '';
        const options = {
            method,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
                ...(body ? { 'Content-Type': 'application/json' } : {}),
            },
            body: body ? JSON.stringify(body) : undefined,
        };

        if (timeoutMs) {
            options.signal = AbortSignal.timeout(timeoutMs);
        }

        const response = await fetch(resolveUrl(url), options);

        if (!response.ok) {
            throw new Error(method + ' ' + url + ' failed');
        }

        return response.status === 204 ? {} : response.json();
    }

    function ensureLoadingOverlay() {
        const id = 'global-loading-overlay';
        let node = document.getElementById(id);
        if (node) {
            return node;
        }

        node = document.createElement('div');
        node.id = id;
        node.style.position = 'fixed';
        node.style.inset = '0';
        node.style.background = 'rgba(0,0,0,0.35)';
        node.style.display = 'none';
        node.style.alignItems = 'center';
        node.style.justifyContent = 'center';
        node.style.zIndex = '9999';
        node.innerHTML = '<div style="background: rgba(255,255,255,0.92); padding: 12px 16px; border-radius: 10px; font-weight: 600;">Загрузка…</div>';
        document.body.appendChild(node);
        return node;
    }

    function showLoadingOverlay() {
        ensureLoadingOverlay().style.display = 'flex';
    }

    function hideLoadingOverlay() {
        const node = document.getElementById('global-loading-overlay');
        if (node) {
            node.style.display = 'none';
        }
    }

    function createMap() {
        const map = new window.ymaps.Map('map', {
            center: PULKOVO,
            zoom: 9,
            controls: ['zoomControl'],
        });
        map.setBounds(MAP_BOUNDS, { checkZoomRange: true, zoomMargin: 10 });
        return map;
    }

    function pointColor(type) {
        if (type === 'input') {
            return 'green';
        }
        if (type === 'intermediate') {
            return 'blue';
        }
        if (type === 'output') {
            return 'green';
        }
        return 'red';
    }

    function darkerColor(color) {
        const map = {
            green: '#0b5d1e',
            blue: '#0b3d91',
            red: '#8b0000',
            yellow: '#b38f00',
            gray: '#555555',
        };
        return map[color] || '#333333';
    }

    function createPointPlacemark(point) {
        const color = pointColor(point.type);
        const stroke = darkerColor(color);
        const iconSvg = '<svg width="10" height="10" viewBox="0 0 30 30" xmlns="http://www.w3.org/2000/svg" stroke-width="2"><path fill="' + color + '" stroke="' + stroke + '" stroke-width="2" d="M15 0 L26 4 L30 15 L26 26 L15 30 L4 26 L0 15 L4 4 Z"></path></svg>';

        return new window.ymaps.Placemark(
            [Number(point.latitude), Number(point.longitude)],
            { hintContent: point.name },
            {
                pointId: point.id,
                iconLayout: 'default#image',
                iconImageHref: 'data:image/svg+xml;utf8,' + encodeURIComponent(iconSvg),
                iconImageSize: [10, 10],
                iconImageOffset: [-5, -5],
            }
        );
    }

    function createAirplanePlacemark(airplane) {
        const angle = Number(airplane.direction || 0);
        // Активные самолеты - желтые, неактивные - серые
        const fill = airplane.is_active ? 'yellow' : 'gray';
        const stroke = darkerColor(fill);
        const iconSvg = '<svg width="30" height="30" viewBox="0 0 30 30" xmlns="http://www.w3.org/2000/svg" stroke-width="2"><path transform="rotate(' + angle + ' 15 15)" d="M15 0 L27 25 L15 20 L3 25 Z" fill="' + fill + '" stroke="' + stroke + '" stroke-width="2"></path></svg>';
        return new window.ymaps.Placemark(
            [Number(airplane.latitude), Number(airplane.longitude)],
            { hintContent: 'Aircraft ' + airplane.id + (airplane.is_active ? ' (active)' : ' (inactive)') },
            {
                airplaneId: airplane.id,
                iconLayout: 'default#image',
                iconImageHref: 'data:image/svg+xml;utf8,' + encodeURIComponent(iconSvg),
                iconImageSize: [30, 30],
                iconImageOffset: [-15, -15],
            }
        );
    }

    function fillForm(form, data, fields) {
        fields.forEach((name) => {
            const element = form.elements.namedItem(name);
            if (element) {
                element.value = data[name] ?? '';
            }
        });
    }

    function parsePointIds(value) {
        return String(value)
            .split(',')
            .map((x) => Number(x.trim()))
            .filter((x) => Number.isInteger(x) && x > 0);
    }

    function initPointsPage() {
        const map = createMap();
        const points = readJson('points-data', []);
        const createForm = document.getElementById('point-create-form');
        const editForm = document.getElementById('point-edit-form');
        const deleteForm = document.getElementById('point-delete-form');
        const editIdInput = editForm.elements.namedItem('id');
        let selectedId = null;

        function redraw(items) {
            map.geoObjects.removeAll();
            items.forEach((point) => {
                const placemark = createPointPlacemark(point);
                placemark.events.add('click', async (event) => {
                    event.stopPropagation();
                    selectedId = point.id;
                    const data = await request('/points/' + selectedId, 'GET');
                    fillForm(editForm, data, ['id', 'name', 'latitude', 'longitude', 'altitude_feet', 'type']);
                    editForm.classList.remove('d-none');
                    deleteForm.classList.remove('d-none');
                    createForm.classList.add('d-none');
                    editForm.action = resolveUrl('/points/' + selectedId);
                    deleteForm.action = resolveUrl('/points/' + selectedId);
                });
                map.geoObjects.add(placemark);
            });
        }

        redraw(points);

        map.events.add('click', function (event) {
            const coords = event.get('coords');
            createForm.elements.namedItem('latitude').value = coords[0].toFixed(8);
            createForm.elements.namedItem('longitude').value = coords[1].toFixed(8);
            createForm.classList.remove('d-none');
            editForm.classList.add('d-none');
            deleteForm.classList.add('d-none');
            selectedId = null;
            editIdInput.value = '';
        });

        createForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const payload = Object.fromEntries(new FormData(createForm).entries());
            const point = await request('/points', 'POST', payload);
            points.push(point);
            redraw(points);
            createForm.reset();
        });

        editForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (!selectedId) {
                return;
            }
            const payload = Object.fromEntries(new FormData(editForm).entries());
            const updated = await request('/points/' + selectedId, 'PUT', payload);
            const index = points.findIndex((point) => Number(point.id) === Number(selectedId));
            if (index >= 0) {
                points[index] = updated;
            }
            redraw(points);
        });

        deleteForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (!selectedId) {
                return;
            }
            await request('/points/' + selectedId, 'DELETE');
            const index = points.findIndex((point) => Number(point.id) === Number(selectedId));
            if (index >= 0) {
                points.splice(index, 1);
            }
            redraw(points);
            deleteForm.classList.add('d-none');
            editForm.classList.add('d-none');
            createForm.classList.remove('d-none');
            selectedId = null;
        });
    }

    function initRoutesPage() {
        const map = createMap();
        const points = readJson('points-data', []);
        const selector = document.getElementById('route-selector');
        const createForm = document.getElementById('route-create-form');
        const editForm = document.getElementById('route-edit-form');
        const deleteForm = document.getElementById('route-delete-form');
        let selectedId = null;
        let draftLine = null;

        const pointById = new Map(points.map((point) => [Number(point.id), point]));

        function drawRoutePoints(routePointIds) {
            map.geoObjects.removeAll();
            const routePoints = routePointIds.map((id) => pointById.get(Number(id))).filter(Boolean);

            routePoints.forEach((point) => {
                map.geoObjects.add(createPointPlacemark(point));
            });

            if (routePoints.length > 1) {
                map.geoObjects.add(new window.ymaps.Polyline(
                    routePoints.map((point) => [Number(point.latitude), Number(point.longitude)]),
                    {},
                    { strokeColor: '#000000', strokeWidth: 3 }
                ));
            }
        }

        function renderDraftRouteFromInput() {
            if (selector.value) {
                return;
            }
            const ids = parsePointIds(createForm.elements.namedItem('point_ids').value);
            const draftPoints = ids.map((id) => pointById.get(Number(id))).filter(Boolean);
            drawAllPointsForSelection();
            if (draftPoints.length > 1) {
                draftLine = new window.ymaps.Polyline(
                    draftPoints.map((point) => [Number(point.latitude), Number(point.longitude)]),
                    {},
                    { strokeColor: '#000000', strokeWidth: 2, strokeStyle: 'shortdash' }
                );
                map.geoObjects.add(draftLine);
            } else {
                draftLine = null;
            }
        }

        function drawAllPointsForSelection() {
            map.geoObjects.removeAll();
            points.forEach((point) => {
                const placemark = createPointPlacemark(point);
                placemark.events.add('click', function (event) {
                    event.stopPropagation();
                    if (selector.value) {
                        return;
                    }
                    const input = createForm.elements.namedItem('point_ids');
                    const ids = parsePointIds(input.value);
                    ids.push(Number(point.id));
                    input.value = Array.from(new Set(ids)).join(',');
                    renderDraftRouteFromInput();
                });
                map.geoObjects.add(placemark);
            });
        }

        drawAllPointsForSelection();
        createForm.elements.namedItem('point_ids').addEventListener('input', renderDraftRouteFromInput);

        selector.addEventListener('change', async function () {
            const routeId = selector.value;
            if (!routeId) {
                selectedId = null;
                createForm.classList.remove('d-none');
                editForm.classList.add('d-none');
                deleteForm.classList.add('d-none');
                drawAllPointsForSelection();
                renderDraftRouteFromInput();
                return;
            }

            const payload = await request('/routes/' + routeId, 'GET');
            const route = payload.route;
            selectedId = Number(route.id);
            fillForm(editForm, {
                id: route.id,
                name: route.name,
                point_ids: (route.point_ids || []).join(','),
            }, ['id', 'name', 'point_ids']);
            editForm.action = resolveUrl('/routes/' + selectedId);
            deleteForm.action = resolveUrl('/routes/' + selectedId);
            editForm.classList.remove('d-none');
            deleteForm.classList.remove('d-none');
            createForm.classList.add('d-none');
            drawRoutePoints(route.point_ids || []);
        });

        createForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const payload = Object.fromEntries(new FormData(createForm).entries());
            payload.point_ids = parsePointIds(payload.point_ids);
            const route = await request('/routes', 'POST', payload);
            const option = document.createElement('option');
            option.value = route.id;
            option.textContent = route.name;
            selector.appendChild(option);
            createForm.reset();
            renderDraftRouteFromInput();
        });

        editForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (!selectedId) {
                return;
            }
            const payload = Object.fromEntries(new FormData(editForm).entries());
            payload.point_ids = parsePointIds(payload.point_ids);
            const route = await request('/routes/' + selectedId, 'PUT', payload);
            const option = selector.querySelector('option[value="' + route.id + '"]');
            if (option) {
                option.textContent = route.name;
            }
            drawRoutePoints(route.point_ids || []);
        });

        deleteForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (!selectedId) {
                return;
            }
            await request('/routes/' + selectedId, 'DELETE');
            const option = selector.querySelector('option[value="' + selectedId + '"]');
            if (option) {
                option.remove();
            }
            selector.value = '';
            selectedId = null;
            editForm.classList.add('d-none');
            deleteForm.classList.add('d-none');
            createForm.classList.remove('d-none');
            drawAllPointsForSelection();
            renderDraftRouteFromInput();
        });
    }

    function initAirplanesPage() {
        const map = createMap();
        const airplanes = readJson('airplanes-data', []);
        const createForm = document.getElementById('airplane-create-form');
        const editForm = document.getElementById('airplane-edit-form');
        const deleteForm = document.getElementById('airplane-delete-form');
        const windField = document.getElementById('setting-windDirection');
        const accelerationField = document.getElementById('setting-accelerationOfTime');
        const intervalField = document.getElementById('setting-intervalGeneratePlane');
        const runwaysField = document.getElementById('setting-purposeOfRunways');
        let selectedId = null;
        let routeLine = null;
        let zone100 = null;
        let zone150 = null;
        let proximityConflicts = [];
        let groqAiAvailable = null;

        let latestPredictRequestId = 0;

        async function maybePredictRouteForCreateForm() {
            const routeSelect = createForm.elements.namedItem('route_id');
            if (!routeSelect || routeSelect.value) {
                return;
            }

            const lat = Number(createForm.elements.namedItem('latitude').value);
            const lon = Number(createForm.elements.namedItem('longitude').value);
            if (!Number.isFinite(lat) || !Number.isFinite(lon)) {
                return;
            }

            const requestId = ++latestPredictRequestId;
            showLoadingOverlay();
            try {
                const payload = await request('/airplanes/predict-route', 'POST', { latitude: lat, longitude: lon });
                if (requestId !== latestPredictRequestId) {
                    return;
                }
                const predicted = payload ? payload.route_id : null;
                if (predicted) {
                    routeSelect.value = String(predicted);
                }
            } catch (error) {
                // ignore: prediction is optional
            } finally {
                if (requestId === latestPredictRequestId) {
                    hideLoadingOverlay();
                }
            }
        }

        async function handleMapCoordinatePick(coords) {
            const bearing = await request('/airplanes/bearing-to-pulkovo', 'POST', {
                latitude: coords[0],
                longitude: coords[1],
            });

            createForm.elements.namedItem('latitude').value = coords[0].toFixed(8);
            createForm.elements.namedItem('longitude').value = coords[1].toFixed(8);
            createForm.elements.namedItem('direction').value = Number(bearing.direction).toFixed(2);
            createForm.elements.namedItem('altitude_feet').value = '15000';
            createForm.elements.namedItem('speed').value = '250';

            const routeSelect = createForm.elements.namedItem('route_id');
            if (routeSelect) {
                routeSelect.value = '';
                latestPredictRequestId++;
            }

            createForm.classList.remove('d-none');
            editForm.classList.add('d-none');
            deleteForm.classList.add('d-none');
            routeLine = null;
            selectedId = null;
            drawAirplanes();

            await maybePredictRouteForCreateForm();
        }

        function createCircle(radiusKm, color) {
            return new window.ymaps.Circle(
                [PULKOVO, radiusKm * 1000],
                {},
                {
                    fillColor: 'rgba(0,0,0,0)',
                    strokeColor: color,
                    strokeWidth: 2,
                    strokeOpacity: 0.8,
                }
            );
        }

        function drawPulkovoZones() {
            zone100 = createCircle(100, '#000000');
            zone150 = createCircle(150, '#000000');

            zone100.events.add('click', async function (event) {
                event.stopPropagation();
                await handleMapCoordinatePick(event.get('coords'));
            });

            zone150.events.add('click', async function (event) {
                event.stopPropagation();
                await handleMapCoordinatePick(event.get('coords'));
            });

            map.geoObjects.add(zone100);
            map.geoObjects.add(zone150);
        }

        function drawProximityConflicts() {
            proximityConflicts.forEach(function (conflict) {
                map.geoObjects.add(new window.ymaps.Circle(
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
        }

        function drawAirplanes() {
            map.geoObjects.removeAll();
            drawPulkovoZones();
            drawProximityConflicts();
            airplanes.forEach((plane) => {
                const placemark = createAirplanePlacemark(plane);
                placemark.events.add('click', async (event) => {
                    event.stopPropagation();
                    selectedId = plane.id;
                    const payload = await request('/airplanes/' + selectedId, 'GET');
                    const airplane = payload.airplane;
                    fillForm(editForm, airplane, ['id', 'latitude', 'longitude', 'altitude_feet', 'direction', 'speed', 'route_id']);
                    editForm.action = resolveUrl('/airplanes/' + selectedId);
                    deleteForm.action = resolveUrl('/airplanes/' + selectedId);
                    editForm.classList.remove('d-none');
                    deleteForm.classList.remove('d-none');
                    createForm.classList.add('d-none');

                    routeLine = null;
                    const points = payload.route_points || [];
                    if (points.length > 1) {
                        routeLine = new window.ymaps.Polyline(
                            points.map((point) => [Number(point.latitude), Number(point.longitude)]),
                            {},
                            { strokeColor: '#000000', strokeWidth: 3 }
                        );
                    }

                    drawAirplanes();
                });
                map.geoObjects.add(placemark);
            });

            if (routeLine) {
                map.geoObjects.add(routeLine);
            }
        }

        drawAirplanes();

        map.events.add('click', async function (event) {
            await handleMapCoordinatePick(event.get('coords'));
        });

        createForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const payload = Object.fromEntries(new FormData(createForm).entries());
            if (!payload.route_id) {
                payload.route_id = null;
            }
            const airplane = await request('/airplanes', 'POST', payload);
            airplanes.push(airplane);
            drawAirplanes();
            createForm.reset();
        });

        editForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (!selectedId) {
                return;
            }
            const payload = Object.fromEntries(new FormData(editForm).entries());
            if (!payload.route_id) {
                payload.route_id = null;
            }
            const updated = await request('/airplanes/' + selectedId, 'PUT', payload);
            const index = airplanes.findIndex((plane) => Number(plane.id) === Number(selectedId));
            if (index >= 0) {
                airplanes[index] = updated;
            }
            drawAirplanes();
        });

        deleteForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (!selectedId) {
                return;
            }
            await request('/airplanes/' + selectedId, 'DELETE');
            const index = airplanes.findIndex((plane) => Number(plane.id) === Number(selectedId));
            if (index >= 0) {
                airplanes.splice(index, 1);
            }
            editForm.classList.add('d-none');
            deleteForm.classList.add('d-none');
            createForm.classList.remove('d-none');
            selectedId = null;
            routeLine = null;
            drawAirplanes();
        });

        if (windField) {
            windField.addEventListener('keyup', async function () {
                await request('/settings/windDirection', 'POST', { value: windField.value });
                await maybePredictRouteForCreateForm();
            });
        }

        if (runwaysField) {
            runwaysField.addEventListener('change', async function () {
                await request('/settings/purposeOfRunways', 'POST', { value: runwaysField.value });
                await maybePredictRouteForCreateForm();
            });
        }

        if (accelerationField) {
            accelerationField.addEventListener('keyup', async function () {
                await request('/settings/accelerationOfTime', 'POST', { value: accelerationField.value });
            });
        }

        // Добавляем обработчик для интервала генерации
        const intervalField = document.getElementById('setting-intervalGeneratePlane');
        if (intervalField) {
            intervalField.addEventListener('keyup', async function () {
                await request('/settings/intervalGeneratePlane', 'POST', { value: intervalField.value });
            });
        }

        function logGroqStatusToConsole(status, error) {
            const prefix = '[AI ATC] Groq';

            if (error) {
                console.warn(prefix, 'проверка не удалась', error);
                return;
            }

            if (status.available) {
                console.info(prefix, 'работает', status.message, status);
            } else {
                console.warn(prefix, 'не работает', status.message, status);
            }
        }

        function logAiAgentResultToConsole(result) {
            if (!result) {
                return;
            }

            const prefix = '[AI ATC]';

            if (result.commands_count > 0) {
                console.group(prefix + ' Команды агента (' + result.commands_count + ')');
                (result.commands || []).forEach(function (command, index) {
                    console.log((index + 1) + '.', command.label, command);
                });
                if (result.agent_message) {
                    console.log('Ответ Groq:', result.agent_message);
                }
                console.log('Конфликты до/после:', result.conflicts_before, '→', result.conflicts_after);
                console.groupEnd();
            } else if (result.ran && result.parse_warning) {
                console.warn(prefix, result.parse_warning, result.agent_message || result);
            } else if (result.ran) {
                console.log(prefix, 'агент ответил, команд нет (NONE или пустой ответ)', result.agent_message || result);
            } else if (result.reason === 'agent_error') {
                console.error(prefix, 'ошибка агента', result.error_message || result, result);
            }
        }

        function isSimulationRunning() {
            if (!accelerationField) {
                return true;
            }
            const value = Number(accelerationField.value);
            return Number.isFinite(value) && value > 0;
        }

        async function refreshGroqStatus() {
            try {
                const status = await request('/airplanes/groq-status', 'GET', undefined, 4000);
                groqAiAvailable = Boolean(status.available);
                logGroqStatusToConsole(status);
            } catch (error) {
                groqAiAvailable = false;
                logGroqStatusToConsole(null, error);
            }
        }

        refreshGroqStatus();

        const TICK_MS = 500;
        const PROXIMITY_MS = 1000;
        const AI_AGENT_MS = 10000;
        const GENERATE_PLANE_MS = 5000; // Проверять каждые 5 секунд
        let lastProximityCheckAt = 0;
        let lastAiAgentAt = 0;
        let lastGenerateAttemptAt = 0;

        setInterval(async function () {
            if (!isSimulationRunning()) {
                drawAirplanes();
                return;
            }

            const now = Date.now();
            const activeCount = airplanes.filter((plane) => plane.is_active).length;

            if (activeCount >= 2 && groqAiAvailable !== false && now - lastAiAgentAt >= AI_AGENT_MS) {
                lastAiAgentAt = now;
                try {
                    const aiResult = await request('/airplanes/ai-avoid-collision', 'POST', undefined, 15000);
                    if (aiResult && aiResult.groq_available === false && aiResult.reason !== 'agent_error') {
                        groqAiAvailable = false;
                        logGroqStatusToConsole({
                            available: false,
                            message: 'Groq стал недоступен во время работы агента',
                            reason: aiResult.reason || 'unreachable',
                        });
                    }
                    logAiAgentResultToConsole(aiResult);
                } catch (error) {
                    groqAiAvailable = false;
                    console.error('[AI ATC]', 'запрос ai-avoid-collision не удался', error);
                }
            }

            for (const airplane of airplanes) {
                if (!airplane.is_active) {
                    continue;
                }
                const updated = await request('/airplanes/' + airplane.id + '/update-position', 'POST');
                Object.assign(airplane, updated);
            }

            if (now - lastProximityCheckAt >= PROXIMITY_MS) {
                lastProximityCheckAt = now;
                const proximity = await request('/airplanes/check-proximity', 'POST');
                proximityConflicts = proximity.conflicts || [];
                if (proximity.time_frozen && accelerationField) {
                    accelerationField.value = String(proximity.acceleration_of_time ?? 0);
                }
            }

            // Генерация случайных самолетов
            if (now - lastGenerateAttemptAt >= GENERATE_PLANE_MS) {
                lastGenerateAttemptAt = now;
                try {
                    const result = await request('/airplanes/generate-random', 'POST');
                    if (result.generated && result.airplane) {
                        airplanes.push(result.airplane);
                        console.log('[Генератор] Создан новый самолет:', result.airplane);
                    }
                } catch (error) {
                    console.error('[Генератор] Ошибка генерации самолета:', error);
                }
            }

            drawAirplanes();
        }, TICK_MS);
    }

    function boot() {
        const page = document.body.dataset.page;
        if (!page || !window.ymaps) {
            return;
        }

        window.ymaps.ready(function () {
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

    boot();
})();
