import { router } from '@inertiajs/vue3';
import { useVueFlow } from '@vue-flow/core';
import type { GraphNode } from '@vue-flow/core';
import { computed, ref } from 'vue';
import { annotationIdOf } from '@/composables/useMapAnnotations';
import { positions as savePositions } from '@/routes/map';

/**
 * The diagram-editing toolkit shared by both maps: snap-to-grid, elk auto-layout,
 * align / distribute of a selection, arrow-key nudging and a layout undo stack.
 * Everything that moves nodes routes through here so history and persistence stay
 * in one place — including the annotation layer, which the bulk endpoint saves
 * alongside the sites and devices.
 */

type Kind = 'site' | 'device' | 'annotation';
type Snapshot = Record<string, { x: number; y: number }>;

/**
 * What the bulk endpoint should update for a node. Annotations carry a prefixed
 * id so that a zone and a site can share the number 7 without colliding.
 */
const target = (node: GraphNode): { kind: Kind; id: number } | null => {
    if (node.type === 'site' || node.type === 'device') {
        return { kind: node.type, id: Number(node.id) };
    }

    const annotation = annotationIdOf(node.id);

    return annotation === null ? null : { kind: 'annotation', id: annotation };
};

/** Zones and notes are backdrops: an automatic layout leaves them alone. */
const isDrawing = (node: GraphNode): boolean =>
    node.type === 'zone' || node.type === 'note';

const SNAP_KEY = 'netroom.map.snap';
export const GRID = 20;

const hasStore = typeof localStorage !== 'undefined';

