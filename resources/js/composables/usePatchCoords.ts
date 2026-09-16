import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import type { Ref } from 'vue';

export type Point = { x: number; y: number };

/**
 * Measures where every port and uplink chip sits inside a container, so the
 * cord overlay can be drawn between real elements rather than a hand-computed
 * grid. Coordinates are relative to the container, which keeps them right no
 * matter how the page is scrolled. Every element is anchored at its centre, and
 * the cord overlay is drawn on top with thin lines, as the network map does.
 */
export function usePatchCoords(container: Ref<HTMLElement | null>) {
    const ports = ref<Record<number, Point>>({});
    const chips = ref<Record<number, Point>>({});
    const size = ref({ width: 0, height: 0 });

    function centreOf(node: HTMLElement, base: DOMRect): Point {
        const rect = node.getBoundingClientRect();

        return {
            x: rect.left - base.left + rect.width / 2,
            y: rect.top - base.top + rect.height / 2,
        };
    }

    function measure(): void {
        const el = container.value;

        if (!el) {
            return;
        }

        const base = el.getBoundingClientRect();
        size.value = { width: el.scrollWidth, height: el.scrollHeight };

        const nextPorts: Record<number, Point> = {};
        el.querySelectorAll<HTMLElement>('[data-pid]').forEach((node) => {
            nextPorts[Number(node.dataset.pid)] = centreOf(node, base);
        });
        ports.value = nextPorts;

        const nextChips: Record<number, Point> = {};
        el.querySelectorAll<HTMLElement>('[data-ext]').forEach((node) => {
            nextChips[Number(node.dataset.ext)] = centreOf(node, base);
        });
        chips.value = nextChips;
    }

    /** Wait for the DOM to settle (data just loaded) before the first measure. */
    async function remeasure(): Promise<void> {
        await nextTick();
        measure();
    }

    let observer: ResizeObserver | null = null;

    onMounted(() => {
        observer = new ResizeObserver(() => measure());

        if (container.value) {
            observer.observe(container.value);
        }

        window.addEventListener('resize', measure);
    });

    onBeforeUnmount(() => {
        observer?.disconnect();
        window.removeEventListener('resize', measure);
    });

    return { ports, chips, size, measure, remeasure };
}
