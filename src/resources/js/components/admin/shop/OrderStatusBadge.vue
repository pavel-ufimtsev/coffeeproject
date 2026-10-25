<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

defineProps<{
    status: string;
    label: string;
}>();
</script>

<template>
    <Badge
        :variant="
            status === 'new'
                ? 'default'
                : status === 'cancelled'
                  ? 'outline'
                  : 'secondary'
        "
    >
        {{ label }}
    </Badge>
</template>
