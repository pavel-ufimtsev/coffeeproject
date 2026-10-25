<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Field from '@/components/admin/catalog/Field.vue';
import NativeSelect from '@/components/admin/catalog/NativeSelect.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, index, store } from '@/routes/admin/products';
import type { CategoryOption, NamedRecord, ProductTypeOption } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Products', href: index() },
            { title: 'New product', href: create() },
        ],
    },
});

const props = defineProps<{
    prefill: { name: string; retailcrm_product_id: number | null };
    types: ProductTypeOption[];
    categories: CategoryOption[];
    roasters: NamedRecord[];
}>();

const form = useForm({
    name: props.prefill.name,
    type: 'coffee',
    category_id: props.categories[0]?.id ?? null,
    roaster_id: props.roasters[0]?.id ?? null,
    retailcrm_product_id: props.prefill.retailcrm_product_id,
});
</script>

<template>
    <Head :title="$t('New product')" />

    <div class="p-4">
        <Heading
            :title="$t('New product')"
            :description="
                $t(
                    'The product starts as a draft. You fill in the rest and put it on sale on the next page.',
                )
            "
        />

        <form class="max-w-xl space-y-6" @submit.prevent="form.submit(store())">
            <Field id="name" :label="$t('Name')" :error="form.errors.name">
                <Input id="name" v-model="form.name" required autofocus />
            </Field>

            <Field id="type" :label="$t('Type')" :error="form.errors.type">
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
                id="category_id"
                :label="$t('Category')"
                :error="form.errors.category_id"
            >
                <NativeSelect id="category_id" v-model="form.category_id">
                    <option
                        v-for="option in categories"
                        :key="option.id"
                        :value="option.id"
                    >
                        {{ option.label }}
                    </option>
                </NativeSelect>
            </Field>

            <Field
                id="roaster_id"
                :label="$t('Roaster')"
                :error="form.errors.roaster_id"
            >
                <NativeSelect id="roaster_id" v-model="form.roaster_id">
                    <option :value="null">{{ $t('None') }}</option>
                    <option
                        v-for="roaster in roasters"
                        :key="roaster.id"
                        :value="roaster.id"
                    >
                        {{ roaster.name }}
                    </option>
                </NativeSelect>
            </Field>

            <p
                v-if="form.retailcrm_product_id"
                class="rounded-lg bg-muted p-3 text-sm"
            >
                {{
                    $t(
                        'Will be linked to retailCRM product #:id; its active offers become the sizes.',
                        { id: form.retailcrm_product_id },
                    )
                }}
            </p>
            <InputError :message="form.errors.retailcrm_product_id" />

            <Button :disabled="form.processing">{{
                $t('Create draft')
            }}</Button>
        </form>
    </div>
</template>
