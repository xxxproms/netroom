<script setup lang="ts">
import { Check } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps<{
    name: string;
    modelValue?: string | null;
}>();

/** A short palette reads faster than a colour wheel and keeps racks legible. */
const palette = [
    '#0284c7',
    '#0891b2',
    '#059669',
    '#65a30d',
    '#d97706',
    '#dc2626',
    '#e11d48',
    '#7c3aed',
    '#4f46e5',
    '#64748b',
];

const selected = ref<string | null>(props.modelValue ?? null);
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5">
        <!-- Empty means "colour it by what the device is". -->
        <button
            type="button"
            class="flex size-7 items-center justify-center rounded-md border text-xs text-muted-foreground"
            :class="{ 'ring-2 ring-primary ring-offset-1': !selected }"
            :title="t('device.colorAuto')"
            @click="selected = null"
        >
            {{ t('device.auto') }}
        </button>

        <button
            v-for="color in palette"
            :key="color"
            type="button"
            class="flex size-7 items-center justify-center rounded-md border"
            :style="{ backgroundColor: color }"
            :class="{ 'ring-2 ring-primary ring-offset-1': selected === color }"
            @click="selected = color"
        >
            <Check v-if="selected === color" class="size-4 text-white" />
        </button>

        <input type="hidden" :name="name" :value="selected ?? ''" />
    </div>
</template>
