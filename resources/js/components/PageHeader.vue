<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="space-y-0.5">
            <h1 class="text-xl font-semibold tracking-tight">{{ title }}</h1>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
