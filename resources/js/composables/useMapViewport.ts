import { useVueFlow } from '@vue-flow/core';
import type { ViewportTransform } from '@vue-flow/core';
import { watch } from 'vue';

/**
 * Where the user last left the map. Coming back to a diagram you have zoomed
 * into and finding it reframed from scratch is its own small insult, so the pan
 * and zoom are remembered per map, in this browser only — it is a reading
 * preference, not something the department needs on the server.
 */

const KEY = 'netroom.map.viewport.';
const hasStore = typeof localStorage !== 'undefined';

/** Anything older than this is a different session and a different question. */
const STALE_MS = 12 * 60 * 60 * 1000;

type Saved = ViewportTransform & { at: number };

export function useMapViewport(flowId: string) {
    const { getViewport, setViewport, fitView, viewport } = useVueFlow(flowId);

    function remember(): void {
        if (!hasStore) {
            return;
        }

        const { x, y, zoom } = getViewport();

        try {
            localStorage.setItem(
                KEY + flowId,
                JSON.stringify({ x, y, zoom, at: Date.now() }),
            );
        } catch {
            // A browser with storage turned off simply does not remember.
        }
    }

    function read(): Saved | null {
        if (!hasStore) {
            return null;
        }

        try {
            const raw = localStorage.getItem(KEY + flowId);
            const saved = raw ? (JSON.parse(raw) as Saved) : null;

            return saved && Date.now() - saved.at < STALE_MS ? saved : null;
        } catch {
            return null;
        }
    }

    /**
     * Restores the remembered view, or frames the whole graph when there is
     * nothing to restore — which is also what someone arriving first sees.
     */
    function restore(): void {
        const saved = read();

        if (saved) {
            setViewport({ x: saved.x, y: saved.y, zoom: saved.zoom });

            return;
        }

        fitView({ padding: 0.2 });
    }

    // Watching the live viewport rather than the end-of-gesture event: the
    // zoom buttons and fitView move the map without any gesture ending, and a
    // view the user set with a button is still the view they want back.
    let timer: ReturnType<typeof setTimeout> | null = null;

    watch(
        viewport,
        () => {
            if (timer !== null) {
                clearTimeout(timer);
            }

            timer = setTimeout(() => {
                remember();
                timer = null;
            }, 400);
        },
        { deep: true },
    );

    return { remember, restore };
}
