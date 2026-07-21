<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Download } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useMapLayout } from '@/composables/useMapLayout';
import { exportSvgToPng } from '@/composables/useSvgExport';
import { map as mapIndex } from '@/routes';
import { move as moveDevice } from '@/routes/map/devices';
import type { MapDevice, MapLink, SiteSummary } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    site: SiteSummary;
    devices: MapDevice[];
    links: MapLink[];
    can: { arrange: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.map', href: mapIndex() }],
    },
});

const WIDTH = 1200;
const HEIGHT = 760;

const { positions, dragging, start, move, end } = useMapLayout(props.devices, {
    width: WIDTH,
    gap: 210,
    onDrop: (id, point) => {
        router.patch(
            moveDevice(id).url,
            { map_x: point.x, map_y: point.y },
            { preserveScroll: true, preserveState: true },
        );
    },
});

const svg = ref<SVGSVGElement | null>(null);

const kindColors: Record<string, string> = {
    switch: '#0284c7',
    patch_panel: '#d97706',
    router: '#7c3aed',
    firewall: '#e11d48',
    server: '#059669',
    ups: '#64748b',
    other: '#737373',
};

const colorOf = (device: MapDevice): string =>
    device.color ?? kindColors[device.kind] ?? kindColors.other;

/** Copper is a plain grey line, fibre a cyan dashed one. */
const linkColor = (link: MapLink): string =>
    link.media === 'fibre' ? '#0891b2' : '#94a3b8';

const linkDash = (link: MapLink): string =>
    link.media === 'fibre' ? '9 5' : '0';

function endpoints(link: MapLink) {
    return { a: positions[link.a], b: positions[link.b] };
}

function midpoint(link: MapLink): { x: number; y: number } | null {
    const { a, b } = endpoints(link);

    return a && b ? { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 } : null;
}

const hasDevices = computed(() => props.devices.length > 0);

function exportPng(): void {
    if (svg.value) {
        exportSvgToPng(svg.value, `site-${props.site.code}`);
    }
}
</script>

<template>
    <Head :title="`${site.name} — ${t('map.title')}`" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            :title="`${site.name} — ${t('map.title')}`"
            :description="t('map.siteHint')"
        >
            <template #actions>
                <Link :href="mapIndex().url">
                    <Button size="sm" variant="outline">
                        <ArrowLeft class="size-4" />
                        {{ t('map.backToGlobal') }}
                    </Button>
                </Link>
                <Button size="sm" variant="outline" @click="exportPng">
                    <Download class="size-4" />
                    {{ t('map.exportPng') }}
                </Button>
            </template>
        </PageHeader>

        <p
            v-if="!hasDevices"
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            {{ t('map.noDevices') }}
        </p>

        <div v-else class="overflow-auto rounded-xl border bg-card">
            <svg
                ref="svg"
                :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
                class="min-w-[900px] touch-none select-none"
                :style="{ width: '100%', aspectRatio: `${WIDTH} / ${HEIGHT}` }"
                @pointermove="move"
                @pointerup="end"
                @pointerleave="end"
            >
                <rect :width="WIDTH" :height="HEIGHT" fill="#f8fafc" />

                <g
                    v-for="link in links"
                    :key="link.id"
                >
                    <template v-if="endpoints(link).a && endpoints(link).b">
                        <line
                            :x1="endpoints(link).a.x"
                            :y1="endpoints(link).a.y"
                            :x2="endpoints(link).b.x"
                            :y2="endpoints(link).b.y"
                            :stroke="linkColor(link)"
                            stroke-width="2.5"
                            :stroke-dasharray="linkDash(link)"
                        />
                        <text
                            v-if="link.media === 'fibre' && midpoint(link)"
                            :x="midpoint(link)!.x"
                            :y="midpoint(link)!.y - 6"
                            text-anchor="middle"
                            font-size="12"
                            fill="#0891b2"
                        >
                            {{ t('cable.strandCount', { count: link.strands ?? 1 }) }}
                        </text>
                    </template>
                </g>

                <g
                    v-for="device in devices"
                    :key="device.id"
                    :transform="`translate(${positions[device.id].x}, ${positions[device.id].y})`"
                    class="cursor-move"
                    @pointerdown="start(device.id, $event)"
                >
                    <rect
                        x="-78"
                        y="-26"
                        width="156"
                        height="52"
                        rx="10"
                        fill="#ffffff"
                        :stroke="colorOf(device)"
                        stroke-width="2.5"
                        :opacity="dragging === device.id ? 0.85 : 1"
                    />
                    <rect x="-78" y="-26" width="6" height="52" rx="3" :fill="colorOf(device)" />
                    <text
                        x="0"
                        y="-3"
                        text-anchor="middle"
                        font-size="15"
                        font-weight="600"
                        fill="#0f172a"
                    >
                        {{ device.name.slice(0, 16) }}
                    </text>
                    <text
                        x="0"
                        y="16"
                        text-anchor="middle"
                        font-size="12"
                        fill="#64748b"
                    >
                        {{ t(`model.kind.${device.kind}`) }}
                    </text>
                </g>
            </svg>
        </div>

        <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground">
            <span class="flex items-center gap-2">
                <svg width="34" height="8"><line x1="0" y1="4" x2="34" y2="4" stroke="#94a3b8" stroke-width="2.5" /></svg>
                {{ t('cable.mediaKind.utp') }}
            </span>
            <span class="flex items-center gap-2">
                <svg width="34" height="8"><line x1="0" y1="4" x2="34" y2="4" stroke="#0891b2" stroke-width="2.5" stroke-dasharray="6 4" /></svg>
                {{ t('cable.mediaKind.fibre') }}
            </span>
            <span v-if="can.arrange">{{ t('map.dragHint') }}</span>
        </div>
    </div>
</template>
