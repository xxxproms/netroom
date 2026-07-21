<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, Network, Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import DeviceFormDialog from '@/components/devices/DeviceFormDialog.vue';
import PortTable from '@/components/devices/PortTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    destroy,
    index as devicesIndex,
    vlans as deviceVlans,
} from '@/routes/devices';
import { show as showRack } from '@/routes/racks';
import type { Device, DeviceModelSummary, Rack } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    device: Device;
    models: DeviceModelSummary[];
    racks: Rack[];
    statuses: string[];
    faces: string[];
    cable: { media: string[]; statuses: string[]; strands: number[] };
    can: { update: boolean; delete: boolean; cable: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.devices', href: devicesIndex() }],
    },
});

const editing = ref(false);

function remove(): void {
    if (confirm(t('common.deleteConfirm'))) {
        router.delete(destroy(props.device.id).url);
    }
}
</script>

<template>
    <Head :title="device.name" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            :title="device.name"
            :description="`${device.model.vendor} ${device.model.model}`"
        >
            <template #actions>
                <Badge
                    :variant="
                        device.status === 'active' ? 'secondary' : 'outline'
                    "
                >
                    {{ t(`device.statusKind.${device.status}`) }}
                </Badge>
                <a
                    v-if="device.mgmt_url"
                    :href="device.mgmt_url"
                    target="_blank"
                    rel="noopener"
                >
                    <Button size="sm" variant="outline">
                        <ExternalLink class="size-4" />
                        {{ t('device.openWebUi') }}
                    </Button>
                </a>
                <Link
                    v-if="device.model.kind === 'switch'"
                    :href="deviceVlans(device.id).url"
                >
                    <Button size="sm" variant="outline">
                        <Network class="size-4" />
                        {{ t('vlanMatrix.open') }}
                    </Button>
                </Link>
                <Button
                    v-if="can.update"
                    size="sm"
                    variant="outline"
                    @click="editing = true"
                >
                    <Pencil class="size-4" />
                    {{ t('common.edit') }}
                </Button>
                <Button
                    v-if="can.delete"
                    size="sm"
                    variant="ghost"
                    class="text-destructive"
                    @click="remove"
                >
                    <Trash2 class="size-4" />
                </Button>
            </template>
        </PageHeader>

        <dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border p-4">
                <dt class="text-xs text-muted-foreground">
                    {{ t('device.mgmtIp') }}
                </dt>
                <dd class="mt-1 font-mono text-sm">
                    {{ device.mgmt_ip ?? '—' }}
                </dd>
            </div>
            <div class="rounded-xl border p-4">
                <dt class="text-xs text-muted-foreground">
                    {{ t('device.location') }}
                </dt>
                <dd class="mt-1 text-sm">
                    <Link
                        v-if="device.rack"
                        :href="showRack(device.rack.id)"
                        class="hover:underline"
                    >
                        {{ device.rack.name }}
                        <span v-if="device.position_u" class="font-mono">
                            · {{ device.position_u }}U
                        </span>
                    </Link>
                    <span v-else>{{ t('device.noRack') }}</span>
                </dd>
            </div>
            <div class="rounded-xl border p-4">
                <dt class="text-xs text-muted-foreground">
                    {{ t('device.serial') }}
                </dt>
                <dd class="mt-1 font-mono text-sm">
                    {{ device.serial ?? '—' }}
                </dd>
            </div>
            <div class="rounded-xl border p-4">
                <dt class="text-xs text-muted-foreground">
                    {{ t('model.ports') }}
                </dt>
                <dd class="mt-1 text-sm tabular-nums">
                    {{ device.ports_count }}
                </dd>
            </div>
        </dl>

        <p v-if="device.notes" class="text-sm whitespace-pre-line">
            {{ device.notes }}
        </p>

        <PortTable
            :ports="device.ports ?? []"
            :editable="can.update"
            :site-id="device.site_id!"
            :can-cable="can.cable"
            :cable="cable"
        />
    </div>

    <DeviceFormDialog
        v-model:open="editing"
        :site-id="device.site_id!"
        :models="models"
        :statuses="statuses"
        :faces="faces"
        :racks="racks"
        :device="device"
    />
</template>
