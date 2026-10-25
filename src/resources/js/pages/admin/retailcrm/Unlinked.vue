<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { EyeOff, Plus, RefreshCw } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { formatPrice } from '@/lib/catalog';
import { create } from '@/routes/admin/products';
import { index, unlinked } from '@/routes/admin/retailcrm';
import {
    destroy as unignore,
    store as ignore,
} from '@/routes/admin/retailcrm/ignored';

type CrmRow = {
    id: number;
    name: string;
    article: string | null;
    created_at: string | null;
    offers: { name: string; price: number | null }[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'retailCRM', href: index() },
            { title: 'Products not on the site', href: unlinked() },
        ],
    },
});

const props = defineProps<{
    configured: boolean;
    error: string | null;
    products: CrmRow[];
    ignored: { id: number; retailcrm_product_id: number; name: string }[];
}>();

const processing = ref(false);

const hide = (products: CrmRow[]) => {
    router.post(
        ignore.url(),
        { products: products.map(({ id, name }) => ({ id, name })) },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
        },
    );
};
</script>

<template>
    <Head :title="$t('Products not on the site')" />

    <div class="space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-0.5">
                <h2 class="text-xl font-semibold tracking-tight">
                    {{ $t('Products not on the site') }}
                </h2>
                <p class="max-w-2xl text-sm text-muted-foreground">
                    {{
                        $t(
                            'Active in retailCRM but not linked to any product here. Create a product for new items; hide the ones the site will not sell, so only new items show up here.',
                        )
                    }}
                </p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="unlinked({ query: { refresh: 1 } })">
                        <RefreshCw /> {{ $t('Reload from retailCRM') }}
                    </Link>
                </Button>
                <Button
                    v-if="products.length"
                    variant="outline"
                    :disabled="processing"
                    @click="hide(products)"
                >
                    <EyeOff />
                    {{ $t('Hide all :count', { count: products.length }) }}
                </Button>
            </div>
        </div>

        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        <p v-else-if="!configured" class="text-sm text-muted-foreground">
            {{ $t('retailCRM is not connected.') }}
        </p>

        <ul v-if="products.length" class="divide-y rounded-xl border text-sm">
            <li
                v-for="product in products"
                :key="product.id"
                class="flex flex-wrap items-center gap-3 px-3 py-2"
            >
                <div class="min-w-0 flex-1">
                    <p class="font-medium">
                        {{ product.name }}
                        <span class="font-normal text-muted-foreground">
                            #{{ product.id }}
                            <template v-if="product.article">
                                · {{ product.article }}
                            </template>
                            <template v-if="product.created_at">
                                · {{ product.created_at.slice(0, 10) }}
                            </template>
                        </span>
                    </p>
                    <p class="text-muted-foreground">
                        <template v-for="(offer, i) in product.offers" :key="i">
                            <template v-if="i">; </template>
                            {{ offer.name }} — {{ formatPrice(offer.price) }}
                        </template>
                    </p>
                </div>
                <Button size="sm" as-child>
                    <Link
                        :href="
                            create({
                                query: {
                                    retailcrm_product_id: product.id,
                                    name: product.name,
                                },
                            })
                        "
                    >
                        <Plus /> {{ $t('Create product') }}
                    </Link>
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    :disabled="processing"
                    @click="hide([product])"
                >
                    <EyeOff /> {{ $t('Hide') }}
                </Button>
            </li>
        </ul>
        <p
            v-else-if="configured && !error"
            class="text-sm text-muted-foreground"
        >
            {{ $t('Everything active in retailCRM is on the site or hidden.') }}
        </p>

        <Collapsible v-if="ignored.length">
            <CollapsibleTrigger as-child>
                <Button variant="ghost" size="sm">
                    {{
                        $t('Hidden products (:count)', {
                            count: ignored.length,
                        })
                    }}
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <ul class="mt-2 divide-y rounded-xl border text-sm">
                    <li
                        v-for="item in ignored"
                        :key="item.id"
                        class="flex items-center justify-between gap-2 px-3 py-1.5"
                    >
                        <span>
                            {{ item.name }}
                            <span class="text-muted-foreground">
                                #{{ item.retailcrm_product_id }}
                            </span>
                        </span>
                        <Button
                            size="sm"
                            variant="ghost"
                            @click="
                                router.delete(unignore.url(item.id), {
                                    preserveScroll: true,
                                })
                            "
                        >
                            {{ $t('Show again') }}
                        </Button>
                    </li>
                </ul>
            </CollapsibleContent>
        </Collapsible>
    </div>
</template>
