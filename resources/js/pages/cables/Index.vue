<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EndLabel from '@/components/cables/EndLabel.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { destroy, index as cablesIndex } from '@/routes/cables';
import type { CableRow } from '@/types';

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

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-[15px]">
                <thead class="bg-muted/50 text-sm text-muted-foreground">
                    <tr>
                        <th class="w-28 px-4 py-3 text-left font-medium">
                            {{ t('cable.label') }}
                        </th>
                        <th class="px-4 py-3 text-left font-medium">
                            {{ t('cable.endA') }}
                        </th>
                        <th class="px-4 py-3 text-left font-medium">
                            {{ t('cable.endB') }}
                        </th>
                        <th class="w-32 px-4 py-3 text-left font-medium">
                            {{ t('cable.media') }}
                        </th>
                        <th class="w-24 px-4 py-3 text-right font-medium">
                            {{ t('cable.lengthCm') }}
                        </th>
                        <th
                            v-if="can.update"
                            class="w-16 px-4 py-3 text-right font-medium"
                        >
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="cable in found"
                        :key="cable.id"
                        class="border-t"
                        :class="{
                            'opacity-60': cable.status !== 'connected',
                        }"
                    >
                        <td class="px-4 py-2.5 font-mono">
                            {{ cable.label ?? '—' }}
                        </td>
                        <td class="px-4 py-2">
                            <EndLabel :end="cable.a" />
                        </td>
                        <td class="px-4 py-2">
                            <EndLabel :end="cable.b" />
                        </td>
                        <td class="px-4 py-2">
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
                        </td>
                        <td
                            class="px-4 py-2.5 text-right text-muted-foreground tabular-nums"
                        >
                            {{ cable.length_cm ?? '—' }}
                        </td>
                        <td v-if="can.update" class="px-2 py-1.5 text-right">
                            <Button
                                size="icon"
                                variant="ghost"
                                class="size-8 text-destructive"
                                :title="t('cable.disconnect')"
                                @click="remove(cable)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
