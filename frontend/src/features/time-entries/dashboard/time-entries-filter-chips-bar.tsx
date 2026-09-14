/**
 * Active filter chips row of the "Periodo" card (spec 0122 D-13, AC-033):
 * one dismissible chip per active drawer filter, plus a "Ripristina filtri"
 * action. Renders nothing when no filter is active — mirrors q-net's
 * `filterChipsSlot`, only shown when `activeFilterChips.length > 0`.
 */

import { useTranslation } from 'react-i18next'
import { RotateCcw, X } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useTimeEntriesFilterChips } from '@/features/time-entries/dashboard/use-time-entries-filter-chips'
import type { TimeEntriesFiltersState } from '@/features/time-entries/time-entries-filters'

interface TimeEntriesFilterChipsBarProps {
  filters: TimeEntriesFiltersState
  onRemoveChip: (key: string) => void
  onReset: () => void
}

export function TimeEntriesFilterChipsBar({ filters, onRemoveChip, onReset }: TimeEntriesFilterChipsBarProps) {
  const { t } = useTranslation()
  const chips = useTimeEntriesFilterChips(filters)

  if (chips.length === 0) {
    return null
  }

  return (
    <div className="flex flex-wrap items-center gap-1.5" data-slot="time-entries-filter-chips">
      {chips.map((chip) => (
        <Badge key={chip.key} variant="secondary" className="gap-1 pl-2">
          {chip.label}
          <button
            type="button"
            aria-label={`${t('timeEntries.filters.removeSelection')} ${chip.label}`}
            className="rounded-sm p-0.5 outline-none hover:text-foreground focus-visible:ring-[2px] focus-visible:ring-ring/50"
            onClick={() => onRemoveChip(chip.key)}
          >
            <X className="size-3" aria-hidden="true" />
          </button>
        </Badge>
      ))}
      <Button type="button" variant="ghost" size="sm" onClick={onReset} className="h-6 gap-1 px-2 text-xs">
        <RotateCcw className="size-3.5" aria-hidden="true" />
        {t('timeEntries.filters.reset')}
      </Button>
    </div>
  )
}
