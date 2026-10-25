<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Link2, Search } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { formatPrice } from '@/lib/catalog';
import { store as link } from '@/routes/admin/products/retailcrm';
import { search as searchRoute } from '@/routes/admin/retailcrm';
import type { CrmProduct } from '@/types';

/**
 * Find a retailCRM product and choose which of its offers the site sells.
 * Linking replaces the product's sizes with the chosen offers.
 */
const props = defineProps<{
    productId: number;
    productName: string;
    linkedOfferIds: number[];
    linked: boolean;
}>();

const open = ref(false);
const query = ref('');
const results = ref<CrmProduct[]>([]);
const searching = ref(false);
const searchError = ref<string>();
const chosen = ref<CrmProduct | null>(null);
const offerIds = ref<number[]>([]);
const errors = ref<Record<string, string>>({});
const processing = ref(false);

const search = async () => {
    const q = query.value.trim();

    if (q.length < 2) {
        results.value = [];

        return;
    }

    searching.value = true;
    searchError.value = undefined;

    try {
        const response = await fetch(searchRoute.url({ query: { q } }), {
            headers: { Accept: 'application/json' },
        });
        const body = await response.json();

        if (!response.ok) {
            throw new Error(body.message ?? response.statusText);
        }

        results.value = body.products;
    } catch (e) {
        searchError.value = e instanceof Error ? e.message : String(e);
    } finally {
        searching.value = false;
    }
};

watch(query, useDebounceFn(search, 350));

watch(open, (isOpen) => {
    if (isOpen && !query.value) {
        query.value = props.productName;
    }
});

const choose = (product: CrmProduct) => {
    chosen.value = product;
    const linked = product.offers.filter((offer) =>
        props.linkedOfferIds.includes(offer.id),
    );
    offerIds.value = (
        linked.length ? linked : product.offers.filter((offer) => offer.active)
    ).map((offer) => offer.id);
};

const toggle = (id: number, checked: boolean) => {
    offerIds.value = checked
        ? [...offerIds.value, id]
        : offerIds.value.filter((offerId) => offerId !== id);
};

const submit = () => {
    if (!chosen.value) {
        return;
    }

    router.post(
        link.url(props.productId),
        { retailcrm_product_id: chosen.value.id, offer_ids: offerIds.value },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onSuccess: () => {
                open.value = false;
                chosen.value = null;
            },
            onError: (e) => (errors.value = e),
            onFinish: () => (processing.value = false),
        },
    );
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button type="button" :variant="linked ? 'outline' : 'default'">
                <Link2 />
                {{
                    linked
                        ? $t('Change retailCRM link')
                        : $t('Link to retailCRM')
                }}
            </Button>
        </DialogTrigger>
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ $t('Link to retailCRM') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Find the product in retailCRM and tick the offers to sell. They replace the current sizes; prices are fetched right away.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <template v-if="!chosen">
                <div class="relative">
                    <Search
                        class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                    />
                    <Input
                        v-model="query"
                        type="search"
                        class="pl-9"
                        :placeholder="$t('Name, article or retailCRM ID')"
                        :aria-label="$t('Search retailCRM')"
                    />
                </div>
                <InputError :message="searchError" />
                <p v-if="searching" class="text-sm text-muted-foreground">
                    {{ $t('Searching…') }}
                </p>
                <ul
                    v-else-if="results.length"
                    class="divide-y rounded-lg border"
                >
                    <li v-for="product in results" :key="product.id">
                        <button
                            type="button"
                            class="w-full px-3 py-2 text-left text-sm hover:bg-muted/50"
                            @click="choose(product)"
                        >
                            <span class="font-medium">{{ product.name }}</span>
                            <span class="text-muted-foreground">
                                · #{{ product.id }}
                                <template v-if="product.article">
                                    · {{ product.article }}
                                </template>
                                ·
                                {{
                                    $t(':count offers', {
                                        count: product.offers.length,
                                    })
                                }}
                            </span>
                            <Badge
                                v-if="!product.active"
                                variant="secondary"
                                class="ml-2"
                            >
                                {{ $t('Off in retailCRM') }}
                            </Badge>
                            <span
                                v-if="product.linked_to"
                                class="block text-xs text-amber-700 dark:text-amber-400"
                            >
                                {{
                                    $t('Already linked to «:name»', {
                                        name: product.linked_to,
                                    })
                                }}
                            </span>
                        </button>
                    </li>
                </ul>
                <p
                    v-else-if="query.trim().length >= 2"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('Nothing found in retailCRM.') }}
                </p>
            </template>

            <template v-else>
                <div class="flex items-center justify-between gap-2">
                    <p class="font-medium">
                        {{ chosen.name }}
                        <span class="text-muted-foreground"
                            >#{{ chosen.id }}</span
                        >
                    </p>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="chosen = null"
                    >
                        {{ $t('Back to search') }}
                    </Button>
                </div>
                <ul class="divide-y rounded-lg border">
                    <li
                        v-for="offer in chosen.offers"
                        :key="offer.id"
                        class="flex items-start gap-3 px-3 py-2 text-sm"
                    >
                        <Checkbox
                            :id="`offer-${offer.id}`"
                            :model-value="offerIds.includes(offer.id)"
                            class="mt-0.5"
                            @update:model-value="
                                toggle(offer.id, $event === true)
                            "
                        />
                        <label :for="`offer-${offer.id}`" class="flex-1">
                            <span class="font-medium">{{ offer.label }}</span>
                            <span class="text-muted-foreground">
                                · {{ offer.name }} · #{{ offer.id }}
                            </span>
                            <span
                                v-if="!offer.active"
                                class="block text-xs text-muted-foreground"
                            >
                                {{ $t('Off in retailCRM') }}
                            </span>
                            <span
                                v-if="offer.linked_to"
                                class="block text-xs text-amber-700 dark:text-amber-400"
                            >
                                {{
                                    $t('Already sold as «:name»', {
                                        name: offer.linked_to,
                                    })
                                }}
                            </span>
                        </label>
                        <span class="tabular-nums">{{
                            formatPrice(offer.price)
                        }}</span>
                    </li>
                </ul>
                <InputError
                    :message="errors.retailcrm_product_id ?? errors.offer_ids"
                />
                <DialogFooter>
                    <Button
                        type="button"
                        :disabled="processing || offerIds.length === 0"
                        @click="submit"
                    >
                        {{
                            $t('Link :count offers', {
                                count: offerIds.length,
                            })
                        }}
                    </Button>
                </DialogFooter>
            </template>
        </DialogContent>
    </Dialog>
</template>
