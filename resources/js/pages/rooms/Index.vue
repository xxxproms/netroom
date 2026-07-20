<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { DoorClosed } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { index as roomsIndex, show } from '@/routes/rooms';
import type { Room, SiteSummary } from '@/types';

const { t } = useI18n();

defineProps<{
    rooms: Room[];
    sites: SiteSummary[];
    kinds: string[];
    can: { create: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.rooms', href: roomsIndex() }],
    },
});
</script>

<template>
    <Head :title="t('room.title')" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader :title="t('room.title')" />

        <EmptyState v-if="!rooms.length" :message="t('room.empty')" />

        <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="room in rooms"
                :key="room.id"
                :href="show(room.id)"
                class="flex items-center gap-3 rounded-xl border p-4 transition-colors hover:border-primary/50 hover:bg-accent/40"
            >
                <span
                    class="flex size-9 items-center justify-center rounded-lg bg-muted"
                >
                    <DoorClosed class="size-4.5 text-muted-foreground" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ room.name }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ t(`room.kind.${room.kind}`) }}
                        <template v-if="room.floor">
                            · {{ t('room.floor') }} {{ room.floor }}
                        </template>
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1">
                    <Badge variant="outline" class="font-mono text-xs">
                        {{ room.site?.code }}
                    </Badge>
                    <span class="text-xs text-muted-foreground">
                        {{ t('room.racksCount') }}:
                        <span class="font-medium text-foreground">{{
                            room.racks_count
                        }}</span>
                    </span>
                </div>
            </Link>
        </div>
    </div>
</template>
