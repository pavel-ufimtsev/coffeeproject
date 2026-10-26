<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Field from '@/components/admin/catalog/Field.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { preview, test, update } from '@/routes/admin/emails';

type Texts = {
    subject: string;
    heading: string;
    intro: string;
    outro: string;
};

const props = defineProps<{
    template: {
        key: string;
        title: string;
        description: string;
        placeholders: string[];
        texts: Texts;
        defaults: Texts;
    };
}>();

const form = useForm<Texts>({ ...props.template.texts });

// Bumped after each save so the iframe shows the saved texts.
const version = ref(0);
const sending = ref(false);

const submit = () => {
    form.submit(update(props.template.key), {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults();
            version.value++;
        },
    });
};

const resetToDefaults = () => {
    Object.assign(form, props.template.defaults);
};

const sendTest = () => {
    router.post(
        test.url(props.template.key),
        {},
        {
            preserveScroll: true,
            onStart: () => (sending.value = true),
            onFinish: () => (sending.value = false),
        },
    );
};
</script>

<template>
    <div class="grid gap-8 xl:grid-cols-[minmax(0,32rem)_minmax(0,1fr)]">
        <form class="space-y-6" @submit.prevent="submit">
            <Heading
                :title="template.title"
                :description="template.description"
            />

            <Field
                :id="`${template.key}_subject`"
                :label="$t('Subject')"
                :error="form.errors.subject"
            >
                <Input
                    :id="`${template.key}_subject`"
                    v-model="form.subject"
                    required
                />
            </Field>
            <Field
                :id="`${template.key}_heading`"
                :label="$t('Heading')"
                :error="form.errors.heading"
            >
                <Input
                    :id="`${template.key}_heading`"
                    v-model="form.heading"
                    required
                />
            </Field>
            <Field
                :id="`${template.key}_intro`"
                :label="$t('Text above')"
                :error="form.errors.intro"
                :hint="
                    template.key === 'order_placed'
                        ? $t('Shown above the order lines.')
                        : $t('Shown above the button.')
                "
            >
                <Textarea
                    :id="`${template.key}_intro`"
                    v-model="form.intro"
                    rows="5"
                />
            </Field>
            <Field
                :id="`${template.key}_outro`"
                :label="$t('Text below')"
                :error="form.errors.outro"
                :hint="$t('Shown under the button, above the contacts.')"
            >
                <Textarea
                    :id="`${template.key}_outro`"
                    v-model="form.outro"
                    rows="3"
                />
            </Field>

            <div class="space-y-2 rounded-lg bg-muted/50 px-4 py-3 text-sm">
                <p>
                    {{ $t('Placeholders') }}:
                    <code
                        v-for="name in template.placeholders"
                        :key="name"
                        class="mr-1 rounded bg-background px-1.5 py-0.5 text-xs"
                        >{{ '{' + name + '}' }}</code
                    >
                </p>
                <p class="text-muted-foreground">
                    {{
                        $t(
                            'A blank line starts a new paragraph. **Bold** and [link](https://...) work too.',
                        )
                    }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button :disabled="form.processing || !form.isDirty">
                    {{ $t('Save changes') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="sending || form.isDirty"
                    :title="form.isDirty ? $t('Save first') : undefined"
                    @click="sendTest"
                >
                    {{ $t('Send me a test') }}
                </Button>
                <Button type="button" variant="ghost" @click="resetToDefaults">
                    {{ $t('Restore default text') }}
                </Button>
            </div>
        </form>

        <div class="space-y-2">
            <p class="text-sm text-muted-foreground">
                {{
                    form.isDirty
                        ? $t(
                              'Preview of the saved version. Save to see your changes.',
                          )
                        : $t('Preview')
                }}
            </p>
            <iframe
                :key="version"
                :src="preview.url(template.key)"
                :title="template.title"
                class="h-[44rem] w-full rounded-lg border bg-white"
            />
        </div>
    </div>
</template>
