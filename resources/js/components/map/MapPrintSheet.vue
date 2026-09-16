<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { ANNOTATION_COLORS } from '@/composables/useMapAnnotations';
import type { MapAnnotation } from '@/types';

/**
 * The diagram as a sheet of paper: a frame, the drawing, and a title block
 * naming what this is, who printed it and when — the things a drawing pinned
 * inside a cupboard door has to answer on its own.
 *
 * It is teleported to the body and only becomes visible while printing, so the
 * page around it can be hidden without fighting the layout.
 */

const { t, d } = useI18n();

defineProps<{
    image: string;
    title: string;
    subtitle?: string | null;
    author: string;
    lines: { color: string; dashed?: boolean; label: string }[];
    dots?: { color: string; label: string }[];
    zones?: MapAnnotation[];
}>();

const printedAt = new Date();
</script>

<template>
    <div class="map-print-sheet">
        <div class="map-print-frame">
            <img :src="image" :alt="title" class="map-print-image" />
        </div>

        <div class="map-print-block">
            <div class="map-print-titles">
                <p class="map-print-title">{{ title }}</p>
                <p v-if="subtitle" class="map-print-subtitle">{{ subtitle }}</p>
            </div>

            <div class="map-print-legend">
                <span v-for="line in lines" :key="line.label">
                    <svg width="24" height="8">
                        <line
                            x1="0"
                            y1="4"
                            x2="24"
                            y2="4"
                            :stroke="line.color"
                            stroke-width="2.5"
                            :stroke-dasharray="line.dashed ? '5 3' : undefined"
                        />
                    </svg>
                    {{ line.label }}
                </span>
                <span v-for="dot in dots ?? []" :key="dot.label">
                    <i
                        class="map-print-dot"
                        :style="{ background: dot.color }"
                    />
                    {{ dot.label }}
                </span>
                <span v-for="zone in zones ?? []" :key="zone.id">
                    <i
                        class="map-print-zone"
                        :style="{
                            borderColor: zone.color ?? ANNOTATION_COLORS.zone,
                        }"
                    />
                    {{ zone.text || t('map.zoneUntitled') }}
                </span>
            </div>

            <dl class="map-print-meta">
                <dt>{{ t('map.printedBy') }}</dt>
                <dd>{{ author }}</dd>
                <dt>{{ t('map.printedAt') }}</dt>
                <dd>{{ d(printedAt, 'short') }}</dd>
                <dt>{{ t('app.name') }}</dt>
                <dd>{{ t('app.tagline') }}</dd>
            </dl>
        </div>
    </div>
</template>
