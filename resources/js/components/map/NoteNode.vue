<script setup lang="ts">
import { NodeResizer } from '@vue-flow/node-resizer';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    ANNOTATION_COLORS,
    annotationIdOf,
    saveAnnotationBox,
} from '@/composables/useMapAnnotations';
import type { AnnotationNodeData } from './ZoneNode.vue';

const props = defineProps<{
    id: string;
    data: AnnotationNodeData;
    selected?: boolean;
}>();

const { t } = useI18n();

const accent = computed(() => props.data.color ?? ANNOTATION_COLORS.note);

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
        class="size-full overflow-hidden rounded-md border-l-4 bg-card px-3 py-2 shadow-sm"
        :class="[
            selected ? 'ring-2 ring-primary ring-offset-2' : '',
            data.editable ? 'cursor-move' : '',
        ]"
        :style="{ borderColor: accent, background: `${accent}1f` }"
    >
        <NodeResizer
            v-if="data.editable"
            :color="accent"
            :min-width="120"
            :min-height="60"
            @resize-end="onResizeEnd"
        />

        <p
            class="h-full overflow-hidden text-xs leading-snug whitespace-pre-wrap"
            :class="
                data.text ? 'text-foreground' : 'text-muted-foreground italic'
            "
        >
            {{ data.text || t('map.noteUntitled') }}
        </p>
    </div>
</template>
