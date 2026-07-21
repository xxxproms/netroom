<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Plus } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '@/components/EmptyState.vue';
import SubnetFormDialog from '@/components/ipam/SubnetFormDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as subnetsIndex, show } from '@/routes/subnets';
import type { SubnetSummary } from '@/types';

const { t } = useI18n();

defineProps<{
    subnets: SubnetSummary[];
    domains: { id: number; name: string }[];
    selectedDomainId: number | null;
    vlans: { id: number; vid: number; name: string }[];
    can: { manage: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.ipam', href: subnetsIndex() }],
    },
});

const creating = ref(false);

function pickDomain(id: number): void {
    router.get(subnetsIndex().url, { domain: id }, { preserveState: false });
}

/** Green up to two-thirds, amber to nearly full, red when it is packed. */
function barColor(percent: number): string {
    return percent >= 90
        ? 'bg-red-500'
        : percent >= 66
          ? 'bg-amber-500'
          : 'bg-emerald-500';
}
</script>

<template>
    <Head :title="t('subnet.title')" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader :title="t('subnet.title')" :description="t('subnet.listHint')">
            <template #actions>
                <select
                    v-if="domains.length > 1"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    :value="selectedDomainId ?? ''"
                    @change="pickDomain(Number(($event.target as HTMLSelectElement).value))"
                >
                    <option
                        v-for="domain in domains"
                        :key="domain.id"
                        :value="domain.id"
                    >
                        {{ domain.name }}
                    </option>
                </select>
                <Button
                    v-if="can.manage && selectedDomainId"
                    size="sm"
                    @click="creating = true"
                >
                    <Plus class="size-4" />
                    {{ t('subnet.new') }}
                </Button>
            </template>
        </PageHeader>

        <EmptyState v-if="!subnets.length" :message="t('subnet.empty')" />

        <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="subnet in subnets"
                :key="subnet.id"
                :href="show(subnet.id)"
                class="flex flex-col gap-3 rounded-xl border p-4 transition-colors hover:border-primary/50 hover:bg-accent/40"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-mono text-base font-semibold">
                            {{ subnet.cidr }}
                        </p>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ subnet.name ?? t('subnet.unnamed') }}
                        </p>
                    </div>
                    <Badge
                        v-if="subnet.conflicts > 0"
                        variant="outline"
                        class="shrink-0 border-red-500/40 text-red-600"
                    >
                        <AlertTriangle class="size-3.5" />
                        {{ subnet.conflicts }}
                    </Badge>
                    <Badge
                        v-else-if="subnet.vlan"
                        variant="secondary"
                        class="shrink-0 font-mono"
                    >
                        VLAN {{ subnet.vlan.vid }}
                    </Badge>
                </div>

                <div class="flex flex-col gap-1.5">
                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full transition-all"
                            :class="barColor(subnet.utilisation)"
                            :style="{ width: `${subnet.utilisation}%` }"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('subnet.usage', { used: subnet.used, total: subnet.capacity }) }}
                        · {{ subnet.utilisation }}%
                    </p>
                </div>
            </Link>
        </div>
    </div>

    <SubnetFormDialog
        v-if="selectedDomainId"
        v-model:open="creating"
        :domain-id="selectedDomainId"
        :vlans="vlans"
    />
</template>
