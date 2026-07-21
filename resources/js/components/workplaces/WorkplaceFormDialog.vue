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
import { store, update } from '@/routes/workplaces';
import type { Room, SiteSummary, Workplace } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    siteId: number;
    sites?: SiteSummary[];
    rooms: Room[];
    workplace?: Workplace;
}>();

const open = defineModel<boolean>('open', { required: true });

const action = () =>
    props.workplace ? update.form(props.workplace.id) : store.form();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ workplace ? t('workplace.edit') : t('workplace.new') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('workplace.hint') }}
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="action()"
                class="grid gap-4"
                :options="{ preserveScroll: true }"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <FormField
                    v-if="sites?.length"
                    id="workplace-site"
                    :label="t('site.one')"
                    :error="errors.site_id"
                >
                    <select
                        id="workplace-site"
                        name="site_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    >
                        <option
                            v-for="option in sites"
                            :key="option.id"
                            :value="option.id"
                            :selected="
                                (workplace?.site_id ?? siteId) === option.id
                            "
                        >
                            {{ option.name }}
                        </option>
                    </select>
                </FormField>
                <input v-else type="hidden" name="site_id" :value="siteId" />

                <FormField
                    id="workplace-name"
                    :label="t('common.name')"
                    :error="errors.name"
                >
                    <Input
                        id="workplace-name"
                        name="name"
                        :default-value="workplace?.name"
                        placeholder="Каб. 204, место 1"
                        required
                        autofocus
                    />
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="workplace-person"
                        :label="t('workplace.person')"
                        :error="errors.person"
                    >
                        <Input
                            id="workplace-person"
                            name="person"
                            :default-value="workplace?.person ?? ''"
                        />
                    </FormField>

                    <FormField
                        id="workplace-floor"
                        :label="t('room.floor')"
                        :error="errors.floor"
                    >
                        <Input
                            id="workplace-floor"
                            name="floor"
                            :default-value="workplace?.floor ?? ''"
                        />
                    </FormField>
                </div>

                <FormField
                    id="workplace-room"
                    :label="t('room.one')"
                    :error="errors.room_id"
                >
                    <select
                        id="workplace-room"
                        name="room_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    >
                        <option value="">{{ t('workplace.noRoom') }}</option>
                        <option
                            v-for="room in rooms"
                            :key="room.id"
                            :value="room.id"
                            :selected="workplace?.room_id === room.id"
                        >
                            {{ room.name }}
                        </option>
                    </select>
                </FormField>

                <FormField
                    id="workplace-notes"
                    :label="t('common.notes')"
                    :error="errors.notes"
                >
                    <textarea
                        id="workplace-notes"
                        name="notes"
                        rows="2"
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        :value="workplace?.notes ?? ''"
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
