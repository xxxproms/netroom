<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import DeviceModelFormDialog from '@/components/device-models/DeviceModelFormDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { destroy, index as deviceModels } from '@/routes/device-models';
import type { DeviceModel } from '@/types';

const { t } = useI18n();

defineProps<{
    models: DeviceModel[];
    kinds: string[];
    media: string[];
    roles: string[];
    can: { manage: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.models', href: deviceModels() }],
    },
});

const creating = ref(false);
const editing = ref<DeviceModel | undefined>();

function remove(model: DeviceModel): void {
    if (confirm(t('common.deleteConfirm'))) {
        router.delete(destroy(model.id).url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="t('model.title')" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader :title="t('model.title')">
            <template #actions>
                <Button v-if="can.manage" size="sm" @click="creating = true">
                    <Plus class="size-4" />
                    {{ t('model.new') }}
                </Button>
            </template>
        </PageHeader>

        <EmptyState v-if="!models.length" :message="t('model.empty')" />

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-medium">
                            {{ t('model.vendor') }}
                        </th>
                        <th class="px-4 py-2.5 text-left font-medium">
                            {{ t('model.model') }}
                        </th>
                        <th class="px-4 py-2.5 text-left font-medium">
                            {{ t('common.type') }}
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            {{ t('model.ports') }}
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">U</th>
                        <th
                            v-if="can.manage"
                            class="px-4 py-2.5 text-right font-medium"
                        >
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="model in models"
                        :key="model.id"
                        class="border-t"
                    >
                        <td class="px-4 py-2.5 text-muted-foreground">
                            {{ model.vendor }}
                        </td>
                        <td class="px-4 py-2.5 font-medium">
                            {{ model.model }}
                        </td>
                        <td class="px-4 py-2.5">
                            <Badge variant="secondary">
                                {{ t(`model.kind.${model.kind}`) }}
                            </Badge>
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ model.port_count }}
                        </td>
                        <td
                            class="px-4 py-2.5 text-right text-muted-foreground tabular-nums"
                        >
                            {{ model.u_height }}
                        </td>
                        <td v-if="can.manage" class="px-2 py-2 text-right">
                            <Button
                                size="icon"
                                variant="ghost"
                                class="size-8"
                                @click="editing = model"
                            >
                                <Pencil class="size-4" />
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="size-8 text-destructive"
                                @click="remove(model)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <DeviceModelFormDialog
        v-model:open="creating"
        :kinds="kinds"
        :media="media"
        :roles="roles"
    />
    <DeviceModelFormDialog
        v-if="editing"
        :open="true"
        :kinds="kinds"
        :media="media"
        :roles="roles"
        :model="editing"
        @update:open="editing = undefined"
    />
</template>
