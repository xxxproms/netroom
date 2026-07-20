<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- A rack holding three units: the smallest thing NetRoom documents. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        :class="className"
        v-bind="$attrs"
    >
        <rect x="3" y="2.5" width="18" height="19" rx="2.5" />
        <path d="M3 8.25h18M3 14h18" />
        <circle cx="6.75" cy="5.4" r="0.9" fill="currentColor" stroke="none" />
        <circle cx="6.75" cy="11.1" r="0.9" fill="currentColor" stroke="none" />
        <circle
            cx="6.75"
            cy="16.85"
            r="0.9"
            fill="currentColor"
            stroke="none"
        />
        <path d="M10.5 5.4h7M10.5 11.1h7M10.5 16.85h7" opacity="0.55" />
    </svg>
</template>
