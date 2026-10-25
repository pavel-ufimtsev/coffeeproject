<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ImageOff, Plus } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import CheckboxField from '@/components/admin/catalog/CheckboxField.vue';
import NativeSelect from '@/components/admin/catalog/NativeSelect.vue';
import StatusBadge from '@/components/admin/catalog/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatPrice } from '@/lib/catalog';
import { create, edit, index } from '@/routes/admin/products';
import type {
    CategoryOption,
    Option,
    Paginated,
    ProductListItem,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Products', href: index() }],
    },
});

const props = defineProps<{
    products: Paginated<ProductListItem>;
    filters: {
        search: string;
        type: string;
        category: string;
        status: string;
        attention: boolean;
    };
    types: Option[];
    statuses: Option[];
    categories: CategoryOption[];
    attentionCount: number;
}>();

const search = ref(props.filters.search);
const type = ref(props.filters.type);
const category = ref(props.filters.category);
const status = ref(props.filters.status);
const attention = ref(props.filters.attention);

const applyFilters = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            type: type.value || undefined,
            category: category.value || undefined,
            status: status.value || undefined,
            attention: attention.value ? 1 : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(search, useDebounceFn(applyFilters, 300));
watch([type, category, status, attention], applyFilters);

const previousPage = computed(() => props.products.links.at(0)?.url ?? null);
const nextPage = computed(() => props.products.links.at(-1)?.url ?? null);
</script>

<template>
    <Head :title="$t('Products')" />

    <div class="space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-0.5">
                <h2 class="text-xl font-semibold tracking-tight">
                    {{ $t('Products') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Content lives here; sizes and prices come from retailCRM.',
                        )
                    }}
                </p>
            </div>
            <Button as-child>
                <Link :href="create()"><Plus /> {{ $t('New product') }}</Link>
            </Button>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <Input
                v-model="search"
                type="search"
                :placeholder="$t('Name, address or retailCRM ID')"
                class="max-w-xs"
                :aria-label="$t('Search products')"
            />
            <NativeSelect
                v-model="status"
                class="w-40"
                :aria-label="$t('Status')"
            >
                <option value="">{{ $t('All statuses') }}</option>
                <option
                    v-for="option in statuses"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </NativeSelect>
            <NativeSelect
                v-model="category"
                class="w-56"
                :aria-label="$t('Category')"
            >
                <option value="">{{ $t('All categories') }}</option>
                <option
                    v-for="option in categories"
                    :key="option.id"
                    :value="String(option.id)"
                >
                    {{ option.label }}
                </option>
            </NativeSelect>
            <NativeSelect v-model="type" class="w-44" :aria-label="$t('Type')">
                <option value="">{{ $t('All types') }}</option>
                <option
                    v-for="option in types"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </NativeSelect>
            <CheckboxField
                v-model="attention"
                :label="
                    $t('Needs attention (:count)', { count: attentionCount })
                "
            />
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="w-16 px-4 py-3 font-medium">
                            <span class="sr-only">{{ $t('Photo') }}</span>
                        </th>
                        <th class="px-4 py-3 font-medium">{{ $t('Name') }}</th>
                        <th class="px-4 py-3 font-medium">
                            {{ $t('Category') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ $t('Status') }}
                        </th>
                        <th class="px-4 py-3 text-right font-medium">
                            {{ $t('Price from') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="product in products.data"
                        :key="product.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-2">
                            <img
                                v-if="product.thumb"
                                :src="product.thumb"
                                alt=""
                                class="size-12 rounded-md object-cover"
                                loading="lazy"
                            />
                            <span
                                v-else
                                class="grid size-12 place-items-center rounded-md bg-muted text-muted-foreground"
                            >
                                <ImageOff class="size-4" />
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <Link
                                :href="edit(product.id)"
                                class="font-medium hover:underline"
                            >
                                {{ product.name }}
                            </Link>
                            <p
                                v-if="product.tagline"
                                class="text-muted-foreground"
                            >
                                {{ product.tagline }}
                            </p>
                            <p
                                v-for="problem in product.problems"
                                :key="problem"
                                class="flex items-center gap-1 text-amber-700 dark:text-amber-400"
                            >
                                <AlertTriangle class="size-3.5" />
                                {{ problem }}
                            </p>
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ product.category }}
                            <p class="text-xs">{{ product.type_label }}</p>
                        </td>
                        <td class="px-4 py-2">
                            <StatusBadge
                                :status="product.status"
                                :label="product.status_label"
                            />
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ formatPrice(product.min_price) }}
                        </td>
                    </tr>
                    <tr v-if="products.data.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            {{ $t('No products match these filters.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="products.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span>
                {{
                    $t(':from–:to of :total', {
                        from: products.from ?? 0,
                        to: products.to ?? 0,
                        total: products.total,
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
    </div>
</template>
