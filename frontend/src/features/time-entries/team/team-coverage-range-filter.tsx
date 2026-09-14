/**
 * Coverage range filter of the team view (spec 0122 D-10), q-net parity
 * (`coverage-range-filter.tsx:68-77`): one double-handle `Slider`, matching
 * the shared `components/ui/slider.tsx` `thumbLabels` extension (MT-U2) that
 * renders one `Thumb` per `value` entry.
 */

import { X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Slider } from '@/components/ui/slider'
import { cn } from '@/lib/utils'
import { TIME_ENTRY_COVERAGE_MAX_PERCENTAGE } from '@/features/time-entries/time-entry-constants'
import { isTeamCoverageRangeActive } from '@/features/time-entries/team/team-tree'

interface TeamCoverageRangeFilterProps {
  value: [number, number]
  onChange: (value: [number, number]) => void
  className?: string
}

function formatRangeLabel(range: [number, number]): string {
  const upper = range[1] >= TIME_ENTRY_COVERAGE_MAX_PERCENTAGE ? '100%+' : `${range[1]}%`
  return `${range[0]}% – ${upper}`
}

export function TeamCoverageRangeFilter({ value, onChange, className }: TeamCoverageRangeFilterProps) {
  const { t } = useTranslation()
  const isActive = isTeamCoverageRangeActive(value)

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          type="button"
          variant="outline"
          className={cn('h-9 w-full justify-between font-normal', !isActive && 'text-muted-foreground', className)}
        >
          <span className="truncate">
            {isActive ? `${t('timeEntries.pulse.coverage')}: ${formatRangeLabel(value)}` : t('timeEntries.pulse.coverage')}
          </span>
          {isActive ? (
            <span
              role="button"
              tabIndex={0}
              aria-label={t('common.clear')}
              onClick={(event) => {
                event.preventDefault()
                event.stopPropagation()
                onChange([0, TIME_ENTRY_COVERAGE_MAX_PERCENTAGE])
              }}
              onKeyDown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                  event.preventDefault()
                  event.stopPropagation()
                  onChange([0, TIME_ENTRY_COVERAGE_MAX_PERCENTAGE])
                }
              }}
              className="ml-2 rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
            >
              <X className="size-3.5" />
            </span>
          ) : null}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-72 space-y-3">
        <div className="flex items-center justify-between text-xs font-medium text-muted-foreground">
          <span>{t('timeEntries.team.coverageRange')}</span>
          <span className="font-semibold text-foreground">{formatRangeLabel(value)}</span>
        </div>
        <Slider
          min={0}
          max={TIME_ENTRY_COVERAGE_MAX_PERCENTAGE}
          step={1}
          minStepsBetweenThumbs={1}
          value={value}
          thumbLabels={[t('timeEntries.team.coverageMin'), t('timeEntries.team.coverageMax')]}
          onValueChange={(values) => {
            if (values.length >= 2) {
              onChange([values[0], values[1]])
            }
          }}
        />
        <div className="flex justify-between text-[11px] text-muted-foreground">
          <span>0%</span>
          <span>100%+</span>
        </div>
      </PopoverContent>
    </Popover>
  )
}
