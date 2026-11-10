<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import {
    Bold,
    Coffee,
    Heading2,
    Heading3,
    ImageIcon,
    Italic,
    Link2,
    List,
    ListOrdered,
    Quote,
    SquarePlay,
} from '@lucide/vue';
import { nextTick, ref, watch } from 'vue';
import NativeSelect from '@/components/admin/catalog/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/lib/i18n';
import { requestJson } from '@/lib/blog';
import { preview as previewRoute } from '@/routes/admin/blog/posts';
import type { BlogImage, Option } from '@/types';
import ImagePicker from './ImagePicker.vue';

/**
 * Markdown editor for blog posts: a toolbar for the common syntax and the
 * site's embeds, and a preview rendered by the server exactly like the article.
 */
const body = defineModel<string>({ required: true });

defineProps<{
    id?: string;
    products: Option[];
    invalid?: boolean;
}>();

const t = useTrans();
const textarea = ref<HTMLTextAreaElement>();
const mode = ref<'write' | 'split' | 'preview'>('write');
const html = ref('');
const previewError = ref<string>();
const pickerOpen = ref(false);
const insertBar = ref<'product' | 'youtube' | null>(null);
const productSlug = ref('');
const youtubeLink = ref('');

/** Replace the selection with text, keeping the cursor where it makes sense. */
const insert = (before: string, after = '', placeholder = '') => {
    const el = textarea.value;

    if (!el) {
        body.value += before + placeholder + after;

        return;
    }

    const start = el.selectionStart;
    const end = el.selectionEnd;
    const selected = body.value.slice(start, end) || placeholder;
    body.value =
        body.value.slice(0, start) +
        before +
        selected +
        after +
        body.value.slice(end);

    nextTick(() => {
        el.focus();
        el.setSelectionRange(
            start + before.length,
            start + before.length + selected.length,
        );
    });
};

/** Put a block (heading, embed, image) on a line of its own. */
const insertBlock = (block: string) => {
    const el = textarea.value;
    const at = el ? el.selectionStart : body.value.length;
    const head = body.value.slice(0, at);
    const prefix =
        head === '' || head.endsWith('\n\n')
            ? ''
            : head.endsWith('\n')
              ? '\n'
              : '\n\n';
    insert(`${prefix}${block}\n\n`);
};

const linePrefix = (prefix: string, placeholder: string) => {
    const el = textarea.value;
    const at = el ? el.selectionStart : body.value.length;
    const atLineStart = at === 0 || body.value[at - 1] === '\n';
    insert(`${atLineStart ? '' : '\n'}${prefix}`, '', placeholder);
};

const tools = [
    {
        icon: Heading2,
        label: 'Heading',
        run: () => linePrefix('## ', t('Heading')),
    },
    {
        icon: Heading3,
        label: 'Subheading',
        run: () => linePrefix('### ', t('Subheading')),
    },
    { icon: Bold, label: 'Bold', run: () => insert('**', '**', t('text')) },
    { icon: Italic, label: 'Italic', run: () => insert('*', '*', t('text')) },
    {
        icon: Link2,
        label: 'Link',
        run: () => insert('[', '](https://)', t('link text')),
    },
    {
        icon: List,
        label: 'Bulleted list',
        run: () => linePrefix('- ', t('item')),
    },
    {
        icon: ListOrdered,
        label: 'Numbered list',
        run: () => linePrefix('1. ', t('item')),
    },
    { icon: Quote, label: 'Quote', run: () => linePrefix('> ', t('quote')) },
];

const addImage = (image: BlogImage) => insertBlock(image.markdown);

const addProduct = () => {
    if (productSlug.value) {
        insertBlock(`::product[${productSlug.value}]`);
    }

    insertBar.value = null;
    productSlug.value = '';
};

const addYoutube = () => {
    const link = youtubeLink.value.trim();
    const id =
        link.match(
            /(?:youtu\.be\/|v=|embed\/|shorts\/)([A-Za-z0-9_-]{6,})/,
        )?.[1] ?? (/^[A-Za-z0-9_-]{6,}$/.test(link) ? link : null);

    if (id) {
        insertBlock(`::youtube[${id}]`);
    }

    insertBar.value = null;
    youtubeLink.value = '';
};

const render = async () => {
    try {
        const result = await requestJson<{ html: string }>(previewRoute.url(), {
            method: 'POST',
            body: { body: body.value },
        });
        html.value = result.html;
        previewError.value = undefined;
    } catch (e) {
        previewError.value = e instanceof Error ? e.message : String(e);
    }
};

const renderSoon = useDebounceFn(render, 600);

watch(mode, (value) => value !== 'write' && render());
watch(body, () => mode.value !== 'write' && renderSoon());
</script>

