<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BadgeCheck, Check, ExternalLink, EyeOff, Trash2 } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/lib/i18n';
import { destroy, index, update } from '@/routes/admin/reviews';
import type { Paginated } from '@/types';

type Status = 'pending' | 'approved' | 'rejected';

type ReviewRow = {
    id: number;
    name: string;
    email: string;
    rating: number;
    body: string;
    brew_method: string | null;
    is_verified_buyer: boolean;
    status: Status;
    created_at: string;
    product: { name: string; url: string };
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Reviews', href: index() }],
    },
});

const props = defineProps<{
    reviews: Paginated<ReviewRow>;
    filters: { status: Status; search: string };
    counts: Record<Status, number>;
}>();

const tabs: { value: Status; label: string }[] = [
    { value: 'pending', label: 'Waiting for approval' },
    { value: 'approved', label: 'Published reviews' },
    { value: 'rejected', label: 'Rejected reviews' },
];

const t = useTrans();
const search = ref(props.filters.search);

const go = (status: Status) => {
    router.get(
        index.url(),
        { status, search: search.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(
    search,
    useDebounceFn(() => go(props.filters.status), 300),
);

const setStatus = (review: ReviewRow, status: Status) => {
    router.patch(update.url(review.id), { status }, { preserveScroll: true });
};

const remove = (review: ReviewRow) => {
    if (window.confirm(t('Delete this review for good?'))) {
        router.delete(destroy.url(review.id), { preserveScroll: true });
    }
};

const previousPage = computed(() => props.reviews.links.at(0)?.url ?? null);
const nextPage = computed(() => props.reviews.links.at(-1)?.url ?? null);
</script>

<template>
    <Head :title="$t('Reviews')" />

    <div class="space-y-6 p-4">
        <div class="space-y-0.5">
            <h2 class="text-xl font-semibold tracking-tight">
                {{ $t('Reviews') }}
            </h2>
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Reviews appear on product pages once you publish them.',
                    )
                }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex rounded-lg border p-0.5" role="tablist">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    role="tab"
                    :aria-selected="filters.status === tab.value"
                    class="rounded-md px-3 py-1.5 text-sm"
                    :class="
                        filters.status === tab.value
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="go(tab.value)"
                >
                    {{ $t(tab.label) }}
                    <span class="ml-1 tabular-nums opacity-70">{{
                        counts[tab.value]
                    }}</span>
                </button>
            </div>
            <Input
                v-model="search"
                type="search"
                :placeholder="$t('Search reviews')"
                class="max-w-xs"
                :aria-label="$t('Search reviews')"
            />
        </div>

        <ul class="space-y-3">
            <li
                v-for="review in reviews.data"
                :key="review.id"
                class="space-y-3 rounded-xl border p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <a
                            :href="review.product.url"
                            target="_blank"
                            class="inline-flex items-center gap-1 font-medium hover:underline"
                        >
                            {{ review.product.name }}
                            <ExternalLink class="size-3.5" />
                        </a>
                        <p class="text-sm text-muted-foreground">
                            <span
                                class="text-amber-500"
                                :aria-label="`${review.rating}/5`"
                                >{{ '★'.repeat(review.rating)
                                }}<span class="text-muted-foreground/40">{{
                                    '★'.repeat(5 - review.rating)
                                }}</span></span
                            >
                            · {{ review.name }} · {{ review.email }} ·
                            {{ review.created_at }}
                            <template v-if="review.brew_method">
                                · {{ review.brew_method }}</template
                            >
                        </p>
                    </div>
                    <Badge
                        v-if="review.is_verified_buyer"
                        variant="secondary"
                        class="gap-1"
                    >
                        <BadgeCheck class="size-3.5" />
                        {{ $t('Bought this coffee') }}
                    </Badge>
                </div>

                <p class="text-sm whitespace-pre-line">{{ review.body }}</p>

                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="review.status !== 'approved'"
                        size="sm"
                        @click="setStatus(review, 'approved')"
                    >
                        <Check /> {{ $t('Publish') }}
                    </Button>
                    <Button
                        v-if="review.status !== 'rejected'"
                        size="sm"
                        variant="outline"
                        @click="setStatus(review, 'rejected')"
                    >
                        <EyeOff />
                        {{
                            review.status === 'approved'
                                ? $t('Unpublish')
                                : $t('Reject')
                        }}
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        class="text-destructive"
                        @click="remove(review)"
                    >
                        <Trash2 /> {{ $t('Delete') }}
                    </Button>
                </div>
            </li>
            <li
                v-if="reviews.data.length === 0"
                class="rounded-xl border px-4 py-8 text-center text-muted-foreground"
            >
                {{ $t('No reviews here.') }}
            </li>
        </ul>

        <div
            v-if="reviews.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span>
                {{
                    $t(':from–:to of :total', {
                        from: reviews.from ?? 0,
                        to: reviews.to ?? 0,
                        total: reviews.total,
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
