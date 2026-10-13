import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
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
  const tabs: { value: StatusTab; name: string }[] = [
    { value: ALL_TAB, name: t('purchaseOrders.tabs.all') },
    ...entries.flatMap((entry) => (isPurchaseOrderStatus(entry.systemKey) ? [{ value: entry.systemKey, name: entry.name }] : [])),
  ]

  return (
    <div role="tablist" aria-label={label} className="flex flex-wrap items-center gap-1 rounded-xl border bg-surface p-2">
      {tabs.map((tab) => (
        <button
          key={tab.value}
          type="button"
          role="tab"
          aria-selected={active === tab.value}
          onClick={() => onSelect(tab.value)}
          className={cn(
            'rounded-md px-2.5 py-1 text-xs font-medium transition-colors',
            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            active === tab.value ? 'bg-primary text-primary-foreground' : 'bg-card text-foreground hover:bg-muted',
          )}
        >
          {tab.name}
        </button>
      ))}
    </div>
  )
}
