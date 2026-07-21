<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import {
    Pencil,
    Plug,
    Plus,
    Route as RouteIcon,
    Trash2,
    Unplug,
} from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CableFormDialog from '@/components/cables/CableFormDialog.vue';
import EndLabel from '@/components/cables/EndLabel.vue';
import TraceDialog from '@/components/cables/TraceDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import WorkplaceFormDialog from '@/components/workplaces/WorkplaceFormDialog.vue';
import { destroy as removeCable } from '@/routes/cables';
import {
    destroy as removeOutlet,
    store as storeOutlet,
    trace,
} from '@/routes/outlets';
import { destroy, index as workplacesIndex } from '@/routes/workplaces';
import type { Outlet, Room, Workplace } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    workplace: Workplace;
    rooms: Room[];
    outletMedia: string[];
    cable: { media: string[]; statuses: string[]; strands: number[] };
    can: { update: boolean; delete: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.workplaces', href: workplacesIndex() }],
    },
});

const editing = ref(false);
const adding = ref(false);
const connecting = ref<Outlet | null>(null);
const tracing = ref<Outlet | null>(null);

function remove(): void {
    if (confirm(t('common.deleteConfirm'))) {
        router.delete(destroy(props.workplace.id).url);
    }
}

function removeSocket(outlet: Outlet): void {
    if (confirm(t('common.deleteConfirm'))) {
        router.delete(removeOutlet(outlet.id).url, { preserveScroll: true });
    }
}

function disconnect(outlet: Outlet): void {
    if (outlet.link && confirm(t('cable.disconnectConfirm'))) {
        router.delete(removeCable(outlet.link.cable.id).url, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head :title="workplace.name" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            :title="workplace.name"
            :description="workplace.person ?? t('workplace.noPerson')"
        >
            <template #actions>
                <Badge variant="outline" class="font-mono">
                    {{ workplace.site?.code }}
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

        <dl class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border p-4">
                <dt class="text-xs text-muted-foreground">
                    {{ t('room.one') }}
                </dt>
                <dd class="mt-1 text-sm">
                    {{ workplace.room?.name ?? t('workplace.noRoom') }}
                </dd>
            </div>
            <div class="rounded-xl border p-4">
                <dt class="text-xs text-muted-foreground">
                    {{ t('room.floor') }}
                </dt>
                <dd class="mt-1 text-sm">{{ workplace.floor ?? '—' }}</dd>
            </div>
            <div class="rounded-xl border p-4">
                <dt class="text-xs text-muted-foreground">
                    {{ t('outlet.title') }}
                </dt>
                <dd class="mt-1 text-sm tabular-nums">
                    {{ workplace.outlets?.length ?? 0 }}
                </dd>
            </div>
        </dl>

        <p v-if="workplace.notes" class="text-sm whitespace-pre-line">
            {{ workplace.notes }}
        </p>

        <section class="flex flex-col gap-2">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold">{{ t('outlet.title') }}</h2>
                <Button
                    v-if="can.update"
                    size="sm"
                    variant="outline"
                    @click="adding = !adding"
                >
                    <Plus class="size-4" />
                    {{ t('outlet.new') }}
                </Button>
            </div>

            <!-- Sockets are added inline: a workplace rarely has more than two. -->
            <Form
                v-if="adding"
                v-bind="storeOutlet.form(workplace.id)"
                class="flex flex-wrap items-end gap-2 rounded-xl border p-3"
                :options="{ preserveScroll: true }"
                @success="adding = false"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-1.5">
                    <label class="text-sm" for="outlet-label">
                        {{ t('outlet.label') }}
                    </label>
                    <Input
                        id="outlet-label"
                        name="label"
                        class="h-9 w-40 font-mono"
                        placeholder="204-1"
                        required
                        autofocus
                    />
                </div>
                <div class="grid gap-1.5">
                    <label class="text-sm" for="outlet-media">
                        {{ t('model.media') }}
                    </label>
                    <select
                        id="outlet-media"
                        name="media"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    >
                        <option
                            v-for="value in outletMedia"
                            :key="value"
                            :value="value"
                        >
                            {{ t(`model.mediaKind.${value}`) }}
                        </option>
                    </select>
                </div>
                <Button type="submit" size="sm" :disabled="processing">
                    {{ t('common.save') }}
                </Button>
                <p v-if="errors.label" class="text-sm text-destructive">
                    {{ errors.label }}
                </p>
            </Form>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-[15px]">
                    <thead class="bg-muted/50 text-sm text-muted-foreground">
                        <tr>
                            <th class="w-32 px-4 py-3 text-left font-medium">
                                {{ t('outlet.label') }}
                            </th>
                            <th class="w-28 px-4 py-3 text-left font-medium">
                                {{ t('model.media') }}
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                {{ t('cable.connectedTo') }}
                            </th>
                            <th class="w-36 px-4 py-3 text-right font-medium">
                                {{ t('common.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="outlet in workplace.outlets ?? []"
                            :key="outlet.id"
                            class="border-t"
                        >
                            <td class="px-4 py-2.5 font-mono">
                                {{ outlet.label }}
                            </td>
                            <td class="px-4 py-2">
                                <Badge variant="outline" class="text-xs">
                                    {{ t(`model.mediaKind.${outlet.media}`) }}
                                </Badge>
                            </td>
                            <td class="px-4 py-2">
                                <EndLabel
                                    v-if="outlet.link?.far"
                                    :end="outlet.link.far"
                                />
                                <span
                                    v-else
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ t('cable.notConnected') }}
                                </span>
                            </td>
                            <td
                                class="px-2 py-1.5 text-right whitespace-nowrap"
                            >
                                <Button
                                    v-if="outlet.link"
                                    size="icon"
                                    variant="ghost"
                                    class="size-8"
                                    :title="t('trace.title')"
                                    @click="tracing = outlet"
                                >
                                    <RouteIcon class="size-4" />
                                </Button>
                                <Button
                                    v-if="can.update && !outlet.link"
                                    size="icon"
                                    variant="ghost"
                                    class="size-8"
                                    :title="t('cable.connect')"
                                    @click="connecting = outlet"
                                >
                                    <Plug class="size-4" />
                                </Button>
                                <Button
                                    v-if="can.update && outlet.link"
                                    size="icon"
                                    variant="ghost"
                                    class="size-8"
                                    :title="t('cable.disconnect')"
                                    @click="disconnect(outlet)"
                                >
                                    <Unplug class="size-4" />
                                </Button>
                                <Button
                                    v-if="can.update"
                                    size="icon"
                                    variant="ghost"
                                    class="size-8 text-destructive"
                                    :title="t('common.delete')"
                                    @click="removeSocket(outlet)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!workplace.outlets?.length">
                            <td
                                colspan="4"
                                class="px-4 py-6 text-center text-sm text-muted-foreground"
                            >
                                {{ t('outlet.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <WorkplaceFormDialog
        v-model:open="editing"
        :site-id="workplace.site_id!"
        :rooms="rooms"
        :workplace="workplace"
    />

    <CableFormDialog
        v-if="connecting"
        :key="connecting.id"
        :open="true"
        :from-type="'outlet'"
        :from-id="connecting.id"
        :from-label="connecting.label"
        :site-id="workplace.site_id!"
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
        :title="tracing.label"
        @update:open="tracing = null"
    />
</template>
