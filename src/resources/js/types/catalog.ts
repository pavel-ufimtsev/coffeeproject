export type Option<T = string> = {
    value: T;
    label: string;
};

export type ProductStatus = 'draft' | 'active' | 'archived';

export type ProductTypeOption = Option & {
    spec_fields: Record<string, string>;
};

/** A category in tree order, as Category::options() gives it. */
export type CategoryOption = {
    id: number;
    name: string;
    label: string;
    root_id: number;
    depth: number;
    is_active: boolean;
};

export type NamedRecord = {
    id: number;
    name: string;
};

/** A product in short, for lists and pickers. */
export type ProductSummary = NamedRecord & {
    status: ProductStatus;
    status_label: string;
};

export type ProductListItem = ProductSummary & {
    tagline: string | null;
    thumb: string | null;
    category: string;
    type_label: string;
    min_price: number | null;
    problems: string[];
};

export type ProductImage = {
    id: number;
    url: string;
    thumb: string;
    alt: string | null;
};

export type ProductVariant = {
    id: number;
    label: string;
    weight_g: number | null;
    retailcrm_offer_id: number | null;
    is_active: boolean;
    sync_status: 'ok' | 'missing' | 'inactive' | 'no_price' | null;
    price: number | null;
    old_price: number | null;
    synced_at: string | null;
    location_ids: number[];
};

export type EditableProduct = {
    id: number;
    name: string;
    slug: string;
    type: string;
    status: ProductStatus;
    category_id: number;
    roaster_id: number | null;
    tagline: string | null;
    summary: string | null;
    description: string | null;
    specs: Record<string, string>;
    is_featured: boolean;
    sort: number;
    seo_title: string | null;
    seo_description: string | null;
    retailcrm_product_id: number | null;
    retailcrm_name: string | null;
    retailcrm_active: boolean | null;
    filter_value_ids: number[];
    collection_ids: number[];
    url: string;
    problems: string[];
    images: ProductImage[];
    variants: ProductVariant[];
};

export type FilterGroupOption = NamedRecord & {
    category_id: number;
    values: (NamedRecord & { parent_id: number | null })[];
};

/** Heading, intro and SEO texts every listing page has. */
export type LandingTexts = {
    h1: string | null;
    intro: string | null;
    seo_title: string | null;
    seo_description: string | null;
};

/** A product found in retailCRM by the link picker. */
export type CrmProduct = {
    id: number;
    name: string;
    article: string | null;
    active: boolean;
    linked_to: string | null;
    offers: {
        id: number;
        name: string;
        label: string;
        price: number | null;
        active: boolean;
        linked_to: string | null;
    }[];
};

export type SyncIssue = {
    product: string;
    variant: string | null;
    retailcrm_product_id: number | null;
    retailcrm_offer_id: number | null;
    problem: string;
};

export type SyncChange = {
    product: string;
    variant: string;
    from: { price: string | null; old_price: string | null };
    to: { price: string | null; old_price: string | null };
};
