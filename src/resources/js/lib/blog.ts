/**
 * Helpers for the blog admin: JSON calls outside Inertia and a line diff
 * for comparing revisions.
 */

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Call an admin JSON endpoint. Throws with the server's message on failure.
 */
export async function requestJson<T>(
    url: string,
    init: { method?: string; body?: unknown } = {},
): Promise<T> {
    const isForm = init.body instanceof FormData;
    const response = await fetch(url, {
        method: init.method ?? 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
            ...(init.body && !isForm
                ? { 'Content-Type': 'application/json' }
                : {}),
        },
        body: isForm
            ? (init.body as FormData)
            : init.body
              ? JSON.stringify(init.body)
              : undefined,
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const errors = data.errors
            ? (Object.values(data.errors).flat() as string[])
            : [];
        throw new Error(errors[0] ?? data.message ?? response.statusText);
    }

    return data as T;
}

export type DiffLine = { type: 'same' | 'added' | 'removed'; text: string };

/**
 * Line-by-line diff (longest common subsequence). Unchanged runs longer
 * than a few lines are folded so the changes stand out.
 */
export function diffLines(before: string, after: string): DiffLine[] {
    const a = before.split('\n');
    const b = after.split('\n');
    const n = a.length;
    const m = b.length;

    // Very long texts: fall back to "everything changed" rather than a huge table.
    if (n * m > 4_000_000) {
        return [
            ...a.map((text) => ({ type: 'removed' as const, text })),
            ...b.map((text) => ({ type: 'added' as const, text })),
        ];
    }

    const lcs: Uint32Array[] = Array.from(
        { length: n + 1 },
        () => new Uint32Array(m + 1),
    );

    for (let i = n - 1; i >= 0; i--) {
        for (let j = m - 1; j >= 0; j--) {
            lcs[i][j] =
                a[i] === b[j]
                    ? lcs[i + 1][j + 1] + 1
                    : Math.max(lcs[i + 1][j], lcs[i][j + 1]);
        }
    }

    const lines: DiffLine[] = [];
    let i = 0;
    let j = 0;

    while (i < n && j < m) {
        if (a[i] === b[j]) {
            lines.push({ type: 'same', text: a[i] });
            i++;
            j++;
        } else if (lcs[i + 1][j] >= lcs[i][j + 1]) {
            lines.push({ type: 'removed', text: a[i++] });
        } else {
            lines.push({ type: 'added', text: b[j++] });
        }
    }

    while (i < n) {
        lines.push({ type: 'removed', text: a[i++] });
    }

    while (j < m) {
        lines.push({ type: 'added', text: b[j++] });
    }

    return lines;
}

export const triageLabels: Record<string, string> = {
    publish: 'Publish',
    refresh: 'Refresh',
    merge: 'Merge',
    retire: 'Retire',
};
