<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ExternalLink, RefreshCw } from '@lucide/vue';
import { computed, watch } from 'vue';
import CheckboxField from '@/components/admin/catalog/CheckboxField.vue';
import ConfirmDelete from '@/components/admin/catalog/ConfirmDelete.vue';
import Field from '@/components/admin/catalog/Field.vue';
import NativeSelect from '@/components/admin/catalog/NativeSelect.vue';
import ProductImages from '@/components/admin/catalog/ProductImages.vue';
import RetailCrmLinkDialog from '@/components/admin/catalog/RetailCrmLinkDialog.vue';
import SortableList from '@/components/admin/catalog/SortableList.vue';
import StatusBadge from '@/components/admin/catalog/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatPrice } from '@/lib/catalog';
import { destroy, edit, index, update } from '@/routes/admin/products';
import { update as refreshPrices } from '@/routes/admin/products/retailcrm';
import type {
    CategoryOption,
    EditableProduct,
    FilterGroupOption,
    NamedRecord,
    Option,
    ProductTypeOption,
    ProductVariant,
} from '@/types';

const props = defineProps<{
    product: EditableProduct;
    types: ProductTypeOption[];
    statuses: Option[];
    categories: CategoryOption[];
    roasters: NamedRecord[];
    locations: NamedRecord[];
    filterGroups: FilterGroupOption[];
    collections: (NamedRecord & { category_id: number })[];
    retailcrmUrl: string | null;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Products', href: index() },
        { title: props.product.name, href: edit(props.product.id) },
    ],
});

const cloneVariants = (variants: ProductVariant[]) =>
    variants.map((variant) => ({
        ...variant,
        location_ids: [...variant.location_ids],
    }));

const form = useForm({
    name: props.product.name,
    slug: props.product.slug,
    type: props.product.type,
    status: props.product.status,
    category_id: props.product.category_id,
    roaster_id: props.product.roaster_id,
    tagline: props.product.tagline,
    summary: props.product.summary,
    description: props.product.description,
    specs: { ...props.product.specs } as Record<string, string>,
    is_featured: props.product.is_featured,
    sort: props.product.sort,
    seo_title: props.product.seo_title,
    seo_description: props.product.seo_description,
    filter_value_ids: [...props.product.filter_value_ids],
    collection_ids: [...props.product.collection_ids],
    variants: cloneVariants(props.product.variants),
});

// Linking retailCRM replaces the sizes on the server; show the new ones.
watch(
    () => props.product.variants,
    (variants) => (form.variants = cloneVariants(variants)),
);

const specFields = computed(
    () =>
        props.types.find((type) => type.value === form.type)?.spec_fields ?? {},
);
const rootId = computed(
    () => props.categories.find((c) => c.id === form.category_id)?.root_id,
);
const groups = computed(() =>
    props.filterGroups.filter((group) => group.category_id === rootId.value),
);
const rootCollections = computed(() =>
    props.collections.filter((c) => c.category_id === rootId.value),
);

const toggleId = (list: number[], id: number, on: boolean) =>
    on ? [...new Set([...list, id])] : list.filter((item) => item !== id);

const save = () => {
    form.transform((data) => ({
        ...data,
        variants: data.variants.map((variant) => ({
            id: variant.id,
            label: variant.label,
            weight_g: variant.weight_g || null,
            is_active: variant.is_active,
            location_ids: variant.location_ids,
        })),
    })).submit(update(props.product.id), { preserveScroll: true });
};

// Errors of nested fields ("specs.country", "variants.0.label") are keyed by path.
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);
const variantError = (index: number) =>
    Object.entries(errors.value).find(([key]) =>
        key.startsWith(`variants.${index}.`),
    )?.[1];

const syncStatusLabel: Record<string, string> = {
    missing: 'Offer not found in retailCRM',
    inactive: 'Off in retailCRM',
    no_price: 'No price in retailCRM',
};
</script>

