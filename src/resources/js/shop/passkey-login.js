import { Passkeys, UserCancelledError } from '@laravel/passkeys';

/**
 * Show the "log in with a passkey" button when the browser supports it.
 *
 * @param {HTMLElement} root
 */
export function init(root) {
    if (!Passkeys.isSupported()) {
        return;
    }

    const button = root.querySelector('button');
    const error = root.querySelector('[data-passkey-error]');

    root.hidden = false;

    button.addEventListener('click', async () => {
        button.disabled = true;
        error.hidden = true;

        try {
            const response = await Passkeys.verify();

            window.location.assign(response.redirect ?? '/account');
        } catch (e) {
            if (!(e instanceof UserCancelledError)) {
                error.textContent = e.message;
                error.hidden = false;
            }

            button.disabled = false;
        }
    });
}
