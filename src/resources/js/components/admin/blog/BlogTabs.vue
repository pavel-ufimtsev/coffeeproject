<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { index as categoriesIndex } from '@/routes/admin/blog/categories';
import { index as imagesIndex } from '@/routes/admin/blog/images';
import { index as postsIndex } from '@/routes/admin/blog/posts';

const page = usePage();

const tabs = computed(() => [
    { title: 'Posts', href: postsIndex.url() },
    { title: 'Images', href: imagesIndex.url() },
    { title: 'Sections', href: categoriesIndex.url() },
]);

const isCurrent = (href: string) =>
    page.url.split('?')[0].startsWith(new URL(href, 'http://x').pathname);
</script>

<template>
    <nav class="flex gap-1 border-b text-sm" :aria-label="$t('Blog')">
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            class="-mb-px border-b-2 px-3 py-2"
            :class="
                isCurrent(tab.href)
                    ? 'border-primary font-medium text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
            :aria-current="isCurrent(tab.href) ? 'page' : undefined"
        >
            {{ $t(tab.title) }}
        </Link>
    </nav>
</template>
