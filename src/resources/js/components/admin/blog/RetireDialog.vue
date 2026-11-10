<script setup lang="ts">
import { ref, watch } from 'vue';
import Field from '@/components/admin/catalog/Field.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

/**
 * Ask where a retired post's address should lead before archiving it.
 */
const props = defineProps<{
    suggested: string;
    title: string;
    processing?: boolean;
    error?: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    confirm: [payload: { redirect_to: string; note: string }];
}>();

const redirectTo = ref(props.suggested);
const note = ref('');

watch(open, (isOpen) => {
    if (isOpen) {
        redirectTo.value = props.suggested;
        note.value = '';
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'The post leaves the site. Its address will lead to the page below, so old links keep working.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>
            <form
                id="retire-form"
                class="grid gap-4"
                @submit.prevent="
                    emit('confirm', { redirect_to: redirectTo, note })
                "
            >
                <Field
                    id="retire-redirect"
                    :label="$t('Redirect to')"
                    :hint="
                        $t(
                            'A live post like /blog/… or a section like /blog/c/…',
                        )
                    "
                    :error="error"
                >
                    <Input
                        id="retire-redirect"
                        v-model="redirectTo"
                        placeholder="/blog"
                        required
                    />
                </Field>
                <Field id="retire-note" :label="$t('Reason (optional)')">
                    <Input id="retire-note" v-model="note" maxlength="255" />
                </Field>
            </form>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button type="button" variant="secondary">
                        {{ $t('Cancel') }}
                    </Button>
                </DialogClose>
                <Button form="retire-form" :disabled="processing">
                    {{ $t('Retire') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
