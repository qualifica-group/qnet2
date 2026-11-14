import { useTranslation } from 'react-i18next'
import { FilterTabs } from '@/components/ui/filter-tabs'
import type { TableRowsAggregates } from '@/features/table/types'
import { ALL_CONTRACT_STATUSES_TAB } from '@/features/contracts/use-contract-status-tabs'

interface ContractStatusTabsProps {
  /** Active contract status names, in the configured order. */
  statuses: readonly string[]
  active: string | null
  /** Figures per status name; absent until the grid has answered. */
  counts?: TableRowsAggregates
  onSelect: (tab: string) => void
}

/** Compact tab strip per contract status, as on the legacy list: "All" first, then each status with its count when not zero. */
export function ContractStatusTabs({ statuses, active, counts, onSelect }: ContractStatusTabsProps) {
  const { t, i18n } = useTranslation()
  const tabs = [
    { value: ALL_CONTRACT_STATUSES_TAB, label: t('contracts.statusTabs.all') },
    ...statuses.map((status) => {
      const count = counts?.[status] ?? 0
      return { value: status, label: count > 0 ? `${status} (${count.toLocaleString(i18n.language)})` : status }
    }),
  ]

  return (
    <FilterTabs<string>
      aria-label={t('contracts.statusTabs.label')}
      value={active}
      options={tabs}
      onValueChange={onSelect}
      className="w-auto justify-end"
    />
  )
}
