<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ImageDown, Plus } from '@lucide/vue';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import NewUplinkDialog from '@/components/patch/NewUplinkDialog.vue';
import PortStrip from '@/components/patch/PortStrip.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { usePatchCoords } from '@/composables/usePatchCoords';
import type { Point } from '@/composables/usePatchCoords';
import { buildRackSvg } from '@/composables/usePatchSvg';
import { exportSvgToPng } from '@/composables/useSvgExport';
import {
    appearance as editCable,
    destroy as removeCable,
    store as createCable,
} from '@/routes/cables';
import type { Cable, PatchPort, RackPatch } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    rackId: number;
    rackName: string;
}>();

const container = ref<HTMLElement | null>(null);
const { ports, chips, size, remeasure } = usePatchCoords(container);

const data = ref<RackPatch | null>(null);
const loading = ref(true);
const selected = ref<number | null>(null);
const error = ref<string | null>(null);

async function load(): Promise<void> {
    const response = await fetch(`/racks/${props.rackId}/patch`, {
        headers: { Accept: 'application/json' },
    });

    data.value = (await response.json()) as RackPatch;
    loading.value = false;
    await remeasure();
}

onMounted(load);

const canWire = computed(() => data.value?.can.wire ?? false);

/** Every port keyed by id, for pairing cords and knowing what is free. */
const portIndex = computed<Record<number, PatchPort>>(() => {
    const index: Record<number, PatchPort> = {};

    for (const device of data.value?.devices ?? []) {
        for (const port of device.ports) {
            index[port.id] = port;
        }
    }

    return index;
});

const isFree = (id: number): boolean =>
    !!portIndex.value[id] && !portIndex.value[id].link;

const mediaColor: Record<string, string> = {
    utp: '#94a3b8',
    fibre: '#0891b2',
};

const cordColor = (cable: Pick<Cable, 'color' | 'media'>): string =>
    cable.color ?? mediaColor[cable.media] ?? '#94a3b8';

/** Colours cycle so a fresh cord never blends into its neighbour. */
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

type Cord = {
    id: number;
    a: Point;
    b: Point;
    color: string;
    fibre: boolean;
};

const cords = computed<Cord[]>(() => {
    if (!data.value) {
        return [];
    }

    const placed = ports.value;
    const result: Cord[] = [];
    const seen = new Set<number>();

    for (const device of data.value.devices) {
        for (const port of device.ports) {
            const link = port.link;

            if (!link || seen.has(link.cable.id)) {
                continue;
            }

            const far = link.far;

            if (far?.kind === 'port' && portIndex.value[far.id]) {
                const a = placed[port.id];
                const b = placed[far.id];

                if (a && b) {
                    result.push({
                        id: link.cable.id,
                        a,
                        b,
                        color: cordColor(link.cable),
                        fibre: link.cable.media === 'fibre',
                    });
                    seen.add(link.cable.id);
                }
            }
        }
    }

    for (const ext of data.value.externals) {
        const a = placed[ext.near_port_id];
        const b = chips.value[ext.cable_id];

        if (a && b) {
            result.push({
                id: ext.cable_id,
                a,
                b,
                color: ext.color ?? mediaColor[ext.media] ?? '#94a3b8',
                fibre: ext.media === 'fibre',
            });
        }
    }

    return result;
});

/** The two sockets a selected cable plugs into, lit together. */
const litPorts = computed<number[]>(() => {
    if (selected.value === null || !data.value) {
        return [];
    }

    const ids: number[] = [];

    for (const device of data.value.devices) {
        for (const port of device.ports) {
            if (port.link?.cable.id === selected.value) {
                ids.push(port.id);

                if (port.link.far?.kind === 'port') {
                    ids.push(port.link.far.id);
                }
            }
        }
    }

    return [...new Set(ids)];
});

/** The selected cable as the editor needs it — from a strip or the rail alike. */
const current = computed<{
    id: number;
    label: string | null;
    length_cm: number | null;
    color: string | null;
    status: string;
} | null>(() => {
    if (selected.value === null || !data.value) {
        return null;
    }

    for (const device of data.value.devices) {
        for (const port of device.ports) {
            if (port.link?.cable.id === selected.value) {
                return port.link.cable;
            }
        }
    }

    const ext = data.value.externals.find((e) => e.cable_id === selected.value);

    return ext
        ? {
              id: ext.cable_id,
              label: ext.label,
              length_cm: ext.length_cm,
              color: ext.color,
              status: ext.status,
          }
        : null;
});

const editForm = reactive({
    label: '',
    length_cm: '' as number | string,
    color: null as string | null,
    status: 'connected',
});

