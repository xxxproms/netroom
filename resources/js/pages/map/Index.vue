<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Link2, Link2Off, Plus, Spline, Waypoints } from '@lucide/vue';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { ConnectionMode, VueFlow, useVueFlow } from '@vue-flow/core';
import type {
    Connection,
    Edge,
    Node,
    NodeDragEvent,
    NodeMouseEvent,
} from '@vue-flow/core';
import { MiniMap } from '@vue-flow/minimap';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { useI18n } from 'vue-i18n';
import AnnotationDialog from '@/components/map/AnnotationDialog.vue';
import FloatingEdge from '@/components/map/FloatingEdge.vue';
import MapExportMenu from '@/components/map/MapExportMenu.vue';
import MapLegend from '@/components/map/MapLegend.vue';
import MapPrintSheet from '@/components/map/MapPrintSheet.vue';
import MapSelection from '@/components/map/MapSelection.vue';
import MapToolbar from '@/components/map/MapToolbar.vue';
import MapViewBar from '@/components/map/MapViewBar.vue';
import NodeStyleDialog from '@/components/map/NodeStyleDialog.vue';
import NoteNode from '@/components/map/NoteNode.vue';
import SiteNode from '@/components/map/SiteNode.vue';
import TunnelFormDialog from '@/components/map/TunnelFormDialog.vue';
import ZoneNode from '@/components/map/ZoneNode.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { autoGrid } from '@/composables/mapLayout';
import {
    annotationIdOf,
    annotationNodes,
    saveAnnotationPosition,
    useMapAnnotations,
} from '@/composables/useMapAnnotations';
import {
    exportMapPng,
    exportMapSvg,
    printSheet,
    printableImage,
} from '@/composables/useMapExport';
import type { PaperOrientation, PaperSize } from '@/composables/useMapExport';
import { facetGroup, useMapFocus } from '@/composables/useMapFocus';
import type { FacetGroup, MapEntity } from '@/composables/useMapFocus';
import { useMapLinking } from '@/composables/useMapLinking';
import { useMapRouting } from '@/composables/useMapRouting';
import { GRID, useMapTools } from '@/composables/useMapTools';
import { useMapViewport } from '@/composables/useMapViewport';
import { map as mapIndex } from '@/routes';
import { site as siteMap } from '@/routes/map';
import { move as moveSite, style as styleSite } from '@/routes/map/sites';
import { show as showSite } from '@/routes/sites';
import { destroy as removeTunnel } from '@/routes/tunnels';
import type { MapAnnotation, MapSite, Tunnel } from '@/types';

const { t } = useI18n();
const page = usePage();
const { routing, toggle: toggleRouting } = useMapRouting();
const { linking, toggle: toggleLinking, stop: stopLinking } = useMapLinking();

const props = defineProps<{
    sites: MapSite[];
    tunnels: Tunnel[];
    annotations: MapAnnotation[];
    types: string[];
    statuses: string[];
    can: { manage: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.map', href: mapIndex() }],
    },
});

const { can } = props;

const FLOW_ID = 'map-global';
const {
    fitView,
    getSelectedNodes,
    getViewport,
    setViewport,
    updateNode,
    updateNodeInternals,
} = useVueFlow(FLOW_ID);
const tools = useMapTools(FLOW_ID);
const viewport = useMapViewport(FLOW_ID);
const drawings = useMapAnnotations(FLOW_ID, null);

// Vue Flow measures the DOM, so it can only run in the browser; rendering it
// under SSR would mismatch on hydration. Gate it on mount instead.
const mounted = ref(false);
onMounted(() => (mounted.value = true));

// Once the pane is up, force a measurement so the floating edges have node
// sizes to route against from the first paint, then put the user back where
// they left the map (or frame the whole graph, first time round).
function onPaneReady(): void {
    updateNodeInternals();
    viewport.restore();
}

/** Kerio VPN solid, IPsec dashed; red down, grey planned, sky when up. */
const edgeColor = (tunnel: Tunnel): string =>
    tunnel.status === 'down'
        ? '#dc2626'
        : tunnel.status === 'planned'
          ? '#94a3b8'
          : '#0ea5e9';

const legendLines = [
    { color: '#0ea5e9', label: t('tunnel.typeKind.kerio_vpn') },
    { color: '#0ea5e9', dashed: true, label: t('tunnel.typeKind.ipsec') },
];

const legendDots = [
    { color: '#0ea5e9', label: t('tunnel.statusKind.up') },
    { color: '#dc2626', label: t('tunnel.statusKind.down') },
    { color: '#94a3b8', label: t('tunnel.statusKind.planned') },
];

const grid = autoGrid(props.sites, { width: 1200, gap: 260 });

