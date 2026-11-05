import { useTranslation } from 'react-i18next'
import { useFormContext } from 'react-hook-form'
import { AsyncPaginatedSelect, type AsyncPaginatedSelectLabels } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import type { ForSelectItem } from '@/features/for-select/types'
import type { TreasuryMovementFormValues } from '@/features/treasury-movements/treasury-movement-schema'

type PickerName = 'financial_account_id' | 'counterpart_account_id' | 'registry_id' | 'work_order_id'

const RESOURCE_OF: Record<PickerName, string> = {
  financial_account_id: 'financial-accounts',
  counterpart_account_id: 'financial-accounts',
  registry_id: 'registries',
  work_order_id: 'work-orders',
}

/** i18n key segment of each picker. */
const KEY_OF: Record<PickerName, string> = {
  financial_account_id: 'account',
  counterpart_account_id: 'counterpart',
  registry_id: 'registry',
  work_order_id: 'workOrder',
}

interface TreasuryPickerProps {
  name: PickerName
  required?: boolean
  disabled?: boolean
  /** Hydrates the trigger label of a saved value that is not on the first page. */
  selectedItem?: ForSelectItem | null
  params?: Record<string, number>
  isItemDisabled?: (item: ForSelectItem) => boolean
  /** Fired after the user changed the value (e.g. to reset a dependent field). */
  onPicked?: () => void
}

/** One for-select picker bound to a form field; the accessible error triad comes from `FormControl`/`FormMessage`. */
export function TreasuryPicker({
  name,
  required = false,
  disabled = false,
  selectedItem,
  params,
  isItemDisabled,
  onPicked,
}: TreasuryPickerProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<TreasuryMovementFormValues>()
  const label = t(`treasuryMovements.form.${KEY_OF[name]}`)
  const labels: AsyncPaginatedSelectLabels = {
    placeholder: t('treasuryMovements.form.pick'),
    searchPlaceholder: t('treasuryMovements.form.search'),
    empty: t('treasuryMovements.form.noResults'),
    error: t('treasuryMovements.form.loadError'),
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
              resource={RESOURCE_OF[name]}
              value={field.value}
              onChange={(next) => {
                field.onChange(next)
                onPicked?.()
              }}
              selectedItem={selectedItem}
              labels={labels}
              disabled={disabled}
              params={params}
              isItemDisabled={isItemDisabled}
            />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}
