/**
 * The cart drawer. Add-to-cart and quantity forms post in the background and
 * the server sends back the redrawn drawer; without JavaScript the same forms
 * post normally and land on /cart.
 *
 * Motion: the drawer slides in while the product photo glides into its line,
 * the contents fade in, and the line just added or changed glows. Nothing
 * overshoots or bounces.
 * All of it is skipped when the shopper prefers reduced motion.
 *
 * @param {HTMLDialogElement|null} drawer Missing on the /cart page itself.
 */
export function init(drawer) {
    const body = drawer?.querySelector('[data-cart-body]');
    const panel = drawer?.querySelector('.drawer__panel');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const calm = () =>
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const ENTER_MS = 900;
    const FLIGHT_MS = 600;

    const lineFor = (key) =>
        key ? body?.querySelector(`[data-line="${CSS.escape(key)}"]`) : null;

    /**
     * Replay a one-off CSS animation class on an element.
     */
    const flash = (element, className) => {
        if (!element) {
            return;
        }

        element.classList.remove(className);
        void element.offsetWidth;
        element.classList.add(className);
        element.addEventListener(
            'animationend',
            (event) => {
                if (event.target === element) {
                    element.classList.remove(className);
                }
            },
            { once: true },
        );
    };

    const open = () => {
        if (!drawer || drawer.open) {
            return;
        }

        drawer.classList.add('is-entering');
        drawer.showModal();
        document.documentElement.classList.add('has-drawer');
        setTimeout(() => drawer.classList.remove('is-entering'), ENTER_MS);
    };

    const close = () => {
        if (!drawer?.open || drawer.classList.contains('is-closing')) {
            return;
        }

        const finish = () => {
            drawer.classList.remove('is-closing');
            drawer.close();
        };

        if (calm()) {
            finish();

            return;
        }

        drawer.classList.add('is-closing');
        panel.addEventListener('transitionend', finish, { once: true });
        // In case the transition is interrupted and never ends.
        setTimeout(() => drawer.open && finish(), 600);
    };

    const render = ({ count, html }) => {
        for (const badge of document.querySelectorAll('[data-cart-count]')) {
            badge.textContent = count;
            badge.hidden = count === 0;
            flash(badge, 'is-bumped');
        }

        if (body) {
            body.innerHTML = html;
        }
    };

    /**
     * Fly a copy of the product photo into its line in the opening drawer.
     * The copy lives inside the dialog, the only layer above the backdrop.
     *
     * @param {HTMLImageElement|null|undefined} image
     * @param {HTMLElement|null|undefined} line
     */
    const fly = (image, line) => {
        const target = line?.querySelector('.cart-line__media');

        if (!image || !target || calm()) {
            return;
        }

        const from = image.getBoundingClientRect();
        const to = target.getBoundingClientRect();
        // The panel is still off-screen: aim where the line will come to rest.
        const offset =
            panel.getBoundingClientRect().left -
            (window.innerWidth - panel.offsetWidth);
        const toLeft = to.left - offset;
        const scale = to.width / from.width;

        const ghost = image.cloneNode();
        ghost.removeAttribute('loading');
        ghost.className = 'cart-ghost';
        Object.assign(ghost.style, {
            left: `${from.left}px`,
            top: `${from.top}px`,
            width: `${from.width}px`,
            height: `${from.height}px`,
        });
        drawer.append(ghost);
        target.style.opacity = '0';

        // A straight glide that slows into place and dissolves into the line.
        const dx = toLeft - from.left;
        const dy = to.top - from.top;
        const flight = ghost.animate(
            [
                { transform: 'translate(0, 0) scale(1)', opacity: 1 },
                {
                    transform: `translate(${dx}px, ${dy}px) scale(${scale})`,
                    opacity: 0,
                },
            ],
            {
                duration: FLIGHT_MS,
                easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                fill: 'forwards',
            },
        );

        void flight.finished.finally(() => {
            ghost.remove();
            target.style.opacity = '';
            flash(target, 'is-landed');
        });
    };

    /**
     * @param {HTMLFormElement} form
     * @param {HTMLElement|null} submitter
     */
    const send = async (form, submitter) => {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form, submitter),
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));

            throw new Error(data.message ?? response.statusText);
        }

        return response.json();
    };

    /**
     * @param {HTMLFormElement} form
     * @param {HTMLElement|null} submitter
     */
    const add = async (form, submitter) => {
        const button = form.querySelector('.add-button');
        const label = button.querySelector('.add-button__label');
        const text = label.textContent;
        const image = form
            .closest('[data-price-scope]')
            ?.querySelector('.product-card__image, .product__image');

        button.disabled = true;
        button.classList.add('is-loading');

        try {
            const data = await send(form, submitter);

            render(data);
            button.classList.replace('is-loading', 'is-done');
            label.textContent = button.dataset.doneLabel ?? '✓';

            const line = lineFor(data.added);

            open();
            fly(image, line);
            flash(line, 'is-added');
        } catch (error) {
            button.classList.remove('is-loading');
            label.textContent = error.message;
        }

        setTimeout(() => {
            button.classList.remove('is-done');
            button.disabled = false;
            label.textContent = text;
        }, 1600);
    };

    /**
     * Change a quantity or remove a line inside the drawer.
     *
     * @param {HTMLFormElement} form
     * @param {HTMLElement|null} submitter
     */
    const update = async (form, submitter) => {
        const line = form.closest('[data-line]');
        const key = line?.dataset.line;
        const removing =
            form.querySelector('input[name="_method"]')?.value === 'DELETE' ||
            submitter?.value === '0';

        body.setAttribute('aria-busy', 'true');

        try {
            // A removed line folds away while the request runs.
            const [data] = await Promise.all([
                send(form, submitter),
                removing && line && !calm()
                    ? line.animate(
                          [
                              { opacity: 1, transform: 'none' },
                              { opacity: 0, transform: 'translateX(2rem)' },
                          ],
                          {
                              duration: 220,
                              easing: 'ease-in',
                              fill: 'forwards',
                          },
                      ).finished
                    : null,
            ]);

            render(data);

            if (!removing) {
                flash(lineFor(key), 'is-updated');
            }
        } catch {
            form.submit();
        } finally {
            body.removeAttribute('aria-busy');
        }
    };

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-cart-open]') && drawer) {
            event.preventDefault();
            open();
        }
    });

    drawer?.addEventListener('click', (event) => {
        // The dialog covers the page; a click outside the panel lands on it.
        if (
            event.target === drawer ||
            event.target.closest('[data-cart-close]')
        ) {
            close();
        }
    });

    drawer?.addEventListener('cancel', (event) => {
        event.preventDefault();
        close();
    });

    drawer?.addEventListener('close', () => {
        document.documentElement.classList.remove('has-drawer');
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (form.matches('[data-add-to-cart]')) {
            event.preventDefault();
            void add(form, event.submitter);
        } else if (form.matches('[data-cart-update]') && body) {
            event.preventDefault();
            void update(form, event.submitter);
        }
    });

    // The price next to a card or the product page's buy box follows the size.
    document.addEventListener('change', (event) => {
        const input = event.target;

        if (!(input instanceof HTMLInputElement) || input.name !== 'variant') {
            return;
        }

        const scope = input.closest('[data-price-scope]');
        const price = scope?.querySelector('[data-price]');
        const oldPrice = scope?.querySelector('[data-old-price]');

        if (price) {
            price.textContent = input.dataset.price;
            flash(price, 'is-changed');
        }

        if (oldPrice) {
            oldPrice.textContent = input.dataset.oldPrice;
            oldPrice.hidden = !input.dataset.oldPrice;
        }
    });
}
