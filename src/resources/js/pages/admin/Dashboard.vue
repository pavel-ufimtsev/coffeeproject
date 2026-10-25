<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes/admin';
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
}>();
</script>

<template>
    <Head :title="$t('Dashboard')" />

    <div class="flex flex-1 flex-col gap-4 p-4">
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
