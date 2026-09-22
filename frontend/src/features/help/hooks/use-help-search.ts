import { useMemo } from 'react'
import { useQueries } from '@tanstack/react-query'
import { helpQueryKeys, loadHelpGuide, type HelpLocale } from '@/features/help/help-content-loader'
import { HELP_SEARCH_MIN_LENGTH } from '@/features/help/help-text-normalize'
import { searchHelpGuides, type HelpSearchResult } from '@/features/help/help-search-utils'
import type { HelpVisibleGuide } from '@/features/help/help-visible-guides'
import type { HelpGuide } from '@/features/help/types'

const EMPTY_RESULTS: HelpSearchResult[] = []

interface UseHelpSearchResult {
  /** `false` below the 2-character threshold (AC-005): search stays inactive. */
  isActive: boolean
  results: HelpSearchResult[]
  isLoading: boolean
}

/**
 * Searches the text of every visible guide (AC-005). The guides are fetched
 * via the same cache key `useHelpGuide` uses, so opening a result reuses the
 * data already downloaded for the search instead of fetching it again.
 */
export function useHelpSearch(
  locale: HelpLocale,
  visibleGuides: readonly HelpVisibleGuide[],
  query: string,
): UseHelpSearchResult {
  const trimmedQuery = query.trim()
  const isActive = trimmedQuery.length >= HELP_SEARCH_MIN_LENGTH

  const queries = useQueries({
    queries: visibleGuides.map((guide) => ({
      queryKey: helpQueryKeys.guide(locale, guide.key),
      queryFn: () => loadHelpGuide(locale, guide.key),
      staleTime: Infinity,
      enabled: isActive,
    })),
  })

  const results = useMemo(() => {
    if (!isActive) {
      return EMPTY_RESULTS
    }
    const guides = queries
      .map((result) => result.data)
      .filter((guide): guide is HelpGuide => guide != null)
    return searchHelpGuides(guides, trimmedQuery)
  }, [isActive, queries, trimmedQuery])

  return {
    isActive,
    results,
    isLoading: isActive && queries.some((result) => result.isLoading),
  }
}
