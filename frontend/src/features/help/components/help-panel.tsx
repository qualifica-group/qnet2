import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { normalizeHelpLocale } from '@/features/help/help-content-loader'
import { useHelpVisibleGuides } from '@/features/help/hooks/use-help-visible-guides'
import { useHelpCurrentGuideKey } from '@/features/help/hooks/use-help-current-guide-key'
import { useHelpSearch } from '@/features/help/hooks/use-help-search'
import { HelpSearchBox } from '@/features/help/components/help-search-box'
import { HelpSearchResults } from '@/features/help/components/help-search-results'
import { HelpGuideIndex } from '@/features/help/components/help-guide-index'
import { HelpGuideView } from '@/features/help/components/help-guide-view'
import type { HelpSearchResult } from '@/features/help/help-search-utils'

type HelpPanelTab = 'page' | 'index'

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
  const [activeTab, setActiveTab] = useState<HelpPanelTab>('page')
  const [scrollToSectionId, setScrollToSectionId] = useState<string | null>(null)

  const search = useHelpSearch(locale, guides, query)
  const activeKey = selectedKey ?? currentGuideKey
  const activeGuideIcon = guides.find((guide) => guide.key === activeKey)?.icon ?? null

  // Step 1: opening a guide (from the index or a search hit) always switches
  // to the "this page" tab, landing on that guide's content (AC-015).
  const openGuide = (key: string, sectionId: string | null = null) => {
    setSelectedKey(key)
    setActiveTab('page')
    setQuery('')
    setScrollToSectionId(sectionId)
  }

  const handleSearchResultSelect = (result: HelpSearchResult) => {
    openGuide(result.guideKey, result.sectionId)
  }

  // Step 2: search stays visible above both tabs and, once active (>= 2
  // chars), its results replace whichever tab's content is on screen.
  const searchResults = search.isActive ? (
    <HelpSearchResults results={search.results} isLoading={search.isLoading} onSelect={handleSearchResultSelect} />
  ) : null

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      {trigger ? <SheetTrigger asChild>{trigger}</SheetTrigger> : null}
      <SheetContent className="gap-0" storageKey="sheet-width:help">
        <SheetHeader>
          <SheetTitle>{t('help.title')}</SheetTitle>
          <SheetDescription>{t('help.description')}</SheetDescription>
        </SheetHeader>

        <div className="border-b border-border px-4 pb-3">
          <HelpSearchBox value={query} onChange={setQuery} />
        </div>

        <Tabs
          value={activeTab}
          onValueChange={(value) => setActiveTab(value as HelpPanelTab)}
          className="min-h-0 flex-1 gap-0 px-4 pt-3"
        >
          <TabsList>
            <TabsTrigger value="page">{t('help.tabCurrentPage')}</TabsTrigger>
            <TabsTrigger value="index">{t('help.tabAllGuides')}</TabsTrigger>
          </TabsList>
          <TabsContent value="page" className="mt-3 min-h-0 overflow-y-auto">
            {searchResults ?? (
              <HelpGuideView
                locale={locale}
                guideKey={activeKey}
                guideIcon={activeGuideIcon}
                scrollToSectionId={scrollToSectionId}
                onScrolled={() => setScrollToSectionId(null)}
              />
            )}
          </TabsContent>
          <TabsContent value="index" className="mt-3 min-h-0 overflow-y-auto">
            {searchResults ?? (
              <HelpGuideIndex guides={guides} currentGuideKey={currentGuideKey} onSelect={openGuide} />
            )}
          </TabsContent>
        </Tabs>
      </SheetContent>
    </Sheet>
  )
}
