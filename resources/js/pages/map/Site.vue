<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Link2, Link2Off, Spline, Waypoints } from '@lucide/vue';
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
import DeviceLinkDialog from '@/components/map/DeviceLinkDialog.vue';
import DeviceNode from '@/components/map/DeviceNode.vue';
import FloatingEdge from '@/components/map/FloatingEdge.vue';
import MapExportMenu from '@/components/map/MapExportMenu.vue';
import MapLegend from '@/components/map/MapLegend.vue';
import MapPrintSheet from '@/components/map/MapPrintSheet.vue';
import MapSelection from '@/components/map/MapSelection.vue';
import MapToolbar from '@/components/map/MapToolbar.vue';
import MapViewBar from '@/components/map/MapViewBar.vue';
import { statusColor } from '@/components/map/nodeMeta';
import NodeStyleDialog from '@/components/map/NodeStyleDialog.vue';
import NoteNode from '@/components/map/NoteNode.vue';
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
import { show as showDevice } from '@/routes/devices';
import { move as moveDevice, style as styleDevice } from '@/routes/map/devices';
import { patch as rackPatch, show as showRack } from '@/routes/racks';
import type { MapAnnotation, MapDevice, MapLink, SiteSummary } from '@/types';

const { t } = useI18n();
const page = usePage();
const { routing, toggle: toggleRouting } = useMapRouting();
const { linking, toggle: toggleLinking, stop: stopLinking } = useMapLinking();

const props = defineProps<{
    site: SiteSummary;
    devices: MapDevice[];
    links: MapLink[];
    annotations: MapAnnotation[];
    can: { arrange: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.map', href: mapIndex() }],
    },
});

const FLOW_ID = 'map-site';
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
const drawings = useMapAnnotations(FLOW_ID, props.site.id);

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

const grid = autoGrid(props.devices, { width: 1200, gap: 230 });

const legendLines = [
    { color: '#94a3b8', label: t('cable.mediaKind.utp') },
    { color: '#0891b2', dashed: true, label: t('cable.mediaKind.fibre') },
];

const legendDots = ['active', 'spare', 'failed', 'decommissioned'].map(
    (status) => ({
        color: statusColor[status],
        label: t(`device.statusKind.${status}`),
    }),
);

// What the view bar searches and filters on. Facets come from the devices that
// are actually here, so a filter can never blank the canvas.
const entities = computed<MapEntity[]>(() =>
    props.devices.map((device) => ({
        id: String(device.id),
        label: device.name,
        sub: [device.model, device.mgmt_ip, device.rack?.name]
            .filter(Boolean)
            .join(' · '),
        facets: {
            kind: [device.kind],
            status: [device.status],
            vlan: device.vlans.map(String),
        },
    })),
);

const focusEdges = computed(() =>
    props.links.map((link) => ({
        id: String(link.id),
        source: String(link.a),
        target: String(link.b),
    })),
);

const focus = useMapFocus(FLOW_ID, entities, focusEdges);

const groups = computed<FacetGroup[]>(() =>
    [
        facetGroup(entities.value, 'kind', t('common.type'), (value) =>
            t(`model.kind.${value}`),
        ),
        facetGroup(entities.value, 'status', t('common.status'), (value) =>
            t(`device.statusKind.${value}`),
        ),
        facetGroup(entities.value, 'vlan', t('nav.vlans'), (value) =>
            t('map.vlanTag', { vid: value }),
        ),
    ].filter((group): group is FacetGroup => group !== null),
);

// Drawings come first so that, at equal z, the kit still paints over them.
const buildNodes = (): Node[] => [
    ...annotationNodes(props.annotations, props.can.arrange),
    ...props.devices.map((device) => ({
        id: String(device.id),
        type: 'device',
        position: grid(device),
        class: focus.nodeClass(String(device.id)),
        ariaLabel: `${device.name}, ${device.model}, ${t(
            `device.statusKind.${device.status}`,
        )}`,
        data: {
            name: device.name,
            kind: device.kind,
            model: device.model,
            status: device.status,
            ports_count: device.ports_count,
            mgmt_ip: device.mgmt_ip,
            color: device.color,
        },
    })),
];

const buildEdges = (): Edge[] =>
    props.links.map((link) => ({
        id: String(link.id),
        source: String(link.a),
        target: String(link.b),
        type: 'floating',
        class: focus.edgeClass({
            id: String(link.id),
            source: String(link.a),
            target: String(link.b),
        }),
        data: {
            color: link.media === 'fibre' ? '#0891b2' : '#94a3b8',
            dashed: link.media === 'fibre',
            label:
                link.media === 'fibre'
                    ? t('cable.strandCount', { count: link.strands ?? 1 })
                    : null,
        },
    }));

const nodes = ref<Node[]>(buildNodes());
const edges = ref<Edge[]>(buildEdges());

// A new cable or a rename changes props; reseed so the graph mirrors them.
watch(
    [() => props.devices, () => props.annotations],
    () => (nodes.value = buildNodes()),
    { deep: true },
);
watch(
    () => props.links,
    () => (edges.value = buildEdges()),
    { deep: true },
);

// Narrowing only repaints: node classes go through updateNode so a drag that
// has not been saved yet is not snapped back to whatever the props still say.
watch(focus.paint, () => {
    props.devices.forEach((device) =>
        updateNode(String(device.id), {
            class: focus.nodeClass(String(device.id)),
        }),
    );
    edges.value = buildEdges();
});

