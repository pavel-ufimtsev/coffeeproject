/**
 * Filters, sorting and brew methods without page reloads. Every link in the
 * catalog is a real page; here it is fetched in the background, the catalog
 * swapped in under a spinner and the address bar updated, so Back and
 * shared links keep working.
 *
 * @param {HTMLElement} root The [data-catalog] element; it stays, its contents change.
 */
export function init(root) {
    const LIVE_LINKS =
        '[data-region="nav"] a, [data-filters] a, [data-dropdown] a, .catalog__empty a';
    const MIN_SPINNER_MS = 250;
    const rootSegment = (url) => url.pathname.split('/')[1];

    let request = null;

    const setBusy = (busy) => {
        const results = root.querySelector('[data-results]');

        results?.setAttribute('aria-busy', String(busy));
        root.classList.toggle('is-loading', busy);
    };

    /**
     * Copy the page title and the head tags that change between pages.
     *
     * @param {Document} doc
     */
    const updateHead = (doc) => {
        document.title = doc.title;

        for (const selector of [
            'meta[name="description"]',
            'meta[name="robots"]',
            'link[rel="canonical"]',
        ]) {
            const current = document.head.querySelector(selector);
            const next = doc.head.querySelector(selector);

            if (current && next) {
                current.replaceWith(next);
            } else if (next) {
                document.head.append(next);
            } else {
                current?.remove();
            }
        }
    };

    /**
     * Note where focus is (region and link text) so the same control can be
     * focused again after the swap.
     */
    const focusKey = () => {
        const active = document.activeElement;

        if (!root.contains(active)) {
            return null;
        }

        const region = active.closest('[data-region]')?.dataset.region;

        return region
            ? { region, text: active.textContent.trim(), tag: active.tagName }
            : null;
    };

    const restoreFocus = (key) => {
        if (!key) {
            return;
        }

        const match = [
            ...root.querySelectorAll(
                `[data-region="${key.region}"] ${key.tag.toLowerCase()}`,
            ),
        ].find((el) => el.textContent.trim() === key.text);

        match?.focus({ preventScroll: true });
    };

    /**
     * @param {string} url
     * @param {{ push: boolean }} options
     */
    const load = async (url, { push }) => {
        request?.abort();
        request = new AbortController();
        const { signal } = request;

        setBusy(true);

        try {
            const [response] = await Promise.all([
                fetch(url, { signal, headers: { Accept: 'text/html' } }),
                new Promise((resolve) => setTimeout(resolve, MIN_SPINNER_MS)),
            ]);

            if (!response.ok) {
                throw new Error(response.statusText);
            }

            const doc = new DOMParser().parseFromString(
                await response.text(),
                'text/html',
            );
            const next = doc.querySelector('[data-catalog]');

            if (!next) {
                throw new Error('Not a catalog page');
            }

            const openGroups = new Set(
                [...root.querySelectorAll('.filter[open]')].map(
                    (group) => group.dataset.group,
                ),
            );
            const focus = focusKey();

            root.innerHTML = next.innerHTML;

            for (const group of root.querySelectorAll('.filter')) {
                if (openGroups.has(group.dataset.group)) {
                    group.open = true;
                }
            }

            updateHead(doc);

            if (push) {
                history.pushState({ catalog: true }, '', response.url || url);
            }

            restoreFocus(focus);

            // Bring the results into view when the page was scrolled past them,
            // except while the phone filter panel is open on top.
            const bar = root.querySelector('.catalog__bar');

            if (
                bar &&
                bar.getBoundingClientRect().top < 0 &&
                !root.classList.contains('filters-open')
            ) {
                bar.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            window.location.assign(url);
        } finally {
            if (!signal.aborted) {
                setBusy(false);
            }
        }
    };

    root.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');

        if (
            !link ||
            !link.matches(LIVE_LINKS) ||
            event.defaultPrevented ||
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey
        ) {
            return;
        }

        const url = new URL(link.href);

        if (
            url.origin !== location.origin ||
            rootSegment(url) !== rootSegment(location)
        ) {
            return;
        }

        event.preventDefault();
        link.closest('[data-dropdown]')?.removeAttribute('open');
        void load(url.href, { push: true });
    });

    window.addEventListener('popstate', () => {
        void load(location.href, { push: false });
    });

    // Filters on phones: a panel that slides in from the side.
    const setFiltersOpen = (open) => {
        root.classList.toggle('filters-open', open);
        document.documentElement.classList.toggle('has-drawer', open);
        root.querySelector('[data-filters-open]')?.setAttribute(
            'aria-expanded',
            String(open),
        );

        if (open) {
            root.querySelector('[data-filters] .filter__summary')?.focus();
        }
    };

    root.addEventListener('click', (event) => {
        if (event.target.closest('[data-filters-open]')) {
            setFiltersOpen(true);
        } else if (event.target.closest('[data-filters-close]')) {
            setFiltersOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && root.classList.contains('filters-open')) {
            setFiltersOpen(false);
            root.querySelector('[data-filters-open]')?.focus();
        }
    });
}
