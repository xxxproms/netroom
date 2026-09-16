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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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

        <Table v-else>
            <TableHeader>
                <TableRow>
                    <TableHead>{{ t('model.vendor') }}</TableHead>
                    <TableHead>{{ t('model.model') }}</TableHead>
                    <TableHead>{{ t('common.type') }}</TableHead>
                    <TableHead class="text-right">
                        {{ t('model.ports') }}
                    </TableHead>
                    <TableHead class="text-right">U</TableHead>
                    <TableHead v-if="can.manage" class="text-right">
                        {{ t('common.actions') }}
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="model in models" :key="model.id">
                    <TableCell class="text-muted-foreground">
                        {{ model.vendor }}
                    </TableCell>
                    <TableCell class="font-medium">{{ model.model }}</TableCell>
                    <TableCell>
                        <Badge variant="secondary">
                            {{ t(`model.kind.${model.kind}`) }}
                        </Badge>
                    </TableCell>
                    <TableCell class="text-right tabular-nums">
                        {{ model.port_count }}
                    </TableCell>
                    <TableCell
                        class="text-right text-muted-foreground tabular-nums"
                    >
                        {{ model.u_height }}
                    </TableCell>
                    <TableCell v-if="can.manage" class="text-right">
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
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
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