// One selected device gets a panel: what it is, and the way through to its
// rack and the patch panel — the map is an entry point, not a dead end.
const selected = computed<MapDevice | null>(() => {
    const picked = getSelectedNodes.value;

    return picked.length === 1
        ? (props.devices.find((d) => String(d.id) === picked[0].id) ?? null)
        : null;
});

const selectionRows = computed(() => {
    const device = selected.value;

    if (!device) {
        return [];
    }

    return [
        {
            label: t('common.status'),
            value: t(`device.statusKind.${device.status}`),
        },
        { label: t('map.ports'), value: String(device.ports_count) },
        ...(device.mgmt_ip
            ? [{ label: t('device.mgmtIp'), value: device.mgmt_ip }]
            : []),
        ...(device.rack
            ? [{ label: t('map.rack'), value: device.rack.name }]
            : []),
        ...(device.vlans.length
            ? [{ label: t('nav.vlans'), value: device.vlans.join(', ') }]
            : []),
    ];
});

const selectionLinks = computed(() => {
    const device = selected.value;

    if (!device) {
        return [];
    }

    return [
        { label: t('map.openDevice'), href: showDevice(device.id).url },
        ...(device.rack
            ? [
                  {
                      label: t('map.openRack'),
                      href: showRack(device.rack.id).url,
                  },
                  {
                      label: t('map.openPatch'),
                      href: rackPatch(device.rack.id).url,
                  },
              ]
            : []),
    ];
});

const wrapper = ref<HTMLElement | null>(null);

// Drawing a cable: keep the two devices, open the port picker between them.
const linkFrom = ref<{ id: number; name: string } | null>(null);
const linkTo = ref<{ id: number; name: string } | null>(null);
const linkOpen = ref(false);

const deviceById = (id: string): { id: number; name: string } | null => {
    const device = props.devices.find((d) => String(d.id) === id);

    return device ? { id: device.id, name: device.name } : null;
};

function onConnect({ source, target }: Connection): void {
    if (!source || !target || source === target) {
        return;
    }

    linkFrom.value = deviceById(source);
    linkTo.value = deviceById(target);

    if (linkFrom.value && linkTo.value) {
        linkOpen.value = true;
        stopLinking();
    }
}

// Inline rename/recolour of a device, double-clicked on the canvas.
const styling = ref<MapDevice | null>(null);
const styleError = ref<string | null>(null);

function onNodeDblClick({ node }: NodeMouseEvent): void {
    if (!props.can.arrange || drawings.edit(node.id, props.annotations)) {
        return;
    }

    styling.value = props.devices.find((d) => String(d.id) === node.id) ?? null;
    styleError.value = null;
}

function saveStyle(value: { name: string; color: string | null }): void {
    if (!styling.value) {
        return;
    }

    router.patch(styleDevice(styling.value.id).url, value, {
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
        moveDevice(Number(node.id)).url,
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
        !props.can.arrange ||
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
    framed((pane) => exportMapPng(pane, `site-${props.site.code}`, dark()));

const exportSvg = (): Promise<unknown> =>
    framed((pane) => exportMapSvg(pane, `site-${props.site.code}`, dark()));

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
    <Head :title="`${site.name} — ${t('map.title')}`" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            :title="`${site.name} — ${t('map.title')}`"
            :description="t('map.siteHint')"
        >
            <template #actions>
                <MapViewBar
                    v-if="mounted && devices.length"
                    :focus="focus"
                    :groups="groups"
                />
                <Link :href="mapIndex().url">
                    <Button size="sm" variant="outline">
                        <ArrowLeft class="size-4" />
                        {{ t('map.backToGlobal') }}
                    </Button>
                </Link>
                <Button
                    v-if="devices.length"
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
                    v-if="devices.length"
                    :busy="exporting"
                    @png="exportPng"
                    @svg="exportSvg"
                    @print="printMap"
                />
                <Button
                    v-if="devices.length && can.arrange"
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
            </template>
        </PageHeader>

        <p
            v-if="!devices.length"
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            {{ t('map.noDevices') }}
        </p>

        <div
            v-else
            ref="wrapper"
            class="map-canvas relative h-[70vh] min-h-[520px] overflow-hidden rounded-xl border bg-card"
        >
            <MapSelection
                v-if="mounted && selected"
                :title="selected.name"
                :subtitle="selected.model"
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
                v-if="mounted && can.arrange"
                :tools="tools"
                @add="drawings.add"
            />

            <VueFlow
                v-if="mounted"
                :id="FLOW_ID"
                :nodes="nodes"
                :edges="edges"
                :nodes-draggable="can.arrange && !linking"
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
                @node-double-click="onNodeDblClick"
                @connect="onConnect"
            >
                <template #node-device="deviceProps">
                    <DeviceNode v-bind="deviceProps" />
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
            v-if="devices.length"
            class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground"
        >
            <span>{{ linking ? t('map.linkHint') : t('map.panHint') }}</span>
            <span v-if="!linking">{{ t('map.focusHint') }}</span>
            <span v-if="can.arrange && !linking">{{ t('map.toolsHint') }}</span>
            <span v-if="can.arrange && !linking">{{
                t('map.annotationHint')
            }}</span>
        </div>
    </div>

    <DeviceLinkDialog
        v-model:open="linkOpen"
        :from="linkFrom"
        :to="linkTo"
        :existing-count="links.length"
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
        :title="t('map.editDevice')"
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
            :title="`${site.name} — ${t('map.title')}`"
            :subtitle="site.code"
            :author="page.props.auth.user.name"
            :lines="legendLines"
            :dots="legendDots"
            :zones="annotations.filter((a) => a.type === 'zone')"
        />
    </Teleport>
</template>
