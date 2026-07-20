<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Building2, Plus } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import SiteFormDialog from '@/components/sites/SiteFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as sitesIndex, show } from '@/routes/sites';
import type { Site, VlanDomainSummary } from '@/types';

const { t } = useI18n();

defineProps<{
    sites: Site[];
    domains: VlanDomainSummary[];
    kinds: string[];
    can: { create: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.sites', href: sitesIndex() }],
    },
});

const creating = ref(false);
</script>

<template>
    <Head :title="t('site.title')" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader :title="t('site.title')">
            <template #actions>
                <Button v-if="can.create" size="sm" @click="creating = true">
                    <Plus class="size-4" />
                    {{ t('site.new') }}
                </Button>
            </template>
        </PageHeader>

        <EmptyState v-if="!sites.length" :message="t('site.empty')" />

        <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="site in sites"
                :key="site.id"
                :href="show(site.id)"
                class="group rounded-xl border p-4 transition-colors hover:border-primary/50 hover:bg-accent/40"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <span
                            class="flex size-9 items-center justify-center rounded-lg text-white"
                            :style="{
                                backgroundColor: site.color ?? 'var(--primary)',
                            }"
                        >
                            <Building2 class="size-4.5" />
                        </span>
                        <div>
                            <p class="font-medium">{{ site.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ t(`site.kind.${site.kind}`) }}
                                <template v-if="site.city">
                                    · {{ site.city }}
                                </template>
                            </p>
                        </div>
                    </div>

                    <Badge variant="outline" class="font-mono">
                        {{ site.code }}
                    </Badge>
                </div>

                <dl
                    class="mt-4 flex items-center gap-4 text-xs text-muted-foreground"
                >
                    <div class="flex items-center gap-1">
                        <dt>{{ t('site.roomsCount') }}:</dt>
                        <dd class="font-medium text-foreground">
                            {{ site.rooms_count }}
                        </dd>
                    </div>
                    <div class="flex items-center gap-1 truncate">
                        <dt>{{ t('site.domain') }}:</dt>
                        <dd class="truncate font-medium text-foreground">
                            {{ site.vlan_domain?.name }}
                        </dd>
                    </div>
                </dl>
            </Link>
        </div>
    </div>

    <SiteFormDialog v-model:open="creating" :domains="domains" :kinds="kinds" />
</template>
