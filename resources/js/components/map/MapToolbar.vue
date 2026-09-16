<script setup lang="ts">
import {
    AlignCenterHorizontal,
    AlignCenterVertical,
    AlignEndHorizontal,
    AlignEndVertical,
    AlignHorizontalSpaceBetween,
    AlignStartHorizontal,
    AlignStartVertical,
    AlignVerticalSpaceBetween,
    Grid3x3,
    Redo2,
    SquareDashed,
    StickyNote,
    Undo2,
    Wand2,
} from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import type { useMapTools } from '@/composables/useMapTools';
import type { MapAnnotationType } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    tools: ReturnType<typeof useMapTools>;
}>();

const emit = defineEmits<{
    add: [type: MapAnnotationType];
}>();

const { tools } = props;
</script>

<template>
    <div
        class="map-toolbar pointer-events-auto absolute top-3 right-3 z-10 flex flex-col gap-2"
    >
        <div
            class="flex items-center gap-1 rounded-lg border bg-card/95 p-1 shadow-sm backdrop-blur"
        >
            <Button
                size="icon"
                variant="ghost"
                class="size-8"
                :disabled="!tools.canUndo.value"
                :title="t('map.undo')"
                @click="tools.undo()"
            >
                <Undo2 class="size-4" />
            </Button>
            <Button
                size="icon"
                variant="ghost"
                class="size-8"
                :disabled="!tools.canRedo.value"
                :title="t('map.redo')"
                @click="tools.redo()"
            >
                <Redo2 class="size-4" />
            </Button>

            <span class="mx-0.5 h-5 w-px bg-border" />

            <Button
                size="icon"
                :variant="tools.snap.value ? 'default' : 'ghost'"
                class="size-8"
                :title="t('map.snap')"
                @click="tools.toggleSnap()"
            >
                <Grid3x3 class="size-4" />
            </Button>
            <Button
                size="icon"
                variant="ghost"
                class="size-8"
                :title="t('map.autoLayout')"
                @click="tools.autoLayout()"
            >
                <Wand2 class="size-4" />
            </Button>

            <span class="mx-0.5 h-5 w-px bg-border" />

            <Button
                size="icon"
                variant="ghost"
                class="size-8"
                :title="t('map.addZone')"
                @click="emit('add', 'zone')"
            >
                <SquareDashed class="size-4" />
            </Button>
            <Button
                size="icon"
                variant="ghost"
                class="size-8"
                :title="t('map.addNote')"
                @click="emit('add', 'note')"
            >
                <StickyNote class="size-4" />
            </Button>
        </div>

        <div
            v-if="tools.selectedCount.value >= 2"
            class="flex items-center gap-1 rounded-lg border bg-card/95 p-1 shadow-sm backdrop-blur"
        >
            <Button
                size="icon"
                variant="ghost"
                class="size-8"
                :title="t('map.alignLeft')"
                @click="tools.align('left')"
            >
                <AlignStartVertical class="size-4" />
            </Button>
            <Button
                size="icon"
                variant="ghost"
                class="size-8"
                :title="t('map.alignHCenter')"
                @click="tools.align('hcenter')"
            >
                <AlignCenterVertical class="size-4" />
            </Button>
            <Button
                size="icon"
                variant="ghost"
                class="size-8"
                :title="t('map.alignRight')"
                @click="tools.align('right')"
            >
                <AlignEndVertical class="size-4" />
            </Button>

            <span class="mx-0.5 h-5 w-px bg-border" />

            <Button
                size="icon"
                variant="ghost"
                class="size-8"
                :title="t('map.alignTop')"
                @click="tools.align('top')"
            >
                <AlignStartHorizontal class="size-4" />
            </Button>
            <Button
                size="icon"
                variant="ghost"
                class="size-8"
                :title="t('map.alignVMiddle')"
                @click="tools.align('vmiddle')"
            >
                <AlignCenterHorizontal class="size-4" />
            </Button>
            <Button
                size="icon"
                variant="ghost"
                class="size-8"
                :title="t('map.alignBottom')"
                @click="tools.align('bottom')"
            >
                <AlignEndHorizontal class="size-4" />
            </Button>

            <template v-if="tools.selectedCount.value >= 3">
                <span class="mx-0.5 h-5 w-px bg-border" />

                <Button
                    size="icon"
                    variant="ghost"
                    class="size-8"
                    :title="t('map.distributeH')"
                    @click="tools.distribute('h')"
                >
                    <AlignHorizontalSpaceBetween class="size-4" />
                </Button>
                <Button
                    size="icon"
                    variant="ghost"
                    class="size-8"
                    :title="t('map.distributeV')"
                    @click="tools.distribute('v')"
                >
                    <AlignVerticalSpaceBetween class="size-4" />
                </Button>
            </template>
        </div>
    </div>
</template>
