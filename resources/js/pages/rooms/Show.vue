<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Server, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import RackFormDialog from '@/components/racks/RackFormDialog.vue';
import RoomFormDialog from '@/components/rooms/RoomFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { destroy, index as rooms } from '@/routes/rooms';
import { show as showSite } from '@/routes/sites';
import type { Rack, Room } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    room: Room;
    kinds: string[];
    rackKinds: string[];
    can: { update: boolean; delete: boolean; createRack: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.rooms', href: rooms() }],
    },
});

const editing = ref(false);
const addingRack = ref(false);
const editingRack = ref<Rack | undefined>();

function remove(): void {
    if (confirm(t('common.deleteConfirm'))) {
        router.delete(destroy(props.room.id).url);
    }
}

function edit(rack: Rack): void {
    editingRack.value = rack;
}
</script>

<template>
    <Head :title="room.name" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            :title="room.name"
            :description="
                [
                    t(`room.kind.${room.kind}`),
                    room.floor ? `${t('room.floor')} ${room.floor}` : null,
                ]
                    .filter(Boolean)
                    .join(' · ')
            "
        >
            <template #actions>
                <Link :href="showSite(room.site!.id)">
                    <Badge variant="outline" class="font-mono">
                        {{ room.site?.code }}
                    </Badge>
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

        <p v-if="room.notes" class="text-sm whitespace-pre-line">
            {{ room.notes }}
        </p>

        <section class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold">{{ t('room.racks') }}</h2>
                <Button
                    v-if="can.createRack"
                    size="sm"
                    variant="outline"
                    @click="addingRack = true"
                >
                    <Plus class="size-4" />
                    {{ t('rack.new') }}
                </Button>
            </div>

            <EmptyState v-if="!room.racks?.length" :message="t('rack.empty')" />

            <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="rack in room.racks"
                    :key="rack.id"
                    class="flex items-center gap-3 rounded-xl border p-4"
                >
                    <span
                        class="flex size-9 items-center justify-center rounded-lg bg-muted"
                    >
                        <Server class="size-4.5 text-muted-foreground" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ rack.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ t(`rack.kind.${rack.kind}`) }} ·
                            {{ rack.u_height }}U
                        </p>
                    </div>
                    <Button
                        v-if="can.update"
                        size="icon"
                        variant="ghost"
                        class="size-8"
                        @click="edit(rack)"
                    >
                        <Pencil class="size-4" />
                    </Button>
                </div>
            </div>
        </section>
    </div>

    <RoomFormDialog
        v-model:open="editing"
        :site-id="room.site_id!"
        :room="room"
        :kinds="kinds"
    />
    <RackFormDialog
        v-model:open="addingRack"
        :room-id="room.id"
        :kinds="rackKinds"
    />
    <RackFormDialog
        v-if="editingRack"
        :open="true"
        :room-id="room.id"
        :kinds="rackKinds"
        :rack="editingRack"
        @update:open="editingRack = undefined"
    />
</template>
