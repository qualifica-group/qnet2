import { useTranslation } from 'react-i18next'
import type { Control, FieldPathByValue, FieldValues } from 'react-hook-form'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'

interface BaseProps {
  /** Resource segment of the for-select endpoint (`referents`, `companies`, ...). */
  resource: string
  label: string
  placeholder: string
  required?: boolean
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
 * Single for-select picker bound to a nullable id, without the quick-create
 * action of `RelationSelectField`: used by dialogs that only FILTER or pick
 * existing agents/companies (settlement runs, adjustments).
 */
export function ForSelectFormField<TFieldValues extends FieldValues>({
  control,
  name,
  resource,
  label,
  placeholder,
  required = false,
}: SingleProps<TFieldValues>) {
  const { t } = useTranslation()
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel required={required} className="text-xs">
            {label}
          </FormLabel>
          <FormControl>
            <AsyncPaginatedSelect
              resource={resource}
              value={field.value as number | null}
              onChange={field.onChange}
              labels={{
                placeholder,
                searchPlaceholder: t('commissionSettlements.picker.search'),
                empty: t('commissionSettlements.picker.empty'),
                error: t('commissionSettlements.picker.error'),
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

/** Multi for-select picker bound to an id array (same no-quick-create rationale as the single one). */
export function ForSelectMultiFormField<TFieldValues extends FieldValues>({
  control,
  name,
  resource,
  label,
  placeholder,
}: MultiProps<TFieldValues>) {
  const { t } = useTranslation()
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel className="text-xs">{label}</FormLabel>
          <FormControl>
            <AsyncPaginatedMultiSelect
              resource={resource}
              value={field.value as number[]}
              onChange={field.onChange}
              labels={{
                placeholder,
                searchPlaceholder: t('commissionSettlements.picker.search'),
                empty: t('commissionSettlements.picker.empty'),
                error: t('commissionSettlements.picker.error'),
                removeLabel: t('common.remove'),
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