// What the view bar searches and filters on, built from the sites on this map.
const entities = computed<MapEntity[]>(() =>
    props.sites.map((site) => ({
        id: String(site.id),
        label: site.name,
        sub: [site.code, site.vlan_domain].filter(Boolean).join(' · '),
        facets: {
            kind: [site.kind],
            domain: site.vlan_domain ? [site.vlan_domain] : [],
        },
    })),
);

const focusEdges = computed(() =>
    props.tunnels.map((tunnel) => ({
        id: String(tunnel.id),
        source: String(tunnel.site_a_id),
        target: String(tunnel.site_b_id),
    })),
);

const focus = useMapFocus(FLOW_ID, entities, focusEdges);

const groups = computed<FacetGroup[]>(() =>
    [
        facetGroup(entities.value, 'kind', t('common.type'), (value) =>
            t(`site.kind.${value}`),
        ),
        facetGroup(entities.value, 'domain', t('map.domain'), (value) => value),
    ].filter((group): group is FacetGroup => group !== null),
);

// One selected site gets a panel with the way through to its own pages.
const selected = computed<MapSite | null>(() => {
    const picked = getSelectedNodes.value;

    return picked.length === 1
        ? (props.sites.find((s) => String(s.id) === picked[0].id) ?? null)
        : null;
});

const selectionRows = computed(() => {
    const site = selected.value;

    if (!site) {
        return [];
    }

    return [
        { label: t('common.type'), value: t(`site.kind.${site.kind}`) },
        { label: t('nav.devices'), value: String(site.devices_count) },
        { label: t('nav.rooms'), value: String(site.rooms_count) },
        ...(site.vlan_domain
            ? [{ label: t('map.domain'), value: site.vlan_domain }]
            : []),
    ];
});

const selectionLinks = computed(() => {
    const site = selected.value;

    return site
        ? [
              { label: t('map.openSiteMap'), href: siteMap(site.id).url },
              { label: t('map.openSite'), href: showSite(site.id).url },
          ]
        : [];
});

// Drawings come first so that, at equal z, the kit still paints over them.
const buildNodes = (): Node[] => [
    ...annotationNodes(props.annotations, can.manage),
    ...props.sites.map((site) => ({
        id: String(site.id),
        type: 'site',
        position: grid(site),
        class: focus.nodeClass(String(site.id)),
        ariaLabel: `${site.name}, ${site.code}, ${t(`site.kind.${site.kind}`)}`,
        data: {
            name: site.name,
            code: site.code,
            kind: site.kind,
            color: site.color,
            rooms_count: site.rooms_count,
            devices_count: site.devices_count,
        },
    })),
];

const buildEdges = (): Edge[] =>
    props.tunnels.map((tunnel) => ({
        id: String(tunnel.id),
        source: String(tunnel.site_a_id),
        target: String(tunnel.site_b_id),
        type: 'floating',
        class: focus.edgeClass({
            id: String(tunnel.id),
            source: String(tunnel.site_a_id),
            target: String(tunnel.site_b_id),
        }),
        data: {
            color: edgeColor(tunnel),
            dashed: tunnel.type === 'ipsec',
            label: t(`tunnel.typeKind.${tunnel.type}`),
        },
    }));

const nodes = ref<Node[]>(buildNodes());
const edges = ref<Edge[]>(buildEdges());

// After a create/delete the props change; reseed so the graph reflects it.
// Positions live in props too (saved on drag-stop), so nothing is lost.
watch(
    [() => props.sites, () => props.annotations],
    () => (nodes.value = buildNodes()),
    { deep: true },
);
watch(
    () => props.tunnels,
    () => (edges.value = buildEdges()),
    { deep: true },
);

// Narrowing only repaints: node classes go through updateNode so a drag that
// has not been saved yet is not snapped back to whatever the props still say.
watch(focus.paint, () => {
    props.sites.forEach((site) =>
        updateNode(String(site.id), {
            class: focus.nodeClass(String(site.id)),
        }),
    );
    edges.value = buildEdges();
});

const adding = ref(false);
const wrapper = ref<HTMLElement | null>(null);

// Drawing a tunnel: remember the two ends and open the form preselected.
const presetA = ref<number | null>(null);
const presetB = ref<number | null>(null);

function onConnect({ source, target }: Connection): void {
    if (!source || !target || source === target) {
        return;
    }

    presetA.value = Number(source);
    presetB.value = Number(target);
    adding.value = true;
    stopLinking();
}

// Inline rename/recolour of a site, double-clicked on the canvas.
const styling = ref<MapSite | null>(null);
const styleError = ref<string | null>(null);

function onNodeDblClick({ node }: NodeMouseEvent): void {
    if (!can.manage || drawings.edit(node.id, props.annotations)) {
        return;
    }

    styling.value = props.sites.find((s) => String(s.id) === node.id) ?? null;
    styleError.value = null;
}

