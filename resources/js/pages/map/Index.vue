<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Plus } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import TunnelFormDialog from '@/components/map/TunnelFormDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useMapLayout } from '@/composables/useMapLayout';
import { exportSvgToPng } from '@/composables/useSvgExport';
import { map as mapIndex } from '@/routes';
import { site as siteMap } from '@/routes/map';
import { move as moveSite } from '@/routes/map/sites';
import { destroy as removeTunnel } from '@/routes/tunnels';
import type { MapSite, Tunnel } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    sites: MapSite[];
    tunnels: Tunnel[];
    types: string[];
    statuses: string[];
    can: { manage: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.map', href: mapIndex() }],
    },
});

const WIDTH = 1200;
const HEIGHT = 760;

const { positions, dragging, start, move, end } = useMapLayout(props.sites, {
    width: WIDTH,
    gap: 240,
    onDrop: (id, point) => {
        router.patch(
            moveSite(id).url,
            { map_x: point.x, map_y: point.y },
            { preserveScroll: true, preserveState: true },
        );
    },
});

const svg = ref<SVGSVGElement | null>(null);
const adding = ref(false);

/** Kerio VPN is a solid line, IPsec dashed — the two ways sites are joined. */
const dash = (tunnel: Tunnel): string =>
    tunnel.type === 'ipsec' ? '10 6' : '0';

const edgeColor = (tunnel: Tunnel): string =>
    tunnel.status === 'down'
        ? '#dc2626'
        : tunnel.status === 'planned'
          ? '#94a3b8'
          : '#0ea5e9';

const nodeColor = (site: MapSite): string => site.color ?? '#475569';

function tunnelMidpoint(tunnel: Tunnel): { x: number; y: number } | null {
    const a = positions[tunnel.site_a_id];
    const b = positions[tunnel.site_b_id];

    return a && b ? { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 } : null;
}

function endpoints(tunnel: Tunnel) {
    return { a: positions[tunnel.site_a_id], b: positions[tunnel.site_b_id] };
}

function open(site: MapSite): void {
    // A click that ended a drag should not also navigate.
    if (dragging.value === null) {
        router.get(siteMap(site.id).url);
    }
}

function removeTunnelLine(tunnel: Tunnel): void {
    if (confirm(t('tunnel.removeConfirm'))) {
        router.delete(removeTunnel(tunnel.id).url, { preserveScroll: true });
    }
}

function exportPng(): void {
    if (svg.value) {
        exportSvgToPng(svg.value, 'network-map');
    }
}
</script>

<template>
    <Head :title="t('map.title')" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader :title="t('map.title')" :description="t('map.globalHint')">
            <template #actions>
                <Button size="sm" variant="outline" @click="exportPng">
                    <Download class="size-4" />
                    {{ t('map.exportPng') }}
                </Button>
                <Button v-if="can.manage" size="sm" @click="adding = true">
                    <Plus class="size-4" />
                    {{ t('tunnel.new') }}
                </Button>
            </template>
        </PageHeader>

        <div class="overflow-auto rounded-xl border bg-card">
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

                <!-- Tunnels sit under the sites they connect. -->
                <g
                    v-for="tunnel in tunnels"
                    :key="tunnel.id"
                    class="cursor-pointer"
                    @click="can.manage && removeTunnelLine(tunnel)"
                >
                    <template v-if="endpoints(tunnel).a && endpoints(tunnel).b">
                        <line
                            :x1="endpoints(tunnel).a.x"
                            :y1="endpoints(tunnel).a.y"
                            :x2="endpoints(tunnel).b.x"
                            :y2="endpoints(tunnel).b.y"
                            :stroke="edgeColor(tunnel)"
                            :stroke-width="3"
                            :stroke-dasharray="dash(tunnel)"
                        />
                        <g v-if="tunnelMidpoint(tunnel)">
                            <rect
                                :x="tunnelMidpoint(tunnel)!.x - 46"
                                :y="tunnelMidpoint(tunnel)!.y - 13"
                                width="92"
                                height="26"
                                rx="13"
                                fill="#ffffff"
                                :stroke="edgeColor(tunnel)"
                            />
                            <text
                                :x="tunnelMidpoint(tunnel)!.x"
                                :y="tunnelMidpoint(tunnel)!.y + 5"
                                text-anchor="middle"
                                font-size="13"
                                fill="#334155"
                            >
                                {{ t(`tunnel.typeKind.${tunnel.type}`) }}
                            </text>
                        </g>
                    </template>
                </g>

                <!-- Sites on top, draggable. -->
                <g
                    v-for="site in sites"
                    :key="site.id"
                    :transform="`translate(${positions[site.id].x}, ${positions[site.id].y})`"
                    class="cursor-pointer"
                    @pointerdown="start(site.id, $event)"
                    @click="open(site)"
                >
                    <rect
                        x="-80"
                        y="-40"
                        width="160"
                        height="80"
                        rx="14"
                        :fill="nodeColor(site)"
                        :opacity="dragging === site.id ? 0.85 : 1"
                    />
                    <text
                        x="0"
                        y="-8"
                        text-anchor="middle"
                        font-size="17"
                        font-weight="600"
                        fill="#ffffff"
                    >
                        {{ site.name.slice(0, 18) }}
                    </text>
                    <text
                        x="0"
                        y="15"
                        text-anchor="middle"
                        font-size="13"
                        fill="#ffffff"
                        opacity="0.85"
                    >
                        {{ site.code }} ·
                        {{
                            t('map.deviceCount', { count: site.devices_count })
                        }}
                    </text>
                </g>
            </svg>
        </div>

        <div
            class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground"
        >
            <span class="flex items-center gap-2">
                <svg width="34" height="8">
                    <line
                        x1="0"
                        y1="4"
                        x2="34"
                        y2="4"
                        stroke="#0ea5e9"
                        stroke-width="3"
                    />
                </svg>
                {{ t('tunnel.typeKind.kerio_vpn') }}
            </span>
            <span class="flex items-center gap-2">
                <svg width="34" height="8">
                    <line
                        x1="0"
                        y1="4"
                        x2="34"
                        y2="4"
                        stroke="#0ea5e9"
                        stroke-width="3"
                        stroke-dasharray="6 4"
                    />
                </svg>
                {{ t('tunnel.typeKind.ipsec') }}
            </span>
            <span v-if="can.manage">{{ t('map.dragHint') }}</span>
        </div>
    </div>

    <TunnelFormDialog
        v-model:open="adding"
        :sites="sites"
        :types="types"
        :statuses="statuses"
    />
</template>
