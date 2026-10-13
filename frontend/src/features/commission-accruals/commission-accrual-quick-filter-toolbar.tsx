import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { ACCRUAL_QUICK_FILTERS, type AccrualQuickFilter } from '@/features/commission-accruals/types'

interface CommissionAccrualQuickFilterToolbarProps {
  value: AccrualQuickFilter | null
  onChange: (filter: AccrualQuickFilter) => void
}

/** Aperto / Liquidato esternamente / Tutti tabs; they write into the grid filter model. */
export function CommissionAccrualQuickFilterToolbar({ value, onChange }: CommissionAccrualQuickFilterToolbarProps) {
  const { t } = useTranslation()

  return (
    <div
      role="tablist"
      aria-label={t('commissionAccruals.quickFilter.label')}
      className="flex flex-wrap items-center gap-1 rounded-xl border border-border bg-surface p-3"
    >
      {ACCRUAL_QUICK_FILTERS.map((filter) => {
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
            {t(`commissionAccruals.quickFilter.${filter}`)}
          </button>
        )
      })}
    </div>
  )
}
