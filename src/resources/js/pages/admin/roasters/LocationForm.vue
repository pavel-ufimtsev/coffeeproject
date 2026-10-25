<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import CheckboxField from '@/components/admin/catalog/CheckboxField.vue';
import ConfirmDelete from '@/components/admin/catalog/ConfirmDelete.vue';
import Field from '@/components/admin/catalog/Field.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { destroy, edit, update } from '@/routes/admin/locations';
import { edit as editRoaster, index } from '@/routes/admin/roasters';
import { create, store } from '@/routes/admin/roasters/locations';
import type { Option } from '@/types';

const props = defineProps<{
    roaster: { id: number; name: string; city: string };
    location: {
        id: number;
        name: string;
        city: string;
        address: string | null;
        retailcrm_store_code: string | null;
        roast_days: string[] | null;
        is_active: boolean;
    } | null;
    days: Option[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Roasters', href: index() },
        { title: props.roaster.name, href: editRoaster(props.roaster.id) },
        props.location
            ? { title: props.location.name, href: edit(props.location.id) }
            : { title: 'New location', href: create(props.roaster.id) },
    ],
});

const form = useForm({
    name: props.location?.name ?? '',
    city: props.location?.city ?? props.roaster.city,
    address: props.location?.address ?? null,
    retailcrm_store_code: props.location?.retailcrm_store_code ?? null,
    roast_days: [...(props.location?.roast_days ?? [])],
    is_active: props.location?.is_active ?? true,
});

const toggleDay = (day: string, on: boolean) => {
    form.roast_days = on
        ? [...form.roast_days, day]
        : form.roast_days.filter((d) => d !== day);
};

const submit = () => {
    form.submit(
        props.location ? update(props.location.id) : store(props.roaster.id),
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head :title="location?.name ?? $t('New location')" />

    <div class="space-y-10 p-4">
        <form class="max-w-xl space-y-6" @submit.prevent="submit">
            <Heading
                :title="location?.name ?? $t('New location')"
                :description="roaster.name"
            />
            <Field id="name" :label="$t('Name')" :error="form.errors.name">
                <Input id="name" v-model="form.name" required />
            </Field>
            <Field id="city" :label="$t('City')" :error="form.errors.city">
                <Input id="city" v-model="form.city" required />
            </Field>
            <Field
                id="address"
                :label="$t('Address')"
                :error="form.errors.address"
            >
                <Input id="address" v-model="form.address" />
            </Field>
            <Field
                id="retailcrm_store_code"
                :label="$t('retailCRM warehouse code')"
                :error="form.errors.retailcrm_store_code"
                :hint="
                    $t('Orders shipped from here are sent to this warehouse.')
                "
            >
                <Input
                    id="retailcrm_store_code"
                    v-model="form.retailcrm_store_code"
                />
            </Field>
            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">
                    {{ $t('Roast days') }}
                </legend>
                <div class="flex flex-wrap gap-x-4 gap-y-2">
                    <CheckboxField
                        v-for="day in days"
                        :key="day.value"
                        :model-value="form.roast_days.includes(day.value)"
                        :label="day.label"
                        @update:model-value="toggleDay(day.value, $event)"
                    />
                </div>
                <InputError :message="form.errors.roast_days" />
            </fieldset>
            <CheckboxField v-model="form.is_active" :label="$t('Active')" />
            <Button :disabled="form.processing">
                {{ location ? $t('Save changes') : $t('Create location') }}
            </Button>
        </form>

        <div v-if="location" class="max-w-xl space-y-4">
            <Heading
                variant="small"
                :title="$t('Delete location')"
                :description="
                    $t('Sizes that ship from here will no longer list it.')
                "
            />
            <ConfirmDelete
                :action="destroy(location.id)"
                :title="$t('Delete :name?', { name: location.name })"
            />
        </div>
    </div>
</template>
