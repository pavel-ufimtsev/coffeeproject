<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ImagePlus } from '@lucide/vue';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import {
    destroy as destroyImage,
    update as updateImage,
} from '@/routes/admin/product-images';
import { reorder, store } from '@/routes/admin/products/images';
import type { ProductImage } from '@/types';
import ConfirmDelete from './ConfirmDelete.vue';
import SortableList from './SortableList.vue';

/**
 * Product photos. Every change is saved straight away, separately from the
 * product form. The first photo is the main one.
 */
const props = defineProps<{
    productId: number;
    images: ProductImage[];
}>();

const items = ref<ProductImage[]>([...props.images]);
watch(
    () => props.images,
    (images) => (items.value = [...images]),
);

const uploading = ref(false);
const error = ref<string>();
const input = ref<HTMLInputElement>();

const upload = (event: Event) => {
    const files = (event.target as HTMLInputElement).files;

    if (!files?.length) {
        return;
    }

    router.post(
        store.url(props.productId),
        { images: [...files] },
        {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => {
                uploading.value = true;
                error.value = undefined;
            },
            onError: (errors) => (error.value = Object.values(errors)[0]),
            onFinish: () => {
                uploading.value = false;

                if (input.value) {
                    input.value.value = '';
                }
            },
        },
    );
};

const saveOrder = (ids: number[]) => {
    router.put(reorder.url(props.productId), { ids }, { preserveScroll: true });
};

const saveAlt = (image: ProductImage) => {
    router.patch(
        updateImage.url(image.id),
        { alt: image.alt },
        { preserveScroll: true },
    );
};
</script>

<template>
    <div class="space-y-4">
        <SortableList
            v-if="items.length"
            v-model="items"
            grid
            @reordered="saveOrder"
        >
            <template #handle="{ item, index }">
                <div class="relative">
                    <img
                        :src="item.thumb"
                        :alt="item.alt ?? ''"
                        class="aspect-square w-full rounded-lg object-cover"
                        draggable="false"
                    />
                    <span
                        v-if="index === 0"
                        class="absolute top-1 left-1 rounded bg-background/90 px-1.5 py-0.5 text-xs font-medium"
                    >
                        {{ $t('Main') }}
                    </span>
                </div>
            </template>
            <template #default="{ item }">
                <Input
                    v-model="item.alt"
                    class="h-8 text-xs"
                    :placeholder="$t('Photo description')"
                    :aria-label="$t('Photo description')"
                    @change="saveAlt(item)"
                />
            </template>
            <template #actions="{ item }">
                <ConfirmDelete
                    icon
                    :action="destroyImage(item.id)"
                    :title="$t('Delete this photo?')"
                    :label="$t('Delete photo')"
                />
            </template>
        </SortableList>

        <label
            class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed p-6 text-sm text-muted-foreground hover:bg-muted/40"
            :class="uploading && 'pointer-events-none opacity-60'"
        >
            <ImagePlus class="size-4" />
            {{
                uploading
                    ? $t('Uploading…')
                    : $t('Add photos (JPEG, PNG or WebP, up to 15 MB each)')
            }}
            <input
                ref="input"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                multiple
                class="sr-only"
                @change="upload"
            />
        </label>
        <InputError :message="error" />
        <p class="text-sm text-muted-foreground">
            {{
                $t(
                    'Drag photos to change their order. The first one is shown in the catalog.',
                )
            }}
        </p>
    </div>
</template>
