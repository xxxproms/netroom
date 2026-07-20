<script setup lang="ts">
import { Inbox } from '@lucide/vue';

defineProps<{
    message: string;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-6 py-14 text-center"
    >
        <Inbox class="size-8 text-muted-foreground/60" />
        <p class="text-sm text-muted-foreground">{{ message }}</p>
        <slot />
    </div>
</template>
