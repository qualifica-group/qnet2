import { useQuery } from '@tanstack/react-query'
import { helpQueryKeys, loadHelpGuide, type HelpLocale } from '@/features/help/help-content-loader'
import type { HelpGuideKey } from '@/features/help/types'

/**
 * A single guide's content, lazy-loaded once per (locale, key) and cached
 * forever (`staleTime: Infinity`): the text is static per release, so there
 * is nothing to ever refetch or invalidate.
 */
export function useHelpGuide(locale: HelpLocale, key: HelpGuideKey) {
  return useQuery({
    queryKey: helpQueryKeys.guide(locale, key),
    queryFn: () => loadHelpGuide(locale, key),
    staleTime: Infinity,
  })
}
