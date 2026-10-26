import type { ReactNode } from 'react'
import { useWatch, type Control } from 'react-hook-form'
import { Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { costTypeOptionRenderer } from '@/features/cost-types/cost-type-option-renderer'
import { COST_TYPES_FOR_SELECT_RESOURCE } from '@/features/cost-types/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'
import { fromCents, type AmountsCents } from '@/lib/amounts/line-amounts'
import { formatEuro } from '@/features/invoices/invoice-format'
import { cn } from '@/lib/utils'
import type { VatRateRef } from '@/features/invoices/types'
import { idToSelectValue, NO_ID } from '@/features/purchase-invoices/purchase-invoice-editor-lines'
import type { PurchaseInvoiceWriteFormValues } from '@/features/purchase-invoices/purchase-invoice-schema'
import { WORK_ORDERS_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'

/** `meta.rate` of `GET /vat-rates/for-select`: the percentage as decimal string. */
interface VatRateForSelectItem extends ForSelectItem {
  meta?: { rate: string | null }
}

/** Quantities carry 3 decimals (ODA rows). */
const QUANTITY_STEP = '0.001'

interface PurchaseInvoiceEditorLineRowProps {
  index: number
  control: Control<PurchaseInvoiceWriteFormValues>
  amounts: AmountsCents
  onRememberVat: (ref: VatRateRef) => void
  /** Absent on a record (spec 0263): the line is deleted from its saved row, Annulla drops a new one. */
  onRemove?: () => void
  /** Full-width band under the fields (the record's Annulla/Salva). */
  footer?: ReactNode
  className?: string
}

function toNumber(raw: string): number {
  return raw === '' ? Number.NaN : Number(raw)
}

type SelectName = 'vat_rate_id' | 'cost_type_id' | 'work_order_id'

interface LineSelectProps {
  control: Control<PurchaseInvoiceWriteFormValues>
  index: number
  name: SelectName
  resource: string
  label: string
  placeholder: string
  required?: boolean
  renderItem?: (item: ForSelectItem) => ReactNode
  onItemChange?: (item: ForSelectItem | null) => void
  /** Shows the "+" quick-create; off for the VAT rate, whose amounts need the rate the create result does not carry. */
  quickCreate?: boolean
}

/** Compact for-select picker of one id of a row, with the accessible error triad via FormControl. */
function LineSelect({ control, index, name, resource, label, placeholder, required, renderItem, onItemChange, quickCreate = false }: LineSelectProps) {
  const { t } = useTranslation()
  const { renderAction, selectedItemFor } = useQuickCreateAction(resource)
  return (
    <FormField
      control={control}
      name={`lines.${index}.${name}`}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel required={required} className="text-xs">
            {label}
          </FormLabel>
          <FormControl>
            <AsyncPaginatedSelect
              resource={resource}
              value={idToSelectValue(field.value ?? NO_ID)}
              onChange={(id) => field.onChange(id ?? (required ? NO_ID : null))}
              onItemChange={onItemChange}
              selectedItem={selectedItemFor(idToSelectValue(field.value ?? NO_ID))}
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
              action={quickCreate ? renderAction((ref) => field.onChange(ref.id)) : undefined}
            />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}

interface NumberFieldProps {
  control: Control<PurchaseInvoiceWriteFormValues>
  index: number
  name: 'quantity' | 'unit_price'
  label: string
  step: string
  /** Compact note under the input (e.g. the billable maximum). */
  hint?: string
}

function LineNumberField({ control, index, name, label, step, hint }: NumberFieldProps) {
  return (
    <FormField
      control={control}
      name={`lines.${index}.${name}`}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel className="text-xs">{label}</FormLabel>
          <FormControl>
            <Input
              type="number"
              step={step}
              className="h-8 text-right text-xs"
              {...field}
              value={Number.isNaN(field.value) ? '' : field.value}
              onChange={(event) => field.onChange(toNumber(event.target.value))}
            />
          </FormControl>
          {hint ? <p className="text-right text-xs text-muted-foreground">{hint}</p> : null}
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}

/** One editable document row: description, qty, price, VAT, cost type, work order and the read-only amounts. */
export function PurchaseInvoiceEditorLineRow({
  index,
  control,
  amounts,
  onRememberVat,
  onRemove,
  footer,
  className,
}: PurchaseInvoiceEditorLineRowProps) {
  const { t } = useTranslation()
  const n = index + 1
  const label = (key: string) => `${t(`purchaseInvoices.editor.lines.${key}`)} ${n}`
  const availableQuantity = useWatch({ control, name: `lines.${index}.available_quantity` })

  return (
    <li className={cn('grid gap-2 rounded-md border bg-background p-2', className)}>
      <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_6rem_7rem_11rem]">
        <FormField
          control={control}
          name={`lines.${index}.description`}
          render={({ field }) => (
            <FormItem className="gap-1 sm:col-span-2 lg:col-span-1">
              <FormLabel required className="text-xs">
                {label('description')}
              </FormLabel>
              <FormControl>
                <Input className="h-8 text-xs" {...field} />
              </FormControl>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
        <LineNumberField
          control={control}
          index={index}
          name="quantity"
          label={label('quantity')}
          step={QUANTITY_STEP}
          hint={
            availableQuantity === null || availableQuantity === undefined
              ? undefined
              : t('purchaseInvoices.editor.lines.maxQuantity', { max: availableQuantity })
          }
        />
        <LineNumberField control={control} index={index} name="unit_price" label={label('unitPrice')} step="any" />
        <LineSelect
          control={control}
          index={index}
          name="vat_rate_id"
          resource={VAT_RATES_FOR_SELECT_RESOURCE}
          label={label('vatRate')}
          placeholder={t('purchaseInvoices.editor.lines.noVat')}
          onItemChange={(item) => {
            const rate = (item as VatRateForSelectItem | null)?.meta?.rate
            if (item && rate != null) {
              onRememberVat({ id: item.id, name: item.label, rate })
            }
          }}
        />
      </div>
      <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_6rem_6rem_2rem] lg:items-end">
        <LineSelect
          control={control}
          index={index}
          name="cost_type_id"
          resource={COST_TYPES_FOR_SELECT_RESOURCE}
          label={label('costType')}
          placeholder={t('purchaseInvoices.editor.lines.selectCostType')}
          required
          quickCreate
          renderItem={costTypeOptionRenderer}
        />
        <LineSelect
          control={control}
          index={index}
          name="work_order_id"
          resource={WORK_ORDERS_FOR_SELECT_RESOURCE}
          label={label('workOrder')}
          placeholder={t('purchaseInvoices.editor.lines.selectWorkOrder')}
          quickCreate
        />
        <dl className="grid grid-cols-2 gap-x-2 text-xs sm:col-span-2 lg:contents">
          <div className="flex justify-between lg:block lg:text-right">
            <dt className="text-muted-foreground">{t('purchaseInvoices.editor.lines.net')}</dt>
            <dd className="tabular-nums">{formatEuro(fromCents(amounts.net))}</dd>
          </div>
          <div className="flex justify-between lg:block lg:text-right">
            <dt className="text-muted-foreground">{t('purchaseInvoices.editor.lines.total')}</dt>
            <dd className="font-medium tabular-nums">{formatEuro(fromCents(amounts.total))}</dd>
          </div>
        </dl>
        {onRemove ? (
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            className="justify-self-end"
            aria-label={label('remove')}
            onClick={onRemove}
          >
            <Trash2 />
          </Button>
        ) : null}
      </div>
      {footer}
    </li>
  )
}
