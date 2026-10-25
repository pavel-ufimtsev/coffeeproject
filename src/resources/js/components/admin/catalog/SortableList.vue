<script setup lang="ts" generic="T extends { id: number }">
import {
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    GripVertical,
} from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * A list the user can reorder by dragging the handle or with the arrow
 * buttons. Emits `reordered` with the new ID order once a move is finished.
 */
const props = defineProps<{
    grid?: boolean;
    class?: string;
}>();

const items = defineModel<T[]>({ required: true });

const emit = defineEmits<{
    reordered: [ids: number[]];
}>();

// Only the handle starts a drag, so text in the item can still be selected.
const armed = ref<number | null>(null);
const dragging = ref<number | null>(null);

const move = (from: number, to: number) => {
    if (to < 0 || to >= items.value.length || from === to) {
        return;
    }

    const next = [...items.value];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);
    items.value = next;
};

const finish = () =>
    emit(
        'reordered',
        items.value.map((item) => item.id),
    );

const onDragStart = (index: number, event: DragEvent) => {
    dragging.value = index;
    event.dataTransfer?.setData('text/plain', String(items.value[index].id));

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
};

const onDragEnter = (index: number) => {
    if (dragging.value === null || dragging.value === index) {
        return;
    }

    move(dragging.value, index);
    dragging.value = index;
};

const onDragEnd = () => {
    const moved = dragging.value !== null;
    dragging.value = null;
    armed.value = null;

    if (moved) {
        finish();
    }
};

const step = (index: number, delta: number) => {
    move(index, index + delta);
    finish();
};
</script>

<template>
    <ul
        :class="
            cn(
                props.grid
                    ? 'grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4'
                    : 'divide-y rounded-xl border',
                props.class,
            )
        "
    >
        <li
            v-for="(item, index) in items"
            :key="item.id"
            :draggable="armed === index"
            :class="[
                'bg-background',
                props.grid
                    ? 'flex flex-col gap-2 rounded-xl border p-2'
                    : 'flex items-center gap-3 px-3 py-2',
                dragging === index && 'opacity-50',
            ]"
            @dragstart="onDragStart(index, $event)"
            @dragenter.prevent="onDragEnter(index)"
            @dragover.prevent
            @dragend="onDragEnd"
        >
            <template v-if="!props.grid">
                <GripVertical
                    class="size-4 shrink-0 cursor-grab text-muted-foreground"
                    aria-hidden="true"
                    @pointerdown="armed = index"
                    @pointerup="armed = null"
                />
                <div class="min-w-0 flex-1">
                    <slot :item="item" :index="index" />
                </div>
            </template>
            <template v-else>
                <div
                    class="cursor-grab"
                    @pointerdown="armed = index"
                    @pointerup="armed = null"
                >
                    <slot name="handle" :item="item" :index="index" />
                </div>
                <slot :item="item" :index="index" />
            </template>

            <div :class="['flex shrink-0', props.grid && 'justify-between']">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-7"
                    :disabled="index === 0"
                    :aria-label="$t('Move up')"
                    @click="step(index, -1)"
                >
                    <ArrowLeft v-if="props.grid" />
                    <ArrowUp v-else />
                </Button>
                <slot name="actions" :item="item" :index="index" />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-7"
                    :disabled="index === items.length - 1"
                    :aria-label="$t('Move down')"
                    @click="step(index, 1)"
                >
                    <ArrowRight v-if="props.grid" />
                    <ArrowDown v-else />
                </Button>
            </div>
        </li>
    </ul>
</template>
