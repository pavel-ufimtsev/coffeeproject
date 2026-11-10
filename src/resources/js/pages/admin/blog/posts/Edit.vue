<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    AlertTriangle,
    ExternalLink,
    ImageOff,
    ImagePlus,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ImagePicker from '@/components/admin/blog/ImagePicker.vue';
import MarkdownEditor from '@/components/admin/blog/MarkdownEditor.vue';
import PostStatusBadge from '@/components/admin/blog/PostStatusBadge.vue';
import RetireDialog from '@/components/admin/blog/RetireDialog.vue';
import RevisionsPanel from '@/components/admin/blog/RevisionsPanel.vue';
import TriageBadge from '@/components/admin/blog/TriageBadge.vue';
import CheckboxField from '@/components/admin/catalog/CheckboxField.vue';
import ConfirmDelete from '@/components/admin/catalog/ConfirmDelete.vue';
import Field from '@/components/admin/catalog/Field.vue';
import NativeSelect from '@/components/admin/catalog/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/lib/i18n';
import {
    create,
    destroy,
    edit,
    index,
    status as statusRoute,
    store,
    update,
} from '@/routes/admin/blog/posts';
import type { BlogImage, BlogPostForm, BlogRevision, Option } from '@/types';

const props = defineProps<{
    post: BlogPostForm | null;
    revisions: BlogRevision[];
    statuses: Option[];
    categories: Option[];
    products: Option[];
    suggestedRedirect: string;
}>();

const t = useTrans();

setLayoutProps({
    breadcrumbs: [
        { title: 'Blog', href: index() },
        props.post
            ? { title: props.post.title, href: edit(props.post.id) }
            : { title: 'New post', href: create() },
    ],
});

const fromPost = (post: BlogPostForm | null) => ({
    title: post?.title ?? '',
    slug: post?.slug ?? '',
    excerpt: post?.excerpt ?? '',
    body: post?.body ?? '',
    category: post?.category ?? '',
    cover_image_id: post?.cover?.id ?? null,
    published_at: post?.published_at ?? '',
    seo_title: post?.seo_title ?? '',
    seo_description: post?.seo_description ?? '',
    canonical_url: post?.canonical_url ?? '',
    noindex: post?.noindex ?? false,
    author_name: post?.author_name ?? '',
    tags: (post?.tags ?? []).join(', '),
    products: [...(post?.products ?? [])],
    note: '',
});

const form = useForm(fromPost(props.post));
const cover = ref(props.post?.cover ?? null);

// After a save the server sends the post again; make that the new baseline.
watch(
    () => props.post,
    (post) => {
        if (post) {
            form.defaults(fromPost(post));
            form.reset();
            cover.value = post.cover;
        }
    },
);

const payload = (data: ReturnType<typeof fromPost>) => ({
    ...data,
    slug: data.slug || null,
    category: data.category || null,
    published_at: data.published_at || null,
    tags: data.tags
        .split(',')
        .map((tag) => tag.trim())
        .filter(Boolean),
});

const submit = () => {
    form.transform(payload).submit(
        props.post ? update(props.post.id) : store(),
        {
            preserveScroll: true,
            onSuccess: () => clearLocalCopy(),
        },
    );
};

// Products shown as cards under the article.
const productToAdd = ref('');
const productLabel = (slug: string) =>
    props.products.find((p) => p.value === slug)?.label ?? slug;
watch(productToAdd, (slug) => {
    if (slug && !form.products.includes(slug)) {
        form.products.push(slug);
    }

    productToAdd.value = '';
});

const coverPickerOpen = ref(false);
const setCover = (image: BlogImage) => {
    form.cover_image_id = image.id;
    cover.value = { id: image.id, thumb: image.thumb, alt: image.alt };
};

