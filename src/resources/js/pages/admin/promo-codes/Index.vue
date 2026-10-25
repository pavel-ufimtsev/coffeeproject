<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/admin/promo-codes';

type PromoRow = {
    id: number;
    code: string;
    value_label: string;
    note: string | null;
    used_count: number;
    max_uses: number | null;
    period: string | null;
    is_active: boolean;
    problem: string | null;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Promo codes', href: index() }],
    },
});

defineProps<{ promoCodes: PromoRow[] }>();
</script>

<template>
    <Head :title="$t('Promo codes')" />

    <div class="space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-0.5">
                <h2 class="text-xl font-semibold tracking-tight">
                    {{ $t('Promo codes') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Discounts customers enter at checkout. They apply to goods, not delivery.',
                        )
                    }}
                </p>
            </div>
            <Button as-child>
                <Link :href="create()"
                    ><Plus /> {{ $t('New promo code') }}</Link
                >
            </Button>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ $t('Code') }}</th>
                        <th class="px-4 py-3 font-medium">
                            {{ $t('Discount') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ $t('Period') }}
                        </th>
                        <th class="px-4 py-3 text-right font-medium">
                            {{ $t('Used') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ $t('Status') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="promo in promoCodes"
                        :key="promo.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="edit(promo.id)"
                                class="font-mono font-medium hover:underline"
                            >
                                {{ promo.code }}
                            </Link>
                            <span
                                v-if="promo.note"
                                class="block text-xs text-muted-foreground"
                            >
                                {{ promo.note }}
                            </span>
                        </td>
                        <td class="px-4 py-3 tabular-nums">
                            {{ promo.value_label }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ promo.period ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ promo.used_count
                            }}<span
                                v-if="promo.max_uses"
                                class="text-muted-foreground"
                            >
                                / {{ promo.max_uses }}</span
                            >
                        </td>
                        <td class="px-4 py-3">
                            <Badge v-if="!promo.is_active" variant="secondary">
                                {{ $t('Off') }}
                            </Badge>
                            <Badge v-else-if="promo.problem" variant="outline">
                                {{ promo.problem }}
                            </Badge>
                            <Badge v-else>{{ $t('Works') }}</Badge>
                        </td>
                    </tr>
                    <tr v-if="promoCodes.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            {{ $t('No promo codes yet.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
