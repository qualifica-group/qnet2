import { useTranslation } from 'react-i18next'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { INSTALLMENT_QUICK_FILTERS, type InstallmentQuickFilter } from '@/features/invoice-installments/types'

interface InstallmentQuickFilterToolbarProps {
  value: InstallmentQuickFilter | null
  onChange: (filter: InstallmentQuickFilter) => void
}

/** Da incassare / In scadenza / Scadute / Incassate / Tutte tabs; they write into the grid filter model. */
export function InstallmentQuickFilterToolbar({ value, onChange }: InstallmentQuickFilterToolbarProps) {
  const { t } = useTranslation()

  return (
    <FilterTabs<InstallmentQuickFilter>
      aria-label={t('invoiceInstallments.quickFilter.label')}
      value={value}
      options={INSTALLMENT_QUICK_FILTERS.map((filter) => ({ value: filter, label: t(`invoiceInstallments.quickFilter.${filter}`) }))}
      onValueChange={onChange}
    />
  )
}
