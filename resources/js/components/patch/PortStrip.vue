<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PatchDevice, PatchPort } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    device: PatchDevice;
    highlight: number[];
    dimmed: boolean;
    dragActive: boolean;
    dropCandidate: number | null;
}>();

const kindColors: Record<string, string> = {
    switch: '#0284c7',
    patch_panel: '#d97706',
    router: '#7c3aed',
    firewall: '#e11d48',
    server: '#059669',
    ups: '#64748b',
    other: '#737373',
};

const accent = computed(
    () =>
        props.device.color ?? kindColors[props.device.kind] ?? kindColors.other,
);

/** Ports read more clearly split by role, as a patch panel's front and rear. */
const groups = computed(() => {
    const roles = [...new Set(props.device.ports.map((port) => port.role))];

    return roles.map((role) => ({
        role,
        ports: props.device.ports.filter((port) => port.role === role),
    }));
});

/** Copper cords are grey, fibre cyan — unless the cable was given a colour. */
const mediaColor: Record<string, string> = {
    utp: '#94a3b8',
    fibre: '#0891b2',
};

function colorOf(port: PatchPort): string | null {
    if (!port.link) {
        return null;
    }

    return (
        port.link.cable.color ?? mediaColor[port.link.cable.media] ?? '#94a3b8'
    );
}

const isFree = (port: PatchPort): boolean => !port.link;
</script>

<template>
    <div
        class="rounded-xl border bg-card p-3 transition-opacity"
        :class="{ 'opacity-40': dimmed }"
        :style="{ borderLeftColor: accent, borderLeftWidth: '4px' }"
    >
        <div class="mb-2 flex items-baseline gap-2">
            <span class="text-sm font-semibold">{{ device.name }}</span>
            <span class="text-xs text-muted-foreground">
                {{ t(`model.kind.${device.kind}`) }}
                <template v-if="device.position_u">
                    · {{ device.position_u }}U
                </template>
            </span>
        </div>

        <div v-for="group in groups" :key="group.role" class="mb-1.5 last:mb-0">
            <span
                v-if="groups.length > 1"
                class="mb-1 block text-[11px] text-muted-foreground"
            >
                {{ t(`model.roleKind.${group.role}`) }}
            </span>
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="port in group.ports"
                    :key="port.id"
                    :data-pid="port.id"
                    type="button"
                    class="relative flex size-7 touch-none items-center justify-center rounded-md border font-mono text-[10px] tabular-nums transition-all"
                    :class="[
                        port.enabled ? '' : 'opacity-40',
                        highlight.includes(port.id)
                            ? 'z-10 scale-110 ring-2 ring-primary ring-offset-1'
                            : '',
                        dropCandidate === port.id
                            ? 'z-10 scale-110 ring-2 ring-emerald-500 ring-offset-1'
                            : '',
                        dragActive && isFree(port) && dropCandidate !== port.id
                            ? 'ring-1 ring-primary/40'
                            : '',
                        port.link
                            ? 'cursor-pointer border-transparent text-white shadow-xs'
                            : 'border-border bg-muted/40 text-muted-foreground hover:border-primary/50',
                        dragActive ? '' : 'cursor-pointer',
                    ]"
                    :style="
                        port.link
                            ? { backgroundColor: colorOf(port) ?? undefined }
                            : undefined
                    "
                    :title="port.name"
                >
                    {{ port.number }}
                    <span
                        v-if="port.is_uplink"
                        class="absolute -top-1 -right-1 size-2 rounded-full bg-primary ring-1 ring-card"
                    />
                </button>
            </div>
        </div>
    </div>
</template>
