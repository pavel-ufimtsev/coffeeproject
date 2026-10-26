/**
 * Format a Russian number as it is typed: "+7 909 701-90-07". A leading 8
 * or 7 is the country code; anything else is taken as the number itself.
 *
 * @param {HTMLInputElement|null} input
 */
export function initPhone(input) {
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
