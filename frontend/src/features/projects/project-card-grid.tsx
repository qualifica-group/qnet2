import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { SlidersHorizontal } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { AdvancedFilterPanel, ADVANCED_FILTER_PANEL_ANIMATION } from '@/features/table/advanced-filters/advanced-filter-panel'
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible'
import { useTableAdvancedFilters } from '@/features/table/advanced-filters/use-table-advanced-filters'
import { useTableConfig } from '@/features/table/use-table-config'
import { projectCardsQueryKeyPrefix } from '@/features/projects/api'
import { ProjectCard } from '@/features/projects/project-card'
import { ProjectCardSkeleton } from '@/features/projects/project-card-skeleton'
import { ProjectDetailScreen } from '@/features/projects/project-screens'
import { useProjectCards } from '@/features/projects/use-project-cards'

const INITIAL_SKELETON_COUNT = 6
const NEXT_PAGE_SKELETON_COUNT = 3

/** Domain key, shared with the table view so both Sheets restore the same width. */
const PROJECTS_DOMAIN = 'projects'

/**
 * Card grid body of the projects page (spec 0026 AC-007/009). Infinite
 * scroll via a bottom sentinel observed against the page viewport (`root:
 * null`, unlike the bounded notifications panel this pattern mirrors —
 * `features/notifications/notification-list.tsx`), with the loading / error /
 * empty triad and skeleton cards while fetching.
 *
 * Advanced filters (spec 0032 AC-018): reuses the SAME `AdvancedFilterPanel` +
 * `useTableAdvancedFilters` the AG Grid view (`TableView`, mounted by
 * `ProjectsTable`) uses, off the SAME `useTableConfig('projects')` cache and
 * the SAME backend-persisted applied state — so switching between the two
 * mutually-exclusive views always resumes the last-applied filters. Unlike
 * the SSRM grid, this view is TanStack-driven: `advancedFilters.activeValues`
 * is part of `useProjectCards`'s query key, so Apply/Reset need no imperative
 * refresh — the changed key alone triggers a refetch.
 */
export function ProjectCardGrid() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const invalidateStats = useInvalidateModuleStats(PROJECTS_DOMAIN)
  const sentinelRef = useRef<HTMLDivElement>(null)

  // The card's pencil opens the project's detail, which edits in place (spec 0220 D-4).
  const [openedId, setOpenedId] = useState<number | null>(null)
  const [filtersOpen, setFiltersOpen] = useState(false)

  const { data: tableConfig } = useTableConfig(PROJECTS_DOMAIN)
  const { descriptors: advancedFilterDescriptors, filters: advancedFilters } =
    useTableAdvancedFilters({
      domain: PROJECTS_DOMAIN,
      descriptors: tableConfig?.advancedFilters,
      applied: tableConfig?.appliedAdvancedFilters,
      onApplied: () => {},
    })

  const { data, isPending, isError, refetch, hasNextPage, isFetchingNextPage, fetchNextPage } =
    useProjectCards({ advancedFilters: advancedFilters.activeValues })

  const cards = useMemo(() => data?.pages.flatMap((page) => page.items) ?? [], [data])

  // Load the next page when the bottom sentinel scrolls into the viewport.
  useEffect(() => {
    const sentinel = sentinelRef.current
    if (!sentinel || !hasNextPage) {
      return
    }

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasNextPage && !isFetchingNextPage) {
          void fetchNextPage()
        }
      },
      { rootMargin: '0px 0px 200px 0px' },
    )
    observer.observe(sentinel)

    return () => observer.disconnect()
  }, [hasNextPage, isFetchingNextPage, fetchNextPage, cards.length])

  const closeDetail = useCallback(() => setOpenedId(null), [])

  // An in-place save keeps the detail open: only the cards and the stats refresh.
  const handleDetailChanged = useCallback(() => {
    void queryClient.invalidateQueries({ queryKey: projectCardsQueryKeyPrefix() })
    invalidateStats()
  }, [queryClient, invalidateStats])

  let body: ReactNode
  if (isError) {
    body = (
      <div className="flex flex-col items-center gap-2 py-10 text-center">
        <p className="text-sm text-muted-foreground">{t('projects.grid.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => void refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  } else if (isPending) {
    body = (
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {Array.from({ length: INITIAL_SKELETON_COUNT }).map((_, index) => (
          <ProjectCardSkeleton key={index} />
        ))}
      </div>
    )
  } else if (cards.length === 0) {
    body = <p className="py-10 text-center text-sm text-muted-foreground">{t('projects.grid.empty')}</p>
  } else {
    body = (
      <div className="flex flex-col gap-3">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {cards.map((project) => (
            <ProjectCard key={project.id} project={project} onEdit={setOpenedId} />
          ))}
          {isFetchingNextPage
            ? Array.from({ length: NEXT_PAGE_SKELETON_COUNT }).map((_, index) => (
                <ProjectCardSkeleton key={`next-${index}`} />
              ))
            : null}
        </div>
        {/* Observed to trigger the next page; only mounted while one is available. */}
        {hasNextPage ? <div ref={sentinelRef} aria-hidden="true" /> : null}
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-3">
      {advancedFilterDescriptors.length > 0 ? (
        <div className="flex items-center justify-end">
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="relative gap-1.5"
            aria-label={t('table.advancedFilters.toggle')}
            aria-pressed={filtersOpen}
            onClick={() => setFiltersOpen((value) => !value)}
          >
            <SlidersHorizontal aria-hidden="true" className="size-3.5" />
            {/* Visible label duplicates the button's own aria-label; harmless
                for AT (same accessible name either way) and keeps the
                affordance readable at a glance, unlike the icon-only toolbar
                variant. */}
            <span aria-hidden="true">{t('table.advancedFilters.toggle')}</span>
            {advancedFilters.activeCount > 0 ? (
              <Badge
                variant="destructive"
                className="h-4 min-w-4 justify-center rounded-full px-1 text-[10px] tabular-nums"
                aria-label={t('table.advancedFilters.activeCount', {
                  count: advancedFilters.activeCount,
                })}
              >
                {advancedFilters.activeCount}
              </Badge>
            ) : null}
          </Button>
        </div>
      ) : null}

      {advancedFilterDescriptors.length > 0 ? (
        <Collapsible open={filtersOpen}>
          <CollapsibleContent className={ADVANCED_FILTER_PANEL_ANIMATION}>
            <div className="overflow-hidden rounded-xl border border-border">
              <AdvancedFilterPanel descriptors={advancedFilterDescriptors} filters={advancedFilters} />
            </div>
          </CollapsibleContent>
        </Collapsible>
      ) : null}

      {body}

      <Sheet open={openedId !== null} onOpenChange={(open) => (open ? undefined : closeDetail())}>
        <SheetContent className="gap-0" storageKey={`sheet-width:${PROJECTS_DOMAIN}`}>
          <SheetHeader className="sr-only">
            <SheetTitle>{t('projects.detail.title')}</SheetTitle>
            <SheetDescription>{t('projects.detail.subtitle')}</SheetDescription>
          </SheetHeader>
          {openedId !== null ? <ProjectDetailScreen id={openedId} onChanged={handleDetailChanged} /> : null}
        </SheetContent>
      </Sheet>
    </div>
  )
}
