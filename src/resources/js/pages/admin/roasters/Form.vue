<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref, watch } from 'vue';
import CheckboxField from '@/components/admin/catalog/CheckboxField.vue';
import ConfirmDelete from '@/components/admin/catalog/ConfirmDelete.vue';
import Field from '@/components/admin/catalog/Field.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { slugify } from '@/lib/catalog';
import { edit as editLocation } from '@/routes/admin/locations';
import {
    create,
    destroy,
    edit,
    index,
    store,
    update,
} from '@/routes/admin/roasters';
import { create as createLocation } from '@/routes/admin/roasters/locations';

type LocationRow = {
    id: number;
    name: string;
    city: string;
    retailcrm_store_code: string | null;
    roast_days: string[] | null;
    is_active: boolean;
    variants_count: number;
};

const props = defineProps<{
    roaster: {
        id: number;
        slug: string;
        name: string;
        city: string;
        story: string | null;
        website: string | null;
        is_partner: boolean;
        is_active: boolean;
        products_count: number;
        locations: LocationRow[];
    } | null;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Roasters', href: index() },
        props.roaster
            ? { title: props.roaster.name, href: edit(props.roaster.id) }
            : { title: 'New roaster', href: create() },
    ],
});

const form = useForm({
    name: props.roaster?.name ?? '',
    slug: props.roaster?.slug ?? '',
    city: props.roaster?.city ?? '',
    story: props.roaster?.story ?? null,
    website: props.roaster?.website ?? null,
    is_partner: props.roaster?.is_partner ?? true,
    is_active: props.roaster?.is_active ?? true,
});

const slugTouched = ref(props.roaster !== null);
watch(
    () => form.name,
    (name) => {
        if (!slugTouched.value) {
            form.slug = slugify(name);
        }
    },
);

const submit = () => {
    form.submit(props.roaster ? update(props.roaster.id) : store(), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="roaster?.name ?? $t('New roaster')" />

    <div class="space-y-10 p-4">
        <form class="max-w-xl space-y-6" @submit.prevent="submit">
            <Heading :title="roaster?.name ?? $t('New roaster')" />
            <Field id="name" :label="$t('Name')" :error="form.errors.name">
                <Input id="name" v-model="form.name" required />
            </Field>
            <Field id="slug" :label="$t('Code')" :error="form.errors.slug">
                <Input
                    id="slug"
                    v-model="form.slug"
                    required
                    @input="slugTouched = true"
                />
            </Field>
            <Field id="city" :label="$t('City')" :error="form.errors.city">
                <Input id="city" v-model="form.city" required />
            </Field>
            <Field
                id="website"
                :label="$t('Website')"
                :error="form.errors.website"
            >
                <Input id="website" v-model="form.website" type="url" />
            </Field>
            <Field
                id="story"
                :label="$t('About the roaster')"
                :error="form.errors.story"
            >
                <Textarea id="story" v-model="form.story" rows="5" />
            </Field>
            <CheckboxField
                v-model="form.is_partner"
                :label="$t('Partner roaster')"
                :hint="$t('Not Coffee Project itself.')"
            />
            <CheckboxField
                v-model="form.is_active"
                :label="$t('Shown on the site')"
            />
            <Button :disabled="form.processing">
                {{ roaster ? $t('Save changes') : $t('Create roaster') }}
            </Button>
        </form>

        <div v-if="roaster" class="max-w-2xl space-y-4">
            <div class="flex items-center justify-between gap-2">
                <Heading
                    variant="small"
                    :title="$t('Ships from')"
                    :description="
                        $t(
                            'Places orders are roasted and sent from. Each maps to a retailCRM warehouse.',
                        )
                    "
                />
                <Button variant="outline" size="sm" as-child>
                    <Link :href="createLocation(roaster.id)">
                        <Plus /> {{ $t('New location') }}
                    </Link>
                </Button>
            </div>
            <ul class="divide-y rounded-xl border text-sm">
                <li
                    v-for="location in roaster.locations"
                    :key="location.id"
                    class="flex flex-wrap items-center gap-2 px-3 py-2"
                >
                    <Link
                        :href="editLocation(location.id)"
                        class="font-medium hover:underline"
                    >
                        {{ location.name }}
                    </Link>
                    <span class="text-muted-foreground">{{
                        location.city
                    }}</span>
                    <Badge
                        v-if="location.retailcrm_store_code"
                        variant="outline"
                    >
                        {{ location.retailcrm_store_code }}
                    </Badge>
                    <Badge v-if="!location.is_active" variant="secondary">
                        {{ $t('Hidden') }}
                    </Badge>
                    <span class="ml-auto text-muted-foreground">
                        {{
                            $t(':count sizes', {
                                count: location.variants_count,
                            })
                        }}
                    </span>
                </li>
                <li
                    v-if="!roaster.locations.length"
                    class="px-3 py-4 text-muted-foreground"
                >
                    {{ $t('No locations yet.') }}
                </li>
            </ul>
        </div>

        <div
            v-if="
                roaster && !roaster.products_count && !roaster.locations.length
            "
            class="max-w-xl space-y-4"
        >
            <Heading variant="small" :title="$t('Delete roaster')" />
            <ConfirmDelete
                :action="destroy(roaster.id)"
                :title="$t('Delete :name?', { name: roaster.name })"
            />
        </div>
    </div>
</template>
