<script setup lang="ts">
import { router } from '@inertiajs/vue3';
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
import { Input } from '@/components/ui/input';
import { store, targets } from '@/routes/cables';

type EndType = 'port' | 'outlet';

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
    /** The end the dialog was opened from — it is fixed. */
    fromType: EndType;
    fromId: number;
    fromLabel: string;
    siteId: number;
    media: string[];
    statuses: string[];
    strands: number[];
}>();

const open = defineModel<boolean>('open', { required: true });

const side = ref<'device' | 'workplace'>('device');
const ownerId = ref<number | null>(null);
const targetId = ref<number | null>(null);
const owners = ref<{ id: number; name: string; person?: string | null }[]>([]);
const ports = ref<PortOption[]>([]);
const outlets = ref<OutletOption[]>([]);
const errors = ref<Record<string, string>>({});
const saving = ref(false);

const form = ref({
    media: 'utp',
    strands: 2,
    label: '',
    length_cm: '',
    status: 'connected',
});

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

    const scope = side.value === 'device' ? 'devices' : 'workplaces';

    owners.value = (await load(
        `${targets().url}?scope=${scope}&site=${props.siteId}`,
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

// The dialog is mounted already open (keyed), so seed the owner list on mount;
// the watcher covers a reused instance being reopened.
onMounted(() => {
    if (open.value) {
        void loadOwners();
    }
});

watch(open, (isOpen) => {
    if (isOpen) {
        errors.value = {};
        void loadOwners();
    }
});

watch(side, () => void loadOwners());
watch(ownerId, () => void loadTargets());

const canSave = computed(() => targetId.value !== null && !saving.value);

function save(): void {
    saving.value = true;

    router.post(
        store().url,
        {
            a_type: props.fromType,
            a_id: props.fromId,
            b_type: side.value === 'device' ? 'port' : 'outlet',
            b_id: targetId.value,
            media: form.value.media,
            strands: form.value.media === 'fibre' ? form.value.strands : null,
            label: form.value.label || null,
            length_cm: form.value.length_cm
                ? Number(form.value.length_cm)
                : null,
            status: form.value.status,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
            onError: (received) => {
                errors.value = received as Record<string, string>;
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('cable.new') }}</DialogTitle>
                <DialogDescription>
                    {{ t('cable.fromHint', { end: fromLabel }) }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <FormField id="cable-side" :label="t('cable.otherEnd')">
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

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="cable-owner"
                        :label="
                            side === 'device'
                                ? t('device.one')
                                : t('workplace.one')
                        "
                    >
                        <select
                            id="cable-owner"
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
                        id="cable-target"
                        :label="
                            side === 'device'
                                ? t('port.number')
                                : t('outlet.one')
                        "
                        :error="errors.b_id"
                    >
                        <select
                            id="cable-target"
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

                <div class="grid gap-4 sm:grid-cols-3">
                    <FormField
                        id="cable-media"
                        :label="t('cable.media')"
                        :error="errors.media"
                    >
                        <select
                            id="cable-media"
                            v-model="form.media"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option
                                v-for="value in media"
                                :key="value"
                                :value="value"
                            >
                                {{ t(`cable.mediaKind.${value}`) }}
                            </option>
                        </select>
                    </FormField>

                    <!-- Only fibre is counted in strands. -->
                    <FormField
                        v-if="form.media === 'fibre'"
                        id="cable-strands"
                        :label="t('cable.strands')"
                        :error="errors.strands"
                    >
                        <select
                            id="cable-strands"
                            v-model.number="form.strands"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option
                                v-for="value in strands"
                                :key="value"
                                :value="value"
                            >
                                {{ t('cable.strandCount', { count: value }) }}
                            </option>
                        </select>
                    </FormField>

                    <FormField
                        id="cable-label"
                        :label="t('cable.label')"
                        :error="errors.label"
                    >
                        <Input
                            id="cable-label"
                            v-model="form.label"
                            class="font-mono"
                        />
                    </FormField>

                    <FormField
                        id="cable-length"
                        :label="t('cable.lengthCm')"
                        :error="errors.length_cm"
                    >
                        <Input
                            id="cable-length"
                            v-model="form.length_cm"
                            type="number"
                            min="1"
                        />
                    </FormField>
                </div>

                <p v-if="errors.a_id" class="text-sm text-destructive">
                    {{ errors.a_id }}
                </p>
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="open = false">
                    {{ t('common.cancel') }}
                </Button>
                <Button :disabled="!canSave" @click="save">
                    {{ t('cable.connect') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
