/**
 * The cookie banner: save the answer in the background and slide the banner
 * away. If the request fails, the form posts normally instead.
 *
 * @param {HTMLElement} banner
 */
export function init(banner) {
    const form = banner.querySelector('[data-cookie-form]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        // Read the answer before disabling the buttons: a disabled submit
        // button is left out of the form data.
        const data = new FormData(form, event.submitter);

        for (const button of form.querySelectorAll('button')) {
            button.disabled = true;
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: data,
            });

            if (!response.ok) {
                throw new Error(response.statusText);
            }
        } catch {
            // Post the normal way, carrying the clicked button's value.
            const choice = document.createElement('input');
            choice.type = 'hidden';
            choice.name = 'choice';
            choice.value = data.get('choice');
            form.append(choice);
            form.submit();

            return;
        }

        banner.classList.add('is-leaving');
        banner.addEventListener('animationend', () => banner.remove(), {
            once: true,
        });

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            banner.remove();
        }
    });
}
