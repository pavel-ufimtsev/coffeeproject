<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ExternalLink, RefreshCw } from '@lucide/vue';
import { ref } from 'vue';
import CheckboxField from '@/components/admin/catalog/CheckboxField.vue';
import Field from '@/components/admin/catalog/Field.vue';
import NativeSelect from '@/components/admin/catalog/NativeSelect.vue';
import OrderStatusBadge from '@/components/admin/shop/OrderStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { edit as editProduct } from '@/routes/admin/products';
import { index, retailcrm, show, update } from '@/routes/admin/orders';
import { complete } from '@/routes/checkout';

type Item = {
    id: number;
    product_id: number | null;
    name: string;
    meta: string;
    price: string;
    quantity: number;
    total: string;
};

const props = defineProps<{
    order: {
        id: number;
        number: string;
        token: string;
        created_at: string;
        status: string;
        is_paid: boolean;
        customer: string;
        phone: string;
        phone_link: string;
        email: string;
        user: { id: number; name: string } | null;
        delivery: string;
        address: string;
        access: string;
        courier_note: string | null;
        payment: string;
        payment_status: string | null;
        payment_id: string | null;
        paid_at: string | null;
        comment: string | null;
        promo_code: string | null;
        subtotal: string;
        discount: string | null;
        delivery_price: string;
        total: string;
        items: Item[];
        retailcrm_id: string | null;
        retailcrm_url: string | null;
        retailcrm_error: string | null;
    };
    statuses: { value: string; label: string }[];
    retailcrmEnabled: boolean;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Orders', href: index() },
        { title: props.order.number, href: show(props.order.number) },
    ],
});

const form = useForm({
    status: props.order.status,
    is_paid: props.order.is_paid,
});

const save = () => {
    form.submit(update(props.order.number), { preserveScroll: true });
};

const pushing = ref(false);
const push = () => {
    router.post(
        retailcrm.url(props.order.number),
        {},
        {
            preserveScroll: true,
            onStart: () => (pushing.value = true),
            onFinish: () => (pushing.value = false),
        },
    );
};

const statusLabel = (value: string) =>
    props.statuses.find((s) => s.value === value)?.label ?? value;
</script>

