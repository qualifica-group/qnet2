import type { ReactNode } from 'react'
import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'
import type { PurchaseOrderFormValues } from '@/features/purchase-orders/purchase-order-schema'

type Control_ = Control<PurchaseOrderFormValues>

/** Compact trigger of the line pickers (one-band row, as the RDA lines of spec 0209 D-9). */
const COMPACT_SELECT = 'h-7 min-h-7 px-2 text-xs'

interface LineFieldBase {
  control: Control_
  index: number
  disabled: boolean
  /** Accessible name (the column header names it on desktop only). */
  label: string
}

function toNumber(raw: string): number {
  return raw === '' ? Number.NaN : Number(raw)
}

function useSelectLabels(placeholder: string, triggerLabel: string) {
  const { t } = useTranslation()
  return {
    placeholder,
    searchPlaceholder: t('purchaseOrderForm.select.search'),
    empty: t('purchaseOrderForm.select.empty'),
    error: t('purchaseOrderForm.select.error'),
    clearLabel: t('common.clear'),
    triggerLabel,
    retry: t('common.retry'),
  }
}

/** Description of a line. */
export function LineDescriptionField({ control, index, disabled, label }: LineFieldBase) {
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
  placeholder: string
}

/** Numeric input of a line (quantity or unit price); NaN stands for an empty box. */
export function LineNumberField({ control, index, disabled, label, name, placeholder }: LineNumberFieldProps) {
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

interface LineRelationSelectProps extends LineFieldBase {
  name: 'cost_type_id' | 'unit_of_measure_id' | 'vat_rate_id' | 'work_order_id'
  resource: string
  placeholder: string
  onItemChange?: (item: ForSelectItem | null) => void
  /** Custom option/trigger content (e.g. the colored cost type badge). */
  renderItem?: (item: ForSelectItem) => ReactNode
}

/** Compact for-select picker of one id of a line (cost type, U.m., VAT rate, work order). */
export function LineRelationSelect({
  control,
  index,
  disabled,
  label,
  name,
  resource,
  placeholder,
  onItemChange,
  renderItem,
}: LineRelationSelectProps) {
  const labels = useSelectLabels(placeholder, label)
  return (
    <FormField
      control={control}
      name={`lines.${index}.${name}`}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormControl>
            <AsyncPaginatedSelect
              resource={resource}
              value={field.value}
              onChange={field.onChange}
              onItemChange={onItemChange}
              renderItem={renderItem}
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

interface LineProductSelectProps extends LineFieldBase {
  /** Fired after a pick or a quick-create so the row can propose the product's defaults (D-13). */
  onProductPicked: (item: ForSelectItem | null) => void
}

/** Product picker with the "+" quick-create; the product is optional (free description otherwise). */
export function LineProductSelect({ control, index, disabled, label, onProductPicked }: LineProductSelectProps) {
  const { t } = useTranslation()
  const { renderAction, selectedItemFor } = useQuickCreateAction(PRODUCTS_FOR_SELECT_RESOURCE)
  const labels = useSelectLabels(t('purchaseOrderForm.lines.productPlaceholder'), label)
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
              onItemChange={onProductPicked}
              className={COMPACT_SELECT}
              disabled={disabled}
              labels={labels}
              action={renderAction((ref) => {
                field.onChange(ref.id)
                onProductPicked({ id: ref.id, label: ref.name })
              }, disabled)}
            />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}
