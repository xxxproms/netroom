<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowUpFromLine, Check, Pencil, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { update } from '@/routes/ports';
import type { Port } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    ports: Port[];
    editable: boolean;
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

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-xs text-muted-foreground">
                    <tr>
                        <th class="w-16 px-4 py-2.5 text-left font-medium">
                            {{ t('port.number') }}
                        </th>
                        <th class="w-28 px-4 py-2.5 text-left font-medium">
                            {{ t('model.media') }}
                        </th>
                        <th class="w-24 px-4 py-2.5 text-right font-medium">
                            {{ t('model.speed') }}
                        </th>
                        <th class="px-4 py-2.5 text-left font-medium">
                            {{ t('port.description') }}
                        </th>
                        <th
                            v-if="editable"
                            class="w-24 px-4 py-2.5 text-right font-medium"
                        >
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="port in group.ports"
                        :key="port.id"
                        class="border-t"
                        :class="{ 'opacity-50': !port.enabled }"
                    >
                        <td class="px-4 py-2 font-mono">{{ port.name }}</td>
                        <td class="px-4 py-2">
                            <Badge variant="outline" class="text-[11px]">
                                {{ t(`model.mediaKind.${port.media}`) }}
                            </Badge>
                        </td>
                        <td
                            class="px-4 py-2 text-right text-muted-foreground tabular-nums"
                        >
                            {{ port.speed_mbps ?? '—' }}
                        </td>
                        <td class="px-4 py-2">
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
                                    class="text-[11px]"
                                >
                                    {{ t('port.uplink') }}
                                </Badge>
                            </span>
                        </td>
                        <td v-if="editable" class="px-2 py-1.5 text-right">
                            <Button
                                size="icon"
                                variant="ghost"
                                class="size-8"
                                :title="t('port.uplink')"
                                @click="toggleUplink(port)"
                            >
                                <ArrowUpFromLine
                                    class="size-4"
                                    :class="{
                                        'text-primary': port.is_uplink,
                                    }"
                                />
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="size-8"
                                @click="edit(port)"
                            >
                                <Pencil class="size-4" />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
