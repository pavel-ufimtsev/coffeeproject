<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ExternalLink, Plus } from '@lucide/vue';
import BlogTabs from '@/components/admin/blog/BlogTabs.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/admin/blog/categories';
import { index as postsIndex } from '@/routes/admin/blog/posts';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Blog', href: postsIndex() },
            { title: 'Sections', href: index() },
        ],
    },
});

defineProps<{
    categories: {
        id: number;
        slug: string;
        name: string;
        is_active: boolean;
        sort: number;
        url: string;
        posts: number;
        visible_posts: number;
    }[];
}>();
</script>

<template>
    <Head :title="$t('Blog sections')" />

    <div class="space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-0.5">
                <h2 class="text-xl font-semibold tracking-tight">
                    {{ $t('Blog') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ $t('Sections have their own pages at /blog/c/….') }}
                </p>
            </div>
            <Button as-child>
                <Link :href="create()"><Plus /> {{ $t('New section') }}</Link>
            </Button>
        </div>

        <BlogTabs />

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ $t('Name') }}</th>
                        <th class="px-4 py-3 font-medium">
                            {{ $t('Address') }}
                        </th>
                        <th class="px-4 py-3 text-right font-medium">
                            {{ $t('Live posts') }}
                        </th>
                        <th class="px-4 py-3 text-right font-medium">
                            {{ $t('All posts') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="category in categories"
                        :key="category.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="edit(category.id)"
                                class="font-medium hover:underline"
                            >
                                {{ category.name }}
                            </Link>
                            <Badge
                                v-if="!category.is_active"
                                variant="secondary"
                                class="ml-2"
                            >
                                {{ $t('Hidden') }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <a
                                :href="category.url"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1 hover:text-foreground"
                            >
                                /blog/c/{{ category.slug }}
                                <ExternalLink class="size-3" />
                            </a>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ category.visible_posts }}
                        </td>
                        <td
                            class="px-4 py-3 text-right text-muted-foreground tabular-nums"
                        >
                            {{ category.posts }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
