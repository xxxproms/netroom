<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
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
import { store, update } from '@/routes/subnets';
import type { Subnet } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    domainId: number;
    vlans: { id: number; vid: number; name: string }[];
    subnet?: Subnet;
}>();

const open = defineModel<boolean>('open', { required: true });

const action = () =>
    props.subnet ? update.form(props.subnet.id) : store.form();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ subnet ? t('subnet.edit') : t('subnet.new') }}
                </DialogTitle>
                <DialogDescription>{{ t('subnet.hint') }}</DialogDescription>
            </DialogHeader>

            <Form
                v-bind="action()"
                class="grid gap-4"
                :options="{ preserveScroll: true }"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <input
                    type="hidden"
                    name="vlan_domain_id"
                    :value="subnet?.vlan_domain_id ?? domainId"
                />

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="subnet-cidr"
                        :label="t('subnet.cidr')"
                        :error="errors.cidr"
                    >
                        <Input
                            id="subnet-cidr"
                            name="cidr"
                            class="font-mono"
                            placeholder="10.40.0.0/24"
                            :default-value="subnet?.cidr"
                            required
                            autofocus
                        />
                    </FormField>

                    <FormField
                        id="subnet-gateway"
                        :label="t('subnet.gateway')"
                        :error="errors.gateway"
                    >
                        <Input
                            id="subnet-gateway"
                            name="gateway"
                            class="font-mono"
                            placeholder="10.40.0.1"
                            :default-value="subnet?.gateway ?? ''"
                        />
                    </FormField>
                </div>

                <FormField
                    id="subnet-name"
                    :label="t('common.name')"
                    :error="errors.name"
                >
                    <Input
                        id="subnet-name"
                        name="name"
                        :default-value="subnet?.name ?? ''"
                        :placeholder="t('subnet.namePlaceholder')"
                    />
                </FormField>

                <FormField
                    id="subnet-vlan"
                    :label="t('vlan.one')"
                    :error="errors.vlan_id"
                >
                    <select
                        id="subnet-vlan"
                        name="vlan_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    >
                        <option value="">{{ t('subnet.noVlan') }}</option>
                        <option
                            v-for="vlan in vlans"
                            :key="vlan.id"
                            :value="vlan.id"
                            :selected="subnet?.vlan_id === vlan.id"
                        >
                            {{ vlan.vid }} · {{ vlan.name }}
                        </option>
                    </select>
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
