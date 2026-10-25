<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import DeleteUserDialog from '@/components/admin/DeleteUserDialog.vue';
import UserForm from '@/components/admin/UserForm.vue';
import Heading from '@/components/Heading.vue';
import { edit, index } from '@/routes/admin/users';
import type { ManagedUser, RoleOption } from '@/types';

const props = defineProps<{
    user: ManagedUser;
    roles: RoleOption[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Users', href: index() },
        { title: props.user.name, href: edit(props.user.id) },
    ],
});
</script>

<template>
    <Head :title="$t('Edit :name', { name: props.user.name })" />

    <div class="space-y-10 p-4">
        <div>
            <Heading :title="user.name" :description="user.email" />

            <UserForm
                :form="UserController.update.form(user.id)"
                :roles="roles"
                :user="user"
                :submit-label="$t('Save changes')"
            />
        </div>

        <div v-if="user.can.delete" class="max-w-xl space-y-4">
            <Heading
                variant="small"
                :title="$t('Delete user')"
                :description="$t('Remove this account permanently.')"
            />
            <DeleteUserDialog :user="user" />
        </div>
    </div>
</template>
