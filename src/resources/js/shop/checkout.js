/**
 * The checkout page. Everything here is an enhancement: without JavaScript
 * the form, promo code and saved-address links all work as plain requests.
 *
 * - the order summary folds up on phones;
 * - picking a saved address fills the fields in place;
 * - the promo code and delivery method redraw the summary in the background;
 * - the phone is formatted as it is typed;
 * - empty or malformed fields get an inline message instead of the
 *   browser's bubble, and the first one is scrolled into view;
 * - the button shows progress and cannot be pressed twice.
 *
 * @param {HTMLElement} root The [data-checkout] element.
 */
export function init(root) {
    const form = root.querySelector('[data-checkout-form]');
    const aside = root.querySelector('[data-checkout-aside]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    form.noValidate = true;

    initSummaryToggle(aside);
    initPaymentChoice(form);
    initAddressPicker(form);
    initPhone(form.querySelector('[data-phone]'));
    initValidation(form);

    let request = null;

    /**
     * Swap in the summary, delivery options and totals from the server.
     *
     * @param {Response} response
     */
    const applyQuote = async (response) => {
        const data = await response.json();

        if (!response.ok) {
            const message = data.errors?.promo?.[0];

            if (message) {
                showPromoError(message);
            }

            return;
        }

        const summary = aside.querySelector('[data-summary]');
        const delivery = form.querySelector('[data-delivery]');
        const focusedPromo =
            document.activeElement?.closest('[data-promo-form]');

        summary.innerHTML = data.summary;
        delivery.innerHTML = data.delivery;

        root.querySelectorAll('[data-checkout-total]').forEach((el) => {
            el.textContent = data.totalLabel;
        });
        form.querySelector('[data-expected-total]').value = data.total;

        // Keep focus in the promo area: on the field after an error, on the
        // remove button after applying.
        if (focusedPromo) {
            const target =
                summary.querySelector('[aria-invalid="true"]') ??
                summary.querySelector('.promo-applied__remove') ??
                summary.querySelector('[name="promo"]');

            target?.focus({ preventScroll: true });
        }
    };

    const showPromoError = (message) => {
        const input = aside.querySelector('[name="promo"]');
        const error = aside.querySelector('#checkout-promo-error');

        if (!input || !error) {
            return;
        }

        error.textContent = message;
        error.hidden = false;
        input.setAttribute('aria-invalid', 'true');
        input.setAttribute('aria-describedby', error.id);
        input.focus();
    };

    /**
     * @param {string} url
     * @param {RequestInit} init
     */
    const fetchQuote = async (url, init = {}) => {
        request?.abort();
        request = new AbortController();

        const summary = aside.querySelector('[data-summary]');
        summary.classList.add('is-updating');

        try {
            const response = await fetch(url, {
                ...init,
                signal: request.signal,
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
            });

            await applyQuote(response);
        } catch (error) {
            if (error.name !== 'AbortError') {
                throw error;
            }
        } finally {
            summary.classList.remove('is-updating');
        }
    };

    const selectedDelivery = () =>
        form.querySelector('[name="delivery_method"]:checked')?.value ?? '';

    // Promo code: apply and remove in place.
    aside.addEventListener('submit', (event) => {
        const promoForm = event.target.closest('[data-promo-form]');

        if (!promoForm) {
            return;
        }

        event.preventDefault();

        const body = new FormData(promoForm);
        body.set('delivery', selectedDelivery());

        const promoInput = promoForm.querySelector('[name="promo"]');

        if (promoInput && !promoInput.value.trim()) {
            showPromoError(promoInput.dataset.msgRequired);

            return;
        }

        void fetchQuote(promoForm.action, { method: 'POST', body });
    });

    aside.addEventListener('input', (event) => {
        if (event.target.name === 'promo') {
            clearError(event.target);
            aside
                .querySelector('#checkout-promo-error')
                ?.setAttribute('hidden', '');
        }
    });

    // Delivery method: the price and free-delivery hint depend on it.
    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-delivery-method]')) {
            const url = new URL(root.dataset.summaryUrl);
            url.searchParams.set('delivery', event.target.value);
            void fetchQuote(url);
        }
    });

    // Placing the order: one press only, with progress on the button.
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) {
            return;
        }

        const button = form.querySelector('[data-checkout-submit]');

        if (button.classList.contains('is-busy')) {
            event.preventDefault();

            return;
        }

        button.classList.add('is-busy');
        button.setAttribute('aria-disabled', 'true');
        button.querySelector('[data-submit-label]').textContent =
            button.dataset.busyLabel;
    });

    // Coming back with the Back button restores the page from cache; let
    // the button be pressed again.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            window.location.reload();
        }
    });
}

