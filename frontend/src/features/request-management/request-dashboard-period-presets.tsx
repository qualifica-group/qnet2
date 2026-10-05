import { useTranslation } from 'react-i18next'
import { CalendarRange } from 'lucide-react'
import {
  activePreset,
  DASHBOARD_PERIOD_PRESETS,
  type DashboardDateRange,
  presetRange,
} from '@/features/request-management/dashboard-period-presets'
import { cn } from '@/lib/utils'

const TRACK_CLASS = 'flex w-max items-center gap-0.5 rounded-lg border bg-card p-0.5'
const OPTION_CLASS =
  'inline-flex shrink-0 items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium whitespace-nowrap outline-none transition-colors focus-visible:ring-[2px] focus-visible:ring-ring/50'
const OPTION_SELECTED_CLASS = 'bg-primary text-primary-foreground shadow-sm'
const OPTION_IDLE_CLASS = 'text-muted-foreground hover:bg-muted hover:text-foreground'

export interface DashboardPeriodPresetsProps {
  /** The applied dates: the selected option is derived from them, never stored (D-2). */
  range: DashboardDateRange
  /** Applies a preset's dates straight to the dashboard, no sheet involved. */
  onApply: (range: DashboardDateRange) => void
  /** "Personalizzato": the filter sheet, where any range can be typed. */
  onCustom: () => void
  disabled: boolean
}

/**
 * One-click periods of the statistics toolbar (spec 0192 D-2). A toggle
 * group of plain buttons with `aria-pressed`, scrolling sideways on a narrow
 * screen instead of wrapping into a second row. "Personalizzato" is pressed
 * whenever the applied dates match no preset.
 */
export function DashboardPeriodPresets({ range, onApply, onCustom, disabled }: DashboardPeriodPresetsProps) {
  const { t } = useTranslation()
  const active = activePreset(range)

  return (
    <div className="min-w-0 overflow-x-auto">
      <div role="group" aria-label={t('requestManagement.dashboard.periods.label')} className={TRACK_CLASS}>
        {DASHBOARD_PERIOD_PRESETS.map((preset) => (
          <button
            key={preset}
            type="button"
            aria-pressed={active === preset}
            disabled={disabled}
            onClick={() => onApply(presetRange(preset))}
            className={cn(OPTION_CLASS, active === preset ? OPTION_SELECTED_CLASS : OPTION_IDLE_CLASS)}
          >
            {t(`requestManagement.dashboard.periods.${preset}`)}
          </button>
        ))}
        <button
          type="button"
          aria-pressed={active === null}
          disabled={disabled}
          onClick={onCustom}
          className={cn(OPTION_CLASS, active === null ? OPTION_SELECTED_CLASS : OPTION_IDLE_CLASS)}
        >
          <CalendarRange aria-hidden="true" className="size-3.5" />
          {t('requestManagement.dashboard.periods.custom')}
        </button>
      </div>
    </div>
  )
}