<template>
    <div
        class="overflow-hidden rounded-md border shadow-xs"
        :class="invalid ? 'border-destructive' : 'border-input'"
    >
        <div
            class="flex flex-wrap items-center gap-0.5 border-b bg-muted/40 px-1 py-1"
        >
            <template v-if="mode !== 'preview'">
                <Button
                    v-for="tool in tools"
                    :key="tool.label"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    :title="$t(tool.label)"
                    :aria-label="$t(tool.label)"
                    @click="tool.run"
                >
                    <component :is="tool.icon" />
                </Button>
                <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    :title="$t('Image')"
                    :aria-label="$t('Image')"
                    @click="pickerOpen = true"
                >
                    <ImageIcon />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    :title="$t('Product card')"
                    :aria-label="$t('Product card')"
                    @click="
                        insertBar = insertBar === 'product' ? null : 'product'
                    "
                >
                    <Coffee />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    :title="$t('YouTube video')"
                    :aria-label="$t('YouTube video')"
                    @click="
                        insertBar = insertBar === 'youtube' ? null : 'youtube'
                    "
                >
                    <SquarePlay />
                </Button>
            </template>
            <div
                class="ml-auto flex rounded-md border bg-background p-0.5 text-xs"
            >
                <button
                    v-for="option in ['write', 'split', 'preview'] as const"
                    :key="option"
                    type="button"
                    class="rounded px-2 py-1"
                    :class="
                        mode === option
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    :aria-pressed="mode === option"
                    @click="mode = option"
                >
                    {{
                        $t(
                            option === 'write'
                                ? 'Write'
                                : option === 'split'
                                  ? 'Side by side'
                                  : 'Preview',
                        )
                    }}
                </button>
            </div>
        </div>

        <form
            v-if="insertBar"
            class="flex flex-wrap items-center gap-2 border-b px-2 py-2"
            @submit.prevent="
                insertBar === 'product' ? addProduct() : addYoutube()
            "
        >
            <NativeSelect
                v-if="insertBar === 'product'"
                v-model="productSlug"
                class="max-w-sm"
                :aria-label="$t('Product')"
            >
                <option value="">{{ $t('Choose a product') }}</option>
                <option v-for="p in products" :key="p.value" :value="p.value">
                    {{ p.label }}
                </option>
            </NativeSelect>
            <Input
                v-else
                v-model="youtubeLink"
                class="max-w-sm"
                :placeholder="$t('YouTube link or video ID')"
                :aria-label="$t('YouTube link or video ID')"
            />
            <Button type="submit" size="sm">{{ $t('Insert') }}</Button>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="insertBar = null"
            >
                {{ $t('Cancel') }}
            </Button>
        </form>

        <div :class="mode === 'split' ? 'grid lg:grid-cols-2' : ''">
            <textarea
                v-show="mode !== 'preview'"
                :id="id"
                ref="textarea"
                v-model="body"
                class="block min-h-[60vh] w-full resize-y bg-transparent px-3 py-2 font-mono text-sm leading-relaxed outline-none"
                spellcheck="true"
                lang="ru"
                :aria-invalid="invalid || undefined"
            />
            <div
                v-if="mode !== 'write'"
                class="max-h-[80vh] min-h-[60vh] overflow-y-auto border-t px-5 py-4 lg:border-t-0"
                :class="mode === 'split' ? 'lg:border-l' : ''"
            >
                <p v-if="previewError" class="text-sm text-destructive">
                    {{ previewError }}
                </p>
                <!-- Rendered by the server's BlogMarkdown: raw HTML is stripped there. -->
                <div class="blog-preview" v-html="html" />
            </div>
        </div>

        <ImagePicker
            v-model:open="pickerOpen"
            :title="$t('Insert image')"
            @select="addImage"
        />
    </div>
</template>

<style scoped>
.blog-preview {
    font-size: 0.95rem;
    line-height: 1.7;
    max-width: 44rem;
}
.blog-preview :deep(h2) {
    font-size: 1.35rem;
    font-weight: 600;
    margin: 1.6em 0 0.6em;
}
.blog-preview :deep(h3) {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 1.4em 0 0.5em;
}
.blog-preview :deep(p),
.blog-preview :deep(ul),
.blog-preview :deep(ol),
.blog-preview :deep(blockquote),
.blog-preview :deep(.table-scroll) {
    margin: 0 0 1em;
}
.blog-preview :deep(ul) {
    list-style: disc;
    padding-left: 1.4em;
}
.blog-preview :deep(ol) {
    list-style: decimal;
    padding-left: 1.4em;
}
.blog-preview :deep(a) {
    text-decoration: underline;
}
.blog-preview :deep(img) {
    border-radius: 0.5rem;
    height: auto;
    max-width: 100%;
}
.blog-preview :deep(blockquote) {
    border-left: 3px solid var(--border);
    color: var(--muted-foreground);
    padding-left: 1em;
}
.blog-preview :deep(table) {
    border-collapse: collapse;
    font-size: 0.875rem;
}
.blog-preview :deep(th),
.blog-preview :deep(td) {
    border: 1px solid var(--border);
    padding: 0.35em 0.6em;
}
.blog-preview :deep(iframe) {
    aspect-ratio: 16 / 9;
    height: auto;
    width: 100%;
}
.blog-preview :deep(.blog-preview-product) {
    background: var(--muted);
    border: 1px dashed var(--border);
    border-radius: 0.5rem;
    margin: 0 0 1em;
    padding: 0.75em 1em;
}
</style>
