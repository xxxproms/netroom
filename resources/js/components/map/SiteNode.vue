<script setup lang="ts">
import { Handle, Position } from '@vue-flow/core';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMapLinking } from '@/composables/useMapLinking';
import { kindOf, siteIcon } from './nodeMeta';

export type SiteNodeData = {
    name: string;
    code: string;
    kind: string;
    color: string | null;
    rooms_count: number;
    devices_count: number;
};

const props = defineProps<{
    data: SiteNodeData;
    selected?: boolean;
}>();

const { t } = useI18n();

const background = computed(() => props.data.color ?? '#475569');
const icon = computed(() => siteIcon[kindOf(siteIcon, props.data.kind)]);

const { linking } = useMapLinking();
const handleClass = computed(() =>
    linking.value
        ? '!size-3.5 !border-2 !border-white !bg-primary !opacity-100'
        : '!size-2 !border-0 !bg-white/40 !opacity-0',
);
</script>

<template>
    <div
        class="flex min-w-[176px] items-center gap-3 rounded-2xl px-4 py-3 text-white shadow-md transition-shadow hover:shadow-lg"
        :class="selected ? 'ring-2 ring-primary ring-offset-2' : ''"
        :style="{ background: background }"
    >
        <span
            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/15"
        >
            <component :is="icon" class="size-5.5" />
        </span>

        <span class="flex flex-col">
            <span class="text-base leading-tight font-semibold">
                {{ data.name }}
            </span>
            <span class="text-xs opacity-80">{{ data.code }}</span>
            <span class="mt-1 flex gap-2 text-[11px] opacity-90">
                <span>{{
                    t('map.deviceCount', { count: data.devices_count })
                }}</span>
                <span>·</span>
                <span>{{
                    t('map.roomCount', { count: data.rooms_count })
                }}</span>
            </span>
        </span>

        <Handle type="target" :position="Position.Left" :class="handleClass" />
        <Handle type="source" :position="Position.Right" :class="handleClass" />
    </div>
</template>
