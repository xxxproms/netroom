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
import { store, update } from '@/routes/sites';
import type { Site, VlanDomainSummary } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    domains: VlanDomainSummary[];
    kinds: string[];
    site?: Site;
}>();

const open = defineModel<boolean>('open', { required: true });

const action = () => (props.site ? update.form(props.site.id) : store.form());
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ site ? t('site.edit') : t('site.new') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('site.domainHint') }}
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="action()"
                class="grid gap-4"
                :options="{ preserveScroll: true }"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-4 sm:grid-cols-[1fr_8rem]">
                    <FormField
                        id="name"
                        :label="t('common.name')"
                        :error="errors.name"
                    >
                        <Input
                            id="name"
                            name="name"
                            :default-value="site?.name"
                            required
                            autofocus
                        />
                    </FormField>

                    <FormField
                        id="code"
                        :label="t('site.code')"
                        :error="errors.code"
                    >
                        <Input
                            id="code"
                            name="code"
                            class="font-mono uppercase"
                            :default-value="site?.code"
                            required
                        />
                    </FormField>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="kind"
                        :label="t('common.type')"
                        :error="errors.kind"
                    >
                        <select
                            id="kind"
                            name="kind"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option
                                v-for="kind in kinds"
                                :key="kind"
                                :value="kind"
                                :selected="(site?.kind ?? 'complex') === kind"
                            >
                                {{ t(`site.kind.${kind}`) }}
                            </option>
                        </select>
                    </FormField>

                    <FormField
                        id="vlan_domain_id"
                        :label="t('site.domain')"
                        :error="errors.vlan_domain_id"
                    >
                        <select
                            id="vlan_domain_id"
                            name="vlan_domain_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option
                                v-for="domain in domains"
                                :key="domain.id"
                                :value="domain.id"
                                :selected="site?.vlan_domain_id === domain.id"
                            >
                                {{ domain.name }}
                            </option>
                        </select>
                    </FormField>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="city"
                        :label="t('site.city')"
                        :error="errors.city"
                    >
                        <Input
                            id="city"
                            name="city"
                            :default-value="site?.city ?? ''"
                        />
                    </FormField>

                    <FormField
                        id="color"
                        :label="t('common.color')"
                        :error="errors.color"
                    >
                        <Input
                            id="color"
                            name="color"
                            type="color"
                            class="h-9 w-full p-1"
                            :default-value="site?.color ?? '#2563eb'"
                        />
                    </FormField>
                </div>

                <FormField
                    id="address"
                    :label="t('site.address')"
                    :error="errors.address"
                >
                    <Input
                        id="address"
                        name="address"
                        :default-value="site?.address ?? ''"
                    />
                </FormField>

                <FormField
                    id="notes"
                    :label="t('common.notes')"
                    :error="errors.notes"
                >
                    <textarea
                        id="notes"
                        name="notes"
                        rows="2"
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        :value="site?.notes ?? ''"
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
