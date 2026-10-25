<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import type { Method } from '@inertiajs/core';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ManagedUser, Role, RoleOption } from '@/types';

type Props = {
    form: { action: string; method: Method };
    roles: RoleOption[];
    user?: ManagedUser;
    submitLabel: string;
};

const props = defineProps<Props>();

const role = ref<Role>(props.user?.role ?? 'user');
const canChangeRole = props.user?.can.change_role ?? true;
</script>

<template>
    <Form
        v-bind="form"
        class="max-w-xl space-y-6"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-2">
            <Label for="name">{{ $t('Name') }}</Label>
            <Input
                id="name"
                name="name"
                :default-value="user?.name"
                required
                autocomplete="off"
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="email">{{ $t('Email address') }}</Label>
            <Input
                id="email"
                type="email"
                name="email"
                :default-value="user?.email"
                required
                autocomplete="off"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="role">{{ $t('Role') }}</Label>
            <input type="hidden" name="role" :value="role" />
            <Select v-model="role" :disabled="!canChangeRole">
                <SelectTrigger id="role" class="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in roles"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p v-if="!canChangeRole" class="text-sm text-muted-foreground">
                {{ $t("You can't change your own role.") }}
            </p>
            <InputError :message="errors.role" />
        </div>

        <div class="grid gap-2">
            <Label for="password">{{ $t('Password') }}</Label>
            <PasswordInput
                id="password"
                name="password"
                :required="!user"
                autocomplete="new-password"
            />
            <p v-if="user" class="text-sm text-muted-foreground">
                {{ $t('Leave blank to keep the current password.') }}
            </p>
            <InputError :message="errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">{{
                $t('Confirm password')
            }}</Label>
            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                :required="!user"
                autocomplete="new-password"
            />
        </div>

        <Button :disabled="processing">{{ submitLabel }}</Button>
    </Form>
</template>
