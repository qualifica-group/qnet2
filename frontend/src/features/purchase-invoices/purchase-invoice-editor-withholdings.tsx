import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import type { PurchaseInvoiceWriteFormValues } from '@/features/purchase-invoices/purchase-invoice-schema'

type DeductionName = 'withholding_amount' | 'enasarco_amount'

function toAmountOrNull(raw: string): number | null {
  return raw === '' ? null : Number(raw)
}

function DeductionField({
  control,
  name,
  label,
}: {
  control: Control<PurchaseInvoiceWriteFormValues>
  name: DeductionName
  label: string
}) {
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel className="text-xs">{label}</FormLabel>
          <FormControl>
            <Input
              type="number"
              step="0.01"
              min="0"
              className="h-8 text-right text-xs"
              {...field}
              value={field.value ?? ''}
              onChange={(event) => field.onChange(toAmountOrNull(event.target.value))}
            />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}

/** Optional deductions that reduce the net payable (spec 0267 D-6): withholding tax and ENASARCO charged to the company. */
export function PurchaseInvoiceEditorWithholdings({ control }: { control: Control<PurchaseInvoiceWriteFormValues> }) {
  const { t } = useTranslation()

  return (
    <section aria-labelledby="purchase-invoice-editor-withholdings" className="grid gap-2 rounded-lg border bg-card p-3">
      <h3 id="purchase-invoice-editor-withholdings" className="text-sm font-semibold">
        {t('purchaseInvoices.editor.sections.withholdings')}
      </h3>
      <div className="grid gap-3 sm:grid-cols-2">
        <DeductionField control={control} name="withholding_amount" label={t('purchaseInvoices.editor.fields.withholdingAmount')} />
        <DeductionField control={control} name="enasarco_amount" label={t('purchaseInvoices.editor.fields.enasarcoAmount')} />
      </div>
      <p className="text-xs text-muted-foreground">{t('purchaseInvoices.editor.withholdingsHint')}</p>
    </section>
  )
}
