<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRackScale } from '@/composables/useRackScale';
import type { RackDevice } from '@/types';

const { t } = useI18n();
const { scale, rackScales } = useRackScale();

const props = defineProps<{
    uHeight: number;
    devices: RackDevice[];
    face: string;
    editable: boolean;
}>();

const emit = defineEmits<{
    select: [device: RackDevice];
    move: [device: RackDevice, position: number];
    addAt: [position: number];
}>();

const metrics = computed(() => rackScales[scale.value]);

/** Units are numbered from the bottom, but a rack is drawn from the top. */
const units = computed(() =>
    Array.from({ length: props.uHeight }, (_, index) => props.uHeight - index),
);

const mounted = computed(() =>
    props.devices.filter((device) => device.face === props.face),
);

const occupied = computed(() => {
    const map = new Map<number, RackDevice>();

    for (const device of mounted.value) {
        for (let i = 0; i < device.model.u_height; i++) {
            map.set(device.position_u + i, device);
        }
    }

    return map;
});

/**
 * The default colour says what a device is; an explicit colour on the device
 * overrides it, for picking one out of a row of identical switches.
 */
const kindColors: Record<string, string> = {
    switch: '#0284c7',
    patch_panel: '#d97706',
    router: '#7c3aed',
    firewall: '#e11d48',
    server: '#059669',
    ups: '#64748b',
    other: '#737373',
};

const colorOf = (device: RackDevice): string =>
    device.color ?? kindColors[device.model.kind] ?? kindColors.other;

const dragging = ref<RackDevice | null>(null);
const dropTarget = ref<number | null>(null);

function startDrag(device: RackDevice, event: DragEvent): void {
    if (!props.editable) {
        return;
    }

    dragging.value = device;
    event.dataTransfer?.setData('text/plain', String(device.id));
}

function dropOn(unit: number): void {
    const device = dragging.value;

    dragging.value = null;
    dropTarget.value = null;

    if (device && device.position_u !== unit) {
        emit('move', device, unit);
    }
}
</script>

<template>
    <div class="inline-flex flex-col rounded-xl border bg-muted/30 p-3">
        <p class="mb-2 text-center text-sm font-medium">
            {{ t(`rack.face.${face}`) }}
            <span class="font-normal text-muted-foreground">
                · {{ uHeight }}U
            </span>
        </p>

        <div class="flex">
            <!-- Unit numbers down the side, as they are labelled on a rack. -->
            <div class="mr-1.5 flex flex-col">
                <div
                    v-for="unit in units"
                    :key="unit"
                    class="flex w-8 items-center justify-end pr-1.5 font-mono text-muted-foreground tabular-nums"
                    :class="metrics.label"
                    :style="{ height: `${metrics.unit}px` }"
                >
                    {{ unit }}
                </div>
            </div>

            <div
                class="relative rounded-md border bg-background"
                :style="{ width: `${metrics.width}px` }"
            >
                <template v-for="unit in units" :key="unit">
                    <!-- A device is drawn once, at its bottom unit. -->
                    <div
                        v-if="
                            occupied.get(unit) &&
                            occupied.get(unit)!.position_u === unit
                        "
                        :draggable="editable"
                        class="absolute inset-x-0 flex cursor-pointer items-center gap-2 overflow-hidden rounded border-l-4 bg-card px-3 shadow-xs transition-shadow hover:shadow-md"
                        :style="{
                            bottom: `${(unit - 1) * metrics.unit}px`,
                            height: `${occupied.get(unit)!.model.u_height * metrics.unit - 3}px`,
                            borderLeftColor: colorOf(occupied.get(unit)!),
                            backgroundColor: `color-mix(in srgb, ${colorOf(occupied.get(unit)!)} 12%, var(--card))`,
                        }"
                        :title="`${occupied.get(unit)!.name} — ${occupied.get(unit)!.model.vendor} ${occupied.get(unit)!.model.model}`"
                        @click="emit('select', occupied.get(unit)!)"
                        @dragstart="startDrag(occupied.get(unit)!, $event)"
                    >
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate leading-tight font-semibold"
                                :class="metrics.name"
                            >
                                {{ occupied.get(unit)!.name }}
                            </p>
                            <p
                                class="truncate leading-tight text-muted-foreground"
                                :class="metrics.detail"
                            >
                                {{ occupied.get(unit)!.model.model }}
                            </p>
                        </div>

                        <span
                            v-if="occupied.get(unit)!.mgmt_ip"
                            class="shrink-0 font-mono text-muted-foreground tabular-nums"
                            :class="metrics.detail"
                        >
                            {{ occupied.get(unit)!.mgmt_ip }}
                        </span>
                    </div>

                    <!-- An empty unit: a drop target, and a shortcut to add. -->
                    <div
                        v-else-if="!occupied.get(unit)"
                        class="absolute inset-x-0 flex items-center justify-center border-b border-dashed border-border/60 transition-colors"
                        :class="{
                            'bg-primary/10': dropTarget === unit,
                            'group cursor-pointer hover:bg-accent/60': editable,
                        }"
                        :style="{
                            bottom: `${(unit - 1) * metrics.unit}px`,
                            height: `${metrics.unit}px`,
                        }"
                        @dragover.prevent="dropTarget = unit"
                        @dragleave="dropTarget === unit && (dropTarget = null)"
                        @drop.prevent="dropOn(unit)"
                        @click="editable && emit('addAt', unit)"
                    >
                        <span
                            v-if="editable"
                            class="text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
                            :class="metrics.detail"
                        >
                            {{ t('rack.addHere') }}
                        </span>
                    </div>
                </template>

                <div :style="{ height: `${uHeight * metrics.unit}px` }" />
            </div>
        </div>
    </div>
</template>