watch(current, (cable) => {
    if (cable) {
        editForm.label = cable.label ?? '';
        editForm.length_cm = cable.length_cm ?? '';
        editForm.color = cable.color;
        editForm.status = cable.status;
    }
});

// ---- Cabling by hand -------------------------------------------------------

const dragFrom = ref<number | null>(null);
const cursor = ref<Point | null>(null);
const dropCandidate = ref<number | null>(null);
const downPort = ref<number | null>(null);
const moved = ref(false);

// Dropping a dragged port on the uplink rail opens the far-end picker.
const uplinkOpen = ref(false);
const uplinkFrom = ref<number | null>(null);
const railHot = ref(false);

const uplinkFromLabel = computed<string>(() => {
    if (uplinkFrom.value === null || !data.value) {
        return '';
    }

    for (const device of data.value.devices) {
        const port = device.ports.find((p) => p.id === uplinkFrom.value);

        if (port) {
            return `${device.name} · ${port.name}`;
        }
    }

    return '';
});

function pointFrom(event: PointerEvent): Point {
    const base = container.value!.getBoundingClientRect();

    return { x: event.clientX - base.left, y: event.clientY - base.top };
}

function portAt(event: PointerEvent): number | null {
    const el = document.elementFromPoint(event.clientX, event.clientY);
    const button = (el as HTMLElement | null)?.closest('[data-pid]');

    return button ? Number((button as HTMLElement).dataset.pid) : null;
}

const onRail = (event: PointerEvent): boolean =>
    !!(
        document.elementFromPoint(
            event.clientX,
            event.clientY,
        ) as HTMLElement | null
    )?.closest('[data-ext]');

/** Over the "new uplink" drop zone — where a dragged port becomes an uplink. */
const onNewExt = (event: PointerEvent): boolean =>
    !!(
        document.elementFromPoint(
            event.clientX,
            event.clientY,
        ) as HTMLElement | null
    )?.closest('[data-newext]');

function onDown(event: PointerEvent): void {
    const pid = portAt(event);
    downPort.value = pid;
    moved.value = false;

    if (canWire.value && pid !== null && isFree(pid)) {
        dragFrom.value = pid;
        cursor.value = pointFrom(event);
        container.value?.setPointerCapture(event.pointerId);
    }
}

function onMove(event: PointerEvent): void {
    if (dragFrom.value === null) {
        return;
    }

    moved.value = true;
    cursor.value = pointFrom(event);

    const pid = portAt(event);
    dropCandidate.value =
        pid !== null && pid !== dragFrom.value && isFree(pid) ? pid : null;
    railHot.value = dropCandidate.value === null && onNewExt(event);
}

function onUp(event: PointerEvent): void {
    const target = portAt(event);

    if (dragFrom.value !== null && moved.value) {
        if (target !== null && target !== dragFrom.value && isFree(target)) {
            connect(dragFrom.value, target);
        } else if (onNewExt(event)) {
            // Dropped on the rail — pick the far end for a cross-rack uplink.
            uplinkFrom.value = dragFrom.value;
            uplinkOpen.value = true;
        }
    } else if (!onRail(event) && !onNewExt(event)) {
        // A plain click selects the cable in a port, or clears the selection.
        selected.value =
            target !== null
                ? (portIndex.value[target]?.link?.cable.id ?? null)
                : null;
    }

    dragFrom.value = null;
    cursor.value = null;
    dropCandidate.value = null;
    downPort.value = null;
    moved.value = false;
    railHot.value = false;
}

/** Copper stays copper; the moment either end is fibre, so is the cord. */
function inferMedia(a: PatchPort, b: PatchPort): 'utp' | 'fibre' {
    return a.media === 'rj45' && b.media === 'rj45' ? 'utp' : 'fibre';
}

function connect(fromId: number, toId: number): void {
    const a = portIndex.value[fromId];
    const b = portIndex.value[toId];

    if (!a || !b) {
        return;
    }

    const media = inferMedia(a, b);
    error.value = null;

    router.post(
        createCable().url,
        {
            a_type: 'port',
            a_id: fromId,
            b_type: 'port',
            b_id: toId,
            media,
            strands: media === 'fibre' ? 2 : null,
            color: palette[cords.value.length % palette.length],
            status: 'connected',
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => load(),
            onError: (errors) => {
                error.value = Object.values(errors)[0] ?? null;
            },
        },
    );
}

/**
 * Lay a cable from a local port to a far end in another rack or a workplace —
 * the picker resolved which. Posts the same way as an in-rack cord, so the
 * commutation mode is preserved and the new uplink is picked up by a refetch.
 */
