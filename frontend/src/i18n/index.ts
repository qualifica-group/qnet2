import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import { en } from '@/i18n/locales/en'
import { it } from '@/i18n/locales/it'
import { migrations as migrationsEn } from '@/i18n/locales/en-migrations'
import { migrations as migrationsIt } from '@/i18n/locales/it-migrations'
import { aiAssistantSettings as aiAssistantSettingsEn } from '@/i18n/locales/en-ai-assistant-settings'
import { aiAssistantSettings as aiAssistantSettingsIt } from '@/i18n/locales/it-ai-assistant-settings'
import { systemHealth as systemHealthEn } from '@/i18n/locales/en-system-health'
import { systemHealth as systemHealthIt } from '@/i18n/locales/it-system-health'
import { workspaces as workspacesEn } from '@/i18n/locales/en-workspaces'
import { workspaces as workspacesIt } from '@/i18n/locales/it-workspaces'
import { onboarding as onboardingEn } from '@/i18n/locales/en-onboarding'
import { onboarding as onboardingIt } from '@/i18n/locales/it-onboarding'
import { maintenance as maintenanceEn } from '@/i18n/locales/en-maintenance'
import { maintenance as maintenanceIt } from '@/i18n/locales/it-maintenance'
import type { IntegrationI18nResources } from '@/integrations/types'

// Each integration ships its own namespace in `integrations/<name>/i18n/index.ts`. Registered eagerly (not by the
// lazy page) because the backend nav label `<name>:nav.label` must resolve before that page loads. Globbed so this
// file never names an integration.
const integrationResources = Object.values(
  import.meta.glob<{ default: IntegrationI18nResources }>('../integrations/*/i18n/index.ts', { eager: true }),
).map((module) => module.default)

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

// Chrome offers to translate the page when <html lang> disagrees with its text.
i18n.on('languageChanged', (locale) => {
  if (typeof document !== 'undefined') {
    document.documentElement.lang = locale
  }
})

void i18n.use(initReactI18next).init({
  resources: {
    // `migrations` is registered as its own namespace (not merged into the
    // default `translation` bundle) so the backend-driven nav label and the
    // breadcrumb can resolve `migrations:nav.label` app-wide — before the lazy
    // migrations feature module is ever loaded. See features/migrations/i18n.ts.
    // `systemHealth`, `workspaces` and `onboarding` are separate namespaces for the same reason (en.ts/it.ts at the size limit).
    en: { translation: en, migrations: migrationsEn, systemHealth: systemHealthEn, aiAssistantSettings: aiAssistantSettingsEn, workspaces: workspacesEn, onboarding: onboardingEn, maintenance: maintenanceEn, ...Object.fromEntries(integrationResources.map((r) => [r.namespace, r.en])) },
    it: { translation: it, migrations: migrationsIt, systemHealth: systemHealthIt, aiAssistantSettings: aiAssistantSettingsIt, workspaces: workspacesIt, onboarding: onboardingIt, maintenance: maintenanceIt, ...Object.fromEntries(integrationResources.map((r) => [r.namespace, r.it])) },
  },
  lng: defaultLocale,
  fallbackLng: fallbackLocale,
  interpolation: {
    escapeValue: false, // React already escapes values.
  },
})

export default i18n
