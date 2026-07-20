<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { store, update } from '@/routes/vlans';
import type { Vlan } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    domainId: number;
    vlan?: Vlan;
}>();

const open = defineModel<boolean>('open', { required: true });

const action = () => (props.vlan ? update.form(props.vlan.id) : store.form());
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ vlan ? t('vlan.edit') : t('vlan.new') }}
                </DialogTitle>
            </DialogHeader>

            <Form
                v-bind="action()"
                class="grid gap-4"
                :options="{ preserveScroll: true }"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <input type="hidden" name="vlan_domain_id" :value="domainId" />

                <div class="grid gap-4 sm:grid-cols-[7rem_1fr]">
                    <FormField
                        id="vid"
                        :label="t('vlan.vid')"
                        :error="errors.vid"
                    >
                        <Input
                            id="vid"
                            name="vid"
                            type="number"
                            min="1"
                            max="4094"
                            class="font-mono"
                            :default-value="vlan?.vid"
                            required
                            autofocus
                        />
                    </FormField>

                    <FormField
                        id="vlan-name"
                        :label="t('common.name')"
                        :error="errors.name"
                    >
                        <Input
                            id="vlan-name"
                            name="name"
                            :default-value="vlan?.name"
                            required
                        />
                    </FormField>
                </div>

                <FormField
                    id="vlan-description"
                    :label="t('common.description')"
                    :error="errors.description"
                >
                    <Input
                        id="vlan-description"
                        name="description"
                        :default-value="vlan?.description ?? ''"
                    />
                </FormField>

                <FormField
                    id="vlan-color"
                    :label="t('common.color')"
                    :error="errors.color"
                >
                    <Input
                        id="vlan-color"
                        name="color"
                        type="color"
                        class="h-9 w-24 p-1"
                        :default-value="vlan?.color ?? '#64748b'"
                    />
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
