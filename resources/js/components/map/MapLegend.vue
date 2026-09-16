<script setup lang="ts">
import { ChevronDown, ChevronUp } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ANNOTATION_COLORS } from '@/composables/useMapAnnotations';
import type { MapAnnotation } from '@/types';

/**
 * The key to the drawing. It lives inside the canvas rather than under it, so
 * an exported picture explains itself to whoever opens the file. On a narrow
 * screen it drops below the editing toolbar rather than fighting it for the
 * same strip of canvas.
 */

const { t } = useI18n();

defineProps<{
    lines: { color: string; dashed?: boolean; label: string }[];
    dots?: { color: string; label: string }[];
    zones?: MapAnnotation[];
}>();

const open = ref(true);
</script>

<template>
    <div
        class="map-legend pointer-events-auto absolute top-16 left-3 z-10 max-w-[220px] rounded-lg border bg-card/95 text-xs shadow-sm backdrop-blur md:top-3"
    >
        <button
            type="button"
            class="flex w-full items-center justify-between gap-2 px-2.5 py-1.5 font-semibold text-foreground"
            @click="open = !open"
        >
            {{ t('map.legend') }}
            <component :is="open ? ChevronUp : ChevronDown" class="size-3.5" />
        </button>

        <div v-if="open" class="flex flex-col gap-1.5 px-2.5 pt-0.5 pb-2.5">
            <span
                v-for="line in lines"
                :key="line.label"
                class="flex items-center gap-2 text-muted-foreground"
            >
                <svg width="26" height="8" class="shrink-0">
                    <line
                        x1="0"
                        y1="4"
                        x2="26"
                        y2="4"
                        :stroke="line.color"
                        stroke-width="2.5"
                        :stroke-dasharray="line.dashed ? '5 3' : undefined"
                    />
                </svg>
                {{ line.label }}
            </span>

            <span
                v-for="dot in dots ?? []"
                :key="dot.label"
                class="flex items-center gap-2 text-muted-foreground"
            >
                <span
                    class="ml-2 size-2.5 shrink-0 rounded-full"
                    :style="{ background: dot.color }"
                />
                {{ dot.label }}
            </span>

            <template v-if="zones?.length">
                <span class="mt-1 border-t pt-1.5 font-medium text-foreground">
                    {{ t('map.zones') }}
                </span>
                <span
                    v-for="zone in zones"
                    :key="zone.id"
                    class="flex items-center gap-2 text-muted-foreground"
                >
                    <span
                        class="size-3 shrink-0 rounded-sm border-2 border-dashed"
                        :style="{
                            borderColor: zone.color ?? ANNOTATION_COLORS.zone,
                        }"
                    />
                    <span class="truncate">
                        {{ zone.text || t('map.zoneUntitled') }}
                    </span>
                </span>
            </template>
        </div>
    </div>
</template>