function connectExternal(end: {
    bType: 'port' | 'outlet';
    bId: number;
    media: string;
}): void {
    const near = uplinkFrom.value ? portIndex.value[uplinkFrom.value] : null;

    if (!near) {
        return;
    }

    const media =
        near.media === 'rj45' && end.media === 'rj45' ? 'utp' : 'fibre';
    error.value = null;

    router.post(
        createCable().url,
        {
            a_type: 'port',
            a_id: uplinkFrom.value,
            b_type: end.bType,
            b_id: end.bId,
            media,
            strands: media === 'fibre' ? 2 : null,
            color: palette[cords.value.length % palette.length],
            status: 'connected',
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => load(),
            onError: (errors) => {
                error.value = Object.values(errors)[0] ?? null;
            },
        },
    );
}

function saveEdit(): void {
    if (!current.value) {
        return;
    }

    router.patch(
        editCable(current.value.id).url,
        {
            label: editForm.label || null,
            length_cm: editForm.length_cm ? Number(editForm.length_cm) : null,
            color: editForm.color,
            status: editForm.status,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => load(),
        },
    );
}

function disconnect(): void {
    if (current.value === null || !confirm(t('cable.disconnectConfirm'))) {
        return;
    }

    router.delete(removeCable(current.value.id).url, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            selected.value = null;
            load();
        },
    });
}

function cordPath(a: Point, b: Point): string {
    const dy = Math.max(20, Math.min(120, Math.abs(b.y - a.y) * 0.4));

    return `M ${a.x} ${a.y} C ${a.x} ${a.y + dy}, ${b.x} ${b.y - dy}, ${b.x} ${b.y}`;
}

