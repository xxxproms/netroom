<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ColorPicker from '@/components/ColorPicker.vue';
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
import { store, update } from '@/routes/devices';
import type { Device, DeviceModelSummary, Rack } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    siteId: number;
    models: DeviceModelSummary[];
    statuses: string[];
    faces: string[];
    racks?: Rack[];
    rackId?: number;
    positionU?: number;
    face?: string;
    device?: Device;
}>();

const open = defineModel<boolean>('open', { required: true });

const action = () =>
    props.device ? update.form(props.device.id) : store.form();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ device ? t('device.edit') : t('device.new') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('device.portsHint') }}
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="action()"
                class="grid gap-4"
                :options="{ preserveScroll: true }"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <input type="hidden" name="site_id" :value="siteId" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="device-name"
                        :label="t('common.name')"
                        :error="errors.name"
                    >
                        <Input
                            id="device-name"
                            name="name"
                            :default-value="device?.name"
                            required
                            autofocus
                        />
                    </FormField>

                    <FormField
                        id="device-model"
                        :label="t('device.model')"
                        :error="errors.device_model_id"
                    >
                        <select
                            id="device-model"
                            name="device_model_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                            required
                        >
                            <option
                                v-for="model in models"
                                :key="model.id"
                                :value="model.id"
                                :selected="device?.device_model_id === model.id"
                            >
                                {{ model.vendor }} {{ model.model }}
                            </option>
                        </select>
                    </FormField>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <FormField
                        id="device-rack"
                        :label="t('rack.one')"
                        :error="errors.rack_id"
                    >
                        <select
                            id="device-rack"
                            name="rack_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option value="">{{ t('device.noRack') }}</option>
                            <option
                                v-for="rack in racks ?? []"
                                :key="rack.id"
                                :value="rack.id"
                                :selected="
                                    (device?.rack_id ?? rackId) === rack.id
                                "
                            >
                                {{ rack.name }}
                            </option>
                        </select>
                    </FormField>

                    <FormField
                        id="device-position"
                        :label="t('device.position')"
                        :error="errors.position_u"
                    >
                        <Input
                            id="device-position"
                            name="position_u"
                            type="number"
                            min="1"
                            :default-value="device?.position_u ?? positionU"
                        />
                    </FormField>

                    <FormField
                        id="device-face"
                        :label="t('device.face')"
                        :error="errors.face"
                    >
                        <select
                            id="device-face"
                            name="face"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option
                                v-for="value in faces"
                                :key="value"
                                :value="value"
                                :selected="
                                    (device?.face ?? face ?? 'front') === value
                                "
                            >
                                {{ t(`rack.face.${value}`) }}
                            </option>
                        </select>
                    </FormField>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="device-ip"
                        :label="t('device.mgmtIp')"
                        :error="errors.mgmt_ip"
                    >
                        <Input
                            id="device-ip"
                            name="mgmt_ip"
                            class="font-mono"
                            placeholder="10.40.0.100"
                            :default-value="device?.mgmt_ip ?? ''"
                        />
                    </FormField>

                    <FormField
                        id="device-status"
                        :label="t('common.status')"
                        :error="errors.status"
                    >
                        <select
                            id="device-status"
                            name="status"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option
                                v-for="value in statuses"
                                :key="value"
                                :value="value"
                                :selected="
                                    (device?.status ?? 'active') === value
                                "
                            >
                                {{ t(`device.statusKind.${value}`) }}
                            </option>
                        </select>
                    </FormField>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="device-serial"
                        :label="t('device.serial')"
                        :error="errors.serial"
                    >
                        <Input
                            id="device-serial"
                            name="serial"
                            :default-value="device?.serial ?? ''"
                        />
                    </FormField>

                    <FormField
                        id="device-url"
                        :label="t('device.mgmtUrl')"
                        :error="errors.mgmt_url"
                    >
                        <Input
                            id="device-url"
                            name="mgmt_url"
                            placeholder="http://10.40.0.100/"
                            :default-value="device?.mgmt_url ?? ''"
                        />
                    </FormField>
                </div>

                <FormField
                    id="device-color"
                    :label="t('device.color')"
                    :hint="t('device.colorHint')"
                    :error="errors.color"
                >
                    <ColorPicker name="color" :model-value="device?.color" />
                </FormField>

                <FormField
                    id="device-notes"
                    :label="t('common.notes')"
                    :error="errors.notes"
                >
                    <textarea
                        id="device-notes"
                        name="notes"
                        rows="2"
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        :value="device?.notes ?? ''"
                    ></textarea>
                </FormField>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="open = false">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{ t('common.save') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
