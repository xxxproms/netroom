<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Check, FileUp } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { commit } from '@/actions/App/Http/Controllers/ImportController';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { importMethod } from '@/routes';

type Warning = {
    switch: string;
    kind: string;
    sheet?: string;
    sheet_ports?: number;
    model_ports?: number;
};

type SwitchRow = {
    name: string;
    site: string | null;
    model: string | null;
    mgmt_ip: string | null;
    port_count: number;
    uplinks: number[];
    memberships: number;
    has_warning: boolean;
};

const props = defineProps<{
    token: string;
    filename: string;
    domain: string;
    sites: { code: string; name: string }[];
    counts: {
        switches: number;
        vlans: number;
        memberships: number;
        warnings: number;
    };
    vlans: { vid: number; name: string }[];
    switches: SwitchRow[];
    warnings: Warning[];
}>();

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.import', href: importMethod() }],
    },
});

const summary = computed(() => [
    { label: t('import.countSwitches'), value: props.counts.switches },
    { label: t('import.countVlans'), value: props.counts.vlans },
    { label: t('import.countMemberships'), value: props.counts.memberships },
    { label: t('import.countWarnings'), value: props.counts.warnings },
]);

/** The site's readable name, or a dash when the sheet gave no site. */
function siteName(code: string | null): string | null {
    if (code === null) {
        return null;
    }

    return props.sites.find((site) => site.code === code)?.name ?? code;
}

function warningText(warning: Warning): string {
    switch (warning.kind) {
        case 'garbled_name':
            return t('import.wGarbled', { switch: warning.switch });
        case 'missing_sheet':
            return t('import.wMissingSheet', {
                switch: warning.switch,
                sheet: warning.sheet ?? '',
            });
        case 'no_sheet':
            return t('import.wNoSheet', { switch: warning.switch });
        case 'port_count_mismatch':
            return t('import.wPortMismatch', {
                switch: warning.switch,
                sheet_ports: warning.sheet_ports ?? 0,
                model_ports: warning.model_ports ?? 0,
            });
        default:
            return warning.switch;
    }
}
</script>

<template>
    <Head :title="t('import.preview')" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            :title="t('import.preview')"
            :description="t('import.previewHint')"
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
                    <Button type="submit" size="sm" :disabled="processing">
                        <FileUp class="size-4" />
                        {{
                            processing
                                ? t('import.importing')
                                : t('import.confirm')
                        }}
                    </Button>
                </Form>
            </template>
        </PageHeader>

        <div
            class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
        >
            <span class="font-medium text-foreground">{{ filename }}</span>
            <span>·</span>
            <span>{{ t('import.domain') }}: {{ domain }}</span>
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

        <div
            v-if="warnings.length"
            class="flex flex-col gap-2 rounded-xl border border-amber-500/30 bg-amber-500/5 p-4"
        >
            <p class="flex items-center gap-2 text-sm font-medium">
                <AlertTriangle class="size-4 text-amber-500" />
                {{ t('import.warningsTitle') }}
            </p>
            <ul class="flex flex-col gap-1 pl-6 text-sm text-muted-foreground">
                <li
                    v-for="(warning, index) in warnings"
                    :key="index"
                    class="list-disc"
                >
                    {{ warningText(warning) }}
                </li>
            </ul>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead
                    class="border-b bg-muted/40 text-left text-muted-foreground"
                >
                    <tr>
                        <th class="px-3 py-2 font-medium">
                            {{ t('import.colName') }}
                        </th>
                        <th class="px-3 py-2 font-medium">
                            {{ t('import.colSite') }}
                        </th>
                        <th class="px-3 py-2 font-medium">
                            {{ t('import.colModel') }}
                        </th>
                        <th class="px-3 py-2 font-medium">
                            {{ t('import.colMgmt') }}
                        </th>
                        <th class="px-3 py-2 text-right font-medium">
                            {{ t('import.colPorts') }}
                        </th>
                        <th class="px-3 py-2 text-right font-medium">
                            {{ t('import.colUplinks') }}
                        </th>
                        <th class="px-3 py-2 text-right font-medium">
                            {{ t('import.colMemberships') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in switches"
                        :key="row.name"
                        class="border-b last:border-0"
                        :class="row.site === null ? 'opacity-55' : ''"
                    >
                        <td class="px-3 py-2 font-medium">
                            <span class="flex items-center gap-1.5">
                                {{ row.name }}
                                <AlertTriangle
                                    v-if="row.has_warning"
                                    class="size-3.5 text-amber-500"
                                />
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <Badge v-if="row.site" variant="secondary">
                                {{ siteName(row.site) }}
                            </Badge>
                            <span v-else class="text-xs text-muted-foreground">
                                {{ t('import.noSite') }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            {{ row.model ?? '—' }}
                        </td>
                        <td class="px-3 py-2 font-mono text-xs">
                            {{ row.mgmt_ip ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ row.port_count }}
                        </td>
                        <td
                            class="px-3 py-2 text-right text-muted-foreground tabular-nums"
                        >
                            {{ row.uplinks.length || '—' }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ row.memberships }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
            <Check class="size-3.5" />
            {{ t('import.skipHint') }}
        </p>
    </div>
</template>
