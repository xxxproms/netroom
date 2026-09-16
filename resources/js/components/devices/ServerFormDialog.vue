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
import { store } from '@/routes/servers';

defineProps<{
    siteId: number;
    rackId: number;
    statuses: string[];
    faces: string[];
    portMedia: string[];
    positionU?: number;
    face?: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('server.new') }}</DialogTitle>
                <DialogDescription>{{ t('server.hint') }}</DialogDescription>
            </DialogHeader>

            <Form
                v-bind="store.form()"
                class="grid gap-4"
                :options="{ preserveScroll: true }"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <input type="hidden" name="site_id" :value="siteId" />
                <input type="hidden" name="rack_id" :value="rackId" />

                <FormField
                    id="server-name"
                    :label="t('common.name')"
                    :error="errors.name"
                >
                    <Input
                        id="server-name"
                        name="name"
                        required
                        autofocus
                        placeholder="SRV-DB-01"
                    />
                </FormField>

                <div class="grid gap-4 sm:grid-cols-3">
                    <FormField
                        id="server-units"
                        :label="t('server.units')"
                        :error="errors.u_height"
                    >
                        <Input
                            id="server-units"
                            name="u_height"
                            type="number"
                            min="1"
                            max="10"
                            :default-value="1"
                        />
                    </FormField>

                    <FormField
                        id="server-ports"
                        :label="t('server.portCount')"
                        :error="errors.port_count"
                    >
                        <Input
                            id="server-ports"
                            name="port_count"
                            type="number"
                            min="1"
                            max="200"
                            :default-value="4"
                        />
                    </FormField>

                    <FormField
                        id="server-media"
                        :label="t('server.portMedia')"
                        :error="errors.port_media"
                    >
                        <select
                            id="server-media"
                            name="port_media"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option
                                v-for="value in portMedia"
                                :key="value"
                                :value="value"
                                :selected="value === 'rj45'"
                            >
                                {{ t(`model.mediaKind.${value}`) }}
                            </option>
                        </select>
                    </FormField>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <FormField
                        id="server-position"
                        :label="t('device.position')"
                        :error="errors.position_u"
                    >
                        <Input
                            id="server-position"
                            name="position_u"
                            type="number"
                            min="1"
                            :default-value="positionU"
                        />
                    </FormField>

                    <FormField
                        id="server-face"
                        :label="t('device.face')"
                        :error="errors.face"
                    >
                        <select
                            id="server-face"
                            name="face"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option
                                v-for="value in faces"
                                :key="value"
                                :value="value"
                                :selected="(face ?? 'front') === value"
                            >
                                {{ t(`rack.face.${value}`) }}
                            </option>
                        </select>
                    </FormField>

                    <FormField
                        id="server-status"
                        :label="t('common.status')"
                        :error="errors.status"
                    >
                        <select
                            id="server-status"
                            name="status"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option
                                v-for="value in statuses"
                                :key="value"
                                :value="value"
                                :selected="value === 'active'"
                            >
                                {{ t(`device.statusKind.${value}`) }}
                            </option>
                        </select>
                    </FormField>
                </div>

                <FormField
                    id="server-ip"
                    :label="t('device.mgmtIp')"
                    :error="errors.mgmt_ip"
                >
                    <Input
                        id="server-ip"
                        name="mgmt_ip"
                        class="font-mono"
                        placeholder="10.40.0.50"
                    />
                </FormField>

                <FormField
                    id="server-color"
                    :label="t('device.color')"
                    :hint="t('device.colorHint')"
                    :error="errors.color"
                >
                    <ColorPicker name="color" />
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
