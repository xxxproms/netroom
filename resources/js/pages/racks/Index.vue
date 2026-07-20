<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Server } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { index as racksIndex, show } from '@/routes/racks';
import type { Rack } from '@/types';

const { t } = useI18n();

defineProps<{
    racks: Rack[];
    kinds: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.racks', href: racksIndex() }],
    },
});
</script>

<template>
    <Head :title="t('rack.title')" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader :title="t('rack.title')" />

        <EmptyState v-if="!racks.length" :message="t('common.empty')" />

        <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="rack in racks"
                :key="rack.id"
                :href="show(rack.id)"
                class="flex items-center gap-3 rounded-xl border p-4 transition-colors hover:border-primary/50 hover:bg-accent/40"
            >
                <span
                    class="flex size-9 items-center justify-center rounded-lg bg-muted"
                >
                    <Server class="size-4.5 text-muted-foreground" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ rack.name }}</p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ rack.room?.name }} ·
                        {{ t(`rack.kind.${rack.kind}`) }} · {{ rack.u_height }}U
                    </p>
                </div>
                <Badge variant="outline" class="font-mono text-xs">
                    {{ rack.site?.code }}
                </Badge>
            </Link>
        </div>
    </div>
</template>
