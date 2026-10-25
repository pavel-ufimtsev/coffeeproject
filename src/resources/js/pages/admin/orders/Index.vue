<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Check } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import OrderStatusBadge from '@/components/admin/shop/OrderStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, show } from '@/routes/admin/orders';
import type { Paginated } from '@/types';

type OrderRow = {
    id: number;
    number: string;
    created_at: string;
    customer: string;
    phone: string;
    item_count: number;
    total: string;
    status: string;
    status_label: string;
    in_retailcrm: boolean;
    retailcrm_failed: boolean;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Orders', href: index() }],
    },
});

const props = defineProps<{
    orders: Paginated<OrderRow>;
    filters: { search: string; status: string };
    statuses: { value: string; label: string }[];
}>();

const search = ref(props.filters.search);
const status = ref(props.filters.status || 'all');

const applyFilters = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(search, useDebounceFn(applyFilters, 300));
watch(status, applyFilters);

const previousPage = computed(() => props.orders.links.at(0)?.url ?? null);
const nextPage = computed(() => props.orders.links.at(-1)?.url ?? null);
</script>

<template>
    <Head :title="$t('Orders')" />

    <div class="space-y-6 p-4">
        <div class="space-y-0.5">
            <h2 class="text-xl font-semibold tracking-tight">
                {{ $t('Orders') }}
            </h2>
            <p class="text-sm text-muted-foreground">
                {{ $t('Orders placed on the site, newest first.') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <Input
                v-model="search"
                type="search"
                :placeholder="$t('Number, phone, email or surname')"
                class="max-w-xs"
                :aria-label="$t('Search orders')"
            />
            <Select v-model="status">
                <SelectTrigger
                    class="w-44"
                    :aria-label="$t('Filter by status')"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{
                        $t('All statuses')
                    }}</SelectItem>
                    <SelectItem
                        v-for="option in statuses"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ $t('Order') }}</th>
                        <th class="px-4 py-3 font-medium">{{ $t('Date') }}</th>
                        <th class="px-4 py-3 font-medium">
                            {{ $t('Customer') }}
                        </th>
                        <th class="px-4 py-3 text-right font-medium">
                            {{ $t('Total') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ $t('Status') }}
                        </th>
                        <th class="px-4 py-3 font-medium">retailCRM</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="order in orders.data"
                        :key="order.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="show(order.number)"
                                class="font-medium hover:underline"
                            >
                                {{ order.number }}
                            </Link>
                        </td>
                        <td
                            class="px-4 py-3 text-muted-foreground tabular-nums"
                        >
                            {{ order.created_at }}
                        </td>
                        <td class="px-4 py-3">
                            {{ order.customer }}
                            <span class="block text-xs text-muted-foreground">
                                {{ order.phone }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ order.total }}
                            <span class="block text-xs text-muted-foreground">
                                {{
                                    $t(':count pcs.', {
                                        count: order.item_count,
                                    })
                                }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <OrderStatusBadge
                                :status="order.status"
                                :label="order.status_label"
                            />
                        </td>
                        <td class="px-4 py-3">
                            <Check
                                v-if="order.in_retailcrm"
                                class="size-4 text-green-600"
                                :aria-label="$t('Sent')"
                            />
                            <AlertTriangle
                                v-else-if="order.retailcrm_failed"
                                class="size-4 text-destructive"
                                :aria-label="$t('Failed')"
                            />
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                    </tr>
                    <tr v-if="orders.data.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            {{ $t('No orders yet.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="orders.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span>
                {{
                    $t(':from–:to of :total', {
                        from: orders.from ?? 0,
                        to: orders.to ?? 0,
                        total: orders.total,
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
