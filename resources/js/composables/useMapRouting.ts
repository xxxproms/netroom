import { ref, watch } from 'vue';

/** How map edges are drawn — a straight run, or right-angled like a diagram. */
export type EdgeRouting = 'straight' | 'orthogonal';

const KEY = 'netroom.map.routing';

function initial(): EdgeRouting {
    if (typeof localStorage === 'undefined') {
        return 'orthogonal';
    }

    return localStorage.getItem(KEY) === 'straight' ? 'straight' : 'orthogonal';
}

// One shared preference across both maps — a UI setting, not per-graph state.
const routing = ref<EdgeRouting>(initial());

watch(routing, (value) => {
    if (typeof localStorage !== 'undefined') {
        localStorage.setItem(KEY, value);
    }
});

/** Reactive edge-routing preference plus a toggle, remembered between visits. */
export function useMapRouting() {
    const toggle = (): void => {
        routing.value =
            routing.value === 'orthogonal' ? 'straight' : 'orthogonal';
    };

    return { routing, toggle };
}
