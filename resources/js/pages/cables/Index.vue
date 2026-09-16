<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EndLabel from '@/components/cables/EndLabel.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusChip from '@/components/StatusChip.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { destroy, index as cablesIndex } from '@/routes/cables';
import type { CableRow } from '@/types';

/** Green when live, amber while only planned, grey once retired. */
const cableTone: Record<string, 'green' | 'amber' | 'gray'> = {
    connected: 'green',
    planned: 'amber',
    decommissioned: 'gray',
};

const { t } = useI18n();

const props = defineProps<{
    cables: CableRow[];
    media: string[];
    statuses: string[];
    strands: number[];
    can: { update: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.cables', href: cablesIndex() }],
    },
});

const search = ref('');

const endText = (end: CableRow['a']): string =>
    end.kind === 'port'
        ? `${end.device.name} ${end.name} ${end.description ?? ''}`
        : `${end.workplace.name} ${end.label} ${end.workplace.person ?? ''}`;

/** One box searches labels, devices, sockets and people alike. */
const found = computed(() => {
    const needle = search.value.trim().toLowerCase();

    if (!needle) {
        return props.cables;
    }

    return props.cables.filter((cable) =>
        `${cable.label ?? ''} ${endText(cable.a)} ${endText(cable.b)}`
            .toLowerCase()
            .includes(needle),
    );
});

function remove(cable: CableRow): void {
    if (confirm(t('cable.disconnectConfirm'))) {
        router.delete(destroy(cable.id).url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="t('cable.title')" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            :title="t('cable.title')"
            :description="t('cable.journalHint')"
        >
            <template #actions>
                <Input
                    v-model="search"
                    class="h-9 w-64"
                    :placeholder="t('cable.search')"
                />
            </template>
        </PageHeader>

        <EmptyState v-if="!cables.length" :message="t('cable.empty')" />
        <EmptyState
            v-else-if="!found.length"
            :message="t('common.nothingFound')"
        />

        <Table v-else>
            <TableHeader>
                <TableRow>
                    <TableHead class="w-28">{{ t('cable.label') }}</TableHead>
                    <TableHead>{{ t('cable.endA') }}</TableHead>
                    <TableHead>{{ t('cable.endB') }}</TableHead>
                    <TableHead class="w-32">{{ t('cable.media') }}</TableHead>
                    <TableHead class="w-28">{{ t('common.status') }}</TableHead>
                    <TableHead class="w-24 text-right">
                        {{ t('cable.lengthCm') }}
                    </TableHead>
                    <TableHead v-if="can.update" class="w-16 text-right">
                        {{ t('common.actions') }}
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow
                    v-for="cable in found"
                    :key="cable.id"
                    :class="{ 'opacity-60': cable.status !== 'connected' }"
                >
                    <TableCell class="font-mono">
                        {{ cable.label ?? '—' }}
                    </TableCell>
                    <TableCell><EndLabel :end="cable.a" /></TableCell>
                    <TableCell><EndLabel :end="cable.b" /></TableCell>
                    <TableCell>
                        <Badge variant="outline" class="text-xs">
                            {{ t(`cable.mediaKind.${cable.media}`) }}
                            <template v-if="cable.strands">
                                ·
                                {{
                                    t('cable.strandCount', {
                                        count: cable.strands,
                                    })
                                }}
                            </template>
                        </Badge>
                    </TableCell>
                    <TableCell>
                        <StatusChip :tone="cableTone[cable.status]">
                            {{ t(`cable.statusKind.${cable.status}`) }}
                        </StatusChip>
                    </TableCell>
                    <TableCell
                        class="text-right text-muted-foreground tabular-nums"
                    >
                        {{ cable.length_cm ?? '—' }}
                    </TableCell>
                    <TableCell v-if="can.update" class="py-1.5 text-right">
                        <Button
                            size="icon"
                            variant="ghost"
                            class="size-8 text-destructive"
                            :title="t('cable.disconnect')"
                            @click="remove(cable)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
