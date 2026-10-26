import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { INVOICE_TAGS_FOR_SELECT_RESOURCE } from '@/features/invoice-tags/for-select-api'
import type { InvoiceTagRef } from '@/features/invoices/types'

interface TagFormValues {
  invoice_tag_id: number | null
}

interface InvoiceTagFieldProps<T extends TagFormValues> {
  control: Control<T>
  /** Saved tag: stays displayed even if it was deactivated after saving (not in the active list). */
  selected: InvoiceTagRef | null
  /** Label classes differ between the editor (compact) and the details dialog. */
  labelClassName?: string
}

/** Optional "Tag" of an invoice: active catalog entries; cleared (null) = "Nessun tag". */
export function InvoiceTagField<T extends TagFormValues>({ control, selected, labelClassName }: InvoiceTagFieldProps<T>) {
  const { t } = useTranslation()
  const { renderAction, selectedItemFor } = useQuickCreateAction(INVOICE_TAGS_FOR_SELECT_RESOURCE)
  const label = t('invoiceEditor.fields.tag')

  return (
    <FormField
      control={control as unknown as Control<TagFormValues>}
      name="invoice_tag_id"
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel className={labelClassName}>{label}</FormLabel>
          <FormControl>
            <AsyncPaginatedSelect
              resource={INVOICE_TAGS_FOR_SELECT_RESOURCE}
              value={field.value}
              onChange={(id) => field.onChange(id ?? null)}
              selectedItem={selectedItemFor(field.value) ?? (selected ? { id: selected.id, label: selected.name } : null)}
              action={renderAction((ref) => field.onChange(ref.id))}
              labels={{
                placeholder: t('invoiceEditor.fields.noTag'),
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