/** Save the whole rack's commutation as a PNG built from the data, not the DOM. */
function exportPng(): void {
    if (!data.value) {
        return;
    }

    const svg = buildRackSvg(data.value, props.rackName);
    exportSvgToPng(svg, `${props.rackName}-commutation`);
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="loading" class="flex justify-center py-16">
            <Spinner />
        </div>

        <template v-else-if="data">
            <div v-if="data.devices.length" class="flex justify-end">
                <Button size="sm" variant="outline" @click="exportPng">
                    <ImageDown class="size-4" />
                    {{ t('patch.exportPng') }}
                </Button>
            </div>

            <!-- The uplink rail: cables arriving from other racks or workplaces. -->
            <div
                v-if="data.externals.length || canWire"
                class="rounded-xl border border-dashed bg-muted/30 p-3 transition-colors"
                :class="railHot ? 'border-primary bg-primary/5' : ''"
            >
                <p class="mb-2 text-xs font-medium text-muted-foreground">
                    {{ t('patch.uplinks') }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="ext in data.externals"
                        :key="ext.cable_id"
                        :data-ext="ext.cable_id"
                        type="button"
                        class="flex items-center gap-2 rounded-lg border px-3 py-1.5 text-left transition-colors hover:border-primary/50"
                        :class="
                            selected === ext.cable_id
                                ? 'border-primary ring-2 ring-primary ring-offset-1'
                                : ''
                        "
                        @click="
                            selected =
                                selected === ext.cable_id ? null : ext.cable_id
                        "
                    >
                        <span
                            class="size-3 shrink-0 rounded-full"
                            :style="{
                                backgroundColor:
                                    ext.color ?? mediaColor[ext.media],
                            }"
                        />
                        <span class="min-w-0">
                            <span class="block truncate text-xs font-medium">
                                {{ ext.far.label }}
                            </span>
                            <span
                                v-if="ext.far.sub"
                                class="block truncate text-[11px] text-muted-foreground"
                            >
                                {{ ext.far.sub }}
                            </span>
                        </span>
                    </button>

                    <!-- Drop a dragged port here to run a new cross-rack uplink. -->
                    <button
                        v-if="canWire"
                        data-newext
                        type="button"
                        class="flex items-center gap-1.5 rounded-lg border border-dashed px-3 py-1.5 text-xs text-muted-foreground transition-colors hover:border-primary/50 hover:text-foreground"
                        :class="railHot ? 'border-primary text-foreground' : ''"
                        @click="selected = null"
                    >
                        <Plus class="size-3.5" />
                        {{ t('patch.newUplink') }}
                    </button>
                </div>
            </div>

            <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

            <p
                v-if="!data.devices.length"
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                {{ t('patch.empty') }}
            </p>

            <!-- Strips and the cord overlay share one positioned container. -->
            <div
                v-else
                ref="container"
                class="relative touch-none select-none"
                @pointerdown="onDown"
                @pointermove="onMove"
                @pointerup="onUp"
            >
                <svg
                    class="pointer-events-none absolute inset-0"
                    :width="size.width"
                    :height="size.height"
                >
                    <path
                        v-for="cord in cords"
                        :key="cord.id"
                        :d="cordPath(cord.a, cord.b)"
                        fill="none"
                        :stroke="cord.color"
                        :stroke-width="selected === cord.id ? 4 : 2.25"
                        :stroke-dasharray="cord.fibre ? '7 4' : '0'"
                        stroke-linecap="round"
                        :opacity="
                            selected === null || selected === cord.id ? 1 : 0.2
                        "
                    />
                    <!-- The cord being dragged, trailing the cursor. -->
                    <path
                        v-if="dragFrom !== null && cursor && ports[dragFrom]"
                        :d="cordPath(ports[dragFrom], cursor)"
                        fill="none"
                        stroke="#0284c7"
                        stroke-width="2.5"
                        stroke-dasharray="4 4"
                        stroke-linecap="round"
                    />
                </svg>

                <div class="relative flex flex-col gap-3">
                    <PortStrip
                        v-for="device in data.devices"
                        :key="device.id"
                        :device="device"
                        :highlight="litPorts"
                        :drag-active="dragFrom !== null"
                        :drop-candidate="dropCandidate"
                        :dimmed="
                            selected !== null &&
                            !device.ports.some((p) => litPorts.includes(p.id))
                        "
                    />
                </div>
            </div>

            <!-- Editor for the selected cord, or the legend when nothing is picked. -->
            <div
                v-if="current"
                class="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-3"
            >
                <label class="grid gap-1 text-xs">
                    <span class="text-muted-foreground">{{
                        t('cable.label')
                    }}</span>
                    <Input
                        v-model="editForm.label"
                        class="h-8 w-32 font-mono"
                        :disabled="!canWire"
                    />
                </label>
                <label class="grid gap-1 text-xs">
                    <span class="text-muted-foreground">{{
                        t('cable.lengthCm')
                    }}</span>
                    <Input
                        v-model="editForm.length_cm"
                        type="number"
                        min="1"
                        class="h-8 w-24"
                        :disabled="!canWire"
                    />
                </label>
                <label class="grid gap-1 text-xs">
                    <span class="text-muted-foreground">{{
                        t('common.status')
                    }}</span>
                    <select
                        v-model="editForm.status"
                        class="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                        :disabled="!canWire"
                    >
                        <option
                            v-for="value in data.statuses"
                            :key="value"
                            :value="value"
                        >
                            {{ t(`cable.statusKind.${value}`) }}
                        </option>
                    </select>
                </label>
                <div class="grid gap-1 text-xs">
                    <span class="text-muted-foreground">{{
                        t('cable.color')
                    }}</span>
                    <div class="flex flex-wrap items-center gap-1">
                        <button
                            v-for="color in palette"
                            :key="color"
                            type="button"
                            class="size-6 rounded-md border"
                            :style="{ backgroundColor: color }"
                            :class="
                                editForm.color === color
                                    ? 'ring-2 ring-primary ring-offset-1'
                                    : ''
                            "
                            :disabled="!canWire"
                            @click="editForm.color = color"
                        />
                    </div>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <Button
                        v-if="canWire"
                        size="sm"
                        variant="outline"
                        class="text-destructive"
                        @click="disconnect"
                    >
                        {{ t('cable.disconnect') }}
                    </Button>
                    <Button v-if="canWire" size="sm" @click="saveEdit">
                        {{ t('common.save') }}
                    </Button>
                    <Button size="sm" variant="ghost" @click="selected = null">
                        {{ t('patch.clear') }}
                    </Button>
                </div>
            </div>

            <div
                v-else
                class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-muted-foreground"
            >
                <span class="flex items-center gap-2">
                    <svg width="30" height="8">
                        <line
                            x1="0"
                            y1="4"
                            x2="30"
                            y2="4"
                            stroke="#94a3b8"
                            stroke-width="2.25"
                        />
                    </svg>
                    {{ t('cable.mediaKind.utp') }}
                </span>
                <span class="flex items-center gap-2">
                    <svg width="30" height="8">
                        <line
                            x1="0"
                            y1="4"
                            x2="30"
                            y2="4"
                            stroke="#0891b2"
                            stroke-width="2.25"
                            stroke-dasharray="6 4"
                        />
                    </svg>
                    {{ t('cable.mediaKind.fibre') }}
                </span>
                <span v-if="canWire">{{ t('patch.dragHint') }}</span>
                <span v-else>{{ t('patch.selectHint') }}</span>
            </div>

            <NewUplinkDialog
                v-if="uplinkOpen"
                v-model:open="uplinkOpen"
                :from-label="uplinkFromLabel"
                :sites="data.sites"
                :default-site-id="data.site_id"
                @select="connectExternal"
            />
        </template>
    </div>
</template>
