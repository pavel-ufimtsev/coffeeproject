<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref, watch } from 'vue';
import SortableList from '@/components/admin/catalog/SortableList.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index, reorder } from '@/routes/admin/filter-groups';

type GroupRow = {
    id: number;
    name: string;
    slug: string;
    is_visible: boolean;
    values_count: number;
    landing_count: number;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Filters', href: index() }],
    },
});

const props = defineProps<{
    roots: { id: number; name: string; groups: GroupRow[] }[];
}>();

const roots = ref(props.roots.map((root) => ({ ...root })));
watch(
    () => props.roots,
    (value) => (roots.value = value.map((root) => ({ ...root }))),
);

const saveOrder = (ids: number[]) => {
    router.put(reorder.url(), { ids }, { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('Filters')" />

    <div class="space-y-8 p-4">
        <div class="space-y-0.5">
            <h2 class="text-xl font-semibold tracking-tight">
                {{ $t('Filters') }}
            </h2>
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Filters shoppers use in the catalog. Values marked as landing pages get their own address, e.g. /coffee/kenya.',
                    )
                }}
            </p>
        </div>

        <section v-for="root in roots" :key="root.id" class="space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h3 class="font-semibold">{{ root.name }}</h3>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="create({ query: { category: root.id } })">
                        <Plus /> {{ $t('New filter') }}
                    </Link>
                </Button>
            </div>
            <SortableList
                v-if="root.groups.length"
                v-model="root.groups"
                @reordered="saveOrder"
            >
                <template #default="{ item: group }">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <Link
                            :href="edit(group.id)"
                            class="font-medium hover:underline"
                        >
                            {{ group.name }}
                        </Link>
                        <span class="text-muted-foreground"
                            >?{{ group.slug }}=</span
                        >
                        <Badge v-if="!group.is_visible" variant="secondary">
                            {{ $t('Hidden') }}
                        </Badge>
                        <span class="ml-auto text-muted-foreground">
                            {{
                                $t(':values values, :landing landing pages', {
                                    values: group.values_count,
                                    landing: group.landing_count,
                                })
                            }}
                        </span>
                    </div>
                </template>
            </SortableList>
            <p v-else class="text-sm text-muted-foreground">
                {{ $t('No filters yet.') }}
            </p>
        </section>
    </div>
</template>
