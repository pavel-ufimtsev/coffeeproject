/**
 * The coffee quiz one question at a time: picking an answer moves on to the
 * next question, and the last answer shows the results. Without JavaScript
 * all questions show at once with a submit button.
 *
 * @param {HTMLFormElement} form
 */
export function init(form) {
    const steps = [...form.querySelectorAll('[data-quiz-step]')];
    const progress = form.querySelector('[data-quiz-progress]');
    const bar = progress.querySelector('span');
    const submit = form.querySelector('[data-quiz-submit]');
    let current = 0;

    const show = (index, focus = true) => {
        current = index;
        steps.forEach((step, i) => {
            step.hidden = i !== index;
        });
        bar.style.inlineSize = `${(index / steps.length) * 100}%`;

        if (focus) {
            const picked = steps[index].querySelector('input:checked');
            (picked ?? steps[index].querySelector('input')).focus({
                preventScroll: true,
            });
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    form.classList.add('is-stepped');
    progress.hidden = false;
    submit.hidden = true;

    for (const nav of form.querySelectorAll('[data-quiz-nav]')) {
        nav.hidden = false;
    }

    const next = () => {
        // A short pause lets the choice register visually before moving on.
        window.setTimeout(() => {
            if (current < steps.length - 1) {
                show(current + 1);
            } else {
                bar.style.inlineSize = '100%';
                form.requestSubmit();
            }
        }, 180);
    };

    // Move on after a real click (also on the answer already picked, after
    // going back) or Enter; arrow keys only browse the answers.
    form.addEventListener('click', (event) => {
        if (event.target.matches('input[type="radio"]') && event.detail > 0) {
            next();
        }
    });

    form.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.matches('input[type="radio"]')) {
            event.preventDefault();
            event.target.checked = true;
            next();
        }
    });

    form.addEventListener('click', (event) => {
        if (event.target.closest('[data-quiz-back]')) {
            show(Math.max(0, current - 1));
        }
    });

    show(0, false);
}
