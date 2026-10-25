<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref, watch } from 'vue';
import SortableList from '@/components/admin/catalog/SortableList.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index, reorder } from '@/routes/admin/roasters';

type RoasterRow = {
    id: number;
    name: string;
    city: string;
    is_partner: boolean;
    is_active: boolean;
    products_count: number;
    locations_count: number;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Roasters', href: index() }],
    },
});

const props = defineProps<{ roasters: RoasterRow[] }>();

const roasters = ref([...props.roasters]);
watch(
    () => props.roasters,
    (value) => (roasters.value = [...value]),
);

const saveOrder = (ids: number[]) => {
    router.put(reorder.url(), { ids }, { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('Roasters')" />

    <div class="space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-0.5">
                <h2 class="text-xl font-semibold tracking-tight">
                    {{ $t('Roasters') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Who roasts the coffee and the places orders ship from.',
                        )
                    }}
                </p>
            </div>
            <Button as-child>
                <Link :href="create()"><Plus /> {{ $t('New roaster') }}</Link>
            </Button>
        </div>

        <SortableList v-model="roasters" @reordered="saveOrder">
            <template #default="{ item }">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <Link
                        :href="edit(item.id)"
                        class="font-medium hover:underline"
                    >
                        {{ item.name }}
                    </Link>
                    <span class="text-muted-foreground">{{ item.city }}</span>
                    <Badge v-if="item.is_partner" variant="outline">
                        {{ $t('Partner') }}
                    </Badge>
                    <Badge v-if="!item.is_active" variant="secondary">
                        {{ $t('Hidden') }}
                    </Badge>
                    <span class="ml-auto text-muted-foreground">
                        {{
                            $t(':products products, :locations locations', {
                                products: item.products_count,
                                locations: item.locations_count,
                            })
                        }}
                    </span>
                </div>
            </template>
        </SortableList>
    </div>
</template>
