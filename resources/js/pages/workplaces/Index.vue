<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { MapPin, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import WorkplaceFormDialog from '@/components/workplaces/WorkplaceFormDialog.vue';
import { index as workplacesIndex, show } from '@/routes/workplaces';
import type { Room, SiteSummary, Workplace } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    workplaces: Workplace[];
    sites: SiteSummary[];
    rooms: Room[];
    outletMedia: string[];
    can: { create: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.workplaces', href: workplacesIndex() }],
    },
});

const creating = ref(false);
const search = ref('');

/** The list gets long, and people look for a person as often as a room. */
const found = computed(() => {
    const needle = search.value.trim().toLowerCase();

    if (!needle) {
        return props.workplaces;
    }

    return props.workplaces.filter((workplace) =>
        [workplace.name, workplace.person, workplace.room?.name]
            .filter(Boolean)
            .some((value) => value!.toLowerCase().includes(needle)),
    );
});
</script>

<template>
    <Head :title="t('workplace.title')" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader :title="t('workplace.title')">
            <template #actions>
                <Input
                    v-model="search"
                    class="h-9 w-56"
                    :placeholder="t('workplace.search')"
                />
                <Button v-if="can.create" size="sm" @click="creating = true">
                    <Plus class="size-4" />
                    {{ t('workplace.new') }}
                </Button>
            </template>
        </PageHeader>

        <EmptyState v-if="!workplaces.length" :message="t('workplace.empty')" />

        <EmptyState
            v-else-if="!found.length"
            :message="t('common.nothingFound')"
        />

        <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="workplace in found"
                :key="workplace.id"
                :href="show(workplace.id)"
                class="flex items-center gap-3 rounded-xl border p-4 transition-colors hover:border-primary/50 hover:bg-accent/40"
            >
                <span
                    class="flex size-9 items-center justify-center rounded-lg bg-muted"
                >
                    <MapPin class="size-4.5 text-muted-foreground" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ workplace.name }}</p>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ workplace.person ?? t('workplace.noPerson') }}
                        <template v-if="workplace.room">
                            · {{ workplace.room.name }}
                        </template>
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1">
                    <Badge variant="outline" class="font-mono text-xs">
                        {{ workplace.site?.code }}
                    </Badge>
                    <span class="text-xs text-muted-foreground">
                        {{ t('outlet.title') }}:
                        <span class="font-medium text-foreground">
                            {{ workplace.outlets_count }}
                        </span>
                    </span>
                </div>
            </Link>
        </div>
    </div>

    <WorkplaceFormDialog
        v-model:open="creating"
        :site-id="sites[0]?.id ?? 0"
        :sites="sites"
        :rooms="rooms"
    />
</template>
