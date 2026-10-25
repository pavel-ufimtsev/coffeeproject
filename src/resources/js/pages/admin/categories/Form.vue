<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { ref, watch } from 'vue';
import CheckboxField from '@/components/admin/catalog/CheckboxField.vue';
import ConfirmDelete from '@/components/admin/catalog/ConfirmDelete.vue';
import Field from '@/components/admin/catalog/Field.vue';
import LandingTextFields from '@/components/admin/catalog/LandingTextFields.vue';
import NativeSelect from '@/components/admin/catalog/NativeSelect.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { slugify } from '@/lib/catalog';
import { useTrans } from '@/lib/i18n';
import {
    create,
    destroy,
    edit,
    index,
    store,
    update,
} from '@/routes/admin/categories';
import type { LandingTexts, NamedRecord } from '@/types';

type EditableCategory = LandingTexts & {
    id: number;
    parent_id: number | null;
    name: string;
    slug: string;
    is_active: boolean;
    is_root: boolean;
    url: string;
    products_count: number;
};

const props = defineProps<{
    category: EditableCategory | null;
    parentId: number | null;
    roots: (NamedRecord & { slug: string })[];
}>();

const t = useTrans();

setLayoutProps({
    breadcrumbs: [
        { title: 'Categories', href: index() },
        props.category
            ? { title: props.category.name, href: edit(props.category.id) }
            : { title: 'New category', href: create() },
    ],
});

const form = useForm({
    parent_id: props.parentId ?? props.roots[0]?.id ?? null,
    name: props.category?.name ?? '',
    slug: props.category?.slug ?? '',
    h1: props.category?.h1 ?? null,
    intro: props.category?.intro ?? null,
    seo_title: props.category?.seo_title ?? null,
    seo_description: props.category?.seo_description ?? null,
    is_active: props.category?.is_active ?? true,
});

// Suggest an address from the name until the slug is edited by hand.
const slugTouched = ref(props.category !== null);
watch(
    () => form.name,
    (name) => {
        if (!slugTouched.value) {
            form.slug = slugify(name);
        }
    },
);

const rootSlug = () =>
    props.roots.find((root) => root.id === form.parent_id)?.slug ?? '';

const submit = () => {
    form.submit(props.category ? update(props.category.id) : store(), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="category?.name ?? t('New category')" />

    <div class="space-y-10 p-4">
        <div>
            <Heading
                :title="category?.name ?? $t('New category')"
                :description="
                    category?.is_root
                        ? $t('A top section of the catalog.')
                        : undefined
                "
            />
            <a
                v-if="category"
                :href="category.url"
                target="_blank"
                class="-mt-6 mb-6 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
            >
                {{ category.url }} <ExternalLink class="size-3.5" />
            </a>

            <form class="max-w-2xl space-y-6" @submit.prevent="submit">
                <Field
                    v-if="!category"
                    id="parent_id"
                    :label="$t('Section')"
                    :error="form.errors.parent_id"
                >
                    <NativeSelect id="parent_id" v-model="form.parent_id">
                        <option
                            v-for="root in roots"
                            :key="root.id"
                            :value="root.id"
                        >
                            {{ root.name }}
                        </option>
                    </NativeSelect>
                </Field>

                <Field id="name" :label="$t('Name')" :error="form.errors.name">
                    <Input id="name" v-model="form.name" required />
                </Field>

                <Field
                    v-if="!category?.is_root"
                    id="slug"
                    :label="$t('Address')"
                    :error="form.errors.slug"
                    :hint="
                        $t(
                            '/:root/:slug. Changing it redirects the old address.',
                            {
                                root: category
                                    ? (category.url.split('/').at(-2) ?? '')
                                    : rootSlug(),
                                slug: form.slug,
                            },
                        )
                    "
                >
                    <Input
                        id="slug"
                        v-model="form.slug"
                        required
                        @input="slugTouched = true"
                    />
                </Field>

                <LandingTextFields
                    v-model:h1="form.h1"
                    v-model:intro="form.intro"
                    v-model:seo-title="form.seo_title"
                    v-model:seo-description="form.seo_description"
                    :errors="form.errors"
                    :heading-placeholder="form.name"
                />

                <CheckboxField
                    v-model="form.is_active"
                    :label="$t('Shown on the site')"
                />

                <Button :disabled="form.processing">
                    {{ category ? $t('Save changes') : $t('Create category') }}
                </Button>
            </form>
        </div>

        <div v-if="category && !category.is_root" class="max-w-xl space-y-4">
            <Heading
                variant="small"
                :title="$t('Delete category')"
                :description="
                    category.products_count
                        ? $t(
                              'Move its :count products to another category first.',
                              {
                                  count: category.products_count,
                              },
                          )
                        : $t('Its address will redirect to the section.')
                "
            />
            <ConfirmDelete
                v-if="!category.products_count"
                :action="destroy(category.id)"
                :title="$t('Delete :name?', { name: category.name })"
            />
        </div>
    </div>
</template>
