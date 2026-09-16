<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
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
import { targets } from '@/routes/cables';

type PortOption = {
    id: number;
    name: string;
    role: string;
    media: string;
    description: string | null;
    taken: boolean;
};

type OutletOption = {
    id: number;
    label: string;
    media: string;
    taken: boolean;
};

const { t } = useI18n();

const props = defineProps<{
    /** The near end the uplink starts from — fixed, shown for context. */
    fromLabel: string;
    sites: { id: number; name: string }[];
    defaultSiteId: number | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    select: [end: { bType: 'port' | 'outlet'; bId: number; media: string }];
}>();

const side = ref<'device' | 'workplace'>('device');
const siteId = ref<number | null>(null);
const ownerId = ref<number | null>(null);
const targetId = ref<number | null>(null);
const owners = ref<{ id: number; name: string; person?: string | null }[]>([]);
const ports = ref<PortOption[]>([]);
const outlets = ref<OutletOption[]>([]);

async function load(url: string): Promise<unknown> {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    return response.json();
}

async function loadOwners(): Promise<void> {
    ownerId.value = null;
    targetId.value = null;
    ports.value = [];
    outlets.value = [];

    if (siteId.value === null) {
        owners.value = [];

        return;
    }

    const scope = side.value === 'device' ? 'devices' : 'workplaces';

    owners.value = (await load(
        `${targets().url}?scope=${scope}&site=${siteId.value}`,
    )) as { id: number; name: string }[];
}

async function loadTargets(): Promise<void> {
    targetId.value = null;

    if (ownerId.value === null) {
        return;
    }

    if (side.value === 'device') {
        ports.value = (await load(
            `${targets().url}?scope=ports&device=${ownerId.value}`,
        )) as PortOption[];

        return;
    }

    outlets.value = (await load(
        `${targets().url}?scope=outlets&workplace=${ownerId.value}`,
    )) as OutletOption[];
}

function reset(): void {
    side.value = 'device';
    siteId.value = props.defaultSiteId;
    void loadOwners();
}

onMounted(() => {
    if (open.value) {
        reset();
    }
});

watch(open, (isOpen) => {
    if (isOpen) {
        reset();
    }
});

watch(side, () => void loadOwners());
watch(siteId, () => void loadOwners());
watch(ownerId, () => void loadTargets());

/** The chosen far end, with the media the parent needs to infer the cord. */
const chosen = computed<{ media: string } | null>(() => {
    if (targetId.value === null) {
        return null;
    }

    const option =
        side.value === 'device'
            ? ports.value.find((p) => p.id === targetId.value)
            : outlets.value.find((o) => o.id === targetId.value);

    return option ?? null;
});

function confirm(): void {
    if (targetId.value === null || chosen.value === null) {
        return;
    }

    emit('select', {
        bType: side.value === 'device' ? 'port' : 'outlet',
        bId: targetId.value,
        media: chosen.value.media,
    });

    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('patch.newUplink') }}</DialogTitle>
                <DialogDescription>
                    {{ t('cable.fromHint', { end: fromLabel }) }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <FormField id="uplink-side" :label="t('cable.otherEnd')">
                    <div class="flex gap-2">
                        <Button
                            :variant="side === 'device' ? 'default' : 'outline'"
                            size="sm"
                            class="flex-1"
                            @click="side = 'device'"
                        >
                            {{ t('cable.toDevice') }}
                        </Button>
                        <Button
                            :variant="
                                side === 'workplace' ? 'default' : 'outline'
                            "
                            size="sm"
                            class="flex-1"
                            @click="side = 'workplace'"
                        >
                            {{ t('cable.toWorkplace') }}
                        </Button>
                    </div>
                </FormField>

                <FormField id="uplink-site" :label="t('nav.sites')">
                    <select
                        id="uplink-site"
                        v-model="siteId"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    >
                        <option :value="null">{{ t('common.choose') }}</option>
                        <option
                            v-for="site in sites"
                            :key="site.id"
                            :value="site.id"
                        >
                            {{ site.name }}
                        </option>
                    </select>
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="uplink-owner"
                        :label="
                            side === 'device'
                                ? t('device.one')
                                : t('workplace.one')
                        "
                    >
                        <select
                            id="uplink-owner"
                            v-model="ownerId"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option :value="null">
                                {{ t('common.choose') }}
                            </option>
                            <option
                                v-for="owner in owners"
                                :key="owner.id"
                                :value="owner.id"
                            >
                                {{ owner.name }}
                            </option>
                        </select>
                    </FormField>

                    <FormField
                        id="uplink-target"
                        :label="
                            side === 'device'
                                ? t('port.number')
                                : t('outlet.one')
                        "
                    >
                        <select
                            id="uplink-target"
                            v-model="targetId"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option :value="null">
                                {{ t('common.choose') }}
                            </option>
                            <template v-if="side === 'device'">
                                <option
                                    v-for="port in ports"
                                    :key="port.id"
                                    :value="port.id"
                                    :disabled="port.taken"
                                >
                                    {{ port.name }} ·
                                    {{ t(`model.roleKind.${port.role}`) }}
                                    {{
                                        port.taken
                                            ? `— ${t('cable.taken')}`
                                            : ''
                                    }}
                                </option>
                            </template>
                            <template v-else>
                                <option
                                    v-for="outlet in outlets"
                                    :key="outlet.id"
                                    :value="outlet.id"
                                    :disabled="outlet.taken"
                                >
                                    {{ outlet.label }}
                                    {{
                                        outlet.taken
                                            ? `— ${t('cable.taken')}`
                                            : ''
                                    }}
                                </option>
                            </template>
                        </select>
                    </FormField>
                </div>
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="open = false">
                    {{ t('common.cancel') }}
                </Button>
                <Button :disabled="targetId === null" @click="confirm">
                    {{ t('cable.connect') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
