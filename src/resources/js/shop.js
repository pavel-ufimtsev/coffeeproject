// Storefront entry. Plain ES modules: each enhancement loads only on pages
// that have the matching markup.

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
