import { ref } from 'vue';

/**
 * The map's "draw a link" mode. While on, nodes expose grab handles and dragging
 * from one to another opens the right dialog (a tunnel between sites, a cable
 * between devices). Transient — a view state, so it is not persisted like
 * routing is; each map owns whether it is even offered.
 */
const linking = ref(false);

export function useMapLinking() {
    const toggle = (): void => {
        linking.value = !linking.value;
    };

    const stop = (): void => {
        linking.value = false;
    };

    return { linking, toggle, stop };
}
