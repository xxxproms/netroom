<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Cpu,
    Pencil,
    Plus,
    Sparkles,
    Trash2,
    User,
} from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import SubnetFormDialog from '@/components/ipam/SubnetFormDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { store as storeAddress } from '@/routes/ip-addresses';
import { destroy as removeAddress } from '@/routes/ip-addresses';
import { destroy, index as subnetsIndex } from '@/routes/subnets';
import type { Occupant, Subnet, SubnetUsage } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    subnet: Subnet;
    summary: SubnetUsage;
    nextFree: string | null;
    vlans: { id: number; vid: number; name: string }[];
    statuses: string[];
    can: { manage: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.ipam', href: subnetsIndex() }],
    },
});

const editing = ref(false);
const newAddress = ref('');

function remove(): void {
    if (confirm(t('common.deleteConfirm'))) {
        router.delete(destroy(props.subnet.id).url);
    }
}

function useFree(): void {
    if (props.nextFree) {
        newAddress.value = props.nextFree;
        document.getElementById('address-field')?.focus();
    }
}

/** A reservation carries an id; a device-derived row cannot be released here. */
function reservationId(occupant: Occupant): number | null {
    const claim = occupant.claims.find((c) => c.source === 'reservation');

    return claim?.id ?? null;
}

function releaseReservation(id: number): void {
    if (confirm(t('ip.releaseConfirm'))) {
        router.delete(removeAddress(id).url, { preserveScroll: true });
    }
}

function barColor(percent: number): string {
    return percent >= 90
        ? 'bg-red-500'
        : percent >= 66
          ? 'bg-amber-500'
          : 'bg-emerald-500';
}
</script>

