<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Save, Undo2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import VlanMatrix from '@/components/devices/VlanMatrix.vue';
import type { Membership, VlanChange } from '@/components/devices/VlanMatrix.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { index as devicesIndex, show as showDevice } from '@/routes/devices';
import { update as saveMatrix } from '@/routes/devices/vlans';
import type { Port, SiteSummary, Vlan } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    device: {
        id: number;
        name: string;
        model: string;
        site: SiteSummary;
    };
    ports: Port[];
    vlans: Vlan[];
    membership: Membership;
    can: { update: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.devices', href: devicesIndex() }],
    },
});

const changes = ref<VlanChange[]>([]);
const saving = ref(false);

const pending = computed(() => changes.value.length);

function save(): void {
    saving.value = true;

    router.put(
        saveMatrix(props.device.id).url,
        { changes: changes.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                changes.value = [];
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="`${device.name} — VLAN`" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            :title="`${device.name} — ${t('vlanMatrix.title')}`"
            :description="device.model"
        >
            <template #actions>
                <Link :href="showDevice(device.id).url">
                    <Button size="sm" variant="outline">
                        <ArrowLeft class="size-4" />
                        {{ t('vlanMatrix.backToDevice') }}
                    </Button>
                </Link>

                <template v-if="can.update">
                    <Button
                        size="sm"
                        variant="ghost"
                        :disabled="!pending || saving"
                        @click="changes = []"
                    >
                        <Undo2 class="size-4" />
                        {{ t('common.cancel') }}
                    </Button>
                    <Button size="sm" :disabled="!pending || saving" @click="save">
                        <Save class="size-4" />
                        {{
                            pending
                                ? t('vlanMatrix.saveCount', { count: pending })
                                : t('common.save')
                        }}
                    </Button>
                </template>
            </template>
        </PageHeader>

        <VlanMatrix
            v-model:changes="changes"
            :ports="ports"
            :vlans="vlans"
            :membership="membership"
            :editable="can.update"
        />
    </div>
</template>