// Status changes. They work on the saved post, so unsaved edits must be saved first.
const changing = ref(false);
const retireOpen = ref(false);
const statusError = ref<string>();
const changeStatus = (
    action: 'publish' | 'unpublish' | 'archive',
    extra: Record<string, string> = {},
) => {
    if (!props.post) {
        return;
    }

    router.post(
        statusRoute.url(props.post.id),
        { action, ...extra },
        {
            preserveScroll: true,
            onStart: () => {
                changing.value = true;
                statusError.value = undefined;
            },
            onFinish: () => (changing.value = false),
            onSuccess: () => (retireOpen.value = false),
            onError: (errors) => (statusError.value = Object.values(errors)[0]),
        },
    );
};

const publishLabel = computed(() =>
    form.published_at && new Date(form.published_at) > new Date()
        ? t('Schedule')
        : t('Publish'),
);

const seoDescriptionLength = computed(() => form.seo_description.length);

// Unsaved work survives a closed tab: a copy is kept in this browser until saved.
const storageKey = `blog-post-draft-${props.post?.id ?? 'new'}`;
const localCopy = ref<{
    savedAt: string;
    data: ReturnType<typeof fromPost>;
} | null>(null);

const clearLocalCopy = () => {
    try {
        localStorage.removeItem(storageKey);
    } catch {
        // Storage can be unavailable (private mode); nothing to clear then.
    }

    localCopy.value = null;
};

const keepLocalCopy = useDebounceFn(() => {
    if (!form.isDirty) {
        return;
    }

    try {
        localStorage.setItem(
            storageKey,
            JSON.stringify({
                savedAt: new Date().toISOString(),
                data: form.data(),
            }),
        );
    } catch {
        // Without storage the editor still works; only the recovery copy is lost.
    }
}, 1500);

watch(() => form.data(), keepLocalCopy, { deep: true });

const restoreLocalCopy = () => {
    if (localCopy.value) {
        Object.assign(form, localCopy.value.data);
    }

    localCopy.value = null;
};

const beforeLeave = (event: BeforeUnloadEvent) => {
    if (form.isDirty) {
        event.preventDefault();
    }
};

let removeRouterGuard: (() => void) | undefined;

