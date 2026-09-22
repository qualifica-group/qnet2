import { useTranslation } from 'react-i18next'
import { Skeleton } from '@/components/ui/skeleton'
import type { HelpSearchResult } from '@/features/help/help-search-utils'

interface HelpSearchResultsProps {
  results: HelpSearchResult[]
  isLoading: boolean
  onSelect: (result: HelpSearchResult) => void
}

/** Search hits as "Guide title › Section title" (AC-005), or the empty-state message. */
export function HelpSearchResults({ results, isLoading, onSelect }: HelpSearchResultsProps) {
  const { t } = useTranslation()

  if (isLoading) {
    return (
      <div className="flex flex-col gap-2">
        <span className="sr-only" role="status">
          {t('common.loading')}
        </span>
        <div aria-hidden="true" className="flex flex-col gap-2">
          <Skeleton className="h-8 w-full" />
          <Skeleton className="h-8 w-full" />
        </div>
      </div>
    )
  }

  if (results.length === 0) {
    return (
      <p className="px-1 text-sm text-muted-foreground" role="status">
        {t('help.searchNoResults')}
      </p>
    )
  }

  return (
    <ul className="flex flex-col gap-0.5">
      {results.map((result, index) => (
        <li key={`${result.guideKey}-${result.sectionId}-${index}`}>
          <button
            type="button"
            onClick={() => onSelect(result)}
            className="w-full truncate rounded-md px-2 py-1.5 text-left text-sm text-foreground hover:bg-muted/60"
          >
            {result.guideTitle} › {result.sectionTitle}
          </button>
        </li>
      ))}
    </ul>
  )
}
