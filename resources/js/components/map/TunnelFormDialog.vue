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
import { store } from '@/routes/tunnels';
import type { MapSite } from '@/types';

const { t } = useI18n();

defineProps<{
    sites: MapSite[];
    types: string[];
    statuses: string[];
}>();

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('tunnel.new') }}</DialogTitle>
                <DialogDescription>{{ t('tunnel.hint') }}</DialogDescription>
            </DialogHeader>

            <Form
                v-bind="store.form()"
                class="grid gap-4"
                :options="{ preserveScroll: true }"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="tunnel-a"
                        :label="t('tunnel.siteA')"
                        :error="errors.site_a_id"
                    >
                        <select
                            id="tunnel-a"
                            name="site_a_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                            required
                        >
                            <option
                                v-for="site in sites"
                                :key="site.id"
                                :value="site.id"
                            >
                                {{ site.name }}
                            </option>
                        </select>
                    </FormField>

                    <FormField
                        id="tunnel-b"
                        :label="t('tunnel.siteB')"
                        :error="errors.site_b_id"
                    >
                        <select
                            id="tunnel-b"
                            name="site_b_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                            required
                        >
                            <option
                                v-for="site in sites"
                                :key="site.id"
                                :value="site.id"
                            >
                                {{ site.name }}
                            </option>
                        </select>
                    </FormField>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="tunnel-type"
                        :label="t('tunnel.type')"
                        :error="errors.type"
                    >
                        <select
                            id="tunnel-type"
                            name="type"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option
                                v-for="value in types"
                                :key="value"
                                :value="value"
                            >
                                {{ t(`tunnel.typeKind.${value}`) }}
                            </option>
                        </select>
                    </FormField>

                    <FormField
                        id="tunnel-status"
                        :label="t('common.status')"
                        :error="errors.status"
                    >
                        <select
                            id="tunnel-status"
                            name="status"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option
                                v-for="value in statuses"
                                :key="value"
                                :value="value"
                            >
                                {{ t(`tunnel.statusKind.${value}`) }}
                            </option>
                        </select>
                    </FormField>
                </div>

                <FormField
                    id="tunnel-label"
                    :label="t('tunnel.label')"
                    :error="errors.label"
                >
                    <Input id="tunnel-label" name="label" class="font-mono" />
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
