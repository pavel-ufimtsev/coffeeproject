<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import Field from './Field.vue';

defineProps<{
    errors: Partial<Record<string, string>>;
    headingPlaceholder?: string;
}>();

const h1 = defineModel<string | null>('h1', { required: true });
const intro = defineModel<string | null>('intro', { required: true });
const seoTitle = defineModel<string | null>('seoTitle', { required: true });
const seoDescription = defineModel<string | null>('seoDescription', {
    required: true,
});
</script>

<template>
    <div class="grid gap-6">
        <Field
            id="h1"
            :label="$t('Page heading (H1)')"
            :error="errors.h1"
            :hint="$t('Leave empty to use the name.')"
        >
            <Input id="h1" v-model="h1" :placeholder="headingPlaceholder" />
        </Field>

        <Field
            id="intro"
            :label="$t('Intro text')"
            :error="errors.intro"
            :hint="
                $t('Shown under the heading. Helps the page rank in search.')
            "
        >
            <Textarea id="intro" v-model="intro" rows="4" />
        </Field>

        <Field
            id="seo_title"
            :label="$t('SEO title')"
            :error="errors.seo_title"
            :hint="
                $t(':count characters. Search engines show about 60.', {
                    count: seoTitle?.length ?? 0,
                })
            "
        >
            <Input
                id="seo_title"
                v-model="seoTitle"
                :placeholder="$t('Generated from the heading when empty')"
            />
        </Field>

        <Field
            id="seo_description"
            :label="$t('SEO description')"
            :error="errors.seo_description"
            :hint="
                $t(':count characters. Search engines show about 160.', {
                    count: seoDescription?.length ?? 0,
                })
            "
        >
            <Textarea
                id="seo_description"
                v-model="seoDescription"
                rows="3"
                :placeholder="$t('Generated when empty')"
            />
        </Field>
    </div>
</template>
