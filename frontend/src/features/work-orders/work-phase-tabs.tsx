import { useTranslation } from 'react-i18next'
import { FilterTabs } from '@/components/ui/filter-tabs'
import type { TableRowsAggregates } from '@/features/table/types'
import { ALL_WORK_PHASES_TAB, WORK_PHASES, type WorkPhaseTab } from '@/features/work-orders/use-work-phase-tabs'

const TAB_VALUES: readonly WorkPhaseTab[] = [ALL_WORK_PHASES_TAB, ...WORK_PHASES]

interface WorkPhaseTabsProps {
  active: WorkPhaseTab | null
  /** Figures per tab, keyed by tab value; absent until the grid has answered. */
  counts?: TableRowsAggregates
  onSelect: (tab: WorkPhaseTab) => void
}

/** Compact tab strip per working phase, as on the legacy list: "All" first, then the five phases, each with its count when not zero. */
export function WorkPhaseTabs({ active, counts, onSelect }: WorkPhaseTabsProps) {
  const { t, i18n } = useTranslation()
  const tabs = TAB_VALUES.map((value) => {
    const label = t(`workOrders.workPhaseTabs.${value}`)
    // As on the legacy list, "All" never shows a figure.
    const count = value === ALL_WORK_PHASES_TAB ? 0 : (counts?.[value] ?? 0)
    return { value, label: count > 0 ? `${label} (${count.toLocaleString(i18n.language)})` : label }
  })

  return (
    <FilterTabs<WorkPhaseTab>
      aria-label={t('workOrders.workPhaseTabs.label')}
      value={active}
      options={tabs}
      onValueChange={onSelect}
      className="w-auto justify-end"
    />
  )
}
