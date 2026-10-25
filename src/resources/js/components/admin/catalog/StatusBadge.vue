<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { ProductStatus } from '@/types';

defineProps<{
    status: ProductStatus;
    label: string;
}>();
</script>

<template>
    <Badge
        :variant="
            status === 'active'
                ? 'default'
                : status === 'draft'
                  ? 'outline'
                  : 'secondary'
        "
    >
        {{ label }}
    </Badge>
</template>
