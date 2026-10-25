<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import ConfirmDelete from '@/components/admin/catalog/ConfirmDelete.vue';
import Field from '@/components/admin/catalog/Field.vue';
import NativeSelect from '@/components/admin/catalog/NativeSelect.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    create,
    destroy,
    edit,
    index,
    store,
    update,
} from '@/routes/admin/redirects';

const props = defineProps<{
    redirect: {
        id: number;
        from_path: string;
        to_path: string;
        status_code: number;
        hits: number;
        last_hit_at: string | null;
    } | null;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Redirects', href: index() },
        props.redirect
            ? { title: props.redirect.from_path, href: edit(props.redirect.id) }
            : { title: 'New redirect', href: create() },
    ],
});

const form = useForm({
    from_path: props.redirect?.from_path ?? '',
    to_path: props.redirect?.to_path ?? '',
    status_code: props.redirect?.status_code ?? 301,
});

const submit = () => {
    form.submit(props.redirect ? update(props.redirect.id) : store());
};
</script>

<template>
    <Head :title="$t('Redirect')" />

    <div class="space-y-10 p-4">
        <form class="max-w-xl space-y-6" @submit.prevent="submit">
            <Heading
                :title="redirect ? $t('Edit redirect') : $t('New redirect')"
                :description="
                    redirect
                        ? $t('Used :count times, last on :date.', {
                              count: redirect.hits,
                              date: redirect.last_hit_at ?? '—',
                          })
                        : undefined
                "
            />
            <Field
                id="from_path"
                :label="$t('Old address')"
                :error="form.errors.from_path"
                :hint="
                    $t('A path such as /moskva/coffee. A full URL is fine too.')
                "
            >
                <Input id="from_path" v-model="form.from_path" required />
            </Field>
            <Field
                id="to_path"
                :label="$t('New address')"
                :error="form.errors.to_path"
            >
                <Input id="to_path" v-model="form.to_path" required />
            </Field>
            <Field
                id="status_code"
                :label="$t('Type')"
                :error="form.errors.status_code"
            >
                <NativeSelect id="status_code" v-model="form.status_code">
                    <option :value="301">
                        {{ $t('301 — moved for good') }}
                    </option>
                    <option :value="302">{{ $t('302 — temporary') }}</option>
                </NativeSelect>
            </Field>
            <Button :disabled="form.processing">
                {{ redirect ? $t('Save changes') : $t('Create redirect') }}
            </Button>
        </form>

        <div v-if="redirect" class="max-w-xl space-y-4">
            <Heading variant="small" :title="$t('Delete redirect')" />
            <ConfirmDelete
                :action="destroy(redirect.id)"
                :title="$t('Delete this redirect?')"
                :description="$t('The old address will show a not-found page.')"
            />
        </div>
    </div>
</template>