onMounted(() => {
    try {
        const saved = localStorage.getItem(storageKey);
        const parsed = saved ? JSON.parse(saved) : null;

        if (
            parsed?.data &&
            JSON.stringify(parsed.data) !== JSON.stringify(form.data())
        ) {
            localCopy.value = parsed;
        }
    } catch {
        localCopy.value = null;
    }

    window.addEventListener('beforeunload', beforeLeave);
    removeRouterGuard = router.on('before', (event) => {
        const visit = event.detail.visit;

        if (
            form.isDirty &&
            visit.method === 'get' &&
            !window.confirm(
                t('You have unsaved changes. Leave without saving?'),
            )
        ) {
            return false;
        }
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeLeave);
    removeRouterGuard?.();
});

const slugChanged = computed(
    () => props.post?.is_visible && form.slug !== props.post.slug,
);
</script>

<template>
    <Head :title="post?.title ?? $t('New post')" />

    <form class="p-4" @submit.prevent="submit">
        <div
            v-if="localCopy"
            class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
        >
            <AlertTriangle class="size-4" />
            {{
                $t('There are unsaved edits from :time in this browser.', {
                    time: new Date(localCopy.savedAt).toLocaleString(),
                })
            }}
            <Button type="button" size="sm" @click="restoreLocalCopy">
                {{ $t('Bring them back') }}
            </Button>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="clearLocalCopy"
            >
                {{ $t('Discard') }}
            </Button>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <!-- The article -->
            <div class="min-w-0 space-y-5">
                <Field
                    id="title"
                    :label="$t('Title')"
                    :error="form.errors.title"
                >
                    <Input
                        id="title"
                        v-model="form.title"
                        required
                        class="text-lg font-medium"
                    />
                </Field>
                <Field
                    id="slug"
                    :label="$t('Address')"
                    :error="form.errors.slug"
                    :hint="
                        slugChanged
                            ? $t(
                                  'The old address will redirect to the new one.',
                              )
                            : post?.source === 'archive'
                              ? $t('Restored posts keep their old address.')
                              : post
                                ? undefined
                                : $t('Leave empty to make it from the title.')
                    "
                >
                    <div class="flex items-center gap-1">
                        <span class="text-sm text-muted-foreground"
                            >/blog/</span
                        >
                        <Input
                            id="slug"
                            v-model="form.slug"
                            :required="!!post"
                            :disabled="post?.source === 'archive'"
                            class="font-mono text-sm"
                        />
                    </div>
                </Field>
                <Field
                    id="excerpt"
                    :label="$t('Excerpt')"
                    :hint="
                        $t(
                            'One or two sentences for the post card and the feed.',
                        )
                    "
                    :error="form.errors.excerpt"
                >
                    <Textarea id="excerpt" v-model="form.excerpt" rows="2" />
                </Field>

                <div
                    v-if="post?.missing_images.length"
                    class="flex gap-2 rounded-lg border bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
                >
                    <ImageOff class="mt-0.5 size-4 shrink-0" />
                    <span>
                        {{
                            $t(
                                ':count images of the old post were lost and are not in the text.',
                                { count: post.missing_images.length },
                            )
                        }}
                    </span>
                </div>

                <Field id="body" :label="$t('Text')" :error="form.errors.body">
                    <MarkdownEditor
                        id="body"
                        v-model="form.body"
                        :products="products"
                        :invalid="!!form.errors.body"
                    />
                </Field>
                <p v-if="post" class="text-xs text-muted-foreground">
                    {{
                        $t(':words words, :minutes min read', {
                            words: post.words,
                            minutes: post.reading_minutes,
                        })
                    }}
                </p>

                <div
                    class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center gap-3 border-t bg-background/95 px-4 py-3 backdrop-blur"
                >
                    <Input
                        v-model="form.note"
                        class="max-w-md flex-1"
                        maxlength="255"
                        :placeholder="
                            $t(
                                'What did you change? (optional, saved with the revision)',
                            )
                        "
                        :aria-label="$t('Change note')"
                    />
                    <Button
                        :disabled="form.processing || (!!post && !form.isDirty)"
                    >
                        {{ post ? $t('Save changes') : $t('Save draft') }}
                    </Button>
                    <span
                        v-if="post && form.isDirty"
                        class="text-sm text-muted-foreground"
                    >
                        {{ $t('Unsaved changes') }}
                    </span>
                    <span
                        v-if="form.hasErrors"
                        class="text-sm text-destructive"
                    >
                        {{ $t('Check the highlighted fields.') }}
                    </span>
                </div>
            </div>

            <!-- Settings -->
            <aside class="space-y-5">
                <section v-if="post" class="space-y-3 rounded-xl border p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-semibold">{{ $t('Status') }}</h3>
                        <PostStatusBadge
                            :status="post.status"
                            :label="post.status_label"
                        />
                    </div>
                    <a
                        :href="post.url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1 text-sm hover:underline"
                    >
                        <ExternalLink class="size-3.5" />
                        {{
                            post.is_visible
                                ? $t('Open on the site')
                                : $t('Preview on the site')
                        }}
                    </a>
                    <p
                        v-if="post.redirects_to"
                        class="text-sm text-muted-foreground"
                    >
                        {{
                            $t('Its address redirects to :path', {
                                path: post.redirects_to,
                            })
                        }}
                    </p>
                    <Field
                        id="published_at"
                        :label="$t('Publication date')"
                        :error="form.errors.published_at"
                        :hint="
                            $t(
                                'Readers see this date. A future date schedules the post.',
                            )
                        "
                    >
                        <Input
                            id="published_at"
                            v-model="form.published_at"
                            type="datetime-local"
                        />
                    </Field>
                    <p
                        v-if="form.isDirty"
                        class="text-xs text-muted-foreground"
                    >
                        {{
                            $t(
                                'Save your changes before publishing or retiring.',
                            )
                        }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="post.status !== 'published'"
                            type="button"
                            size="sm"
                            :disabled="changing || form.isDirty"
                            @click="changeStatus('publish')"
                        >
                            {{ publishLabel }}
                        </Button>
                        <Button
                            v-if="
                                post.status === 'published' ||
                                post.status === 'scheduled'
                            "
                            type="button"
                            size="sm"
                            variant="outline"
                            :disabled="changing || form.isDirty"
                            @click="changeStatus('unpublish')"
                        >
                            {{ $t('Back to drafts') }}
                        </Button>
                        <Button
                            v-if="post.status !== 'archived'"
                            type="button"
                            size="sm"
                            variant="outline"
                            :disabled="changing || form.isDirty"
                            @click="retireOpen = true"
                        >
                            {{ $t('Retire…') }}
                        </Button>
                    </div>
                    <p v-if="statusError" class="text-sm text-destructive">
                        {{ statusError }}
                    </p>
                </section>

                <section
                    v-if="post?.triage"
                    class="space-y-3 rounded-xl border p-4"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-semibold">{{ $t('Agent verdict') }}</h3>
                        <TriageBadge :triage="post.triage" />
                    </div>
                    <p v-if="post.triage.reason" class="text-sm">
                        {{ post.triage.reason }}
                    </p>
                    <p
                        v-if="post.triage.merge_into"
                        class="text-sm text-muted-foreground"
                    >
                        {{ $t('Merge into') }}:
                        <a
                            :href="`/blog/${post.triage.merge_into}`"
                            target="_blank"
                            class="underline"
                            >/blog/{{ post.triage.merge_into }}</a
                        >
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ post.triage.triaged_at }}
                    </p>
                    <div
                        v-if="post.status === 'draft'"
                        class="flex flex-wrap gap-2"
                    >
                        <Button
                            v-if="
                                post.triage.decision === 'publish' ||
                                post.triage.decision === 'refresh'
                            "
                            type="button"
                            size="sm"
                            :disabled="changing || form.isDirty"
                            @click="changeStatus('publish')"
                        >
                            {{ $t('Accept: publish') }}
                        </Button>
                        <Button
                            v-else
                            type="button"
                            size="sm"
                            :disabled="changing || form.isDirty"
                            @click="
                                changeStatus('archive', {
                                    redirect_to: suggestedRedirect,
                                })
                            "
                        >
                            {{
                                $t('Accept: retire to :path', {
                                    path: suggestedRedirect,
                                })
                            }}
                        </Button>
                    </div>
                </section>

                <section class="space-y-4 rounded-xl border p-4">
                    <h3 class="font-semibold">{{ $t('Placement') }}</h3>
                    <Field
                        id="category"
                        :label="$t('Section')"
                        :error="form.errors.category"
                    >
                        <NativeSelect id="category" v-model="form.category">
                            <option value="">{{ $t('No section') }}</option>
                            <option
                                v-for="option in categories"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </NativeSelect>
                    </Field>
                    <Field
                        id="tags"
                        :label="$t('Tags')"
                        :hint="
                            $t(
                                'Comma separated. Not shown to readers; they pick related posts.',
                            )
                        "
                        :error="form.errors.tags"
                    >
                        <Input id="tags" v-model="form.tags" />
                    </Field>
                    <Field
                        id="products"
                        :label="$t('Products under the article')"
                        :error="form.errors.products"
                    >
                        <ul
                            v-if="form.products.length"
                            class="space-y-1 text-sm"
                        >
                            <li
                                v-for="slug in form.products"
                                :key="slug"
                                class="flex items-center justify-between gap-2 rounded-md border px-2 py-1"
                            >
                                <span class="truncate">{{
                                    productLabel(slug)
                                }}</span>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-destructive"
                                    :aria-label="
                                        $t('Remove :name', {
                                            name: productLabel(slug),
                                        })
                                    "
                                    @click="
                                        form.products = form.products.filter(
                                            (s) => s !== slug,
                                        )
                                    "
                                >
                                    <X class="size-4" />
                                </button>
                            </li>
                        </ul>
                        <NativeSelect id="products" v-model="productToAdd">
                            <option value="">{{ $t('Add a product…') }}</option>
                            <option
                                v-for="p in products"
                                :key="p.value"
                                :value="p.value"
                                :disabled="form.products.includes(p.value)"
                            >
                                {{ p.label }}
                            </option>
                        </NativeSelect>
                    </Field>
                    <Field
                        :label="$t('Cover')"
                        :error="form.errors.cover_image_id"
                    >
                        <div v-if="cover" class="relative">
                            <img
                                :src="cover.thumb"
                                :alt="cover.alt ?? ''"
                                class="aspect-video w-full rounded-md object-cover"
                            />
                            <Button
                                type="button"
                                size="icon"
                                variant="secondary"
                                class="absolute top-1 right-1 size-7"
                                :aria-label="$t('Remove cover')"
                                @click="
                                    form.cover_image_id = null;
                                    cover = null;
                                "
                            >
                                <X />
                            </Button>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="coverPickerOpen = true"
                        >
                            <ImagePlus />
                            {{
                                cover ? $t('Change cover') : $t('Choose cover')
                            }}
                        </Button>
                    </Field>
                </section>

                <section class="space-y-4 rounded-xl border p-4">
                    <h3 class="font-semibold">{{ $t('Search engines') }}</h3>
                    <Field
                        id="seo_title"
                        :label="$t('Page title')"
                        :hint="$t('Leave empty to use the post title.')"
                        :error="form.errors.seo_title"
                    >
                        <Input id="seo_title" v-model="form.seo_title" />
                    </Field>
                    <Field
                        id="seo_description"
                        :label="$t('Search snippet')"
                        :hint="
                            $t(':count characters; 140–160 is best.', {
                                count: seoDescriptionLength,
                            })
                        "
                        :error="form.errors.seo_description"
                    >
                        <Textarea
                            id="seo_description"
                            v-model="form.seo_description"
                            rows="3"
                        />
                    </Field>
                    <Field
                        id="author_name"
                        :label="$t('Author')"
                        :error="form.errors.author_name"
                    >
                        <Input id="author_name" v-model="form.author_name" />
                    </Field>
                    <Field
                        id="canonical_url"
                        :label="$t('Canonical address')"
                        :hint="
                            $t(
                                'Only if the main copy of this text lives elsewhere.',
                            )
                        "
                        :error="form.errors.canonical_url"
                    >
                        <Input
                            id="canonical_url"
                            v-model="form.canonical_url"
                            type="url"
                        />
                    </Field>
                    <CheckboxField
                        v-model="form.noindex"
                        :label="$t('Hide from search engines')"
                    />
                </section>

                <section v-if="post" class="space-y-3 rounded-xl border p-4">
                    <h3 class="font-semibold">{{ $t('Revisions') }}</h3>
                    <RevisionsPanel
                        :post-id="post.id"
                        :revisions="revisions"
                        :current="{
                            title: post.title,
                            excerpt: post.excerpt,
                            body: post.body,
                        }"
                    />
                </section>

                <section
                    v-if="post && !post.is_visible"
                    class="space-y-3 rounded-xl border p-4"
                >
                    <h3 class="font-semibold">{{ $t('Delete') }}</h3>
                    <p class="text-sm text-muted-foreground">
                        {{
                            $t(
                                'Retiring with a redirect is usually better. Deleted posts go to the bin and can be restored.',
                            )
                        }}
                    </p>
                    <ConfirmDelete
                        :action="destroy(post.id)"
                        :title="
                            $t('Move :title to the bin?', { title: post.title })
                        "
                        :label="$t('Move to bin')"
                    />
                </section>
            </aside>
        </div>

        <RetireDialog
            v-if="post"
            v-model:open="retireOpen"
            :suggested="suggestedRedirect"
            :title="$t('Retire this post?')"
            :processing="changing"
            :error="statusError"
            @confirm="changeStatus('archive', $event)"
        />
        <ImagePicker
            v-model:open="coverPickerOpen"
            :title="$t('Choose cover')"
            @select="setCover"
        />
    </form>
</template>
