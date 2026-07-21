<script setup lang="ts">
import { ArrowUp, Eraser, Tag } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import type { Port, Vlan } from '@/types';

export type VlanMode = 'tagged' | 'untagged';
export type Membership = Record<number, Record<number, VlanMode>>;
export type VlanChange = {
    port_id: number;
    vlan_id: number;
    mode: VlanMode | null;
};

const { t } = useI18n();

const props = defineProps<{
    ports: Port[];
    vlans: Vlan[];
    membership: Membership;
    editable: boolean;
}>();

/** Staged changes, so a run of clicks is one save rather than one per cell. */
const changes = defineModel<VlanChange[]>('changes', { required: true });

// Inertia hands over a reactive proxy, which structuredClone refuses to copy.
const copy = (membership: Membership): Membership =>
    JSON.parse(JSON.stringify(membership));

const state = ref<Membership>(copy(props.membership));

/**
 * The staged changes are held here rather than read back out of the model:
 * a single click can touch three cells, and a prop only refreshes on the next
 * render, so reading the model in between would lose the earlier writes.
 */
const staged = ref<VlanChange[]>([...changes.value]);

watch(staged, (list) => {
    changes.value = [...list];
});

watch(
    () => props.membership,
    (fresh) => {
        state.value = copy(fresh);
    },
);

// Dropping the staged changes — cancelling, or saving them — puts the grid
// back on what the server holds.
watch(changes, (list) => {
    if (!list.length) {
        staged.value = [];
        displaced.clear();
        state.value = copy(props.membership);
    }
});

const modeOf = (portId: number, vlanId: number): VlanMode | undefined =>
    state.value[portId]?.[vlanId];

/** Untagged first: most ports are access ports, so that is the common click. */
const nextMode = (current: VlanMode | undefined): VlanMode | null =>
    current === undefined
        ? 'untagged'
        : current === 'untagged'
          ? 'tagged'
          : null;

/** Which VLAN lost the PVID of a port, so that clicking on can hand it back. */
const displaced = new Map<number, number>();

const untaggedOn = (portId: number): number | undefined => {
    const found = Object.entries(state.value[portId] ?? {}).find(
        ([, mode]) => mode === 'untagged',
    );

    return found ? Number(found[0]) : undefined;
};

function set(portId: number, vlanId: number, mode: VlanMode | null): void {
    const current = modeOf(portId, vlanId);

    if (current === mode) {
        return;
    }

    // A port carries one untagged VLAN — its PVID — so a new one replaces it.
    if (mode === 'untagged') {
        const previous = untaggedOn(portId);

        if (previous !== undefined && previous !== vlanId) {
            displaced.set(portId, previous);
            write(portId, previous, null);
        }
    }

    write(portId, vlanId, mode);

    // Clicking past untagged gives the PVID back to the VLAN that held it,
    // so a cell cycled by mistake costs nothing.
    if (current === 'untagged' && mode !== 'untagged') {
        const previous = displaced.get(portId);
        displaced.delete(portId);

        if (previous !== undefined && previous !== vlanId) {
            write(portId, previous, 'untagged');
        }
    }
}

function write(portId: number, vlanId: number, mode: VlanMode | null): void {
    state.value[portId] ??= {};

    if (mode === null) {
        delete state.value[portId][vlanId];
    } else {
        state.value[portId][vlanId] = mode;
    }

    stage({ port_id: portId, vlan_id: vlanId, mode });
}

/** Keeps one entry per cell, and drops it again if the cell is back as it was. */
function stage(change: VlanChange): void {
    const rest = staged.value.filter(
        (entry) =>
            entry.port_id !== change.port_id ||
            entry.vlan_id !== change.vlan_id,
    );

    const saved = props.membership[change.port_id]?.[change.vlan_id] ?? null;

    staged.value = saved === change.mode ? rest : [...rest, change];
}

const isDirty = (portId: number, vlanId: number): boolean =>
    staged.value.some(
        (change) => change.port_id === portId && change.vlan_id === vlanId,
    );

function cycle(portId: number, vlanId: number): void {
    if (props.editable) {
        set(portId, vlanId, nextMode(modeOf(portId, vlanId)));
    }
}

/** Whole-row work: a management VLAN usually runs on every port at once. */
function fillRow(vlanId: number, mode: VlanMode | null): void {
    props.ports.forEach((port) => set(port.id, vlanId, mode));
}

const pvidOf = (portId: number): Vlan | undefined =>
    props.vlans.find((vlan) => modeOf(portId, vlan.id) === 'untagged');

const colorOf = (vlan: Vlan): string => vlan.color ?? '#64748b';

const hovered = ref<{ port: number | null; vlan: number | null }>({
    port: null,
    vlan: null,
});

function cellStyle(vlan: Vlan, mode: VlanMode | undefined) {
    if (mode === 'untagged') {
        return { backgroundColor: colorOf(vlan), color: '#fff' };
    }

    if (mode === 'tagged') {
        return {
            backgroundColor: `color-mix(in srgb, ${colorOf(vlan)} 22%, var(--card))`,
            color: colorOf(vlan),
            boxShadow: `inset 0 0 0 2px ${colorOf(vlan)}`,
        };
    }

    return {};
}

const columnCount = computed(() => props.ports.length);
</script>

