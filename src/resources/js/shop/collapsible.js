/**
 * Fold long text (the roaster's notes) to a few paragraphs with a button to
 * read it all. Short texts are left alone; without JavaScript all of it shows.
 *
 * @param {HTMLElement} section
 */
export function init(section) {
    const body = section.querySelector('[data-collapsible-body]');

    // Only fold when there is clearly more to read than the folded height.
    if (body.scrollHeight < 420) {
        return;
    }

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'button button--secondary product-notes__toggle';
    button.setAttribute('aria-controls', body.id);

    const set = (collapsed) => {
        section.classList.toggle('is-collapsed', collapsed);
        button.setAttribute('aria-expanded', String(!collapsed));
        button.textContent = collapsed
            ? section.dataset.moreLabel
            : section.dataset.lessLabel;
    };

    button.addEventListener('click', () => {
        const collapsing = !section.classList.contains('is-collapsed');
        set(collapsing);

        if (collapsing) {
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    set(true);
    body.after(button);
}
