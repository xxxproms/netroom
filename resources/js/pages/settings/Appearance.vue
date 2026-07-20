<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import LanguageTabs from '@/components/LanguageTabs.vue';
import { edit } from '@/routes/appearance';

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="t('settings.appearance')" />

    <h1 class="sr-only">{{ t('settings.appearance') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('settings.appearance')"
            description="Update the appearance settings for your account"
        />
        <AppearanceTabs />
    </div>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('settings.language')"
            :description="t('settings.languageDescription')"
        />
        <LanguageTabs />
    </div>
</template>
