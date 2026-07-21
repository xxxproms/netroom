<script setup lang="ts">
import { ArrowDown } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import EndLabel from '@/components/cables/EndLabel.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { CableEnd, TraceStep } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    /** Where to fetch the path from — a port's or an outlet's trace route. */
    url: string;
    title: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const path = ref<TraceStep[]>([]);
const loading = ref(false);

const isCable = (step: TraceStep): boolean => step.kind === 'cable';

async function load(): Promise<void> {
    loading.value = true;
    path.value = [];

    const response = await fetch(props.url, {
        headers: { Accept: 'application/json' },
    });

    path.value = ((await response.json()) as { path: TraceStep[] }).path;
    loading.value = false;
}

// The dialog is remounted each time it opens (keyed, mounted already open), so
// the load runs on mount; the watcher covers a reused instance being reopened.
onMounted(() => {
    if (open.value) {
        void load();
    }
});

watch(open, (isOpen) => {
    if (isOpen) {
        void load();
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('trace.title') }}</DialogTitle>
                <DialogDescription>{{ title }}</DialogDescription>
            </DialogHeader>

            <p v-if="loading" class="text-sm text-muted-foreground">
                {{ t('common.loading') }}
            </p>

            <p
                v-else-if="path.length < 2"
                class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
            >
                {{ t('trace.nothing') }}
            </p>

            <ol v-else class="flex flex-col gap-1">
                <li v-for="(step, index) in path" :key="index">
                    <!-- Cables are the arrows between the ends, not stops. -->
                    <div
                        v-if="isCable(step)"
                        class="flex items-center gap-2 py-1 pl-2 text-sm text-muted-foreground"
                    >
                        <ArrowDown class="size-4" />
                        <span>{{ t(`cable.mediaKind.${step.media}`) }}</span>
                        <span v-if="step.strands">
                            · {{ t('cable.strandCount', { count: step.strands }) }}
                        </span>
                        <span v-if="step.label" class="font-mono">
                            · {{ step.label }}
                        </span>
                        <span v-if="step.length_cm">
                            · {{ step.length_cm }} {{ t('cable.cm') }}
                        </span>
                    </div>

                    <div v-else class="rounded-lg border p-3">
                        <EndLabel :end="(step as CableEnd)" />
                    </div>
                </li>
            </ol>
        </DialogContent>
    </Dialog>
</template>
