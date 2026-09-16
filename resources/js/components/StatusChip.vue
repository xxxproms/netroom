<script setup lang="ts">
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    tone?: 'green' | 'amber' | 'red' | 'gray' | 'blue' | 'violet';
    class?: HTMLAttributes['class'];
}>();

/** A muted fill with a matching dot — a status reads at a glance, not as noise. */
const tones: Record<string, string> = {
    green: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    amber: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    red: 'bg-rose-500/15 text-rose-700 dark:text-rose-300',
    gray: 'bg-muted text-muted-foreground',
    blue: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    violet: 'bg-violet-500/15 text-violet-700 dark:text-violet-300',
};

const cls = computed(() => tones[props.tone ?? 'gray']);
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                cls,
                props.class,
            )
        "
    >
        <span class="size-1.5 rounded-full bg-current opacity-70" />
        <slot />
    </span>
</template>
