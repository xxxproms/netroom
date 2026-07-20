<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Copy, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import VlanCopyDialog from '@/components/vlans/VlanCopyDialog.vue';
import VlanFormDialog from '@/components/vlans/VlanFormDialog.vue';
import { destroy, index as vlansIndex } from '@/routes/vlans';
import type { Vlan, VlanDomainSummary } from '@/types';

const { t } = useI18n();

defineProps<{
    vlans: Vlan[];
    domains: VlanDomainSummary[];
    selectedDomainId: number | null;
    can: { manage: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.vlans', href: vlansIndex() }],
    },
});

const creating = ref(false);
const copying = ref(false);
const editing = ref<Vlan | undefined>();

function switchDomain(event: Event): void {
    const value = Number((event.target as HTMLSelectElement).value);

    router.get(vlansIndex().url, { domain: value }, { preserveState: false });
}

function remove(vlan: Vlan): void {
    if (confirm(t('common.deleteConfirm'))) {
        router.delete(destroy(vlan.id).url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="t('vlan.title')" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader :title="t('vlan.title')">
            <template #actions>
                <select
                    v-if="domains.length > 1"
                    class="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                    :value="selectedDomainId ?? ''"
                    @change="switchDomain"
                >
                    <option
                        v-for="option in domains"
                        :key="option.id"
                        :value="option.id"
                    >
                        {{ option.name }}
                    </option>
                </select>

                <Button
                    v-if="can.manage && domains.length > 1"
                    size="sm"
                    variant="outline"
                    @click="copying = true"
                >
                    <Copy class="size-4" />
                    {{ t('vlan.copy') }}
                </Button>

                <Button
                    v-if="can.manage && selectedDomainId"
                    size="sm"
                    @click="creating = true"
                >
                    <Plus class="size-4" />
                    {{ t('vlan.new') }}
                </Button>
            </template>
        </PageHeader>

        <EmptyState v-if="!domains.length" :message="t('vlan.noDomains')" />

        <EmptyState v-else-if="!vlans.length" :message="t('vlan.empty')" />

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-xs text-muted-foreground">
                    <tr>
                        <th class="w-24 px-4 py-2.5 text-left font-medium">
                            {{ t('vlan.vid') }}
                        </th>
                        <th class="px-4 py-2.5 text-left font-medium">
                            {{ t('common.name') }}
                        </th>
                        <th class="px-4 py-2.5 text-left font-medium">
                            {{ t('common.description') }}
                        </th>
                        <th
                            v-if="can.manage"
                            class="px-4 py-2.5 text-right font-medium"
                        >
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="vlan in vlans" :key="vlan.id" class="border-t">
                        <td class="px-4 py-2.5">
                            <span class="flex items-center gap-2">
                                <span
                                    class="size-2.5 rounded-full"
                                    :style="{
                                        backgroundColor:
                                            vlan.color ?? 'var(--muted)',
                                    }"
                                />
                                <span class="font-mono tabular-nums">
                                    {{ vlan.vid }}
                                </span>
                            </span>
                        </td>
                        <td class="px-4 py-2.5 font-medium">{{ vlan.name }}</td>
                        <td class="px-4 py-2.5 text-muted-foreground">
                            {{ vlan.description }}
                        </td>
                        <td v-if="can.manage" class="px-2 py-2 text-right">
                            <Button
                                size="icon"
                                variant="ghost"
                                class="size-8"
                                @click="editing = vlan"
                            >
                                <Pencil class="size-4" />
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="size-8 text-destructive"
                                @click="remove(vlan)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <VlanFormDialog
        v-if="selectedDomainId"
        v-model:open="creating"
        :domain-id="selectedDomainId"
    />
    <VlanFormDialog
        v-if="editing && selectedDomainId"
        :open="true"
        :domain-id="selectedDomainId"
        :vlan="editing"
        @update:open="editing = undefined"
    />
    <VlanCopyDialog
        v-model:open="copying"
        :domains="domains"
        :selected-domain-id="selectedDomainId"
    />
</template>
