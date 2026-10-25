<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import UserForm from '@/components/admin/UserForm.vue';
import Heading from '@/components/Heading.vue';
import { create, index } from '@/routes/admin/users';
import type { RoleOption } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: index() },
            { title: 'New user', href: create() },
        ],
    },
});

defineProps<{
    roles: RoleOption[];
}>();
</script>

<template>
    <Head :title="$t('New user')" />

    <div class="p-4">
        <Heading
            :title="$t('New user')"
            :description="
                $t(
                    'Create a customer or admin account. The email is marked as verified.',
                )
            "
        />

        <UserForm
            :form="UserController.store.form()"
            :roles="roles"
            :submit-label="$t('Create user')"
        />
    </div>
</template>
