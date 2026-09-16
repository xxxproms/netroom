<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Cable, Check, FileUp } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { commit } from '@/actions/App/Http/Controllers/ImportController';
import PageHeader from '@/components/PageHeader.vue';
import StatusChip from '@/components/StatusChip.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { importMethod } from '@/routes';

type Action = 'create' | 'update' | 'skip';

type CablingRow = {
    label: string | null;
    a: string;
    b: string;
    media: string;
    color: string | null;
    status: string;
    action: Action;
    reason: string | null;
};

const props = defineProps<{
    token: string;
    filename: string;
    counts: { create: number; update: number; skip: number; total: number };
    rows: CablingRow[];
}>();

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.import', href: importMethod() }],
    },
});

const summary = computed(() => [
    { label: t('cabling.countCreate'), value: props.counts.create },
    { label: t('cabling.countUpdate'), value: props.counts.update },
    { label: t('cabling.countSkip'), value: props.counts.skip },
    { label: t('cabling.countTotal'), value: props.counts.total },
]);

/** Green to lay, blue to refresh, grey to leave — the same read as the journal. */
const actionTone: Record<Action, 'green' | 'blue' | 'gray'> = {
    create: 'green',
    update: 'blue',
    skip: 'gray',
};

const actionLabel: Record<Action, string> = {
    create: 'cabling.actionCreate',
    update: 'cabling.actionUpdate',
    skip: 'cabling.actionSkip',
};

function reasonText(reason: string | null): string | null {
    return reason ? t(`cabling.reason.${reason}`) : null;
}
</script>

<template>
    <Head :title="t('cabling.preview')" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            :title="t('cabling.preview')"
            :description="t('cabling.previewHint')"
        >
            <template #actions>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="importMethod()">
                        <ArrowLeft class="size-4" />
                        {{ t('import.back') }}
                    </Link>
                </Button>
                <Form
                    v-bind="commit.form()"
                    class="inline"
                    v-slot="{ processing }"
                >
                    <input type="hidden" name="token" :value="token" />
                    <Button
                        type="submit"
                        size="sm"
                        :disabled="
                            processing || counts.create + counts.update === 0
                        "
                    >
                        <FileUp class="size-4" />
                        {{
                            processing
                                ? t('cabling.importing')
                                : t('cabling.confirm')
                        }}
                    </Button>
                </Form>
            </template>
        </PageHeader>

        <div
            class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
        >
            <Cable class="size-4" />
            <span class="font-medium text-foreground">{{ filename }}</span>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div
                v-for="item in summary"
                :key="item.label"
                class="rounded-xl border p-4"
            >
                <p class="text-2xl font-semibold tabular-nums">
                    {{ item.value }}
                </p>
                <p class="text-sm text-muted-foreground">{{ item.label }}</p>
            </div>
        </div>

        <p
            v-if="!rows.length"
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            {{ t('cabling.empty') }}
        </p>

        <Table v-else>
            <TableHeader>
                <TableRow>
                    <TableHead class="w-24">{{
                        t('cabling.colLabel')
                    }}</TableHead>
                    <TableHead>{{ t('cabling.colA') }}</TableHead>
                    <TableHead>{{ t('cabling.colB') }}</TableHead>
                    <TableHead class="w-24">{{
                        t('cabling.colMedia')
                    }}</TableHead>
                    <TableHead class="w-28">{{
                        t('cabling.colStatus')
                    }}</TableHead>
                    <TableHead class="w-52">{{
                        t('cabling.colAction')
                    }}</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow
                    v-for="(row, index) in rows"
                    :key="index"
                    :class="{ 'opacity-60': row.action === 'skip' }"
                >
                    <TableCell class="font-mono">
                        <span class="flex items-center gap-1.5">
                            <span
                                class="inline-block size-2.5 shrink-0 rounded-full ring-1 ring-black/10"
                                :style="{
                                    backgroundColor: row.color ?? 'transparent',
                                }"
                            />
                            {{ row.label ?? '—' }}
                        </span>
                    </TableCell>
                    <TableCell>{{ row.a }}</TableCell>
                    <TableCell>{{ row.b }}</TableCell>
                    <TableCell class="text-muted-foreground">
                        {{ t(`cable.mediaKind.${row.media}`) }}
                    </TableCell>
                    <TableCell>
                        {{ t(`cable.statusKind.${row.status}`) }}
                    </TableCell>
                    <TableCell>
                        <span class="flex flex-col gap-0.5">
                            <StatusChip
                                :tone="actionTone[row.action]"
                                class="w-fit"
                            >
                                {{ t(actionLabel[row.action]) }}
                            </StatusChip>
                            <span
                                v-if="reasonText(row.reason)"
                                class="text-xs text-muted-foreground"
                            >
                                {{ reasonText(row.reason) }}
                            </span>
                        </span>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
            <Check class="size-3.5" />
            {{ t('cabling.skipHint') }}
        </p>
    </div>
</template>
