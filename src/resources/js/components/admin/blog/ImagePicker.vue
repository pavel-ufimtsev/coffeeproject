<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { ImagePlus, LoaderCircle } from '@lucide/vue';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { requestJson } from '@/lib/blog';
import { library, store } from '@/routes/admin/blog/images';
import type { BlogImage, Paginated } from '@/types';

/**
 * Pick an image from the blog library, or upload a new one.
 */
const open = defineModel<boolean>('open', { required: true });

defineProps<{ title: string }>();

const emit = defineEmits<{ select: [image: BlogImage] }>();

const search = ref('');
const images = ref<BlogImage[]>([]);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const uploading = ref(false);
const error = ref<string>();

const load = async (reset = true) => {
    loading.value = true;
    error.value = undefined;

    try {
        const next = reset ? 1 : page.value + 1;
        const result = await requestJson<Paginated<BlogImage>>(
            library.url({
                query: { search: search.value || undefined, page: next },
            }),
        );
        images.value = reset ? result.data : [...images.value, ...result.data];
        page.value = result.current_page;
        lastPage.value = result.last_page;
    } catch (e) {
        error.value = e instanceof Error ? e.message : String(e);
    } finally {
        loading.value = false;
    }
};

watch(open, (isOpen) => isOpen && load());
watch(
    search,
    useDebounceFn(() => load(), 300),
);

const upload = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    uploading.value = true;
    error.value = undefined;

    try {
        const data = new FormData();
        data.append('file', file);
        const image = await requestJson<BlogImage>(store.url(), {
            method: 'POST',
            body: data,
        });
        emit('select', image);
        open.value = false;
    } catch (e) {
        error.value = e instanceof Error ? e.message : String(e);
    } finally {
        uploading.value = false;
        input.value = '';
    }
};

const choose = (image: BlogImage) => {
    emit('select', image);
    open.value = false;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Choose a picture from the library or upload a new one. It is stored as WebP.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="flex flex-wrap items-center gap-3">
                <Input
                    v-model="search"
                    type="search"
                    class="max-w-xs"
                    :placeholder="$t('Search by description or file name')"
                    :aria-label="$t('Search images')"
                />
                <label
                    class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-md border px-3 text-sm hover:bg-accent"
                >
                    <LoaderCircle
                        v-if="uploading"
                        class="size-4 animate-spin"
                    />
                    <ImagePlus v-else class="size-4" />
                    {{ $t('Upload') }}
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/gif,image/webp"
                        class="sr-only"
                        :disabled="uploading"
                        @change="upload"
                    />
                </label>
            </div>
            <InputError :message="error" />

            <div
                class="grid max-h-[60vh] grid-cols-3 gap-2 overflow-y-auto sm:grid-cols-4 md:grid-cols-6"
            >
                <button
                    v-for="image in images"
                    :key="image.id"
                    type="button"
                    class="group overflow-hidden rounded-md border text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :title="image.alt ?? image.original_name ?? ''"
                    @click="choose(image)"
                >
                    <img
                        :src="image.thumb"
                        :alt="image.alt ?? ''"
                        class="aspect-square w-full object-cover transition group-hover:opacity-80"
                        loading="lazy"
                    />
                </button>
                <p
                    v-if="!loading && !images.length"
                    class="col-span-full py-6 text-center text-sm text-muted-foreground"
                >
                    {{ $t('No images found.') }}
                </p>
            </div>
            <Button
                v-if="page < lastPage"
                variant="outline"
                size="sm"
                :disabled="loading"
                @click="load(false)"
            >
                {{ $t('Show more') }}
            </Button>
        </DialogContent>
    </Dialog>
</template>
