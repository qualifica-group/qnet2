import { fallbackLocale } from '@/i18n'
import type { HelpGuide, HelpGuideKey } from '@/features/help/types'

export type HelpLocale = 'it' | 'en'

/**
 * One dynamic import per `content/{it,en}/<key>.ts` file (AC-012): Vite emits
 * a separate chunk per entry, so a guide's text never ships in the initial
 * bundle. `import.meta.glob` without `eager` returns loader functions, not
 * modules — nothing is imported until `loadHelpGuide` actually calls one.
 */
const guideLoaders = import.meta.glob<{ default: HelpGuide }>('./content/*/*.ts')

/** Narrows an i18next language tag (e.g. `it-IT`) to a supported help locale. */
export function normalizeHelpLocale(language: string | null | undefined): HelpLocale {
  const primary = language?.slice(0, 2).toLowerCase()
  if (primary === 'it' || primary === 'en') {
    return primary
  }
  return fallbackLocale as HelpLocale
}

/** Loads one guide's content, or `null` when the key has no authored file yet. */
export async function loadHelpGuide(
  locale: HelpLocale,
  key: HelpGuideKey,
): Promise<HelpGuide | null> {
  const loader = guideLoaders[`./content/${locale}/${key}.ts`]
  if (!loader) {
    return null
  }
  const module = await loader()
  return module.default
}

export const helpQueryKeys = {
  guide: (locale: HelpLocale, key: HelpGuideKey) => ['help-guide', locale, key] as const,
}
