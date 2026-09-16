<script setup lang="ts">
import { Handle, Position } from '@vue-flow/core';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMapLinking } from '@/composables/useMapLinking';
import { deviceColor, deviceIcon, kindOf, statusColor } from './nodeMeta';

export type DeviceNodeData = {
    name: string;
    kind: string;
    model: string;
    status: string;
    ports_count: number;
    mgmt_ip: string | null;
    color: string | null;
};

const props = defineProps<{
    data: DeviceNodeData;
    selected?: boolean;
}>();

const { t } = useI18n();

const kind = computed(() => kindOf(deviceIcon, props.data.kind));
const accent = computed(
    () => props.data.color ?? deviceColor[kind.value] ?? deviceColor.other,
);
const icon = computed(() => deviceIcon[kind.value]);
const dot = computed(
    () => statusColor[props.data.status] ?? statusColor.decommissioned,
);

const { linking } = useMapLinking();
const handleClass = computed(() =>
    linking.value
        ? '!size-3.5 !border-2 !border-white !bg-primary !opacity-100'
        : '!size-2 !border-0 !opacity-0',
);
</script>

<template>
    <div
        class="flex max-w-[240px] min-w-[172px] items-stretch overflow-hidden rounded-xl border-2 bg-card shadow-sm transition-shadow hover:shadow-md"
        :class="selected ? 'ring-2 ring-primary ring-offset-2' : ''"
        :style="{ borderColor: accent }"
    >
        <span
            class="flex w-9 shrink-0 items-center justify-center text-white"
            :style="{ background: accent }"
        >
            <component :is="icon" class="size-4.5" />
        </span>

        <span class="flex min-w-0 flex-col gap-0.5 px-3 py-2">
            <span class="flex items-center gap-1.5">
                <span
                    class="size-2 shrink-0 rounded-full"
                    :style="{ background: dot }"
                    :title="t(`device.statusKind.${data.status}`)"
                />
                <span
                    class="truncate text-sm font-semibold text-foreground"
                    :title="data.name"
                >
                    {{ data.name }}
                </span>
            </span>

            <span class="truncate text-xs text-muted-foreground">
                {{ t(`model.kind.${data.kind}`) }} · {{ data.model }}
            </span>

            <span
                class="flex items-center gap-2 text-[11px] text-muted-foreground"
            >
                <span>{{
                    t('map.portCount', { count: data.ports_count })
                }}</span>
                <span v-if="data.mgmt_ip" class="font-mono">
                    {{ data.mgmt_ip }}
                </span>
            </span>
        </span>

        <Handle type="target" :position="Position.Left" :class="handleClass" />
        <Handle type="source" :position="Position.Right" :class="handleClass" />
    </div>
</template>
