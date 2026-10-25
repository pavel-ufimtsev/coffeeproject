<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

defineProps<{
    label: string;
    hint?: string;
    disabled?: boolean;
}>();

const checked = defineModel<boolean>({ required: true });
</script>

<template>
    <Label class="items-start gap-3 font-normal">
        <Checkbox
            :model-value="checked"
            :disabled="disabled"
            class="mt-0.5"
            @update:model-value="checked = $event === true"
        />
        <span class="grid gap-1 leading-snug">
            <span>{{ label }}</span>
            <span v-if="hint" class="text-sm text-muted-foreground">
                {{ hint }}
            </span>
        </span>
    </Label>
</template>
