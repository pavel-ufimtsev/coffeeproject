<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, ImageOff, Plus } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import BlogTabs from '@/components/admin/blog/BlogTabs.vue';
import PostStatusBadge from '@/components/admin/blog/PostStatusBadge.vue';
import RetireDialog from '@/components/admin/blog/RetireDialog.vue';
import TriageBadge from '@/components/admin/blog/TriageBadge.vue';
import CheckboxField from '@/components/admin/catalog/CheckboxField.vue';
import NativeSelect from '@/components/admin/catalog/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/lib/i18n';
import { triageLabels } from '@/lib/blog';
import { bulk, create, edit, index, restore } from '@/routes/admin/blog/posts';
import type { BlogPostListItem, Option, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Blog', href: index() }],
    },
});

const props = defineProps<{
    posts: Paginated<BlogPostListItem>;
    filters: {
        search: string;
        status: string;
        category: string;
        triage: string;
        source: string;
        attention: boolean;
        trashed: boolean;
    };
    statuses: Option[];
    categories: Option[];
    counts: Record<string, number>;
}>();

const t = useTrans();
const search = ref(props.filters.search);
const status = ref(props.filters.status);
const category = ref(props.filters.category);
const triage = ref(props.filters.triage);
const source = ref(props.filters.source);
const attention = ref(props.filters.attention);
const trashed = ref(props.filters.trashed);

const applyFilters = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            category: category.value || undefined,
            triage: triage.value || undefined,
            source: source.value || undefined,
            attention: attention.value ? 1 : undefined,
            trashed: trashed.value ? 1 : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(search, useDebounceFn(applyFilters, 300));
watch([status, category, triage, source, attention, trashed], applyFilters);

const previousPage = computed(() => props.posts.links.at(0)?.url ?? null);
const nextPage = computed(() => props.posts.links.at(-1)?.url ?? null);

const summary = computed(() =>
    [
        t(':count published', { count: props.counts.published ?? 0 }),
        t(':count drafts', { count: props.counts.draft ?? 0 }),
        props.counts.scheduled
            ? t(':count scheduled', { count: props.counts.scheduled })
            : null,
        t(':count retired', { count: props.counts.archived ?? 0 }),
    ]
        .filter(Boolean)
        .join(' · '),
);

// Bulk actions.
const selected = ref<number[]>([]);
watch(
    () => props.posts.data,
    () => (selected.value = []),
);
const selectable = computed(() =>
    props.posts.data.filter((post) => !post.deleted),
);
const allSelected = computed(
    () =>
        selectable.value.length > 0 &&
        selected.value.length === selectable.value.length,
);
const toggleAll = (checked: boolean) => {
    selected.value = checked ? selectable.value.map((post) => post.id) : [];
};
const toggle = (id: number, checked: boolean) => {
    selected.value = checked
        ? [...selected.value, id]
        : selected.value.filter((value) => value !== id);
};

const processing = ref(false);
const retireOpen = ref(false);
const bulkError = ref<string>();

const runBulk = (action: 'publish' | 'unpublish' | 'archive', extra = {}) => {
    router.post(
        bulk.url(),
        { action, ids: selected.value, ...extra },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onSuccess: () => (retireOpen.value = false),
            onError: (errors) => (bulkError.value = Object.values(errors)[0]),
        },
    );
};
</script>

