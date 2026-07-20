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
import { store, update } from '@/routes/racks';
import type { Rack } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    roomId: number;
    kinds: string[];
    rack?: Rack;
}>();

const open = defineModel<boolean>('open', { required: true });

const action = () => (props.rack ? update.form(props.rack.id) : store.form());
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ rack ? t('rack.edit') : t('rack.new') }}
                </DialogTitle>
            </DialogHeader>

            <Form
                v-bind="action()"
                class="grid gap-4"
                :options="{ preserveScroll: true }"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <input type="hidden" name="room_id" :value="roomId" />

                <FormField
                    id="rack-name"
                    :label="t('common.name')"
                    :error="errors.name"
                >
                    <Input
                        id="rack-name"
                        name="name"
                        :default-value="rack?.name"
                        required
                        autofocus
                    />
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="rack-kind"
                        :label="t('common.type')"
                        :error="errors.kind"
                    >
                        <select
                            id="rack-kind"
                            name="kind"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option
                                v-for="kind in kinds"
                                :key="kind"
                                :value="kind"
                                :selected="(rack?.kind ?? 'rack') === kind"
                            >
                                {{ t(`rack.kind.${kind}`) }}
                            </option>
                        </select>
                    </FormField>

                    <FormField
                        id="rack-height"
                        :label="t('rack.height')"
                        :error="errors.u_height"
                    >
                        <Input
                            id="rack-height"
                            name="u_height"
                            type="number"
                            min="1"
                            max="60"
                            :default-value="rack?.u_height ?? 42"
                            required
                        />
                    </FormField>
                </div>

                <FormField
                    id="rack-notes"
                    :label="t('common.notes')"
                    :error="errors.notes"
                >
                    <textarea
                        id="rack-notes"
                        name="notes"
                        rows="2"
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        :value="rack?.notes ?? ''"
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
