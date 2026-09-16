<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { MapAnnotation } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    annotation: MapAnnotation;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    save: [value: { text: string | null; color: string | null }];
    remove: [];
}>();

const swatches = [
    '#64748b',
    '#2563eb',
    '#0891b2',
    '#059669',
    '#65a30d',
    '#d97706',
    '#dc2626',
    '#db2777',
    '#7c3aed',
];

const text = ref(props.annotation.text ?? '');
const color = ref<string | null>(props.annotation.color);

watch(
    () => props.annotation,
    (annotation) => {
        text.value = annotation.text ?? '';
        color.value = annotation.color;
    },
);

function save(): void {
    emit('save', {
        text: text.value.trim() === '' ? null : text.value.trim(),
        color: color.value,
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>
                    {{ t(`map.edit.${annotation.type}`) }}
                </DialogTitle>
            </DialogHeader>

            <div class="grid gap-4">
                <FormField
                    id="annotation-text"
                    :label="
                        annotation.type === 'zone'
                            ? t('map.zoneLabel')
                            : t('map.noteText')
                    "
                >
                    <textarea
                        id="annotation-text"
                        v-model="text"
                        :rows="annotation.type === 'zone' ? 1 : 4"
                        maxlength="200"
                        autofocus
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                    ></textarea>
                </FormField>

                <FormField id="annotation-color" :label="t('map.nodeColor')">
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-for="swatch in swatches"
                            :key="swatch"
                            type="button"
                            class="size-6 rounded-full border transition"
                            :class="
                                color === swatch
                                    ? 'ring-2 ring-primary ring-offset-2'
                                    : ''
                            "
                            :style="{ background: swatch }"
                            @click="color = swatch"
                        />
                        <button
                            type="button"
                            class="rounded-md border px-2 py-1 text-xs text-muted-foreground"
                            :class="
                                color === null
                                    ? 'ring-2 ring-primary ring-offset-1'
                                    : ''
                            "
                            @click="color = null"
                        >
                            {{ t('map.colorAuto') }}
                        </button>
                    </div>
                </FormField>
            </div>

            <DialogFooter class="sm:justify-between">
                <Button variant="destructive" @click="emit('remove')">
                    {{ t('common.delete') }}
                </Button>

                <span class="flex gap-2">
                    <Button variant="ghost" @click="open = false">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button @click="save">{{ t('common.save') }}</Button>
                </span>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
