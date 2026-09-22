import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ListTree } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet'
import { normalizeHelpLocale } from '@/features/help/help-content-loader'
import { useHelpVisibleGuides } from '@/features/help/hooks/use-help-visible-guides'
import { useHelpCurrentGuideKey } from '@/features/help/hooks/use-help-current-guide-key'
import { useHelpSearch } from '@/features/help/hooks/use-help-search'
import { HelpSearchBox } from '@/features/help/components/help-search-box'
import { HelpSearchResults } from '@/features/help/components/help-search-results'
import { HelpGuideIndex } from '@/features/help/components/help-guide-index'
import { HelpGuideView } from '@/features/help/components/help-guide-view'
import type { HelpSearchResult } from '@/features/help/help-search-utils'

interface HelpPanelProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /**
   * The header icon button, rendered inside a real `SheetTrigger`: Radix
   * needs the actual trigger element (not just a controlled `open` prop) to
   * return focus to it when the panel closes (AC-010). Omitted in tests that
   * exercise the panel's content on its own.
   */
  trigger?: ReactNode
}

/**
 * Right-side help panel (spec 0143): the current module's guide by default,
 * an index of every visible guide, and a search across all of them.
 */
export function HelpPanel({ open, onOpenChange, trigger }: HelpPanelProps) {
  const { t, i18n } = useTranslation()
  const locale = normalizeHelpLocale(i18n.language)
  const { guides } = useHelpVisibleGuides()
  const currentGuideKey = useHelpCurrentGuideKey(guides)

  const [query, setQuery] = useState('')
  const [selectedKey, setSelectedKey] = useState<string | null>(null)
  const [showIndex, setShowIndex] = useState(false)
  const [scrollToSectionId, setScrollToSectionId] = useState<string | null>(null)

  const search = useHelpSearch(locale, guides, query)
  const activeKey = selectedKey ?? currentGuideKey

  // Step 1: opening a guide (from the index or a search hit) always leaves
  // both the index and the search behind, landing on that guide's content.
  const openGuide = (key: string, sectionId: string | null = null) => {
    setSelectedKey(key)
    setShowIndex(false)
    setQuery('')
    setScrollToSectionId(sectionId)
  }

  const handleSearchResultSelect = (result: HelpSearchResult) => {
    openGuide(result.guideKey, result.sectionId)
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      {trigger ? <SheetTrigger asChild>{trigger}</SheetTrigger> : null}
      <SheetContent className="gap-0" storageKey="sheet-width:help">
        <SheetHeader>
          <SheetTitle>{t('help.title')}</SheetTitle>
          <SheetDescription>{t('help.description')}</SheetDescription>
        </SheetHeader>

        <div className="flex flex-col gap-2 border-b border-border px-4 pb-3">
          <HelpSearchBox value={query} onChange={setQuery} />
          {!search.isActive && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="w-fit text-xs"
              onClick={() => setShowIndex(true)}
            >
              <ListTree className="size-3.5" aria-hidden="true" />
              {t('help.allGuides')}
            </Button>
          )}
        </div>

        <div className="flex-1 overflow-y-auto p-4">
          {search.isActive ? (
            <HelpSearchResults
              results={search.results}
              isLoading={search.isLoading}
              onSelect={handleSearchResultSelect}
            />
          ) : showIndex ? (
            <HelpGuideIndex guides={guides} onSelect={openGuide} />
          ) : (
            <HelpGuideView
              locale={locale}
              guideKey={activeKey}
              scrollToSectionId={scrollToSectionId}
              onScrolled={() => setScrollToSectionId(null)}
            />
          )}
        </div>
      </SheetContent>
    </Sheet>
  )
}
