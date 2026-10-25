import { usePage } from '@inertiajs/vue3';
import type { App } from 'vue';

export type Replacements = Record<string, string | number>;

/**
 * Translate an English string the same way Laravel's __() does: look it up
 * in the JSON translations shared by the server and fill in :placeholders.
 */
export function translate(
    translations: Record<string, string> | undefined,
    key: string,
    replacements: Replacements = {},
): string {
    let line = translations?.[key] ?? key;

    for (const [name, value] of Object.entries(replacements)) {
        line = line.replaceAll(`:${name}`, String(value));
    }

    return line;
}

/**
 * Translation function for <script setup> code.
 */
export function useTrans() {
    const page = usePage();

    return (key: string, replacements?: Replacements) =>
        translate(page.props.translations, key, replacements);
}

/**
 * Make $t() available in every component template.
 */
export function installTranslations(app: App): void {
    app.mixin({
        methods: {
            $t(key: string, replacements?: Replacements): string {
                return translate(
                    this.$page.props.translations,
                    key,
                    replacements,
                );
            },
        },
    });
}
