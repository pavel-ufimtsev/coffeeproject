<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes/admin';
import { index as productsIndex } from '@/routes/admin/products';
import { index as retailcrmIndex } from '@/routes/admin/retailcrm';
import { index as usersIndex } from '@/routes/admin/users';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

defineProps<{
    stats: {
        users: number;
        admins: number;
        newUsersThisWeek: number;
    };
    catalog: {
        active: number;
        drafts: number;
        needsAttention: number;
    };
    lastSync: {
        started_at: string;
        error: string | null;
        issues: number;
        changed: number;
    } | null;
}>();
</script>

<template>
    <Head :title="$t('Dashboard')" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="grid auto-rows-min gap-4 md:grid-cols-4">
            <Link :href="productsIndex({ query: { status: 'active' } })">
                <Card class="transition-colors hover:bg-accent/50">
                    <CardHeader>
                        <CardDescription>{{
                            $t('Products on sale')
                        }}</CardDescription>
                        <CardTitle class="text-3xl tabular-nums">
                            {{ catalog.active }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </Link>
            <Link :href="productsIndex({ query: { attention: 1 } })">
                <Card class="transition-colors hover:bg-accent/50">
                    <CardHeader>
                        <CardDescription>{{
                            $t('Need attention')
                        }}</CardDescription>
                        <CardTitle
                            class="text-3xl tabular-nums"
                            :class="
                                catalog.needsAttention > 0 && 'text-amber-600'
                            "
                        >
                            {{ catalog.needsAttention }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </Link>
            <Link :href="productsIndex({ query: { status: 'draft' } })">
                <Card class="transition-colors hover:bg-accent/50">
                    <CardHeader>
                        <CardDescription>{{ $t('Drafts') }}</CardDescription>
                        <CardTitle class="text-3xl tabular-nums">
                            {{ catalog.drafts }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </Link>
            <Link :href="retailcrmIndex()">
                <Card class="transition-colors hover:bg-accent/50">
                    <CardHeader>
                        <CardDescription>{{
                            $t('Last retailCRM sync')
                        }}</CardDescription>
                        <CardTitle class="text-base">
                            <template v-if="!lastSync">{{
                                $t('Never')
                            }}</template>
                            <template v-else>
                                {{ lastSync.started_at }}
                                <span
                                    class="block text-sm font-normal"
                                    :class="
                                        lastSync.error || lastSync.issues
                                            ? 'text-amber-600'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{
                                        lastSync.error
                                            ? $t('Failed')
                                            : $t(':count problems', {
                                                  count: lastSync.issues,
                                              })
                                    }}
                                </span>
                            </template>
                        </CardTitle>
                    </CardHeader>
                </Card>
            </Link>
        </div>
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <Link :href="usersIndex({ query: { role: 'user' } })">
                <Card class="transition-colors hover:bg-accent/50">
                    <CardHeader>
                        <CardDescription>{{ $t('Customers') }}</CardDescription>
                        <CardTitle class="text-3xl tabular-nums">
                            {{ stats.users }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </Link>
            <Card>
                <CardHeader>
                    <CardDescription>{{ $t('New this week') }}</CardDescription>
                    <CardTitle class="text-3xl tabular-nums">
                        {{ stats.newUsersThisWeek }}
                    </CardTitle>
                </CardHeader>
            </Card>
            <Link :href="usersIndex({ query: { role: 'admin' } })">
                <Card class="transition-colors hover:bg-accent/50">
                    <CardHeader>
                        <CardDescription>{{ $t('Admins') }}</CardDescription>
                        <CardTitle class="text-3xl tabular-nums">
                            {{ stats.admins }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </Link>
        </div>
    </div>
</template>
