<script setup lang="ts">
import { Head, setLayoutProps, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import CheckboxField from '@/components/admin/catalog/CheckboxField.vue';
import ConfirmDelete from '@/components/admin/catalog/ConfirmDelete.vue';
import Field from '@/components/admin/catalog/Field.vue';
import NativeSelect from '@/components/admin/catalog/NativeSelect.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    create,
    destroy,
    edit,
    index,
    store,
    update,
} from '@/routes/admin/promo-codes';

const props = defineProps<{
    promoCode: {
        id: number;
        code: string;
        type: string;
        value: number;
        min_subtotal: number | null;
        starts_at: string | null;
        ends_at: string | null;
        max_uses: number | null;
        used_count: number;
        is_active: boolean;
        note: string | null;
    } | null;
    types: { value: string; label: string }[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Promo codes', href: index() },
        props.promoCode
            ? { title: props.promoCode.code, href: edit(props.promoCode.id) }
            : { title: 'New promo code', href: create() },
    ],
});

const form = useForm({
    code: props.promoCode?.code ?? '',
    type: props.promoCode?.type ?? 'percent',
    value: props.promoCode?.value ?? null,
    min_subtotal: props.promoCode?.min_subtotal ?? null,
    starts_at: props.promoCode?.starts_at ?? null,
    ends_at: props.promoCode?.ends_at ?? null,
    max_uses: props.promoCode?.max_uses ?? null,
    is_active: props.promoCode?.is_active ?? true,
    note: props.promoCode?.note ?? null,
});

const deleteError = computed(
    () => (usePage().props.errors as Record<string, string>).promo,
);

const submit = () => {
    form.transform((data) => ({ ...data, code: data.code.toUpperCase() }));
    form.submit(props.promoCode ? update(props.promoCode.id) : store(), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="promoCode?.code ?? $t('New promo code')" />

    <div class="space-y-10 p-4">
        <form class="max-w-xl space-y-6" @submit.prevent="submit">
            <Heading
                :title="promoCode?.code ?? $t('New promo code')"
                :description="
                    promoCode
                        ? $t('Used :count times.', {
                              count: promoCode.used_count,
                          })
                        : undefined
                "
            />
            <Field
                id="code"
                :label="$t('Code')"
                :error="form.errors.code"
                :hint="$t('Customers type it in any case.')"
            >
                <Input
                    id="code"
                    v-model="form.code"
                    required
                    class="font-mono uppercase"
                    autocomplete="off"
                />
            </Field>
            <div class="grid gap-4 sm:grid-cols-2">
                <Field
                    id="type"
                    :label="$t('Discount type')"
                    :error="form.errors.type"
                >
                    <NativeSelect id="type" v-model="form.type">
                        <option
                            v-for="option in types"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </NativeSelect>
                </Field>
                <Field
                    id="value"
                    :label="
                        form.type === 'percent'
                            ? $t('Percent')
                            : $t('Amount, ₽')
                    "
                    :error="form.errors.value"
                >
                    <Input
                        id="value"
                        v-model="form.value"
                        type="number"
                        min="1"
                        :max="form.type === 'percent' ? 100 : undefined"
                        step="1"
                        required
                    />
                </Field>
            </div>
            <Field
                id="min_subtotal"
                :label="$t('Minimum order, ₽')"
                :error="form.errors.min_subtotal"
                :hint="
                    $t('Goods total needed to use the code. Empty: any order.')
                "
            >
                <Input
                    id="min_subtotal"
                    v-model="form.min_subtotal"
                    type="number"
                    min="0"
                    step="1"
                />
            </Field>
            <div class="grid gap-4 sm:grid-cols-2">
                <Field
                    id="starts_at"
                    :label="$t('Starts')"
                    :error="form.errors.starts_at"
                >
                    <Input
                        id="starts_at"
                        v-model="form.starts_at"
                        type="date"
                    />
                </Field>
                <Field
                    id="ends_at"
                    :label="$t('Ends (inclusive)')"
                    :error="form.errors.ends_at"
                >
                    <Input id="ends_at" v-model="form.ends_at" type="date" />
                </Field>
            </div>
            <Field
                id="max_uses"
                :label="$t('Usage limit')"
                :error="form.errors.max_uses"
                :hint="
                    $t(
                        'Orders that can use the code in total. Empty: no limit.',
                    )
                "
            >
                <Input
                    id="max_uses"
                    v-model="form.max_uses"
                    type="number"
                    min="1"
                    step="1"
                />
            </Field>
            <Field
                id="note"
                :label="$t('Note')"
                :error="form.errors.note"
                :hint="
                    $t('For admins only, e.g. where the code was published.')
                "
            >
                <Input id="note" v-model="form.note" />
            </Field>
            <CheckboxField v-model="form.is_active" :label="$t('Code works')" />
            <Button :disabled="form.processing">
                {{ promoCode ? $t('Save changes') : $t('Create promo code') }}
            </Button>
        </form>

        <div v-if="promoCode" class="max-w-xl space-y-4">
            <Heading
                variant="small"
                :title="$t('Delete promo code')"
                :description="
                    $t(
                        'Only codes that were never used can be deleted. Switch it off instead.',
                    )
                "
            />
            <InputError :message="deleteError" />
            <ConfirmDelete
                v-if="!promoCode.used_count"
                :action="destroy(promoCode.id)"
                :title="$t('Delete :name?', { name: promoCode.code })"
            />
        </div>
    </div>
</template>
