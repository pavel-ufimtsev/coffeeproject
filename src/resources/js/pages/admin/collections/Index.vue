<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, Plus } from '@lucide/vue';
import { ref, watch } from 'vue';
import SortableList from '@/components/admin/catalog/SortableList.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index, reorder } from '@/routes/admin/collections';

type CollectionRow = {
    id: number;
    name: string;
    slug: string;
    is_landing: boolean;
    products_count: number;
    active_products_count: number;
    url: string | null;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Collections', href: index() }],
    },
});

const props = defineProps<{
    roots: { id: number; name: string; collections: CollectionRow[] }[];
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
    <Head :title="$t('Collections')" />

    <div class="space-y-8 p-4">
        <div class="space-y-0.5">
            <h2 class="text-xl font-semibold tracking-tight">
                {{ $t('Collections') }}
            </h2>
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Hand-picked groups of products, such as Sale or Decaf, with their own order.',
                    )
                }}
            </p>
        </div>

        <section v-for="root in roots" :key="root.id" class="space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h3 class="font-semibold">{{ root.name }}</h3>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="create({ query: { category: root.id } })">
                        <Plus /> {{ $t('New collection') }}
                    </Link>
                </Button>
            </div>
            <SortableList
                v-if="root.collections.length"
                v-model="root.collections"
                @reordered="saveOrder"
            >
                <template #default="{ item: collection }">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <Link
                            :href="edit(collection.id)"
                            class="font-medium hover:underline"
                        >
                            {{ collection.name }}
                        </Link>
                        <a
                            v-if="collection.url"
                            :href="collection.url"
                            target="_blank"
                        >
                            <Badge variant="outline">
                                {{ $t('Landing page') }} <ExternalLink />
                            </Badge>
                        </a>
                        <span class="ml-auto text-muted-foreground">
                            {{
                                $t(':active of :total', {
                                    active: collection.active_products_count,
                                    total: collection.products_count,
                                })
                            }}
                        </span>
                    </div>
                </template>
            </SortableList>
            <p v-else class="text-sm text-muted-foreground">
                {{ $t('No collections yet.') }}
            </p>
        </section>
    </div>
</template>
