<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import DeviceFormDialog from '@/components/devices/DeviceFormDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import RackElevation from '@/components/racks/RackElevation.vue';
import RackScaleToggle from '@/components/racks/RackScaleToggle.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show as showDevice } from '@/routes/devices';
import { index as racksIndex } from '@/routes/racks';
import { move } from '@/routes/racks/devices';
import { show as showRoom } from '@/routes/rooms';
import type { DeviceModelSummary, RackDetail, RackDevice } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    rack: RackDetail;
    devices: RackDevice[];
    models: DeviceModelSummary[];
    statuses: string[];
    faces: string[];
    can: { update: boolean; createDevice: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.racks', href: racksIndex() }],
    },
});

const adding = ref(false);
const addingAt = ref<number | undefined>();
const addingFace = ref('front');

function open(device: RackDevice): void {
    router.get(showDevice(device.id).url);
}

function reposition(device: RackDevice, position: number): void {
    router.put(
        move({ rack: props.rack.id, device: device.id }).url,
        { position_u: position, face: device.face },
        { preserveScroll: true },
    );
}

function addAt(position: number, face: string): void {
    if (!props.can.createDevice) {
        return;
    }

    addingAt.value = position;
    addingFace.value = face;
    adding.value = true;
}
</script>

<template>
    <Head :title="rack.name" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            :title="rack.name"
            :description="`${t(`rack.kind.${rack.kind}`)} · ${rack.u_height}U`"
        >
            <template #actions>
                <RackScaleToggle />
                <Link :href="showRoom(rack.room.id)">
                    <Badge variant="outline">{{ rack.room.name }}</Badge>
                </Link>
                <Badge variant="outline" class="font-mono">
                    {{ rack.site.code }}
                </Badge>
                <Button
                    v-if="can.createDevice"
                    size="sm"
                    @click="addAt(1, 'front')"
                >
                    <Plus class="size-4" />
                    {{ t('device.new') }}
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-6">
            <RackElevation
                v-for="face in faces"
                :key="face"
                :u-height="rack.u_height"
                :devices="devices"
                :face="face"
                :editable="can.update"
                @select="open"
                @move="reposition"
                @add-at="(position) => addAt(position, face)"
            />

            <div class="min-w-56 flex-1 space-y-2">
                <h2 class="text-sm font-semibold">{{ t('device.mounted') }}</h2>
                <p v-if="!devices.length" class="text-sm text-muted-foreground">
                    {{ t('device.empty') }}
                </p>
                <Link
                    v-for="device in devices"
                    :key="device.id"
                    :href="showDevice(device.id)"
                    class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm transition-colors hover:border-primary/50 hover:bg-accent/40"
                >
                    <span class="min-w-0">
                        <span class="block truncate font-medium">
                            {{ device.name }}
                        </span>
                        <span
                            class="block truncate text-xs text-muted-foreground"
                        >
                            {{ device.model.vendor }} {{ device.model.model }}
                        </span>
                    </span>
                    <span
                        class="shrink-0 font-mono text-xs text-muted-foreground"
                    >
                        {{ device.position_u }}U
                    </span>
                </Link>
            </div>
        </div>
    </div>

    <DeviceFormDialog
        v-model:open="adding"
        :site-id="rack.site.id"
        :models="models"
        :statuses="statuses"
        :faces="faces"
        :racks="[
            {
                id: rack.id,
                name: rack.name,
                kind: rack.kind,
                u_height: rack.u_height,
            },
        ]"
        :rack-id="rack.id"
        :position-u="addingAt"
        :face="addingFace"
    />
</template>