/**
 * Paying online: the button says where it leads, with a note under it.
 *
 * @param {HTMLFormElement} form
 */
function initPaymentChoice(form) {
    const label = form.querySelector('[data-submit-label]');
    const text = form.querySelector('[data-submit-text]');
    const hint = form.querySelector('[data-online-hint]');

    const update = () => {
        const online =
            form.querySelector('[data-payment-method]:checked')?.dataset
                .online === '1';

        text.textContent = online
            ? label.dataset.labelOnline
            : label.dataset.labelOffline;
        hint.hidden = !online;
    };

    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-payment-method]')) {
            update();
        }
    });

    update();
}

/**
 * Phones: the summary is a fold-out bar at the top showing the total.
 *
 * @param {HTMLElement} aside
 */
function initSummaryToggle(aside) {
    const toggle = aside.querySelector('[data-summary-toggle]');
    const text = toggle.querySelector('[data-toggle-text]');

    toggle.addEventListener('click', () => {
        const open = !aside.classList.contains('is-open');

        aside.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        text.textContent = open ? text.dataset.hide : text.dataset.show;
    });

    // A promo error from a page reload: show it rather than hide it.
    if (aside.querySelector('[data-summary] [aria-invalid="true"]')) {
        toggle.click();
    }
}

/**
 * Saved addresses: the chips are links (they work without JavaScript); here
 * they fill the fields in place and remember which address edits go to.
 *
 * @param {HTMLFormElement} form
 */
function initAddressPicker(form) {
    const picker = form.querySelector('[data-address-picker]');

    if (!picker) {
        return;
    }

    const idInput = form.querySelector('[data-address-id]');
    const note = form.querySelector('[data-address-note]');
    const labelField = form.querySelector('[data-address-label]');
    const labelInput = labelField?.querySelector('input');
    const fields = [...form.querySelectorAll('[data-address-field]')];

    // What was typed into "New address", kept while browsing saved ones.
    let draft = null;

    picker.addEventListener('click', (event) => {
        const chip = event.target.closest('[data-address-choice]');

        if (!chip) {
            return;
        }

        event.preventDefault();

        if (chip.getAttribute('aria-current') === 'true') {
            return;
        }

        if (idInput.value === 'new') {
            draft = Object.fromEntries(fields.map((f) => [f.name, f.value]));
        }

        const id = chip.dataset.addressChoice;
        const values =
            id === 'new'
                ? (draft ?? { city: picker.dataset.defaultCity })
                : JSON.parse(chip.dataset.fields);

        for (const field of fields) {
            field.value = values[field.name] ?? '';
            clearError(field);
        }

        picker
            .querySelectorAll('[data-address-choice]')
            .forEach((el) => el.removeAttribute('aria-current'));
        chip.setAttribute('aria-current', 'true');
        idInput.value = id;

        const isNew = id === 'new';

        labelField.hidden = !isNew;
        labelInput.required = isNew;
        clearError(labelInput);
        note.hidden = isNew;
        note.textContent = isNew
            ? ''
            : note.dataset.template.replace(':label', chip.dataset.label);

        if (isNew) {
            labelInput.focus();
        }
    });
}

/**
 * Format a Russian number as it is typed: "+7 909 701-90-07". A leading 8
 * or 7 is the country code; anything else is taken as the number itself.
 *
 * @param {HTMLInputElement|null} input
 */
