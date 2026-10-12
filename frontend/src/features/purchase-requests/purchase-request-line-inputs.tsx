import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import type { ForSelectItem } from '@/features/for-select/types'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'
import { UNITS_OF_MEASURE_FOR_SELECT_RESOURCE } from '@/features/units-of-measure/for-select-api'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'

type Control_ = Control<PurchaseRequestFormValues>

/** `meta.rate` of `GET /vat-rates/for-select`: the percentage as decimal string. */
interface VatRateItem extends ForSelectItem {
  meta?: { rate: string | null }
}

interface LineFieldBase {
  control: Control_
  index: number
  disabled: boolean
}

function toNumber(raw: string): number {
  return raw === '' ? Number.NaN : Number(raw)
}

interface LineTextFieldProps extends LineFieldBase {
  label: string
}

/** Description of a line; the label is the accessible name only (the column header names it on desktop). */
export function LineTextField({ control, index, disabled, label }: LineTextFieldProps) {
  return (
    <FormField
      control={control}
      name={`lines.${index}.description`}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormControl>
            <Input className="h-7 text-xs" aria-label={label} placeholder={label} disabled={disabled} {...field} />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}

interface LineNumberFieldProps extends LineFieldBase {
  name: 'quantity' | 'unit_price'
  label: string
  /** Short hint shown in the empty box (the phone card has no column header). */
  placeholder: string
}

/** Numeric input of a line (quantity or unit price); NaN stands for an empty box. */
export function LineNumberField({ control, index, disabled, name, label, placeholder }: LineNumberFieldProps) {
  return (
    <FormField
      control={control}
      name={`lines.${index}.${name}`}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormControl>
            <Input
              type="number"
              step="any"
              className="h-7 text-right text-xs"
              aria-label={label}
              placeholder={placeholder}
              disabled={disabled}
              {...field}
              value={Number.isNaN(field.value) ? '' : field.value}
              onChange={(event) => field.onChange(toNumber(event.target.value))}
            />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}

interface LineSelectLabels {
  placeholder: string
  triggerLabel: string
}

/** Compact trigger of the line pickers (one-band row, spec 0209 D-9). */
const COMPACT_SELECT = 'h-7 min-h-7 px-2 text-xs'

function useSelectLabels({ placeholder, triggerLabel }: LineSelectLabels) {
  const { t } = useTranslation()
  return {
    placeholder,
    searchPlaceholder: t('purchaseRequests.select.search'),
    empty: t('purchaseRequests.select.empty'),
    error: t('purchaseRequests.select.error'),
    clearLabel: t('common.clear'),
    triggerLabel,
    retry: t('common.retry'),
  }
}

interface LineProductSelectProps extends LineFieldBase {
  label: string
  /** Fired after a pick or a quick-create so the row can propose the product's defaults (D-13). */
  onProductPicked: (productId: number) => void
}

/** Product picker with the "+" quick-create; the product is optional (free description otherwise). */
export function LineProductSelect({ control, index, disabled, label, onProductPicked }: LineProductSelectProps) {
  const { t } = useTranslation()
  const { renderAction, selectedItemFor } = useQuickCreateAction(PRODUCTS_FOR_SELECT_RESOURCE)
  const labels = useSelectLabels({ placeholder: t('purchaseRequests.lines.productPlaceholder'), triggerLabel: label })
  return (
    <FormField
      control={control}
      name={`lines.${index}.product_id`}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormControl>
            <AsyncPaginatedSelect
              resource={PRODUCTS_FOR_SELECT_RESOURCE}
              value={field.value}
              onChange={field.onChange}
              selectedItem={selectedItemFor(field.value)}
              onItemChange={(item) => {
                if (item) onProductPicked(item.id)
              }}
              className={COMPACT_SELECT}
              disabled={disabled}
              labels={labels}
              action={renderAction((ref) => {
                field.onChange(ref.id)
                onProductPicked(ref.id)
              }, disabled)}
            />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}

interface LineUnitSelectProps extends LineFieldBase {
  label: string
}

export function LineUnitSelect({ control, index, disabled, label }: LineUnitSelectProps) {
  const { t } = useTranslation()
  const labels = useSelectLabels({ placeholder: t('purchaseRequests.lines.unitPlaceholder'), triggerLabel: label })
  return (
    <FormField
      control={control}
      name={`lines.${index}.unit_of_measure_id`}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormControl>
            <AsyncPaginatedSelect
              resource={UNITS_OF_MEASURE_FOR_SELECT_RESOURCE}
              value={field.value}
              onChange={field.onChange}
              className={COMPACT_SELECT}
              disabled={disabled}
              labels={labels}
            />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}

interface LineVatSelectProps extends LineFieldBase {
  label: string
  /** Mirrors the picked rate's percentage into the row (the preview needs it, the form sends the id). */
  onRateChange: (ratePercent: number | null) => void
}

export function LineVatSelect({ control, index, disabled, label, onRateChange }: LineVatSelectProps) {
  const { t } = useTranslation()
  const labels = useSelectLabels({ placeholder: t('purchaseRequests.lines.vatPlaceholder'), triggerLabel: label })
  return (
    <FormField
      control={control}
      name={`lines.${index}.vat_rate_id`}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormControl>
            <AsyncPaginatedSelect
              resource={VAT_RATES_FOR_SELECT_RESOURCE}
              value={field.value}
              onChange={field.onChange}
              onItemChange={(item) => {
                const rate = (item as VatRateItem | null)?.meta?.rate
                onRateChange(item && rate != null ? Number(rate) : null)
              }}
              className={COMPACT_SELECT}
              disabled={disabled}
              labels={labels}
            />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}
