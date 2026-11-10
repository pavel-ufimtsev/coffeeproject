<script setup lang="ts">
import { Bot } from '@lucide/vue';
import { triageLabels } from '@/lib/blog';
import type { BlogTriage } from '@/types';

defineProps<{
    triage: BlogTriage;
}>();

const tone: Record<string, string> = {
    publish:
        'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
    refresh:
        'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300',
    merge: 'border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-300',
    retire: 'border-stone-200 bg-stone-100 text-stone-700 dark:border-stone-800 dark:bg-stone-900 dark:text-stone-300',
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="tone[triage.decision ?? ''] ?? 'border-border'"
    >
        <Bot class="size-3" aria-hidden="true" />
        {{ triage.decision ? $t(triageLabels[triage.decision]) : '—' }}
        <span v-if="triage.confidence !== null" class="opacity-70">
            {{ triage.confidence }}%
        </span>
    </span>
</template>
