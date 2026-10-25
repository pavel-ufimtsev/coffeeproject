/**
 * On narrow screens, start with the filter panel folded away so the products
 * come first, unless the shopper has already picked filters.
 *
 * @param {HTMLDetailsElement} panel
 */
export function init(panel) {
    if (
        !panel.hasAttribute('data-filtered') &&
        window.matchMedia('(max-width: 56rem)').matches
    ) {
        panel.open = false;
    }
}
