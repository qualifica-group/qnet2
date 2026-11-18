import { useTranslation } from 'react-i18next'
import { FilterTabs } from '@/components/ui/filter-tabs'
import type { TableRowsAggregates } from '@/features/table/types'
import { ALL_STATUSES_TAB } from '@/features/table/use-status-filter-tabs'

interface StatusFilterTabsProps {
  /** Status names, in the configured order. */
  statuses: readonly string[]
  active: string | null
  /** Figures per status name; absent until the grid has answered. */
  counts?: TableRowsAggregates
  onSelect: (tab: string) => void
  /** Accessible name of the tab strip. */
  label: string
}

/** Compact tab strip per status, as on the legacy lists: "All" first, then each status with its count when not zero. */
export function StatusFilterTabs({ statuses, active, counts, onSelect, label }: StatusFilterTabsProps) {
  const { t, i18n } = useTranslation()
  const tabs = [
    { value: ALL_STATUSES_TAB, label: t('table.statusTabs.all') },
    ...statuses.map((status) => {
      const count = counts?.[status] ?? 0
      return { value: status, label: count > 0 ? `${status} (${count.toLocaleString(i18n.language)})` : status }
    }),
  ]

  return (
    <FilterTabs<string>
      aria-label={label}
      value={active}
      options={tabs}
      onValueChange={onSelect}
      className="w-auto justify-end"
    />
  )
}
