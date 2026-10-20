import { useTranslation } from 'react-i18next'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { usePurchaseOrderStatusCatalog } from '@/features/purchase-order-statuses/use-purchase-order-status-catalog'
import { ALL_TAB, type StatusTab } from '@/features/purchase-orders/use-status-tab-filter'
import { isPurchaseOrderStatus } from '@/features/purchase-orders/types'

interface StatusTabsProps {
  active: StatusTab
  onSelect: (tab: StatusTab) => void
  label: string
}

/** Compact tab strip per order status ("All" first, then the catalog order and names); each tab writes into the grid filter model. */
export function StatusTabs({ active, onSelect, label }: StatusTabsProps) {
  const { t } = useTranslation()
  const { entries } = usePurchaseOrderStatusCatalog()
  const tabs: { value: StatusTab; label: string }[] = [
    { value: ALL_TAB, label: t('purchaseOrders.tabs.all') },
    ...entries.flatMap((entry) => (isPurchaseOrderStatus(entry.systemKey) ? [{ value: entry.systemKey, label: entry.name }] : [])),
  ]

  return <FilterTabs<StatusTab> aria-label={label} value={active} options={tabs} onValueChange={onSelect} />
}
