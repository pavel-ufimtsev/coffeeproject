export type BlogPostStatus = 'draft' | 'scheduled' | 'published' | 'archived';

export type TriageDecision = 'publish' | 'refresh' | 'merge' | 'retire';

export type BlogTriage = {
    decision: TriageDecision | null;
    confidence: number | null;
    reason: string | null;
    merge_into?: string | null;
    triaged_at?: string;
};

export type BlogPostListItem = {
    id: number;
    title: string;
    slug: string;
    status: BlogPostStatus;
    status_label: string;
    is_visible: boolean;
    category: string | null;
    published_at: string | null;
    source: string;
    images_missing: number;
    triage: BlogTriage | null;
    deleted: boolean;
};

export type BlogPostForm = {
    id: number;
    slug: string;
    title: string;
    excerpt: string | null;
    body: string;
    seo_title: string | null;
    seo_description: string | null;
    canonical_url: string | null;
    noindex: boolean;
    author_name: string | null;
    source: string;
    legacy_url: string | null;
    reading_minutes: number;
    category: string | null;
    cover: { id: number; thumb: string; alt: string | null } | null;
    status: BlogPostStatus;
    status_label: string;
    is_visible: boolean;
    published_at: string | null;
    edited_at: string | null;
    tags: string[];
    products: string[];
    url: string;
    redirects_to: string | null;
    words: number;
    missing_images: string[];
    triage: BlogTriage | null;
};

export type BlogRevision = {
    id: number;
    author: string;
    note: string | null;
    title: string;
    created_at: string | null;
};

export type BlogImage = {
    id: number;
    url: string;
    thumb: string;
    width: number;
    height: number;
    alt: string | null;
    caption: string | null;
    credit: string | null;
    original_name: string | null;
    uploaded_by: string | null;
    markdown: string;
    posts: { id: number; title: string }[];
};
