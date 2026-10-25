/**
 * Our pickup point map on Yandex Maps JS API 2.1 (CDEK_MAP=yandex21), for
 * map keys that JS API 3.0, and so the CDEK widget, refuses. It asks the
 * same server as the widget: /checkout/cdek for the points in view and
 * /checkout/geocode to find a city. The popup is the checkout's
 * [data-pickup-map] dialog; its texts come from its data attributes.
 */
const YANDEX_URL = 'https://api-maps.yandex.ru/2.1/';

/** Ekaterinburg, if the starting city cannot be found. */
const FALLBACK_CENTER = [56.8389, 60.6057];

/** Below this zoom the area holds too many points to ask for. */
const MIN_ZOOM = 9;

let ymapsReady = null;
let map = null;
let points = null;
let onPick = null;
const shown = new Map();

/**
 * Open the map; `pick` gets the chosen point ({ code, … }).
 *
 * @param {{ apiKey: string, servicePath: string, geocoderPath: string, defaultLocation: string }} settings
 * @param {(point: object) => void} pick
 */
export async function openPickupMap(settings, pick) {
    onPick = pick;

    const dialog = document.querySelector('[data-pickup-map]');

    if (!dialog.open) {
        dialog.showModal();
        // The dialog, not the search box: no keyboard popping up on phones.
        dialog.focus({ preventScroll: true });
    }

    if (map) {
        map.container.fitToViewport();

        return;
    }

    bindDialog(dialog, settings);

    const ymaps = await loadYandex(settings.apiKey);
    const center =
        (await geocode(settings, settings.defaultLocation))?.center ??
        FALLBACK_CENTER;

    map = new ymaps.Map(
        dialog.querySelector('[data-pickup-map-canvas]'),
        { center, zoom: 11, controls: ['zoomControl', 'geolocationControl'] },
        { suppressMapOpenBlock: true },
    );

    points = new ymaps.ObjectManager({ clusterize: true, gridSize: 64 });
    points.objects.options.set({ preset: 'islands#greenDotIcon' });
    points.clusters.options.set({ preset: 'islands#greenClusterIcons' });
    map.geoObjects.add(points);

    let timer = null;
    map.events.add('boundschange', () => {
        clearTimeout(timer);
        timer = setTimeout(() => void loadPoints(dialog, settings), 350);
    });

    void loadPoints(dialog, settings);
}

function loadYandex(apiKey) {
    ymapsReady ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = `${YANDEX_URL}?apikey=${encodeURIComponent(apiKey)}&lang=ru_RU`;
        script.onload = () => window.ymaps.ready(() => resolve(window.ymaps));
        script.onerror = () => {
            ymapsReady = null;
            reject(new Error('Yandex Maps did not load'));
        };
        document.head.append(script);
    });

    return ymapsReady;
}

function bindDialog(dialog, settings) {
    dialog
        .querySelector('[data-pickup-map-close]')
        .addEventListener('click', () => dialog.close());

    // A click on the dimmed backdrop closes the map.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }

        const choose = event.target.closest('[data-pickup-choose]');

        if (choose) {
            const point = shown.get(choose.dataset.pickupChoose);

            dialog.close();
            onPick?.(point);
        }
    });

    dialog
        .querySelector('[data-pickup-map-search]')
        .addEventListener('submit', async (event) => {
            event.preventDefault();

            const query = event.target.elements.query.value.trim();

            if (!query || !map) {
                return;
            }

            const place = await geocode(settings, query);

            if (!place) {
                setStatus(dialog, dialog.dataset.msgNotFound);

                return;
            }

            if (place.bounds) {
                map.setBounds(place.bounds, { checkZoomRange: true });
            } else {
                map.setCenter(place.center, 13);
            }
        });
}

/**
 * The centre and extent of a place, by name; null if Yandex finds nothing.
 *
 * @returns {Promise<{ center: number[], bounds: number[][] | null } | null>}
 */
async function geocode(settings, query) {
    try {
        const url = new URL(settings.geocoderPath, window.location.href);
        url.searchParams.set('geocode', query);
        url.searchParams.set('results', '1');

        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        const place =
            data.response?.GeoObjectCollection?.featureMember?.[0]?.GeoObject;

        if (!place) {
            return null;
        }

        // Yandex writes "longitude latitude"; the map wants [latitude, longitude].
        const latLng = (pair) => pair.split(' ').map(Number).reverse();
        const envelope = place.boundedBy?.Envelope;

        return {
            center: latLng(place.Point.pos),
            bounds: envelope
                ? [latLng(envelope.lowerCorner), latLng(envelope.upperCorner)]
                : null,
        };
    } catch {
        return null;
    }
}

let pending = null;

async function loadPoints(dialog, settings) {
    if (map.getZoom() < MIN_ZOOM) {
        setStatus(dialog, dialog.dataset.msgZoom);

        return;
    }

    const [[south, west], [north, east]] = map.getBounds();
    const url = new URL(settings.servicePath, window.location.href);

    url.search = new URLSearchParams({
        action: 'byCoordinate',
        latitude_left_bottom: south.toFixed(5),
        longitude_left_bottom: west.toFixed(5),
        latitude_right_top: north.toFixed(5),
        longitude_right_top: east.toFixed(5),
    });

    pending?.abort();
    pending = new AbortController();

    try {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            signal: pending.signal,
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const list = await response.json();
        const added = list
            .filter(
                (point) => point.location?.latitude && !shown.has(point.code),
            )
            .map((point) => {
                shown.set(point.code, point);

                return feature(dialog, point);
            });

        if (added.length) {
            points.add({ type: 'FeatureCollection', features: added });
        }

        setStatus(dialog, list.length ? '' : dialog.dataset.msgEmpty);
    } catch (error) {
        if (error.name !== 'AbortError') {
            setStatus(dialog, dialog.dataset.msgError);
        }
    }
}

function feature(dialog, point) {
    const isLocker = point.type === 'POSTAMAT';
    const kind = isLocker
        ? dialog.dataset.labelLocker
        : dialog.dataset.labelPickup;

    return {
        type: 'Feature',
        id: point.code,
        geometry: {
            type: 'Point',
            coordinates: [point.location.latitude, point.location.longitude],
        },
        properties: {
            hintContent: escape(point.location.address ?? point.name),
            balloonContentHeader: escape(kind),
            balloonContentBody: [
                `<p class="pickup-balloon__address">${escape(point.location.address ?? '')}</p>`,
                point.work_time
                    ? `<p class="pickup-balloon__note">${escape(point.work_time)}</p>`
                    : '',
                `<button type="button" class="button pickup-balloon__choose" data-pickup-choose="${escape(point.code)}">${escape(dialog.dataset.labelChoose)}</button>`,
            ].join(''),
        },
        options: isLocker ? { preset: 'islands#darkGreenDotIcon' } : {},
    };
}

function setStatus(dialog, message) {
    const status = dialog.querySelector('[data-pickup-map-status]');

    status.textContent = message;
    status.hidden = !message;
}

function escape(text) {
    return String(text).replace(
        /[&<>"']/g,
        (char) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            })[char],
    );
}
