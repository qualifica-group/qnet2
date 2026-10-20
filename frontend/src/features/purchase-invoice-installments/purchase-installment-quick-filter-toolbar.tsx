import { useTranslation } from 'react-i18next'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { PURCHASE_QUICK_FILTERS, type PurchaseQuickFilter } from '@/features/purchase-invoice-installments/types'

interface PurchaseInstallmentQuickFilterToolbarProps {
  value: PurchaseQuickFilter | null
  onChange: (filter: PurchaseQuickFilter) => void
}

/** Da pagare / In scadenza / Scadute / Pagate / Tutte tabs; they write into the grid filter model. */
export function PurchaseInstallmentQuickFilterToolbar({ value, onChange }: PurchaseInstallmentQuickFilterToolbarProps) {
  const { t } = useTranslation()

  return (
    <FilterTabs<PurchaseQuickFilter>
      aria-label={t('purchaseInvoiceInstallments.quickFilter.label')}
      value={value}
      options={PURCHASE_QUICK_FILTERS.map((filter) => ({ value: filter, label: t(`purchaseInvoiceInstallments.quickFilter.${filter}`) }))}
      onValueChange={onChange}
    />
  )
}