<template>
    <Head :title="order.number" />

    <div class="grid gap-8 p-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-8">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-xl font-semibold tracking-tight">
                    {{ order.number }}
                </h2>
                <OrderStatusBadge
                    :status="order.status"
                    :label="statusLabel(order.status)"
                />
                <span class="text-sm text-muted-foreground">
                    {{ order.created_at }}
                </span>
                <a
                    :href="complete.url(order.token)"
                    target="_blank"
                    class="ml-auto inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                >
                    {{ $t('Customer page') }} <ExternalLink class="size-3" />
                </a>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">
                                {{ $t('Item') }}
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                {{ $t('Price') }}
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                {{ $t('Qty') }}
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                {{ $t('Sum') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in order.items"
                            :key="item.id"
                            class="border-t"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    v-if="item.product_id"
                                    :href="editProduct(item.product_id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ item.name }}
                                </Link>
                                <span v-else class="font-medium">{{
                                    item.name
                                }}</span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                >
                                    {{ item.meta }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ item.price }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ item.quantity }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ item.total }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t text-sm">
                        <tr>
                            <td colspan="3" class="px-4 pt-3 text-right">
                                {{ $t('Goods') }}
                            </td>
                            <td class="px-4 pt-3 text-right tabular-nums">
                                {{ order.subtotal }}
                            </td>
                        </tr>
                        <tr v-if="order.discount">
                            <td colspan="3" class="px-4 pt-1 text-right">
                                {{
                                    $t('Promo code :code', {
                                        code: order.promo_code ?? '',
                                    })
                                }}
                            </td>
                            <td class="px-4 pt-1 text-right tabular-nums">
                                {{ order.discount }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-4 pt-1 text-right">
                                {{ $t('Delivery') }}
                            </td>
                            <td class="px-4 pt-1 text-right tabular-nums">
                                {{ order.delivery_price }}
                            </td>
                        </tr>
                        <tr class="font-semibold">
                            <td colspan="3" class="px-4 py-3 text-right">
                                {{ $t('Total') }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ order.total }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <dl class="grid gap-6 text-sm sm:grid-cols-2">
                <div class="space-y-1">
                    <dt class="font-medium text-muted-foreground">
                        {{ $t('Customer') }}
                    </dt>
                    <dd>
                        {{ order.customer }}
                        <span v-if="order.user" class="text-muted-foreground">
                            ({{ $t('account') }})
                        </span>
                    </dd>
                    <dd>
                        <a
                            :href="`tel:${order.phone_link}`"
                            class="hover:underline"
                            >{{ order.phone }}</a
                        >
                    </dd>
                    <dd>
                        <a
                            :href="`mailto:${order.email}`"
                            class="hover:underline"
                            >{{ order.email }}</a
                        >
                    </dd>
                </div>
                <div class="space-y-1">
                    <dt class="font-medium text-muted-foreground">
                        {{ order.delivery }}
                    </dt>
                    <dd>{{ order.address }}</dd>
                    <dd v-if="order.access">{{ order.access }}</dd>
                    <dd v-if="order.courier_note" class="text-muted-foreground">
                        {{ order.courier_note }}
                    </dd>
                </div>
                <div class="space-y-1">
                    <dt class="font-medium text-muted-foreground">
                        {{ $t('Payment') }}
                    </dt>
                    <dd>{{ order.payment }}</dd>
                    <dd v-if="order.payment_status">
                        {{ order.payment_status }}
                        <span
                            v-if="order.paid_at"
                            class="text-muted-foreground"
                        >
                            · {{ order.paid_at }}
                        </span>
                    </dd>
                    <dd
                        v-if="order.payment_id"
                        class="text-xs text-muted-foreground"
                    >
                        T-Bank PaymentId {{ order.payment_id }}
                    </dd>
                </div>
                <div v-if="order.comment" class="space-y-1">
                    <dt class="font-medium text-muted-foreground">
                        {{ $t('Order comment') }}
                    </dt>
                    <dd class="whitespace-pre-line">{{ order.comment }}</dd>
                </div>
            </dl>
        </div>

        <div class="space-y-8">
            <form class="space-y-4" @submit.prevent="save">
                <Heading variant="small" :title="$t('Status')" />
                <Field
                    id="status"
                    :label="$t('Status')"
                    :error="form.errors.status"
                >
                    <NativeSelect id="status" v-model="form.status">
                        <option
                            v-for="option in statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </NativeSelect>
                </Field>
                <CheckboxField v-model="form.is_paid" :label="$t('Paid')" />
                <Button :disabled="form.processing || !form.isDirty">
                    {{ $t('Save') }}
                </Button>
            </form>

            <div class="space-y-3">
                <Heading variant="small" title="retailCRM" />
                <p v-if="order.retailcrm_id" class="text-sm">
                    {{
                        $t('Sent, retailCRM ID :id.', {
                            id: order.retailcrm_id,
                        })
                    }}
                    <a
                        v-if="order.retailcrm_url"
                        :href="order.retailcrm_url"
                        target="_blank"
                        class="inline-flex items-center gap-1 hover:underline"
                    >
                        {{ $t('Open') }} <ExternalLink class="size-3" />
                    </a>
                </p>
                <template v-else>
                    <p
                        v-if="order.retailcrm_error"
                        class="text-sm break-words text-destructive"
                    >
                        {{ order.retailcrm_error }}
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        {{ $t('Not sent yet.') }}
                    </p>
                    <Button
                        v-if="retailcrmEnabled"
                        variant="outline"
                        size="sm"
                        :disabled="pushing"
                        @click="push"
                    >
                        <RefreshCw :class="{ 'animate-spin': pushing }" />
                        {{ $t('Send to retailCRM') }}
                    </Button>
                </template>
            </div>
        </div>
    </div>
</template>
