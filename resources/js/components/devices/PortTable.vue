<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ArrowUpFromLine,
    Check,
    Pencil,
    Plug,
    Route as RouteIcon,
    Unplug,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CableFormDialog from '@/components/cables/CableFormDialog.vue';
import EndLabel from '@/components/cables/EndLabel.vue';
import TraceDialog from '@/components/cables/TraceDialog.vue';
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
import { destroy as removeCable } from '@/routes/cables';
import { trace, update } from '@/routes/ports';
import type { Port } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    ports: Port[];
    editable: boolean;
    siteId: number;
    canCable: boolean;
    cable: { media: string[]; statuses: string[]; strands: number[] };
}>();

/** Patch panels list their front and rear sides separately. */
const groups = computed(() => {
    const roles = [...new Set(props.ports.map((port) => port.role))];

    return roles.map((role) => ({
        role,
        ports: props.ports.filter((port) => port.role === role),
    }));
});

const editingId = ref<number | null>(null);
const draft = ref('');

const connecting = ref<Port | null>(null);
const tracing = ref<Port | null>(null);

function disconnect(port: Port): void {
    if (port.link && confirm(t('cable.disconnectConfirm'))) {
        router.delete(removeCable(port.link.cable.id).url, {
            preserveScroll: true,
        });
    }
}

function edit(port: Port): void {
    editingId.value = port.id;
    draft.value = port.description ?? '';
}

function save(port: Port): void {
    router.patch(
        update(port.id).url,
        {
            description: draft.value,
            is_uplink: port.is_uplink,
            enabled: port.enabled,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                editingId.value = null;
            },
        },
    );
}

function toggleUplink(port: Port): void {
    router.patch(
        update(port.id).url,
        {
            description: port.description ?? '',
            is_uplink: !port.is_uplink,
            enabled: port.enabled,
        },
        { preserveScroll: true },
    );
}
</script>

<template>
    <section
        v-for="group in groups"
        :key="group.role"
        class="flex flex-col gap-2"
    >
        <h2 class="text-sm font-semibold">
            {{ t(`model.roleKind.${group.role}`) }}
            <span class="font-normal text-muted-foreground">
                · {{ group.ports.length }}
            </span>
        </h2>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead class="w-16">{{ t('port.number') }}</TableHead>
                    <TableHead class="w-28">{{ t('model.media') }}</TableHead>
                    <TableHead class="w-24 text-right">
                        {{ t('model.speed') }}
                    </TableHead>
                    <TableHead>{{ t('port.description') }}</TableHead>
                    <TableHead>{{ t('cable.connectedTo') }}</TableHead>
                    <TableHead class="w-36 text-right">
                        {{ t('common.actions') }}
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow
                    v-for="port in group.ports"
                    :key="port.id"
                    :class="{ 'opacity-50': !port.enabled }"
                >
                    <TableCell class="font-mono">{{ port.name }}</TableCell>
                    <TableCell>
                        <Badge variant="outline" class="text-xs">
                            {{ t(`model.mediaKind.${port.media}`) }}
                        </Badge>
                    </TableCell>
                    <TableCell
                        class="text-right text-muted-foreground tabular-nums"
                    >
                        {{ port.speed_mbps ?? '—' }}
                    </TableCell>
                    <TableCell>
                        <div
                            v-if="editingId === port.id"
                            class="flex items-center gap-1"
                        >
                            <Input
                                v-model="draft"
                                class="h-8"
                                autofocus
                                @keyup.enter="save(port)"
                                @keyup.escape="editingId = null"
                            />
                            <Button
                                size="icon"
                                variant="ghost"
                                class="size-8"
                                @click="save(port)"
                            >
                                <Check class="size-4" />
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="size-8"
                                @click="editingId = null"
                            >
                                <X class="size-4" />
                            </Button>
                        </div>
                        <span
                            v-else
                            class="flex items-center gap-2"
                            :class="{
                                'text-muted-foreground': !port.description,
                            }"
                        >
                            {{ port.description || t('port.free') }}
                            <Badge
                                v-if="port.is_uplink"
                                variant="secondary"
                                class="text-xs"
                            >
                                {{ t('port.uplink') }}
                            </Badge>
                        </span>
                    </TableCell>
                    <TableCell>
                        <EndLabel v-if="port.link?.far" :end="port.link.far" />
                        <span v-else class="text-sm text-muted-foreground">
                            {{ t('cable.notConnected') }}
                        </span>
                    </TableCell>
                    <TableCell class="py-1.5 text-right whitespace-nowrap">
                        <Button
                            v-if="port.link"
                            size="icon"
                            variant="ghost"
                            class="size-8"
                            :title="t('trace.title')"
                            @click="tracing = port"
                        >
                            <RouteIcon class="size-4" />
                        </Button>
                        <Button
                            v-if="canCable && !port.link"
                            size="icon"
                            variant="ghost"
                            class="size-8"
                            :title="t('cable.connect')"
                            @click="connecting = port"
                        >
                            <Plug class="size-4" />
                        </Button>
                        <Button
                            v-if="canCable && port.link"
                            size="icon"
                            variant="ghost"
                            class="size-8"
                            :title="t('cable.disconnect')"
                            @click="disconnect(port)"
                        >
                            <Unplug class="size-4" />
                        </Button>
                        <Button
                            v-if="editable"
                            size="icon"
                            variant="ghost"
                            class="size-8"
                            :title="t('port.uplink')"
                            @click="toggleUplink(port)"
                        >
                            <ArrowUpFromLine
                                class="size-4"
                                :class="{ 'text-primary': port.is_uplink }"
                            />
                        </Button>
                        <Button
                            v-if="editable"
                            size="icon"
                            variant="ghost"
                            class="size-8"
                            :title="t('common.edit')"
                            @click="edit(port)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </section>

    <CableFormDialog
        v-if="connecting"
        :key="connecting.id"
        :open="true"
        :from-type="'port'"
        :from-id="connecting.id"
        :from-label="connecting.name"
        :site-id="siteId"
        :media="cable.media"
        :statuses="cable.statuses"
        :strands="cable.strands"
        @update:open="connecting = null"
    />

    <TraceDialog
        v-if="tracing"
        :key="`trace-${tracing.id}`"
        :open="true"
        :url="trace(tracing.id).url"
        :title="tracing.name"
        @update:open="tracing = null"
    />
</template>
