<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import CheckboxField from '@/components/admin/catalog/CheckboxField.vue';
import Field from '@/components/admin/catalog/Field.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { edit, update } from '@/routes/admin/delivery';
import { update as updateCdek } from '@/routes/admin/delivery/cdek';

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
    cdek: {
        enabled: boolean;
        free_from: number | null;
        length: number;
        width: number;
        height: number;
        missing: string[];
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

const cdekForm = useForm<{
    enabled: boolean;
    free_from: number | string | null;
    length: number | string;
    width: number | string;
    height: number | string;
}>({
    enabled: props.cdek.enabled,
    free_from: props.cdek.free_from,
    length: props.cdek.length,
    width: props.cdek.width,
    height: props.cdek.height,
});

const submitCdek = () => {
    cdekForm.transform((data) => ({
        ...data,
        free_from: data.free_from === '' ? null : data.free_from,
    }));
    cdekForm.submit(updateCdek(), { preserveScroll: true });
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

        <form
            class="max-w-xl space-y-6 border-t pt-6"
            @submit.prevent="submitCdek"
        >
            <Heading
                :title="$t('CDEK across Russia')"
                :description="
                    $t(
                        'Pickup points and parcel lockers. CDEK calculates the price for the box below; orders are paid by card online.',
                    )
                "
            />
            <p
                v-if="cdek.missing.length"
                class="rounded-lg bg-amber-500/10 px-4 py-3 text-sm"
            >
                {{
                    $t('CDEK is hidden at checkout until this is set up: :what.', {
                        what: cdek.missing.join(', '),
                    })
                }}
            </p>
            <CheckboxField
                v-model="cdekForm.enabled"
                :label="$t('CDEK delivery is available')"
            />
            <Field
                id="cdek_free_from"
                :label="$t('Free from, ₽')"
                :error="cdekForm.errors.free_from"
                :hint="$t('Empty: never free.')"
            >
                <Input
                    id="cdek_free_from"
                    v-model="cdekForm.free_from"
                    type="number"
                    min="0"
                    step="1"
                />
            </Field>
            <div class="grid gap-4 sm:grid-cols-3">
                <Field
                    id="cdek_length"
                    :label="$t('Box length, cm')"
                    :error="cdekForm.errors.length"
                >
                    <Input
                        id="cdek_length"
                        v-model="cdekForm.length"
                        type="number"
                        min="1"
                        max="150"
                        required
                    />
                </Field>
                <Field
                    id="cdek_width"
                    :label="$t('Box width, cm')"
                    :error="cdekForm.errors.width"
                >
                    <Input
                        id="cdek_width"
                        v-model="cdekForm.width"
                        type="number"
                        min="1"
                        max="150"
                        required
                    />
                </Field>
                <Field
                    id="cdek_height"
                    :label="$t('Box height, cm')"
                    :error="cdekForm.errors.height"
                >
                    <Input
                        id="cdek_height"
                        v-model="cdekForm.height"
                        type="number"
                        min="1"
                        max="150"
                        required
                    />
                </Field>
            </div>
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Weight is the variants\' weights plus :grams g for packaging; a variant without a weight counts as :default g.',
                        { grams: '150', default: '300' },
                    )
                }}
            </p>
            <Button :disabled="cdekForm.processing || !cdekForm.isDirty">
                {{ $t('Save changes') }}
            </Button>
        </form>
    </div>
</template>
