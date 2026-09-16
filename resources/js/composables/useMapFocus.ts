import { useVueFlow } from '@vue-flow/core';
import { computed, ref, watch } from 'vue';
import type { ComputedRef } from 'vue';

/**
 * Finding your way around a large diagram: search by name, narrow the map down
 * to the kit you care about, and trace what a device is wired to. All three end
 * in the same place — a set of nodes and edges that stay lit while the rest of
 * the map fades back — so they live together rather than fighting each other.
 */

/** One thing on the map, described in the terms the view bar can search on. */
export type MapEntity = {
    /** The Vue Flow node id, so a match can be focused straight away. */
    id: string;
    label: string;
    sub: string | null;
    /** Values this entity can be filtered by, e.g. status or VLAN. */
    facets: Record<string, string[]>;
};

export type FacetGroup = {
    key: string;
    label: string;
    options: { value: string; label: string }[];
};

export type MapEdge = { id: string; source: string; target: string };

/**
 * A filter group built from what is actually on this map, so the menu never
 * offers a choice that would empty the canvas. A group with a single value is
 * dropped — there would be nothing to narrow.
 */
export function facetGroup(
    entities: MapEntity[],
    key: string,
    label: string,
    labelOf: (value: string) => string,
): FacetGroup | null {
    const values = new Set<string>();

    entities.forEach((entity) =>
        (entity.facets[key] ?? []).forEach((value) => values.add(value)),
    );

    if (values.size < 2) {
        return null;
    }

    return {
        key,
        label,
        options: [...values]
            .sort((a, b) => a.localeCompare(b, undefined, { numeric: true }))
            .map((value) => ({ value, label: labelOf(value) })),
    };
}

export function useMapFocus(
    flowId: string,
    entities: ComputedRef<MapEntity[]>,
    edges: ComputedRef<MapEdge[]>,
) {
    const { fitView, getSelectedNodes, addSelectedNodes, findNode } =
        useVueFlow(flowId);

    const query = ref('');
    const searching = ref(false);

    /** Chosen values per facet key; an empty list means "do not narrow on it". */
    const filters = ref<Record<string, string[]>>({});
    const tracing = ref(false);

    // --- filtering -----------------------------------------------------------
    const activeFilters = computed(() =>
        Object.entries(filters.value).filter(([, values]) => values.length > 0),
    );

    const filterCount = computed(() =>
        activeFilters.value.reduce((sum, [, values]) => sum + values.length, 0),
    );

    const passes = (entity: MapEntity): boolean =>
        activeFilters.value.every(([key, values]) =>
            (entity.facets[key] ?? []).some((value) => values.includes(value)),
        );

    function toggleFilter(key: string, value: string): void {
        const current = filters.value[key] ?? [];

        filters.value = {
            ...filters.value,
            [key]: current.includes(value)
                ? current.filter((v) => v !== value)
                : [...current, value],
        };
    }

    const isFiltered = (key: string, value: string): boolean =>
        (filters.value[key] ?? []).includes(value);

    const clearFilters = (): void => {
        filters.value = {};
    };

    // --- search --------------------------------------------------------------
    const matches = computed<MapEntity[]>(() => {
        const needle = query.value.trim().toLowerCase();

        if (needle === '') {
            return [];
        }

        return entities.value
            .filter(
                (entity) =>
                    entity.label.toLowerCase().includes(needle) ||
                    (entity.sub ?? '').toLowerCase().includes(needle),
            )
            .slice(0, 8);
    });

    /** The node a search hit just jumped to, lit briefly so the eye catches it. */
    const flashed = ref<string | null>(null);
    let flashTimer: ReturnType<typeof setTimeout> | null = null;

    function focus(id: string): void {
        if (!findNode(id)) {
            return;
        }

        fitView({ nodes: [id], duration: 400, padding: 0.6, maxZoom: 1.4 });
        flashed.value = id;

        if (flashTimer !== null) {
            clearTimeout(flashTimer);
        }

        flashTimer = setTimeout(() => {
            flashed.value = null;
            flashTimer = null;
        }, 2000);

        searching.value = false;
        query.value = '';
    }

    // --- trace ---------------------------------------------------------------
    // While tracing, selecting one node lights its cables and whatever sits at
    // the far end of them; the selection itself drives it, so the user needs no
    // second gesture.
    const traceRoot = ref<string | null>(null);

    watch([getSelectedNodes, tracing], ([selected, on]) => {
        traceRoot.value = on && selected.length === 1 ? selected[0].id : null;
    });

    const traced = computed<{ nodes: Set<string>; edges: Set<string> } | null>(
        () => {
            const root = traceRoot.value;

            if (root === null) {
                return null;
            }

            const nodes = new Set<string>([root]);
            const lit = new Set<string>();

            edges.value.forEach((edge) => {
                if (edge.source === root || edge.target === root) {
                    lit.add(edge.id);
                    nodes.add(edge.source);
                    nodes.add(edge.target);
                }
            });

            return { nodes, edges: lit };
        },
    );

    // --- what the canvas paints ----------------------------------------------
    const narrowed = computed<Set<string> | null>(() => {
        if (traced.value) {
            return traced.value.nodes;
        }

        if (activeFilters.value.length === 0) {
            return null;
        }

        return new Set(entities.value.filter(passes).map((e) => e.id));
    });

    /** Drawings are the backdrop, not the subject: narrowing never fades them. */
    const known = computed(() => new Set(entities.value.map((e) => e.id)));

    function nodeClass(id: string): string | undefined {
        const classes: string[] = [];
        const set = narrowed.value;

        if (set && known.value.has(id) && !set.has(id)) {
            classes.push('map-dim');
        }

        if (flashed.value === id) {
            classes.push('map-flash');
        }

        return classes.length > 0 ? classes.join(' ') : undefined;
    }

    function edgeClass(edge: MapEdge): string | undefined {
        if (traced.value) {
            return traced.value.edges.has(edge.id) ? 'map-lit' : 'map-dim';
        }

        const set = narrowed.value;

        if (set && (!set.has(edge.source) || !set.has(edge.target))) {
            return 'map-dim';
        }

        return undefined;
    }

    /** Everything that changes what the canvas paints, for the pages to watch. */
    const paint = computed(() => [
        narrowed.value ? [...narrowed.value].sort().join(',') : '',
        traced.value ? [...traced.value.edges].sort().join(',') : '',
        flashed.value ?? '',
    ]);

    /** Selecting the one node a panel should describe, from the search list. */
    const select = (id: string): void => {
        const node = findNode(id);

        if (node) {
            addSelectedNodes([node]);
        }
    };

    return {
        query,
        searching,
        matches,
        focus,
        select,
        filters,
        filterCount,
        toggleFilter,
        isFiltered,
        clearFilters,
        tracing,
        traceRoot,
        nodeClass,
        edgeClass,
        paint,
    };
}
