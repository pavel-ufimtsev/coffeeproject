<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import CheckboxField from '@/components/admin/catalog/CheckboxField.vue';
import Field from '@/components/admin/catalog/Field.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { edit, update } from '@/routes/admin/delivery';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Delivery', href: edit() }],
    },
});

const props = defineProps<{
    courier: {
        enabled: boolean;
        price: number;
        free_from: number | null;
    };
}>();

// Number inputs give back strings once edited; an emptied one is ''.
const form = useForm<{
    enabled: boolean;
    price: number | string;
    free_from: number | string | null;
}>({
    enabled: props.courier.enabled,
    price: props.courier.price,
    free_from: props.courier.free_from,
});

const rub = (value: number) =>
    new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 0,
    }).format(value);

const preview = computed(() => {
    const price = Number(form.price) || 0;
    const free =
        form.free_from === null || form.free_from === ''
            ? null
            : Number(form.free_from);

    if (price === 0) {
        return { below: rub(0), above: null };
    }

    return { below: rub(price), above: free === null ? null : rub(free) };
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        free_from: data.free_from === '' ? null : data.free_from,
    }));
    form.submit(update(), { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('Delivery')" />

    <div class="space-y-6 p-4">
        <form class="max-w-xl space-y-6" @submit.prevent="submit">
            <Heading
                :title="$t('Courier in Ekaterinburg')"
                :description="
                    $t(
                        'Prices customers see at checkout. Free delivery counts goods after the promo code discount.',
                    )
                "
            />
            <CheckboxField
                v-model="form.enabled"
                :label="$t('Courier delivery is available')"
                :hint="
                    $t(
                        'When off, customers cannot place orders until another delivery method is added.',
                    )
                "
            />
            <div class="grid gap-4 sm:grid-cols-2">
                <Field
                    id="price"
                    :label="$t('Delivery price, ₽')"
                    :error="form.errors.price"
                >
                    <Input
                        id="price"
                        v-model="form.price"
                        type="number"
                        min="0"
                        step="1"
                        required
                    />
                </Field>
                <Field
                    id="free_from"
                    :label="$t('Free from, ₽')"
                    :error="form.errors.free_from"
                    :hint="$t('Empty: never free.')"
                >
                    <Input
                        id="free_from"
                        v-model="form.free_from"
                        type="number"
                        min="0"
                        step="1"
                    />
                </Field>
            </div>
            <p class="rounded-lg bg-muted/50 px-4 py-3 text-sm">
                <template v-if="preview.above">
                    {{
                        $t(
                            'Orders below :amount pay :price; from :amount delivery is free.',
                            {
                                amount: preview.above,
                                price: preview.below,
                            },
                        )
                    }}
                </template>
                <template v-else>
                    {{
                        $t('Every order pays :price for delivery.', {
                            price: preview.below,
                        })
                    }}
                </template>
            </p>
            <Button :disabled="form.processing || !form.isDirty">
                {{ $t('Save changes') }}
            </Button>
        </form>
    </div>
</template>
