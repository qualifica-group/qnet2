/**
 * "Periodo" card (spec 0122 D-13, AC-032/AC-033/AC-039): header + filters
 * trigger + export menu, active filter chips, prev/next/preset/custom-range
 * navigation, and an optional footer slot (the view switch, D-10). Entirely
 * driven by the `useTimeEntriesFilters` result passed in via `filters` and
 * the list request's `meta` — no state of its own besides the drawer's own
 * open flag. Mirrors q-net's `WorkActivitiesPeriodSelector` (structure/
 * density/colors, D-2).
 */

import { useMemo, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Filter } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'
import { TimeEntriesExportMenu } from '@/features/time-entries/dashboard/time-entries-export-menu'
import { TimeEntriesFilterChipsBar } from '@/features/time-entries/dashboard/time-entries-filter-chips-bar'
import { TimeEntriesFiltersDrawer } from '@/features/time-entries/dashboard/time-entries-filters-drawer'
import { TimeEntriesPeriodNav } from '@/features/time-entries/dashboard/time-entries-period-nav'
import { buildTimeEntriesFilterParams, countActiveTimeEntriesFilters } from '@/features/time-entries/time-entries-filters'
import type { UseTimeEntriesFiltersResult } from '@/features/time-entries/use-time-entries-filters'
import type { TimeEntriesListMeta } from '@/features/time-entries/types'

interface TimeEntriesPeriodCardProps {
  filters: UseTimeEntriesFiltersResult
  meta?: TimeEntriesListMeta | null
  isLoading?: boolean
  footerSlot?: ReactNode
  className?: string
}

export function TimeEntriesPeriodCard({ filters, meta, isLoading = false, footerSlot, className }: TimeEntriesPeriodCardProps) {
  const { t } = useTranslation()
  const [drawerOpen, setDrawerOpen] = useState(false)

  const filterParams = useMemo(() => buildTimeEntriesFilterParams(filters.filters), [filters.filters])
  const activeFiltersCount = countActiveTimeEntriesFilters(filters.filters)

  if (isLoading) {
    return (
      <Card className={cn('gap-2 py-3 shadow-sm', className)}>
        <CardHeader>
          <div className="flex flex-wrap items-start justify-between gap-2">
            <div className="min-w-0 space-y-1.5">
              <Skeleton className="h-4 w-20" />
              <Skeleton className="h-3 w-72 max-w-full" />
            </div>
            <div className="flex items-center gap-2">
              <Skeleton className="size-9 rounded-md" />
              <Skeleton className="size-9 rounded-md" />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex flex-wrap items-center gap-2">
              <Skeleton className="h-10 w-20 rounded-md" />
              <Skeleton className="h-10 w-56 rounded-md" />
              <Skeleton className="h-10 w-20 rounded-md" />
            </div>
            <Skeleton className="h-10 w-80 rounded-md" />
          </div>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card className={cn('gap-2 py-3 shadow-sm', className)}>
      <CardHeader>
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div className="min-w-0">
            <CardTitle className="text-base">{t('timeEntries.period.cardTitle')}</CardTitle>
            <CardDescription className="text-xs">{t('timeEntries.period.cardDescription')}</CardDescription>
          </div>
          <div className="flex items-center gap-2">
            <Button
              aria-label={t('timeEntries.filters.title')}
              type="button"
              variant="outline"
              size="icon"
              className="relative"
              onClick={() => setDrawerOpen(true)}
            >
              <Filter className="size-4" aria-hidden="true" />
              {activeFiltersCount > 0 ? (
                <Badge
                  variant="default"
                  className="absolute -top-1.5 -right-1.5 h-4 min-w-4 justify-center rounded-full px-1 text-[10px]"
                >
                  {activeFiltersCount}
                </Badge>
              ) : null}
            </Button>
            <TimeEntriesExportMenu
              canExport={meta?.can_export ?? false}
              canExportMonthly={meta?.can_export_monthly ?? false}
              filteredParams={filterParams}
              defaultUserId={meta?.selected_user?.id ?? null}
              anchorDate={filters.anchorDate}
            />
          </div>
        </div>
        <div className="mt-1 w-full">
          <TimeEntriesFilterChipsBar
            filters={filters.filters}
            onRemoveChip={filters.removeFilterChip}
            onReset={filters.resetFilters}
          />
        </div>
      </CardHeader>

      <CardContent>
        <TimeEntriesPeriodNav
          navigationUnit={filters.navigationUnit}
          anchorDate={filters.anchorDate}
          dateFrom={filterParams.date_from}
          dateTo={filterParams.date_to}
          onSelectPreset={filters.selectPeriodPreset}
          onNavigatePrevious={filters.goToPreviousPeriod}
          onNavigateNext={filters.goToNextPeriod}
          onNavigateToday={filters.goToToday}
          onCustomDateRangeChange={({ from, to }) => filters.setCustomDateRange(from, to)}
        />
      </CardContent>

      {footerSlot ? (
        <div className="relative -mb-3 overflow-hidden rounded-b-xl">
          <div aria-hidden="true" className="h-px bg-gradient-to-r from-transparent via-primary/30 to-transparent" />
          <div className="bg-gradient-to-b from-muted/30 to-muted/10 px-4 py-3">{footerSlot}</div>
        </div>
      ) : null}

      <TimeEntriesFiltersDrawer
        open={drawerOpen}
        onOpenChange={setDrawerOpen}
        filters={filters.filters}
        onFiltersChange={filters.setFilters}
        onReset={filters.resetFilters}
        canFilterUsers={meta?.can_filter_users ?? false}
      />
    </Card>
  )
}
