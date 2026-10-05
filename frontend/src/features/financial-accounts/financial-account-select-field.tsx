import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import type { FinancialAccountFormValues } from '@/features/financial-accounts/financial-account-schema'

/** Form keys holding a closed set of values, edited through a select. */
export type SelectFieldName = 'type' | 'card_type' | 'card_circuit'

export interface SelectOption {
  value: string
  label: string
}

interface FinancialAccountSelectFieldProps {
  control: Control<FinancialAccountFormValues>
  name: SelectFieldName
  label: string
  options: readonly SelectOption[]
  placeholder?: string
  required?: boolean
  /** Locks the control regardless of field permissions (e.g. the immutable type on edit). */
  forceDisabled?: boolean
  /** Fired after the user picks a value, with the new value. */
  onValueChange?: (value: string) => void
}

/** Metadata-aware closed-set select shared by the type, card type and circuit fields. */
export function FinancialAccountSelectField({
  control,
  name,
  label,
  options,
  placeholder,
  required,
  forceDisabled = false,
  onValueChange,
}: FinancialAccountSelectFieldProps) {
  return (
    <MetaField control={control} name={name} metaKey={name} label={label} required={required}>
      {({ field, disabled }) => (
        <Select
          value={field.value}
          onValueChange={(next) => {
            field.onChange(next)
            onValueChange?.(next)
          }}
          disabled={disabled || forceDisabled}
        >
          <FormControl>
            <SelectTrigger className="w-full">
              <SelectValue placeholder={placeholder} />
            </SelectTrigger>
          </FormControl>
          <SelectContent>
            {options.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </MetaField>
  )
}
