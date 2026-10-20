import { useTranslation } from 'react-i18next'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { ACCRUAL_QUICK_FILTERS, type AccrualQuickFilter } from '@/features/commission-accruals/types'

interface CommissionAccrualQuickFilterToolbarProps {
  value: AccrualQuickFilter | null
  onChange: (filter: AccrualQuickFilter) => void
}

/** Aperto / Liquidato esternamente / Tutti tabs; they write into the grid filter model. */
export function CommissionAccrualQuickFilterToolbar({ value, onChange }: CommissionAccrualQuickFilterToolbarProps) {
  const { t } = useTranslation()

  return (
    <FilterTabs<AccrualQuickFilter>
      aria-label={t('commissionAccruals.quickFilter.label')}
      value={value}
      options={ACCRUAL_QUICK_FILTERS.map((filter) => ({ value: filter, label: t(`commissionAccruals.quickFilter.${filter}`) }))}
      onValueChange={onChange}
    />
  )
}
