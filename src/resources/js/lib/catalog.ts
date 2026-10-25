const TRANSLIT: Record<string, string> = {
    а: 'a',
    б: 'b',
    в: 'v',
    г: 'g',
    д: 'd',
    е: 'e',
    ё: 'e',
    ж: 'zh',
    з: 'z',
    и: 'i',
    й: 'y',
    к: 'k',
    л: 'l',
    м: 'm',
    н: 'n',
    о: 'o',
    п: 'p',
    р: 'r',
    с: 's',
    т: 't',
    у: 'u',
    ф: 'f',
    х: 'kh',
    ц: 'ts',
    ч: 'ch',
    ш: 'sh',
    щ: 'shch',
    ъ: '',
    ы: 'y',
    ь: '',
    э: 'e',
    ю: 'yu',
    я: 'ya',
};

/**
 * Make a URL slug from a name, transliterating Russian: "Кения Волк" → "keniya-volk".
 */
export function slugify(name: string): string {
    return Array.from(name.toLowerCase())
        .map((char) => TRANSLIT[char] ?? char)
        .join('')
        .normalize('NFKD')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

const rub = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 });

/**
 * Format a price in roubles: 1 177 ₽.
 */
export function formatPrice(price: number | string | null | undefined): string {
    if (price === null || price === undefined || price === '') {
        return '-';
    }

    return `${rub.format(Number(price))} ₽`;
}
