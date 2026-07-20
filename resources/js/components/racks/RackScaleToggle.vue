<script setup lang="ts">
import { Minus, Plus } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { useRackScale } from '@/composables/useRackScale';
import type { RackScale } from '@/composables/useRackScale';

const { t } = useI18n();
const { scale, setScale } = useRackScale();

const steps: RackScale[] = ['compact', 'normal', 'large'];

const index = computed(() => steps.indexOf(scale.value));

function step(by: number): void {
    const next = steps[index.value + by];

    if (next) {
        setScale(next);
    }
}
</script>

<template>
    <div class="flex items-center gap-1 rounded-lg border p-0.5">
        <Button
            size="icon"
            variant="ghost"
            class="size-7"
            :disabled="index === 0"
            :title="t('rack.smaller')"
            @click="step(-1)"
        >
            <Minus class="size-4" />
        </Button>

        <span class="min-w-16 text-center text-xs text-muted-foreground">
            {{ t(`rack.scale.${scale}`) }}
        </span>

        <Button
            size="icon"
            variant="ghost"
            class="size-7"
            :disabled="index === steps.length - 1"
            :title="t('rack.bigger')"
            @click="step(1)"
        >
            <Plus class="size-4" />
        </Button>
    </div>
</template>
