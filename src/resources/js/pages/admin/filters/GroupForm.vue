<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ExternalLink, Plus } from '@lucide/vue';
import { ref, watch } from 'vue';
import CheckboxField from '@/components/admin/catalog/CheckboxField.vue';
import ConfirmDelete from '@/components/admin/catalog/ConfirmDelete.vue';
import Field from '@/components/admin/catalog/Field.vue';
import NativeSelect from '@/components/admin/catalog/NativeSelect.vue';
import SortableList from '@/components/admin/catalog/SortableList.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
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
} from '@/routes/admin/filter-groups';
import {
    create as createValue,
    reorder as reorderValues,
} from '@/routes/admin/filter-groups/filter-values';
import { edit as editValue } from '@/routes/admin/filter-values';
import type { NamedRecord } from '@/types';

type ValueRow = {
    id: number;
    name: string;
    slug: string;
    parent_id: number | null;
    is_landing: boolean;
    products_count: number;
    active_products_count: number;
    url: string | null;
};

const props = defineProps<{
    group: {
        id: number;
        category_id: number;
        category_name: string;
        name: string;
        slug: string;
        is_visible: boolean;
        values: ValueRow[];
    } | null;
    categoryId: number | null;
    roots: NamedRecord[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Filters', href: index() },
        props.group
            ? { title: props.group.name, href: edit(props.group.id) }
            : { title: 'New filter', href: create() },
    ],
});

const form = useForm({
    category_id: props.categoryId ?? props.roots[0]?.id ?? null,
    name: props.group?.name ?? '',
    slug: props.group?.slug ?? '',
    is_visible: props.group?.is_visible ?? true,
});

const slugTouched = ref(props.group !== null);
watch(
    () => form.name,
    (name) => {
        if (!slugTouched.value) {
            form.slug = slugify(name);
        }
    },
);

const values = ref<ValueRow[]>([...(props.group?.values ?? [])]);
watch(
    () => props.group?.values,
    (value) => (values.value = [...(value ?? [])]),
);

const parentName = (id: number | null) =>
    values.value.find((value) => value.id === id)?.name;

const submit = () => {
    form.submit(props.group ? update(props.group.id) : store(), {
        preserveScroll: true,
    });
};

const saveOrder = (ids: number[]) => {
    if (props.group) {
        router.put(
            reorderValues.url(props.group.id),
            { ids },
            { preserveScroll: true },
        );
    }
};
</script>

<template>
    <Head :title="group?.name ?? $t('New filter')" />

    <div class="space-y-10 p-4">
        <div>
            <Heading
                :title="group?.name ?? $t('New filter')"
                :description="group?.category_name"
            />

            <form class="max-w-xl space-y-6" @submit.prevent="submit">
                <Field
                    v-if="!group"
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
                    :label="$t('Parameter in the address')"
                    :error="form.errors.slug"
                    :hint="
                        $t('Used in filtered addresses: ?:slug=value', {
                            slug: form.slug,
                        })
                    "
                >
                    <Input
                        id="slug"
                        v-model="form.slug"
                        required
                        @input="slugTouched = true"
                    />
                </Field>
                <CheckboxField
                    v-model="form.is_visible"
                    :label="$t('Shown in the catalog filters')"
                />
                <Button :disabled="form.processing">
                    {{ group ? $t('Save changes') : $t('Create filter') }}
                </Button>
            </form>
        </div>

        <div v-if="group" class="max-w-3xl space-y-4">
            <div class="flex items-center justify-between gap-2">
                <Heading
                    variant="small"
                    :title="$t('Values')"
                    :description="$t('Drag to change the order shoppers see.')"
                />
                <Button variant="outline" size="sm" as-child>
                    <Link :href="createValue(group.id)">
                        <Plus /> {{ $t('New value') }}
                    </Link>
                </Button>
            </div>
            <SortableList
                v-if="values.length"
                v-model="values"
                @reordered="saveOrder"
            >
                <template #default="{ item: value }">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <Link
                            :href="editValue(value.id)"
                            class="font-medium hover:underline"
                        >
                            {{ value.name }}
                        </Link>
                        <span
                            v-if="value.parent_id"
                            class="text-muted-foreground"
                        >
                            ← {{ parentName(value.parent_id) }}
                        </span>
                        <a
                            v-if="value.url"
                            :href="value.url"
                            target="_blank"
                            class="inline-flex items-center gap-1"
                        >
                            <Badge variant="outline">
                                {{ $t('Landing page') }}
                                <ExternalLink />
                            </Badge>
                        </a>
                        <span class="ml-auto text-muted-foreground">
                            {{
                                $t(':active of :total', {
                                    active: value.active_products_count,
                                    total: value.products_count,
                                })
                            }}
                        </span>
                    </div>
                </template>
            </SortableList>
            <p v-else class="text-sm text-muted-foreground">
                {{ $t('No values yet.') }}
            </p>
        </div>

        <div v-if="group && !values.length" class="max-w-xl space-y-4">
            <Heading variant="small" :title="$t('Delete filter')" />
            <ConfirmDelete
                :action="destroy(group.id)"
                :title="$t('Delete :name?', { name: group.name })"
            />
        </div>
    </div>
</template>
