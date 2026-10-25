<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ExternalLink, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CheckboxField from '@/components/admin/catalog/CheckboxField.vue';
import ConfirmDelete from '@/components/admin/catalog/ConfirmDelete.vue';
import Field from '@/components/admin/catalog/Field.vue';
import LandingTextFields from '@/components/admin/catalog/LandingTextFields.vue';
import NativeSelect from '@/components/admin/catalog/NativeSelect.vue';
import SortableList from '@/components/admin/catalog/SortableList.vue';
import StatusBadge from '@/components/admin/catalog/StatusBadge.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { slugify } from '@/lib/catalog';
import {
    create,
    destroy,
    edit,
    index,
    store,
    update,
} from '@/routes/admin/collections';
import type { LandingTexts, NamedRecord, ProductSummary } from '@/types';

const props = defineProps<{
    collection:
        | (LandingTexts & {
              id: number;
              category_id: number;
              name: string;
              slug: string;
              is_landing: boolean;
              url: string | null;
              products: ProductSummary[];
          })
        | null;
    categoryId: number | null;
    roots: (NamedRecord & { slug: string })[];
    candidates: ProductSummary[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Collections', href: index() },
        props.collection
            ? { title: props.collection.name, href: edit(props.collection.id) }
            : { title: 'New collection', href: create() },
    ],
});

const members = ref<ProductSummary[]>([...(props.collection?.products ?? [])]);

const form = useForm({
    category_id: props.categoryId ?? props.roots[0]?.id ?? null,
    name: props.collection?.name ?? '',
    slug: props.collection?.slug ?? '',
    is_landing: props.collection?.is_landing ?? true,
    h1: props.collection?.h1 ?? null,
    intro: props.collection?.intro ?? null,
    seo_title: props.collection?.seo_title ?? null,
    seo_description: props.collection?.seo_description ?? null,
});

const slugTouched = ref(props.collection !== null);
watch(
    () => form.name,
    (name) => {
        if (!slugTouched.value) {
            form.slug = slugify(name);
        }
    },
);

// A new collection lists products of the chosen section, so reload them.
watch(
    () => form.category_id,
    (category) => {
        if (!props.collection) {
            members.value = [];
            router.reload({
                data: { category },
                only: ['candidates', 'categoryId'],
            });
        }
    },
);

const toAdd = ref<number | null>(null);
const available = computed(() =>
    props.candidates.filter(
        (product) => !members.value.some((member) => member.id === product.id),
    ),
);

const add = () => {
    const product = props.candidates.find((p) => p.id === toAdd.value);

    if (product) {
        members.value = [...members.value, product];
    }

    toAdd.value = null;
};

const rootSlug = computed(
    () => props.roots.find((root) => root.id === form.category_id)?.slug ?? '',
);

const submit = () => {
    form.transform((data) => ({
        ...data,
        product_ids: members.value.map((member) => member.id),
    })).submit(props.collection ? update(props.collection.id) : store(), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="collection?.name ?? $t('New collection')" />

    <div class="space-y-10 p-4">
        <form class="max-w-2xl space-y-6" @submit.prevent="submit">
            <Heading :title="collection?.name ?? $t('New collection')" />
            <a
                v-if="collection?.url"
                :href="collection.url"
                target="_blank"
                class="-mt-6 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
            >
                {{ collection.url }} <ExternalLink class="size-3.5" />
            </a>

            <Field
                v-if="!collection"
                id="category_id"
                :label="$t('Section')"
                :error="form.errors.category_id"
            >
                <NativeSelect id="category_id" v-model="form.category_id">
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
                id="slug"
                :label="$t('Address')"
                :error="form.errors.slug"
                :hint="`/${rootSlug}/${form.slug}`"
            >
                <Input
                    id="slug"
                    v-model="form.slug"
                    required
                    @input="slugTouched = true"
                />
            </Field>
            <CheckboxField
                v-model="form.is_landing"
                :label="$t('Landing page')"
                :hint="
                    $t('Gives the collection its own page for search engines.')
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

            <div class="space-y-3">
                <Heading
                    variant="small"
                    :title="$t('Products (:count)', { count: members.length })"
                    :description="
                        $t('Shown on the collection page in this order.')
                    "
                />
                <SortableList v-if="members.length" v-model="members">
                    <template #default="{ item }">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="flex-1">{{ item.name }}</span>
                            <StatusBadge
                                :status="item.status"
                                :label="item.status_label"
                            />
                        </div>
                    </template>
                    <template #actions="{ item }">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-7"
                            :aria-label="$t('Remove')"
                            @click="
                                members = members.filter(
                                    (m) => m.id !== item.id,
                                )
                            "
                        >
                            <X />
                        </Button>
                    </template>
                </SortableList>
                <div class="flex gap-2">
                    <NativeSelect
                        v-model="toAdd"
                        :aria-label="$t('Add a product')"
                    >
                        <option :value="null">
                            {{ $t('Add a product…') }}
                        </option>
                        <option
                            v-for="product in available"
                            :key="product.id"
                            :value="product.id"
                        >
                            {{ product.name }} ({{ product.status_label }})
                        </option>
                    </NativeSelect>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="toAdd === null"
                        @click="add"
                    >
                        {{ $t('Add') }}
                    </Button>
                </div>
            </div>

            <Button :disabled="form.processing">
                {{ collection ? $t('Save changes') : $t('Create collection') }}
            </Button>
        </form>

        <div v-if="collection" class="max-w-xl space-y-4">
            <Heading
                variant="small"
                :title="$t('Delete collection')"
                :description="
                    collection.is_landing
                        ? $t('Its page will redirect to the section.')
                        : undefined
                "
            />
            <ConfirmDelete
                :action="destroy(collection.id)"
                :title="$t('Delete :name?', { name: collection.name })"
            />
        </div>
    </div>
</template>
