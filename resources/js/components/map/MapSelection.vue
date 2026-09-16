<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';

/**
 * What the one selected node is, and where to go from it — the map is a way in
 * to the rack and the patch panel, not a dead end.
 */

defineProps<{
    title: string;
    subtitle?: string | null;
    rows: { label: string; value: string }[];
    links: { label: string; href: string }[];
}>();
</script>

<template>
    <div
        class="map-selection pointer-events-auto absolute bottom-3 left-1/2 z-10 w-[min(22rem,calc(100%-2rem))] -translate-x-1/2 rounded-lg border bg-card/95 p-3 shadow-md backdrop-blur"
    >
        <p class="truncate text-sm font-semibold text-foreground">
            {{ title }}
        </p>
        <p v-if="subtitle" class="truncate text-xs text-muted-foreground">
            {{ subtitle }}
        </p>

        <dl
            v-if="rows.length"
            class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs"
        >
            <template v-for="row in rows" :key="row.label">
                <dt class="text-muted-foreground">{{ row.label }}</dt>
                <dd class="break-words text-foreground">{{ row.value }}</dd>
            </template>
        </dl>

        <div v-if="links.length" class="mt-3 flex flex-wrap gap-2">
            <Link v-for="link in links" :key="link.href" :href="link.href">
                <Button size="sm" variant="outline" class="h-7 gap-1 px-2">
                    {{ link.label }}
                    <ArrowUpRight class="size-3.5" />
                </Button>
            </Link>
        </div>
    </div>
</template>
