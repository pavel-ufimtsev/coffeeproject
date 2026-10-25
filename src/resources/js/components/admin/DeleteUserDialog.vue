<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { ManagedUser } from '@/types';

defineProps<{
    user: ManagedUser;
}>();
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button variant="destructive">{{ $t('Delete user') }}</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{ $t('Delete :name?', { name: user.name }) }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            ":email will be removed permanently. This can't be undone.",
                            { email: user.email },
                        )
                    }}
                </DialogDescription>
            </DialogHeader>
            <Form
                v-bind="UserController.destroy.form(user.id)"
                v-slot="{ processing }"
            >
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">{{ $t('Cancel') }}</Button>
                    </DialogClose>
                    <Button variant="destructive" :disabled="processing">
                        {{ $t('Delete') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
