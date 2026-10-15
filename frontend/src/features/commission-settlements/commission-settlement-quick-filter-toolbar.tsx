import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { SETTLEMENT_QUICK_FILTERS, type SettlementQuickFilter } from '@/features/commission-settlements/types'

interface CommissionSettlementQuickFilterToolbarProps {
  value: SettlementQuickFilter | null
  onChange: (filter: SettlementQuickFilter) => void
}

/** Bozze / Emessi / Documento ricevuto / Approvati / Annullati / Tutti tabs; they write into the grid filter model. */
export function CommissionSettlementQuickFilterToolbar({ value, onChange }: CommissionSettlementQuickFilterToolbarProps) {
  const { t } = useTranslation()

  return (
    <div
      role="tablist"
      aria-label={t('commissionSettlements.quickFilter.label')}
      className="flex flex-wrap items-center gap-1 rounded-xl border border-border bg-surface p-3"
    >
      {SETTLEMENT_QUICK_FILTERS.map((filter) => {
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
            {t(`commissionSettlements.quickFilter.${filter}`)}
          </button>
        )
      })}
    </div>
  )
}
