<script setup lang="ts">
import { NodeResizer } from '@vue-flow/node-resizer';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    ANNOTATION_COLORS,
    annotationIdOf,
    saveAnnotationBox,
} from '@/composables/useMapAnnotations';

export type AnnotationNodeData = {
    text: string | null;
    color: string | null;
    editable: boolean;
};

const props = defineProps<{
    id: string;
    data: AnnotationNodeData;
    selected?: boolean;
}>();

const { t } = useI18n();

const accent = computed(() => props.data.color ?? ANNOTATION_COLORS.zone);

/** Resizing moves the top-left corner too, so the whole box is saved. */
function onResizeEnd(event: {
    params: { x: number; y: number; width: number; height: number };
}): void {
    const id = annotationIdOf(props.id);

    if (id !== null) {
        saveAnnotationBox(id, event.params);
    }
}
</script>

<template>
    <div
        class="size-full rounded-xl border-2 border-dashed"
        :class="selected ? 'ring-2 ring-primary ring-offset-2' : ''"
        :style="{ borderColor: accent, background: `${accent}14` }"
    >
        <NodeResizer
            v-if="data.editable"
            :color="accent"
            :min-width="120"
            :min-height="80"
            @resize-end="onResizeEnd"
        />

        <span
            class="zone-handle absolute top-0 left-0 max-w-full truncate rounded-tl-[10px] rounded-br-lg px-2.5 py-1 text-xs font-semibold text-white"
            :class="data.editable ? 'cursor-move' : ''"
            :style="{ background: accent }"
        >
            {{ data.text || t('map.zoneUntitled') }}
        </span>
    </div>
</template>
