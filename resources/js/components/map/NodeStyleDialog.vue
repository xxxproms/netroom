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
import { Input } from '@/components/ui/input';

const { t } = useI18n();

const props = defineProps<{
    title: string;
    name: string;
    color: string | null;
    error?: string | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    save: [value: { name: string; color: string | null }];
}>();

const swatches = [
    '#475569',
    '#2563eb',
    '#0891b2',
    '#059669',
    '#65a30d',
    '#d97706',
    '#dc2626',
    '#db2777',
    '#7c3aed',
];

const name = ref(props.name);
const color = ref<string | null>(props.color);

watch(open, (isOpen) => {
    if (isOpen) {
        name.value = props.name;
        color.value = props.color;
    }
});

function save(): void {
    if (name.value.trim() === '') {
        return;
    }

    emit('save', { name: name.value.trim(), color: color.value });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
            </DialogHeader>

            <div class="grid gap-4">
                <FormField id="node-name" :label="t('map.nodeName')">
                    <Input id="node-name" v-model="name" autofocus />
                </FormField>

                <FormField id="node-color" :label="t('map.nodeColor')">
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

                <p v-if="error" class="text-sm text-destructive">
                    {{ error }}
                </p>
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="open = false">
                    {{ t('common.cancel') }}
                </Button>
                <Button :disabled="name.trim() === ''" @click="save">
                    {{ t('common.save') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
