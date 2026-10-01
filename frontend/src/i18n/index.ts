import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import { en } from '@/i18n/locales/en'
import { it } from '@/i18n/locales/it'
import { migrations as migrationsEn } from '@/i18n/locales/en-migrations'
import { migrations as migrationsIt } from '@/i18n/locales/it-migrations'
import { systemHealth as systemHealthEn } from '@/i18n/locales/en-system-health'
import { systemHealth as systemHealthIt } from '@/i18n/locales/it-system-health'

export const defaultLocale = 'it'
export const fallbackLocale = 'en'
export const supportedLocales = ['en', 'it'] as const

type SupportedLocale = (typeof supportedLocales)[number]

function isSupportedLocale(locale: string): locale is SupportedLocale {
  return (supportedLocales as readonly string[]).includes(locale)
}

/**
 * Switches the active UI language to a backend-provided locale, ignoring any
 * value the frontend does not ship translations for.
 */
export function applyLocale(locale: string | null | undefined): void {
  if (locale && isSupportedLocale(locale) && i18n.language !== locale) {
    void i18n.changeLanguage(locale)
  }
}

void i18n.use(initReactI18next).init({
  resources: {
    // `migrations` is registered as its own namespace (not merged into the
    // default `translation` bundle) so the backend-driven nav label and the
    // breadcrumb can resolve `migrations:nav.label` app-wide — before the lazy
    // migrations feature module is ever loaded. See features/migrations/i18n.ts.
    // `systemHealth` is a separate namespace for the same reason (en.ts/it.ts at the size limit).
    en: { translation: en, migrations: migrationsEn, systemHealth: systemHealthEn },
    it: { translation: it, migrations: migrationsIt, systemHealth: systemHealthIt },
  },
  lng: defaultLocale,
  fallbackLng: fallbackLocale,
  interpolation: {
    escapeValue: false, // React already escapes values.
  },
})

export default i18n
