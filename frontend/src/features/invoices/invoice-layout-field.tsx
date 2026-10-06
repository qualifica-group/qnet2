import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE } from '@/features/document-layouts/for-select-api'
import type { NamedRef } from '@/features/invoices/types'

/** Hoisted so the picker's `params` identity is stable across renders. */
const INVOICES_LAYOUT_PARAMS = { module: 'invoices' } as const

interface LayoutFormValues {
  layout_id: number | null
}

interface InvoiceLayoutFieldProps<T extends LayoutFormValues> {
  control: Control<T>
  /** Saved layout: stays displayed even if it was deactivated after saving (not in the active list). */
  selected: NamedRef | null
}

/** "Layout di stampa": active Invoices layouts; cleared (null) = "Predefinito". */
export function InvoiceLayoutField<T extends LayoutFormValues>({ control, selected }: InvoiceLayoutFieldProps<T>) {
  const { t } = useTranslation()
  const label = t('invoiceEditor.fields.layout')
  const placeholder = t('invoiceEditor.fields.layoutPlaceholder')

  return (
    <FormField
      control={control as unknown as Control<LayoutFormValues>}
      name="layout_id"
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel className="text-xs">{label}</FormLabel>
          <FormControl>
            <AsyncPaginatedSelect
              resource={DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE}
              value={field.value}
              onChange={(id) => field.onChange(id ?? null)}
              selectedItem={selected ? { id: selected.id, label: selected.name } : null}
              params={INVOICES_LAYOUT_PARAMS}
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
