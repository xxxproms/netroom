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
import { copy } from '@/routes/vlans';
import type { VlanDomainSummary } from '@/types';

const { t } = useI18n();

defineProps<{
    domains: VlanDomainSummary[];
    selectedDomainId: number | null;
}>();

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('vlan.copy') }}</DialogTitle>
                <DialogDescription>{{ t('vlan.copyHint') }}</DialogDescription>
            </DialogHeader>

            <Form
                v-bind="copy.form()"
                class="grid gap-4"
                :options="{ preserveScroll: true }"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <FormField
                    id="from_domain_id"
                    :label="t('vlan.copyFrom')"
                    :error="errors.from_domain_id"
                >
                    <select
                        id="from_domain_id"
                        name="from_domain_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    >
                        <option
                            v-for="domain in domains"
                            :key="domain.id"
                            :value="domain.id"
                            :selected="domain.id === selectedDomainId"
                        >
                            {{ domain.name }}
                        </option>
                    </select>
                </FormField>

                <FormField
                    id="to_domain_id"
                    :label="t('vlan.copyTo')"
                    :error="errors.to_domain_id"
                >
                    <select
                        id="to_domain_id"
                        name="to_domain_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    >
                        <option
                            v-for="domain in domains"
                            :key="domain.id"
                            :value="domain.id"
                            :selected="domain.id !== selectedDomainId"
                        >
                            {{ domain.name }}
                        </option>
                    </select>
                </FormField>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="open = false">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{ t('vlan.copy') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
