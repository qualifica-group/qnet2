import type { ReactNode } from 'react'
import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import type { ForSelectItem } from '@/features/for-select/types'
import type { NamedRef } from '@/features/invoices/types'
import { idToSelectValue, NO_ID } from '@/features/purchase-invoices/purchase-invoice-editor-lines'
import type { PurchaseInvoiceWriteFormValues } from '@/features/purchase-invoices/purchase-invoice-schema'

export type RelationFieldName =
  | 'company_id'
  | 'supplier_id'
  | 'payment_method_id'
  | 'financial_account_id'
  | 'company_site_id'
  | 'operational_site_id'
  | 'cost_type_id'
  | 'work_order_id'

interface PurchaseInvoiceEditorRelationFieldProps {
  control: Control<PurchaseInvoiceWriteFormValues>
  name: RelationFieldName
  /** for-select resource segment (`companies`, `registries`, ...). */
  resource: string
  label: string
  placeholder: string
  selected: NamedRef | null
  required?: boolean
  disabled?: boolean
  params?: Record<string, string | number>
  renderItem?: (item: ForSelectItem) => ReactNode
  /** Fired after the user changed the selection (the form value is already updated). */
  onPicked?: () => void
}

/** Server-backed single select bound to one id of the form header (required ones hold NaN while empty). */
export function PurchaseInvoiceEditorRelationField({
  control,
  name,
  resource,
  label,
  placeholder,
  selected,
  required = false,
  disabled = false,
  params,
  renderItem,
  onPicked,
}: PurchaseInvoiceEditorRelationFieldProps) {
  const { t } = useTranslation()
  const { renderAction, selectedItemFor } = useQuickCreateAction(resource)
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
              selectedItem={selectedItemFor(idToSelectValue(field.value ?? NO_ID)) ?? selectedItem}
              disabled={disabled}
              params={params}
              renderItem={renderItem}
              labels={{
                placeholder,
                searchPlaceholder: t('purchaseInvoices.editor.select.search'),
                empty: t('purchaseInvoices.editor.select.empty'),
                error: t('purchaseInvoices.editor.select.error'),
                clearLabel: t('common.clear'),
                triggerLabel: label,
                retry: t('common.retry'),
              }}
              action={renderAction((ref) => {
                field.onChange(ref.id)
                onPicked?.()
              }, disabled)}
            />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}
