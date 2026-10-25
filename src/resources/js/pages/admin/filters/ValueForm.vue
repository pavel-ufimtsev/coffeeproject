<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { ref, watch } from 'vue';
import CheckboxField from '@/components/admin/catalog/CheckboxField.vue';
import ConfirmDelete from '@/components/admin/catalog/ConfirmDelete.vue';
import Field from '@/components/admin/catalog/Field.vue';
import LandingTextFields from '@/components/admin/catalog/LandingTextFields.vue';
import NativeSelect from '@/components/admin/catalog/NativeSelect.vue';
import StatusBadge from '@/components/admin/catalog/StatusBadge.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { slugify } from '@/lib/catalog';
import { edit as editGroup, index } from '@/routes/admin/filter-groups';
import { create, store } from '@/routes/admin/filter-groups/filter-values';
import { destroy, edit, update } from '@/routes/admin/filter-values';
import { edit as editProduct } from '@/routes/admin/products';
import type { LandingTexts, NamedRecord, ProductSummary } from '@/types';

const props = defineProps<{
    group: {
        id: number;
        name: string;
        slug: string;
        category_name: string;
        category_slug: string;
    };
    value:
        | (LandingTexts & {
              id: number;
              name: string;
              slug: string;
              parent_id: number | null;
              is_landing: boolean;
              url: string | null;
              products: ProductSummary[];
          })
        | null;
    parents: NamedRecord[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Filters', href: index() },
        { title: props.group.name, href: editGroup(props.group.id) },
        props.value
            ? { title: props.value.name, href: edit(props.value.id) }
            : { title: 'New value', href: create(props.group.id) },
    ],
});

const form = useForm({
    name: props.value?.name ?? '',
    slug: props.value?.slug ?? '',
    parent_id: props.value?.parent_id ?? null,
    is_landing: props.value?.is_landing ?? false,
    h1: props.value?.h1 ?? null,
    intro: props.value?.intro ?? null,
    seo_title: props.value?.seo_title ?? null,
    seo_description: props.value?.seo_description ?? null,
});

const slugTouched = ref(props.value !== null);
watch(
    () => form.name,
    (name) => {
        if (!slugTouched.value) {
            form.slug = slugify(name);
        }
    },
);

const submit = () => {
    form.submit(props.value ? update(props.value.id) : store(props.group.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="value?.name ?? $t('New value')" />

    <div class="space-y-10 p-4">
        <div>
            <Heading
                :title="value?.name ?? $t('New value')"
                :description="`${group.category_name} · ${group.name}`"
            />
            <a
                v-if="value?.url"
                :href="value.url"
                target="_blank"
                class="-mt-6 mb-6 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
            >
                {{ value.url }} <ExternalLink class="size-3.5" />
            </a>

            <form class="max-w-2xl space-y-6" @submit.prevent="submit">
                <Field id="name" :label="$t('Name')" :error="form.errors.name">
                    <Input id="name" v-model="form.name" required />
                </Field>
                <Field
                    id="slug"
                    :label="$t('Address')"
                    :error="form.errors.slug"
                    :hint="
                        form.is_landing
                            ? `/${group.category_slug}/${form.slug}`
                            : `?${group.slug}=${form.slug}`
                    "
                >
                    <Input
                        id="slug"
                        v-model="form.slug"
                        required
                        @input="slugTouched = true"
                    />
                </Field>
                <Field
                    v-if="parents.length"
                    id="parent_id"
                    :label="$t('Belongs to')"
                    :error="form.errors.parent_id"
                    :hint="
                        $t(
                            'Products with this value also appear under the parent, e.g. Kenya under Africa.',
                        )
                    "
                >
                    <NativeSelect id="parent_id" v-model="form.parent_id">
                        <option :value="null">{{ $t('None') }}</option>
                        <option
                            v-for="parent in parents"
                            :key="parent.id"
                            :value="parent.id"
                        >
                            {{ parent.name }}
                        </option>
                    </NativeSelect>
                </Field>
                <CheckboxField
                    v-model="form.is_landing"
                    :label="$t('Landing page')"
                    :hint="
                        $t(
                            'Gives the value its own page for search engines. Indexed once it has at least 3 products.',
                        )
                    "
                />

                <LandingTextFields
                    v-if="form.is_landing"
                    v-model:h1="form.h1"
                    v-model:intro="form.intro"
                    v-model:seo-title="form.seo_title"
                    v-model:seo-description="form.seo_description"
                    :errors="form.errors"
                    :heading-placeholder="form.name"
                />

                <Button :disabled="form.processing">
                    {{ value ? $t('Save changes') : $t('Create value') }}
                </Button>
            </form>
        </div>

        <div v-if="value" class="max-w-2xl space-y-4">
            <Heading
                variant="small"
                :title="
                    $t('Products (:count)', { count: value.products.length })
                "
                :description="$t('Tag products on their edit page.')"
            />
            <ul class="divide-y rounded-xl border text-sm">
                <li
                    v-for="product in value.products"
                    :key="product.id"
                    class="flex items-center justify-between gap-2 px-3 py-2"
                >
                    <Link
                        :href="editProduct(product.id)"
                        class="hover:underline"
                    >
                        {{ product.name }}
                    </Link>
                    <StatusBadge
                        :status="product.status"
                        :label="product.status_label"
                    />
                </li>
            </ul>
        </div>

        <div v-if="value" class="max-w-xl space-y-4">
            <Heading
                variant="small"
                :title="$t('Delete value')"
                :description="
                    value.is_landing
                        ? $t('Its page will redirect to the section.')
                        : undefined
                "
            />
            <ConfirmDelete
                :action="destroy(value.id)"
                :title="$t('Delete :name?', { name: value.name })"
            />
        </div>
    </div>
</template>
