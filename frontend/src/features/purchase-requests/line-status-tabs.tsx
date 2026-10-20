import { useTranslation } from 'react-i18next'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { usePurchaseRequestLineStatusCatalog } from '@/features/purchase-request-line-statuses/use-purchase-request-line-status-catalog'
import { ALL_TAB, type StatusTab } from '@/features/purchase-requests/use-status-tab-filter'
import type { LineStatus } from '@/features/purchase-requests/types'

interface LineStatusTabsProps {
  active: StatusTab
  onSelect: (tab: StatusTab) => void
  label: string
}

/** Compact tab strip per line status ("All" first, then the catalog order and names); each tab writes into the grid filter model. */
export function LineStatusTabs({ active, onSelect, label }: LineStatusTabsProps) {
  const { t } = useTranslation()
  const { statuses } = usePurchaseRequestLineStatusCatalog()
  const tabs: readonly { value: StatusTab; label: string }[] = [
    { value: ALL_TAB, label: t('purchaseRequests.tabs.all') },
    ...statuses.map((status) => ({ value: status.systemKey as LineStatus, label: status.name })),
  ]
  return <FilterTabs<StatusTab> aria-label={label} value={active} options={tabs} onValueChange={onSelect} />
}
