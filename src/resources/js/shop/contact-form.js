import { initPhone } from './phone.js';

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * The contact form: checks fields as they are filled in, sends the message
 * in the background and swaps the form for the thank-you without a page
 * reload. The server checks everything again; without JavaScript the form
 * posts normally.
 *
 * @param {HTMLElement} root
 */
export function init(root) {
    const form = root.querySelector('[data-contact-form]');
    const thanks = root.querySelector('[data-contact-thanks]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    form.noValidate = true;
    initPhone(form.querySelector('[data-phone]'));
    initCounter(form);

    // Re-check a field once it has shown an error, so the message goes away
    // as soon as it is fixed; untouched fields are only checked on leaving.
    form.addEventListener('input', (event) => {
        if (event.target.getAttribute('aria-invalid') === 'true') {
            check(event.target);
        }
    });

    form.addEventListener('change', (event) => {
        if (event.target.matches('[type="checkbox"], [type="radio"]')) {
            check(event.target);
        }
    });

    form.addEventListener(
        'blur',
        (event) => {
            if (
                event.target.matches?.('input, textarea') &&
                !event.target.matches('[type="checkbox"], [type="radio"]') &&
                event.target.value.trim()
            ) {
                check(event.target);
            }
        },
        true,
    );

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        setFormError(form, null);

        const fields = [
            ...form.querySelectorAll(
                'input:not([type="hidden"]):not([name="website"]), textarea',
            ),
        ].filter(
            // One check per radio group.
            (field, index, all) =>
                field.type !== 'radio' ||
                all.findIndex((other) => other.name === field.name) === index,
        );
        const invalid = fields.filter((field) => !check(field));

        if (invalid.length) {
            focusField(invalid[0]);

            return;
        }

        setBusy(form, true);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
            });

            if (response.status === 422) {
                showServerErrors(form, (await response.json()).errors ?? {});

                return;
            }

            if (response.status === 429) {
                setFormError(form, form.dataset.msgThrottle);

                return;
            }

            if (!response.ok) {
                throw new Error(response.statusText);
            }

            const { message } = await response.json();
            showThanks(form, thanks, message);
        } catch {
            setFormError(form, form.dataset.msgNetwork);
        } finally {
            setBusy(form, false);
        }
    });

    root.querySelector('[data-contact-again]').addEventListener('click', () => {
        swap(() => {
            thanks.hidden = true;
            form.hidden = false;
            form.querySelector('[name="message"]').focus();
        });
    });
}

/**
 * The problem with a field's value, or null when it is fine. Messages come
 * from the field's data-msg-* attributes.
 *
 * @param {HTMLInputElement|HTMLTextAreaElement} field
 */
function problem(field) {
    if (field.type === 'radio') {
        const group = field.form.elements.namedItem(field.name);

        return field.required && !group.value
            ? field.dataset.msgRequired
            : null;
    }

    if (field.type === 'checkbox') {
        return field.required && !field.checked
            ? field.dataset.msgRequired
            : null;
    }

    const value = field.value.trim();

    if (!value) {
        return field.required ? field.dataset.msgRequired : null;
    }

    if (field.type === 'email' && !EMAIL.test(value)) {
        return field.dataset.msgInvalid;
    }

    if (field.type === 'tel' && value.replace(/\D/g, '').length !== 11) {
        return field.dataset.msgInvalid;
    }

    if (field.minLength > 0 && value.length < field.minLength) {
        return field.dataset.msgShort;
    }

    return null;
}

function check(field) {
    const message = problem(field);

    if (message) {
        showError(field, message);
    } else {
        clearError(field);
    }

    return !message;
}

function errorElement(field) {
    return field.closest('[data-field]')?.querySelector('.float-field__error');
}

function showError(field, message) {
    const error = errorElement(field);
    const targets =
        field.type === 'radio'
            ? field.form.querySelectorAll(`[name="${field.name}"]`)
            : [field];

    for (const target of targets) {
        target.setAttribute('aria-invalid', 'true');

        if (error) {
            target.setAttribute('aria-describedby', error.id);
        }
    }

    if (error) {
        error.textContent = message;
        error.hidden = false;
    }
}

function clearError(field) {
    const targets =
        field.type === 'radio'
            ? field.form.querySelectorAll(`[name="${field.name}"]`)
            : [field];

    for (const target of targets) {
        target.removeAttribute('aria-invalid');
    }

    const error = errorElement(field);

    if (error) {
        error.hidden = true;
    }
}

function focusField(field) {
    const target =
        field.type === 'radio'
            ? (field.form.querySelector(`[name="${field.name}"]:checked`) ??
              field)
            : field;

    target.focus({ preventScroll: true });
    target
        .closest('[data-field]')
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/**
 * Put the server's validation messages under their fields; anything without
 * a field (the honeypot) goes above the form.
 *
 * @param {HTMLFormElement} form
 * @param {Record<string, string[]>} errors
 */
function showServerErrors(form, errors) {
    let first = null;

    for (const [name, messages] of Object.entries(errors)) {
        const field = form.querySelector(`[name="${name}"]`);

        if (!field || field.name === 'website') {
            setFormError(form, messages[0]);
            continue;
        }

        showError(field, messages[0]);
        first ??= field;
    }

    if (first) {
        focusField(first);
    }
}

function setFormError(form, message) {
    const box = form.querySelector('[data-contact-error]');

    box.textContent = message ?? '';
    box.hidden = !message;
}

function setBusy(form, busy) {
    const button = form.querySelector('[data-contact-submit]');
    const label = button.querySelector('[data-contact-submit-label]');

    label.dataset.idleLabel ??= label.textContent;
    label.textContent = busy ? label.dataset.busyLabel : label.dataset.idleLabel;
    button.disabled = busy;
    button.classList.toggle('is-busy', busy);
    form.setAttribute('aria-busy', String(busy));
}

/**
 * Swap the form for the thank-you and move focus there, so screen readers
 * announce it.
 */
function showThanks(form, thanks, message) {
    thanks.querySelector('[data-contact-thanks-text]').textContent = message;

    // Keep name, email and phone for a follow-up; clear the message itself.
    const textarea = form.querySelector('[name="message"]');
    textarea.value = '';
    textarea.dispatchEvent(new Event('input'));

    swap(() => {
        form.hidden = true;
        thanks.hidden = false;
        thanks.focus({ preventScroll: true });
        thanks.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

/**
 * Run a DOM change as a cross-fade where the browser supports view
 * transitions and the visitor has not asked for less motion.
 */
function swap(change) {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');

    if (!document.startViewTransition || reduce.matches) {
        change();

        return;
    }

    document.startViewTransition(change);
}

/**
 * "120 / 5000" under the message once the visitor starts typing.
 *
 * @param {HTMLFormElement} form
 */
function initCounter(form) {
    const textarea = form.querySelector('[name="message"]');
    const counter = form.querySelector('[data-contact-counter]');
    const max = textarea.maxLength;

    textarea.addEventListener('input', () => {
        const length = textarea.value.length;

        counter.hidden = length === 0;
        counter.textContent = `${length} / ${max}`;
        counter.classList.toggle('is-near-limit', length > max * 0.9);
    });
}
