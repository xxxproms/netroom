<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { index as devicesIndex, show } from '@/routes/devices';
import type { Device } from '@/types';

const { t } = useI18n();

defineProps<{
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

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-[15px]">
                <thead class="bg-muted/50 text-sm text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">
                            {{ t('common.name') }}
                        </th>
                        <th class="px-4 py-3 text-left font-medium">
                            {{ t('device.model') }}
                        </th>
                        <th class="px-4 py-3 text-left font-medium">
                            {{ t('device.mgmtIp') }}
                        </th>
                        <th class="px-4 py-3 text-left font-medium">
                            {{ t('device.location') }}
                        </th>
                        <th class="px-4 py-3 text-right font-medium">
                            {{ t('model.ports') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="device in devices.filter(
                            (item) =>
                                !search ||
                                item.name
                                    .toLowerCase()
                                    .includes(search.toLowerCase()) ||
                                (item.mgmt_ip ?? '').includes(search),
                        )"
                        :key="device.id"
                        class="border-t hover:bg-accent/40"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="show(device.id)"
                                class="font-medium hover:underline"
                            >
                                {{ device.name }}
                            </Link>
                            <Badge
                                v-if="device.status !== 'active'"
                                variant="outline"
                                class="ml-2 text-xs"
                            >
                                {{ t(`device.statusKind.${device.status}`) }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ device.model.vendor }} {{ device.model.model }}
                        </td>
                        <td class="px-4 py-3 font-mono">
                            {{ device.mgmt_ip ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <span class="font-mono">{{
                                device.site.code
                            }}</span>
                            <template v-if="device.rack">
                                · {{ device.rack.name }}
                                <span
                                    v-if="device.position_u"
                                    class="font-mono"
                                >
                                    · {{ device.position_u }}U
                                </span>
                            </template>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ device.ports_count }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
