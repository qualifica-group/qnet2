import { useTranslation } from 'react-i18next'
import { useFormContext } from 'react-hook-form'
import { AsyncPaginatedSelect, type AsyncPaginatedSelectLabels } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import type { StockMovementFormValues } from '@/features/stock/stock-movement-schema'

/** for-select resources behind the pickers (ADR 0011: protected by `auth:sanctum` alone). */
const PRODUCTS_RESOURCE = 'products'
const SITES_RESOURCE = 'operational-sites'
const USERS_RESOURCE = 'users'

/** Only products that accept movements (spec 0307 D-9): the for-select `usage` filter, spec 0142. */
const TRACKED_PRODUCTS_PARAMS = { usage: 'INTERNAL' }

type PickerName = 'product_id' | 'operational_site_id' | 'to_operational_site_id' | 'user_id'
type PickerKind = 'product' | 'site' | 'user'

const RESOURCE_OF: Record<PickerKind, string> = {
  product: PRODUCTS_RESOURCE,
  site: SITES_RESOURCE,
  user: USERS_RESOURCE,
}

interface StockPickerProps {
  name: PickerName
  kind: PickerKind
  label: string
  required?: boolean
}

/** One for-select picker bound to a form field, with the accessible error triad from `FormControl`/`FormMessage`. */
export function StockPicker({ name, kind, label, required = false }: StockPickerProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<StockMovementFormValues>()

  const labels: AsyncPaginatedSelectLabels = {
    placeholder: t(`stock.form.pickers.${kind}.placeholder`),
    searchPlaceholder: t(`stock.form.pickers.${kind}.search`),
    empty: t(`stock.form.pickers.${kind}.empty`),
    error: t(`stock.form.pickers.${kind}.error`),
    clearLabel: t('common.clear'),
    triggerLabel: label,
    retry: t('common.retry'),
  }

  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel required={required}>{label}</FormLabel>
          <FormControl>
            <AsyncPaginatedSelect
              resource={RESOURCE_OF[kind]}
              value={field.value}
              onChange={field.onChange}
              labels={labels}
              showAvatar={kind === 'user'}
              params={kind === 'product' ? TRACKED_PRODUCTS_PARAMS : undefined}
            />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}
