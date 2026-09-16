<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
    <div
        data-slot="table-container"
        class="relative w-full overflow-x-auto rounded-xl border"
    >
        <table
            data-slot="table"
            :class="cn('w-full caption-bottom text-[15px]', props.class)"
        >
            <slot />
        </table>
    </div>
</template>
