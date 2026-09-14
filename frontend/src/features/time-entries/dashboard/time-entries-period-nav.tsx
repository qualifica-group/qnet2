/**
 * Period navigation row of the "Periodo" card (spec 0122 D-13, AC-032):
 * prev/next/today, the resolved period label, the preset control and the
 * custom-range popover. Mirrors q-net's `WorkActivitiesPeriodSelector`
 * `CardContent` 1:1 (structure/density, D-2), split out of the card shell so
 * the card itself stays focused on layout/actions/chips/footer.
 */

import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { CalendarDays, CalendarSearch, ChevronLeft, ChevronRight } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { cn } from '@/lib/utils'
import { TimeEntriesPeriodPresetControl } from '@/features/time-entries/dashboard/time-entries-period-preset-control'
import {
  formatPeriodLabel,
  isSamePeriodAsToday,
  parseDateString,
  type TimeEntriesPeriodUnit,
} from '@/features/time-entries/time-entry-period'

interface TimeEntriesPeriodNavProps {
  navigationUnit: TimeEntriesPeriodUnit | null
  anchorDate: string
  dateFrom?: string
  dateTo?: string
  onSelectPreset: (unit: TimeEntriesPeriodUnit) => void
  onNavigatePrevious: () => void
  onNavigateNext: () => void
  onNavigateToday: () => void
  onCustomDateRangeChange: (range: { from: string; to: string }) => void
}

function formatFallbackDate(value: string | undefined, locale: string): string {
  const parsed = parseDateString(value)
  return parsed ? new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(parsed) : ''
}

export function TimeEntriesPeriodNav({
  navigationUnit,
  anchorDate,
  dateFrom,
  dateTo,
  onSelectPreset,
  onNavigatePrevious,
  onNavigateNext,
  onNavigateToday,
  onCustomDateRangeChange,
}: TimeEntriesPeriodNavProps) {
  const { t, i18n } = useTranslation()
  const [customFrom, setCustomFrom] = useState(dateFrom ?? '')
  const [customTo, setCustomTo] = useState(dateTo ?? '')

  // Reset the draft fields to the live range each time the popover OPENS
  // (event-driven, not an effect syncing on every `dateFrom`/`dateTo` render —
  // react-hooks/set-state-in-effect flags setState-in-effect as cascading).
  const resetDraftRange = (open: boolean) => {
    if (open) {
      setCustomFrom(dateFrom ?? '')
      setCustomTo(dateTo ?? '')
    }
  }

  const anchorAsDate = useMemo(() => parseDateString(anchorDate) ?? new Date(), [anchorDate])
  const periodLabel = navigationUnit ? formatPeriodLabel(navigationUnit, anchorAsDate, i18n.language) : null
  const isAnchorToday = navigationUnit ? isSamePeriodAsToday(navigationUnit, anchorAsDate) : true

  const fallbackFrom = formatFallbackDate(dateFrom, i18n.language)
  const fallbackTo = formatFallbackDate(dateTo, i18n.language)
  const fallbackLabel =
    fallbackFrom && fallbackTo ? `${fallbackFrom} – ${fallbackTo}` : fallbackFrom || fallbackTo || '—'
  const displayLabel = periodLabel ?? fallbackLabel

  return (
    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <div className="flex flex-wrap items-center gap-2">
        <div className="inline-flex items-center rounded-md border border-field-border bg-field p-1">
          <Button
            aria-label={t('timeEntries.period.previous')}
            type="button"
            variant="ghost"
            disabled={!navigationUnit}
            onClick={onNavigatePrevious}
            className="h-8 rounded-sm px-2 text-xs text-muted-foreground hover:bg-accent hover:text-foreground"
          >
            <ChevronLeft className="size-3.5" aria-hidden="true" />
          </Button>
          <Button
            aria-label={t('timeEntries.period.next')}
            type="button"
            variant="ghost"
            disabled={!navigationUnit}
            onClick={onNavigateNext}
            className="h-8 rounded-sm px-2 text-xs text-muted-foreground hover:bg-accent hover:text-foreground"
          >
            <ChevronRight className="size-3.5" aria-hidden="true" />
          </Button>
        </div>

        <div className="inline-flex h-10 min-w-0 items-center gap-2 rounded-md border border-field-border bg-field px-3 sm:min-w-[220px]">
          <CalendarDays className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
          <span className="min-w-0 truncate text-xs font-semibold text-foreground">{displayLabel}</span>
        </div>

        <div className="inline-flex items-center rounded-md border border-field-border bg-field p-1">
          <Button
            type="button"
            variant="ghost"
            disabled={!navigationUnit || isAnchorToday}
            onClick={onNavigateToday}
            className={cn(
              'h-8 gap-1.5 rounded-sm px-3 text-xs transition-colors',
              !navigationUnit || isAnchorToday
                ? 'text-muted-foreground/60'
                : 'text-muted-foreground hover:bg-accent hover:text-foreground',
            )}
          >
            {t('timeEntries.period.today')}
          </Button>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <TimeEntriesPeriodPresetControl
          className="min-w-0 flex-1 sm:w-auto sm:flex-initial"
          value={navigationUnit}
          onChange={onSelectPreset}
        />

        <div className="inline-flex shrink-0 items-center rounded-md border border-field-border bg-field p-1">
          <Popover onOpenChange={resetDraftRange}>
            <PopoverTrigger asChild>
              <Button
                aria-label={t('timeEntries.period.pickCustomRange')}
                type="button"
                variant="ghost"
                className={cn(
                  'h-8 gap-2 rounded-sm px-3 text-xs transition-colors',
                  !navigationUnit
                    ? 'bg-primary text-primary-foreground shadow-sm hover:bg-primary/90 hover:text-primary-foreground'
                    : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                )}
              >
                <CalendarSearch className="size-3.5 shrink-0" aria-hidden="true" />
              </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-72 space-y-3 p-3">
              <div className="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                {t('timeEntries.period.customRange')}
              </div>
              <div className="space-y-2">
                <label className="block space-y-1">
                  <span className="text-xs font-medium text-muted-foreground">{t('timeEntries.period.fromDate')}</span>
                  <Input type="date" value={customFrom} onChange={(event) => setCustomFrom(event.target.value)} />
                </label>
                <label className="block space-y-1">
                  <span className="text-xs font-medium text-muted-foreground">{t('timeEntries.period.toDate')}</span>
                  <Input type="date" value={customTo} onChange={(event) => setCustomTo(event.target.value)} />
                </label>
              </div>
              <Button
                type="button"
                className="w-full"
                onClick={() => onCustomDateRangeChange({ from: customFrom, to: customTo })}
              >
                {t('timeEntries.period.apply')}
              </Button>
            </PopoverContent>
          </Popover>
        </div>
      </div>
    </div>
  )
}
