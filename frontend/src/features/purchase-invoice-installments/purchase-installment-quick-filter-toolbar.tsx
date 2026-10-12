import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import {
  PURCHASE_QUICK_FILTERS,
  type PurchaseQuickFilter,
} from '@/features/purchase-invoice-installments/types'

interface PurchaseInstallmentQuickFilterToolbarProps {
  value: PurchaseQuickFilter | null
  onChange: (filter: PurchaseQuickFilter) => void
}

/** Da pagare / In scadenza / Scadute / Pagate / Tutte tabs; they write into the grid filter model. */
export function PurchaseInstallmentQuickFilterToolbar({ value, onChange }: PurchaseInstallmentQuickFilterToolbarProps) {
  const { t } = useTranslation()

  return (
    <div
      role="tablist"
      aria-label={t('purchaseInvoiceInstallments.quickFilter.label')}
      className="flex flex-wrap items-center gap-1 rounded-xl border border-border bg-surface p-3"
    >
      {PURCHASE_QUICK_FILTERS.map((filter) => {
        const selected = value === filter
        return (
          <button
            key={filter}
            type="button"
            role="tab"
            aria-selected={selected}
            onClick={() => onChange(filter)}
            className={cn(
              'rounded-md px-2.5 py-1 text-xs font-medium transition-colors',
              'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
              selected ? 'bg-primary text-primary-foreground' : 'bg-card text-foreground hover:bg-muted',
            )}
          >
            {t(`purchaseInvoiceInstallments.quickFilter.${filter}`)}
          </button>
        )
      })}
    </div>
  )
}
