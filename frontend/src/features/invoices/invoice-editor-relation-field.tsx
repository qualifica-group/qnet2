import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import type { ForSelectItem } from '@/features/for-select/types'
import { idToSelectValue, NO_ID } from '@/features/invoices/invoice-editor-source'
import type { InvoiceWriteFormValues } from '@/features/invoices/invoice-schema'
import type { NamedRef } from '@/features/invoices/types'

type RelationFieldName = 'company_id' | 'customer_registry_id' | 'payment_method_id' | 'financial_account_id'

interface InvoiceEditorRelationFieldProps {
  control: Control<InvoiceWriteFormValues>
  name: RelationFieldName
  /** for-select resource segment (`companies`, `registries`, ...). */
  resource: string
  label: string
  placeholder: string
  selected: NamedRef | null
  required?: boolean
  disabled?: boolean
  params?: Record<string, string | number>
  /** Fired after the user changed the selection (the form value is already updated). */
  onPicked?: () => void
}

/** Server-backed single select bound to one id of the invoice form. */
export function InvoiceEditorRelationField({
  control,
  name,
  resource,
  label,
  placeholder,
  selected,
  required = false,
  disabled = false,
  params,
  onPicked,
}: InvoiceEditorRelationFieldProps) {
  const { t } = useTranslation()
  const selectedItem: ForSelectItem | null = selected ? { id: selected.id, label: selected.name } : null

  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel required={required} className="text-xs">
            {label}
          </FormLabel>
          <FormControl>
            <AsyncPaginatedSelect
              resource={resource}
              value={idToSelectValue(field.value ?? NO_ID)}
              onChange={(id) => {
                field.onChange(id ?? (required ? NO_ID : null))
                onPicked?.()
              }}
              selectedItem={selectedItem}
              disabled={disabled}
              params={params}
              labels={{
                placeholder,
                searchPlaceholder: t('invoiceEditor.fields.searchPlaceholder'),
                empty: t('invoiceEditor.fields.selectEmpty'),
                error: t('invoiceEditor.fields.selectError'),
                clearLabel: t('common.clear'),
                triggerLabel: label,
                retry: t('common.retry'),
              }}
            />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}
