export type Point = { x: number; y: number };

export type Positioned = {
    id: number;
    map_x: number | null;
    map_y: number | null;
};

/**
 * A placement function for map nodes: a node that was arranged by hand keeps its
 * saved spot; one that never was gets a tidy grid cell, so the map is legible
 * from the first visit. Vue Flow owns dragging — this only seeds initial spots.
 */
export function autoGrid(
    items: Positioned[],
    options: { width: number; gap?: number },
): (item: Positioned) => Point {
    const gap = options.gap ?? 220;
    const perRow = Math.max(1, Math.floor(options.width / gap));

    const order = new Map<number, number>();
    items.forEach((item, index) => order.set(item.id, index));

    return (item: Positioned): Point => {
        if (item.map_x !== null && item.map_y !== null) {
            return { x: item.map_x, y: item.map_y };
        }

        const index = order.get(item.id) ?? 0;

        return {
            x: gap / 2 + (index % perRow) * gap,
            y: gap / 2 + Math.floor(index / perRow) * gap,
        };
    };
}
