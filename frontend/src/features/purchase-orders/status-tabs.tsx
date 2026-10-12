import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { ALL_TAB, type StatusTab } from '@/features/purchase-orders/use-status-tab-filter'
import { PURCHASE_ORDER_STATUSES } from '@/features/purchase-orders/types'

const TABS: readonly StatusTab[] = [ALL_TAB, ...PURCHASE_ORDER_STATUSES]

interface StatusTabsProps {
  active: StatusTab
  onSelect: (tab: StatusTab) => void
  label: string
}

/** Compact tab strip per order status ("All" first); each tab writes into the grid filter model. */
export function StatusTabs({ active, onSelect, label }: StatusTabsProps) {
  const { t } = useTranslation()
  return (
    <div role="tablist" aria-label={label} className="flex flex-wrap items-center gap-1 rounded-xl border bg-surface p-2">
      {TABS.map((tab) => {
        const selected = active === tab
        return (
          <button
            key={tab}
            type="button"
            role="tab"
            aria-selected={selected}
            onClick={() => onSelect(tab)}
            className={cn(
              'rounded-md px-2.5 py-1 text-xs font-medium transition-colors',
              'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
              selected ? 'bg-primary text-primary-foreground' : 'bg-card text-foreground hover:bg-muted',
            )}
          >
            {tab === ALL_TAB ? t('purchaseOrders.tabs.all') : t(`purchaseOrders.statuses.${tab}`)}
          </button>
        )
      })}
    </div>
  )
}
