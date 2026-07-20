import { ref } from 'vue';

export type RackScale = 'compact' | 'normal' | 'large';

type ScaleMetrics = {
    unit: number;
    width: number;
    name: string;
    detail: string;
    label: string;
};

/**
 * How tall a rack unit is drawn. A unit has to fit the device name and its
 * model on two readable lines, so the smallest step is still 36px — the
 * earlier 26px clipped the second line outright.
 */
export const rackScales: Record<RackScale, ScaleMetrics> = {
    compact: {
        unit: 36,
        width: 300,
        name: 'text-sm',
        detail: 'text-xs',
        label: 'text-xs',
    },
    normal: {
        unit: 48,
        width: 380,
        name: 'text-base',
        detail: 'text-sm',
        label: 'text-sm',
    },
    large: {
        unit: 64,
        width: 460,
        name: 'text-lg',
        detail: 'text-base',
        label: 'text-base',
    },
};

const STORAGE_KEY = 'netroom.rack-scale';

const stored =
    typeof localStorage !== 'undefined'
        ? (localStorage.getItem(STORAGE_KEY) as RackScale | null)
        : null;

const scale = ref<RackScale>(
    stored && stored in rackScales ? stored : 'normal',
);

/**
 * The chosen size is a matter of eyesight rather than of the data, so it is
 * remembered in the browser rather than on the account.
 */
export function useRackScale() {
    function setScale(value: RackScale): void {
        scale.value = value;

        if (typeof localStorage !== 'undefined') {
            localStorage.setItem(STORAGE_KEY, value);
        }
    }

    return { scale, setScale, rackScales };
}
