<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, Plus } from '@lucide/vue';
import { ref, watch } from 'vue';
import SortableList from '@/components/admin/catalog/SortableList.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index, reorder } from '@/routes/admin/categories';

type CategoryRow = {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    url: string;
    products_count: number;
    active_products_count: number;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Categories', href: index() }],
    },
});

const props = defineProps<{
    roots: (CategoryRow & { children: CategoryRow[] })[];
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
    <Head :title="$t('Categories')" />

    <div class="space-y-6 p-4">
        <div class="space-y-0.5">
            <h2 class="text-xl font-semibold tracking-tight">
                {{ $t('Categories') }}
            </h2>
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Coffee, Tea and Accessories are the top sections; their addresses are fixed. Drag to change the order.',
                    )
                }}
            </p>
        </div>

        <SortableList v-model="roots" @reordered="saveOrder">
            <template #default="{ item: root }">
                <div class="space-y-3 py-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="edit(root.id)"
                            class="font-semibold hover:underline"
                        >
                            {{ root.name }}
                        </Link>
                        <a
                            :href="root.url"
                            target="_blank"
                            class="text-sm text-muted-foreground hover:underline"
                        >
                            /{{ root.slug }}
                            <ExternalLink class="inline size-3" />
                        </a>
                        <Badge v-if="!root.is_active" variant="secondary">
                            {{ $t('Hidden') }}
                        </Badge>
                        <span class="text-sm text-muted-foreground">
                            {{
                                $t(':active of :total products on sale', {
                                    active: root.active_products_count,
                                    total: root.products_count,
                                })
                            }}
                        </span>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="ml-auto"
                            as-child
                        >
                            <Link
                                :href="create({ query: { parent: root.id } })"
                            >
                                <Plus /> {{ $t('Subcategory') }}
                            </Link>
                        </Button>
                    </div>

                    <SortableList
                        v-if="root.children.length"
                        v-model="root.children"
                        @reordered="saveOrder"
                    >
                        <template #default="{ item: child }">
                            <div
                                class="flex flex-wrap items-center gap-2 text-sm"
                            >
                                <Link
                                    :href="edit(child.id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ child.name }}
                                </Link>
                                <span class="text-muted-foreground">
                                    /{{ root.slug }}/{{ child.slug }}
                                </span>
                                <Badge
                                    v-if="!child.is_active"
                                    variant="secondary"
                                >
                                    {{ $t('Hidden') }}
                                </Badge>
                                <span class="ml-auto text-muted-foreground">
                                    {{
                                        $t(':active of :total', {
                                            active: child.active_products_count,
                                            total: child.products_count,
                                        })
                                    }}
                                </span>
                            </div>
                        </template>
                    </SortableList>
                </div>
            </template>
        </SortableList>
    </div>
</template>
