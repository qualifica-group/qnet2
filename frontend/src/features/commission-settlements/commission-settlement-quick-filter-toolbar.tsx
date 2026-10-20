import { useTranslation } from 'react-i18next'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { SETTLEMENT_QUICK_FILTERS, type SettlementQuickFilter } from '@/features/commission-settlements/types'

interface CommissionSettlementQuickFilterToolbarProps {
  value: SettlementQuickFilter | null
  onChange: (filter: SettlementQuickFilter) => void
}

/** Bozze / Emessi / Documento ricevuto / Approvati / Annullati / Tutti tabs; they write into the grid filter model. */
export function CommissionSettlementQuickFilterToolbar({ value, onChange }: CommissionSettlementQuickFilterToolbarProps) {
  const { t } = useTranslation()

  return (
    <FilterTabs<SettlementQuickFilter>
      aria-label={t('commissionSettlements.quickFilter.label')}
      value={value}
      options={SETTLEMENT_QUICK_FILTERS.map((filter) => ({ value: filter, label: t(`commissionSettlements.quickFilter.${filter}`) }))}
      onValueChange={onChange}
    />
  )
}
