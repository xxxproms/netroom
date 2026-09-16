import { router } from '@inertiajs/vue3';
import { useVueFlow } from '@vue-flow/core';
import type { Node } from '@vue-flow/core';
import { ref } from 'vue';
import {
    destroy as destroyAnnotation,
    store as storeAnnotation,
    update as updateAnnotation,
} from '@/routes/map/annotations';
import type { MapAnnotation, MapAnnotationType } from '@/types';

/**
 * The annotation layer: zones that frame the kit sharing a server room, a floor
 * or a VLAN domain, and notes pinned beside them. They live on the same canvas
 * as sites and devices, so their node ids carry a prefix to stay out of the way
 * of the real ones.
 */

export const ANNOTATION_PREFIX = 'ann:';

export const annotationIdOf = (nodeId: string): number | null =>
    nodeId.startsWith(ANNOTATION_PREFIX)
        ? Number(nodeId.slice(ANNOTATION_PREFIX.length))
        : null;

export const SIZES: Record<
    MapAnnotationType,
    { width: number; height: number }
> = {
    zone: { width: 320, height: 220 },
    note: { width: 220, height: 120 },
};

/** The colour a drawing falls back to when none was picked. */
export const ANNOTATION_COLORS: Record<MapAnnotationType, string> = {
    zone: '#64748b',
    note: '#d97706',
};

/**
 * A zone is a backdrop: it sits under the kit and the links it frames, drags
 * only by its title bar so panning across it still works, and never takes part
 * in wiring. A note floats above instead, and moves from anywhere on it.
 */
export const annotationNodes = (
    list: MapAnnotation[],
    editable: boolean,
): Node[] =>
    list.map((annotation) => ({
        id: ANNOTATION_PREFIX + annotation.id,
        type: annotation.type,
        position: { x: annotation.map_x, y: annotation.map_y },
        style: {
            width: `${annotation.width}px`,
            height: `${annotation.height}px`,
            // Vue Flow writes `pointer-events: all` inline on every node, so a
            // zone can only become click-through from here, not from the sheet.
            ...(annotation.type === 'zone'
                ? { pointerEvents: 'none' as const }
                : {}),
        },
        zIndex: annotation.type === 'zone' ? -1 : 1,
        dragHandle: annotation.type === 'zone' ? '.zone-handle' : undefined,
        draggable: editable,
        selectable: editable,
        connectable: false,
        data: {
            text: annotation.text,
            color: annotation.color,
            editable,
        },
    }));

export function useMapAnnotations(flowId: string, siteId: number | null) {
    const { vueFlowRef, screenToFlowCoordinate } = useVueFlow(flowId);

    /** The drawing whose text and colour are open in the dialog. */
    const editing = ref<MapAnnotation | null>(null);

    /** Drops a fresh drawing in the middle of what the user is looking at. */
    function add(type: MapAnnotationType): void {
        const size = SIZES[type];
        const rect = vueFlowRef.value?.getBoundingClientRect();
        const centre = rect
            ? screenToFlowCoordinate({
                  x: rect.left + rect.width / 2,
                  y: rect.top + rect.height / 2,
              })
            : { x: 0, y: 0 };

        router.post(
            storeAnnotation().url,
            {
                site_id: siteId,
                type,
                text: null,
                map_x: Math.max(0, Math.round(centre.x - size.width / 2)),
                map_y: Math.max(0, Math.round(centre.y - size.height / 2)),
                width: size.width,
                height: size.height,
                color: null,
                // Notes read on top of the zones they annotate.
                z: type === 'note' ? 1 : 0,
            },
            { preserveScroll: true, preserveState: true },
        );
    }

    function save(value: { text: string | null; color: string | null }): void {
        if (!editing.value) {
            return;
        }

        router.patch(updateAnnotation(editing.value.id).url, value, {
            preserveScroll: true,
            onSuccess: () => (editing.value = null),
        });
    }

    function remove(): void {
        if (!editing.value) {
            return;
        }

        router.delete(destroyAnnotation(editing.value.id).url, {
            preserveScroll: true,
            onSuccess: () => (editing.value = null),
        });
    }

    /** Opens the dialog for the annotation behind a node id, if it is one. */
    function edit(nodeId: string, list: MapAnnotation[]): boolean {
        const id = annotationIdOf(nodeId);

        if (id === null) {
            return false;
        }

        editing.value = list.find((a) => a.id === id) ?? null;

        return true;
    }

    return { editing, add, save, remove, edit };
}

/** Saves where a drawing came to rest after it was dragged. */
export function saveAnnotationPosition(id: number, x: number, y: number): void {
    router.patch(
        updateAnnotation(id).url,
        {
            map_x: Math.max(0, Math.round(x)),
            map_y: Math.max(0, Math.round(y)),
        },
        { preserveScroll: true, preserveState: true },
    );
}

/** Saves a drawing's box after it was resized — used from the node itself. */
export function saveAnnotationBox(
    id: number,
    box: { x: number; y: number; width: number; height: number },
): void {
    router.patch(
        updateAnnotation(id).url,
        {
            map_x: Math.max(0, Math.round(box.x)),
            map_y: Math.max(0, Math.round(box.y)),
            width: Math.round(box.width),
            height: Math.round(box.height),
        },
        { preserveScroll: true, preserveState: true },
    );
}
