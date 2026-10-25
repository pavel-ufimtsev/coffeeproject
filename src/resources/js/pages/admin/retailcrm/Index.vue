<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ExternalLink, RefreshCw } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatPrice } from '@/lib/catalog';
import { edit as editProduct } from '@/routes/admin/products';
import { index, sync, unlinked } from '@/routes/admin/retailcrm';
import type { SyncChange, SyncIssue } from '@/types';

type Run = {
    id: number;
    started_at: string;
    duration: number | null;
    products_checked: number;
    variants_changed: number;
    error: string | null;
    changes: SyncChange[];
    issues: SyncIssue[];
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'retailCRM', href: index() }],
    },
});

const props = defineProps<{
    configured: boolean;
    url: string | null;
    runs: Run[];
    productIds: Record<string, number>;
}>();

const syncing = ref(false);
const expanded = ref<number | null>(props.runs[0]?.id ?? null);
const latest = props.runs[0] ?? null;

const runSync = () => {
    router.post(
        sync.url(),
        {},
        {
            preserveScroll: true,
            onStart: () => (syncing.value = true),
            onFinish: () => (syncing.value = false),
        },
    );
};
</script>

<template>
    <Head title="retailCRM" />

    <div class="space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-0.5">
                <h2 class="text-xl font-semibold tracking-tight">retailCRM</h2>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Prices are copied from retailCRM every 30 minutes. New orders are sent there when RETAILCRM_PUSH_ORDERS is on.',
                        )
                    }}
                    <a
                        v-if="url"
                        :href="url"
                        target="_blank"
                        class="inline-flex items-center gap-1 hover:underline"
                    >
                        {{ url }} <ExternalLink class="size-3" />
                    </a>
                </p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="unlinked()">{{
                        $t('Products not on the site')
                    }}</Link>
                </Button>
                <Button :disabled="!configured || syncing" @click="runSync">
                    <RefreshCw :class="syncing && 'animate-spin'" />
                    {{ syncing ? $t('Syncing…') : $t('Sync now') }}
                </Button>
            </div>
        </div>

        <p
            v-if="!configured"
            class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
        >
            {{
                $t(
                    'Set RETAILCRM_URL and RETAILCRM_API_KEY in .env to connect.',
                )
            }}
        </p>

        <section v-if="latest && latest.issues.length" class="space-y-3">
            <h3 class="font-semibold">
                {{ $t('Problems found by the last sync') }}
            </h3>
            <ul class="divide-y rounded-xl border text-sm">
                <li
                    v-for="(issue, i) in latest.issues"
                    :key="i"
                    class="flex flex-wrap items-center gap-2 px-3 py-2"
                >
                    <AlertTriangle class="size-4 text-amber-600" />
                    <Link
                        v-if="productIds[issue.product]"
                        :href="editProduct(productIds[issue.product])"
                        class="font-medium hover:underline"
                    >
                        {{ issue.product }}
                    </Link>
                    <span v-else class="font-medium">{{ issue.product }}</span>
                    <span v-if="issue.variant" class="text-muted-foreground">
                        · {{ issue.variant }}
                    </span>
                    <span>{{ issue.problem }}</span>
                    <span class="ml-auto text-xs text-muted-foreground">
                        #{{ issue.retailcrm_product_id
                        }}<template v-if="issue.retailcrm_offer_id">
                            / {{ issue.retailcrm_offer_id }}</template
                        >
                    </span>
                </li>
            </ul>
        </section>

        <section class="space-y-3">
            <h3 class="font-semibold">{{ $t('Sync history') }}</h3>
            <ul class="divide-y rounded-xl border text-sm">
                <li v-for="run in runs" :key="run.id">
                    <button
                        type="button"
                        class="flex w-full flex-wrap items-center gap-3 px-3 py-2 text-left hover:bg-muted/40"
                        @click="expanded = expanded === run.id ? null : run.id"
                    >
                        <span class="tabular-nums">{{ run.started_at }}</span>
                        <Badge v-if="run.error" variant="destructive">
                            {{ $t('Failed') }}
                        </Badge>
                        <span v-else class="text-muted-foreground">
                            {{
                                $t(
                                    ':checked products, :changed prices changed, :issues problems',
                                    {
                                        checked: run.products_checked,
                                        changed: run.variants_changed,
                                        issues: run.issues.length,
                                    },
                                )
                            }}
                        </span>
                        <span
                            v-if="run.duration !== null"
                            class="ml-auto text-xs text-muted-foreground"
                        >
                            {{ $t(':seconds s', { seconds: run.duration }) }}
                        </span>
                    </button>
                    <div
                        v-if="
                            expanded === run.id &&
                            (run.error || run.changes.length)
                        "
                        class="space-y-1 bg-muted/30 px-3 py-2"
                    >
                        <p v-if="run.error" class="text-destructive">
                            {{ run.error }}
                        </p>
                        <p v-for="(change, i) in run.changes" :key="i">
                            {{ change.product }} · {{ change.variant }}:
                            {{ formatPrice(change.from.price) }} →
                            {{ formatPrice(change.to.price) }}
                            <span
                                v-if="change.to.old_price"
                                class="text-muted-foreground"
                            >
                                ({{
                                    $t('was :price', {
                                        price: formatPrice(change.to.old_price),
                                    })
                                }})
                            </span>
                        </p>
                    </div>
                </li>
                <li v-if="!runs.length" class="px-3 py-4 text-muted-foreground">
                    {{ $t('No syncs yet.') }}
                </li>
            </ul>
        </section>
    </div>
</template>
