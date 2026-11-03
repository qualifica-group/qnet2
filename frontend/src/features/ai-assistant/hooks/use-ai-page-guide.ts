import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { GENERAL_HELP_KEY } from '@/features/help/help-guide-keys'
import { normalizeHelpLocale } from '@/features/help/help-content-loader'
import { useHelpCurrentGuideKey } from '@/features/help/hooks/use-help-current-guide-key'
import { useHelpGuide } from '@/features/help/hooks/use-help-guide'
import { useHelpVisibleGuides } from '@/features/help/hooks/use-help-visible-guides'
import { serializeHelpGuide } from '@/features/ai-assistant/guide-text'

export interface AiPageGuide {
  locale: 'it' | 'en'
  /** Key of the guide sent to the assistant, `null` while it is unresolved. */
  guideKey: string | null
  /** Plain-text guide truncated to `maxChars`, `null` until loaded or if none exists. */
  guideText: string | null
}

/**
 * The in-app guide of the current page, serialized for the assistant
 * (AC-024). `forceGeneral` pins the `general` guide (the /ai-assistant page).
 */
export function useAiPageGuide(maxChars: number, forceGeneral = false): AiPageGuide {
  const { i18n } = useTranslation()
  const locale = normalizeHelpLocale(i18n.language)
  const { guides } = useHelpVisibleGuides()
  const currentKey = useHelpCurrentGuideKey(guides)
  const guideKey = forceGeneral ? GENERAL_HELP_KEY : currentKey
  const guideQuery = useHelpGuide(locale, guideKey)

  const guide = guideQuery.data ?? null
  const guideText = useMemo(
    () => (guide ? serializeHelpGuide(guide, maxChars) : null),
    [guide, maxChars],
  )

  return { locale, guideKey: guide ? guideKey : null, guideText }
}
