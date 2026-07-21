import { reactive, ref } from 'vue';

export type Positioned = {
    id: number;
    map_x: number | null;
    map_y: number | null;
};

export type Point = { x: number; y: number };

type Options = {
    /** Canvas width, so the auto-layout grid knows where to wrap. */
    width: number;
    /** Spacing of the fallback grid for nodes never placed by hand. */
    gap?: number;
    /** Persist a node's new spot once it is dropped. */
    onDrop: (id: number, point: Point) => void;
};

/**
 * Shared drag-on-an-SVG behaviour for both maps. Nodes remember where they were
 * put; the ones that never were get laid out on a tidy grid so the map is
 * legible from the first visit.
 */
export function useMapLayout(items: Positioned[], options: Options) {
    const gap = options.gap ?? 200;
    const perRow = Math.max(1, Math.floor(options.width / gap));

    const positions = reactive<Record<number, Point>>({});

    items.forEach((item, index) => {
        positions[item.id] =
            item.map_x !== null && item.map_y !== null
                ? { x: item.map_x, y: item.map_y }
                : {
                      x: gap / 2 + (index % perRow) * gap,
                      y: gap / 2 + Math.floor(index / perRow) * gap,
                  };
    });

    const dragging = ref<number | null>(null);
    let svg: SVGSVGElement | null = null;
    let offset: Point = { x: 0, y: 0 };

    /** Screen pixels → SVG user units, so a drag tracks the cursor at any zoom. */
    function toSvg(event: PointerEvent): Point {
        if (!svg) {
            return { x: event.clientX, y: event.clientY };
        }

        const point = svg.createSVGPoint();
        point.x = event.clientX;
        point.y = event.clientY;

        const ctm = svg.getScreenCTM();

        return ctm
            ? point.matrixTransform(ctm.inverse())
            : { x: point.x, y: point.y };
    }

    function start(id: number, event: PointerEvent): void {
        svg = (event.target as SVGElement).ownerSVGElement;
        const cursor = toSvg(event);
        const node = positions[id];
        offset = { x: cursor.x - node.x, y: cursor.y - node.y };
        dragging.value = id;

        (event.target as Element).setPointerCapture(event.pointerId);
    }

    function move(event: PointerEvent): void {
        if (dragging.value === null) {
            return;
        }

        const cursor = toSvg(event);
        positions[dragging.value] = {
            x: Math.max(0, Math.round(cursor.x - offset.x)),
            y: Math.max(0, Math.round(cursor.y - offset.y)),
        };
    }

    function end(): void {
        if (dragging.value === null) {
            return;
        }

        const id = dragging.value;
        dragging.value = null;
        options.onDrop(id, positions[id]);
    }

    return { positions, dragging, start, move, end };
}
