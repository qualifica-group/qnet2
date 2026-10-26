import { useTranslation } from 'react-i18next'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import type { ForSelectItem } from '@/features/for-select/types'
import { FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE } from '@/features/financial-accounts/for-select-api'
import type { NamedRef } from '@/features/invoices/types'

interface PurchaseFinancialAccountSelectProps {
  value: number | null
  onChange: (value: number | null) => void
  /** Restricts the options to the accounts of the document company (and company-less ones, server side). */
  companyId?: number
  /** Known label of the current value (the document default), so the trigger never shows a bare id. */
  selected?: NamedRef | null
  label: string
  /** Wired by `FormControl` (label association and the accessible error triad). */
  id?: string
  'aria-describedby'?: string
  'aria-invalid'?: boolean
}

/** Payment-account picker over the shared financial-accounts for-select, optionally scoped to one company. */
export function PurchaseFinancialAccountSelect({
  value,
  onChange,
  companyId,
  selected = null,
  label,
  ...aria
}: PurchaseFinancialAccountSelectProps) {
  const { t } = useTranslation()
  const { renderAction, selectedItemFor } = useQuickCreateAction(FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE)
  const selectedItem: ForSelectItem | null = selected ? { id: selected.id, label: selected.name } : null

  return (
    <AsyncPaginatedSelect
      resource={FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE}
      value={value}
      onChange={onChange}
      selectedItem={selectedItemFor(value) ?? selectedItem}
      params={companyId === undefined ? undefined : { company_id: companyId }}
      labels={{
        placeholder: t('purchaseInvoiceInstallments.payment.financialAccountPlaceholder'),
        searchPlaceholder: t('purchaseInvoiceInstallments.payment.searchPlaceholder'),
        empty: t('purchaseInvoiceInstallments.payment.selectEmpty'),
        error: t('purchaseInvoiceInstallments.payment.selectError'),
        clearLabel: t('common.clear'),
        triggerLabel: label,
        retry: t('common.retry'),
      }}
      action={renderAction((ref) => onChange(ref.id))}
      {...aria}
    />
  )
}