function initPhone(input) {
    if (!input) {
        return;
    }

    const format = (value) => {
        let digits = value.replace(/\D/g, '');

        // "+7" typed or put in on focus: that 7 is the country code.
        if (/^\s*\+7/.test(value)) {
            digits = digits.slice(1);
        }

        if (!digits) {
            return value.trim() === '' ? '' : '+7 ';
        }

        // Habit: "8 909…" or "+7 8 909…". Only drop the 8 or 7 once there
        // are 11 digits, so city codes like 812 and 843 still work.
        if (digits.length > 10 && (digits[0] === '8' || digits[0] === '7')) {
            digits = digits.slice(1);
        }

        digits = digits.slice(0, 10);

        const parts = [
            digits.slice(0, 3),
            digits.slice(3, 6),
            digits.slice(6, 8),
            digits.slice(8, 10),
        ];
        let out = '+7';

        if (parts[0]) out += ' ' + parts[0];
        if (parts[1]) out += ' ' + parts[1];
        if (parts[2]) out += '-' + parts[2];
        if (parts[3]) out += '-' + parts[3];

        return out;
    };

    input.addEventListener('input', (event) => {
        // Let deleting a separator work instead of re-adding it at once.
        if (event.inputType?.startsWith('delete')) {
            return;
        }

        const atEnd = input.selectionStart === input.value.length;
        input.value = format(input.value);

        if (atEnd) {
            input.setSelectionRange(input.value.length, input.value.length);
        }
    });

    input.addEventListener('focus', () => {
        if (!input.value) {
            input.value = '+7 ';
        }
    });

    input.addEventListener('blur', () => {
        input.value = input.value.trim() === '+7' ? '' : format(input.value);
    });
}

/**
 * Inline messages for empty and malformed fields. Messages come from the
 * field's data-msg-required / data-msg-invalid; the server checks again.
 *
 * @param {HTMLFormElement} form
 */
function initValidation(form) {
    const problem = (field) => {
        if (field.closest('[hidden]')) {
            return null;
        }

        const value =
            field.type === 'checkbox' ? field.checked : field.value.trim();

        if (field.required && !value) {
            return field.dataset.msgRequired ?? field.validationMessage;
        }

        if (
            field.name === 'phone' &&
            value &&
            value.replace(/\D/g, '').length !== 11
        ) {
            return field.dataset.msgInvalid;
        }

        if (
            field.type === 'email' &&
            value &&
            !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
        ) {
            return field.dataset.msgInvalid;
        }

        return null;
    };

    const check = (field) => {
        const message = problem(field);

        if (message) {
            showError(field, message);
        } else {
            clearError(field);
        }

        return !message;
    };

    form.addEventListener('submit', (event) => {
        const fields = [
            ...form.querySelectorAll(
                'input[required], input[type="email"], input[name="phone"]',
            ),
        ];
        const invalid = fields.filter((field) => !check(field));

        if (invalid.length) {
            event.preventDefault();
            invalid[0].focus({ preventScroll: true });
            invalid[0]
                .closest('[data-field]')
                .scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    // Re-check a field once it has shown an error, so the message goes away
    // as soon as it is fixed; new fields are only checked on leaving them.
    form.addEventListener('input', (event) => {
        if (event.target.getAttribute('aria-invalid') === 'true') {
            check(event.target);
        }
    });

    form.addEventListener('change', (event) => {
        if (event.target.type === 'checkbox' && event.target.required) {
            check(event.target);
        }
    });

    form.addEventListener(
        'blur',
        (event) => {
            if (
                event.target.matches?.('input') &&
                event.target.value.trim() &&
                event.target.type !== 'checkbox'
            ) {
                check(event.target);
            }
        },
        true,
    );
}

function errorElement(field) {
    return field.closest('[data-field]')?.querySelector('.float-field__error');
}

function showError(field, message) {
    const error = errorElement(field);

    field.setAttribute('aria-invalid', 'true');

    if (error) {
        error.textContent = message;
        error.hidden = false;
        field.setAttribute('aria-describedby', error.id);
    }
}

function clearError(field) {
    if (!field) {
        return;
    }

    field.removeAttribute('aria-invalid');

    const error = errorElement(field);

    if (error) {
        error.hidden = true;
    }
}
