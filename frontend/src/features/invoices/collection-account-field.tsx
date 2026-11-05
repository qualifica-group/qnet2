import { useTranslation } from 'react-i18next'
import { FormControl, FormDescription, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { PurchaseFinancialAccountSelect } from '@/features/purchase-invoice-installments/purchase-financial-account-select'
import type { NamedRef } from '@/features/invoices/types'

interface CollectionAccountFieldProps {
  value: number | null
  onChange: (value: number | null) => void
  /** Known label of the proposed account, so the trigger never shows a bare id. */
  selected?: NamedRef | null
  hint: string
}

/** Optional, clearable "Account" field of the collection dialogs (spec 0355); render inside a `FormField`. */
export function CollectionAccountField({ value, onChange, selected = null, hint }: CollectionAccountFieldProps) {
  const { t } = useTranslation()
  const label = t('treasuryCollection.accountLabel')

  return (
    <FormItem>
      <FormLabel>{label}</FormLabel>
      <FormControl>
        <PurchaseFinancialAccountSelect value={value} onChange={onChange} selected={selected} label={label} />
      </FormControl>
      <FormDescription className="text-xs">{hint}</FormDescription>
      <FormMessage />
    </FormItem>
  )
}
