import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Calendar } from 'lucide-react'
import { DashboardSectionHeader } from '@/features/dashboard/dashboard-section-header'
import { TimeEntriesPeriodPresetControl } from '@/features/time-entries/dashboard/time-entries-period-preset-control'
import { TimeEntriesStatsPanels } from '@/features/time-entries/dashboard/time-entries-stats-panels'
import type { TimeEntriesPeriodUnit } from '@/features/time-entries/time-entry-period'

const DEFAULT_PERIOD_PRESET: TimeEntriesPeriodUnit = 'day'

/**
 * Segnatempo section (spec 0151 D-7/AC-010): the period preset control
 * (default Giorno) drives the SAME `TimeEntriesStatsPanels` the `/time-entries`
 * dashboard uses, imported as-is (feature owned by spec 0122).
 */
export function DashboardTimeEntriesSection() {
  const { t } = useTranslation()
  const [periodPreset, setPeriodPreset] = useState<TimeEntriesPeriodUnit>(DEFAULT_PERIOD_PRESET)

  return (
    <section aria-labelledby="dashboard-time-entries-heading" className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <DashboardSectionHeader
          id="dashboard-time-entries-heading"
          icon={<Calendar aria-hidden="true" />}
          title={t('dashboard.timeEntriesSection.title')}
        />
        <TimeEntriesPeriodPresetControl value={periodPreset} onChange={setPeriodPreset} />
      </div>

      <TimeEntriesStatsPanels params={{ period_preset: periodPreset }} />
    </section>
  )
}
