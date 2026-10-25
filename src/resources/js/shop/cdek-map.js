/**
 * The CDEK pickup point map (CDEK widget v4, Yandex Maps inside). It is
 * heavy, so it loads the first time the customer opens it, then stays on
 * the page as a popup. Settings come from the checkout's data-cdek-map;
 * the widget asks our server (/checkout/cdek) for points and prices.
 */
let widget = null;
let onPick = null;

const YANDEX_GEOCODER = 'https://geocode-maps.yandex.ru/';

/**
 * The widget sends address lookups straight to the Yandex Geocoder with the
 * map key, but Geocoder is a separate Yandex product with its own key. Send
 * them to our server instead (/checkout/geocode), which adds that key.
 *
 * @param {string} path
 */
function routeGeocoderThrough(path) {
    const open = XMLHttpRequest.prototype.open;

    XMLHttpRequest.prototype.open = function (method, url, ...rest) {
        const target = String(url);

        if (target.startsWith(YANDEX_GEOCODER)) {
            const local = new URL(path, window.location.href);
            const { searchParams } = new URL(target);

            for (const name of ['geocode', 'results']) {
                if (searchParams.has(name)) {
                    local.searchParams.set(name, searchParams.get(name));
                }
            }

            return open.call(this, method, local.toString(), ...rest);
        }

        return open.call(this, method, url, ...rest);
    };
}

/**
 * Open the map; `pick` gets the chosen point ({ code, city, address, … }).
 *
 * @param {object} settings
 * @param {(point: object) => void} pick
 */
export async function openPickupMap(settings, pick) {
    // CDEK_MAP=yandex21: our own map on Yandex JS API 2.1 instead of the widget.
    if (settings.map === 'yandex21') {
        const yandex21 = await import('./cdek-map-yandex21.js');

        return yandex21.openPickupMap(settings, pick);
    }

    onPick = pick;

    if (!widget) {
        const { default: CDEKWidget } = await import('@cdek-it/widget');
        const { geocoderPath, map: _map, ...options } = settings;

        routeGeocoderThrough(geocoderPath);

        widget = new CDEKWidget({
            ...options,
            root: 'cdek-pickup-map',
            popup: true,
            canChoose: true,
            lang: 'rus',
            currency: 'RUB',
            hideDeliveryOptions: { door: true, office: false },
            onChoose: (_mode, _tariff, point) => {
                widget.close();
                onPick?.(point);
            },
        });
    }

    widget.open();
}