<template>
    <Head :title="product.name" />

    <form class="space-y-6 p-4 pb-24" @submit.prevent="save">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <h2 class="flex items-center gap-2 text-xl font-semibold">
                    {{ product.name }}
                    <StatusBadge
                        :status="product.status"
                        :label="
                            statuses.find((s) => s.value === product.status)
                                ?.label ?? product.status
                        "
                    />
                </h2>
                <a
                    :href="product.url"
                    target="_blank"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                >
                    {{
                        product.status === 'draft'
                            ? $t('Preview on the site')
                            : $t('Open on the site')
                    }}
                    <ExternalLink class="size-3.5" />
                </a>
            </div>
        </div>

        <div
            v-if="product.problems.length"
            class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
        >
            <p
                v-for="problem in product.problems"
                :key="problem"
                class="flex items-center gap-2"
            >
                <AlertTriangle class="size-4 shrink-0" /> {{ problem }}
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ $t('Content') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-6">
                        <Field
                            id="name"
                            :label="$t('Name')"
                            :error="form.errors.name"
                        >
                            <Input id="name" v-model="form.name" required />
                        </Field>
                        <Field
                            id="slug"
                            :label="$t('Address')"
                            :error="form.errors.slug"
                            :hint="
                                $t(
                                    'Latin letters, digits and hyphens. If you change it, the old address redirects here.',
                                )
                            "
                        >
                            <Input id="slug" v-model="form.slug" required />
                        </Field>
                        <Field
                            id="tagline"
                            :label="$t('Tagline')"
                            :error="form.errors.tagline"
                            :hint="$t('A few words above the name.')"
                        >
                            <Input id="tagline" v-model="form.tagline" />
                        </Field>
                        <Field
                            id="summary"
                            :label="$t('Short description')"
                            :error="form.errors.summary"
                            :hint="$t('Shown next to the price.')"
                        >
                            <Textarea
                                id="summary"
                                v-model="form.summary"
                                rows="4"
                            />
                        </Field>
                        <Field
                            id="description"
                            :label="$t('Full description (HTML)')"
                            :error="form.errors.description"
                        >
                            <Textarea
                                id="description"
                                v-model="form.description"
                                rows="10"
                                class="font-mono text-xs"
                            />
                        </Field>
                    </CardContent>
                </Card>

                <Card v-if="Object.keys(specFields).length">
                    <CardHeader>
                        <CardTitle>{{ $t('Specs') }}</CardTitle>
                        <CardDescription>
                            {{ $t('Empty fields are not shown on the site.') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <Field
                            v-for="(label, key) in specFields"
                            :id="`spec-${key}`"
                            :key="key"
                            :label="label"
                            :error="errors[`specs.${key}`]"
                            :class="key === 'tasting_notes' && 'sm:col-span-2'"
                        >
                            <Input
                                :id="`spec-${key}`"
                                v-model="form.specs[key]"
                            />
                        </Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ $t('Sizes and prices') }}</CardTitle>
                        <CardDescription>
                            <template v-if="product.retailcrm_product_id">
                                {{
                                    $t(
                                        'Linked to retailCRM product #:id «:name».',
                                        {
                                            id: product.retailcrm_product_id,
                                            name: product.retailcrm_name ?? '',
                                        },
                                    )
                                }}
                            </template>
                            <template v-else>
                                {{
                                    $t(
                                        'Not linked yet. Create the product in retailCRM first, then link it here.',
                                    )
                                }}
                            </template>
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <SortableList
                            v-if="form.variants.length"
                            v-model="form.variants"
                        >
                            <template #default="{ item: variant, index: i }">
                                <div
                                    class="grid items-center gap-3 sm:grid-cols-[1fr_6rem_8rem]"
                                >
                                    <Input
                                        v-model="variant.label"
                                        :aria-label="$t('Size label')"
                                    />
                                    <Input
                                        v-model.number="variant.weight_g"
                                        type="number"
                                        min="1"
                                        :placeholder="$t('grams')"
                                        :aria-label="$t('Weight, g')"
                                    />
                                    <div class="text-right tabular-nums">
                                        {{ formatPrice(variant.price) }}
                                        <s
                                            v-if="variant.old_price"
                                            class="block text-xs text-muted-foreground"
                                            >{{
                                                formatPrice(variant.old_price)
                                            }}</s
                                        >
                                    </div>
                                </div>
                                <div
                                    class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm"
                                >
                                    <CheckboxField
                                        v-model="variant.is_active"
                                        :label="$t('Shown on the site')"
                                    />
                                    <CheckboxField
                                        v-for="location in locations"
                                        :key="location.id"
                                        :model-value="
                                            variant.location_ids.includes(
                                                location.id,
                                            )
                                        "
                                        :label="
                                            $t('Ships from :place', {
                                                place: location.name,
                                            })
                                        "
                                        @update:model-value="
                                            variant.location_ids = toggleId(
                                                variant.location_ids,
                                                location.id,
                                                $event,
                                            )
                                        "
                                    />
                                    <span class="text-xs text-muted-foreground">
                                        {{
                                            $t('Offer #:id', {
                                                id:
                                                    variant.retailcrm_offer_id ??
                                                    '—',
                                            })
                                        }}
                                    </span>
                                    <Badge
                                        v-if="
                                            variant.sync_status &&
                                            variant.sync_status !== 'ok'
                                        "
                                        variant="destructive"
                                    >
                                        {{
                                            $t(
                                                syncStatusLabel[
                                                    variant.sync_status
                                                ],
                                            )
                                        }}
                                    </Badge>
                                </div>
                                <InputError :message="variantError(i)" />
                            </template>
                        </SortableList>

                        <div class="flex flex-wrap gap-2">
                            <RetailCrmLinkDialog
                                :product-id="product.id"
                                :product-name="product.name"
                                :linked="product.retailcrm_product_id !== null"
                                :linked-offer-ids="
                                    product.variants
                                        .map((v) => v.retailcrm_offer_id)
                                        .filter((id) => id !== null)
                                "
                            />
                            <Button
                                v-if="product.retailcrm_product_id"
                                type="button"
                                variant="outline"
                                @click="
                                    router.put(
                                        refreshPrices.url(product.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                "
                            >
                                <RefreshCw />
                                {{ $t('Refresh prices') }}
                            </Button>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{
                                $t(
                                    'Prices come from retailCRM and update every 30 minutes. Change them in retailCRM.',
                                )
                            }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ $t('Photos') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ProductImages
                            :product-id="product.id"
                            :images="product.images"
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ $t('Search engines') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-6">
                        <Field
                            id="seo_title"
                            :label="$t('SEO title')"
                            :error="form.errors.seo_title"
                            :hint="
                                $t(
                                    ':count characters. Search engines show about 60.',
                                    { count: form.seo_title?.length ?? 0 },
                                )
                            "
                        >
                            <Input
                                id="seo_title"
                                v-model="form.seo_title"
                                :placeholder="
                                    $t(
                                        'Generated from the name and price when empty',
                                    )
                                "
                            />
                        </Field>
                        <Field
                            id="seo_description"
                            :label="$t('SEO description')"
                            :error="form.errors.seo_description"
                            :hint="
                                $t(
                                    ':count characters. Search engines show about 160.',
                                    {
                                        count:
                                            form.seo_description?.length ?? 0,
                                    },
                                )
                            "
                        >
                            <Textarea
                                id="seo_description"
                                v-model="form.seo_description"
                                rows="3"
                                :placeholder="
                                    $t(
                                        'Taken from the short description when empty',
                                    )
                                "
                            />
                        </Field>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ $t('Publishing') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <Field
                            id="status"
                            :label="$t('Status')"
                            :error="form.errors.status"
                        >
                            <NativeSelect id="status" v-model="form.status">
                                <option
                                    v-for="option in statuses"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </NativeSelect>
                        </Field>
                        <p class="text-sm text-muted-foreground">
                            {{
                                $t(
                                    'Draft: hidden. Active: listed and for sale. Archived: not listed, but the page stays so old links work.',
                                )
                            }}
                        </p>
                        <CheckboxField
                            v-model="form.is_featured"
                            :label="$t('Featured')"
                            :hint="
                                $t(
                                    'Shown first in the catalog and on the home page.',
                                )
                            "
                        />
                        <Field
                            id="sort"
                            :label="$t('Position in the catalog')"
                            :error="form.errors.sort"
                            :hint="$t('Lower numbers come first.')"
                        >
                            <Input
                                id="sort"
                                v-model.number="form.sort"
                                type="number"
                                min="0"
                            />
                        </Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ $t('Placement') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <Field
                            id="category_id"
                            :label="$t('Category')"
                            :error="form.errors.category_id"
                        >
                            <NativeSelect
                                id="category_id"
                                v-model="form.category_id"
                            >
                                <option
                                    v-for="option in categories"
                                    :key="option.id"
                                    :value="option.id"
                                >
                                    {{ option.label }}
                                </option>
                            </NativeSelect>
                        </Field>
                        <Field
                            id="type"
                            :label="$t('Type')"
                            :error="form.errors.type"
                            :hint="$t('Decides which specs the product has.')"
                        >
                            <NativeSelect id="type" v-model="form.type">
                                <option
                                    v-for="option in types"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </NativeSelect>
                        </Field>
                        <Field
                            id="roaster_id"
                            :label="$t('Roaster')"
                            :error="form.errors.roaster_id"
                        >
                            <NativeSelect
                                id="roaster_id"
                                v-model="form.roaster_id"
                            >
                                <option :value="null">{{ $t('None') }}</option>
                                <option
                                    v-for="roaster in roasters"
                                    :key="roaster.id"
                                    :value="roaster.id"
                                >
                                    {{ roaster.name }}
                                </option>
                            </NativeSelect>
                        </Field>
                    </CardContent>
                </Card>

                <Card v-if="groups.length">
                    <CardHeader>
                        <CardTitle>{{ $t('Filters') }}</CardTitle>
                        <CardDescription>
                            {{
                                $t(
                                    'A country also adds its region (Kenya adds Africa) when you save.',
                                )
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-5">
                        <fieldset
                            v-for="group in groups"
                            :key="group.id"
                            class="grid gap-2"
                        >
                            <legend class="mb-2 text-sm font-medium">
                                {{ group.name }}
                            </legend>
                            <CheckboxField
                                v-for="value in group.values"
                                :key="value.id"
                                :class="value.parent_id && 'ml-6'"
                                :model-value="
                                    form.filter_value_ids.includes(value.id)
                                "
                                :label="value.name"
                                @update:model-value="
                                    form.filter_value_ids = toggleId(
                                        form.filter_value_ids,
                                        value.id,
                                        $event,
                                    )
                                "
                            />
                        </fieldset>
                    </CardContent>
                </Card>

                <Card v-if="rootCollections.length">
                    <CardHeader>
                        <CardTitle>{{ $t('Collections') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-2">
                        <CheckboxField
                            v-for="collection in rootCollections"
                            :key="collection.id"
                            :model-value="
                                form.collection_ids.includes(collection.id)
                            "
                            :label="collection.name"
                            @update:model-value="
                                form.collection_ids = toggleId(
                                    form.collection_ids,
                                    collection.id,
                                    $event,
                                )
                            "
                        />
                    </CardContent>
                </Card>

                <Card v-if="product.status === 'draft'">
                    <CardHeader>
                        <CardTitle>{{ $t('Delete draft') }}</CardTitle>
                        <CardDescription>
                            {{
                                $t(
                                    'Only drafts can be deleted. Archive products that were on sale.',
                                )
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ConfirmDelete
                            :action="destroy(product.id)"
                            :title="$t('Delete :name?', { name: product.name })"
                            :description="
                                $t('The draft and its photos are removed.')
                            "
                        />
                    </CardContent>
                </Card>
            </div>
        </div>

        <div
            class="fixed inset-x-0 bottom-0 z-10 border-t bg-background/95 p-3 backdrop-blur md:left-(--sidebar-width)"
        >
            <div class="flex items-center justify-end gap-3 px-4">
                <span v-if="form.isDirty" class="text-sm text-muted-foreground">
                    {{ $t('Unsaved changes') }}
                </span>
                <Button :disabled="form.processing">{{
                    $t('Save changes')
                }}</Button>
            </div>
        </div>
    </form>
</template>
