/**
 * Switch between the authentication code and recovery code forms.
 *
 * @param {HTMLElement} root
 */
export function init(root) {
    const code = root.querySelector('[data-two-factor-code]');
    const recovery = root.querySelector('[data-two-factor-recovery]');

    for (const toggle of root.querySelectorAll('[data-two-factor-toggle]')) {
        toggle.addEventListener('click', () => {
            code.hidden = !code.hidden;
            recovery.hidden = !recovery.hidden;

            const visible = code.hidden ? recovery : code;
            visible.querySelector('input:not([type=hidden])')?.focus();
        });
    }
}
