<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Building2, Check, ChevronsUpDown, Globe } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { site as switchSite } from '@/routes/context';
import type { SiteSummary } from '@/types';

const { t } = useI18n();
const page = usePage();

const context = computed(() => page.props.siteContext);
const current = computed<SiteSummary | null>(() => context.value.current);
const available = computed<SiteSummary[]>(() => context.value.available);

function select(site: SiteSummary | null): void {
    router.put(
        switchSite().url,
        { site_id: site?.id ?? null },
        { preserveScroll: true },
    );
}
</script>

<template>
    <DropdownMenu v-if="available.length">
        <DropdownMenuTrigger as-child>
            <Button
                variant="outline"
                size="sm"
                class="h-8 gap-1.5 px-2.5"
                :aria-label="t('nav.sites')"
            >
                <component
                    :is="current ? Building2 : Globe"
                    class="size-3.5 text-muted-foreground"
                />
                <span class="max-w-40 truncate text-sm">
                    {{ current?.name ?? t('site.all') }}
                </span>
                <ChevronsUpDown class="size-3.5 text-muted-foreground" />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="start" class="w-60">
            <DropdownMenuLabel>{{ t('site.switch') }}</DropdownMenuLabel>
            <DropdownMenuSeparator />

            <DropdownMenuItem class="gap-2" @select="select(null)">
                <Globe class="size-4 text-muted-foreground" />
                <span class="flex-1">{{ t('site.all') }}</span>
                <Check v-if="!current" class="size-4" />
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <DropdownMenuItem
                v-for="option in available"
                :key="option.id"
                class="gap-2"
                @select="select(option)"
            >
                <span
                    class="w-9 shrink-0 rounded bg-muted px-1 py-0.5 text-center font-mono text-[11px] text-muted-foreground"
                >
                    {{ option.code }}
                </span>
                <span class="flex-1 truncate">{{ option.name }}</span>
                <Check v-if="current?.id === option.id" class="size-4" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