<template>
    <div class="flex flex-col gap-3">
        <div
            class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-muted-foreground"
        >
            <span class="flex items-center gap-2">
                <span
                    class="flex size-7 items-center justify-center rounded bg-primary text-sm font-semibold text-primary-foreground"
                >
                    U
                </span>
                {{ t('vlanMatrix.untagged') }}
            </span>
            <span class="flex items-center gap-2">
                <span
                    class="flex size-7 items-center justify-center rounded text-sm font-semibold text-primary shadow-[inset_0_0_0_2px_var(--primary)]"
                >
                    T
                </span>
                {{ t('vlanMatrix.tagged') }}
            </span>
            <span v-if="editable">{{ t('vlanMatrix.clickHint') }}</span>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-max border-separate border-spacing-0 text-sm">
                <thead>
                    <tr>
                        <th
                            class="sticky left-0 z-20 min-w-56 border-b bg-card px-3 py-2 text-left font-medium"
                        >
                            {{ t('vlan.title') }}
                        </th>
                        <th
                            v-for="port in ports"
                            :key="port.id"
                            class="min-w-11 border-b border-l bg-card p-0 text-center font-medium"
                            :class="{
                                'bg-muted': hovered.port === port.id,
                                'opacity-50': !port.enabled,
                            }"
                            :title="port.description ?? port.name"
                        >
                            <div
                                class="flex h-12 flex-col items-center justify-center gap-0.5"
                            >
                                <span class="text-sm font-semibold">
                                    {{ port.number }}
                                </span>
                                <ArrowUp
                                    v-if="port.is_uplink"
                                    class="size-3.5 text-muted-foreground"
                                />
                                <span
                                    v-else-if="port.media !== 'rj45'"
                                    class="text-[11px] text-muted-foreground uppercase"
                                >
                                    {{ port.media }}
                                </span>
                            </div>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="vlan in vlans"
                        :key="vlan.id"
                        class="group"
                        :class="{ 'bg-muted/40': hovered.vlan === vlan.id }"
                    >
                        <th
                            class="sticky left-0 z-10 border-b bg-card px-3 py-1.5 text-left font-normal"
                            :class="{ 'bg-muted': hovered.vlan === vlan.id }"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="w-1.5 shrink-0 self-stretch rounded-full"
                                    :style="{ backgroundColor: colorOf(vlan) }"
                                />
                                <span
                                    class="w-12 shrink-0 font-mono text-sm font-semibold"
                                >
                                    {{ vlan.vid }}
                                </span>
                                <span
                                    class="flex-1 truncate"
                                    :title="vlan.description ?? vlan.name"
                                >
                                    {{ vlan.name }}
                                </span>

                                <span
                                    v-if="editable"
                                    class="flex shrink-0 gap-0.5 opacity-0 transition group-hover:opacity-100"
                                >
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        class="size-7"
                                        :title="t('vlanMatrix.tagAll')"
                                        @click="fillRow(vlan.id, 'tagged')"
                                    >
                                        <Tag class="size-4" />
                                    </Button>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        class="size-7"
                                        :title="t('vlanMatrix.clearRow')"
                                        @click="fillRow(vlan.id, null)"
                                    >
                                        <Eraser class="size-4" />
                                    </Button>
                                </span>
                            </div>
                        </th>

                        <td
                            v-for="port in ports"
                            :key="port.id"
                            class="min-w-11 border-b border-l p-0"
                        >
                            <button
                                type="button"
                                class="flex h-10 w-full items-center justify-center text-sm font-semibold transition"
                                :class="[
                                    editable
                                        ? 'cursor-pointer hover:brightness-95'
                                        : 'cursor-default',
                                    isDirty(port.id, vlan.id)
                                        ? 'ring-2 ring-amber-500 ring-inset'
                                        : '',
                                ]"
                                :style="
                                    cellStyle(vlan, modeOf(port.id, vlan.id))
                                "
                                :title="`${vlan.vid} ${vlan.name} — ${t('port.number')} ${port.name}`"
                                @mouseenter="
                                    hovered = { port: port.id, vlan: vlan.id }
                                "
                                @mouseleave="
                                    hovered = { port: null, vlan: null }
                                "
                                @click="cycle(port.id, vlan.id)"
                            >
                                {{
                                    modeOf(port.id, vlan.id) === 'untagged'
                                        ? 'U'
                                        : modeOf(port.id, vlan.id) === 'tagged'
                                          ? 'T'
                                          : ''
                                }}
                            </button>
                        </td>
                    </tr>

                    <tr v-if="!vlans.length">
                        <td
                            :colspan="columnCount + 1"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            {{ t('vlanMatrix.noVlans') }}
                        </td>
                    </tr>
                </tbody>

                <tfoot>
                    <!-- The PVID row answers "what does an untagged frame join?" -->
                    <tr>
                        <th
                            class="sticky left-0 z-10 bg-card px-3 py-2 text-left text-sm font-medium text-muted-foreground"
                        >
                            {{ t('vlanMatrix.pvid') }}
                        </th>
                        <td
                            v-for="port in ports"
                            :key="port.id"
                            class="border-l px-1 py-2 text-center font-mono text-sm"
                            :class="
                                pvidOf(port.id)
                                    ? 'text-foreground'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ pvidOf(port.id)?.vid ?? '—' }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
