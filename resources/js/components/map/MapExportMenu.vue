<script setup lang="ts">
import { Download, FileImage, Printer } from '@lucide/vue';
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
import { Spinner } from '@/components/ui/spinner';
import type { PaperOrientation, PaperSize } from '@/composables/useMapExport';

/**
 * Every way the diagram leaves the screen, under one button: a picture, a
 * vector file, or paper — where "save as PDF" lives in the print dialog.
 */

const { t } = useI18n();

// Rasterising a big diagram takes seconds, and the page owns that work, so it
// also owns the flag — a timer here would only pretend to know when it is done.
defineProps<{ busy?: boolean }>();

const emit = defineEmits<{
    png: [];
    svg: [];
    print: [paper: { size: PaperSize; orientation: PaperOrientation }];
}>();

const papers: { size: PaperSize; orientation: PaperOrientation }[] = [
    { size: 'a4', orientation: 'landscape' },
    { size: 'a4', orientation: 'portrait' },
    { size: 'a3', orientation: 'landscape' },
    { size: 'a3', orientation: 'portrait' },
];
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button size="sm" variant="outline" :disabled="busy">
                <Spinner v-if="busy" />
                <Download v-else class="size-4" />
                {{ busy ? t('map.exporting') : t('map.export') }}
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-56">
            <DropdownMenuItem @select="emit('png')">
                <FileImage class="size-4" />
                {{ t('map.exportPng') }}
            </DropdownMenuItem>
            <DropdownMenuItem @select="emit('svg')">
                <FileImage class="size-4" />
                {{ t('map.exportSvg') }}
            </DropdownMenuItem>

            <DropdownMenuSeparator />
            <DropdownMenuLabel>{{ t('map.print') }}</DropdownMenuLabel>

            <DropdownMenuItem
                v-for="paper in papers"
                :key="`${paper.size}-${paper.orientation}`"
                @select="emit('print', paper)"
            >
                <Printer class="size-4" />
                {{ paper.size.toUpperCase() }},
                {{ t(`map.paper.${paper.orientation}`) }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