function saveStyle(value: { name: string; color: string | null }): void {
    if (!styling.value) {
        return;
    }

    router.patch(styleSite(styling.value.id).url, value, {
        preserveScroll: true,
        onSuccess: () => (styling.value = null),
        onError: (errors) => {
            styleError.value = Object.values(errors)[0] ?? null;
        },
    });
}

function onDragStop({ node }: NodeDragEvent): void {
    // Dragging a multi-selection carries every node in it, so save them together.
    if (tools.selectedCount.value > 1) {
        tools.persistSelected();

        return;
    }

    const drawing = annotationIdOf(node.id);

    if (drawing !== null) {
        saveAnnotationPosition(drawing, node.position.x, node.position.y);

        return;
    }

    router.patch(
        moveSite(Number(node.id)).url,
        {
            map_x: Math.round(node.position.x),
            map_y: Math.round(node.position.y),
        },
        { preserveScroll: true, preserveState: true },
    );
}

// Arrow keys nudge the selection; Ctrl+Z / Ctrl+Y walk the layout history.
// Ignored while typing, so the dialogs keep their normal keyboard behaviour.
function onKeydown(event: KeyboardEvent): void {
    const target = event.target as HTMLElement | null;

    if (
        !can.manage ||
        (target &&
            (target.isContentEditable ||
                ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)))
    ) {
        return;
    }

    const step = event.shiftKey ? GRID : 1;
    const nudges: Record<string, [number, number]> = {
        ArrowLeft: [-step, 0],
        ArrowRight: [step, 0],
        ArrowUp: [0, -step],
        ArrowDown: [0, step],
    };

    if (event.ctrlKey || event.metaKey) {
        const key = event.key.toLowerCase();

        if (key === 'z' && !event.shiftKey) {
            event.preventDefault();
            tools.undo();
        } else if (key === 'y' || (key === 'z' && event.shiftKey)) {
            event.preventDefault();
            tools.redo();
        }

        return;
    }

    const move = nudges[event.key];

    if (move) {
        event.preventDefault();
        tools.nudge(move[0], move[1]);
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

function onNodeClick({ node, event }: NodeMouseEvent): void {
    // Ctrl/Cmd builds a multi-selection to align, Shift drags a rubber band, and
    // while tracing a click picks the site to follow — none of those mean "open
    // this site", so only a plain click drills in.
    const mouse = event as MouseEvent;

    if (
        linking.value ||
        focus.tracing.value ||
        annotationIdOf(node.id) !== null ||
        mouse.ctrlKey ||
        mouse.metaKey ||
        mouse.shiftKey
    ) {
        return;
    }

    router.get(siteMap(Number(node.id)).url);
}

function onEdgeClick(tunnelId: string): void {
    if (can.manage && confirm(t('tunnel.removeConfirm'))) {
        router.delete(removeTunnel(Number(tunnelId)).url, {
            preserveScroll: true,
        });
    }
}

// Exporting frames the whole diagram first, so nothing is cropped out of the
// file, and puts the view back afterwards — the export is not a navigation.
const exporting = ref(false);

async function framed<T>(
    job: (pane: HTMLElement) => Promise<T>,
): Promise<T | null> {
    if (!wrapper.value || exporting.value) {
        return null;
    }

    const before = getViewport();

    exporting.value = true;
    fitView({ padding: 0.15 });
    await new Promise((r) => setTimeout(r, 250));

    try {
        return await job(wrapper.value);
    } finally {
        setViewport(before);
        exporting.value = false;
    }
}

const dark = (): boolean => document.documentElement.classList.contains('dark');

const exportPng = (): Promise<unknown> =>
    framed((pane) => exportMapPng(pane, 'network-map', dark()));

const exportSvg = (): Promise<unknown> =>
    framed((pane) => exportMapSvg(pane, 'network-map', dark()));

// The sheet is built from a picture of the canvas, so the paper gets the whole
// diagram however far the user had zoomed in.
const sheetImage = ref<string | null>(null);

async function printMap(paper: {
    size: PaperSize;
    orientation: PaperOrientation;
}): Promise<void> {
    const image = await framed((pane) => printableImage(pane));

    if (!image) {
        return;
    }

    sheetImage.value = image;
    await nextTick();
    await printSheet(paper.size, paper.orientation);
    sheetImage.value = null;
}
</script>

<template>
    <Head :title="t('map.title')" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader :title="t('map.title')" :description="t('map.globalHint')">
            <template #actions>
                <MapViewBar v-if="mounted" :focus="focus" :groups="groups" />
                <Button
                    size="sm"
                    variant="outline"
                    :title="t('map.routingToggle')"
                    @click="toggleRouting"
                >
                    <component
                        :is="routing === 'orthogonal' ? Waypoints : Spline"
                        class="size-4"
                    />
                    {{ t(`map.routing.${routing}`) }}
                </Button>
                <MapExportMenu
                    :busy="exporting"
                    @png="exportPng"
                    @svg="exportSvg"
                    @print="printMap"
                />
                <Button
                    v-if="can.manage"
                    size="sm"
                    :variant="linking ? 'default' : 'outline'"
                    :title="t('map.linkHint')"
                    @click="toggleLinking"
                >
                    <component
                        :is="linking ? Link2Off : Link2"
                        class="size-4"
                    />
                    {{ linking ? t('map.linkStop') : t('map.linkMode') }}
                </Button>
                <Button v-if="can.manage" size="sm" @click="adding = true">
                    <Plus class="size-4" />
                    {{ t('tunnel.new') }}
                </Button>
            </template>
        </PageHeader>

        <div
            ref="wrapper"
            class="map-canvas relative h-[70vh] min-h-[520px] overflow-hidden rounded-xl border bg-card"
        >
            <MapSelection
                v-if="mounted && selected"
                :title="selected.name"
                :subtitle="selected.code"
                :rows="selectionRows"
                :links="selectionLinks"
            />

            <MapLegend
                v-if="mounted"
                :lines="legendLines"
                :dots="legendDots"
                :zones="annotations.filter((a) => a.type === 'zone')"
            />

            <MapToolbar
                v-if="mounted && can.manage"
                :tools="tools"
                @add="drawings.add"
            />

            <VueFlow
                v-if="mounted"
                :id="FLOW_ID"
                :nodes="nodes"
                :edges="edges"
                :nodes-draggable="can.manage && !linking"
                :nodes-connectable="linking"
                :connection-mode="ConnectionMode.Loose"
                :snap-to-grid="tools.snap.value"
                :snap-grid="tools.snapGrid"
                :multi-selection-key-code="['Control', 'Meta']"
                :selection-key-code="'Shift'"
                :delete-key-code="null"
                :min-zoom="0.2"
                :max-zoom="2.5"
                :zoom-on-double-click="false"
                :nodes-focusable="true"
                :edges-focusable="false"
                @pane-ready="onPaneReady"
                @node-drag-start="tools.record()"
                @node-drag-stop="onDragStop"
                @node-click="onNodeClick"
                @node-double-click="onNodeDblClick"
                @connect="onConnect"
                @edge-click="onEdgeClick($event.edge.id)"
            >
                <template #node-site="siteProps">
                    <SiteNode v-bind="siteProps" />
                </template>
                <template #node-zone="zoneProps">
                    <ZoneNode v-bind="zoneProps" />
                </template>
                <template #node-note="noteProps">
                    <NoteNode v-bind="noteProps" />
                </template>
                <template #edge-floating="edgeProps">
                    <FloatingEdge v-bind="edgeProps" />
                </template>

                <Background :gap="20" pattern-color="rgba(148,163,184,0.4)" />
                <Controls position="bottom-left" :show-interactive="false" />
                <MiniMap
                    pannable
                    zoomable
                    class="hidden md:block"
                    :node-color="() => '#94a3b8'"
                />
            </VueFlow>
        </div>

        <div
            class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground"
        >
            <span>{{ linking ? t('map.linkHint') : t('map.panHint') }}</span>
            <span v-if="!linking">{{ t('map.focusHint') }}</span>
            <span v-if="can.manage && !linking">{{ t('map.toolsHint') }}</span>
            <span v-if="can.manage && !linking">{{
                t('map.annotationHint')
            }}</span>
        </div>
    </div>

    <TunnelFormDialog
        v-model:open="adding"
        :sites="sites"
        :types="types"
        :statuses="statuses"
        :preset-a="presetA"
        :preset-b="presetB"
    />

    <AnnotationDialog
        v-if="drawings.editing.value"
        :open="true"
        :annotation="drawings.editing.value"
        @update:open="(v) => !v && (drawings.editing.value = null)"
        @save="drawings.save"
        @remove="drawings.remove"
    />

    <NodeStyleDialog
        v-if="styling"
        :open="true"
        :title="t('map.editSite')"
        :name="styling.name"
        :color="styling.color"
        :error="styleError"
        @update:open="(v) => !v && (styling = null)"
        @save="saveStyle"
    />

    <Teleport to="body">
        <MapPrintSheet
            v-if="sheetImage"
            :image="sheetImage"
            :title="t('map.title')"
            :author="page.props.auth.user.name"
            :lines="legendLines"
            :dots="legendDots"
            :zones="annotations.filter((a) => a.type === 'zone')"
        />
    </Teleport>
</template>
