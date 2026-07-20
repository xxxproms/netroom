<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { DoorClosed, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import RoomFormDialog from '@/components/rooms/RoomFormDialog.vue';
import SiteFormDialog from '@/components/sites/SiteFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show as showRoom } from '@/routes/rooms';
import { destroy, index as sites } from '@/routes/sites';
import type { Site, VlanDomainSummary } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    site: Site;
    domains: VlanDomainSummary[];
    kinds: string[];
    can: { update: boolean; delete: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.sites', href: sites() }],
    },
});

const editing = ref(false);
const addingRoom = ref(false);

function remove(): void {
    if (confirm(t('common.deleteConfirm'))) {
        router.delete(destroy(props.site.id).url);
    }
}
</script>

<template>
    <Head :title="site.name" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            :title="site.name"
            :description="
                [t(`site.kind.${site.kind}`), site.city, site.address]
                    .filter(Boolean)
                    .join(' · ')
            "
        >
            <template #actions>
                <Badge variant="outline" class="font-mono">
                    {{ site.code }}
                </Badge>
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

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">
                    {{ t('site.domain') }}
                </p>
                <p class="mt-1 font-medium">{{ site.vlan_domain?.name }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    {{ t('vlanDomain.sitesCount') }}:
                    {{ site.vlan_domain?.sites_count }}
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">
                    {{ t('site.roomsCount') }}
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ site.rooms?.length ?? 0 }}
                </p>
            </div>
            <div v-if="site.notes" class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">
                    {{ t('common.notes') }}
                </p>
                <p class="mt-1 text-sm whitespace-pre-line">{{ site.notes }}</p>
            </div>
        </div>

        <section class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold">{{ t('site.rooms') }}</h2>
                <Button
                    v-if="can.update"
                    size="sm"
                    variant="outline"
                    @click="addingRoom = true"
                >
                    <Plus class="size-4" />
                    {{ t('room.new') }}
                </Button>
            </div>

            <EmptyState v-if="!site.rooms?.length" :message="t('room.empty')" />

            <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="room in site.rooms"
                    :key="room.id"
                    :href="showRoom(room.id)"
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
                    <span class="text-xs text-muted-foreground">
                        {{ t('room.racksCount') }}:
                        <span class="font-medium text-foreground">{{
                            room.racks_count
                        }}</span>
                    </span>
                </Link>
            </div>
        </section>
    </div>

    <SiteFormDialog
        v-model:open="editing"
        :site="site"
        :domains="domains"
        :kinds="kinds"
    />
    <RoomFormDialog v-model:open="addingRoom" :site-id="site.id" />
</template>
