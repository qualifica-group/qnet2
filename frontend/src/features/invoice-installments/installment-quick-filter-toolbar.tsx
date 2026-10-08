import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import {
  INSTALLMENT_QUICK_FILTERS,
  type InstallmentQuickFilter,
} from '@/features/invoice-installments/types'

interface InstallmentQuickFilterToolbarProps {
  value: InstallmentQuickFilter | null
  onChange: (filter: InstallmentQuickFilter) => void
}

/** Da incassare / In scadenza / Scadute / Incassate / Tutte tabs; they write into the grid filter model. */
export function InstallmentQuickFilterToolbar({ value, onChange }: InstallmentQuickFilterToolbarProps) {
  const { t } = useTranslation()

  return (
    <div
      role="tablist"
      aria-label={t('invoiceInstallments.quickFilter.label')}
      className="flex flex-wrap items-center gap-1 rounded-xl border border-border bg-surface p-3"
    >
      {INSTALLMENT_QUICK_FILTERS.map((filter) => {
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
            {t(`invoiceInstallments.quickFilter.${filter}`)}
          </button>
        )
      })}
    </div>
  )
}
