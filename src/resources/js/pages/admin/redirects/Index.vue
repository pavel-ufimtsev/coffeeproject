<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import NativeSelect from '@/components/admin/catalog/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, edit, index } from '@/routes/admin/redirects';
import type { Paginated } from '@/types';

type RedirectRow = {
    id: number;
    from_path: string;
    to_path: string;
    status_code: number;
    hits: number;
    last_hit_at: string | null;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Redirects', href: index() }],
    },
});

const props = defineProps<{
    redirects: Paginated<RedirectRow>;
    filters: { search: string; sort: string };
    total: number;
}>();

const search = ref(props.filters.search);
const sort = ref(props.filters.sort);

const applyFilters = () => {
    router.get(
        index.url(),
        { search: search.value || undefined, sort: sort.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(search, useDebounceFn(applyFilters, 300));
watch(sort, applyFilters);

const previousPage = computed(() => props.redirects.links.at(0)?.url ?? null);
const nextPage = computed(() => props.redirects.links.at(-1)?.url ?? null);
</script>

<template>
    <Head :title="$t('Redirects')" />

    <div class="space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-0.5">
                <h2 class="text-xl font-semibold tracking-tight">
                    {{ $t('Redirects') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t(
                            ':count old addresses lead to new pages. Moved pages are added automatically.',
                            { count: total },
                        )
                    }}
                </p>
            </div>
            <Button as-child>
                <Link :href="create()"><Plus /> {{ $t('New redirect') }}</Link>
            </Button>
        </div>

        <div class="flex flex-wrap gap-3">
            <Input
                v-model="search"
                type="search"
                :placeholder="$t('Search addresses')"
                class="max-w-xs"
                :aria-label="$t('Search addresses')"
            />
            <NativeSelect v-model="sort" class="w-52" :aria-label="$t('Sort')">
                <option value="hits">{{ $t('Most used first') }}</option>
                <option value="recent">{{ $t('Newest first') }}</option>
                <option value="unused">{{ $t('Never used') }}</option>
            </NativeSelect>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">
                            {{ $t('Old address') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ $t('New address') }}
                        </th>
                        <th class="px-4 py-3 text-right font-medium">
                            {{ $t('Visits') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ $t('Last visit') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="redirect in redirects.data"
                        :key="redirect.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="max-w-md px-4 py-2 break-all">
                            <Link
                                :href="edit(redirect.id)"
                                class="hover:underline"
                            >
                                {{ redirect.from_path }}
                            </Link>
                        </td>
                        <td
                            class="max-w-md px-4 py-2 break-all text-muted-foreground"
                        >
                            {{ redirect.to_path }}
                            <span v-if="redirect.status_code !== 301">
                                ({{ redirect.status_code }})
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ redirect.hits }}
                        </td>
                        <td
                            class="px-4 py-2 text-muted-foreground tabular-nums"
                        >
                            {{ redirect.last_hit_at ?? '-' }}
                        </td>
                    </tr>
                    <tr v-if="redirects.data.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            {{ $t('No redirects match.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="redirects.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span>
                {{
                    $t(':from–:to of :total', {
                        from: redirects.from ?? 0,
                        to: redirects.to ?? 0,
                        total: redirects.total,
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
                    <Link :href="previousPage" preserve-scroll>{{
                        $t('Previous')
                    }}</Link>
                </Button>
                <Button v-if="nextPage" variant="outline" size="sm" as-child>
                    <Link :href="nextPage" preserve-scroll>{{
                        $t('Next')
                    }}</Link>
                </Button>
            </div>
        </div>
    </div>
</template>
