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
import { store, update } from '@/routes/rooms';
import type { Room, SiteSummary } from '@/types';

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        siteId: number;
        room?: Room;
        sites?: SiteSummary[];
        kinds?: string[];
    }>(),
    {
        kinds: () => ['server_room', 'office', 'hall', 'other'],
    },
);

const open = defineModel<boolean>('open', { required: true });

const action = () => (props.room ? update.form(props.room.id) : store.form());
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ room ? t('room.edit') : t('room.new') }}
                </DialogTitle>
            </DialogHeader>

            <Form
                v-bind="action()"
                class="grid gap-4"
                :options="{ preserveScroll: true }"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <input type="hidden" name="site_id" :value="siteId" />

                <FormField
                    id="room-name"
                    :label="t('common.name')"
                    :error="errors.name"
                >
                    <Input
                        id="room-name"
                        name="name"
                        :default-value="room?.name"
                        required
                        autofocus
                    />
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="room-kind"
                        :label="t('common.type')"
                        :error="errors.kind"
                    >
                        <select
                            id="room-kind"
                            name="kind"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option
                                v-for="kind in kinds"
                                :key="kind"
                                :value="kind"
                                :selected="
                                    (room?.kind ?? 'server_room') === kind
                                "
                            >
                                {{ t(`room.kind.${kind}`) }}
                            </option>
                        </select>
                    </FormField>

                    <FormField
                        id="room-floor"
                        :label="t('room.floor')"
                        :error="errors.floor"
                    >
                        <Input
                            id="room-floor"
                            name="floor"
                            :default-value="room?.floor ?? ''"
                        />
                    </FormField>
                </div>

                <FormField
                    id="room-notes"
                    :label="t('common.notes')"
                    :error="errors.notes"
                >
                    <textarea
                        id="room-notes"
                        name="notes"
                        rows="2"
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        :value="room?.notes ?? ''"
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
