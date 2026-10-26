import { useTranslation } from 'react-i18next'
import type { Control, FieldPathByValue, FieldValues } from 'react-hook-form'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'

interface BaseProps {
  /** Resource segment of the for-select endpoint (`referents`, `companies`, ...). */
  resource: string
  label: string
  placeholder: string
  required?: boolean
  /** The row around it already shows the label (an in-place record row): keep it for assistive tech only. */
  hideLabel?: boolean
  /** Shows the "+" quick-create next to the picker: data-entry fields only, never a criteria/filter picker. */
  quickCreate?: boolean
}

interface SingleProps<TFieldValues extends FieldValues> extends BaseProps {
  control: Control<TFieldValues>
  name: FieldPathByValue<TFieldValues, number | null>
}

interface MultiProps<TFieldValues extends FieldValues> extends BaseProps {
  control: Control<TFieldValues>
  name: FieldPathByValue<TFieldValues, number[]>
}

/**
 * Single for-select picker bound to a nullable id. No quick-create "+" unless
 * `quickCreate` is set: the settlement-run pickers FILTER what gets settled,
 * so they must not create records.
 */
export function ForSelectFormField<TFieldValues extends FieldValues>({
  control,
  name,
  resource,
  label,
  placeholder,
  required = false,
  hideLabel = false,
  quickCreate = false,
}: SingleProps<TFieldValues>) {
  const { t } = useTranslation()
  const { renderAction, selectedItemFor } = useQuickCreateAction(resource)
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel required={required} className={hideLabel ? 'sr-only' : 'text-xs'}>
            {label}
          </FormLabel>
          <FormControl>
            <AsyncPaginatedSelect
              resource={resource}
              value={field.value as number | null}
              onChange={field.onChange}
              selectedItem={selectedItemFor(field.value as number | null)}
              labels={{
                placeholder,
                searchPlaceholder: t('commissionSettlements.picker.search'),
                empty: t('commissionSettlements.picker.empty'),
                error: t('commissionSettlements.picker.error'),
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

/** Multi for-select picker bound to an id array (same opt-in quick-create rationale as the single one). */
export function ForSelectMultiFormField<TFieldValues extends FieldValues>({
  control,
  name,
  resource,
  label,
  placeholder,
  hideLabel = false,
  quickCreate = false,
}: MultiProps<TFieldValues>) {
  const { t } = useTranslation()
  const { quickCreated, renderAction } = useQuickCreateAction(resource)
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel className={hideLabel ? 'sr-only' : 'text-xs'}>{label}</FormLabel>
          <FormControl>
            <AsyncPaginatedMultiSelect
              resource={resource}
              value={field.value as number[]}
              onChange={field.onChange}
              selectedItems={quickCreated.map((ref) => ({ id: ref.id, label: ref.name }))}
              labels={{
                placeholder,
                searchPlaceholder: t('commissionSettlements.picker.search'),
                empty: t('commissionSettlements.picker.empty'),
                error: t('commissionSettlements.picker.error'),
                removeLabel: t('common.remove'),
                triggerLabel: label,
                retry: t('common.retry'),
              }}
              action={
                quickCreate
                  ? renderAction((ref) => {
                      const current = field.value as number[]
                      if (!current.includes(ref.id)) {
                        field.onChange([...current, ref.id])
                      }
                    })
                  : undefined
              }
            />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}
