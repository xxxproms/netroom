<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusChip from '@/components/StatusChip.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index as devicesIndex, show } from '@/routes/devices';
import type { Device } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    devices: Device[];
    statuses: string[];
    can: { create: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.devices', href: devicesIndex() }],
    },
});

const search = ref('');

const filtered = computed(() => {
    const needle = search.value.trim().toLowerCase();

    if (!needle) {
        return props.devices;
    }

    return props.devices.filter(
        (device) =>
            device.name.toLowerCase().includes(needle) ||
            (device.mgmt_ip ?? '').includes(needle),
    );
});

/** Live is green, a spare blue, a failed unit red, a retired one grey. */
const statusTone: Record<string, 'green' | 'blue' | 'red' | 'gray'> = {
    active: 'green',
    spare: 'blue',
    failed: 'red',
    decommissioned: 'gray',
};
</script>

<template>
    <Head :title="t('device.title')" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader :title="t('device.title')">
            <template #actions>
                <input
                    v-model="search"
                    type="search"
                    :placeholder="t('common.search')"
                    class="h-8 w-48 rounded-md border border-input bg-transparent px-3 text-sm"
                />
            </template>
        </PageHeader>

        <EmptyState v-if="!devices.length" :message="t('device.empty')">
            <p v-if="can.create" class="text-xs text-muted-foreground">
                {{ t('device.addFromRack') }}
            </p>
        </EmptyState>

        <Table v-else>
            <TableHeader>
                <TableRow>
                    <TableHead>{{ t('common.name') }}</TableHead>
                    <TableHead>{{ t('device.model') }}</TableHead>
                    <TableHead class="w-32">{{ t('common.status') }}</TableHead>
                    <TableHead>{{ t('device.mgmtIp') }}</TableHead>
                    <TableHead>{{ t('device.location') }}</TableHead>
                    <TableHead class="text-right">
                        {{ t('model.ports') }}
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="device in filtered" :key="device.id">
                    <TableCell>
                        <Link
                            :href="show(device.id)"
                            class="font-medium hover:underline"
                        >
                            {{ device.name }}
                        </Link>
                    </TableCell>
                    <TableCell class="text-muted-foreground">
                        {{ device.model.vendor }} {{ device.model.model }}
                    </TableCell>
                    <TableCell>
                        <StatusChip :tone="statusTone[device.status]">
                            {{ t(`device.statusKind.${device.status}`) }}
                        </StatusChip>
                    </TableCell>
                    <TableCell class="font-mono">
                        {{ device.mgmt_ip ?? '—' }}
                    </TableCell>
                    <TableCell class="text-muted-foreground">
                        <span class="font-mono">{{ device.site.code }}</span>
                        <template v-if="device.rack">
                            · {{ device.rack.name }}
                            <span v-if="device.position_u" class="font-mono">
                                · {{ device.position_u }}U
                            </span>
                        </template>
                    </TableCell>
                    <TableCell class="text-right tabular-nums">
                        {{ device.ports_count }}
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
