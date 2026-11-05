import type { Control, FieldPath, FieldPathValue, FieldValues } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { InheritedRevenueTypeNote } from '@/features/revenue-types/effective-revenue-type'
import { RevenueTypeSelect } from '@/features/revenue-types/revenue-type-select'
import type { EffectiveRevenueType, RevenueTypeRef } from '@/features/revenue-types/types'

/** Field paths whose value is a nullable id. */
type NullableIdPath<TFieldValues extends FieldValues> = {
  [K in FieldPath<TFieldValues>]: FieldPathValue<TFieldValues, K> extends number | null | undefined ? K : never
}[FieldPath<TFieldValues>]

interface RevenueTypeFieldProps<TFieldValues extends FieldValues, TName extends NullableIdPath<TFieldValues>> {
  control: Control<TFieldValues>
  name: TName
  /** Authorization metadata key of the field. */
  metaKey: string
  label: string
  /** The saved ref of the current value (hydrates the trigger without a lookup). */
  selected?: Pick<RevenueTypeRef, 'id' | 'code' | 'name'> | null
  /** What the record uses when the field is empty; shown as "inherited from category X". */
  effective?: EffectiveRevenueType | null
  /** Hides the inheritance note (a record that has nothing to inherit from). */
  showInheritance?: boolean
}

/** Form row for a revenue sub-account: the shared picker inside `MetaField`, plus the inherited value while empty. */
export function RevenueTypeField<TFieldValues extends FieldValues, TName extends NullableIdPath<TFieldValues>>({
  control,
  name,
  metaKey,
  label,
  selected = null,
  effective = null,
  showInheritance = true,
}: RevenueTypeFieldProps<TFieldValues, TName>) {
  return (
    <MetaField control={control} name={name} metaKey={metaKey} label={label}>
      {({ field, disabled }) => (
        <div className="flex min-w-0 flex-col gap-1">
          <FormControl>
            <RevenueTypeSelect
              value={(field.value as number | null | undefined) ?? null}
              onChange={(next) => field.onChange(next as FieldPathValue<TFieldValues, TName>)}
              selected={selected}
              disabled={disabled}
              aria-label={label}
            />
          </FormControl>
          {showInheritance && field.value == null ? <InheritedRevenueTypeNote effective={effective} /> : null}
        </div>
      )}
    </MetaField>
  )
}
