<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const props = defineProps<{
    action: { url: string };
    title: string;
    description?: string;
    label?: string;
    icon?: boolean;
}>();

const open = ref(false);
const processing = ref(false);

const confirm = () => {
    router.delete(props.action.url, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => {
            processing.value = false;
            open.value = false;
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                v-if="icon"
                type="button"
                variant="ghost"
                size="icon"
                class="size-7 text-muted-foreground hover:text-destructive"
                :aria-label="label ?? $t('Delete')"
            >
                <Trash2 />
            </Button>
            <Button v-else type="button" variant="destructive">
                {{ label ?? $t('Delete') }}
            </Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button type="button" variant="secondary">
                        {{ $t('Cancel') }}
                    </Button>
                </DialogClose>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="processing"
                    @click="confirm"
                >
                    {{ $t('Delete') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
