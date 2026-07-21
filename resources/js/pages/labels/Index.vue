<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { MapPin, Printer, Router, Server } from '@lucide/vue';
import type {LucideIcon} from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { labels } from '@/routes';

const { t } = useI18n();

defineProps<{
    counts: { devices: number; racks: number; workplaces: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.labels', href: labels() }],
    },
});

const types: { key: 'devices' | 'racks' | 'workplaces'; icon: LucideIcon }[] = [
    { key: 'devices', icon: Router },
    { key: 'racks', icon: Server },
    { key: 'workplaces', icon: MapPin },
];
</script>

<template>
    <Head :title="t('labels.title')" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader :title="t('labels.title')" :description="t('labels.hint')" />

        <div class="grid gap-3 sm:grid-cols-3">
            <div
                v-for="type in types"
                :key="type.key"
                class="flex flex-col gap-3 rounded-xl border p-4"
            >
                <component :is="type.icon" class="size-5 text-muted-foreground" />
                <div>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ counts[type.key] }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ t(`labels.type.${type.key}`) }}
                    </p>
                </div>
                <Button
                    as-child
                    size="sm"
                    variant="outline"
                    :disabled="counts[type.key] === 0"
                >
                    <a :href="`/labels/print?type=${type.key}`" target="_blank" rel="noopener">
                        <Printer class="size-4" />
                        {{ t('labels.print') }}
                    </a>
                </Button>
            </div>
        </div>

        <p class="text-xs text-muted-foreground">{{ t('labels.qrHint') }}</p>
    </div>
</template>