<template>
    <Head :title="subnet.cidr" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            :title="subnet.cidr"
            :description="subnet.name ?? subnet.domain.name"
        >
            <template #actions>
                <Badge v-if="subnet.vlan" variant="secondary" class="font-mono">
                    VLAN {{ subnet.vlan.vid }} · {{ subnet.vlan.name }}
                </Badge>
                <Button
                    v-if="can.manage"
                    size="sm"
                    variant="outline"
                    @click="editing = true"
                >
                    <Pencil class="size-4" />
                    {{ t('common.edit') }}
                </Button>
                <Button
                    v-if="can.manage"
                    size="sm"
                    variant="ghost"
                    class="text-destructive"
                    @click="remove"
                >
                    <Trash2 class="size-4" />
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">{{ t('subnet.gateway') }}</p>
                <p class="mt-1 font-mono text-sm">{{ subnet.gateway ?? '—' }}</p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">{{ t('subnet.capacity') }}</p>
                <p class="mt-1 text-sm tabular-nums">{{ summary.capacity }}</p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">{{ t('subnet.free') }}</p>
                <p class="mt-1 text-sm tabular-nums">{{ summary.free }}</p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">{{ t('subnet.utilisation') }}</p>
                <div class="mt-2 flex items-center gap-2">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full"
                            :class="barColor(summary.utilisation)"
                            :style="{ width: `${summary.utilisation}%` }"
                        />
                    </div>
                    <span class="text-sm tabular-nums">{{ summary.utilisation }}%</span>
                </div>
            </div>
        </div>

        <div
            v-if="summary.conflicts > 0"
            class="flex items-center gap-2 rounded-xl border border-red-500/40 bg-red-500/5 px-4 py-3 text-sm text-red-600"
        >
            <AlertTriangle class="size-4" />
            {{ t('subnet.conflictWarning', { count: summary.conflicts }) }}
        </div>

        <!-- Reserve an address, with the next free one one click away. -->
        <Form
            v-if="can.manage"
            v-bind="storeAddress.form(subnet.id)"
            class="flex flex-wrap items-end gap-2 rounded-xl border p-3"
            :options="{ preserveScroll: true }"
            @success="newAddress = ''"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-1.5">
                <label class="text-sm" for="address-field">{{ t('ip.address') }}</label>
                <Input
                    id="address-field"
                    name="address_text"
                    v-model="newAddress"
                    class="h-9 w-40 font-mono"
                    :placeholder="nextFree ?? '10.40.0.2'"
                    required
                />
            </div>
            <div class="grid gap-1.5">
                <label class="text-sm" for="address-host">{{ t('ip.hostname') }}</label>
                <Input id="address-host" name="hostname" class="h-9 w-48" />
            </div>
            <div class="grid gap-1.5">
                <label class="text-sm" for="address-status">{{ t('common.status') }}</label>
                <select
                    id="address-status"
                    name="status"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                >
                    <option v-for="value in statuses" :key="value" :value="value">
                        {{ t(`ip.statusKind.${value}`) }}
                    </option>
                </select>
            </div>
            <Button type="submit" size="sm" :disabled="processing">
                <Plus class="size-4" />
                {{ t('ip.reserve') }}
            </Button>
            <Button
                v-if="nextFree"
                type="button"
                size="sm"
                variant="outline"
                @click="useFree"
            >
                <Sparkles class="size-4" />
                {{ t('ip.nextFree', { address: nextFree }) }}
            </Button>
            <p v-if="errors.address_text" class="w-full text-sm text-destructive">
                {{ errors.address_text }}
            </p>
        </Form>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-[15px]">
                <thead class="bg-muted/50 text-sm text-muted-foreground">
                    <tr>
                        <th class="w-40 px-4 py-3 text-left font-medium">{{ t('ip.address') }}</th>
                        <th class="px-4 py-3 text-left font-medium">{{ t('ip.host') }}</th>
                        <th class="w-32 px-4 py-3 text-left font-medium">{{ t('ip.source') }}</th>
                        <th class="w-28 px-4 py-3 text-left font-medium">{{ t('common.status') }}</th>
                        <th
                            v-if="can.manage"
                            class="w-16 px-4 py-3 text-right font-medium"
                        ></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="occupant in summary.occupants"
                        :key="occupant.long"
                        class="border-t"
                        :class="{ 'bg-red-500/5': occupant.conflict }"
                    >
                        <td class="px-4 py-2.5 font-mono">
                            <span class="flex items-center gap-2">
                                {{ occupant.address }}
                                <Badge
                                    v-if="occupant.is_gateway"
                                    variant="outline"
                                    class="text-xs"
                                >
                                    {{ t('subnet.gateway') }}
                                </Badge>
                                <AlertTriangle
                                    v-if="occupant.conflict"
                                    class="size-4 text-red-500"
                                />
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <div
                                v-for="(claim, index) in occupant.claims"
                                :key="index"
                                class="flex items-center gap-2"
                            >
                                <component
                                    :is="claim.source === 'device' ? Cpu : User"
                                    class="size-4 text-muted-foreground"
                                />
                                <span>
                                    {{ claim.hostname ?? claim.device?.name ?? '—' }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-2 text-sm text-muted-foreground">
                            <span
                                v-for="(claim, index) in occupant.claims"
                                :key="index"
                                class="block"
                            >
                                {{ t(`ip.sourceKind.${claim.source}`) }}
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <Badge
                                v-for="(claim, index) in occupant.claims"
                                :key="index"
                                variant="outline"
                                class="text-xs"
                            >
                                {{ t(`ip.statusKind.${claim.status}`) }}
                            </Badge>
                        </td>
                        <td v-if="can.manage" class="px-2 py-1.5 text-right">
                            <Button
                                v-if="reservationId(occupant) !== null"
                                size="icon"
                                variant="ghost"
                                class="size-8 text-destructive"
                                :title="t('ip.release')"
                                @click="releaseReservation(reservationId(occupant)!)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!summary.occupants.length">
                        <td
                            colspan="5"
                            class="px-4 py-6 text-center text-sm text-muted-foreground"
                        >
                            {{ t('subnet.noAddresses') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <SubnetFormDialog
        v-model:open="editing"
        :domain-id="subnet.vlan_domain_id"
        :vlans="vlans"
        :subnet="subnet"
    />
</template>
