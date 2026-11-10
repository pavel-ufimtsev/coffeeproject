<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import CheckboxField from '@/components/admin/catalog/CheckboxField.vue';
import ConfirmDelete from '@/components/admin/catalog/ConfirmDelete.vue';
import Field from '@/components/admin/catalog/Field.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { slugify } from '@/lib/catalog';
import {
    create,
    destroy,
    edit,
    index,
    store,
    update,
} from '@/routes/admin/blog/categories';
import { index as postsIndex } from '@/routes/admin/blog/posts';

const props = defineProps<{
    category: {
        id: number;
        slug: string;
        name: string;
        description: string | null;
        seo_title: string | null;
        seo_description: string | null;
        sort: number;
        is_active: boolean;
        url: string;
        posts: number;
    } | null;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Blog', href: postsIndex() },
        { title: 'Sections', href: index() },
        props.category
            ? { title: props.category.name, href: edit(props.category.id) }
            : { title: 'New section', href: create() },
    ],
});

const form = useForm({
    name: props.category?.name ?? '',
    slug: props.category?.slug ?? '',
    description: props.category?.description ?? '',
    seo_title: props.category?.seo_title ?? '',
    seo_description: props.category?.seo_description ?? '',
    sort: props.category?.sort ?? 100,
    is_active: props.category?.is_active ?? true,
});

const slugTouched = ref(props.category !== null);
watch(
    () => form.name,
    (name) => {
        if (!slugTouched.value) {
            form.slug = slugify(name);
        }
    },
);

const submit = () =>
    form.submit(props.category ? update(props.category.id) : store(), {
        preserveScroll: true,
    });
</script>

<template>
    <Head :title="category?.name ?? $t('New section')" />

    <div class="space-y-10 p-4">
        <form class="max-w-xl space-y-6" @submit.prevent="submit">
            <Heading :title="category?.name ?? $t('New section')" />
            <Field id="name" :label="$t('Name')" :error="form.errors.name">
                <Input id="name" v-model="form.name" required />
            </Field>
            <Field
                id="slug"
                :label="$t('Address')"
                :hint="
                    category
                        ? $t('Changing it redirects the old address.')
                        : undefined
                "
                :error="form.errors.slug"
            >
                <div class="flex items-center gap-1">
                    <span class="text-sm text-muted-foreground">/blog/c/</span>
                    <Input
                        id="slug"
                        v-model="form.slug"
                        required
                        class="font-mono text-sm"
                        @input="slugTouched = true"
                    />
                </div>
            </Field>
            <Field
                id="description"
                :label="$t('Description')"
                :hint="$t('Shown at the top of the section page.')"
                :error="form.errors.description"
            >
                <Textarea
                    id="description"
                    v-model="form.description"
                    rows="3"
                />
            </Field>
            <Field
                id="seo_title"
                :label="$t('Page title')"
                :error="form.errors.seo_title"
            >
                <Input id="seo_title" v-model="form.seo_title" />
            </Field>
            <Field
                id="seo_description"
                :label="$t('Search snippet')"
                :error="form.errors.seo_description"
            >
                <Textarea
                    id="seo_description"
                    v-model="form.seo_description"
                    rows="2"
                />
            </Field>
            <Field
                id="sort"
                :label="$t('Position')"
                :hint="$t('Smaller numbers come first.')"
                :error="form.errors.sort"
            >
                <Input
                    id="sort"
                    v-model.number="form.sort"
                    type="number"
                    min="0"
                    class="w-32"
                />
            </Field>
            <CheckboxField
                v-model="form.is_active"
                :label="$t('Shown on the site')"
                :hint="$t('A hidden section redirects to the blog.')"
            />
            <Button :disabled="form.processing">
                {{ category ? $t('Save changes') : $t('Create section') }}
            </Button>
        </form>

        <div v-if="category && !category.posts" class="max-w-xl space-y-4">
            <Heading variant="small" :title="$t('Delete section')" />
            <ConfirmDelete
                :action="destroy(category.id)"
                :title="$t('Delete :name?', { name: category.name })"
            />
        </div>
    </div>
</template>
