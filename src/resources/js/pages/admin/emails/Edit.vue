<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import EmailTemplateForm from '@/components/admin/shop/EmailTemplateForm.vue';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/admin/emails';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Emails', href: edit() }],
    },
});

const props = defineProps<{
    templates: {
        key: string;
        title: string;
        description: string;
        placeholders: string[];
        texts: {
            subject: string;
            heading: string;
            intro: string;
            outro: string;
        };
        defaults: {
            subject: string;
            heading: string;
            intro: string;
            outro: string;
        };
    }[];
}>();

const active = ref(props.templates[0].key);
</script>

<template>
    <Head :title="$t('Emails')" />

    <div class="space-y-6 p-4">
        <div class="flex flex-wrap gap-2">
            <Button
                v-for="template in templates"
                :key="template.key"
                :variant="active === template.key ? 'default' : 'outline'"
                size="sm"
                @click="active = template.key"
            >
                {{ template.title }}
            </Button>
        </div>

        <template v-for="template in templates" :key="template.key">
            <EmailTemplateForm
                v-show="active === template.key"
                :template="template"
            />
        </template>
    </div>
</template>
