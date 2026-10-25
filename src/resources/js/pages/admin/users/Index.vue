<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, edit, index } from '@/routes/admin/users';
import type { ManagedUser, Paginated, RoleOption } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: index() }],
    },
});

const props = defineProps<{
    users: Paginated<ManagedUser>;
    filters: { search: string; role: string };
    roles: RoleOption[];
}>();

const search = ref(props.filters.search);
const role = ref(props.filters.role || 'all');

const applyFilters = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            role: role.value === 'all' ? undefined : role.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(search, useDebounceFn(applyFilters, 300));
watch(role, applyFilters);

const previousPage = computed(() => props.users.links.at(0)?.url ?? null);
const nextPage = computed(() => props.users.links.at(-1)?.url ?? null);
</script>

<template>
    <Head :title="$t('Users')" />

    <div class="space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-0.5">
                <h2 class="text-xl font-semibold tracking-tight">
                    {{ $t('Users') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ $t('Customer and admin accounts') }}
                </p>
            </div>
            <Button as-child>
                <Link :href="create()"><Plus /> {{ $t('New user') }}</Link>
            </Button>
        </div>

        <div class="flex flex-wrap gap-3">
            <Input
                v-model="search"
                type="search"
                :placeholder="$t('Search by name or email')"
                class="max-w-xs"
                :aria-label="$t('Search users')"
            />
            <Select v-model="role">
                <SelectTrigger class="w-40" :aria-label="$t('Filter by role')">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{ $t('All roles') }}</SelectItem>
                    <SelectItem
                        v-for="option in roles"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ $t('Name') }}</th>
                        <th class="px-4 py-3 font-medium">
                            {{ $t('Email') }}
                        </th>
                        <th class="px-4 py-3 font-medium">{{ $t('Role') }}</th>
                        <th class="px-4 py-3 font-medium">
                            {{ $t('Joined') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="edit(user.id)"
                                class="font-medium hover:underline"
                            >
                                {{ user.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">
                            {{ user.email }}
                            <span
                                v-if="!user.email_verified"
                                class="ml-1 text-xs text-muted-foreground"
                            >
                                ({{ $t('unverified') }})
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    user.role === 'admin'
                                        ? 'default'
                                        : 'secondary'
                                "
                            >
                                {{ user.role_label }}
                            </Badge>
                        </td>
                        <td
                            class="px-4 py-3 text-muted-foreground tabular-nums"
                        >
                            {{ user.created_at }}
                        </td>
                    </tr>
                    <tr v-if="users.data.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            {{ $t('No users match these filters.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="users.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span>
                {{
                    $t(':from–:to of :total', {
                        from: users.from ?? 0,
                        to: users.to ?? 0,
                        total: users.total,
                    })
                }}
            </span>
            <div class="flex gap-2">
                <Button
                    v-if="previousPage"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="previousPage" preserve-scroll>
                        {{ $t('Previous') }}
                    </Link>
                </Button>
                <Button v-if="nextPage" variant="outline" size="sm" as-child>
                    <Link :href="nextPage" preserve-scroll>
                        {{ $t('Next') }}
                    </Link>
                </Button>
            </div>
        </div>
    </div>
</template>
