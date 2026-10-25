/**
 * Show the price of the size the shopper picks.
 *
 * @param {HTMLFormElement} form
 */
export function init(form) {
    const price = form.querySelector('[data-price]');
    const oldPrice = form.querySelector('[data-old-price]');

    form.addEventListener('change', (event) => {
        const input = event.target;

        if (!(input instanceof HTMLInputElement) || input.name !== 'variant') {
            return;
        }

        price.textContent = input.dataset.price;
        oldPrice.textContent = input.dataset.oldPrice;
        oldPrice.hidden = !input.dataset.oldPrice;
    });
}
