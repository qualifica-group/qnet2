import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigation } from '@/features/navigation/use-navigation'
import type { NavigationItem } from '@/features/navigation/types'
import { GENERAL_HELP_KEY } from '@/features/help/help-guide-keys'
import { flattenVisibleHelpGuides, type HelpVisibleGuide } from '@/features/help/help-visible-guides'

const EMPTY_ITEMS: NavigationItem[] = []

/**
 * The guides the current user may browse: `general` (always first, not part
 * of the menu) followed by every visible module guide, in menu order
 * (AC-004).
 */
export function useHelpVisibleGuides(): { guides: HelpVisibleGuide[]; isLoading: boolean } {
  const { t } = useTranslation()
  const navigationQuery = useNavigation()
  const items = navigationQuery.data ?? EMPTY_ITEMS

  const guides = useMemo<HelpVisibleGuide[]>(() => {
    const general: HelpVisibleGuide = {
      key: GENERAL_HELP_KEY,
      label: t('help.generalGuideTitle'),
      route: null,
      groupLabel: null,
    }
    return [general, ...flattenVisibleHelpGuides(items, t)]
  }, [items, t])

  return { guides, isLoading: navigationQuery.isLoading }
}
