import { createI18n } from 'vue-i18n';
import en from './locales/en.json';
import ru from './locales/ru.json';

export const supportedLocales = ['ru', 'en'] as const;

export type SupportedLocale = (typeof supportedLocales)[number];

export const i18n = createI18n({
    legacy: false,
    locale: 'ru',
    fallbackLocale: 'en',
    messages: { ru, en },
});

export function setLocale(locale: string): void {
    if (!(supportedLocales as readonly string[]).includes(locale)) {
        return;
    }

    i18n.global.locale.value = locale as SupportedLocale;

    // Pages are also rendered server-side, where there is no document.
    if (typeof document !== 'undefined') {
        document.documentElement.lang = locale;
    }
}
