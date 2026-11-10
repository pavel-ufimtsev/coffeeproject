<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { History } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { diffLines, requestJson } from '@/lib/blog';
import type { DiffLine } from '@/lib/blog';
import {
    restore as restoreRoute,
    show as showRoute,
} from '@/routes/admin/blog/posts/revisions';
import type { BlogRevision } from '@/types';

/**
 * The post's revisions. Compare one with the current text and bring it back.
 */
const props = defineProps<{
    postId: number;
    revisions: BlogRevision[];
    current: { title: string; excerpt: string | null; body: string };
}>();

type FullRevision = BlogRevision & {
    excerpt: string | null;
    body: string;
    seo_title: string | null;
    seo_description: string | null;
};

const open = ref(false);
const loading = ref(false);
const restoring = ref(false);
const selected = ref<FullRevision | null>(null);
const error = ref<string>();
const showAll = ref(false);

const visible = computed(() =>
    showAll.value ? props.revisions : props.revisions.slice(0, 8),
);

const compare = async (revision: BlogRevision) => {
    open.value = true;
    loading.value = true;
    selected.value = null;
    error.value = undefined;

    try {
        selected.value = await requestJson<FullRevision>(
            showRoute.url({ post: props.postId, revision: revision.id }),
        );
    } catch (e) {
        error.value = e instanceof Error ? e.message : String(e);
    } finally {
        loading.value = false;
    }
};

/** Lines of the revision (old) against the current text (new), with long unchanged runs folded. */
const diff = computed<(DiffLine | { type: 'fold'; count: number })[]>(() => {
    if (!selected.value) {
        return [];
    }

    const toText = (r: {
        title: string;
        excerpt: string | null;
        body: string;
    }) => `# ${r.title}\n\n${r.excerpt ?? ''}\n\n${r.body}`;
    const lines = diffLines(toText(selected.value), toText(props.current));
    const result: (DiffLine | { type: 'fold'; count: number })[] = [];
    const context = 2;

    lines.forEach((line, i) => {
        if (line.type !== 'same') {
            result.push(line);

            return;
        }

        const near = lines
            .slice(Math.max(0, i - context), i + context + 1)
            .some((l) => l.type !== 'same');

        if (near) {
            result.push(line);
        } else if (result.at(-1)?.type === 'fold') {
            (result.at(-1) as { count: number }).count++;
        } else {
            result.push({ type: 'fold', count: 1 });
        }
    });

    return result;
});

const unchanged = computed(
    () => selected.value && diff.value.every((line) => line.type === 'fold'),
);

const restore = () => {
    if (!selected.value) {
        return;
    }

    router.post(
        restoreRoute.url({ post: props.postId, revision: selected.value.id }),
        {},
        {
            preserveScroll: true,
            onStart: () => (restoring.value = true),
            onFinish: () => (restoring.value = false),
            onSuccess: () => (open.value = false),
        },
    );
};
</script>

<template>
    <div class="space-y-2">
        <ul class="divide-y rounded-lg border text-sm">
            <li v-for="revision in visible" :key="revision.id">
                <button
                    type="button"
                    class="grid w-full gap-0.5 px-3 py-2 text-left hover:bg-muted/50"
                    @click="compare(revision)"
                >
                    <span class="flex items-center justify-between gap-2">
                        <span class="truncate font-medium">{{
                            revision.author
                        }}</span>
                        <span class="shrink-0 text-xs text-muted-foreground">
                            {{ revision.created_at }}
                        </span>
                    </span>
                    <span
                        v-if="revision.note"
                        class="line-clamp-2 text-muted-foreground"
                    >
                        {{ revision.note }}
                    </span>
                </button>
            </li>
            <li
                v-if="!revisions.length"
                class="px-3 py-3 text-muted-foreground"
            >
                {{ $t('No revisions yet.') }}
            </li>
        </ul>
        <Button
            v-if="revisions.length > 8 && !showAll"
            variant="ghost"
            size="sm"
            @click="showAll = true"
        >
            {{ $t('Show all :count', { count: revisions.length }) }}
        </Button>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <History class="size-4" />
                        {{
                            $t('Revision from :date', {
                                date: selected?.created_at ?? '',
                            })
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        <span class="block">
                            {{ selected?.author
                            }}<template v-if="selected?.note">
                                — {{ selected.note }}</template
                            >
                        </span>
                        <span class="mt-1 block">
                            {{
                                $t(
                                    'Red lines are in this revision only, green lines are in the current text.',
                                )
                            }}
                        </span>
                    </DialogDescription>
                </DialogHeader>
                <p v-if="loading" class="text-sm text-muted-foreground">
                    {{ $t('Loading…') }}
                </p>
                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
                <p v-if="unchanged" class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'This revision has the same text as the current one.',
                        )
                    }}
                </p>
                <div
                    v-else-if="selected"
                    class="max-h-[60vh] overflow-auto rounded-md border font-mono text-xs leading-relaxed"
                >
                    <template v-for="(line, i) in diff" :key="i">
                        <div
                            v-if="line.type === 'fold'"
                            class="bg-muted/50 px-3 py-1 text-muted-foreground"
                        >
                            {{
                                $t('… :count unchanged lines', {
                                    count: line.count,
                                })
                            }}
                        </div>
                        <div
                            v-else
                            class="px-3 break-words whitespace-pre-wrap"
                            :class="{
                                'bg-red-50 text-red-900 dark:bg-red-950 dark:text-red-200':
                                    line.type === 'removed',
                                'bg-emerald-50 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200':
                                    line.type === 'added',
                            }"
                        >
                            <span class="opacity-50 select-none">{{
                                line.type === 'removed'
                                    ? '− '
                                    : line.type === 'added'
                                      ? '+ '
                                      : '  '
                            }}</span
                            >{{ line.text || ' ' }}
                        </div>
                    </template>
                </div>
                <DialogFooter>
                    <Button
                        :disabled="!selected || restoring || !!unchanged"
                        @click="restore"
                    >
                        {{ $t('Restore this revision') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