<template>
    <Head :title="$t('Blog')" />

    <div class="space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-0.5">
                <h2 class="text-xl font-semibold tracking-tight">
                    {{ $t('Blog') }}
                </h2>
                <p class="text-sm text-muted-foreground">{{ summary }}</p>
            </div>
            <Button as-child>
                <Link :href="create()"><Plus /> {{ $t('New post') }}</Link>
            </Button>
        </div>

        <BlogTabs />

        <div
            class="grid items-center gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5"
        >
            <Input
                v-model="search"
                type="search"
                :placeholder="$t('Title or address')"
                :aria-label="$t('Search posts')"
            />
            <NativeSelect v-model="status" :aria-label="$t('Status')">
                <option value="">{{ $t('All statuses') }}</option>
                <option
                    v-for="option in statuses"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </NativeSelect>
            <NativeSelect v-model="category" :aria-label="$t('Section')">
                <option value="">{{ $t('All sections') }}</option>
                <option
                    v-for="option in categories"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
                <option value="none">{{ $t('No section') }}</option>
            </NativeSelect>
            <NativeSelect v-model="triage" :aria-label="$t('Agent verdict')">
                <option value="">{{ $t('Any verdict') }}</option>
                <option
                    v-for="(label, value) in triageLabels"
                    :key="value"
                    :value="value"
                >
                    {{ $t(label) }}
                </option>
                <option value="none">{{ $t('Not reviewed') }}</option>
            </NativeSelect>
            <NativeSelect v-model="source" :aria-label="$t('Source')">
                <option value="">{{ $t('Any source') }}</option>
                <option value="archive">{{ $t('Old site') }}</option>
                <option value="admin">{{ $t('Written here') }}</option>
                <option value="agent">{{ $t('Written by the agent') }}</option>
            </NativeSelect>
            <div class="flex flex-wrap gap-x-6 gap-y-2 sm:col-span-full">
                <CheckboxField
                    v-model="attention"
                    :label="
                        $t('Needs a human (:count)', {
                            count: counts.attention,
                        })
                    "
                />
                <CheckboxField
                    v-if="counts.trashed"
                    v-model="trashed"
                    :label="$t('Bin (:count)', { count: counts.trashed })"
                />
            </div>
        </div>

        <div
            v-if="selected.length"
            class="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/40 px-3 py-2 text-sm"
        >
            <span class="mr-2 font-medium">
                {{ $t(':count selected', { count: selected.length }) }}
            </span>
            <Button
                size="sm"
                :disabled="processing"
                @click="runBulk('publish')"
            >
                {{ $t('Publish') }}
            </Button>
            <Button
                size="sm"
                variant="outline"
                :disabled="processing"
                @click="runBulk('unpublish')"
            >
                {{ $t('Back to drafts') }}
            </Button>
            <Button
                size="sm"
                variant="outline"
                :disabled="processing"
                @click="retireOpen = true"
            >
                {{ $t('Retire…') }}
            </Button>
            <Button size="sm" variant="ghost" @click="selected = []">
                {{ $t('Clear') }}
            </Button>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="w-10 px-3 py-3">
                            <Checkbox
                                :model-value="allSelected"
                                :aria-label="$t('Select all')"
                                @update:model-value="toggleAll($event === true)"
                            />
                        </th>
                        <th class="px-3 py-3 font-medium">{{ $t('Post') }}</th>
                        <th class="px-3 py-3 font-medium">
                            {{ $t('Section') }}
                        </th>
                        <th class="px-3 py-3 font-medium">
                            {{ $t('Status') }}
                        </th>
                        <th class="px-3 py-3 font-medium">
                            {{ $t('Agent verdict') }}
                        </th>
                        <th class="px-3 py-3 text-right font-medium">
                            {{ $t('Date') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="post in posts.data"
                        :key="post.id"
                        class="border-t align-top hover:bg-muted/30"
                    >
                        <td class="px-3 py-3">
                            <Checkbox
                                v-if="!post.deleted"
                                :model-value="selected.includes(post.id)"
                                :aria-label="
                                    $t('Select :title', { title: post.title })
                                "
                                @update:model-value="
                                    toggle(post.id, $event === true)
                                "
                            />
                        </td>
                        <td class="max-w-md px-3 py-3">
                            <Link
                                v-if="!post.deleted"
                                :href="edit(post.id)"
                                class="font-medium hover:underline"
                            >
                                {{ post.title }}
                            </Link>
                            <span v-else class="font-medium">{{
                                post.title
                            }}</span>
                            <p
                                class="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground"
                            >
                                <span>/blog/{{ post.slug }}</span>
                                <a
                                    v-if="post.is_visible"
                                    :href="`/blog/${post.slug}`"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center hover:text-foreground"
                                    :aria-label="$t('Open on the site')"
                                >
                                    <ExternalLink class="size-3" />
                                </a>
                                <span
                                    v-if="post.images_missing"
                                    class="inline-flex items-center gap-1 text-amber-700 dark:text-amber-400"
                                >
                                    <ImageOff class="size-3" />
                                    {{
                                        $t(':count images lost', {
                                            count: post.images_missing,
                                        })
                                    }}
                                </span>
                            </p>
                            <Button
                                v-if="post.deleted"
                                size="sm"
                                variant="outline"
                                class="mt-2"
                                @click="router.post(restore.url(post.id))"
                            >
                                {{ $t('Restore as draft') }}
                            </Button>
                        </td>
                        <td class="px-3 py-3 text-muted-foreground">
                            {{ post.category ?? '—' }}
                        </td>
                        <td class="px-3 py-3">
                            <PostStatusBadge
                                :status="post.status"
                                :label="post.status_label"
                            />
                        </td>
                        <td class="max-w-xs px-3 py-3">
                            <template v-if="post.triage">
                                <TriageBadge :triage="post.triage" />
                                <p
                                    v-if="post.triage.reason"
                                    class="mt-1 line-clamp-2 text-xs text-muted-foreground"
                                    :title="post.triage.reason"
                                >
                                    {{ post.triage.reason }}
                                </p>
                            </template>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td
                            class="px-3 py-3 text-right whitespace-nowrap text-muted-foreground tabular-nums"
                        >
                            {{ post.published_at ?? '—' }}
                        </td>
                    </tr>
                    <tr v-if="posts.data.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            {{ $t('No posts match these filters.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="posts.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span>
                {{
                    $t(':from–:to of :total', {
                        from: posts.from ?? 0,
                        to: posts.to ?? 0,
                        total: posts.total,
                    })
                }}
            </span>
            <div class="flex gap-2">
                <Button
                    v-if="previousPage"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="previousPage" preserve-scroll>
                        {{ $t('Previous') }}
                    </Link>
                </Button>
                <Button v-if="nextPage" variant="outline" size="sm" as-child>
                    <Link :href="nextPage" preserve-scroll>
                        {{ $t('Next') }}
                    </Link>
                </Button>
            </div>
        </div>

        <RetireDialog
            v-model:open="retireOpen"
            suggested="/blog"
            :title="$t('Retire :count posts?', { count: selected.length })"
            :processing="processing"
            :error="bulkError"
            @confirm="runBulk('archive', $event)"
        />
    </div>
</template>
