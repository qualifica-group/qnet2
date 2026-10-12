import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { parseNumberInput } from '@/features/invoices/invoice-format'
import type { InvoiceFormValues } from '@/features/invoices/invoice-schema'
import { useInvoiceFieldLabelClass } from '@/features/invoices/use-invoice-field-label-class'
import { INVOICE_TAGS, type InvoiceTag } from '@/features/invoices/types'

const NO_TAG = 'none'

interface InvoiceFieldProps {
  control: Control<InvoiceFormValues>
}

export function InvoiceDocumentDateField({ control, disabled = false }: InvoiceFieldProps & { disabled?: boolean }) {
  const { t } = useTranslation()
  const labelClass = useInvoiceFieldLabelClass('document_date')

  return (
    <FormField
      control={control}
      name="document_date"
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel required className={labelClass}>
            {t('invoiceEditor.fields.documentDate')}
          </FormLabel>
          <FormControl>
            <Input type="date" {...field} disabled={disabled} />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}

export function InvoiceTagField({ control }: InvoiceFieldProps) {
  const { t } = useTranslation()
  const labelClass = useInvoiceFieldLabelClass('tag')

  return (
    <FormField
      control={control}
      name="tag"
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel className={labelClass}>{t('invoiceEditor.fields.tag')}</FormLabel>
          <Select
            value={field.value ?? NO_TAG}
            onValueChange={(value) => field.onChange(value === NO_TAG ? null : (value as InvoiceTag))}
          >
            <FormControl>
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              <SelectItem value={NO_TAG}>{t('invoiceEditor.fields.noTag')}</SelectItem>
              {INVOICE_TAGS.map((tag) => (
                <SelectItem key={tag} value={tag}>
                  {t(`invoiceEditor.tags.${tag}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}

/** Printed notes or internal note: the same textarea, one field each. */
export function InvoiceNoteField({ control, name }: InvoiceFieldProps & { name: 'notes' | 'internal_note' }) {
  const { t } = useTranslation()
  const labelClass = useInvoiceFieldLabelClass(name)

  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel className={labelClass}>
            {t(name === 'notes' ? 'invoiceEditor.fields.notes' : 'invoiceEditor.fields.internalNote')}
          </FormLabel>
          <FormControl>
            <Textarea rows={3} className="min-h-16 text-xs" {...field} value={field.value ?? ''} />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}

/** "Fattura esterna": number and date edited together (the date is required with the number). */
export function InvoiceExternalReferenceFields({ control }: InvoiceFieldProps) {
  const { t } = useTranslation()

  return (
    <div className="grid gap-2">
      <div className="grid gap-3 sm:grid-cols-2">
        <FormField
          control={control}
          name="external_number"
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel className="text-xs">{t('invoices.details.externalNumber')}</FormLabel>
              <FormControl>
                <Input {...field} value={field.value ?? ''} onChange={(event) => field.onChange(event.target.value)} />
              </FormControl>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
        <FormField
          control={control}
          name="external_date"
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel className="text-xs">{t('invoices.details.externalDate')}</FormLabel>
              <FormControl>
                <Input
                  type="date"
                  {...field}
                  value={field.value ?? ''}
                  onChange={(event) => field.onChange(event.target.value)}
                />
              </FormControl>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
      </div>
      <p className="text-xs text-muted-foreground">{t('invoices.details.externalNumberHint')}</p>
    </div>
  )
}

export function InvoiceDeviationField({ control }: InvoiceFieldProps) {
  const { t } = useTranslation()
  const labelClass = useInvoiceFieldLabelClass('deviation')

  return (
    <FormField
      control={control}
      name="deviation"
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel className={labelClass}>{t('invoices.details.deviation')}</FormLabel>
          <FormControl>
            <Input
              type="number"
              step="0.01"
              inputMode="decimal"
              name={field.name}
              ref={field.ref}
              onBlur={field.onBlur}
              value={field.value ?? ''}
              onChange={(event) => field.onChange(parseNumberInput(event.target.value, null))}
            />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}
