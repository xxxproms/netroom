<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Building2,
    Cable,
    EthernetPort,
    History,
    MapPin,
    Network,
    Router,
    Server,
    ShieldCheck
    
} from '@lucide/vue';
import type {LucideIcon} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PageHeader from '@/components/PageHeader.vue';
import { dashboard } from '@/routes';
import { index as cables } from '@/routes/cables';
import { index as devices } from '@/routes/devices';
import { index as racks } from '@/routes/racks';
import { index as rooms } from '@/routes/rooms';
import { index as sites } from '@/routes/sites';
import { index as subnets } from '@/routes/subnets';
import { index as vlans } from '@/routes/vlans';
import { index as workplaces } from '@/routes/workplaces';

const { t, d } = useI18n();

type Stats = {
    sites: number;
    devices: number;
    rooms: number;
    racks: number;
    cables: number;
    workplaces: number;
    vlans: number;
    subnets: number;
};

type Activity = {
    id: number;
    event: string;
    model: string;
    subject: string | null;
    causer: string | null;
    at: string;
};

const props = defineProps<{
    stats: Stats;
    attention: { ipamConflicts: number; switchesWithoutVlans: number };
    activity: Activity[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.dashboard', href: dashboard() }],
    },
});

type Card = { key: keyof Stats; icon: LucideIcon; href: string };

const cards: Card[] = [
    { key: 'sites', icon: Building2, href: sites().url },
    { key: 'devices', icon: Router, href: devices().url },
    { key: 'rooms', icon: Server, href: rooms().url },
    { key: 'racks', icon: Server, href: racks().url },
    { key: 'vlans', icon: Network, href: vlans().url },
    { key: 'subnets', icon: EthernetPort, href: subnets().url },
    { key: 'cables', icon: Cable, href: cables().url },
    { key: 'workplaces', icon: MapPin, href: workplaces().url },
];

const attentionItems = computed(() =>
    [
        {
            key: 'ipamConflicts',
            count: props.attention.ipamConflicts,
            href: subnets().url,
        },
        {
            key: 'switchesWithoutVlans',
            count: props.attention.switchesWithoutVlans,
            href: devices().url,
        },
    ].filter((item) => item.count > 0),
);

const allClear = computed(() => attentionItems.value.length === 0);

/** The verb the log recorded, spelled out for the reader. */
function eventLabel(event: string): string {
    return t(`dashboard.event.${event}`, event);
}
</script>

<template>
    <Head :title="t('nav.dashboard')" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader :title="t('dashboard.title')" :description="t('dashboard.hint')" />

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <Link
                v-for="card in cards"
                :key="card.key"
                :href="card.href"
                class="flex flex-col gap-2 rounded-xl border p-4 transition-colors hover:border-primary/50 hover:bg-accent/40"
            >
                <component :is="card.icon" class="size-5 text-muted-foreground" />
                <p class="text-2xl font-semibold tabular-nums">{{ stats[card.key] }}</p>
                <p class="text-sm text-muted-foreground">{{ t(`dashboard.stat.${card.key}`) }}</p>
            </Link>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="flex flex-col gap-3 rounded-xl border p-4">
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <AlertTriangle class="size-4 text-amber-500" />
                    {{ t('dashboard.attention') }}
                </h2>

                <p
                    v-if="allClear"
                    class="flex items-center gap-2 py-4 text-sm text-muted-foreground"
                >
                    <ShieldCheck class="size-4 text-emerald-500" />
                    {{ t('dashboard.allClear') }}
                </p>

                <ul v-else class="flex flex-col gap-2">
                    <li v-for="item in attentionItems" :key="item.key">
                        <Link
                            :href="item.href"
                            class="flex items-center justify-between gap-3 rounded-lg border border-amber-500/30 bg-amber-500/5 px-3 py-2 text-sm transition-colors hover:bg-amber-500/10"
                        >
                            <span>{{ t(`dashboard.warn.${item.key}`) }}</span>
                            <span class="font-semibold tabular-nums text-amber-600">
                                {{ item.count }}
                            </span>
                        </Link>
                    </li>
                </ul>
            </section>

            <section class="flex flex-col gap-3 rounded-xl border p-4">
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <History class="size-4 text-muted-foreground" />
                    {{ t('dashboard.recent') }}
                </h2>

                <p
                    v-if="!activity.length"
                    class="py-4 text-sm text-muted-foreground"
                >
                    {{ t('dashboard.noActivity') }}
                </p>

                <ul v-else class="flex flex-col divide-y text-sm">
                    <li
                        v-for="entry in activity"
                        :key="entry.id"
                        class="flex items-center justify-between gap-3 py-2"
                    >
                        <div class="min-w-0">
                            <p class="truncate">
                                <span class="text-muted-foreground">
                                    {{ t(`dashboard.model.${entry.model}`, entry.model) }}
                                </span>
                                <span v-if="entry.subject" class="font-medium">
                                    · {{ entry.subject }}
                                </span>
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ eventLabel(entry.event) }}
                                <template v-if="entry.causer">· {{ entry.causer }}</template>
                            </p>
                        </div>
                        <time
                            v-if="entry.at"
                            :datetime="entry.at"
                            class="shrink-0 text-xs text-muted-foreground"
                        >
                            {{ d(new Date(entry.at), 'short') }}
                        </time>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
