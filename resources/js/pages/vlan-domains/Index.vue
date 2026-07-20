<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '@/components/EmptyState.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
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
import {
    destroy,
    index as vlanDomains,
    store,
    update,
} from '@/routes/vlan-domains';
import type { VlanDomain } from '@/types';

const { t } = useI18n();

defineProps<{
    domains: VlanDomain[];
    can: { create: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.vlanDomains', href: vlanDomains() }],
    },
});

const creating = ref(false);
const editing = ref<VlanDomain | undefined>();

function remove(domain: VlanDomain): void {
    if (confirm(t('common.deleteConfirm'))) {
        router.delete(destroy(domain.id).url, { preserveScroll: true });
    }
}

const action = () =>
    editing.value ? update.form(editing.value.id) : store.form();
</script>

<template>
    <Head :title="t('vlanDomain.title')" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            :title="t('vlanDomain.title')"
            :description="t('vlanDomain.hint')"
        >
            <template #actions>
                <Button v-if="can.create" size="sm" @click="creating = true">
                    <Plus class="size-4" />
                    {{ t('vlanDomain.new') }}
                </Button>
            </template>
        </PageHeader>

        <EmptyState v-if="!domains.length" :message="t('vlanDomain.empty')" />

        <div v-else class="grid gap-3 sm:grid-cols-2">
            <div
                v-for="domain in domains"
                :key="domain.id"
                class="rounded-xl border p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-medium">{{ domain.name }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ t('vlanDomain.vlansCount') }}:
                            {{ domain.vlans_count }}
                        </p>
                    </div>

                    <div v-if="can.create" class="flex items-center">
                        <Button
                            size="icon"
                            variant="ghost"
                            class="size-8"
                            @click="editing = domain"
                        >
                            <Pencil class="size-4" />
                        </Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            class="size-8 text-destructive"
                            @click="remove(domain)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-1.5">
                    <Badge
                        v-for="site in domain.sites"
                        :key="site.id"
                        variant="secondary"
                        class="font-mono text-[11px]"
                    >
                        {{ site.code }}
                    </Badge>
                    <span
                        v-if="!domain.sites.length"
                        class="text-xs text-muted-foreground"
                    >
                        {{ t('common.empty') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <Dialog
        :open="creating || !!editing"
        @update:open="
            (value) => {
                if (!value) {
                    creating = false;
                    editing = undefined;
                }
            }
        "
    >
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ editing ? t('vlanDomain.edit') : t('vlanDomain.new') }}
                </DialogTitle>
                <DialogDescription>{{
                    t('vlanDomain.hint')
                }}</DialogDescription>
            </DialogHeader>

            <Form
                v-bind="action()"
                class="grid gap-4"
                :options="{ preserveScroll: true }"
                @success="
                    creating = false;
                    editing = undefined;
                "
                v-slot="{ errors, processing }"
            >
                <FormField
                    id="domain-name"
                    :label="t('common.name')"
                    :error="errors.name"
                >
                    <Input
                        id="domain-name"
                        name="name"
                        :default-value="editing?.name"
                        required
                        autofocus
                    />
                </FormField>

                <FormField
                    id="domain-notes"
                    :label="t('common.notes')"
                    :error="errors.notes"
                >
                    <textarea
                        id="domain-notes"
                        name="notes"
                        rows="2"
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        :value="editing?.notes ?? ''"
                    ></textarea>
                </FormField>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="
                            creating = false;
                            editing = undefined;
                        "
                    >
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
