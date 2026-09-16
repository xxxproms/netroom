<script setup lang="ts">
import {
    BaseEdge,
    EdgeLabelRenderer,
    getSmoothStepPath,
    getStraightPath,
    useVueFlow,
} from '@vue-flow/core';
import { computed } from 'vue';
import { useMapRouting } from '@/composables/useMapRouting';
import { getEdgeParams } from './floatingEdge';

/** Data a map hangs on each edge to style it and label it. */
export type EdgeData = {
    color: string;
    dashed?: boolean;
    label?: string | null;
    clickable?: boolean;
};

const props = defineProps<{
    id: string;
    source: string;
    target: string;
    data?: EdgeData;
    selected?: boolean;
}>();

const { findNode } = useVueFlow();
const { routing } = useMapRouting();

const sourceNode = computed(() => findNode(props.source));
const targetNode = computed(() => findNode(props.target));

/** [path, labelX, labelY, ...] recomputed as either endpoint moves. */
const geometry = computed(() => {
    const s = sourceNode.value;
    const t = targetNode.value;

    if (!s || !t || !s.dimensions.width || !t.dimensions.width) {
        return ['', 0, 0] as const;
    }

    const { sx, sy, tx, ty, sourcePos, targetPos } = getEdgeParams(s, t);

    if (routing.value === 'orthogonal') {
        return getSmoothStepPath({
            sourceX: sx,
            sourceY: sy,
            sourcePosition: sourcePos,
            targetX: tx,
            targetY: ty,
            targetPosition: targetPos,
            borderRadius: 10,
        });
    }

    return getStraightPath({
        sourceX: sx,
        sourceY: sy,
        targetX: tx,
        targetY: ty,
    });
});

const color = computed(() => props.data?.color ?? '#94a3b8');
</script>

<template>
    <BaseEdge
        :id="id"
        :path="geometry[0]"
        :style="{
            stroke: color,
            strokeWidth: selected ? 4 : 2.5,
            strokeDasharray: data?.dashed ? '8 5' : '0',
        }"
    />

    <EdgeLabelRenderer v-if="data?.label">
        <div
            :style="{
                transform: `translate(-50%, -50%) translate(${geometry[1]}px, ${geometry[2]}px)`,
                borderColor: color,
            }"
            class="pointer-events-none absolute rounded-full border bg-card px-2 py-0.5 text-xs font-medium text-foreground shadow-sm"
        >
            {{ data.label }}
        </div>
    </EdgeLabelRenderer>
</template>
