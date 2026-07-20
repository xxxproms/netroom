<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { store, update } from '@/routes/device-models';
import type { DeviceModel, PortTemplate } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    kinds: string[];
    media: string[];
    roles: string[];
    model?: DeviceModel;
}>();

const open = defineModel<boolean>('open', { required: true });

/**
 * A row in the editor. Speed is optional here (patch panel ports have none),
 * while the API sends null — the two are converted when loading and saving.
 */
type TemplateRow = Omit<PortTemplate, 'speed_mbps'> & { speed_mbps?: number };

const emptyTemplate = (): TemplateRow => ({
    name_prefix: '',
    start_number: 1,
    count: 24,
    media: 'rj45',
    speed_mbps: 1000,
    role: 'network',
});

const toRows = (templates?: PortTemplate[]): TemplateRow[] =>
    templates?.map((template) => ({
        ...template,
        speed_mbps: template.speed_mbps ?? undefined,
    })) ?? [emptyTemplate()];

const form = useForm({
    vendor: props.model?.vendor ?? '',
    model: props.model?.model ?? '',
    kind: props.model?.kind ?? 'switch',
    u_height: props.model?.u_height ?? 1,
    notes: props.model?.notes ?? '',
    port_templates: toRows(props.model?.port_templates),
});

// Re-open on a different model: start from that model's values again.
watch(
    () => props.model,
    (model) => {
        form.defaults({
            vendor: model?.vendor ?? '',
            model: model?.model ?? '',
            kind: model?.kind ?? 'switch',
            u_height: model?.u_height ?? 1,
            notes: model?.notes ?? '',
            port_templates: toRows(model?.port_templates),
        });
        form.reset();
    },
);

function addTemplate(): void {
    const last = form.port_templates.at(-1);

    form.port_templates.push({
        ...emptyTemplate(),
        // A new run continues where the previous one ended, which is how
        // uplink ports are usually numbered.
        start_number: last ? last.start_number + last.count : 1,
        count: 4,
        media: 'sfp',
    });
}

function submit(): void {
    const action = props.model ? update(props.model.id) : store();

    form.submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{ model ? t('model.edit') : t('model.new') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('model.portTemplatesHint') }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="vendor"
                        :label="t('model.vendor')"
                        :error="form.errors.vendor"
                    >
                        <Input id="vendor" v-model="form.vendor" required />
                    </FormField>

                    <FormField
                        id="model"
                        :label="t('model.model')"
                        :error="form.errors.model"
                    >
                        <Input id="model" v-model="form.model" required />
                    </FormField>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="model-kind"
                        :label="t('common.type')"
                        :error="form.errors.kind"
                    >
                        <select
                            id="model-kind"
                            v-model="form.kind"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option
                                v-for="kind in kinds"
                                :key="kind"
                                :value="kind"
                            >
                                {{ t(`model.kind.${kind}`) }}
                            </option>
                        </select>
                    </FormField>

                    <FormField
                        id="model-height"
                        :label="t('model.height')"
                        :error="form.errors.u_height"
                    >
                        <Input
                            id="model-height"
                            v-model.number="form.u_height"
                            type="number"
                            min="1"
                            max="10"
                        />
                    </FormField>
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium">
                            {{ t('model.portTemplates') }}
                        </span>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="addTemplate"
                        >
                            <Plus class="size-4" />
                            {{ t('model.addTemplate') }}
                        </Button>
                    </div>

                    <div
                        v-for="(template, index) in form.port_templates"
                        :key="index"
                        class="grid grid-cols-2 items-end gap-2 rounded-lg border p-3 sm:grid-cols-6"
                    >
                        <label class="grid gap-1 text-xs">
                            <span class="text-muted-foreground">
                                {{ t('model.count') }}
                            </span>
                            <Input
                                v-model.number="template.count"
                                type="number"
                                min="1"
                                max="200"
                                class="h-8"
                            />
                        </label>

                        <label class="grid gap-1 text-xs">
                            <span class="text-muted-foreground">
                                {{ t('model.start') }}
                            </span>
                            <Input
                                v-model.number="template.start_number"
                                type="number"
                                min="1"
                                class="h-8"
                            />
                        </label>

                        <label class="grid gap-1 text-xs">
                            <span class="text-muted-foreground">
                                {{ t('model.media') }}
                            </span>
                            <select
                                v-model="template.media"
                                class="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                            >
                                <option
                                    v-for="value in media"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ t(`model.mediaKind.${value}`) }}
                                </option>
                            </select>
                        </label>

                        <label class="grid gap-1 text-xs">
                            <span class="text-muted-foreground">
                                {{ t('model.speed') }}
                            </span>
                            <Input
                                v-model.number="template.speed_mbps"
                                type="number"
                                min="10"
                                class="h-8"
                            />
                        </label>

                        <label class="grid gap-1 text-xs">
                            <span class="text-muted-foreground">
                                {{ t('model.role') }}
                            </span>
                            <select
                                v-model="template.role"
                                class="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                            >
                                <option
                                    v-for="value in roles"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ t(`model.roleKind.${value}`) }}
                                </option>
                            </select>
                        </label>

                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            class="size-8 text-destructive"
                            @click="form.port_templates.splice(index, 1)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="open = false">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ t('common.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
