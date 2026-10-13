import { useTranslation } from 'react-i18next'
import { usePurchaseRequestLineStatusCatalog } from '@/features/purchase-request-line-statuses/use-purchase-request-line-status-catalog'
import { cn } from '@/lib/utils'
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
  const tabs: readonly { key: StatusTab; label: string }[] = [
    { key: ALL_TAB, label: t('purchaseRequests.tabs.all') },
    ...statuses.map((status) => ({ key: status.systemKey as LineStatus, label: status.name })),
  ]
  return (
    <div role="tablist" aria-label={label} className="flex flex-wrap items-center gap-1 rounded-xl border bg-surface p-2">
      {tabs.map((tab) => {
        const selected = active === tab.key
        return (
          <button
            key={tab.key}
            type="button"
            role="tab"
            aria-selected={selected}
            onClick={() => onSelect(tab.key)}
            className={cn(
              'rounded-md px-2.5 py-1 text-xs font-medium transition-colors',
              'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
              selected ? 'bg-primary text-primary-foreground' : 'bg-card text-foreground hover:bg-muted',
            )}
          >
            {tab.label}
          </button>
        )
      })}
    </div>
  )
}
