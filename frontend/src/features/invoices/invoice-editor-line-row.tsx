import type { Control } from 'react-hook-form'
import { Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import type { ForSelectItem } from '@/features/for-select/types'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import { toPreviewLine, type VatRefs, type InvoiceLineValues } from '@/features/invoices/invoice-editor-lines'
import { computeLineAmounts, fromCents } from '@/features/invoices/invoice-amounts'
import { idToSelectValue, NO_ID } from '@/features/invoices/invoice-editor-source'
import type { InvoiceWriteFormValues } from '@/features/invoices/invoice-schema'
import type { VatRateRef } from '@/features/invoices/types'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'

/** `meta.rate` of `GET /vat-rates/for-select`: the percentage as decimal string. */
interface VatRateForSelectItem extends ForSelectItem {
  meta?: { rate: string | null }
}

/** Column template shared by the row and the lines header (lg and up; stacked below). */
export const LINE_GRID_CLASS = 'lg:grid-cols-[minmax(0,1fr)_5rem_7rem_9rem_6rem_6rem_1.5rem] lg:items-start'

interface InvoiceEditorLineRowProps {
  index: number
  control: Control<InvoiceWriteFormValues>
  line: InvoiceLineValues
  vatRefs: VatRefs
  onRememberVat: (ref: VatRateRef) => void
  onRemove: () => void
}

function toNumber(raw: string): number {
  return raw === '' ? Number.NaN : Number(raw)
}

/** One editable document row: description, qty, unit price, VAT and the read-only computed amounts. */
export function InvoiceEditorLineRow({ index, control, line, vatRefs, onRememberVat, onRemove }: InvoiceEditorLineRowProps) {
  const { t } = useTranslation()
  const n = index + 1
  const amounts = computeLineAmounts(toPreviewLine(line, vatRefs))
  const vatRef = vatRefs[line.vat_rate_id]
  const vatItem: ForSelectItem | null = vatRef ? { id: vatRef.id, label: vatRef.name } : null

  return (
    <li className={`grid gap-2 rounded-md border bg-background p-2 ${LINE_GRID_CLASS}`}>
      <FormField
        control={control}
        name={`lines.${index}.description`}
        render={({ field }) => (
          <FormItem className="gap-1">
            <FormLabel className="text-xs lg:sr-only">{t('invoiceEditor.lines.description')} {n}</FormLabel>
            <FormControl>
              <Input className="h-8 text-xs" {...field} />
            </FormControl>
            <FormMessage className="text-xs" />
          </FormItem>
        )}
      />
      <FormField
        control={control}
        name={`lines.${index}.quantity`}
        render={({ field }) => (
          <FormItem className="gap-1">
            <FormLabel className="text-xs lg:sr-only">{t('invoiceEditor.lines.quantity')} {n}</FormLabel>
            <FormControl>
              <Input
                type="number"
                step="any"
                className="h-8 text-right text-xs"
                {...field}
                value={Number.isNaN(field.value) ? '' : field.value}
                onChange={(event) => field.onChange(toNumber(event.target.value))}
              />
            </FormControl>
            <FormMessage className="text-xs" />
          </FormItem>
        )}
      />
      <FormField
        control={control}
        name={`lines.${index}.unit_price`}
        render={({ field }) => (
          <FormItem className="gap-1">
            <FormLabel className="text-xs lg:sr-only">{t('invoiceEditor.lines.unitPrice')} {n}</FormLabel>
            <FormControl>
              <Input
                type="number"
                step="any"
                className="h-8 text-right text-xs"
                {...field}
                value={Number.isNaN(field.value) ? '' : field.value}
                onChange={(event) => field.onChange(toNumber(event.target.value))}
              />
            </FormControl>
            <FormMessage className="text-xs" />
          </FormItem>
        )}
      />
      <FormField
        control={control}
        name={`lines.${index}.vat_rate_id`}
        render={({ field }) => (
          <FormItem className="gap-1">
            <FormLabel className="text-xs lg:sr-only">{t('invoiceEditor.lines.vatRate')} {n}</FormLabel>
            <FormControl>
              <AsyncPaginatedSelect
                resource={VAT_RATES_FOR_SELECT_RESOURCE}
                value={idToSelectValue(field.value)}
                onChange={(id) => field.onChange(id ?? NO_ID)}
                onItemChange={(item) => {
                  const rate = (item as VatRateForSelectItem | null)?.meta?.rate
                  if (item && rate != null) {
                    onRememberVat({ id: item.id, name: item.label, rate })
                  }
                }}
                selectedItem={vatItem}
                labels={{
                  placeholder: t('invoiceEditor.lines.selectVatRate'),
                  searchPlaceholder: t('invoiceEditor.fields.searchPlaceholder'),
                  empty: t('invoiceEditor.fields.selectEmpty'),
                  error: t('invoiceEditor.fields.selectError'),
                  clearLabel: t('common.clear'),
                  triggerLabel: `${t('invoiceEditor.lines.vatRate')} ${n}`,
                  retry: t('common.retry'),
                }}
              />
            </FormControl>
            <FormMessage className="text-xs" />
          </FormItem>
        )}
      />
      <dl className="grid grid-cols-2 gap-x-2 text-xs lg:contents">
        <div className="flex justify-between lg:block lg:pt-1.5 lg:text-right">
          <dt className="text-muted-foreground lg:sr-only">{t('invoiceEditor.lines.net')}</dt>
          <dd className="tabular-nums">{formatQuoteAmount(fromCents(amounts.net))}</dd>
        </div>
        <div className="flex justify-between lg:block lg:pt-1.5 lg:text-right">
          <dt className="text-muted-foreground lg:sr-only">{t('invoiceEditor.lines.total')}</dt>
          <dd className="font-medium tabular-nums">{formatQuoteAmount(fromCents(amounts.total))}</dd>
        </div>
      </dl>
      <Button
        type="button"
        variant="ghost"
        size="icon-xs"
        className="justify-self-end lg:mt-1"
        aria-label={`${t('invoiceEditor.lines.remove')} ${n}`}
        onClick={onRemove}
      >
        <Trash2 />
      </Button>
    </li>
  )
}
