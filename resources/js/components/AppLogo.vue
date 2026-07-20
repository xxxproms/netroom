<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const { t } = useI18n();
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <AppLogoIcon class="size-5 text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="truncate leading-tight font-semibold">{{
            t('app.name')
        }}</span>
        <span class="truncate text-xs text-muted-foreground">{{
            t('app.tagline')
        }}</span>
    </div>
</template>
