// Storefront entry. Plain ES modules: each enhancement loads only on pages
// that have the matching markup. Everything works without JavaScript too.

document.documentElement.classList.add('js');

const passkeyLogin = document.querySelector('[data-passkey-login]');

if (passkeyLogin) {
    void import('./shop/passkey-login.js').then(({ init }) =>
        init(passkeyLogin),
    );
}

const twoFactor = document.querySelector('[data-two-factor]');

if (twoFactor) {
    void import('./shop/two-factor-challenge.js').then(({ init }) =>
        init(twoFactor),
    );
}

if (document.querySelector('[data-cart-open], [data-add-to-cart]')) {
    void import('./shop/cart.js').then(({ init }) =>
        init(document.querySelector('[data-cart]')),
    );
}

const catalog = document.querySelector('[data-catalog]');

if (catalog) {
    void import('./shop/catalog-live.js').then(({ init }) => init(catalog));
}

const cookieBanner = document.querySelector('[data-cookie-banner]');

if (cookieBanner) {
    void import('./shop/cookie-banner.js').then(({ init }) =>
        init(cookieBanner),
    );
}

if (document.querySelector('[data-dropdown]')) {
    void import('./shop/dropdown.js').then(({ init }) => init());
}

const checkout = document.querySelector('[data-checkout]');

if (checkout?.querySelector('[data-checkout-form]')) {
    void import('./shop/checkout.js').then(({ init }) => init(checkout));
}
