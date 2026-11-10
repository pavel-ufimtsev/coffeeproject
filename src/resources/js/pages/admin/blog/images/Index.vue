<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Copy, ImagePlus, LoaderCircle } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import BlogTabs from '@/components/admin/blog/BlogTabs.vue';
import CheckboxField from '@/components/admin/catalog/CheckboxField.vue';
import ConfirmDelete from '@/components/admin/catalog/ConfirmDelete.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/lib/i18n';
import {
    edit as editPost,
    index as postsIndex,
} from '@/routes/admin/blog/posts';
import { destroy, index, store, update } from '@/routes/admin/blog/images';
import type { BlogImage, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Blog', href: postsIndex() },
            { title: 'Images', href: index() },
        ],
    },
});

const props = defineProps<{
    images: Paginated<BlogImage>;
    filters: { search: string; unused: boolean };
    total: number;
}>();

const t = useTrans();
const search = ref(props.filters.search);
const unused = ref(props.filters.unused);

const applyFilters = () =>
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            unused: unused.value ? 1 : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );

watch(search, useDebounceFn(applyFilters, 300));
watch(unused, applyFilters);

const uploading = ref(false);
const uploadError = ref<string>();

const upload = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    router.post(
        store.url(),
        { file },
        {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => {
                uploading.value = true;
                uploadError.value = undefined;
            },
            onError: (errors) => (uploadError.value = Object.values(errors)[0]),
            onFinish: () => {
                uploading.value = false;
                input.value = '';
            },
        },
    );
};

const save = (image: BlogImage) =>
    router.patch(
        update.url(image.id),
        { alt: image.alt, caption: image.caption, credit: image.credit },
        { preserveScroll: true, preserveState: true },
    );

const copy = async (image: BlogImage) => {
    try {
        await navigator.clipboard.writeText(image.markdown);
        toast.success(t('Markdown copied.'));
    } catch {
        toast.error(t('Could not copy. Select the text by hand.'));
    }
};

const previousPage = computed(() => props.images.links.at(0)?.url ?? null);
const nextPage = computed(() => props.images.links.at(-1)?.url ?? null);
</script>

<template>
    <Head :title="$t('Blog images')" />

    <div class="space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-0.5">
                <h2 class="text-xl font-semibold tracking-tight">
                    {{ $t('Blog') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ $t(':count images, stored as WebP.', { count: total }) }}
                </p>
            </div>
            <label
                class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
            >
                <LoaderCircle v-if="uploading" class="size-4 animate-spin" />
                <ImagePlus v-else class="size-4" />
                {{ $t('Upload image') }}
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    class="sr-only"
                    :disabled="uploading"
                    @change="upload"
                />
            </label>
        </div>

        <BlogTabs />
        <InputError :message="uploadError" />

        <div class="flex flex-wrap items-center gap-3">
            <Input
                v-model="search"
                type="search"
                class="max-w-xs"
                :placeholder="$t('Search by description or file name')"
                :aria-label="$t('Search images')"
            />
            <CheckboxField
                v-model="unused"
                :label="$t('Not used by any post')"
            />
        </div>

        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
            <li
                v-for="image in images.data"
                :key="image.id"
                class="space-y-2 rounded-xl border p-3"
            >
                <a :href="image.url" target="_blank" rel="noopener">
                    <img
                        :src="image.thumb"
                        :alt="image.alt ?? ''"
                        class="aspect-video w-full rounded-md bg-muted object-cover"
                        loading="lazy"
                    />
                </a>
                <Input
                    v-model="image.alt"
                    class="h-8 text-xs"
                    :placeholder="$t('Description for screen readers')"
                    :aria-label="$t('Description')"
                    @change="save(image)"
                />
                <Input
                    v-model="image.caption"
                    class="h-8 text-xs"
                    :placeholder="$t('Caption')"
                    :aria-label="$t('Caption')"
                    @change="save(image)"
                />
                <Input
                    v-model="image.credit"
                    class="h-8 text-xs"
                    :placeholder="$t('Photo credit')"
                    :aria-label="$t('Photo credit')"
                    @change="save(image)"
                />
                <p class="text-xs text-muted-foreground">
                    {{ image.width }}×{{ image.height }}
                    <template v-if="image.uploaded_by">
                        · {{ image.uploaded_by }}</template
                    >
                </p>
                <p v-if="image.posts.length" class="text-xs">
                    {{ $t('Used in') }}:
                    <template v-for="(post, i) in image.posts" :key="post.id">
                        <Link :href="editPost(post.id)" class="underline">{{
                            post.title
                        }}</Link
                        ><template v-if="i < image.posts.length - 1"
                            >,
                        </template>
                    </template>
                </p>
                <div class="flex items-center justify-between">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="copy(image)"
                    >
                        <Copy /> {{ $t('Copy Markdown') }}
                    </Button>
                    <ConfirmDelete
                        v-if="!image.posts.length"
                        icon
                        :action="destroy(image.id)"
                        :title="$t('Delete this image?')"
                        :label="$t('Delete image')"
                    />
                </div>
            </li>
        </ul>
        <p
            v-if="!images.data.length"
            class="py-8 text-center text-muted-foreground"
        >
            {{ $t('No images found.') }}
        </p>

        <div
            v-if="images.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span>
                {{
                    $t(':from–:to of :total', {
                        from: images.from ?? 0,
                        to: images.to ?? 0,
                        total: images.total,
                    })
                }}
            </span>
            <div class="flex gap-2">
                <Button
                    v-if="previousPage"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="previousPage" preserve-scroll>{{
                        $t('Previous')
                    }}</Link>
                </Button>
                <Button v-if="nextPage" variant="outline" size="sm" as-child>
                    <Link :href="nextPage" preserve-scroll>{{
                        $t('Next')
                    }}</Link>
                </Button>
            </div>
        </div>
    </div>
</template>
