<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { BlogPostStatus } from '@/types';

defineProps<{
    status: BlogPostStatus;
    label: string;
}>();
</script>

<template>
    <Badge
        :variant="
            status === 'published'
                ? 'default'
                : status === 'archived'
                  ? 'secondary'
                  : 'outline'
        "
    >
        {{ label }}
    </Badge>
</template>
