/**
 * Dropdowns are <details data-dropdown>: close them on a click elsewhere,
 * on Escape, and once an item is picked.
 */
export function init() {
    const closeAll = (except = null) => {
        for (const dropdown of document.querySelectorAll(
            '[data-dropdown][open]',
        )) {
            if (dropdown !== except) {
                dropdown.open = false;
            }
        }
    };

    document.addEventListener('click', (event) => {
        const dropdown = event.target.closest('[data-dropdown]');

        closeAll(dropdown);

        if (dropdown && event.target.closest('.dropdown__menu a')) {
            dropdown.open = false;
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        const open = document.querySelector('[data-dropdown][open]');

        if (open) {
            open.open = false;
            open.querySelector('summary').focus();
        }
    });
}
