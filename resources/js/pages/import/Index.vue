<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Download, FileSpreadsheet, FileUp, Info } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { preview } from '@/actions/App/Http/Controllers/ImportController';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { exportMethod, importMethod } from '@/routes';

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.import', href: importMethod() }],
    },
});

const fileName = ref<string | null>(null);

function onPick(event: Event): void {
    const input = event.target as HTMLInputElement;
    fileName.value = input.files?.[0]?.name ?? null;
}
</script>

<template>
    <Head :title="t('import.title')" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader :title="t('import.title')" :description="t('import.listHint')" />

        <Form
            v-bind="preview.form()"
            :options="{ preserveScroll: true }"
            class="flex max-w-xl flex-col gap-5"
            v-slot="{ errors, processing }"
        >
            <FormField id="import-file" :label="t('import.upload')" :error="errors.file">
                <label
                    for="import-file"
                    class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-6 py-10 text-center transition-colors hover:border-primary/50 hover:bg-accent/40"
                >
                    <FileSpreadsheet class="size-8 text-muted-foreground/70" />
                    <span v-if="fileName" class="text-sm font-medium">{{ fileName }}</span>
                    <span v-else class="text-sm text-muted-foreground">
                        {{ t('import.pick') }}
                    </span>
                    <span class="max-w-md text-xs text-muted-foreground/80">
                        {{ t('import.uploadHint') }}
                    </span>
                    <input
                        id="import-file"
                        type="file"
                        name="file"
                        accept=".xlsx,.xls"
                        class="sr-only"
                        required
                        @change="onPick"
                    />
                </label>
            </FormField>

            <p class="flex items-start gap-2 text-xs text-muted-foreground">
                <Info class="mt-0.5 size-3.5 shrink-0" />
                {{ t('import.skipHint') }}
            </p>

            <div>
                <Button type="submit" :disabled="processing || !fileName">
                    <FileUp class="size-4" />
                    {{ processing ? t('import.analyzing') : t('import.analyze') }}
                </Button>
            </div>
        </Form>

        <div class="flex max-w-xl flex-col gap-2 border-t pt-6">
            <h2 class="text-sm font-semibold">{{ t('import.exportTitle') }}</h2>
            <p class="text-sm text-muted-foreground">{{ t('import.exportHint') }}</p>
            <div>
                <Button as-child variant="outline" size="sm">
                    <a :href="exportMethod().url">
                        <Download class="size-4" />
                        {{ t('import.exportButton') }}
                    </a>
                </Button>
            </div>
        </div>
    </div>
</template>
