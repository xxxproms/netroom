<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { RackDevice } from '@/types';

const { t } = useI18n();

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

const UNIT_HEIGHT = 26;

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

const kindColors: Record<string, string> = {
    switch: 'bg-sky-500/15 border-sky-500/40 text-sky-950 dark:text-sky-100',
    patch_panel:
        'bg-amber-500/15 border-amber-500/40 text-amber-950 dark:text-amber-100',
    router: 'bg-violet-500/15 border-violet-500/40 text-violet-950 dark:text-violet-100',
    firewall:
        'bg-rose-500/15 border-rose-500/40 text-rose-950 dark:text-rose-100',
    server: 'bg-emerald-500/15 border-emerald-500/40 text-emerald-950 dark:text-emerald-100',
    ups: 'bg-slate-500/15 border-slate-500/40 text-slate-950 dark:text-slate-100',
    other: 'bg-neutral-500/15 border-neutral-500/40',
};

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
        <div class="flex">
            <!-- Unit numbers down the side, as they are labelled on a rack. -->
            <div class="mr-1.5 flex flex-col">
                <div
                    v-for="unit in units"
                    :key="unit"
                    class="flex w-6 items-center justify-end pr-1 font-mono text-[10px] text-muted-foreground"
                    :style="{ height: `${UNIT_HEIGHT}px` }"
                >
                    {{ unit }}
                </div>
            </div>

            <div class="relative w-64 rounded-md border bg-background">
                <template v-for="unit in units" :key="unit">
                    <!-- A device is drawn once, at its bottom unit. -->
                    <div
                        v-if="
                            occupied.get(unit) &&
                            occupied.get(unit)!.position_u === unit
                        "
                        :draggable="editable"
                        class="absolute inset-x-0 flex cursor-pointer flex-col justify-center overflow-hidden rounded border px-2"
                        :class="
                            kindColors[occupied.get(unit)!.model.kind] ??
                            kindColors.other
                        "
                        :style="{
                            bottom: `${(unit - 1) * UNIT_HEIGHT}px`,
                            height: `${occupied.get(unit)!.model.u_height * UNIT_HEIGHT - 2}px`,
                        }"
                        @click="emit('select', occupied.get(unit)!)"
                        @dragstart="startDrag(occupied.get(unit)!, $event)"
                    >
                        <span class="truncate text-xs font-medium">
                            {{ occupied.get(unit)!.name }}
                        </span>
                        <span class="truncate text-[10px] opacity-70">
                            {{ occupied.get(unit)!.model.model }}
                        </span>
                    </div>

                    <!-- An empty unit: a drop target, and a shortcut to add. -->
                    <div
                        v-else-if="!occupied.get(unit)"
                        class="absolute inset-x-0 border-b border-dashed border-border/60 transition-colors"
                        :class="{
                            'bg-primary/10': dropTarget === unit,
                            'cursor-pointer hover:bg-accent/60': editable,
                        }"
                        :style="{
                            bottom: `${(unit - 1) * UNIT_HEIGHT}px`,
                            height: `${UNIT_HEIGHT}px`,
                        }"
                        @dragover.prevent="dropTarget = unit"
                        @dragleave="dropTarget === unit && (dropTarget = null)"
                        @drop.prevent="dropOn(unit)"
                        @click="editable && emit('addAt', unit)"
                    />
                </template>

                <div :style="{ height: `${uHeight * UNIT_HEIGHT}px` }" />
            </div>
        </div>

        <p class="mt-2 text-center text-[11px] text-muted-foreground">
            {{ t(`rack.face.${face}`) }} · {{ uHeight }}U
        </p>
    </div>
</template>
