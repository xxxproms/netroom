<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { store as createCable, targets } from '@/routes/cables';

type PortOption = {
    id: number;
    name: string;
    role: string;
    media: string;
    taken: boolean;
};

const { t } = useI18n();

const props = defineProps<{
    /** The two devices the user dragged between, with names for context. */
    from: { id: number; name: string } | null;
    to: { id: number; name: string } | null;
    /** How many cables the site already has — to keep cord colours rotating. */
    existingCount: number;
}>();

const open = defineModel<boolean>('open', { required: true });

const palette = [
    '#2563eb',
    '#16a34a',
    '#dc2626',
    '#d97706',
    '#7c3aed',
    '#0891b2',
    '#db2777',
    '#65a30d',
];

const fromPorts = ref<PortOption[]>([]);
const toPorts = ref<PortOption[]>([]);
const fromPort = ref<number | null>(null);
const toPort = ref<number | null>(null);
const error = ref<string | null>(null);

async function freePorts(deviceId: number): Promise<PortOption[]> {
    const response = await fetch(
        `${targets().url}?scope=ports&device=${deviceId}`,
        { headers: { Accept: 'application/json' } },
    );

    return (await response.json()) as PortOption[];
}

watch(open, async (isOpen) => {
    error.value = null;
    fromPort.value = null;
    toPort.value = null;
    fromPorts.value = [];
    toPorts.value = [];

    if (!isOpen || !props.from || !props.to) {
        return;
    }

    [fromPorts.value, toPorts.value] = await Promise.all([
        freePorts(props.from.id),
        freePorts(props.to.id),
    ]);
});

const mediaOf = (id: number | null, list: PortOption[]): string | null =>
    list.find((p) => p.id === id)?.media ?? null;

function connect(): void {
    if (fromPort.value === null || toPort.value === null) {
        return;
    }

    const a = mediaOf(fromPort.value, fromPorts.value);
    const b = mediaOf(toPort.value, toPorts.value);
    const media = a === 'rj45' && b === 'rj45' ? 'utp' : 'fibre';
    error.value = null;

    router.post(
        createCable().url,
        {
            a_type: 'port',
            a_id: fromPort.value,
            b_type: 'port',
            b_id: toPort.value,
            media,
            strands: media === 'fibre' ? 2 : null,
            color: palette[props.existingCount % palette.length],
            status: 'connected',
        },
        {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
            onError: (errors) => {
                error.value = Object.values(errors)[0] ?? null;
            },
        },
    );
}

const ready = computed(() => fromPort.value !== null && toPort.value !== null);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('map.linkDevices') }}</DialogTitle>
                <DialogDescription>
                    {{ from?.name }} → {{ to?.name }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField id="link-from" :label="from?.name ?? ''">
                    <select
                        id="link-from"
                        v-model="fromPort"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    >
                        <option :value="null">{{ t('common.choose') }}</option>
                        <option
                            v-for="port in fromPorts"
                            :key="port.id"
                            :value="port.id"
                            :disabled="port.taken"
                        >
                            {{ port.name }} ·
                            {{ t(`model.roleKind.${port.role}`) }}
                            {{ port.taken ? `— ${t('cable.taken')}` : '' }}
                        </option>
                    </select>
                </FormField>

                <FormField id="link-to" :label="to?.name ?? ''">
                    <select
                        id="link-to"
                        v-model="toPort"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    >
                        <option :value="null">{{ t('common.choose') }}</option>
                        <option
                            v-for="port in toPorts"
                            :key="port.id"
                            :value="port.id"
                            :disabled="port.taken"
                        >
                            {{ port.name }} ·
                            {{ t(`model.roleKind.${port.role}`) }}
                            {{ port.taken ? `— ${t('cable.taken')}` : '' }}
                        </option>
                    </select>
                </FormField>
            </div>

            <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

            <DialogFooter>
                <Button variant="ghost" @click="open = false">
                    {{ t('common.cancel') }}
                </Button>
                <Button :disabled="!ready" @click="connect">
                    {{ t('cable.connect') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
