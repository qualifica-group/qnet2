/**
 * Period unit segmented control (spec 0122 D-13, AC-032): a pill row on
 * desktop, a `Select` under `md` (ui-design.md §2 compact defaults). Mirrors
 * q-net's `WorkActivitiesPeriodPresetControl` 1:1 (structure/density, D-2).
 */

import { useTranslation } from 'react-i18next'
import { CalendarCheck2, CalendarClock, CalendarDays, CalendarRange, type LucideIcon } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { cn } from '@/lib/utils'
import type { TimeEntriesPeriodUnit } from '@/features/time-entries/time-entry-period'

interface PeriodPresetOption {
  value: TimeEntriesPeriodUnit
  labelKey: string
  icon: LucideIcon
}

const PERIOD_PRESET_OPTIONS: readonly PeriodPresetOption[] = [
  { value: 'day', labelKey: 'day', icon: CalendarClock },
  { value: 'week', labelKey: 'week', icon: CalendarRange },
  { value: 'month', labelKey: 'month', icon: CalendarDays },
  { value: 'year', labelKey: 'year', icon: CalendarCheck2 },
]

interface TimeEntriesPeriodPresetControlProps {
  value: TimeEntriesPeriodUnit | null
  onChange: (unit: TimeEntriesPeriodUnit) => void
  className?: string
}

export function TimeEntriesPeriodPresetControl({
  value,
  onChange,
  className,
}: TimeEntriesPeriodPresetControlProps) {
  const { t } = useTranslation()
  const selectedOption = PERIOD_PRESET_OPTIONS.find((option) => option.value === value)

  return (
    <div className={className}>
      <div
        aria-label={t('timeEntries.period.presetGroupLabel')}
        className="hidden items-center rounded-md border border-field-border bg-field p-1 md:inline-flex"
        role="tablist"
      >
        {PERIOD_PRESET_OPTIONS.map((option) => {
          const Icon = option.icon
          const selected = value === option.value
          return (
            <Button
              key={option.value}
              aria-pressed={selected}
              type="button"
              variant="ghost"
              onClick={() => onChange(option.value)}
              className={cn(
                'h-8 gap-1.5 rounded-sm px-3 text-xs transition-colors',
                selected
                  ? 'bg-primary text-primary-foreground shadow-sm hover:bg-primary/90 hover:text-primary-foreground'
                  : 'text-muted-foreground hover:bg-accent hover:text-foreground',
              )}
            >
              <Icon className="size-3.5 shrink-0" aria-hidden="true" />
              <span>{t(`timeEntries.period.${option.labelKey}`)}</span>
            </Button>
          )
        })}
      </div>

      <div className="md:hidden">
        <Select
          onValueChange={(next) => onChange(next as TimeEntriesPeriodUnit)}
          value={value ?? undefined}
        >
          <SelectTrigger className="h-10 w-full bg-field">
            {selectedOption ? (
              <span className="flex items-center gap-2">
                <selectedOption.icon className="size-3.5" aria-hidden="true" />
                <span>{t(`timeEntries.period.${selectedOption.labelKey}`)}</span>
              </span>
            ) : (
              <SelectValue placeholder={t('timeEntries.period.customRange')} />
            )}
          </SelectTrigger>
          <SelectContent>
            {PERIOD_PRESET_OPTIONS.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                <span className="flex items-center gap-2">
                  <option.icon className="size-3.5" aria-hidden="true" />
                  <span>{t(`timeEntries.period.${option.labelKey}`)}</span>
                </span>
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
    </div>
  )
}