export function useMapTools(flowId: string) {
    const { getNodes, getSelectedNodes, getEdges, updateNode, fitView } =
        useVueFlow(flowId);

    const snap = ref(hasStore && localStorage.getItem(SNAP_KEY) === '1');

    function toggleSnap(): void {
        snap.value = !snap.value;

        if (hasStore) {
            localStorage.setItem(SNAP_KEY, snap.value ? '1' : '0');
        }
    }

    const selectedCount = computed(() => getSelectedNodes.value.length);

    // --- layout history (positions only) --------------------------------------
    const past = ref<Snapshot[]>([]);
    const future = ref<Snapshot[]>([]);
    const canUndo = computed(() => past.value.length > 0);
    const canRedo = computed(() => future.value.length > 0);

    const snapshot = (): Snapshot => {
        const shot: Snapshot = {};
        getNodes.value.forEach((n) => (shot[n.id] = { ...n.position }));

        return shot;
    };

    /** Remember the current layout so the next move can be undone. */
    function record(): void {
        past.value.push(snapshot());
        future.value = [];
    }

    const applySnapshot = (shot: Snapshot): void => {
        Object.entries(shot).forEach(([id, p]) =>
            updateNode(id, { position: { x: p.x, y: p.y } }),
        );
    };

    function persist(list: GraphNode[]): void {
        const nodes = list.flatMap((n) => {
            const it = target(n);

            return it
                ? [
                      {
                          ...it,
                          map_x: Math.round(n.position.x),
                          map_y: Math.round(n.position.y),
                      },
                  ]
                : [];
        });

        if (nodes.length === 0) {
            return;
        }

        router.patch(
            savePositions().url,
            { nodes },
            { preserveState: true, preserveScroll: true },
        );
    }

    const persistAll = (): void => persist(getNodes.value);
    const persistSelected = (): void => persist(getSelectedNodes.value);

    function undo(): void {
        if (!canUndo.value) {
            return;
        }

        future.value.push(snapshot());
        applySnapshot(past.value.pop() as Snapshot);
        persistAll();
    }

    function redo(): void {
        if (!canRedo.value) {
            return;
        }

        past.value.push(snapshot());
        applySnapshot(future.value.pop() as Snapshot);
        persistAll();
    }

    // --- auto layout (elk, loaded on demand — it is a heavy dependency) --------
    async function autoLayout(): Promise<void> {
        const nodes = getNodes.value.filter((n) => !isDrawing(n));

        if (nodes.length === 0) {
            return;
        }

        const { default: ELK } = await import('elkjs/lib/elk.bundled.js');
        const elk = new ELK();

        const laid = await elk.layout({
            id: 'root',
            layoutOptions: {
                'elk.algorithm': 'layered',
                'elk.direction': 'RIGHT',
                'elk.spacing.nodeNode': '60',
                'elk.layered.spacing.nodeNodeBetweenLayers': '120',
            },
            children: nodes.map((n) => ({
                id: n.id,
                width: n.dimensions.width || 180,
                height: n.dimensions.height || 72,
            })),
            edges: getEdges.value.map((e) => ({
                id: e.id,
                sources: [e.source],
                targets: [e.target],
            })),
        });

        record();
        (laid.children ?? []).forEach((c) =>
            updateNode(c.id, { position: { x: c.x ?? 0, y: c.y ?? 0 } }),
        );
        persistAll();
        setTimeout(() => fitView({ padding: 0.2 }), 60);
    }

    // --- align & distribute a multi-selection ---------------------------------
    type AlignDir = 'left' | 'hcenter' | 'right' | 'top' | 'vmiddle' | 'bottom';

    const box = (n: GraphNode) => ({
        x: n.position.x,
        y: n.position.y,
        w: n.dimensions.width || 0,
        h: n.dimensions.height || 0,
    });

    function align(dir: AlignDir): void {
        const sel = getSelectedNodes.value;

        if (sel.length < 2) {
            return;
        }

        const boxes = sel.map(box);
        const minX = Math.min(...boxes.map((b) => b.x));
        const maxR = Math.max(...boxes.map((b) => b.x + b.w));
        const minY = Math.min(...boxes.map((b) => b.y));
        const maxB = Math.max(...boxes.map((b) => b.y + b.h));
        const cx = (minX + maxR) / 2;
        const cy = (minY + maxB) / 2;

        record();
        sel.forEach((n) => {
            const b = box(n);
            const pos = { ...n.position };

            if (dir === 'left') {
                pos.x = minX;
            } else if (dir === 'right') {
                pos.x = maxR - b.w;
            } else if (dir === 'hcenter') {
                pos.x = cx - b.w / 2;
            } else if (dir === 'top') {
                pos.y = minY;
            } else if (dir === 'bottom') {
                pos.y = maxB - b.h;
            } else if (dir === 'vmiddle') {
                pos.y = cy - b.h / 2;
            }

            updateNode(n.id, { position: pos });
        });
        persistSelected();
    }

    function distribute(axis: 'h' | 'v'): void {
        const sel = getSelectedNodes.value;

        if (sel.length < 3) {
            return;
        }

        const centre = (n: GraphNode) =>
            axis === 'h'
                ? n.position.x + (n.dimensions.width || 0) / 2
                : n.position.y + (n.dimensions.height || 0) / 2;

        const ordered = [...sel].sort((a, b) => centre(a) - centre(b));
        const first = centre(ordered[0]);
        const last = centre(ordered[ordered.length - 1]);
        const step = (last - first) / (ordered.length - 1);

        record();
        ordered.forEach((n, i) => {
            const target = first + step * i;
            const pos = { ...n.position };

            if (axis === 'h') {
                pos.x = target - (n.dimensions.width || 0) / 2;
            } else {
                pos.y = target - (n.dimensions.height || 0) / 2;
            }

            updateNode(n.id, { position: pos });
        });
        persistSelected();
    }

    // --- keyboard nudge -------------------------------------------------------
    let nudgeTimer: ReturnType<typeof setTimeout> | null = null;

    function nudge(dx: number, dy: number): void {
        const sel = getSelectedNodes.value;

        if (sel.length === 0) {
            return;
        }

        if (nudgeTimer === null) {
            record();
        }

        sel.forEach((n) =>
            updateNode(n.id, {
                position: { x: n.position.x + dx, y: n.position.y + dy },
            }),
        );

        // Coalesce a burst of key-repeats into one persisted move.
        if (nudgeTimer !== null) {
            clearTimeout(nudgeTimer);
        }

        nudgeTimer = setTimeout(() => {
            persistSelected();
            nudgeTimer = null;
        }, 250);
    }

    return {
        snap,
        snapGrid: [GRID, GRID] as [number, number],
        toggleSnap,
        selectedCount,
        record,
        persistSelected,
        autoLayout,
        align,
        distribute,
        nudge,
        canUndo,
        canRedo,
        undo,
        redo,
    };
}
